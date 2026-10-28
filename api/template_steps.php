<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/auth.php';
requireLogin();
$pdo=db();
$tpl=$_GET['tpl']??'';
if(!$tpl){ echo json_encode([]); exit; }
$st=$pdo->prepare("SELECT * FROM dossier_template_steps WHERE templateId=? ORDER BY `order` ASC");
$st->execute([$tpl]);
echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
