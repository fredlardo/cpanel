<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');
$pdo=db();
// Auth via Bearer api_key (token = raw, stored hash = sha256)
function authApi(PDO $pdo): ?array {
  $hdr=$_SERVER['HTTP_AUTHORIZATION']??''; if(preg_match('/Bearer\s+(.+)/i',$hdr,$m)) $tok=trim($m[1]); else $tok=$_GET['token']??'';
  if(!$tok) return null;
  $h=hash('sha256',$tok);
  $s=$pdo->prepare("SELECT ak.*, e.name as entityName FROM api_keys ak JOIN entities e ON e.id=ak.entityId WHERE ak.token_hash=? LIMIT 1"); $s->execute([$h]);
  $row=$s->fetch(); if($row){ $pdo->prepare("UPDATE api_keys SET last_used_at=NOW() WHERE id=?")->execute([$row['id']]); }
  return $row?:null;
}
$method=$_SERVER['REQUEST_METHOD']; $path=$_GET['path']??'';
if($path==='health'){ echo json_encode(['ok'=>true,'version'=>APP_VERSION]); exit; }
$ak=authApi($pdo); if(!$ak){ http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'Bearer token requis']); exit; }
require_once __DIR__ . '/../includes/licence.php';
$st=licenceStatus($pdo,$ak['entityId']); if(in_array($st['status'],['SUSPENDED','REVOKED','EXPIRED','INVALID'])){ http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'Licence '.$st['status']]); exit; }

if($method==='GET' && $path==='dossiers'){
  $q=$_GET['q']??''; $status=$_GET['status']??'';
  $sql="SELECT d.id,d.reference,d.title,d.status,d.createdAt,s.name as serviceName FROM dossiers d JOIN services s ON s.id=d.serviceId JOIN divisions dv ON dv.id=s.divisionId JOIN organizations o ON o.id=dv.organizationId WHERE o.entityId=? AND d.deletedAt IS NULL";
  $par=[$ak['entityId']]; if($q){ $sql.=" AND (d.title LIKE ? OR d.reference LIKE ? OR d.description LIKE ?)"; $par[]="%$q%"; $par[]="%$q%"; $par[]="%$q%"; } if($status) { $sql.=" AND d.status=?"; $par[]=$status; }
  $sql.=" ORDER BY d.createdAt DESC LIMIT 100";
  $s=$pdo->prepare($sql); $s->execute($par); echo json_encode(['ok'=>true,'data'=>$s->fetchAll()]); exit;
}
if($method==='POST' && $path==='dossiers'){
  $body=json_decode(file_get_contents('php://input'), true)?:$_POST;
  $title=trim($body['title']??''); $serviceId=$body['serviceId']??''; if(!$title||!$serviceId){ http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'title+serviceId requis']); exit; }
  // quota check
  $lic=getEntityLicence($pdo,$ak['entityId']); if($lic){ $cnt=$pdo->prepare("SELECT COUNT(*) FROM dossiers d JOIN services s ON s.id=d.serviceId JOIN divisions dv ON dv.id=s.divisionId JOIN organizations o ON o.id=dv.organizationId WHERE o.entityId=? AND d.deletedAt IS NULL"); $cnt->execute([$ak['entityId']]); if((int)$cnt->fetchColumn() >= (int)$lic['max_dossiers']){ http_response_code(429); echo json_encode(['ok'=>false,'msg'=>'Quota dossiers']); exit; } }
  $id=cuid(); require_once __DIR__ . '/../includes/functions.php'; if(!function_exists('generateReference')) require_once __DIR__ . '/../config/app.php';
  $ref='DOX-'.date('Y').'-'.str_pad(substr($id,-4),4,'0',STR_PAD_LEFT);
  $pdo->prepare("INSERT INTO dossiers (id,title,description,reference,status,serviceId,categoryId,genreId,dossierTypeId,priorityId,confidentialityId,creatorId) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")->execute([$id,$title,$body['description']??'', $ref,'NOUVEAU',$serviceId, $body['categoryId']??$pdo->query("SELECT id FROM categories LIMIT 1")->fetchColumn(), $body['genreId']??$pdo->query("SELECT id FROM genres LIMIT 1")->fetchColumn(), $body['dossierTypeId']??$pdo->query("SELECT id FROM dossier_types LIMIT 1")->fetchColumn(), $body['priorityId']??$pdo->query("SELECT id FROM priorities LIMIT 1")->fetchColumn(), $body['confidentialityId']??$pdo->query("SELECT id FROM confidentiality_levels LIMIT 1")->fetchColumn(), $ak['id']]);
  // webhook
  try{ $whs=$pdo->prepare("SELECT * FROM webhooks WHERE entityId=? AND isActive=1"); $whs->execute([$ak['entityId']]); foreach($whs->fetchAll() as $wh){ $payload=json_encode(['event'=>'dossier.created','dossierId'=>$id,'reference'=>$ref]); @file_get_contents($wh['url'], false, stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/json\r\nX-Webhook-Secret: ".$wh['secret'],'content'=>$payload,'timeout'=>2]])); } }catch(Exception $e){}
  echo json_encode(['ok'=>true,'id'=>$id,'reference'=>$ref]); exit;
}
http_response_code(404); echo json_encode(['ok'=>false,'msg'=>'route inconnue']);
