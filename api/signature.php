<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin(); $pdo=db();
$act=$_POST['act']??$_GET['act']??'';
if($act==='sign' && $_SERVER['REQUEST_METHOD']==='POST'){
  $dossierId=$_POST['dossierId']??''; $docId=$_POST['documentId']??null; $data=$_POST['signature_data']??'';
  if(!$dossierId||!$data) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>'Données manquantes']); exit; }
  $hash=hash('sha256', $data.$dossierId.currentUser()['id'].time());
  $id=cuid();
  $pdo->prepare("INSERT INTO dossier_signatures (id,dossierId,documentId,signerId,signature_data,hash) VALUES (?,?,?,?,?,?)")->execute([$id,$dossierId,$docId?:null,currentUser()['id'],$data,$hash]);
  // also create archiving proof for document if provided
  if($docId){
    $doc=$pdo->prepare("SELECT filePath FROM documents WHERE id=?"); $doc->execute([$docId]); $fp=$doc->fetchColumn();
    if($fp){ $full=__DIR__.'/../'.$fp; if(file_exists($full)){ $sha=hash_file('sha256',$full); $pdo->prepare("INSERT INTO archiving_proofs (id,dossierId,documentId,sha256) VALUES (?,?,?,?)")->execute([cuid(),$dossierId,$docId,$sha]); } }
  }
  require_once __DIR__ . '/../includes/functions.php';
  logAction($pdo,$dossierId,'SIGNATURE', 'Signé par '.currentUser()['name'].' hash '.$hash);
  header('Content-Type: application/json'); echo json_encode(['ok'=>true,'hash'=>$hash]); exit;
}
if($act==='list' && isset($_GET['dossierId'])){
  $s=$pdo->prepare("SELECT ds.*, u.firstName,u.lastName FROM dossier_signatures ds JOIN users u ON u.id=ds.signerId WHERE ds.dossierId=? ORDER BY ds.createdAt DESC"); $s->execute([$_GET['dossierId']]);
  header('Content-Type: application/json'); echo json_encode($s->fetchAll()); exit;
}
header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>'act invalide']);
