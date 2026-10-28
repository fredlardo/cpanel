<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/auth.php';
requireLogin();
$pdo=db(); $u=currentUser();
if($_SERVER['REQUEST_METHOD']!=='POST'){ http_response_code(405); exit; }
$documentId=$_POST['documentId']??''; $dossierId=$_POST['dossierId']??'';
if(!$documentId || empty($_FILES['file']['name'])){ http_response_code(400); echo 'Fichier requis'; exit; }
$stmt=$pdo->prepare("SELECT * FROM documents WHERE id=?"); $stmt->execute([$documentId]); $doc=$stmt->fetch(); if(!$doc){ http_response_code(404); exit; }
// save old version
$pdo->prepare("INSERT INTO document_versions (id,documentId,version,fileName,filePath,fileSize,uploadedById) VALUES (?,?,?,?,?,?,?)")->execute([cuid(),$documentId,(int)$doc['version'],$doc['fileName'],$doc['filePath'],(int)$doc['fileSize'],$doc['uploadedById']]);
// new file
$orig=basename($_FILES['file']['name']); $dir=__DIR__.'/../uploads/'.($dossierId?:$doc['dossierId']).'/'; if(!is_dir($dir)) mkdir($dir,0775,true);
$fname=cuid().'_'.preg_replace('/[^a-zA-Z0-9._-]/','_',$orig);
move_uploaded_file($_FILES['file']['tmp_name'],$dir.$fname);
$newVer=(int)$doc['version']+1;
$pdo->prepare("UPDATE documents SET fileName=?,originalName=?,mimeType=?,fileSize=?,filePath=?,version=? WHERE id=?")->execute([$fname,$orig, mime_content_type($dir.$fname)?:$doc['mimeType'], filesize($dir.$fname), 'uploads/'.($dossierId?:$doc['dossierId']).'/'.$fname, $newVer, $documentId]);
$pdo->prepare("INSERT INTO action_logs (id,action,description,dossierId,userId) VALUES (?,?,?,?,?)")->execute([cuid(),'VERSION_DOCUMENT',"v$doc[version]→v$newVer $orig", $dossierId?:$doc['dossierId'], $u['id']]);
header('Location: '.BASE_URL.'/dashboard/dossiers/view.php?id='.urlencode($dossierId?:$doc['dossierId']).'&tab=docs#docs');
