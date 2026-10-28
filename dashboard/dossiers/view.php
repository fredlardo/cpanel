<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
$pdo=db();
$id=$_GET['id']??''; if(!$id) die('ID manquant');
$tabPost = $_POST['tab'] ?? $_GET['tab'] ?? null;
$isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') || (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') || isset($_POST['ajax']);
function ajaxOrRedirect(bool $isAjax, string $url, array $payload=[]): void {
  if($isAjax){ header('Content-Type: application/json; charset=utf-8'); echo json_encode(array_merge(['ok'=>true,'redirect'=>$url],$payload)); exit; }
  redirect($url);
}
$dEarly=null; try{ $tmp=$pdo->prepare("SELECT reference,title FROM dossiers WHERE id=?"); $tmp->execute([$id]); $dEarly=$tmp->fetch(); }catch(Exception $e){}
// --- POST handlers AVANT toute sortie HTML (évite headers already sent) ---
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_status'])){
  $newStatus=$_POST['status']??'EN_COURS'; $closureComment=trim($_POST['closureComment']??''); $deadline=$_POST['newDeadline']??'';
  $sets="status=?"; $params=[$newStatus];
  if($newStatus==='CLOS'){ $sets.=", closureComment=?"; $params[]=$closureComment?:null; }
  if($deadline){ $sets.=", deadline=?"; $params[]=$deadline; }
  $params[]=$id;
  $pdo->prepare("UPDATE dossiers SET $sets, updatedAt=NOW() WHERE id=?")->execute($params);
  logAction($pdo,$id,'CHANGEMENT_STATUT', "Statut: $newStatus".($closureComment?" — $closureComment":'').($deadline?" (échéance $deadline)":''));
  $_SESSION['flash_success']="Statut mis à jour : $newStatus";
  $rt = $tabPost ?: 'infos';
  ajaxOrRedirect($isAjax, BASE_URL."/dashboard/dossiers/view.php?id=$id&tab=".urlencode($rt), ['msg'=>$_SESSION['flash_success']]);
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['quick_action'])){
  $act=$_POST['quick_action'];
  $map=['instruire'=>'EN_COURS','attente'=>'EN_ATTENTE','clore'=>'CLOS','rejeter'=>'REJETE','annuler'=>'ANNULE','reouvrir'=>'NOUVEAU'];
  $ns=$map[$act]??'EN_COURS';
  $comment=trim($_POST['quick_comment']??'');
  $pdo->prepare("UPDATE dossiers SET status=?, closureComment=?, updatedAt=NOW() WHERE id=?")->execute([$ns, $act==='clore'?$comment:null, $id]);
  logAction($pdo,$id,'TRAITEMENT_'.strtoupper($act), $comment?:("Action: $act → $ns"));
  $_SESSION['flash_success']="Action effectuée : $act ($ns)";
  $rt = $tabPost ?: 'infos';
  ajaxOrRedirect($isAjax, BASE_URL."/dashboard/dossiers/view.php?id=$id&tab=".urlencode($rt), ['msg'=>$_SESSION['flash_success']]);
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['actionType'])){
  $actionType=$_POST['actionType']; $comment=trim($_POST['actionComment']??''); $receiverId=$_POST['receiverId']??null; $targetServiceId=$_POST['targetServiceId']??null;
  if($receiverId==='') $receiverId=null; if($targetServiceId==='') $targetServiceId=null;
  $statusMap=['PRENDRE_EN_CHARGE'=>'EN_COURS','SUSPENDRE'=>'EN_ATTENTE','REJETER'=>'REJETE','VALIDER'=>'EN_ATTENTE','VISER'=>'EN_ATTENTE','APPROUVER'=>'EN_ATTENTE','ACQUITTER'=>'EN_ATTENTE','RAPPEL'=>'EN_ATTENTE'];
  $newStatus=$statusMap[$actionType]??null;
  if($newStatus){ $pdo->prepare("UPDATE dossiers SET status=?, updatedAt=NOW() WHERE id=?")->execute([$newStatus,$id]); }
  $desc=$comment ?: $actionType;
  if($receiverId){ $u=$pdo->prepare("SELECT firstName,lastName FROM users WHERE id=?"); $u->execute([$receiverId]); $ru=$u->fetch(); if($ru) $desc.=" → ".trim($ru['firstName'].' '.$ru['lastName']); }
  if($targetServiceId){ $s=$pdo->prepare("SELECT name FROM services WHERE id=?"); $s->execute([$targetServiceId]); $sn=$s->fetchColumn(); if($sn) $desc.=" (service: $sn)"; }
  logAction($pdo,$id,$actionType,$desc);
  if($receiverId || $targetServiceId){
    try{ $pdo->prepare("INSERT INTO transmissions (id,dossierId,senderId,receiverId,targetServiceId,comment,status,createdAt) VALUES (?,?,?,?,?,?,?,NOW())")->execute([cuid(),$id,currentUser()['id'],$receiverId,$targetServiceId,$comment,$receiverId?'PENDING':null]); }catch(Exception $e){}
    // notifications pour envoi/transfert
    try{
      $recipients=[];
      if($receiverId) $recipients[]=$receiverId;
      if($targetServiceId){
        $q=$pdo->prepare("SELECT u.id FROM users u WHERE u.isActive=1 AND u.id!=? AND (u.serviceId=? OR u.id IN (SELECT userId FROM user_service_access WHERE serviceId=?))");
        $q->execute([currentUser()['id'],$targetServiceId,$targetServiceId]);
        foreach($q->fetchAll(PDO::FETCH_COLUMN) as $uid){ if(!in_array($uid,$recipients,true)) $recipients[]=$uid; }
      }
      $senderName=currentUser()['name']??currentUser()['login'];
      $dTitle=(($dEarly['reference']??$d['reference']??'')).' '.(($dEarly['title']??$d['title']??'')); $short=mb_substr($comment?:$actionType,0,120);
      foreach($recipients as $uid){
        $pdo->prepare("INSERT INTO notifications (id,type,title,message,dossierId,userId) VALUES (?,?,?,?,?,?)")->execute([cuid(),'TRANSMISSION',"Dossier reçu : ".mb_substr($dTitle,0,80), $senderName." — ".$actionType.($short?" : ".$short:""), $id, $uid]);
      }
    }catch(Exception $e){}
  }
  $rt = $tabPost ?: 'infos';
  ajaxOrRedirect($isAjax, BASE_URL."/dashboard/dossiers/view.php?id=$id&tab=".urlencode($rt), ['msg'=>'Traitement envoyé']);
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_org'])){
  $newParent=$_POST['parentId']??null; if($newParent==='') $newParent=null;
  $pdo->prepare("UPDATE dossiers SET parentId=? WHERE id=?")->execute([$newParent,$id]);
  $newTagIds=$_POST['tagIds']??[]; $pdo->prepare("DELETE FROM dossier_tags WHERE dossierId=?")->execute([$id]);
  foreach((array)$newTagIds as $tid){ if($tid) $pdo->prepare("INSERT IGNORE INTO dossier_tags (id,dossierId,tagId) VALUES (?,?,?)")->execute([cuid(),$id,$tid]); }
  logAction($pdo,$id,'MAJ_ORGANISATION','Tags / parent mis à jour'); $rt=$tabPost?:'infos'; ajaxOrRedirect($isAjax, BASE_URL."/dashboard/dossiers/view.php?id=$id&tab=".urlencode($rt), ['msg'=>'Organisation mise à jour']);
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_retention'])){
  $rp=$_POST['retentionPolicyId']??null; if($rp==='') $rp=null; $hold=isset($_POST['legalHold'])?1:0;
  $until=null; if($rp){ $r=$pdo->prepare("SELECT durationDays FROM retention_policies WHERE id=?"); $r->execute([$rp]); $days=$r->fetchColumn(); if($days) $until=date('Y-m-d H:i:s', strtotime("+$days days")); }
  $pdo->prepare("UPDATE dossiers SET retentionPolicyId=?, legalHold=?, retentionUntil=? WHERE id=?")->execute([$rp,$hold,$until,$id]);
  logAction($pdo,$id,'ARCHIVAGE_CONFIG',"Rétention $rp / gel=$hold"); $rt=$tabPost?:'archivage'; ajaxOrRedirect($isAjax, BASE_URL."/dashboard/dossiers/view.php?id=$id&tab=".urlencode($rt), ['msg'=>'Rétention mise à jour']);
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['start_workflow'])){
  $chk=$pdo->prepare("SELECT id FROM dossier_workflows WHERE dossierId=?"); $chk->execute([$id]); $existsWf=$chk->fetch();
  if(!$existsWf){
    $wfId=cuid(); $pdo->prepare("INSERT INTO dossier_workflows (id,name,dossierId,status) VALUES (?,?,?,?)")->execute([$wfId,'Circuit validation standard',$id,'EN_COURS']);
    $steps=[['Instruction',1],['Validation',2],['Visa',3]];
    foreach($steps as $i=>$st){ $pdo->prepare("INSERT INTO dossier_workflow_steps (id,`order`,name,status,workflowId) VALUES (?,?,?,?,?)")->execute([cuid(),$i+1,$st[0], $i===0?'EN_COURS':'EN_ATTENTE',$wfId]); }
    logAction($pdo,$id,'WORKFLOW_START','Circuit démarré'); ajaxOrRedirect($isAjax, BASE_URL."/dashboard/dossiers/view.php?id=$id&tab=workflow#workflow", ['msg'=>'Circuit démarré']);
  }
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['validate_step'])){
  $stepId=$_POST['stepId']??''; $decision=$_POST['decision']??'VALIDE'; $comment=$_POST['comment']??'';
  $pdo->prepare("UPDATE dossier_workflow_steps SET status=?, comment=?, validatedAt=NOW(), validatorId=? WHERE id=?")->execute([$decision,$comment,currentUser()['id'],$stepId]);
  $wfRow=$pdo->prepare("SELECT * FROM dossier_workflows WHERE dossierId=?"); $wfRow->execute([$id]); $wfTmp=$wfRow->fetch();
  if($wfTmp){ $s=$pdo->prepare("SELECT * FROM dossier_workflow_steps WHERE workflowId=? ORDER BY `order` ASC"); $s->execute([$wfTmp['id']]); $all=$s->fetchAll();
    $found=false; foreach($all as $st){ if($st['id']===$stepId){ $found=true; continue; } if($found && $st['status']==='EN_ATTENTE'){ $pdo->prepare("UPDATE dossier_workflow_steps SET status='EN_COURS' WHERE id=?")->execute([$st['id']]); break; } }
    $rem=$pdo->prepare("SELECT COUNT(*) FROM dossier_workflow_steps WHERE workflowId=? AND status NOT IN ('VALIDE','REJETE')"); $rem->execute([$wfTmp['id']]); if((int)$rem->fetchColumn()===0){ $pdo->prepare("UPDATE dossier_workflows SET status='TERMINE', completedAt=NOW() WHERE id=?")->execute([$wfTmp['id']]); }
  }
  logAction($pdo,$id,'WORKFLOW_ETAPE',"$decision $stepId"); ajaxOrRedirect($isAjax, BASE_URL."/dashboard/dossiers/view.php?id=$id&tab=workflow#workflow", ['msg'=>'Étape mise à jour']);
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['upload_doc'])){
  $files=[]; if(isset($_FILES['files'])){ $c=count($_FILES['files']['name']); for($i=0;$i<$c;$i++){ if($_FILES['files']['error'][$i]===UPLOAD_ERR_OK && $_FILES['files']['name'][$i]!=='') $files[]=['name'=>$_FILES['files']['name'][$i],'tmp_name'=>$_FILES['files']['tmp_name'][$i]]; } } elseif(!empty($_FILES['file']['name'])){ $files[]=['name'=>$_FILES['file']['name'],'tmp_name'=>$_FILES['file']['tmp_name']]; }
  if($files){ $dir=__DIR__.'/../../uploads/'.$id.'/'; if(!is_dir($dir)) mkdir($dir,0775,true); $added=[]; foreach($files as $f){ $orig=basename($f['name']); $fname=cuid().'_'.preg_replace('/[^a-zA-Z0-9._-]/','_',$orig); if(move_uploaded_file($f['tmp_name'],$dir.$fname)){ $did=cuid(); $pdo->prepare("INSERT INTO documents (id,fileName,originalName,mimeType,fileSize,filePath,dossierId,uploadedById) VALUES (?,?,?,?,?,?,?,?)")->execute([$did,$fname,$orig,mime_content_type($dir.$fname)?:'application/octet-stream',filesize($dir.$fname),'uploads/'.$id.'/'.$fname,$id,currentUser()['id']]); $added[]=$orig; try{ require_once __DIR__.'/../../includes/ocr.php'; $ocr=ocrExtract($dir.$fname); $pdo->prepare("UPDATE documents SET ocr_text=?,ocr_status=? WHERE id=?")->execute([$ocr['text'],$ocr['status'],$did]); }catch(Exception $e){} } } if($added){ logAction($pdo,$id,'AJOUT_DOCUMENT',count($added).' fichier(s): '.implode(', ',array_slice($added,0,5))); $_SESSION['flash_success']=count($added).' fichier(s) ajouté(s)'; } }
  ajaxOrRedirect($isAjax, BASE_URL."/dashboard/dossiers/view.php?id=$id&tab=docs#docs", ['msg'=>$_SESSION['flash_success']??'Documents ajoutés']);
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['bulk_transfer'])){
  @file_put_contents(__DIR__.'/../../debug_transfer.log', date('c')." bulk_transfer POST ".json_encode($_POST, JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
  $comment=trim($_POST['transfer_comment']??''); $svcIds=$_POST['targetServiceIds']??[]; $recvIds=$_POST['targetReceiverIds']??[]; $deadlineBT=$_POST['transfer_deadline']??'';
  $svcIds=array_filter((array)$svcIds); $recvIds=array_filter((array)$recvIds);
  if(empty($svcIds) && empty($recvIds)){
    ajaxOrRedirect($isAjax, BASE_URL."/dashboard/dossiers/view.php?id=$id&tab=transferts", ['msg'=>'Aucune cible sélectionnée','ok'=>false]);
  }
  $created=0; $allRecipients=[]; $lastErr='';
  foreach((array)$svcIds as $sid){
    try{ $pdo->prepare("INSERT INTO transmissions (id,dossierId,senderId,targetServiceId,comment,status,createdAt) VALUES (?,?,?,?,?,?,NOW())")->execute([cuid(),$id,currentUser()['id'],$sid,$comment,'PENDING']); $created++; }catch(Exception $e){ $lastErr=$e->getMessage(); error_log("svc insert fail: ".$lastErr); }
    try{ $q=$pdo->prepare("SELECT u.id FROM users u WHERE u.isActive=1 AND u.id!=? AND (u.serviceId=? OR u.id IN (SELECT userId FROM user_service_access WHERE serviceId=?))"); $q->execute([currentUser()['id'],$sid,$sid]); foreach($q->fetchAll(PDO::FETCH_COLUMN) as $uid){ if(!in_array($uid,$allRecipients,true)) $allRecipients[]=$uid; } }catch(Exception $e){ $lastErr=$e->getMessage(); }
  }
  foreach((array)$recvIds as $rid){
    try{ $pdo->prepare("INSERT INTO transmissions (id,dossierId,senderId,receiverId,comment,status,createdAt) VALUES (?,?,?,?,?,?,?,NOW())")->execute([cuid(),$id,currentUser()['id'],$rid,$comment,'PENDING']); $created++; if(!in_array($rid,$allRecipients,true)) $allRecipients[]=$rid; }catch(Exception $e){ $lastErr=$e->getMessage(); error_log("recv insert fail: ".$lastErr); }
  }
  if($created===0 && $lastErr){ ajaxOrRedirect($isAjax, BASE_URL."/dashboard/dossiers/view.php?id=$id&tab=transferts", ['ok'=>false,'msg'=>'Erreur transfert: '.$lastErr]); }
  $dTitle=(($dEarly['reference']??$d['reference']??'')).' '.(($dEarly['title']??$d['title']??''));
  // statut: soumis à service/tiers -> EN_ATTENTE (en attente d'être accepté)
  if($created>0){ try{ $pdo->prepare("UPDATE dossiers SET status='EN_ATTENTE', updatedAt=NOW() WHERE id=? AND status IN ('NOUVEAU','EN_COURS')")->execute([$id]); }catch(Exception $e){} }
  $senderName=currentUser()['name']??currentUser()['login'];
  $short=mb_substr($comment?:'Transfert',0,120);
  foreach($allRecipients as $uid){
    try{ $pdo->prepare("INSERT INTO notifications (id,type,title,message,dossierId,userId) VALUES (?,?,?,?,?,?)")->execute([cuid(),'TRANSMISSION',"Dossier transféré : ".mb_substr($dTitle,0,80), $senderName." — Transfert".($short?" : ".$short:""), $id, $uid]); }catch(Exception $e){}
  }
  logAction($pdo,$id,'TRANSFERT_MULTI', ($comment?:'Transfert') . " → ".count($svcIds)." service(s) + ".count($recvIds)." personne(s)" . ($deadlineBT?" (échéance $deadlineBT)":""));
  $_SESSION['flash_success']=$created>0 ? "Transfert effectué : $created cible(s) notifiée(s)" : "Aucune cible";
  ajaxOrRedirect($isAjax, BASE_URL."/dashboard/dossiers/view.php?id=$id&tab=transferts", ['msg'=>$_SESSION['flash_success']]);
}
$pageTitle='Détail dossier';
require_once __DIR__ . '/../../includes/header.php';
$stmt=$pdo->prepare("SELECT d.*, u.firstName,u.lastName,s.name as serviceName,c.name as catName,g.name as genreName,dt.name as typeName,p.name as priorityName,p.color as priorityColor,conf.name as confName, vis.name as visName, parent.reference as parentRef, parent.title as parentTitle, rp.name as retentionName FROM dossiers d LEFT JOIN users u ON u.id=d.creatorId LEFT JOIN services s ON s.id=d.serviceId LEFT JOIN categories c ON c.id=d.categoryId LEFT JOIN genres g ON g.id=d.genreId LEFT JOIN dossier_types dt ON dt.id=d.dossierTypeId LEFT JOIN priorities p ON p.id=d.priorityId LEFT JOIN confidentiality_levels conf ON conf.id=d.confidentialityId LEFT JOIN visibility_levels vis ON vis.id=d.visibilityId LEFT JOIN dossiers parent ON parent.id=d.parentId LEFT JOIN retention_policies rp ON rp.id=d.retentionPolicyId WHERE d.id=?");
$stmt->execute([$id]); $d=$stmt->fetch(); if(!$d) die('Dossier introuvable');
$docs=$pdo->prepare("SELECT * FROM documents WHERE dossierId=? AND deletedAt IS NULL ORDER BY createdAt DESC"); $docs->execute([$id]); $documents=$docs->fetchAll();
// Historique filtres + pagination (10 par page, en bas)
$histPerPage=10; $histPage=max(1,(int)($_GET['hist_page']??1)); $histAction=trim($_GET['hist_action']??''); $histUser=trim($_GET['hist_user']??''); $histQ=trim($_GET['hist_q']??'');
$histWhere="l.dossierId=?"; $histParams=[$id];
if($histAction!==''){ $histWhere.=" AND l.action=?"; $histParams[]=$histAction; }
if($histUser!==''){ $histWhere.=" AND l.userId=?"; $histParams[]=$histUser; }
if($histQ!==''){ $histWhere.=" AND (l.action LIKE ? OR l.description LIKE ?)"; $histParams[]="%$histQ%"; $histParams[]="%$histQ%"; }
$cntStmt=$pdo->prepare("SELECT COUNT(*) FROM action_logs l WHERE $histWhere"); $cntStmt->execute($histParams); $histTotal=(int)$cntStmt->fetchColumn();
$histPages=max(1,(int)ceil($histTotal/$histPerPage)); if($histPage>$histPages) $histPage=$histPages;
$histOffset=($histPage-1)*$histPerPage;
$logs=$pdo->prepare("SELECT l.*, u.firstName,u.lastName FROM action_logs l LEFT JOIN users u ON u.id=l.userId WHERE $histWhere ORDER BY l.createdAt DESC LIMIT $histPerPage OFFSET $histOffset"); $logs->execute($histParams); $actions=$logs->fetchAll();
$histDistinct=$pdo->prepare("SELECT DISTINCT action FROM action_logs WHERE dossierId=? ORDER BY action"); $histDistinct->execute([$id]); $histActionsList=$histDistinct->fetchAll(PDO::FETCH_COLUMN);
$histUsersList=$pdo->prepare("SELECT DISTINCT l.userId, u.firstName,u.lastName FROM action_logs l LEFT JOIN users u ON u.id=l.userId WHERE l.dossierId=? ORDER BY u.firstName"); $histUsersList->execute([$id]); $histUsersRows=$histUsersList->fetchAll();
$trans=$pdo->prepare("SELECT t.*, u.firstName as sfn, u.lastName as sln FROM transmissions t LEFT JOIN users u ON u.id=t.senderId WHERE t.dossierId=? ORDER BY t.createdAt DESC"); $trans->execute([$id]); $transmissions=$trans->fetchAll();
// tags + metadata + comments + tasks + shares + versions + workflow
$tags=$pdo->prepare("SELECT t.* FROM tags t JOIN dossier_tags dt ON dt.tagId=t.id WHERE dt.dossierId=?"); $tags->execute([$id]); $dTags=$tags->fetchAll();
$allTags=$pdo->query("SELECT * FROM tags ORDER BY name")->fetchAll();
$meta=$pdo->prepare("SELECT mf.name,mf.code,dm.value FROM dossier_metadata dm JOIN metadata_fields mf ON mf.id=dm.fieldId WHERE dm.dossierId=?"); $meta->execute([$id]); $metas=$meta->fetchAll();
$comments=$pdo->prepare("SELECT c.*, u.firstName,u.lastName FROM dossier_comments c LEFT JOIN users u ON u.id=c.userId WHERE c.dossierId=? ORDER BY c.createdAt ASC"); $comments->execute([$id]); $coms=$comments->fetchAll();
$tasks=$pdo->prepare("SELECT t.*, u1.firstName as afn, u1.lastName as aln, u2.firstName as cfn, u2.lastName as cln FROM dossier_tasks t LEFT JOIN users u1 ON u1.id=t.assigneeId LEFT JOIN users u2 ON u2.id=t.creatorId WHERE t.dossierId=? ORDER BY t.createdAt DESC"); $tasks->execute([$id]); $taskRows=$tasks->fetchAll();
$shares=$pdo->prepare("SELECT * FROM share_links WHERE dossierId=? OR documentId IN (SELECT id FROM documents WHERE dossierId=?) ORDER BY createdAt DESC"); $shares->execute([$id,$id]); $shareRows=$shares->fetchAll();
$users=$pdo->query("SELECT id,firstName,lastName,serviceId FROM users WHERE isActive=1 ORDER BY firstName")->fetchAll();
$services=$pdo->query("SELECT id,name FROM services WHERE isActive=1 ORDER BY name")->fetchAll();
$retentions=$pdo->query("SELECT * FROM retention_policies WHERE isActive=1 ORDER BY durationDays")->fetchAll();
// workflow
$wf=$pdo->prepare("SELECT * FROM dossier_workflows WHERE dossierId=?"); $wf->execute([$id]); $workflow=$wf->fetch();
$wfSteps=[]; if($workflow){ $s=$pdo->prepare("SELECT ws.*, u.firstName,u.lastName FROM dossier_workflow_steps ws LEFT JOIN users u ON u.id=ws.validatorId WHERE ws.workflowId=? ORDER BY ws.order ASC"); $s->execute([$workflow['id']]); $wfSteps=$s->fetchAll(); }
 $tab=$_GET['tab']??'infos';
$myServices=null; try{ $ms=$pdo->prepare("SELECT serviceId FROM user_service_access WHERE userId=? UNION SELECT serviceId FROM users WHERE id=?"); $ms->execute([currentUser()['id'],currentUser()['id']]); $myServices=array_filter(array_column($ms->fetchAll(), 'serviceId')); }catch(Exception $e){ $myServices=[]; }
$myPending=null; foreach($transmissions as $tr){ if($tr['status']!=='PENDING') continue; if($tr['receiverId']===currentUser()['id']){ $myPending=$tr; break; } if(!empty($tr['targetServiceId']) && in_array($tr['targetServiceId'],$myServices,true) && empty($tr['receiverId'])){ $myPending=$tr; break; } }
$isHolder = $d['currentHolderId']===currentUser()['id'];
?>
<?php if($myPending): ?>
<div class="bg-amber-50 border border-amber-300 rounded-xl p-4 mb-4 flex flex-wrap items-center justify-between gap-3">
  <div class="text-sm"><b>Dossier transféré vers vous</b> — acceptez pour traiter (reste chez vous jusqu’à finalisation) ou refusez avec motif (retour envoyeur → Dossiers rejetés).</div>
  <div class="flex gap-2 items-center">
    <button onclick="respondTrans('accept')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-sm">Accepter</button>
    <button onclick="document.getElementById('rejectBox').classList.toggle('hidden')" class="border bg-white px-4 py-2 rounded-xl text-sm">Refuser…</button>
  </div>
</div>
<div id="rejectBox" class="hidden bg-white border border-red-200 rounded-xl p-3 mb-4">
  <label class="text-xs font-medium">Motif du rejet (obligatoire)</label><textarea id="rejectReason" rows="2" class="input w-full mt-1" placeholder="Expliquez pourquoi vous refusez…"></textarea>
  <button onclick="respondTrans('reject')" class="mt-2 bg-red-600 text-white px-4 py-2 rounded-xl text-sm">Confirmer le rejet</button>
</div>
<script>
function respondTrans(act){
  const id='<?= e($myPending['id']) ?>';
  const fd=new FormData(); fd.append('transmissionId',id); fd.append('action',act);
  if(act==='reject'){ const r=document.getElementById('rejectReason').value.trim(); if(!r){ alert('Motif obligatoire'); return; } fd.append('rejectReason',r); }
  fetch('<?= BASE_URL ?>/api/transmission_respond.php',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(j=>{
    alert(j.msg|| (j.ok?'OK':'Erreur')); if(j.ok) location.reload();
  });
}
</script>
<?php endif; ?>
<?php if($isHolder && !$myPending): ?>
<div class="bg-blue-50 border border-blue-300 rounded-xl p-3 mb-4 flex flex-wrap items-center justify-between gap-3">
  <span class="text-sm text-blue-900">Vous êtes détenteur — dossier chez vous jusqu’à finalisation.</span>
  <button onclick="finalizeDossier()" class="bg-slate-900 text-white px-4 py-2 rounded-xl text-sm">Déclarer traitement terminé — retourner au propriétaire</button>
</div>
<script>function finalizeDossier(){ if(!confirm('Retourner au propriétaire avec tous les traitements ? Disparaîtra de chez vous.')) return; const fd=new FormData(); fd.append('dossierId','<?= e($id) ?>'); fetch('<?= BASE_URL ?>/api/transmission_finalize.php',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(j=>{ alert(j.msg); if(j.ok) location.href='<?= BASE_URL ?>/dashboard/dossiers/index.php'; }); }</script>
<?php endif; ?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
  <div><h1 class="text-xl font-extrabold text-slate-900"><?= e($d['title']) ?> <span class="text-sm font-normal text-slate-400"><?= e($d['reference']) ?></span></h1>
  <p class="text-xs text-slate-500">Créé par <?= e($d['firstName'].' '.$d['lastName']) ?> le <?= date('d/m/Y H:i',strtotime($d['createdAt'])) ?> <?php if($d['parentRef']): ?>• Sous-dossier de <a href="view.php?id=<?= e($d['parentId']) ?>" class="text-blue-600"><?= e($d['parentRef']) ?> — <?= e($d['parentTitle']) ?></a><?php endif; ?></p></div>
  <a href="index.php" class="border px-3 py-1.5 rounded-xl text-sm bg-white">← Retour</a>
</div>
<div class="flex gap-1 mb-4 overflow-x-auto text-sm">
  <?php foreach(['infos'=>'Infos','transferts'=>'Transferts','docs'=>'Documents','collab'=>'Collaboration','workflow'=>'Workflows','securite'=>'Sécurité','archivage'=>'Archivage'] as $k=>$lbl): ?>
    <a href="?id=<?= e($id) ?>&tab=<?= $k ?>" class="px-4 py-2 rounded-xl whitespace-nowrap <?= $tab===$k?'bg-slate-900 text-white':'bg-white border hover:bg-slate-50' ?>"><?= $lbl ?></a>
  <?php endforeach; ?>
</div>
<?php if($tab==='infos'): ?>
<div class="grid lg:grid-cols-3 gap-4">
  <div class="lg:col-span-2 space-y-4">
    <div class="card p-5">
      <h3 class="font-semibold mb-3">Informations</h3>
      <div class="grid grid-cols-2 gap-3 text-sm">
        <div><span class="text-slate-400">Statut</span><div><?= statusBadge($d['status']) ?></div></div>
        <div><span class="text-slate-400">Priorité</span><div><span class="rounded-full px-2 py-0.5 text-xs" style="background:<?= e($d['priorityColor']) ?>20;color:<?= e($d['priorityColor']) ?>"><?= e($d['priorityName']) ?></span></div></div>
        <div><span class="text-slate-400">Service</span><div><?= e($d['serviceName']) ?></div></div>
        <div><span class="text-slate-400">Catégorie / Genre</span><div><?= e($d['catName']) ?> / <?= e($d['genreName']) ?></div></div>
        <div><span class="text-slate-400">Type</span><div><?= e($d['typeName']) ?></div></div>
        <div><span class="text-slate-400">Confidentialité / Visibilité</span><div><?= e($d['confName']) ?> / <?= e($d['visName']?:'—') ?></div></div>
        <div><span class="text-slate-400">Échéance</span><div><?= $d['deadline']?date('d/m/Y',strtotime($d['deadline'])):'—' ?></div></div>
        <div><span class="text-slate-400">Rétention</span><div><?= e($d['retentionName']??'—') ?> <?= $d['legalHold']?'🔒 gel juridique':'' ?></div></div>
      </div>
      <?php if($d['description']): ?><div class="mt-4 p-3 bg-slate-50 rounded-xl text-sm"><?= nl2br(e($d['description'])) ?></div><?php endif; ?>
      <div class="mt-3 flex flex-wrap gap-1"><?php foreach($dTags as $t): ?><span class="inline-flex items-center gap-1 border px-2.5 py-1 rounded-full text-xs bg-white"><span class="h-2 w-2 rounded-full" style="background:<?= e($t['color']) ?>"></span><?= e($t['name']) ?></span><?php endforeach; ?><?php if(empty($dTags)): ?><span class="text-xs text-slate-400">Aucun tag</span><?php endif; ?></div>
      <?php if(!empty($metas)): ?><div class="mt-3 grid grid-cols-2 gap-2 text-sm"><?php foreach($metas as $m): ?><div><span class="text-slate-400"><?= e($m['name']) ?></span><div class="font-medium"><?= e($m['value']) ?></div></div><?php endforeach; ?></div><?php endif; ?>
    </div>
    <div class="card p-5">
      <h3 class="font-semibold mb-3">Organisation — tags & hiérarchie</h3>
      <form method="POST" class="space-y-3"><input type="hidden" name="tab" value="<?= e($tab) ?>">
        <div><label class="text-sm font-medium">Dossier parent</label><select name="parentId" class="input w-full"><option value="">— Racine —</option><?php $plist=$pdo->query("SELECT id,reference,title FROM dossiers WHERE id!='$id' AND deletedAt IS NULL ORDER BY createdAt DESC LIMIT 100")->fetchAll(); foreach($plist as $p): ?><option value="<?= e($p['id']) ?>" <?= $d['parentId']===$p['id']?'selected':'' ?>><?= e($p['reference']) ?> — <?= e($p['title']) ?></option><?php endforeach; ?></select></div>
        <div><label class="text-sm font-medium">Tags</label><div class="flex flex-wrap gap-2 mt-1"><?php $curTagIds=array_column($dTags,'id'); foreach($allTags as $t): ?><label class="inline-flex items-center gap-2 border rounded-full px-3 py-1.5 text-sm bg-white cursor-pointer"><input type="checkbox" name="tagIds[]" value="<?= e($t['id']) ?>" <?= in_array($t['id'],$curTagIds)?'checked':'' ?> class="rounded"><span class="h-2 w-2 rounded-full" style="background:<?= e($t['color']) ?>"></span><?= e($t['name']) ?></label><?php endforeach; ?></div></div>
        <button name="update_org" value="1" class="bg-slate-900 text-white px-4 py-2 rounded-xl text-sm">Enregistrer organisation</button>
      </form>
    </div>
    <div class="card p-4">
      <h3 class="font-semibold mb-2">Transmissions</h3>
      <?php if(empty($transmissions)): ?><p class="text-sm text-slate-400">Aucune transmission</p><?php endif; ?>
      <?php foreach($transmissions as $t): ?><div class="border rounded-xl px-3 py-2 mb-2 text-sm"><div class="flex justify-between"><span><?= e($t['sfn'].' '.$t['sln']) ?></span><span class="text-xs text-slate-400"><?= date('d/m/Y H:i',strtotime($t['createdAt'])) ?></span></div><div class="text-xs"><?= e($t['comment']?:$t['recommendations']?:'—') ?></div></div><?php endforeach; ?>
      <form method="POST" action="<?= BASE_URL ?>/api/transmission.php" class="mt-3 flex gap-2"><input type="hidden" name="dossierId" value="<?= e($id) ?>"><input name="comment" placeholder="Transmettre..." class="input flex-1"><button class="bg-blue-600 text-white px-4 rounded-xl text-sm">Transmettre</button></form>
    </div>
  </div>
  <div class="space-y-4">
    <div class="card p-4">
      <h3 class="font-semibold mb-3 flex items-center gap-2"><i data-lucide="settings-2" class="w-4 h-4"></i> Traitements</h3>
      <div class="text-xs mb-3 flex items-center gap-2">Statut : <?= statusBadge($d['status']) ?> <?php if($d['closureComment']): ?><span class="text-slate-400 truncate">— <?= e($d['closureComment']) ?></span><?php endif; ?></div>
      <?php
        $traitements=[
          ['id'=>'PRENDRE_EN_CHARGE','label'=>'Prendre en charge','icon'=>'user-check','bg'=>'bg-amber-100 text-amber-700 border-amber-200','desc'=>'Vous devenez responsable du dossier'],
          ['id'=>'ANNOTER','label'=>'Annoter','icon'=>'message-square','bg'=>'bg-blue-50 text-blue-700 border-blue-200','desc'=>'Ajouter une annotation / commentaire interne'],
          ['id'=>'DEMANDER_INFOS','label'=>'Demander infos','icon'=>'info','bg'=>'bg-orange-50 text-orange-700 border-orange-200','desc'=>'Solliciter des informations complémentaires'],
          ['id'=>'TRANSMETTRE_AVIS','label'=>'Transmettre pour avis','icon'=>'shield','bg'=>'bg-purple-50 text-purple-700 border-purple-200','desc'=>'Demander un avis à un autre agent/service','needsReceiver'=>true],
          ['id'=>'DELEGUER','label'=>'Déléguer','icon'=>'user','bg'=>'bg-indigo-50 text-indigo-700 border-indigo-200','desc'=>'Confier le dossier à un autre agent','needsReceiver'=>true],
          ['id'=>'COPIE','label'=>'Mettre en copie','icon'=>'copy','bg'=>'bg-cyan-50 text-cyan-700 border-cyan-200','desc'=>'Informer en copie sans action attendue','needsReceiver'=>true],
          ['id'=>'REORIENTER','label'=>'Réorienter','icon'=>'corner-up-right','bg'=>'bg-rose-50 text-rose-700 border-rose-200','desc'=>'Transférer vers un autre service','needsService'=>true],
          ['id'=>'VALIDER','label'=>'Valider','icon'=>'file-check','bg'=>'bg-green-50 text-green-700 border-green-200','desc'=>'Valider le contenu du dossier'],
          ['id'=>'VISER','label'=>'Viser','icon'=>'pen-line','bg'=>'bg-teal-50 text-teal-700 border-teal-200','desc'=>'Apposer un visa'],
          ['id'=>'APPROUVER','label'=>'Approuver','icon'=>'thumbs-up','bg'=>'bg-emerald-50 text-emerald-700 border-emerald-200','desc'=>'Approuver définitivement'],
          ['id'=>'ACQUITTER','label'=>'Acquitter','icon'=>'check-circle','bg'=>'bg-sky-50 text-sky-700 border-sky-200','desc'=>'Accuser réception / acquitter'],
          ['id'=>'NOTE_INTERNE','label'=>'Note interne','icon'=>'sticky-note','bg'=>'bg-violet-50 text-violet-700 border-violet-200','desc'=>'Note à usage interne (non visible demandeur)'],
          ['id'=>'SUSPENDRE','label'=>'Suspendre','icon'=>'pause','bg'=>'bg-amber-50 text-amber-700 border-amber-200','desc'=>'Mettre en attente / suspendre'],
          ['id'=>'REJETER','label'=>'Rejeter','icon'=>'x','bg'=>'bg-red-50 text-red-700 border-red-200','desc'=>'Rejeter le dossier'],
          ['id'=>'RAPPEL','label'=>'Rappel','icon'=>'bell','bg'=>'bg-orange-50 text-orange-700 border-orange-200','desc'=>'Envoyer un rappel au destinataire'],
        ];
      ?>
      <div class="grid grid-cols-2 gap-2">
        <?php foreach($traitements as $t): ?>
          <button onclick="openTraitement('<?= $t['id'] ?>','<?= e($t['label']) ?>', <?= isset($t['needsReceiver'])?'true':'false' ?>, <?= isset($t['needsService'])?'true':'false' ?>)" class="border rounded-xl px-2 py-3 flex flex-col items-center gap-1.5 text-center hover:shadow-sm transition <?= $t['bg'] ?>">
            <i data-lucide="<?= $t['icon'] ?>" class="w-5 h-5"></i>
            <span class="text-[11px] font-semibold leading-tight"><?= e($t['label']) ?></span>
          </button>
        <?php endforeach; ?>
      </div>
      <details class="border rounded-xl mt-3">
        <summary class="px-3 py-2 text-sm font-medium cursor-pointer flex items-center gap-2"><i data-lucide="sliders-horizontal" class="w-4 h-4"></i> Options avancées</summary>
        <form method="POST" class="p-3 space-y-2 border-t bg-slate-50 rounded-b-xl"><input type="hidden" name="tab" value="<?= e($tab) ?>">
          <div class="grid grid-cols-2 gap-2">
            <div><label class="text-xs font-medium">Statut</label><select name="status" class="input"><?php foreach(['NOUVEAU','EN_COURS','EN_ATTENTE','CLOS','ANNULE','REJETE'] as $s): ?><option value="<?= $s ?>" <?= $d['status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>
            <div><label class="text-xs font-medium">Échéance</label><input type="date" name="newDeadline" value="<?= $d['deadline']?date('Y-m-d',strtotime($d['deadline'])):'' ?>" class="input"></div>
          </div>
          <div><label class="text-xs font-medium">Commentaire de clôture (si CLOS)</label><textarea name="closureComment" rows="2" class="input" placeholder="Motif de clôture..."><?= e($d['closureComment']??'') ?></textarea></div>
          <button name="update_status" value="1" class="bg-slate-900 text-white w-full py-2 rounded-xl text-sm">Appliquer</button>
        </form>
      </details>
      <div class="mt-3 flex gap-1 flex-wrap">
        <button onclick="openSign()" class="bg-blue-600 text-white px-3 py-1.5 rounded-xl text-xs flex items-center gap-1"><i data-lucide="pen-tool" class="w-3 h-3"></i> Signer / Cacheter</button>
        <a href="<?= BASE_URL ?>/api/dossier_archive.php?id=<?= e($id) ?>" class="border px-2 py-1 rounded-xl text-xs bg-white hover:bg-slate-50" onclick="return confirm('Archiver ?')">Archiver</a>
        <a href="<?= BASE_URL ?>/api/dossier_delete.php?id=<?= e($id) ?>" class="border px-2 py-1 rounded-xl text-xs text-red-600 bg-white hover:bg-red-50" onclick="return confirm('Corbeille ?')">Corbeille</a>
      </div>
    </div>
    <!-- Modal traitement -->
    <div id="traitementModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden">
        <div class="flex items-center justify-between p-4 border-b"><h3 id="traitementTitle" class="font-semibold"></h3><button onclick="closeTraitement()" class="h-8 w-8 border rounded-xl flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button></div>
        <form method="POST" class="p-4 space-y-3"><input type="hidden" name="tab" value="<?= e($tab) ?>">
          <input type="hidden" name="actionType" id="traitementAction">
          <div id="traitementReceiverWrap" class="hidden"><label class="text-sm font-medium">Destinataire</label><select name="receiverId" class="input w-full"><option value="">— Choisir un agent —</option><?php foreach($users as $u): ?><option value="<?= e($u['id']) ?>"><?= e($u['firstName'].' '.$u['lastName']) ?></option><?php endforeach; ?></select></div>
          <div id="traitementServiceWrap" class="hidden"><label class="text-sm font-medium">Service cible</label><select name="targetServiceId" class="input w-full"><option value="">— Choisir un service —</option><?php foreach($services as $s): ?><option value="<?= e($s['id']) ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
          <div><label class="text-sm font-medium">Commentaire / instruction</label><textarea name="actionComment" rows="3" class="input w-full" placeholder="Votre message..." required></textarea></div>
          <div class="flex justify-end gap-2"><button type="button" onclick="closeTraitement()" class="border px-4 py-2 rounded-xl text-sm">Annuler</button><button class="bg-slate-900 text-white px-5 py-2 rounded-xl text-sm">Valider</button></div>
        </form>
      </div>
    </div>
    <script>
    function openTraitement(id,label,needsReceiver,needsService){
      document.getElementById('traitementAction').value=id;
      document.getElementById('traitementTitle').textContent=label;
      document.getElementById('traitementReceiverWrap').classList.toggle('hidden', !needsReceiver);
      document.getElementById('traitementServiceWrap').classList.toggle('hidden', !needsService);
      document.getElementById('traitementModal').classList.remove('hidden');
      if(window.lucide) lucide.createIcons();
    }
    function closeTraitement(){ document.getElementById('traitementModal').classList.add('hidden'); }
    function openSign(){ document.getElementById('signModal').classList.remove('hidden'); if(window.lucide) lucide.createIcons(); }
    function closeSign(){ document.getElementById('signModal').classList.add('hidden'); }
    </script>
    <!-- Modal signature / cachet -->
    <div id="signModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl w-full max-w-md overflow-hidden">
        <div class="flex items-center justify-between p-4 border-b"><h3 class="font-semibold flex items-center gap-2"><i data-lucide="pen-tool" class="w-4 h-4"></i> Signer / Cacheter</h3><button onclick="closeSign()" class="h-8 w-8 border rounded-xl flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button></div>
        <div class="p-4 space-y-3">
          <p class="text-xs text-slate-500">Dessine ta signature/cachet ci-dessous puis valide. Hash SHA256 + preuve SAE seront générés.</p>
          <canvas id="signCanvas" width="400" height="160" class="border-2 border-dashed rounded-xl w-full bg-slate-50 cursor-crosshair"></canvas>
          <div class="flex gap-2"><button onclick="clearSign()" class="border px-3 py-1.5 rounded-xl text-xs">Effacer</button><span class="text-xs text-slate-400 flex-1">Dossier <?= e($d['reference']) ?></span></div>
          <div class="flex justify-end gap-2"><button onclick="closeSign()" class="border px-4 py-2 rounded-xl text-sm">Annuler</button><button onclick="doSign()" class="bg-blue-600 text-white px-5 py-2 rounded-xl text-sm">Signer</button></div>
          <div id="signMsg" class="text-xs"></div>
        </div>
      </div>
    </div>
    <script>
    (function(){
      const c=document.getElementById('signCanvas'); if(!c) return; const ctx=c.getContext('2d'); let drawing=false;
      function pos(e){ const r=c.getBoundingClientRect(); const t=e.touches?e.touches[0]:e; return {x:(t.clientX-r.left)*(c.width/r.width), y:(t.clientY-r.top)*(c.height/r.height)}; }
      c.addEventListener('mousedown',e=>{drawing=true; const p=pos(e); ctx.beginPath(); ctx.moveTo(p.x,p.y);});
      c.addEventListener('mousemove',e=>{if(!drawing) return; const p=pos(e); ctx.lineTo(p.x,p.y); ctx.strokeStyle='#0f172a'; ctx.lineWidth=2; ctx.lineCap='round'; ctx.stroke();});
      window.addEventListener('mouseup',()=>drawing=false);
      c.addEventListener('touchstart',e=>{drawing=true; const p=pos(e); ctx.beginPath(); ctx.moveTo(p.x,p.y); e.preventDefault();},{passive:false});
      c.addEventListener('touchmove',e=>{if(!drawing) return; const p=pos(e); ctx.lineTo(p.x,p.y); ctx.strokeStyle='#0f172a'; ctx.lineWidth=2; ctx.lineCap='round'; ctx.stroke(); e.preventDefault();},{passive:false});
      window.clearSign=()=>{ctx.clearRect(0,0,c.width,c.height);};
      window.doSign=async()=>{
        const data=c.toDataURL('image/png'); const fd=new FormData(); fd.append('act','sign'); fd.append('dossierId','<?= e($id) ?>'); fd.append('signature_data',data);
        const r=await fetch('<?= BASE_URL ?>/api/signature.php',{method:'POST',body:fd}); const j=await r.json();
        const m=document.getElementById('signMsg'); if(j.ok){ m.textContent='Signé ✓ hash '+j.hash.slice(0,16)+'…'; m.className='text-xs text-emerald-600'; setTimeout(()=>closeSign(),900); } else { m.textContent=j.msg||'Erreur'; m.className='text-xs text-red-600'; }
      };
    })();
    </script>
  </div>
</div>
<!-- Historique en bas pleine largeur -->
<div class="card p-6 mt-6" id="historique">
  <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h3 class="font-semibold flex items-center gap-2"><i data-lucide="history" class="w-5 h-5"></i> Historique des opérations <span class="text-xs bg-slate-900 text-white px-3 py-1.5 rounded-full"><?= $histTotal ?> événement(s)</span></h3>
    <span class="text-xs text-slate-400">Page <?= $histPage ?>/<?= $histPages ?> — <?= $histTotal ?> au total</span>
  </div>
  <form method="GET" class="flex flex-wrap gap-2 mb-4 items-end bg-slate-50 border rounded-xl p-3">
    <input type="hidden" name="id" value="<?= e($id) ?>">
    <input type="hidden" name="tab" value="infos">
    <div><label class="block text-xs font-medium mb-1">Action</label><select name="hist_action" class="input text-sm"><option value="">Toutes</option><?php foreach($histActionsList as $aOpt): ?><option value="<?= e($aOpt) ?>" <?= $histAction===$aOpt?'selected':'' ?>><?= e($aOpt) ?></option><?php endforeach; ?></select></div>
    <div><label class="block text-xs font-medium mb-1">Utilisateur</label><select name="hist_user" class="input text-sm"><option value="">Tous</option><?php foreach($histUsersRows as $hu): if(empty($hu['userId'])) continue; ?><option value="<?= e($hu['userId']) ?>" <?= $histUser===$hu['userId']?'selected':'' ?>><?= e(trim($hu['firstName'].' '.$hu['lastName'])) ?></option><?php endforeach; ?></select></div>
    <div class="flex-1 min-w-[160px]"><label class="block text-xs font-medium mb-1">Recherche</label><input name="hist_q" value="<?= e($histQ) ?>" placeholder="action ou description..." class="input text-sm w-full"></div>
    <button class="bg-slate-900 text-white px-4 py-2 rounded-xl text-sm">Filtrer</button>
    <a href="?id=<?= e($id) ?>&tab=infos#historique" class="border bg-white px-4 py-2 rounded-xl text-sm">Réinitialiser</a>
  </form>
  <?php if(empty($actions)): ?><p class="text-sm text-slate-400 text-center py-10">Aucune opération enregistrée</p><?php else: ?>
  <div class="relative pl-8 border-l border-slate-200 space-y-0">
    <?php
      $iconMap=['CREATION'=>['plus','bg-blue-600'],'CHANGEMENT_STATUT'=>['refresh-cw','bg-amber-500'],'TRAITEMENT_INSTRUIRE'=>['play','bg-blue-600'],'TRAITEMENT_CLORE'=>['check','bg-emerald-600'],'TRAITEMENT_REJETER'=>['x','bg-red-600'],'TRAITEMENT_ANNULER'=>['ban','bg-zinc-600'],'TRAITEMENT_ATTENTE'=>['pause','bg-amber-500'],'PRENDRE_EN_CHARGE'=>['user-check','bg-amber-500'],'ANNOTER'=>['message-square','bg-blue-500'],'DEMANDER_INFOS'=>['info','bg-orange-500'],'TRANSMETTRE_AVIS'=>['shield','bg-purple-600'],'DELEGUER'=>['user','bg-indigo-600'],'COPIE'=>['copy','bg-cyan-600'],'REORIENTER'=>['corner-up-right','bg-rose-600'],'VALIDER'=>['file-check','bg-green-600'],'VISER'=>['pen-line','bg-teal-600'],'APPROUVER'=>['thumbs-up','bg-emerald-600'],'ACQUITTER'=>['check-circle','bg-sky-600'],'NOTE_INTERNE'=>['sticky-note','bg-violet-600'],'SUSPENDRE'=>['pause','bg-amber-600'],'REJETER'=>['x','bg-red-600'],'RAPPEL'=>['bell','bg-orange-500'],'TRANSMISSION'=>['send','bg-blue-500'],'COMMENTAIRE'=>['message-circle','bg-sky-500'],'VERSION_DOCUMENT'=>['file-up','bg-indigo-500'],'AJOUT_DOCUMENT'=>['file-plus','bg-emerald-500'],'MAJ_ORGANISATION'=>['tag','bg-indigo-500'],'ARCHIVAGE_CONFIG'=>['archive','bg-slate-600'],'WORKFLOW_START'=>['workflow','bg-purple-600'],'WORKFLOW_ETAPE'=>['check-circle','bg-emerald-600']];
      foreach($actions as $a):
        $cfg=$iconMap[$a['action']]??['activity','bg-slate-500'];
    ?>
    <div class="relative pb-5">
      <span class="absolute -left-[36px] top-1 h-8 w-8 rounded-full <?= $cfg[1] ?> flex items-center justify-center text-white shadow-sm"><i data-lucide="<?= $cfg[0] ?>" class="w-4 h-4"></i></span>
      <div class="bg-slate-50 border border-slate-100 rounded-xl px-4 py-3">
        <div class="flex items-center justify-between gap-2">
          <span class="text-xs font-bold tracking-wide text-slate-700"><?= e($a['action']) ?></span>
          <span class="text-xs text-slate-400"><?= date('d/m/Y H:i:s',strtotime($a['createdAt'])) ?></span>
        </div>
        <?php if($a['description']): ?><div class="text-sm text-slate-700 mt-1"><?= e($a['description']) ?></div><?php endif; ?>
        <div class="text-xs text-slate-400 mt-1.5 flex items-center gap-1"><i data-lucide="user" class="w-3 h-3"></i> <?= e(trim(($a['firstName']??'').' '.($a['lastName']??'')) ?: 'Système') ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php if($histPages>1): ?>
  <div class="flex items-center justify-between mt-4 pt-4 border-t">
    <div class="text-xs text-slate-400"><?= $histTotal ?> résultat(s) — 10 par page</div>
    <div class="flex gap-1">
      <?php
        $baseQs=http_build_query(array_filter(['id'=>$id,'tab'=>'infos','hist_action'=>$histAction,'hist_user'=>$histUser,'hist_q'=>$histQ]));
        for($p=1;$p<=$histPages;$p++){
          $qs=$baseQs.($baseQs?'&':'')."hist_page=$p";
          $cls=$p===$histPage?'bg-slate-900 text-white border-slate-900':'bg-white hover:bg-slate-50';
          echo '<a href="?'.$qs.'#historique" class="border px-3 py-1.5 rounded-xl text-xs '.$cls.'">'.$p.'</a>';
        }
      ?>
      <?php if($histPage>1): ?><a href="?<?= $baseQs ?>&hist_page=<?= $histPage-1 ?>#historique" class="border bg-white px-3 py-1.5 rounded-xl text-xs">‹ Préc.</a><?php endif; ?>
      <?php if($histPage<$histPages): ?><a href="?<?= $baseQs ?>&hist_page=<?= $histPage+1 ?>#historique" class="border bg-white px-3 py-1.5 rounded-xl text-xs">Suiv. ›</a><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
  <p class="text-xs text-slate-400 mt-3">Chaque création, changement de statut, ajout de document, transmission, workflow et archivage est journalisé (table <code>action_logs</code>).</p>
</div>
<?php elseif($tab==='docs'): ?>
<div class="grid lg:grid-cols-3 gap-4">
  <div class="lg:col-span-2 space-y-4">
    <div class="card p-5">
      <h3 class="font-semibold flex items-center gap-2"><i data-lucide="files" class="w-4 h-4"></i> Documents — versions, prévisualisation, partage</h3>
      <form method="POST" enctype="multipart/form-data" class="mt-3 space-y-2" id="bulkUploadForm"><div id="dropZone" class="border-2 border-dashed rounded-xl p-4 text-center bg-slate-50 hover:bg-white cursor-pointer"><p class="text-sm text-slate-600"><i data-lucide="upload-cloud" class="w-4 h-4 inline"></i> Glisser-déposer ou cliquer pour sélectionner</p><p class="text-xs text-slate-400">Plusieurs fichiers possibles — PDF, images, Word, Excel…</p><input type="file" name="files[]" multiple required id="bulkFiles" class="hidden" accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv"></div><div id="bulkList" class="text-xs text-slate-600"></div><button name="upload_doc" value="1" class="bg-blue-600 text-white px-4 py-2 rounded-xl text-sm">Ajouter les fichiers</button></form><script>const dz=document.getElementById('dropZone'), fi=document.getElementById('bulkFiles'), bl=document.getElementById('bulkList'); if(dz&&fi){ dz.addEventListener('click',()=>fi.click()); dz.addEventListener('dragover',e=>{e.preventDefault();dz.classList.add('border-blue-400','bg-blue-50')}); dz.addEventListener('dragleave',()=>dz.classList.remove('border-blue-400','bg-blue-50')); dz.addEventListener('drop',e=>{e.preventDefault();dz.classList.remove('border-blue-400','bg-blue-50'); fi.files=e.dataTransfer.files; updateList();}); fi.addEventListener('change',updateList); function updateList(){ if(!fi.files.length){bl.innerHTML='';return;} bl.innerHTML=Array.from(fi.files).map(f=>'<div class="flex justify-between border rounded-lg px-2 py-1 bg-white"><span>'+f.name+'</span><span class="text-slate-400">'+(f.size/1024).toFixed(1)+' Ko</span></div>').join(''); } }</script>
      <div class="mt-4 flex flex-wrap gap-2 items-end bg-slate-50 border rounded-xl p-3">
        <div class="flex-1 min-w-[180px]"><label class="block text-xs font-medium mb-1">Filtrer par nom</label><input id="docNameFilter" placeholder="ex: rapport, facture…" class="input text-sm w-full"></div>
        <div class="w-44"><label class="block text-xs font-medium mb-1">Type</label><select id="docTypeFilter" class="input text-sm w-full"><option value="">Tous types</option><option value="pdf">PDF</option><option value="image">Images</option><option value="word">Word</option><option value="excel">Excel</option><option value="video">Vidéo</option><option value="audio">Audio</option><option value="other">Autres</option></select></div>
        <span id="docFilterCount" class="text-xs text-slate-400 pb-2"><?= count($documents) ?> fichier(s)</span>
      </div>
      <div class="mt-3 space-y-2" id="docList">
        <?php if(empty($documents)): ?><p class="text-sm text-slate-400">Aucun document</p><?php endif; ?>
        <?php foreach($documents as $doc):
          $vers=$pdo->prepare("SELECT * FROM document_versions WHERE documentId=? ORDER BY version DESC"); $vers->execute([$doc['id']]); $vRows=$vers->fetchAll();
          $ext=strtolower(pathinfo($doc['originalName'],PATHINFO_EXTENSION)); $mime=$doc['mimeType'];
          if(str_contains($mime,'pdf')) $dtype='pdf'; elseif(str_starts_with($mime,'image/')) $dtype='image'; elseif(in_array($ext,['doc','docx'])) $dtype='word'; elseif(in_array($ext,['xls','xlsx','csv'])) $dtype='excel'; elseif(str_starts_with($mime,'video/')) $dtype='video'; elseif(str_starts_with($mime,'audio/')) $dtype='audio'; else $dtype='other';
        ?>
          <div class="doc-item border rounded-xl p-3 bg-white" data-name="<?= e(strtolower($doc['originalName'])) ?>" data-type="<?= $dtype ?>">
            <div class="flex items-start justify-between gap-2">
              <div><div class="font-medium text-sm"><?= e($doc['originalName']) ?> <span class="text-xs text-slate-400">v<?= (int)$doc['version'] ?></span> <?php if((int)$doc['isLocked']): ?><span class="text-xs bg-red-50 text-red-600 px-2 py-0.5 rounded-full">Verrouillé</span><?php endif; ?></div><div class="text-xs text-slate-400"><?= e($doc['mimeType']) ?> • <?= round($doc['fileSize']/1024,1) ?> Ko • <?= date('d/m/Y H:i',strtotime($doc['createdAt'])) ?></div>
                <?php if(!empty($vRows)): ?><details class="mt-1"><summary class="text-xs text-blue-600 cursor-pointer">Historique versions (<?= count($vRows) ?>)</summary><div class="mt-1 space-y-1"><?php foreach($vRows as $v): ?><div class="text-xs flex justify-between border rounded px-2 py-1"><span>v<?= (int)$v['version'] ?> — <?= e($v['fileName']) ?></span><span class="text-slate-400"><?= date('d/m H:i',strtotime($v['createdAt'])) ?></span></div><?php endforeach; ?></div></details><?php endif; ?>
              </div>
              <div class="flex gap-1 shrink-0">
                <button onclick="previewDoc('<?= e($doc['filePath']) ?>','<?= e($doc['mimeType']) ?>','<?= e($doc['originalName']) ?>','<?= e($doc['id']) ?>')" class="h-8 px-3 border rounded-xl text-xs hover:bg-slate-50">Prévisualiser</button>
                <a href="<?= BASE_URL ?>/<?= e($doc['filePath']) ?>" target="_blank" class="h-8 px-3 border rounded-xl text-xs flex items-center hover:bg-slate-50">Ouvrir</a>
              </div>
            </div>
            <div class="mt-2 flex flex-wrap gap-1 items-center">
              <form action="<?= BASE_URL ?>/api/document_version.php" method="POST" enctype="multipart/form-data" class="flex gap-1 items-center"><input type="hidden" name="documentId" value="<?= e($doc['id']) ?>"><input type="hidden" name="dossierId" value="<?= e($id) ?>"><input type="file" name="file" required class="text-xs"><button class="border px-2 py-1 rounded-xl text-xs hover:bg-slate-50">Nouvelle version</button></form>
              <button onclick="openShare('<?= e($doc['id']) ?>','document')" class="border px-2 py-1 rounded-xl text-xs hover:bg-slate-50">Partager</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div><p id="docNoResult" class="hidden text-sm text-slate-400 text-center py-6">Aucun fichier ne correspond aux filtres.</p><div id="docPagination" class="flex items-center justify-between mt-3 pt-3 border-t hidden"><span id="docPageInfo" class="text-xs text-slate-400"></span><div class="flex gap-1"><button id="docPrev" class="border bg-white px-3 py-1.5 rounded-xl text-xs">‹ Préc.</button><span id="docPageNums" class="flex gap-1"></span><button id="docNext" class="border bg-white px-3 py-1.5 rounded-xl text-xs">Suiv. ›</button></div></div><script>(function(){const nf=document.getElementById('docNameFilter'),tf=document.getElementById('docTypeFilter'),cc=document.getElementById('docFilterCount'),nr=document.getElementById('docNoResult'),pg=document.getElementById('docPagination'),pi=document.getElementById('docPageInfo'),pn=document.getElementById('docPageNums'),prev=document.getElementById('docPrev'),next=document.getElementById('docNext');const perPage=5;let cur=1,filtered=[];function collect(){const q=(nf.value||'').toLowerCase().trim(),t=tf.value;filtered=[];document.querySelectorAll('.doc-item').forEach(el=>{const ok=!q||el.dataset.name.includes(q);const okT=!t||el.dataset.type===t;el._match=ok&&okT;if(el._match) filtered.push(el);});}function render(){const total=filtered.length, pages=Math.max(1,Math.ceil(total/perPage)); if(cur>pages) cur=pages; if(cur<1) cur=1; document.querySelectorAll('.doc-item').forEach(el=>el.classList.add('hidden')); const start=(cur-1)*perPage; filtered.slice(start,start+perPage).forEach(el=>el.classList.remove('hidden')); cc.textContent=total+' fichier(s)'; nr.classList.toggle('hidden', total!==0); pg.classList.toggle('hidden', total<=perPage && pages===1 && total===0 ? true : total<=perPage ? true : false); if(total>perPage) pg.classList.remove('hidden'); pi.textContent= total?('Page '+cur+'/'+pages+' — '+total+' résultat(s) — 5 par page'):''; pn.innerHTML=''; for(let i=1;i<=pages;i++){const b=document.createElement('button'); b.textContent=i; b.className='border px-3 py-1.5 rounded-xl text-xs '+(i===cur?'bg-slate-900 text-white border-slate-900':'bg-white hover:bg-slate-50'); b.addEventListener('click',()=>{cur=i;render();}); pn.appendChild(b);} prev.disabled=cur===1; next.disabled=cur===pages; prev.classList.toggle('opacity-40',cur===1); next.classList.toggle('opacity-40',cur===pages);} function apply(){collect(); cur=1; render();} nf&&nf.addEventListener('input',apply); tf&&tf.addEventListener('change',apply); prev&&prev.addEventListener('click',()=>{if(cur>1){cur--;render();}}); next&&next.addEventListener('click',()=>{let pages=Math.ceil(filtered.length/perPage); if(cur<pages){cur++;render();}}); collect(); render();})();</script>
    </div>
  </div>
  <div class="space-y-4">
    <div class="card p-4">
      <h4 class="font-semibold text-sm mb-2">Partage — liens sécurisés</h4>
      <form id="shareForm" class="space-y-2 text-sm">
        <input type="hidden" name="dossierId" value="<?= e($id) ?>">
        <div><label class="text-xs">Expiration</label><input type="date" name="expiresAt" class="input"></div>
        <div><label class="text-xs">Mot de passe (optionnel)</label><input name="password" class="input" placeholder="••••"></div>
        <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="allowDownload" value="1" checked> Autoriser téléchargement</label>
        <button type="submit" class="bg-slate-900 text-white w-full py-2 rounded-xl">Générer lien dossier</button>
        <div id="shareOut" class="text-xs mt-2"></div>
      </form>
      <div id="shareList" class="mt-3 space-y-1 text-xs"><?php foreach($shareRows as $sh): $u2=BASE_URL.'/share.php?token='.urlencode($sh['token']); ?><div class="border rounded-lg px-2 py-1 flex items-center justify-between gap-2"><a href="<?= e($u2) ?>" target="_blank" class="font-mono truncate text-blue-600"><?= e($u2) ?></a><button onclick="copyText('<?= e($u2) ?>')" class="border bg-white px-2 py-1 rounded-lg text-xs shrink-0">Copier</button></div><?php endforeach; ?></div>
    </div>
    <!-- preview modal -->
    <div id="previewModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-6">
      <div class="bg-white rounded-2xl w-full max-w-5xl max-h-[85vh] flex flex-col overflow-hidden">
        <div class="flex items-center justify-between p-3 border-b gap-2">
          <h3 id="previewTitle" class="font-semibold truncate"></h3>
          <div class="flex items-center gap-1.5 shrink-0">
            <span id="editBadge" class="hidden text-[10px] bg-amber-100 text-amber-700 border border-amber-200 px-2 py-1 rounded-full">Édition</span>
            <button id="btnEdit" onclick="toggleEdit()" class="hidden border bg-white px-3 py-1.5 rounded-xl text-xs font-medium hover:bg-slate-50">Modifier</button>
            <button id="btnSave" onclick="saveEdit()" class="hidden bg-blue-600 text-white px-3 py-1.5 rounded-xl text-xs font-medium hover:bg-blue-700">Sauvegarder</button>
            <button onclick="document.getElementById('previewModal').classList.add('hidden')" class="border px-3 py-1.5 rounded-xl text-sm">Fermer</button>
          </div>
        </div>
        <div id="editToolbar" class="hidden border-b bg-zinc-50 px-3 py-2 flex items-center gap-1">
          <button onclick="document.execCommand('bold')" class="h-7 w-7 border rounded bg-white text-sm font-bold">B</button>
          <button onclick="document.execCommand('italic')" class="h-7 w-7 border rounded bg-white text-sm italic">I</button>
          <button onclick="document.execCommand('underline')" class="h-7 w-7 border rounded bg-white text-sm underline">U</button>
          <span class="text-xs text-slate-400 ml-2">Édition live — modifie puis Sauvegarder (nouvelle version)</span>
        </div>
        <div id="previewBody" class="flex-1 overflow-auto p-4 bg-slate-50"></div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/mammoth@1.6.0/mammoth.browser.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://unpkg.com/html-docx-js@0.3.1/dist/html-docx.js"></script>
<script>
let _edit={type:null, docId:null, dossierId:'<?= e($id) ?>', name:null};
function toggleEdit(){
  const body=document.getElementById('previewBody');
  const area=body.querySelector('#editArea');
  if(!area) return;
  const on=area.getAttribute('contenteditable')==='true';
  area.setAttribute('contenteditable', on?'false':'true');
  if(!on) area.focus();
  document.getElementById('editBadge').classList.toggle('hidden', on);
  document.getElementById('editToolbar').classList.toggle('hidden', on);
  document.getElementById('btnEdit').textContent=on?'Modifier':'Annuler édition';
  document.getElementById('btnEdit').classList.toggle('bg-amber-50', !on);
  // excel cells
  body.querySelectorAll('td').forEach(td=>td.setAttribute('contenteditable', on?'false':'true'));
}
async function saveEdit(){
  const body=document.getElementById('previewBody');
  const btn=document.getElementById('btnSave');
  if(!_edit.docId) return alert('docId manquant');
  btn.disabled=true; btn.textContent='Enregistrement…';
  try{
    let blob, outName=_edit.name;
    if(_edit.type==='word'){
      const area=body.querySelector('#editArea');
      if(!area) throw new Error('zone édition introuvable');
      const html='<!DOCTYPE html><html><head><meta charset=\"utf-8\"></head><body>'+area.innerHTML+'</body></html>';
      if(window.HTMLDocx) blob=HTMLDocx.asBlob(html);
      else blob=new Blob([area.innerText],{type:'application/vnd.openxmlformats-officedocument.wordprocessingml.document'});
      if(!outName.toLowerCase().endsWith('.docx')) outName=outName.replace(/\.\w+$/,'')+'.docx';
    } else if(_edit.type==='excel'){
      // collecte tableaux éditables -> aoa
      const tables=body.querySelectorAll('table');
      const wb=XLSX.utils.book_new();
      tables.forEach((tbl,i)=>{
        const aoa=[]; tbl.querySelectorAll('tr').forEach(tr=>{
          const row=[]; tr.querySelectorAll('td,th').forEach(c=>row.push(c.innerText.trim())); if(row.some(v=>v!=='')) aoa.push(row);
        });
        if(aoa.length){ const ws=XLSX.utils.aoa_to_sheet(aoa); XLSX.utils.book_append_sheet(wb,ws,'Feuille'+(i+1)); }
      });
      if(!wb.SheetNames.length) throw new Error('Aucune donnée');
      const out=XLSX.write(wb,{type:'array',bookType:'xlsx'});
      blob=new Blob([out],{type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'});
      if(!outName.toLowerCase().endsWith('.xlsx')) outName=outName.replace(/\.\w+$/,'')+'.xlsx';
    } else throw new Error('type inconnu');
    const fd=new FormData(); fd.append('documentId', _edit.docId); fd.append('dossierId', _edit.dossierId); fd.append('file', blob, outName);
    // document_version.php fait redirect 302 -> fetch suit et renvoie HTML final 200
    const res=await fetch('<?= BASE_URL ?>/api/document_version.php',{method:'POST', body:fd});
    if(res.ok){ body.insertAdjacentHTML('beforeend','<div class=\"mt-3 bg-emerald-50 border border-emerald-200 text-emerald-700 px-3 py-2 rounded-xl text-xs text-center\">Sauvegardé — nouvelle version créée ✓ (rafraîchis l\\\'onglet Documents)</div>'); btn.textContent='Sauvegardé ✓'; setTimeout(()=>{ document.getElementById('previewModal').classList.add('hidden'); location.reload(); }, 900); }
    else throw new Error('HTTP '+res.status);
  }catch(e){ alert('Erreur save: '+(e.message||e)); btn.disabled=false; btn.textContent='Sauvegarder'; }
}
function previewDoc(path, mime, name, docId){
  _edit={type:null, docId:docId||null, dossierId:'<?= e($id) ?>', name:name};
  document.getElementById('editBadge').classList.add('hidden'); document.getElementById('editToolbar').classList.add('hidden');
  document.getElementById('btnSave').classList.add('hidden'); document.getElementById('btnEdit').classList.add('hidden'); document.getElementById('btnEdit').textContent='Modifier';
  const modal=document.getElementById('previewModal'), body=document.getElementById('previewBody');
  document.getElementById('previewTitle').textContent=name;
  const url='<?= BASE_URL ?>/'+path;
  let html='';
  const isWord=mime.includes('word')||mime.includes('officedocument')||name.toLowerCase().endsWith('.docx')||name.toLowerCase().endsWith('.doc');
  const isExcel=mime.includes('sheet')||name.toLowerCase().endsWith('.xlsx')||name.toLowerCase().endsWith('.xls');
  if(mime.startsWith('image/')) html='<img src="'+url+'" class="max-w-full mx-auto rounded-xl">';
  else if(mime==='application/pdf') html='<iframe src="'+url+'" class="w-full h-[70vh] border rounded-xl"></iframe>';
  else if(mime.includes('video')) html='<video src="'+url+'" controls class="w-full rounded-xl"></video>';
  else if(mime.includes('audio')) html='<audio src="'+url+'" controls class="w-full"></audio>';
  else if(isWord && name.toLowerCase().endsWith('.docx')){ body.innerHTML='<div class="p-8 text-center text-sm text-slate-500">Rendu Word en cours…</div>'; modal.classList.remove('hidden'); fetch(url,{cache:'no-store'}).then(r=>r.arrayBuffer()).then(buf=>mammoth.convertToHtml({arrayBuffer:buf})).then(res=>{
       let h=(res.value||'').trim(); const txt=h.replace(/<[^>]+>/g,'').trim();
       if(txt.length<5) throw new Error('mammoth vide');
       _edit.type='word'; body.innerHTML='<div id="editArea" style="background:white;padding:20px;border-radius:12px;max-width:800px;margin:auto;line-height:1.6;font-size:14px;outline:none;min-height:200px" contenteditable="false">'+h+'</div><div class="mt-3 text-center text-xs text-slate-400">Clique Modifier pour éditer live</div><div class="mt-1 text-center"><a href="'+url+'" target="_blank" class="text-blue-600 text-xs">Télécharger l\'original</a></div>'; document.getElementById('btnEdit').classList.remove('hidden'); document.getElementById('btnSave').classList.remove('hidden');
     }).catch(err=>{ console.warn('mammoth fail',err); fetch('<?= BASE_URL ?>/api/preview.php?id='+encodeURIComponent(docId)+'&_='+Date.now()).then(r=>r.json()).then(j=>{ _edit.type='word'; const inner=j.html||'<p style="color:#999">Aperçu indisponible — téléchargez.</p>'; body.innerHTML='<div id="editArea" style="background:white;padding:20px;border-radius:12px;max-width:800px;margin:auto" contenteditable="false">'+inner+'</div><div class="mt-2 text-xs text-center text-slate-400">Fallback serveur</div>'; document.getElementById('btnEdit').classList.remove('hidden'); document.getElementById('btnSave').classList.remove('hidden'); }).catch(()=> body.innerHTML='<div class="p-8 text-center text-red-600">Impossible — <a href="'+url+'" target="_blank" class="text-blue-600">Télécharger</a></div>'); }); return; }
  else if(isExcel && docId){ body.innerHTML='<div class="p-8 text-center text-sm text-slate-500">Rendu Excel en cours…</div>'; modal.classList.remove('hidden'); fetch(url).then(r=>r.arrayBuffer()).then(buf=>{ const wb=XLSX.read(buf,{type:'array'}); let html=''; wb.SheetNames.slice(0,5).forEach(sn=>{ const ws=wb.Sheets[sn]; html+='<h4 style="font-weight:700;margin:12px 0 6px">'+sn+'</h4>'+XLSX.utils.sheet_to_html(ws,{editable:false}); }); if(!html) html='<p style="color:#999">Feuille vide</p>'; _edit.type='excel'; body.innerHTML='<div id="editArea" style="background:white;padding:12px;border-radius:12px;overflow:auto">'+html+'</div><div class="mt-2 text-center text-xs text-slate-400">Modifier → chaque cellule devient éditable</div><div class="mt-1 text-center"><a href="'+url+'" target="_blank" class="text-blue-600 text-xs">Télécharger</a></div>'; document.getElementById('btnEdit').classList.remove('hidden'); document.getElementById('btnSave').classList.remove('hidden'); }).catch(()=>{ fetch('<?= BASE_URL ?>/api/preview.php?id='+encodeURIComponent(docId)).then(r=>r.json()).then(j=>{ _edit.type='excel'; body.innerHTML='<div id="editArea">'+(j.html||'Erreur')+'</div>'; document.getElementById('btnEdit').classList.remove('hidden'); document.getElementById('btnSave').classList.remove('hidden'); }); }); return; }
  else if((isWord||isExcel) && docId){ body.innerHTML='<div class="p-8 text-center text-sm text-slate-500">Chargement…</div>'; modal.classList.remove('hidden'); fetch('<?= BASE_URL ?>/api/preview.php?id='+encodeURIComponent(docId)).then(r=>r.json()).then(j=>{ if(j.ok&&j.html) body.innerHTML=j.html+'<div class="mt-3 text-center"><a href="'+url+'" target="_blank" class="text-blue-600 text-xs">Ouvrir / Télécharger l\'original</a></div>'; else body.innerHTML='<div class="text-center p-8"><p class="text-sm text-red-600">'+(j.msg||'Erreur')+'</p><a href="'+url+'" target="_blank" class="text-blue-600 text-sm">Télécharger</a></div>'; }).catch(()=>{ body.innerHTML='<div class="text-center p-8 text-red-600">Erreur chargement</div>'; }); return; }
  else html='<div class="text-center p-8"><p class="text-sm text-slate-500">Prévisualisation non disponible pour '+mime+'</p><a href="'+url+'" target="_blank" class="text-blue-600 text-sm">Ouvrir / Télécharger</a><p class="text-xs text-slate-400 mt-2">Formats prévisualisables : PDF, images, vidéos, audio, Word (.docx), Excel (.xlsx).</p></div>';
  body.innerHTML=html; modal.classList.remove('hidden');
}
function copyText(t){ navigator.clipboard.writeText(t).then(()=>alert('Lien copié')).catch(()=>prompt('Copiez le lien :',t)); }
function openShare(id, type){
  const pwd=prompt('Mot de passe (laisser vide pour aucun) :'); const exp=prompt('Expiration (YYYY-MM-DD) ou vide :');
  const fd=new FormData(); if(type==='document') fd.append('documentId', id); else fd.append('dossierId', id);
  if(pwd) fd.append('password', pwd); if(exp) fd.append('expiresAt', exp);
  fetch('<?= BASE_URL ?>/api/share.php',{method:'POST', body:fd}).then(r=>r.json()).then(j=>{ if(j.url){ document.getElementById('shareOut').innerHTML='<div class="bg-emerald-50 border border-emerald-200 rounded-xl p-2 break-all"><a href="'+j.url+'" target="_blank" class="text-blue-600">'+j.url+'</a> <button onclick="copyText(\''+j.url+'\')" class="ml-2 border bg-white px-2 py-1 rounded-lg text-xs">Copier</button></div>'; const list=document.getElementById('shareList'); if(list){ const d=document.createElement('div'); d.className='border rounded-lg px-2 py-1 flex items-center justify-between gap-2 bg-emerald-50'; d.innerHTML='<a href="'+j.url+'" target="_blank" class="font-mono truncate text-blue-600">'+j.url+'</a><button onclick="copyText(\''+j.url+'\')" class="border bg-white px-2 py-1 rounded-lg text-xs shrink-0">Copier</button>'; list.prepend(d);} } else alert(j.error||'Erreur'); });
}
document.getElementById('shareForm').addEventListener('submit', e=>{
  e.preventDefault(); const fd=new FormData(e.target);
  fetch('<?= BASE_URL ?>/api/share.php',{method:'POST', body:fd}).then(r=>r.json()).then(j=>{
    document.getElementById('shareOut').innerHTML=j.url?'<div class="bg-emerald-50 border border-emerald-200 rounded-xl p-2 break-all"><a href="'+j.url+'" target="_blank" class="text-blue-600">'+j.url+'</a> <button onclick="copyText(\''+j.url+'\')" class="ml-2 border bg-white px-2 py-1 rounded-lg text-xs">Copier</button></div>':'<span class="text-red-600">'+(j.error||'Erreur')+'</span>';
    if(j.url){ const list=document.getElementById('shareList'); if(list){ const d=document.createElement('div'); d.className='border rounded-lg px-2 py-1 flex items-center justify-between gap-2 bg-emerald-50'; d.innerHTML='<a href="'+j.url+'" target="_blank" class="font-mono truncate text-blue-600">'+j.url+'</a><button onclick="copyText(\''+j.url+'\')" class="border bg-white px-2 py-1 rounded-lg text-xs shrink-0">Copier</button>'; list.prepend(d);} }
  });
});
</script>
<?php elseif($tab==='collab'): ?>
<div class="grid lg:grid-cols-3 gap-4" id="comments">
  <div class="lg:col-span-2 space-y-4">
    <div class="card p-5">
      <h3 class="font-semibold flex items-center gap-2"><i data-lucide="message-circle" class="w-4 h-4"></i> Fil de discussion — commentaires & mentions</h3>
      <div class="mt-3 flex gap-2">
        <input id="commentFilter" placeholder="Filtrer par auteur ou message..." class="input text-sm flex-1">
        <select id="commentSort" class="input text-sm w-36"><option value="desc">Plus récents</option><option value="asc">Plus anciens</option></select>
      </div>
      <div id="commentList" class="space-y-3 mt-3 max-h-[420px] overflow-auto">
        <?php if(empty($coms)): ?><p id="noComment" class="text-sm text-slate-400">Aucun commentaire — démarrez la discussion</p><?php endif; ?>
        <?php foreach($coms as $c): ?>
          <div class="border rounded-xl px-3 py-2 bg-white"><div class="flex justify-between text-xs"><span class="font-medium"><?= e($c['firstName'].' '.$c['lastName']) ?></span><span class="text-slate-400"><?= date('d/m/Y H:i',strtotime($c['createdAt'])) ?></span></div><div class="text-sm mt-1"><?= nl2br(e($c['content'])) ?></div></div>
        <?php endforeach; ?>
      </div>
      <div id="commentPagination" class="flex items-center justify-between mt-3 pt-3 border-t hidden">
        <span id="commentPageInfo" class="text-xs text-slate-400"></span>
        <div class="flex gap-1">
          <button id="commentPrev" class="border bg-white px-3 py-1.5 rounded-xl text-xs">‹ Préc.</button>
          <span id="commentPageNums" class="flex gap-1"></span>
          <button id="commentNext" class="border bg-white px-3 py-1.5 rounded-xl text-xs">Suiv. ›</button>
        </div>
      </div>
      <form id="commentForm" action="<?= BASE_URL ?>/api/comment.php" method="POST" class="mt-3 flex gap-2"><input type="hidden" name="dossierId" value="<?= e($id) ?>"><input id="commentInput" name="content" required placeholder="Commentaire... utilisez @nom pour mention" class="input flex-1"><button class="bg-blue-600 text-white px-4 rounded-xl text-sm">Envoyer</button><span id="commentStatus" class="text-xs text-slate-400 self-center"></span></form>
      <script>
      (function(){
        const dossierId="<?= e($id) ?>";
        const list=document.getElementById('commentList');
        const filterEl=document.getElementById('commentFilter');
        const sortEl=document.getElementById('commentSort');
        const pagEl=document.getElementById('commentPagination');
        const pageInfo=document.getElementById('commentPageInfo');
        const pageNums=document.getElementById('commentPageNums');
        const prevBtn=document.getElementById('commentPrev');
        const nextBtn=document.getElementById('commentNext');
        const perPage=6;
        let curPage=1;
        let allComments=<?= json_encode($coms) ?>;
        let lastIds=new Set(allComments.map(c=>c.id));
        function getFiltered(){
          const q=(filterEl.value||'').toLowerCase().trim();
          let arr=[...allComments];
          if(q) arr=arr.filter(c=> (c.firstName+' '+c.lastName+' '+c.content).toLowerCase().includes(q));
          const dir=sortEl.value;
          arr.sort((a,b)=> dir==='asc' ? new Date(a.createdAt)-new Date(b.createdAt) : new Date(b.createdAt)-new Date(a.createdAt));
          return arr;
        }
        function renderPage(){
          const filtered=getFiltered();
          const total=filtered.length;
          const pages=Math.max(1,Math.ceil(total/perPage));
          if(curPage>pages) curPage=pages;
          if(curPage<1) curPage=1;
          list.innerHTML='';
          if(total===0){ list.innerHTML='<p class="text-sm text-slate-400">Aucun résultat</p>'; pagEl.classList.add('hidden'); return; }
          const start=(curPage-1)*perPage;
          filtered.slice(start,start+perPage).forEach(c=>{
            const div=document.createElement('div');
            div.className='border rounded-xl px-3 py-2 bg-white';
            const d=new Date(c.createdAt);
            const ds=d.toLocaleString('fr-FR');
            div.innerHTML='<div class="flex justify-between text-xs"><span class="font-medium">'+(c.firstName+' '+c.lastName).replace(/</g,'&lt;')+'</span><span class="text-slate-400">'+ds+'</span></div><div class="text-sm mt-1">'+c.content.replace(/</g,'&lt;').replace(/\n/g,'<br>')+'</div>';
            list.appendChild(div);
          });
          if(total>perPage){
            pagEl.classList.remove('hidden');
            pageInfo.textContent='Page '+curPage+'/'+pages+' — '+total+' message(s) — 6 par page';
            pageNums.innerHTML='';
            for(let i=1;i<=pages;i++){
              const b=document.createElement('button');
              b.textContent=i;
              b.className='border px-2 py-1 rounded-lg text-xs '+(i===curPage?'bg-slate-900 text-white border-slate-900':'bg-white hover:bg-slate-50');
              b.onclick=()=>{curPage=i; renderPage();};
              pageNums.appendChild(b);
            }
            prevBtn.disabled=curPage===1; nextBtn.disabled=curPage===pages;
            prevBtn.classList.toggle('opacity-40',curPage===1);
            nextBtn.classList.toggle('opacity-40',curPage===pages);
          } else {
            pagEl.classList.add('hidden');
          }
        }
        async function poll(){
          try{
            const r=await fetch('<?= BASE_URL ?>/api/comments_poll.php?id='+encodeURIComponent(dossierId),{cache:'no-store'});
            if(!r.ok) return;
            const data=await r.json();
            if(!Array.isArray(data)) return;
            if(data.length!==allComments.length || data.some(c=>!lastIds.has(c.id))){
              const newOnes=data.filter(c=>!lastIds.has(c.id));
              if(newOnes.length>0 && allComments.length>0){
                const push=document.createElement('div');
                push.className='bg-green-600 text-white px-3 py-2 rounded-xl text-sm mb-2';
                push.textContent=newOnes.length+' nouveau(x) message(s)';
                list.prepend(push);
                setTimeout(()=>push.remove(),3000);
              }
              allComments=data;
              lastIds=new Set(data.map(c=>c.id));
              renderPage();
            }
          }catch(e){}
        }
        filterEl.addEventListener('input',()=>{curPage=1; renderPage();});
        sortEl.addEventListener('change',()=>{curPage=1; renderPage();});
        prevBtn.addEventListener('click',()=>{if(curPage>1){curPage--; renderPage();}});
        nextBtn.addEventListener('click',()=>{const f=getFiltered(); const pages=Math.ceil(f.length/perPage); if(curPage<pages){curPage++; renderPage();}});
        setInterval(poll,3000);
        renderPage();
        const form=document.getElementById('commentForm');
        if(form){
          form.addEventListener('submit',async (e)=>{
            e.preventDefault();
            const fd=new FormData(form);
            const status=document.getElementById('commentStatus');
            status.textContent='Envoi...';
            try{
              const r=await fetch(form.action,{method:'POST',body:fd});
              if(r.ok){ form.reset(); status.textContent='Envoyé ✓'; setTimeout(()=>status.textContent='',1500); poll(); }
              else { status.textContent='Erreur'; const t=await r.text(); alert(t); }
            }catch(err){ status.textContent='Erreur réseau'; }
          });
        }
      })();
      </script>
      <p class="text-xs text-slate-400 mt-1">Annotations : sélectionnez du texte dans la prévisualisation pour annoter (à venir).</p>
    </div>
    <div class="card p-5" id="tasks">
      <h3 class="font-semibold flex items-center gap-2"><i data-lucide="check-square" class="w-4 h-4"></i> Tâches</h3>
      <form action="<?= BASE_URL ?>/api/task.php" method="POST" class="grid md:grid-cols-3 gap-2 mt-3 text-sm">
        <input type="hidden" name="dossierId" value="<?= e($id) ?>">
        <input name="title" required placeholder="Nouvelle tâche..." class="input md:col-span-2">
        <select name="assigneeId" class="input"><option value="">— Assigner —</option><?php foreach($users as $u2): ?><option value="<?= e($u2['id']) ?>"><?= e($u2['firstName'].' '.$u2['lastName']) ?></option><?php endforeach; ?></select>
        <input type="date" name="dueDate" class="input">
        <button class="bg-slate-900 text-white rounded-xl px-4">Créer</button>
      </form>
      <div class="mt-3 space-y-2">
        <?php foreach($taskRows as $t): ?>
          <div class="border rounded-xl px-3 py-2 flex items-center justify-between text-sm bg-white">
            <div><div class="font-medium"><?= e($t['title']) ?> <span class="text-xs px-2 py-0.5 rounded-full <?= $t['status']==='TERMINE'?'bg-emerald-50 text-emerald-700':($t['status']==='EN_COURS'?'bg-blue-50 text-blue-700':'bg-amber-50 text-amber-700') ?>"><?= e($t['status']) ?></span></div><div class="text-xs text-slate-400">Créé par <?= e($t['cfn'].' '.$t['cln']) ?> → <?= e($t['afn']? $t['afn'].' '.$t['aln']:'—') ?> • <?= $t['dueDate']?date('d/m/Y',strtotime($t['dueDate'])):'sans échéance' ?></div></div>
            <div class="flex gap-1">
              <?php if($t['status']!=='TERMINE'): ?><a href="<?= BASE_URL ?>/api/task.php?done=<?= e($t['id']) ?>" class="border px-2 py-1 rounded-xl text-xs hover:bg-emerald-50">Terminer</a><?php endif; ?>
              <a href="<?= BASE_URL ?>/api/task.php?del=<?= e($t['id']) ?>" onclick="return confirm('Supprimer ?')" class="border px-2 py-1 rounded-xl text-xs hover:bg-red-50 text-red-600">✕</a>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if(empty($taskRows)): ?><p class="text-xs text-slate-400 mt-2">Aucune tâche</p><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="space-y-4">
    <div class="card p-4">
      <h4 class="font-semibold text-sm">Notifications</h4>
      <p class="text-xs text-slate-500 mt-1">Chaque commentaire / tâche notifie les participants. Voir Notifications dans le header.</p>
    </div>
  </div>
</div>
<?php elseif($tab==='workflow'): ?>
<div class="space-y-4" id="workflow">
  <div class="card p-5">
    <div class="flex items-center justify-between"><h3 class="font-semibold flex items-center gap-2"><i data-lucide="workflow" class="w-4 h-4"></i> Circuit de validation</h3>
      <?php if(!$workflow): ?><form method="POST"><input type="hidden" name="tab" value="workflow"><button name="start_workflow" value="1" class="bg-blue-600 text-white px-4 py-2 rounded-xl text-sm">Démarrer circuit standard (Instruction → Validation → Visa)</button></form><?php else: ?><span class="text-xs px-3 py-1 rounded-full <?= $workflow['status']==='TERMINE'?'bg-emerald-50 text-emerald-700':'bg-blue-50 text-blue-700' ?>"><?= e($workflow['status']) ?></span><?php endif; ?>
    </div>
    <?php if($workflow): ?>
      <div class="mt-4 space-y-3">
        <?php foreach($wfSteps as $st): ?>
          <div class="border rounded-xl p-3 flex items-start justify-between gap-3 <?= $st['status']==='EN_COURS'?'bg-blue-50/50 border-blue-200':'' ?>">
            <div><div class="font-medium text-sm"><?= (int)$st['order'] ?>. <?= e($st['name']) ?> <span class="text-xs px-2 py-0.5 rounded-full <?= $st['status']==='VALIDE'?'bg-emerald-50 text-emerald-700':($st['status']==='EN_COURS'?'bg-blue-600 text-white':'bg-slate-100 text-slate-600') ?>"><?= e($st['status']) ?></span></div>
              <div class="text-xs text-slate-500"><?= $st['comment']?e($st['comment']):'—' ?> <?= $st['validatedAt']?'• '.date('d/m H:i',strtotime($st['validatedAt'])):'' ?> <?= $st['firstName']?'• par '.e($st['firstName'].' '.$st['lastName']):'' ?></div>
            </div>
            <?php if($st['status']==='EN_COURS'): ?>
              <form method="POST" class="flex gap-1 items-center"><input type="hidden" name="tab" value="workflow"><input type="hidden" name="stepId" value="<?= e($st['id']) ?>"><input name="comment" placeholder="Commentaire" class="input text-xs w-32"><button name="validate_step" value="1" onclick="this.form.decision.value='VALIDE'" class="bg-emerald-600 text-white px-3 py-1 rounded-xl text-xs">Valider</button><button name="validate_step" value="1" onclick="this.form.decision.value='REJETE'" class="border px-3 py-1 rounded-xl text-xs text-red-600">Rejeter</button><input type="hidden" name="decision" value="VALIDE"></form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php elseif($tab==='securite'): ?>
<div class="grid lg:grid-cols-2 gap-4">
  <div class="card p-5">
    <h3 class="font-semibold flex items-center gap-2"><i data-lucide="shield" class="w-4 h-4 text-emerald-600"></i> Droits & confidentialité</h3>
    <div class="mt-3 text-sm space-y-2">
      <div>Confidentialité : <b><?= e($d['confName']) ?></b> — niveau <?= (int)($d['confidentialityId']? substr($d['confidentialityId'],-1):'-') ?></div>
      <div>Visibilité : <b><?= e($d['visName']?:'Par défaut') ?></b></div>
      <div>Service propriétaire : <?= e($d['serviceName']) ?></div>
      <div class="text-xs text-slate-500">Les droits au dossier/document sont hérités du service + niveau de confidentialité. Un utilisateur ne voit le dossier que si son niveau ≥ niveau dossier et son service est autorisé (user_service_access).</div>
    </div>
    <div class="mt-4">
      <h4 class="font-medium text-sm">Chiffrement & journalisation</h4>
      <p class="text-xs text-slate-500">Chiffrement au repos (AES-256) côté stockage, TLS en transit. Toutes les actions sont journalisées (voir onglet Infos → Journal).</p>
      <div class="mt-2 text-xs bg-slate-50 border rounded-xl p-2 font-mono">Dossier ID: <?= e($d['id']) ?> • Créateur: <?= e($d['creatorId']) ?></div>
    </div>
  </div>
  <div class="card p-5">
    <h3 class="font-semibold flex items-center gap-2"><i data-lucide="smartphone" class="w-4 h-4"></i> MFA</h3>
    <p class="text-sm text-slate-500">Authentification à deux facteurs (TOTP) configurable dans Profil → Sécurité. ADMIN peut forcer MFA pour tout le service.</p>
    <a href="<?= BASE_URL ?>/dashboard/profil.php" class="inline-block mt-3 border px-4 py-2 rounded-xl text-sm">Gérer mon MFA</a>
  </div>
</div>
<?php elseif($tab==='archivage'): ?>
<div class="grid lg:grid-cols-2 gap-4">
  <div class="card p-5">
    <h3 class="font-semibold flex items-center gap-2"><i data-lucide="archive" class="w-4 h-4"></i> Archivage & rétention</h3>
    <form method="POST" class="space-y-3 mt-3 text-sm"><input type="hidden" name="tab" value="archivage">
      <div><label class="block text-sm font-medium mb-1">Politique de rétention</label><select name="retentionPolicyId" class="input"><option value="">— Aucune —</option><?php foreach($retentions as $r): ?><option value="<?= e($r['id']) ?>" <?= $d['retentionPolicyId']===$r['id']?'selected':'' ?>><?= e($r['name']) ?> (<?= (int)$r['durationDays']/365 ?> ans → <?= e($r['action']) ?>)</option><?php endforeach; ?></select></div>
      <label class="flex items-center gap-2"><input type="checkbox" name="legalHold" value="1" <?= $d['legalHold']?'checked':'' ?>> Gel juridique (legal hold) — bloque toute destruction</label>
      <?php if($d['retentionUntil']): ?><div class="text-xs">Échéance rétention : <b><?= date('d/m/Y',strtotime($d['retentionUntil'])) ?></b></div><?php endif; ?>
      <button name="update_retention" value="1" class="bg-slate-900 text-white px-4 py-2 rounded-xl">Enregistrer</button>
    </form>
    <div class="mt-4 flex gap-2">
      <a href="<?= BASE_URL ?>/api/dossier_archive.php?id=<?= e($id) ?>" class="border px-3 py-1.5 rounded-xl text-sm" onclick="return confirm('Archiver ?')">Archiver maintenant</a>
      <span class="text-xs text-slate-400">Export PDF/A via action Archive (génère copie conforme).</span>
    </div>
  </div>
  <div class="card p-5">
    <h4 class="font-semibold text-sm">Cycle de vie</h4>
    <ul class="text-sm text-slate-600 list-disc pl-5 mt-2 space-y-1">
      <li>Création → En cours → Clos → Archivé</li>
      <li>Rétention calculée automatiquement à partir de la politique</li>
      <li>Gel juridique prioritaire sur toute destruction</li>
      <li>Corbeille : conservation 30 jours avant purge</li>
    </ul>
  </div>
</div>
<?php elseif($tab==='transferts'): ?>
<div class="grid lg:grid-cols-3 gap-4">
  <div class="lg:col-span-2">
    <form method="POST" class="card p-5 space-y-4">
      <h3 class="font-semibold flex items-center gap-2"><i data-lucide="send" class="w-4 h-4"></i> Transférer vers un ou plusieurs services</h3>
      <p class="text-xs text-slate-500">Choisis un ou plusieurs services et/ou agents — chaque cible reçoit une transmission distincte et une notification.</p>
      <div>
        <label class="block text-sm font-medium mb-1">Services cibles</label>
        <select name="targetServiceIds[]" id="svcSelect" multiple size="5" class="input w-full h-32">
          <?php foreach($services as $s): ?><option value="<?= e($s['id']) ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
        </select>
        <p class="text-xs text-slate-400 mt-1">Maintiens Ctrl/Cmd pour sélection multiple</p>
      </div>
      <div id="agentsWrap" class="hidden">
        <label class="block text-sm font-medium mb-1">Agents cibles du/des service(s) sélectionné(s) (optionnel)</label>
        <div class="grid sm:grid-cols-2 gap-2 max-h-40 overflow-y-auto border rounded-xl p-3 bg-slate-50" id="agentsChecks">
          <?php
            $accessMap=$pdo->query("SELECT userId,serviceId FROM user_service_access")->fetchAll(PDO::FETCH_ASSOC);
            $byUser=[]; foreach($accessMap as $r){ $byUser[$r['userId']][]=$r['serviceId']; }
            foreach($users as $u){
              $svcList=array_unique(array_merge([$u['serviceId']??''], $byUser[$u['id']]??[]));
              $svcList=array_filter($svcList);
              $dataSvc=implode(',', $svcList);
              echo '<label data-services="'.e($dataSvc).'" class="agent-row hidden flex items-center gap-2 text-sm bg-white border rounded-lg px-3 py-2 cursor-pointer hover:bg-blue-50"><input type="checkbox" name="targetReceiverIds[]" value="'.e($u['id']).'" class="rounded"> '.e($u['firstName'].' '.$u['lastName']).' <span class="text-xs text-slate-400">('.e($u['serviceId']).')</span></label>';
            }
          ?>
        </div>
        <p class="text-xs text-slate-400 mt-1" id="agentsHint">Coche d'abord un service pour voir ses agents</p>
      </div>
      <script>
      (function(){
        const selEl=document.getElementById('svcSelect');
        const wrap=document.getElementById('agentsWrap');
        const rows=document.querySelectorAll('.agent-row');
        function upd(){
          const sel=[...selEl.selectedOptions].map(o=>o.value);
          if(sel.length===0){ wrap.classList.add('hidden'); rows.forEach(r=>{r.classList.add('hidden'); r.querySelector('input').checked=false;}); return; }
          wrap.classList.remove('hidden');
          let visible=0;
          rows.forEach(r=>{
            const svc=(r.dataset.services||'').split(',');
            const show=svc.some(s=>sel.includes(s));
            r.classList.toggle('hidden', !show);
            if(!show) r.querySelector('input').checked=false;
            else visible++;
          });
          document.getElementById('agentsHint').textContent= visible? visible+' agent(s) disponible(s)':'Aucun agent pour ce service';
        }
        selEl.addEventListener('change',upd);
      })();
      </script>
      <div class="grid sm:grid-cols-2 gap-3">
        <div><label class="block text-sm font-medium mb-1">Échéance / consigne</label><input type="date" name="transfer_deadline" class="input"></div>
        <div class="sm:col-span-1 flex items-end"><span class="text-xs text-slate-400">Informative — notée dans le commentaire</span></div>
      </div>
      <div><label class="block text-sm font-medium mb-1">Commentaire / instructions</label><textarea name="transfer_comment" rows="3" class="input" placeholder="Objet du transfert, actions attendues..."></textarea></div>
      <input type="hidden" name="tab" value="transferts">
      <button name="bulk_transfer" value="1" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl text-sm font-medium">Transférer la sélection</button>
    </form>
  </div>
  <div class="space-y-4">
    <div class="card p-4">
      <h3 class="font-semibold mb-3 flex items-center gap-2"><i data-lucide="history" class="w-4 h-4"></i> Historique des transferts</h3>
      <?php if(empty($transmissions)): ?><p class="text-sm text-slate-400">Aucun transfert</p><?php endif; ?>
      <?php foreach($transmissions as $t): ?>
        <div class="border rounded-xl px-3 py-2 mb-2 text-sm bg-white">
          <div class="flex justify-between text-xs text-slate-400"><span><?= e(($t['sfn']??'').' '.($t['sln']??'')) ?> → <?php if($t['targetServiceId']){ $sn=$pdo->query("SELECT name FROM services WHERE id='".addslashes($t['targetServiceId'])."'")->fetchColumn(); echo e($sn?:$t['targetServiceId']); } elseif($t['receiverId']){ $rn=$pdo->query("SELECT firstName,lastName FROM users WHERE id='".addslashes($t['receiverId'])."'")->fetch(); echo e(trim(($rn['firstName']??'').' '.($rn['lastName']??''))); } else echo '—'; ?></span><span><?= date('d/m/Y H:i',strtotime($t['createdAt'])) ?></span></div>
          <div class="text-xs mt-1"><?= e($t['comment']?:$t['recommendations']?:'—') ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="card p-4 bg-amber-50 border-amber-200">
      <p class="text-xs text-amber-800">Astuce : pour un transfert ciblé avec circuit (avis, délégation…) utilise aussi l'onglet <b>Infos → Traitements</b>. Ce nouvel onglet est le transfert massif multi-services.</p>
    </div>
  </div>
</div>
<?php endif; ?>
<script>
// AJAX sans rechargement pour transferts/traitements — php/dashboard/dossiers/view.php:728
(function(){
  const id=new URLSearchParams(location.search).get('id');
  function toast(msg, ok=true){
    let t=document.getElementById('ajaxToast');
    if(!t){ t=document.createElement('div'); t.id='ajaxToast'; t.className='fixed top-4 right-4 z-[60] px-4 py-3 rounded-xl text-sm font-medium shadow-lg border'; document.body.appendChild(t); }
    t.textContent=msg; t.className='fixed top-4 right-4 z-[60] px-4 py-3 rounded-xl text-sm font-medium shadow-lg border '+(ok?'bg-emerald-600 text-white border-emerald-700':'bg-red-600 text-white border-red-700');
    t.style.display='block'; clearTimeout(t._tm); t._tm=setTimeout(()=>t.style.display='none',4000);
  }
  document.querySelectorAll('form[method=\"POST\"]').forEach(f=>{
    // garder GET filters historique hors AJAX
    f.addEventListener('submit', async (e)=>{
      // si form a pas de champ POST discriminant (just GET), skip
      const hasAction = f.querySelector('[name=\"bulk_transfer\"],[name=\"actionType\"],[name=\"quick_action\"],[name=\"update_status\"],[name=\"upload_doc\"],[name=\"update_org\"],[name=\"update_retention\"],[name=\"start_workflow\"],[name=\"validate_step\"]');
      if(!hasAction) return;
      e.preventDefault();
      const btn=f.querySelector('button[type=\"submit\"],button:not([type])'); const orig=btn?btn.textContent:'';
      if(btn){ btn.disabled=true; btn.textContent='Envoi…'; }
      try{
        const fd=new FormData(f); fd.append('ajax','1');
        // FormData(f) n'inclut pas le name du bouton submitteur → forcer les flags PHP isset($_POST[...]) php/dashboard/dossiers/view.php:16/108
        if(f.querySelector('[name=\"bulk_transfer\"]')) fd.append('bulk_transfer','1');
        if(f.querySelector('[name=\"update_status\"]')) fd.append('update_status','1');
        if(f.querySelector('[name=\"upload_doc\"]')) fd.append('upload_doc','1');
        if(f.querySelector('[name=\"update_org\"]')) fd.append('update_org','1');
        if(f.querySelector('[name=\"update_retention\"]')) fd.append('update_retention','1');
        if(f.querySelector('[name=\"start_workflow\"]')) fd.append('start_workflow','1');
        if(f.querySelector('[name=\"validate_step\"]')) fd.append('validate_step','1');
        // quick_action / actionType passent par hidden quick_action/actionType déjà dans FormData
        const res=await fetch(location.pathname+'?id='+encodeURIComponent(id)+'&tab='+(f.querySelector('[name=\"tab\"]')?.value||new URLSearchParams(location.search).get('tab')||'infos'), {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}});
        const j=await res.json().catch(()=>null);
        if(j && j.ok!==false){ toast(j.msg||'Action effectuée ✓', true); f.reset(); const m=document.getElementById('traitementModal'); if(m) m.classList.add('hidden'); // reset service/agent selections si transfert
          const wraps=document.querySelectorAll('#agentsWrap .agent-row'); // keep hidden handled by change listener
          // petit refresh partiel du bloc transferts sans reload complet
          setTimeout(()=>location.reload(), 900);
        } else toast((j&&j.msg)||'Erreur', false);
      }catch(err){ toast('Erreur réseau', false); }
      finally{ if(btn){ btn.disabled=false; btn.textContent=orig; } }
    });
  });
})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
