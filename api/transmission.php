<?php
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth.php";
requireLogin();
if($_SERVER["REQUEST_METHOD"]==="POST"){
  $dossierId=$_POST["dossierId"]??"";
  $comment=trim($_POST["comment"]??"");
  if($dossierId && $comment){
    $pdo=db();
    $pdo->prepare("INSERT INTO transmissions (id,dossierId,senderId,comment) VALUES (?,?,?,?)")->execute([cuid(),$dossierId, currentUser()["id"], $comment]);
    $pdo->prepare("INSERT INTO action_logs (id,action,description,dossierId,userId) VALUES (?,?,?,?,?)")->execute([cuid(),"TRANSMISSION",$comment,$dossierId, currentUser()["id"]]);
    // notif : informe le créateur si pas l'expéditeur + participants recent
    try{
      $info=$pdo->prepare("SELECT reference,title,creatorId FROM dossiers WHERE id=?"); $info->execute([$dossierId]); $dInfo=$info->fetch();
      $senderId=currentUser()["id"]; $senderName=currentUser()["name"]??currentUser()["login"];
      $targets=[];
      if($dInfo && $dInfo['creatorId'] && $dInfo['creatorId']!==$senderId) $targets[]=$dInfo['creatorId'];
      // participants des transmissions précédentes
      $q=$pdo->prepare("SELECT DISTINCT senderId as uid FROM transmissions WHERE dossierId=? AND senderId!=? UNION SELECT DISTINCT receiverId as uid FROM transmissions WHERE dossierId=? AND receiverId IS NOT NULL AND receiverId!=?");
      $q->execute([$dossierId,$senderId,$dossierId,$senderId]);
      foreach($q->fetchAll(PDO::FETCH_COLUMN) as $uid){ if($uid && !in_array($uid,$targets,true)) $targets[]=$uid; }
      foreach($targets as $uid){
        $pdo->prepare("INSERT INTO notifications (id,type,title,message,dossierId,userId) VALUES (?,?,?,?,?,?)")->execute([cuid(),'TRANSMISSION', "Nouveau commentaire — ".mb_substr(($dInfo['reference']??'').' '.($dInfo['title']??''),0,60), $senderName." : ".mb_substr($comment,0,140), $dossierId, $uid]);
      }
    }catch(Exception $e){}
  }
  header("Location: ".BASE_URL."/dashboard/dossiers/view.php?id=".$dossierId);
  exit;
}
