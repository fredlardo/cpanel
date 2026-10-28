<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/functions.php';
requireLogin();
$pdo=db();
$uid=currentUser()['id'];
$dossierId=$_POST['dossierId'] ?? $_POST['id'] ?? '';
if(!$dossierId){ http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'ID manquant']); exit; }
$d=$pdo->prepare("SELECT * FROM dossiers WHERE id=?"); $d->execute([$dossierId]); $row=$d->fetch();
if(!$row){ http_response_code(404); echo json_encode(['ok'=>false,'msg'=>'Dossier introuvable']); exit; }
if($row['currentHolderId']!==$uid){ http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'Seul le détenteur actuel peut finaliser']); exit; }
// retour au propriétaire (creatorId) sinon sender origin
$owner=$row['creatorId'] ?: $row['currentHolderId'];
$pdo->prepare("UPDATE dossiers SET currentHolderId=?, status='EN_COURS', updatedAt=NOW() WHERE id=?")->execute([$owner,$dossierId]);
// marquer transmissions en cours comme FINALISEE pour disparition
$pdo->prepare("UPDATE transmissions SET status='FINALISEE', updatedAt=NOW() WHERE dossierId=? AND status IN ('ACCEPTED','PENDING')")->execute([$dossierId]);
logAction($pdo,$dossierId,'TRAITEMENT_FINALISE',"Traitement terminé par ".currentUser()['name']." — retour au propriétaire avec tous les traitements");
try{
  $title="Traitement terminé : ".mb_substr(($row['reference']??'')." ".($row['title']??''),0,60);
  $pdo->prepare("INSERT INTO notifications (id,type,title,message,dossierId,userId) VALUES (?,?,?,?,?,?)")->execute([cuid(),'TRAITEMENT',$title, currentUser()['name']." a finalisé — dossier retourné chez le propriétaire", $dossierId, $owner]);
}catch(Exception $e){}
echo json_encode(['ok'=>true,'msg'=>'Traitement déclaré terminé — dossier retourné au propriétaire']);
