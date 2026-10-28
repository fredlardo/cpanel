<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/auth.php';
requireLogin();
header('Content-Type: application/json');
$pdo=db(); $u=currentUser();
$dossierId=$_POST['dossierId']??null; $documentId=$_POST['documentId']??null;
$expires=$_POST['expiresAt']??null; $password=$_POST['password']??null; $allowDownload=isset($_POST['allowDownload'])?(int)$_POST['allowDownload']:1;
if(!$dossierId && !$documentId){ echo json_encode(['error'=>'dossierId ou documentId requis']); exit; }
$token=bin2hex(random_bytes(16)); $id=cuid();
$expiresAt=$expires?date('Y-m-d H:i:s', strtotime($expires)):null;
$pwdHash=$password?password_hash($password,PASSWORD_BCRYPT):null;
$pdo->prepare("INSERT INTO share_links (id,token,dossierId,documentId,password,expiresAt,allowDownload,createdById) VALUES (?,?,?,?,?,?,?,?)")->execute([$id,$token,$dossierId?:null,$documentId?:null,$pwdHash,$expiresAt,$allowDownload,$u['id']]);
echo json_encode(['success'=>true,'token'=>$token,'url'=>BASE_URL.'/share.php?token='.$token]);
