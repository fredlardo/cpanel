<?php
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../config/database.php";
header("Content-Type: application/json");
$q=trim($_GET["q"]??"");
if(strlen($q)<2){ echo json_encode([]); exit; }
$stmt=db()->prepare("SELECT id,reference,title FROM dossiers WHERE deletedAt IS NULL AND (title LIKE ? OR reference LIKE ?) LIMIT 10");
$stmt->execute(["%".$q."%","%".$q."%"]);
echo json_encode($stmt->fetchAll());
