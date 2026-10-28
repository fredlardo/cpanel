<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
requireLogin();
$user = currentUser();
$pdo = db();
$stmt = $pdo->prepare("SELECT COUNT(*) as c FROM notifications WHERE userId=? AND isRead=0");
$stmt->execute([$user['id']]);
$notifCount = (int)($stmt->fetch()['c'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(APP_NAME) ?> - <?= e($pageTitle ?? 'Dashboard') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="bg-[#f8fafc]">
<div class="flex flex-col min-h-screen">
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="flex-1 min-w-0">
<header class="bg-white border-b border-slate-200 px-6 py-3 flex items-center justify-between">
  <div class="flex items-center gap-3 leading-tight">
    <div><p class="text-[13px] text-slate-400"><?= e($user['entityName'] ?? 'État Central') ?> / <?= e($user['organizationName'] ?? 'Administration Centrale') ?></p>
    <p class="text-[12px] text-slate-400"><?= e($user['name']) ?> — <?= e($user['serviceName'] ?? 'Service Courrier') ?></p></div>
  </div>
  <div class="flex items-center gap-1 text-slate-500">
    <a href="<?= BASE_URL ?>/dashboard/rapport.php" class="hidden sm:inline-flex items-center gap-1.5 h-8 px-3 rounded-lg hover:bg-slate-100 text-sm font-medium" title="Reporting"><i data-lucide="bar-chart-3" class="w-[16px] h-[16px]"></i> Reporting</a>
    <a href="<?= BASE_URL ?>/dashboard/profil.php" class="h-8 w-8 flex items-center justify-center rounded-lg hover:bg-slate-100" title="Mon profil"><i data-lucide="user" class="w-[18px] h-[18px]"></i></a>
    <a href="<?= BASE_URL ?>/dashboard/notifications.php" class="relative h-8 w-8 flex items-center justify-center rounded-lg hover:bg-slate-100" title="Notifications"><i data-lucide="bell" class="w-[18px] h-[18px]"></i>
      <?php if($notifCount>0): ?><span class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold rounded-full h-5 min-w-[20px] px-1 flex items-center justify-center"><?= $notifCount ?></span><?php endif; ?>
    </a>
    <button class="h-8 w-8 flex items-center justify-center rounded-lg hover:bg-slate-100"><i data-lucide="menu" class="w-[18px] h-[18px]"></i></button>
  </div>
</header>
<?php if(!empty($_SESSION['flash_success'])): ?><div id="flashPush" class="mx-6 lg:mx-8 mt-4 bg-green-600 text-white px-4 py-3 rounded-xl flex items-center justify-between shadow-lg"><span class="flex items-center gap-2"><i data-lucide="check-circle" class="w-5 h-5"></i> <?= e($_SESSION['flash_success']) ?></span><button onclick="this.parentElement.remove()" class="text-white/80 hover:text-white text-xl leading-none">&times;</button></div><script>setTimeout(()=>document.getElementById('flashPush')?.remove(),4000)</script><?php unset($_SESSION['flash_success']); endif; ?>
<?php if(!empty($_SESSION['flash_error'])): ?><div id="flashErr" class="mx-6 lg:mx-8 mt-4 bg-red-600 text-white px-4 py-3 rounded-xl flex items-center justify-between shadow-lg"><span><?= e($_SESSION['flash_error']) ?></span><button onclick="this.parentElement.remove()" class="text-white/80 hover:text-white text-xl leading-none">&times;</button></div><script>setTimeout(()=>document.getElementById('flashErr')?.remove(),4000)</script><?php unset($_SESSION['flash_error']); endif; ?>
<?php
  // Bandeau + blocage licence par entité
  try{
    require_once __DIR__ . '/licence.php';
    $licPdo=$pdo ?? db(); licenceEnsureTables($licPdo);
    $entIdForBanner=getUserEntityId($licPdo, $user);
    if($entIdForBanner){
      $licSt=licenceStatus($licPdo,$entIdForBanner);
      if(in_array($licSt['status'],['GRACE','EXPIRED','SUSPENDED','REVOKED','INVALID'])){
        $col=$licSt['status']==='GRACE'?'bg-amber-500':($licSt['status']==='EXPIRED'?'bg-red-600':'bg-zinc-800');
        $isSuper=(currentUser()['role']??'')==='SUPERADMIN';
        $href=$isSuper?BASE_URL.'/dashboard/entites/licence.php?id='.urlencode($entIdForBanner):BASE_URL.'/dashboard/ma-licence.php';
        echo '<div class="mx-6 lg:mx-8 mt-3 '.$col.' text-white px-4 py-2 rounded-xl text-xs flex items-center justify-between"><span><i data-lucide="shield-alert" class="w-4 h-4 inline"></i> Licence '.$licSt['status'].' : '.e($licSt['msg']).'</span><a href="'.$href.'" class="underline ml-2">Gérer</a></div>';
        // blocage dur sauf dashboard/ma-licence/logout — fallback JS si headers déjà envoyés
        $uri=$_SERVER['REQUEST_URI']??''; $allowed=in_array($licSt['status'],['SUSPENDED','REVOKED','EXPIRED','INVALID']) && !str_contains($uri,'/ma-licence') && !str_contains($uri,'/dashboard/index.php') && !str_contains($uri,'/dashboard/index') && !str_contains($uri,'/logout');
        if($allowed && !str_contains($uri,'/ma-licence.php') && !str_contains($uri,'/api/')){
          if(!str_contains($uri,'/ma-licence') && !str_contains($uri,'/licence') && $licSt['status']!=='GRACE'){
            $_SESSION['flash_error']='Accès bloqué : licence '.$licSt['status'];
            if(!headers_sent()){ header('Location: '.BASE_URL.'/dashboard/ma-licence.php'); exit; }
            echo '<script>window.location.href='.json_encode(BASE_URL.'/dashboard/ma-licence.php').';</script><noscript><meta http-equiv="refresh" content="0;url='.e(BASE_URL.'/dashboard/ma-licence.php').'"></noscript>'; exit;
          }
        }
      }
    }
  }catch(Exception $e){}
?>
<div class="p-6 lg:p-8">
