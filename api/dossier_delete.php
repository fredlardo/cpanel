<?php
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth.php";
requireLogin();
$id=$_GET["id"]??"";
if($id){
  db()->prepare("UPDATE dossiers SET deletedAt=NOW() WHERE id=?")->execute([$id]);
  db()->prepare("INSERT INTO action_logs (id,action,description,dossierId,userId) VALUES (?,?,?,?,?)")->execute([cuid(),"SUPPRESSION","Mis en corbeille",$id, currentUser()["id"]]);
}
redirect(BASE_URL."/dashboard/dossiers/index.php");
