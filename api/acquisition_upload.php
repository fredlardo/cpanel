<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/auth.php';
requireLogin();
header('Content-Type: application/json');
$pdo=db(); $u=currentUser();
if($_SERVER['REQUEST_METHOD']!=='POST'){ http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }
$source=$_POST['source']??'dragdrop'; $dossierId=$_POST['dossierId']??null;
if(empty($_FILES['files'])){ echo json_encode(['error'=>'Aucun fichier']); exit; }
$out=[];
foreach($_FILES['files']['name'] as $i=>$name){
  $tmp=$_FILES['files']['tmp_name'][$i];
  if(!$tmp||$_FILES['files']['error'][$i]!==0) continue;
  $orig=basename($name);
  $ext=strtolower(pathinfo($orig,PATHINFO_EXTENSION));
  $dir=__DIR__.'/../uploads/acquisition/'.date('Y-m-d').'/';
  if(!is_dir($dir)) mkdir($dir,0775,true);
  $fname=cuid().'_'.preg_replace('/[^a-zA-Z0-9._-]/','_',$orig);
  if(move_uploaded_file($tmp,$dir.$fname)){
    $aid=cuid();
    $pdo->prepare("INSERT INTO acquisition_imports (id,source,fileName,status,dossierId,userId) VALUES (?,?,?,?,?,?)")->execute([$aid,$source,$orig,'EN_ATTENTE',$dossierId?:null,$u['id']]);
    // if dossierId provided also add document
    if($dossierId){
      $did=cuid();
      $pdo->prepare("INSERT INTO documents (id,fileName,originalName,mimeType,fileSize,filePath,dossierId,uploadedById) VALUES (?,?,?,?,?,?,?,?)")->execute([$did,$fname,$orig, mime_content_type($dir.$fname)?:'application/octet-stream', filesize($dir.$fname), 'uploads/acquisition/'.date('Y-m-d').'/'.$fname, $dossierId,$u['id']]);
      $pdo->prepare("UPDATE acquisition_imports SET status='TRAITE' WHERE id=?")->execute([$aid]);
    }
    $out[]=['name'=>$orig,'id'=>$aid,'stored'=>$fname];
  }
}
echo json_encode(['success'=>true,'files'=>$out]);
