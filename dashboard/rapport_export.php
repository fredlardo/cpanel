<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$pdo=db();
$dateFrom = trim($_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days')));
$dateTo   = trim($_GET['date_to'] ?? date('Y-m-d'));
$filterService = trim($_GET['service_id'] ?? '');
$filterUser = trim($_GET['user_id'] ?? '');
$filterCat = trim($_GET['cat_id'] ?? '');
$filterGenre = trim($_GET['genre_id'] ?? '');
$filterDType = trim($_GET['dtype_id'] ?? '');
$filterStatut = trim($_GET['statut'] ?? '');
$filterType = trim($_GET['type'] ?? 'all');
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateFrom)) $dateFrom=date('Y-m-d', strtotime('-30 days'));
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateTo)) $dateTo=date('Y-m-d');
if($dateFrom > $dateTo){ $tmp=$dateFrom; $dateFrom=$dateTo; $dateTo=$tmp; }
$dtFrom=$dateFrom.' 00:00:00'; $dtTo=$dateTo.' 23:59:59';
$allowedTypes=['all','created','emitted','transferred'];
if(!in_array($filterType,$allowedTypes,true)) $filterType='all';
$allowedStatuts=['','NOUVEAU','EN_COURS','EN_ATTENTE','VALIDE','REJETE','CLOS','ANNULE','ARCHIVE'];
if(!in_array($filterStatut,$allowedStatuts,true)) $filterStatut='';
$reportCreated=[]; $reportTransferred=[];
$sqlC="SELECT d.id,d.reference,d.title,d.status,d.createdAt,d.creatorId, s.name as serviceName, u.firstName,u.lastName,(SELECT COUNT(*) FROM documents doc WHERE doc.dossierId=d.id AND doc.deletedAt IS NULL) as docCount FROM dossiers d LEFT JOIN users u ON u.id=d.creatorId LEFT JOIN services s ON s.id=d.serviceId WHERE d.createdAt BETWEEN ? AND ? AND d.deletedAt IS NULL";
$paramsC=[$dtFrom,$dtTo];
if($filterService!==''){ $sqlC.=" AND d.serviceId=?"; $paramsC[]=$filterService; }
if($filterUser!==''){ $sqlC.=" AND d.creatorId=?"; $paramsC[]=$filterUser; }
if($filterCat!==''){ $sqlC.=" AND d.categoryId=?"; $paramsC[]=$filterCat; }
if($filterGenre!==''){ $sqlC.=" AND d.genreId=?"; $paramsC[]=$filterGenre; }
if($filterDType!==''){ $sqlC.=" AND d.dossierTypeId=?"; $paramsC[]=$filterDType; }
if($filterStatut!==''){ $sqlC.=" AND d.status=?"; $paramsC[]=$filterStatut; }
if($filterType==='created' || $filterType==='all'){ $sqlC.=" ORDER BY d.createdAt DESC LIMIT 500"; $st=$pdo->prepare($sqlC); $st->execute($paramsC); $reportCreated=$st->fetchAll(); }
if($filterType==='transferred' || $filterType==='emitted' || $filterType==='all'){
  $sqlT="SELECT t.id,t.createdAt,t.comment,t.targetServiceId,t.receiverId,t.senderId,d.reference,d.title,d.status as dossierStatus,su.firstName as sfn, su.lastName as sln, ss.name as senderSvc,ru.firstName as rfn, ru.lastName as rln, ts.name as targetSvcName FROM transmissions t JOIN dossiers d ON d.id=t.dossierId LEFT JOIN users su ON su.id=t.senderId LEFT JOIN services ss ON ss.id=su.serviceId LEFT JOIN users ru ON ru.id=t.receiverId LEFT JOIN services ts ON ts.id=t.targetServiceId WHERE t.createdAt BETWEEN ? AND ?";
  $paramsT=[$dtFrom,$dtTo];
  if($filterType==='emitted'){ if($filterUser!==''){ $sqlT.=" AND t.senderId=?"; $paramsT[]=$filterUser; } elseif($filterService!==''){ $sqlT.=" AND su.serviceId=?"; $paramsT[]=$filterService; } }
  elseif($filterType==='transferred'){ if($filterUser!==''){ $sqlT.=" AND (t.receiverId=? OR t.receiverId IN (SELECT id FROM users WHERE serviceId=?))"; $paramsT[]=$filterUser; $paramsT[]=$filterService?:''; if($filterService===''){ $sqlT=str_replace(" AND (t.receiverId=? OR t.receiverId IN (SELECT id FROM users WHERE serviceId=?))"," AND t.receiverId=?",$sqlT); $paramsT=[$dtFrom,$dtTo,$filterUser]; } } elseif($filterService!==''){ $sqlT.=" AND (t.targetServiceId=? OR ru.serviceId=?)"; $paramsT[]=$filterService; $paramsT[]=$filterService; } }
  else { if($filterUser!==''){ $sqlT.=" AND (t.senderId=? OR t.receiverId=?)"; $paramsT[]=$filterUser; $paramsT[]=$filterUser; } elseif($filterService!==''){ $sqlT.=" AND (t.targetServiceId=? OR su.serviceId=? OR ru.serviceId=?)"; $paramsT[]=$filterService; $paramsT[]=$filterService; $paramsT[]=$filterService; } }
  if($filterCat!==''){ $sqlT.=" AND d.categoryId=?"; $paramsT[]=$filterCat; }
  if($filterGenre!==''){ $sqlT.=" AND d.genreId=?"; $paramsT[]=$filterGenre; }
  if($filterDType!==''){ $sqlT.=" AND d.dossierTypeId=?"; $paramsT[]=$filterDType; }
  if($filterStatut!==''){ $sqlT.=" AND d.status=?"; $paramsT[]=$filterStatut; }
  $sqlT.=" ORDER BY t.createdAt DESC LIMIT 500"; $st=$pdo->prepare($sqlT); $st->execute($paramsT); $reportTransferred=$st->fetchAll();
}
while(ob_get_level()>0) ob_end_clean();
$filename='rapport_'.str_replace('-','',$dateFrom).'_'.str_replace('-','',$dateTo).'_'.$filterType.($filterStatut?'_'.strtolower($filterStatut):'').'.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: no-cache, must-revalidate');
echo "\xEF\xBB\xBF";
$out=fopen('php://output','w');
if($filterType==='created' || $filterType==='all'){
  fputcsv($out,['CREES — Dossiers créés'],';');
  fputcsv($out,['Reference','Titre','Service','Créé par','Date création','Statut','Nb docs'],';');
  foreach($reportCreated as $r){ fputcsv($out,[$r['reference'],$r['title'],$r['serviceName'],$r['firstName'].' '.$r['lastName'],$r['createdAt'],$r['status'],$r['docCount']],';'); }
  fputcsv($out,[],';');
}
if($filterType!=='created'){
  $label=$filterType==='emitted'?'EMIS — Dossiers émis (par)':'TRANSFERES — Transmissions';
  fputcsv($out,[$label],';');
  fputcsv($out,['Dossier','Titre','Émis par','Service émetteur','Vers (service/personne)','Date','Commentaire','Statut dossier'],';');
  foreach($reportTransferred as $t){ $vers=$t['targetSvcName'] ? $t['targetSvcName'] : trim(($t['rfn']??'').' '.($t['rln']??'')); fputcsv($out,[$t['reference'],$t['title'],trim(($t['sfn']??'').' '.($t['sln']??'')),$t['senderSvc'],$vers?:'—',$t['createdAt'],$t['comment'],$t['dossierStatus']??''], ';'); }
}
exit;
