<?php
ob_start();
// Export CSV AVANT tout output (évite headers already sent par header.php/sidebar.php)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$pdo=db();

// Filtres GET (déclarés avant export pour construire les requêtes)
$dateFrom = trim($_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days')));
$dateTo   = trim($_GET['date_to'] ?? date('Y-m-d'));
$filterService = trim($_GET['service_id'] ?? '');
$filterUser = trim($_GET['user_id'] ?? '');
$filterCat = trim($_GET['cat_id'] ?? '');
$filterGenre = trim($_GET['genre_id'] ?? '');
$filterDType = trim($_GET['dtype_id'] ?? '');
$filterStatut = trim($_GET['statut'] ?? '');
$filterType = trim($_GET['type'] ?? 'all');
$export = $_GET['export'] ?? '';
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateFrom)) $dateFrom=date('Y-m-d', strtotime('-30 days'));
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateTo)) $dateTo=date('Y-m-d');
if($dateFrom > $dateTo){ $tmp=$dateFrom; $dateFrom=$dateTo; $dateTo=$tmp; }
$dtFrom = $dateFrom.' 00:00:00';
$dtTo   = $dateTo.' 23:59:59';
$allowedTypes=['all','created','emitted','transferred'];
if(!in_array($filterType,$allowedTypes,true)) $filterType='all';
$allowedStatuts=['','NOUVEAU','EN_COURS','EN_ATTENTE','VALIDE','REJETE','CLOS','ANNULE','ARCHIVE'];
if(!in_array($filterStatut,$allowedStatuts,true)) $filterStatut='';

// Construction rapports — auto à la simple sélection (plus besoin de cliquer Générer)
$reportCreated=[]; $reportTransferred=[]; $hasSearch = true;

