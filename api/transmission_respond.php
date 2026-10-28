<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/functions.php';
requireLogin();
$pdo=db();
$uid=currentUser()['id'];
$tid=$_POST['transmissionId'] ?? $_POST['id'] ?? '';
$action=$_POST['action'] ?? '';
$reason=trim($_POST['rejectReason'] ?? $_POST['reason'] ?? '');
if(!$tid || !in_array($action,['accept','reject'],true)) { http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Paramètres invalides']); exit; }
$st=$pdo->prepare("SELECT * FROM transmissions WHERE id=? AND (receiverId=? OR targetServiceId IN (SELECT serviceId FROM user_service_access WHERE userId=? UNION SELECT serviceId FROM users WHERE id=?))");
$st->execute([$tid,$uid,$uid,$uid]);
$t=$st->fetch();
if(!$t){ http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'Transmission introuvable ou non destinée à vous/service']); exit; }
// si transmission service, on affecte le receiver à celui qui accepte
if(empty($t['receiverId']) && !empty($t['targetServiceId'])){
  $pdo->prepare("UPDATE transmissions SET receiverId=? WHERE id=?")->execute([$uid,$tid]);
  $t['receiverId']=$uid;
}
if($t['status']==='ACCEPTED' || $t['status']==='REJECTED'){ echo json_encode(['ok'=>false,'msg'=>'Déjà répondu']); exit; }
if($action==='accept'){
  $pdo->prepare("UPDATE transmissions SET status='ACCEPTED', respondedAt=NOW(), updatedAt=NOW() WHERE id=?")->execute([$tid]);
  $pdo->prepare("UPDATE dossiers SET currentHolderId=?, status='EN_COURS', updatedAt=NOW() WHERE id=?")->execute([$uid,$t['dossierId']]);
  logAction($pdo,$t['dossierId'],'TRANSMISSION_ACCEPTEE',"Accepté par ".currentUser()['name']." — EN_ATTENTE → EN_COURS, reste chez lui jusqu’à finalisation");
  // notif sender
  try{
    $d=$pdo->prepare("SELECT reference,title FROM dossiers WHERE id=?"); $d->execute([$t['dossierId']]); $dr=$d->fetch();
    $title="Dossier accepté : ".mb_substr(($dr['reference']??'')." ".($dr['title']??''),0,60);
    $pdo->prepare("INSERT INTO notifications (id,type,title,message,dossierId,userId) VALUES (?,?,?,?,?,?)")->execute([cuid(),'TRANSMISSION',$title, currentUser()['name']." a accepté le dossier", $t['dossierId'], $t['senderId']]);
  }catch(Exception $e){}
  echo json_encode(['ok'=>true,'msg'=>'Dossier accepté — il reste chez vous jusqu’à finalisation']);
} else {
  if($reason===''){ http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Motif de rejet obligatoire']); exit; }
  $pdo->prepare("UPDATE transmissions SET status='REJECTED', rejectReason=?, respondedAt=NOW(), updatedAt=NOW() WHERE id=?")->execute([$reason,$tid]);
  $pdo->prepare("UPDATE dossiers SET status='REJETE', updatedAt=NOW() WHERE id=?")->execute([$t['dossierId']]);
  logAction($pdo,$t['dossierId'],'TRANSMISSION_REJETEE',"Refusé par ".currentUser()['name']." — $reason → REJETE");
  try{
    $d=$pdo->prepare("SELECT reference,title FROM dossiers WHERE id=?"); $d->execute([$t['dossierId']]); $dr=$d->fetch();
    $title="Dossier rejeté : ".mb_substr(($dr['reference']??'')." ".($dr['title']??''),0,60);
    $pdo->prepare("INSERT INTO notifications (id,type,title,message,dossierId,userId) VALUES (?,?,?,?,?,?)")->execute([cuid(),'TRANSMISSION',$title, currentUser()['name']." a rejeté : $reason", $t['dossierId'], $t['senderId']]);
  }catch(Exception $e){}
  echo json_encode(['ok'=>true,'msg'=>'Dossier rejeté — retourné à l’envoyeur']);
}
