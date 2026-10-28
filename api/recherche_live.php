<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');
if(!isLoggedIn()){ http_response_code(401); echo json_encode(['error'=>'auth']); exit; }
$pdo=db();
$q=trim($_GET['q']??'');
$cat=$_GET['cat']??''; $genre=$_GET['genre']??''; $prio=$_GET['prio']??''; $status=$_GET['status']??'';
$tag=$_GET['tag']??''; $from=$_GET['from']??''; $to=$_GET['to']??'';
if($q==='' && !$cat && !$genre && !$prio && !$status && !$tag && !$from && !$to){
  echo json_encode(['total'=>0,'rows'=>[]]); exit;
}
if($q!=='' && mb_strlen($q)<2){
  echo json_encode(['total'=>0,'rows'=>[]]); exit;
}
$where="d.deletedAt IS NULL"; $params=[];
$conds=[]; $likeParams=[];
if($q!==''){
  $like="%$q%";
  $conds[]="(d.title LIKE ? OR d.reference LIKE ? OR d.description LIKE ? OR EXISTS (SELECT 1 FROM documents doc WHERE doc.dossierId=d.id AND doc.ocrText LIKE ?))";
  $likeParams=[$like,$like,$like,$like];
}
if($cat){ $where.=" AND d.categoryId=?"; $params[]=$cat; }
if($genre){ $where.=" AND d.genreId=?"; $params[]=$genre; }
if($prio){ $where.=" AND d.priorityId=?"; $params[]=$prio; }
if($status){ $where.=" AND d.status=?"; $params[]=$status; }
if($from){ $where.=" AND d.createdAt>=?"; $params[]=$from; }
if($to){ $where.=" AND d.createdAt<=?"; $params[]=$to.' 23:59:59'; }
if(!empty($conds)){
  $where.=" AND (".implode(" AND ",$conds).")";
  $params=array_merge($params,$likeParams);
}
$joinTag="";
if($tag){ $joinTag=" JOIN dossier_tags dt ON dt.dossierId=d.id AND dt.tagId=:tagId "; }
$sql="SELECT d.id,d.reference,d.title,d.status,d.createdAt,c.name as catName,p.name as prioName,p.color as prioColor FROM dossiers d LEFT JOIN categories c ON c.id=d.categoryId LEFT JOIN priorities p ON p.id=d.priorityId $joinTag WHERE $where ORDER BY d.createdAt DESC LIMIT 50";
// handle tag param separately if using named param would mix; use positional for tag too
if($tag){
  // need to inject tagId as first positional? Simpler: replace :tagId with ?
  $sql=str_replace(":tagId","?",$sql);
  array_unshift($params,$tag); // actually tag join position before where params? Move to front for correctness with our params order: we used named, now positional first
  // Our $params currently has where params; tag should be first
}
$stmt=$pdo->prepare($sql);
$stmt->execute($params);
$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
// badge helper
foreach($rows as &$r){
  $r['badge']=statusBadge($r['status']);
  $r['date']=date('d/m/Y',strtotime($r['createdAt']));
}
echo json_encode(['total'=>count($rows),'rows'=>$rows]);
