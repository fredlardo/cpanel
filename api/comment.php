<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/auth.php';
requireLogin();
$pdo=db(); $u=currentUser();
if($_SERVER['REQUEST_METHOD']!=='POST'){ http_response_code(405); exit; }
$dossierId=$_POST['dossierId']??''; $content=trim($_POST['content']??''); $parentId=$_POST['parentId']??null;
if(!$dossierId||!$content){ http_response_code(400); echo 'Données manquantes'; exit; }
$id=cuid();
$pdo->prepare("INSERT INTO dossier_comments (id,dossierId,userId,parentId,content) VALUES (?,?,?,?,?)")->execute([$id,$dossierId,$u['id'],$parentId?:null,$content]);
$pdo->prepare("INSERT INTO action_logs (id,action,description,dossierId,userId) VALUES (?,?,?,?,?)")->execute([cuid(),'COMMENTAIRE', mb_substr($content,0,120), $dossierId,$u['id']]);
header('Location: '.BASE_URL.'/dashboard/dossiers/view.php?id='.urlencode($dossierId).'&tab=collab#comments');
exit;
