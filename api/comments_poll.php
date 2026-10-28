<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/auth.php';
requireLogin();
$pdo=db();
$id=$_GET['id']??'';
if(!$id){ http_response_code(400); echo json_encode(['error'=>'id manquant']); exit; }
$stmt=$pdo->prepare("SELECT c.id,c.content,c.createdAt,u.firstName,u.lastName,u.id as userId FROM dossier_comments c LEFT JOIN users u ON u.id=c.userId WHERE c.dossierId=? ORDER BY c.createdAt ASC");
$stmt->execute([$id]);
$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: application/json');
echo json_encode($rows);
