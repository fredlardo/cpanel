<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/auth.php';
requireLogin();
$pdo=db(); $u=currentUser();
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['title'])){
  $dossierId=$_POST['dossierId']??''; $title=trim($_POST['title']??''); $assignee=$_POST['assigneeId']??null; $due=$_POST['dueDate']??null;
  if(!$dossierId||!$title){ http_response_code(400); echo 'Titre requis'; exit; }
  $pdo->prepare("INSERT INTO dossier_tasks (id,dossierId,creatorId,assigneeId,title,dueDate) VALUES (?,?,?,?,?,?)")->execute([cuid(),$dossierId,$u['id'],$assignee?:null,$title,$due?date('Y-m-d H:i:s',strtotime($due)):null]);
  header('Location: '.BASE_URL.'/dashboard/dossiers/view.php?id='.urlencode($dossierId).'&tab=collab#tasks'); exit;
}
if(isset($_GET['done'])){ $pdo->prepare("UPDATE dossier_tasks SET status='TERMINE' WHERE id=?")->execute([$_GET['done']]); $ref=$_SERVER['HTTP_REFERER']??BASE_URL.'/dashboard/dossiers/view.php?id='.($_GET['id']??''); if(strpos($ref,'tab=')===false) $ref.=(strpos($ref,'?')===false?'?':'&').'tab=collab'; header('Location: '.$ref.'#tasks'); exit; }
if(isset($_GET['del'])){ $pdo->prepare("DELETE FROM dossier_tasks WHERE id=?")->execute([$_GET['del']]); $ref=$_SERVER['HTTP_REFERER']??BASE_URL.'/dashboard/dossiers/view.php?id='.($_GET['id']??''); if(strpos($ref,'tab=')===false) $ref.=(strpos($ref,'?')===false?'?':'&').'tab=collab'; header('Location: '.$ref.'#tasks'); exit; }