if($hasSearch){
  // --- Créés : dossiers ---
  $sqlC="SELECT d.id,d.reference,d.title,d.status,d.createdAt,d.creatorId, s.name as serviceName, u.firstName,u.lastName,
         (SELECT COUNT(*) FROM documents doc WHERE doc.dossierId=d.id AND doc.deletedAt IS NULL) as docCount
         FROM dossiers d
         LEFT JOIN users u ON u.id=d.creatorId
         LEFT JOIN services s ON s.id=d.serviceId
         WHERE d.createdAt BETWEEN ? AND ? AND d.deletedAt IS NULL";
   $paramsC=[$dtFrom,$dtTo];
   if($filterService!==''){ $sqlC.=" AND d.serviceId=?"; $paramsC[]=$filterService; }
   if($filterUser!==''){ $sqlC.=" AND d.creatorId=?"; $paramsC[]=$filterUser; }
   if($filterCat!==''){ $sqlC.=" AND d.categoryId=?"; $paramsC[]=$filterCat; }
   if($filterGenre!==''){ $sqlC.=" AND d.genreId=?"; $paramsC[]=$filterGenre; }
   if($filterDType!==''){ $sqlC.=" AND d.dossierTypeId=?"; $paramsC[]=$filterDType; }
   if($filterStatut!==''){ $sqlC.=" AND d.status=?"; $paramsC[]=$filterStatut; }
  if($filterType==='created' || $filterType==='all'){
    $sqlC.=" ORDER BY d.createdAt DESC LIMIT 500";
    $st=$pdo->prepare($sqlC); $st->execute($paramsC); $reportCreated=$st->fetchAll();
  }
  // --- Émis / Transférés : transmissions ---
  if($filterType==='transferred' || $filterType==='emitted' || $filterType==='all'){
    $sqlT="SELECT t.id,t.createdAt,t.comment,t.targetServiceId,t.receiverId,t.senderId,
           d.reference,d.title,d.status as dossierStatus,
           su.firstName as sfn, su.lastName as sln, ss.name as senderSvc,
           ru.firstName as rfn, ru.lastName as rln, ts.name as targetSvcName
           FROM transmissions t
           JOIN dossiers d ON d.id=t.dossierId
           LEFT JOIN users su ON su.id=t.senderId
           LEFT JOIN services ss ON ss.id=su.serviceId
           LEFT JOIN users ru ON ru.id=t.receiverId
           LEFT JOIN services ts ON ts.id=t.targetServiceId
           WHERE t.createdAt BETWEEN ? AND ?";
    $paramsT=[$dtFrom,$dtTo];
    if($filterType==='emitted'){
      // émis par service/individu = sender
      if($filterUser!==''){ $sqlT.=" AND t.senderId=?"; $paramsT[]=$filterUser; }
      elseif($filterService!==''){ $sqlT.=" AND su.serviceId=?"; $paramsT[]=$filterService; }
    } elseif($filterType==='transferred'){
      // transférés vers service/individu = cible
      if($filterUser!==''){ $sqlT.=" AND (t.receiverId=? OR t.receiverId IN (SELECT id FROM users WHERE serviceId=?))"; $paramsT[]=$filterUser; $paramsT[]=$filterService?:''; // fallback
        // simplifié: si user filtré, on prend receiver = user
        // si service aussi, on élargit
        // pour éviter double param vide, on refait propre si service vide:
        if($filterService===''){ $sqlT=str_replace(" AND (t.receiverId=? OR t.receiverId IN (SELECT id FROM users WHERE serviceId=?))"," AND t.receiverId=?",$sqlT); $paramsT=[$dtFrom,$dtTo,$filterUser]; }
      } elseif($filterService!==''){ $sqlT.=" AND (t.targetServiceId=? OR ru.serviceId=?)"; $paramsT[]=$filterService; $paramsT[]=$filterService; }
    } else { // all = émis ou reçu
      if($filterUser!==''){ $sqlT.=" AND (t.senderId=? OR t.receiverId=?)"; $paramsT[]=$filterUser; $paramsT[]=$filterUser; }
      elseif($filterService!==''){ $sqlT.=" AND (t.targetServiceId=? OR su.serviceId=? OR ru.serviceId=?)"; $paramsT[]=$filterService; $paramsT[]=$filterService; $paramsT[]=$filterService; }
    }
    // axes dossier génériques
    if($filterCat!==''){ $sqlT.=" AND d.categoryId=?"; $paramsT[]=$filterCat; }
    if($filterGenre!==''){ $sqlT.=" AND d.genreId=?"; $paramsT[]=$filterGenre; }
    if($filterDType!==''){ $sqlT.=" AND d.dossierTypeId=?"; $paramsT[]=$filterDType; }
    if($filterStatut!==''){ $sqlT.=" AND d.status=?"; $paramsT[]=$filterStatut; }
    $sqlT.=" ORDER BY t.createdAt DESC LIMIT 500";
     $st=$pdo->prepare($sqlT); $st->execute($paramsT); $reportTransferred=$st->fetchAll();
   }
 }

