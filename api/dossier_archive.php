<?php
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
requireLogin();
$id=$_GET["id"]??"";
if($id){
  db()->prepare("UPDATE dossiers SET archivedAt=NOW(), status='CLOS' WHERE id=?")->execute([$id]);
  try{ logAction(db(),$id,"ARCHIVAGE","Dossier archivé"); }catch(Exception $e){}
}
redirect(BASE_URL."/dashboard/dossiers/view.php?id=".$id);