// Export CSV déporté vers rapport_export.php (évite headers already sent via sidebar.php)
// Si ?export=csv demandé sur cette page, redirige vers l'endpoint dédié sans HTML
if($export==='csv'){
  $qs=http_build_query(array_filter($_GET,function($v){return $v!=='csv';},ARRAY_FILTER_USE_KEY));
  // garde tous les filtres, remplace export par csv propre
  header('Location: '.BASE_URL.'/dashboard/rapport_export.php?'.http_build_query($_GET));
  exit;
}
$pageTitle='Reporting — Édition par service / individu';
require_once __DIR__ . '/../includes/header.php';
$u=currentUser();
$services=$pdo->query("SELECT id,name FROM services WHERE isActive=1 ORDER BY name")->fetchAll();
$usersAll=$pdo->query("SELECT u.id,u.firstName,u.lastName,u.serviceId,s.name as svcName FROM users u LEFT JOIN services s ON s.id=u.serviceId WHERE u.isActive=1 ORDER BY u.firstName")->fetchAll();
$cats=$pdo->query("SELECT id,name FROM categories WHERE isActive=1 ORDER BY name")->fetchAll();
$genres=$pdo->query("SELECT id,name FROM genres WHERE isActive=1 ORDER BY name")->fetchAll();
$types=$pdo->query("SELECT id,name FROM dossier_types WHERE isActive=1 ORDER BY name")->fetchAll();
?>
<style>@media print{ header,aside,.no-print{ display:none !important; } main{ padding:0 !important; } .card{ box-shadow:none; border:1px solid #ddd; } }</style>
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
  <h1 class="text-xl font-semibold flex items-center gap-2"><i data-lucide="file-text" class="w-5 h-5"></i> Reporting — édition des rapports</h1>
  <a href="<?= BASE_URL ?>/dashboard/statistiques/index.php" class="border bg-white px-3 py-2 rounded-xl text-sm hover:bg-slate-50 no-print">Statistiques</a>
</div>

<div class="card p-5 no-print">
  <h3 class="font-semibold mb-3">Filtres — période donnée</h3>
  <form method="GET" id="rapportForm" class="space-y-3">
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
    <div>
      <label class="block text-xs font-medium mb-1">Du</label>
      <input type="date" name="date_from" value="<?= e($dateFrom) ?>" class="input text-sm auto-submit">
    </div>
    <div>
      <label class="block text-xs font-medium mb-1">Au</label>
      <input type="date" name="date_to" value="<?= e($dateTo) ?>" class="input text-sm auto-submit">
    </div>
    <div>
      <label class="block text-xs font-medium mb-1">Service</label>
      <select name="service_id" id="repService" class="input text-sm auto-submit">
        <option value="">— Tous les services —</option>
        <?php foreach($services as $s): ?><option value="<?= e($s['id']) ?>" <?= $filterService===$s['id']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs font-medium mb-1">Individu (dans service)</label>
      <select name="user_id" id="repUser" class="input text-sm auto-submit">
        <option value="">— Tous —</option>
        <?php foreach($usersAll as $uu): ?><option data-service="<?= e($uu['serviceId']) ?>" value="<?= e($uu['id']) ?>" <?= $filterUser===$uu['id']?'selected':'' ?>><?= e($uu['firstName'].' '.$uu['lastName']) ?> — <?= e($uu['svcName']) ?></option><?php endforeach; ?>
      </select>
    </div>
    </div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
    <div>
      <label class="block text-xs font-medium mb-1">Catégorie</label>
      <select name="cat_id" class="input text-sm auto-submit">
        <option value="">— Toutes —</option>
        <?php foreach($cats as $c): ?><option value="<?= e($c['id']) ?>" <?= $filterCat===$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs font-medium mb-1">Genre</label>
      <select name="genre_id" class="input text-sm auto-submit">
        <option value="">— Tous —</option>
        <?php foreach($genres as $g): ?><option value="<?= e($g['id']) ?>" <?= $filterGenre===$g['id']?'selected':'' ?>><?= e($g['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs font-medium mb-1">Type dossier</label>
      <select name="dtype_id" class="input text-sm auto-submit">
        <option value="">— Tous —</option>
        <?php foreach($types as $t): ?><option value="<?= e($t['id']) ?>" <?= $filterDType===$t['id']?'selected':'' ?>><?= e($t['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs font-medium mb-1">Statut dossier</label>
      <select name="statut" class="input text-sm auto-submit">
        <option value="">— Tous statuts —</option>
        <?php foreach(['NOUVEAU','EN_COURS','EN_ATTENTE','VALIDE','REJETE','CLOS','ANNULE','ARCHIVE'] as $st): ?><option value="<?= $st ?>" <?= $filterStatut===$st?'selected':'' ?>><?= $st ?></option><?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs font-medium mb-1">Type</label>
      <select name="type" class="input text-sm auto-submit">
        <option value="all" <?= $filterType==='all'?'selected':'' ?>>Tous (créés + transférés)</option>
        <option value="created" <?= $filterType==='created'?'selected':'' ?>>Créés</option>
        <option value="emitted" <?= $filterType==='emitted'?'selected':'' ?>>Émis (par)</option>
        <option value="transferred" <?= $filterType==='transferred'?'selected':'' ?>>Transférés (vers)</option>
      </select>
    </div>
    <div class="flex gap-2">
      <button name="generate" value="1" class="flex-1 bg-slate-900 text-white px-4 py-2 rounded-xl text-sm">Générer</button>
      <a href="<?= BASE_URL ?>/dashboard/rapport.php" class="border bg-white px-3 py-2 rounded-xl text-sm">Reset</a>
    </div>
    </div>
  </form>
  <script>
  (function(){
    const form=document.getElementById('rapportForm');
    const svc=document.getElementById('repService');
    const usr=document.getElementById('repUser');
    function filterUsers(){
      const sid=svc.value;
      [...usr.options].forEach(o=>{
        if(!o.value) return;
        const ds=o.dataset.service;
        o.hidden = sid && ds!==sid;
        if(o.hidden && o.selected){ o.selected=false; usr.value=''; }
      });
    }
    svc.addEventListener('change', ()=>{ filterUsers(); form.submit(); });
    filterUsers();
    // auto-submit à la simple sélection — plus besoin de Générer
    form.querySelectorAll('.auto-submit').forEach(el=>{
      if(el===svc) return; // déjà géré
      el.addEventListener('change', ()=> form.submit());
    });
    // caché Générer mais gardé pour compat — on le masque visuellement
    const genBtn=form.querySelector('button[name=\"generate\"]');
    if(genBtn) genBtn.style.display='none';
  })();
  </script>
</div>

<?php if($hasSearch): ?>
<div class="flex flex-wrap items-center justify-between gap-2 my-4 no-print">
  <p class="text-sm text-slate-600">Période <b><?= e($dateFrom) ?> → <?= e($dateTo) ?></b><?= $filterService? ' · Service: <b>'.e($pdo->query("SELECT name FROM services WHERE id=". $pdo->quote($filterService))->fetchColumn()).'</b>':'' ?><?= $filterUser? ' · Individu: <b>'.e($pdo->query("SELECT firstName||' '||lastName FROM users WHERE id=". $pdo->quote($filterUser))->fetchColumn() ?: $pdo->query("SELECT firstName FROM users WHERE id=". $pdo->quote($filterUser))->fetchColumn()).'</b>':'' ?><?= $filterStatut? ' · Statut: <b>'.e($filterStatut).'</b>':'' ?> · <span class="capitalize"><?= e($filterType) ?></span></p>
  <div class="flex gap-2">
    <a href="<?= BASE_URL ?>/dashboard/rapport_export.php?<?= http_build_query(array_merge($_GET,['export'=>'csv'])) ?>" class="border bg-white px-3 py-2 rounded-xl text-sm hover:bg-slate-50">Exporter CSV</a>
    <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded-xl text-sm">Imprimer / PDF</button>
  </div>
</div>

<?php if($filterType==='created' || $filterType==='all'): ?>
<div class="card p-0 overflow-hidden mb-6">
  <div class="px-5 py-3 border-b flex items-center justify-between">
    <h3 class="font-semibold flex items-center gap-2"><i data-lucide="folder-plus" class="w-4 h-4"></i> Documents / Dossiers créés — <?= count($reportCreated) ?> résultat(s)</h3>
    <span class="text-xs text-slate-400">tri date décroissante · limite 500</span>
  </div>
  <div class="overflow-auto max-h-[420px]">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-xs text-slate-400 sticky top-0">
        <tr><th class="text-left px-3 py-2">Référence</th><th class="text-left px-3 py-2">Titre</th><th class="text-left px-3 py-2">Service</th><th class="text-left px-3 py-2">Créé par</th><th class="text-left px-3 py-2">Date</th><th class="text-left px-3 py-2">Statut</th><th class="text-center px-3 py-2">Docs</th></tr>
      </thead>
      <tbody>
        <?php if(empty($reportCreated)): ?><tr><td colspan="7" class="px-3 py-8 text-center text-slate-400">Aucun dossier créé sur la période / filtre</td></tr><?php else: foreach($reportCreated as $r): ?>
        <tr class="border-t hover:bg-slate-50"><td class="px-3 py-2 font-mono text-xs"><?= e($r['reference']) ?></td><td class="px-3 py-2"><a href="<?= BASE_URL ?>/dashboard/dossiers/view.php?id=<?= e($r['id']) ?>" class="text-blue-600 hover:underline"><?= e(mb_strimwidth($r['title'],0,60,'…')) ?></a></td><td class="px-3 py-2 text-xs"><?= e($r['serviceName']) ?></td><td class="px-3 py-2 text-xs"><?= e(trim($r['firstName'].' '.$r['lastName'])) ?></td><td class="px-3 py-2 text-xs"><?= date('d/m/Y H:i',strtotime($r['createdAt'])) ?></td><td class="px-3 py-2"><span class="text-xs px-2 py-0.5 rounded-full border"><?= e($r['status']) ?></span></td><td class="px-3 py-2 text-center"><?= (int)$r['docCount'] ?></td></tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if($filterType!=='created'): ?>
<div class="card p-0 overflow-hidden">
  <div class="px-5 py-3 border-b flex items-center justify-between">
    <h3 class="font-semibold flex items-center gap-2"><i data-lucide="send" class="w-4 h-4"></i> <?= $filterType==='emitted'?'Dossiers émis (par service/individu)':'Dossiers transférés' ?> — <?= count($reportTransferred) ?> transmission(s)</h3>
    <span class="text-xs text-slate-400">tri date décroissante · limite 500</span>
  </div>
  <div class="overflow-auto max-h-[520px]">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-xs text-slate-400 sticky top-0">
        <tr><th class="text-left px-3 py-2">Dossier</th><th class="text-left px-3 py-2">Titre</th><th class="text-left px-3 py-2">Émis par</th><th class="text-left px-3 py-2">Vers</th><th class="text-left px-3 py-2">Date transfert</th><th class="text-left px-3 py-2">Commentaire</th></tr>
      </thead>
      <tbody>
        <?php if(empty($reportTransferred)): ?><tr><td colspan="6" class="px-3 py-8 text-center text-slate-400">Aucune transmission sur la période / filtre</td></tr><?php else: foreach($reportTransferred as $t): $vers=$t['targetSvcName']?:trim(($t['rfn']??'').' '.($t['rln']??'')); ?>
        <tr class="border-t hover:bg-slate-50"><td class="px-3 py-2 font-mono text-xs"><?= e($t['reference']) ?></td><td class="px-3 py-2"><a href="<?= BASE_URL ?>/dashboard/dossiers/view.php?id=<?= e($t['dossierId']??'') ?>" class="text-blue-600 hover:underline"><?= e(mb_strimwidth($t['title'],0,50,'…')) ?></a></td><td class="px-3 py-2 text-xs"><?= e(trim(($t['sfn']??'').' '.($t['sln']??''))) ?><br><span class="text-slate-400"><?= e($t['senderSvc']??'') ?></span></td><td class="px-3 py-2 text-xs"><?= e($vers?:'—') ?></td><td class="px-3 py-2 text-xs"><?= date('d/m/Y H:i',strtotime($t['createdAt'])) ?></td><td class="px-3 py-2 text-xs max-w-[260px]"><?= e(mb_strimwidth($t['comment']??'',0,80,'…')) ?></td></tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php else: ?>
<div class="card p-8 text-center text-sm text-slate-400">Choisis une période, un service et/ou un individu puis <b>Générer</b> pour éditer le rapport. Exporte en CSV ou imprime en PDF.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>