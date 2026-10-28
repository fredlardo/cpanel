<?php
$pageTitle='Dossiers';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';
$pdo=db();
$user=currentUser();
$isAdmin=$user['role']==='ADMIN';
$filter=$_GET['filter']??'';
$search=trim($_GET['q']??'');
$status=$_GET['status']??'';
$page=max(1,(int)($_GET['page']??1));
$limit=10; $offset=($page-1)*$limit;

// Determine mode
$isInstruire = $filter==='instruire';
$isSuivi = $filter==='suivi';

// Build query per mode
$params=[];
$total=0; $rows=[];

if($isInstruire){
  // Bureau de traitement : transmissions received (direct au user OU au service de l'utilisateur)
  $ids = $user['accessServiceIds'] ?? [];
  if(empty($ids) && !empty($user['serviceId'])) $ids = [$user['serviceId']];
  if(!empty($ids)){
    $in = implode(',', array_fill(0,count($ids),'?'));
    $where="d.deletedAt IS NULL AND d.archivedAt IS NULL AND t.status!='FINALISEE' AND (t.receiverId = ? OR t.targetServiceId IN ($in)) AND (d.currentHolderId IS NULL OR d.currentHolderId=? OR t.status='PENDING')";
    $params[]=$user['id'];
    foreach($ids as $sid) $params[]=$sid;
    $params[]=$user['id'];
  } else {
    $where="t.receiverId = ? AND d.deletedAt IS NULL AND d.archivedAt IS NULL AND t.status!='FINALISEE' AND (d.currentHolderId IS NULL OR d.currentHolderId=?)";
    $params[]=$user['id'];
    $params[]=$user['id'];
  }
  if($search!==''){ $where.=" AND (d.title LIKE ? OR d.reference LIKE ?)"; $params[]="%$search%"; $params[]="%$search%"; }
  if($status && $status!=='ALL' && $status!=='TOUS'){
     if($status==='traite'){ $where.=" AND d.status IN ('CLOS','ANNULE','REJETE')"; }
     else { $where.=" AND d.status=?"; $params[]=$status; }
  } else if($status==='traite'){ $where.=" AND d.status IN ('CLOS','ANNULE','REJETE')"; }
  // count
  $stmt=$pdo->prepare("SELECT COUNT(*) as c FROM transmissions t JOIN dossiers d ON d.id=t.dossierId WHERE $where");
  $stmt->execute($params);
  $total=(int)$stmt->fetch()['c'];
  $pages=max(1,ceil($total/$limit));
  // fetch
  $sql="SELECT d.*, d.status as dStatus, t.id as transmissionId, t.status as transStatus, t.targetServiceId, t.receiverId, t.createdAt as transmissionDate, t.senderId, u.firstName,u.lastName,s.name as serviceName,p.name as priorityName,p.color as priorityColor,c.name as catName,
        (SELECT COUNT(*) FROM documents doc WHERE doc.dossierId=d.id AND doc.deletedAt IS NULL) as docCount
        FROM transmissions t
        JOIN dossiers d ON d.id=t.dossierId
        LEFT JOIN users u ON u.id=t.senderId
        LEFT JOIN services s ON s.id=d.serviceId
        LEFT JOIN priorities p ON p.id=d.priorityId
        LEFT JOIN categories c ON c.id=d.categoryId
        WHERE $where ORDER BY t.createdAt DESC LIMIT $limit OFFSET $offset";
  $stmt=$pdo->prepare($sql);
  $stmt->execute($params);
  $rows=$stmt->fetchAll();
}else{
  if($isSuivi){
    $where="d.deletedAt IS NULL AND d.archivedAt IS NULL AND d.creatorId=? AND d.status NOT IN ('CLOS','ANNULE') AND (d.currentHolderId IS NULL OR d.currentHolderId=?)";
    $params[]=$user['id']; $params[]=$user['id'];
    $title='Dossiers en traitement';
    $subtitle='Suivez l\'évolution de vos dossiers';
  }else{
    // Mes dossiers : creatorId = me, hors archives — si holder ailleurs, masqué jusqu'au retour
    $where="d.deletedAt IS NULL AND d.archivedAt IS NULL AND d.creatorId=? AND (d.currentHolderId IS NULL OR d.currentHolderId=?)";
    $params[]=$user['id']; $params[]=$user['id'];
    $title='Mes dossiers';
    $subtitle='Gérez vos dossiers électroniques';
    // ADMIN alternative: voir tous -> décommenter pour voir tout
    // if($isAdmin){ $where="d.deletedAt IS NULL"; $params=[]; $title='Tous les dossiers'; }
  }
  if($search!==''){ $where.=" AND (d.title LIKE ? OR d.reference LIKE ?)"; $params[]="%$search%"; $params[]="%$search%"; }
  if($status && $status!=='ALL' && $status!=='TOUS'){ $where.=" AND d.status=?"; $params[]=$status; }
  if($isSuivi && $status==='TOUS'){ /* no extra */ }
  $stmt=$pdo->prepare("SELECT COUNT(*) as c FROM dossiers d WHERE $where");
  $stmt->execute($params);
  $total=(int)$stmt->fetch()['c'];
  $pages=max(1,ceil($total/$limit));
  $stmt=$pdo->prepare("SELECT d.*, u.firstName,u.lastName,s.name as serviceName,p.name as priorityName,p.color as priorityColor,c.name as catName, (SELECT COUNT(*) FROM documents doc WHERE doc.dossierId=d.id AND doc.deletedAt IS NULL) as docCount FROM dossiers d LEFT JOIN users u ON u.id=d.creatorId LEFT JOIN services s ON s.id=d.serviceId LEFT JOIN priorities p ON p.id=d.priorityId LEFT JOIN categories c ON c.id=d.categoryId WHERE $where ORDER BY d.updatedAt DESC LIMIT $limit OFFSET $offset");
  $stmt->execute($params);
  $rows=$stmt->fetchAll();
}

// helpers for pagination qs
function qs(array $extra=[]){ $base=['q'=>$_GET['q']??'','status'=>$_GET['status']??'','filter'=>$_GET['filter']??'']; return http_build_query(array_merge($base,$extra)); }
if($isInstruire){ $pageTitle='Dossiers reçus'; $headerTitle='Dossiers reçus'; $headerSub='Dossiers transmis pour traitement'; }
elseif($isSuivi){ $pageTitle='À suivre'; $headerTitle='Dossiers en traitement'; $headerSub='Suivez l\'évolution de vos dossiers'; }
else{ $headerTitle='Mes dossiers'; $headerSub='Gérez vos dossiers électroniques'; }
?>
<?php if($isInstruire): ?>
<div class="flex items-center justify-between mb-4">
  <div><h1 class="text-2xl font-bold text-slate-900"><?= e($headerTitle) ?></h1><p class="text-sm text-slate-500"><?= e($headerSub) ?></p></div>
  <div class="relative hidden md:block">
    <form method="GET"><input type="hidden" name="filter" value="instruire"><input type="hidden" name="status" value="<?= e($status) ?>"><div class="relative"><i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i><input name="q" value="<?= e($search) ?>" placeholder="Rechercher un dossier..." class="input pl-9 w-64"></div></form>
  </div>
</div>
<?php
  // stats for instruire (direct + service)
  $idsS = $user['accessServiceIds'] ?? [];
  if(empty($idsS) && !empty($user['serviceId'])) $idsS = [$user['serviceId']];
  if(!empty($idsS)){
    $inS = implode(',', array_fill(0,count($idsS),'?'));
    $statsStmt=$pdo->prepare("SELECT d.status, COUNT(*) as c FROM transmissions t JOIN dossiers d ON d.id=t.dossierId WHERE d.deletedAt IS NULL AND d.archivedAt IS NULL AND (t.receiverId = ? OR t.targetServiceId IN ($inS)) GROUP BY d.status");
    $statsParams = array_merge([$user['id']], $idsS);
    $statsStmt->execute($statsParams);
  } else {
    $statsStmt=$pdo->prepare("SELECT d.status, COUNT(*) as c FROM transmissions t JOIN dossiers d ON d.id=t.dossierId WHERE t.receiverId=? AND d.deletedAt IS NULL AND d.archivedAt IS NULL GROUP BY d.status");
    $statsStmt->execute([$user['id']]);
  }
 $sc=[]; foreach($statsStmt->fetchAll() as $r) $sc[$r['status']]=(int)$r['c'];
 $nouveau=$sc['NOUVEAU']??0; $encours=$sc['EN_COURS']??0; $attente=$sc['EN_ATTENTE']??0; $traite=($sc['CLOS']??0)+($sc['ANNULE']??0)+($sc['REJETE']??0);
?>
<div class="grid grid-cols-4 gap-4 mb-4">
  <div class="card p-4"><p class="text-xs text-slate-500 mb-1">Nouveaux</p><p class="text-2xl font-bold text-blue-600"><?= $nouveau ?></p></div>
  <div class="card p-4"><p class="text-xs text-slate-500 mb-1">En cours</p><p class="text-2xl font-bold text-amber-600"><?= $encours ?></p></div>
  <div class="card p-4"><p class="text-xs text-slate-500 mb-1">En attente</p><p class="text-2xl font-bold text-orange-600"><?= $attente ?></p></div>
  <div class="card p-4"><p class="text-xs text-slate-500 mb-1">Traités</p><p class="text-2xl font-bold text-green-600"><?= $traite ?></p></div>
</div>
<div class="flex items-center gap-3 mb-4">
  <form method="GET" class="flex gap-2">
    <input type="hidden" name="filter" value="instruire"><input type="hidden" name="q" value="<?= e($search) ?>">
    <select name="status" onchange="this.form.submit()" class="input w-40"><option value="all" <?= ($status==='all'||$status==='')?'selected':'' ?>>Tous</option><option value="NOUVEAU" <?= $status==='NOUVEAU'?'selected':'' ?>>Nouveaux</option><option value="EN_COURS" <?= $status==='EN_COURS'?'selected':'' ?>>En cours</option><option value="EN_ATTENTE" <?= $status==='EN_ATTENTE'?'selected':'' ?>>En attente</option><option value="traite" <?= $status==='traite'?'selected':'' ?>>Traités</option></select>
  </form>
  <span class="text-xs text-slate-400 ml-auto"><?= $total ?> dossier(s) à instruire</span>
</div>
<?php if(empty($rows)): ?>
  <div class="card p-12 text-center"><i data-lucide="inbox" class="w-10 h-10 mx-auto mb-3 opacity-30"></i><p class="font-medium text-slate-600">Aucun dossier à traiter</p><p class="text-xs text-slate-400 mt-1">Les dossiers qui vous seront transmis apparaîtront ici</p></div>
<?php else: ?>
<div class="space-y-3">
<?php foreach($rows as $r): $isNew=$r['status']==='NOUVEAU'; ?>
  <div class="card overflow-hidden <?= $isNew?'border-blue-200 ring-1 ring-blue-100':'' ?>">
    <div class="px-4 py-3 flex items-center justify-between">
      <div class="flex items-center gap-3 min-w-0 flex-1">
        <div class="rounded-full p-2 <?= $isNew?'bg-blue-50 text-blue-600':'bg-zinc-50 text-slate-400' ?>"><i data-lucide="mail" class="w-4 h-4"></i></div>
        <div class="min-w-0">
          <div class="flex items-center gap-2"><span class="text-xs font-mono text-slate-500"><?= e($r['reference']) ?></span><span><?= statusBadge($r['status']) ?></span></div>
          <a href="view.php?id=<?= e($r['id']) ?>" class="text-sm font-medium hover:text-[var(--primary)] truncate block"><?= e($r['title']) ?></a>
        </div>
      </div>
      <div class="flex items-center gap-3 shrink-0 ml-4">
        <div class="text-right hidden md:block"><p class="text-xs text-slate-500"><?= e($r['firstName'].' '.$r['lastName']) ?></p><p class="text-[10px] text-slate-400"><?= date('d/m/Y',strtotime($r['transmissionDate'])) ?></p></div>
        <span class="hidden md:inline-flex rounded-full px-2 py-0.5 text-xs font-medium" style="background:<?= e($r['priorityColor']??'#e5e7eb') ?>20;color:<?= e($r['priorityColor']??'#64748b') ?>"><?= e($r['priorityName']??'—') ?></span>
        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium border <?= ((int)($r['docCount']??0)>0)?'bg-blue-50 text-blue-700 border-blue-200':'bg-zinc-100 text-zinc-500 border-zinc-200' ?>"><i data-lucide="paperclip" class="w-3 h-3"></i> <?= (int)($r['docCount']??0) ?> fichier(s)</span>
      </div>
    </div>
    <div class="px-4 py-2 bg-zinc-50 border-t flex items-center gap-1 flex-wrap">
      <?php if(($r['transStatus']??'')==='PENDING'): ?>
        <span class="text-[11px] text-amber-700 bg-amber-100 px-2 py-1 rounded-full font-medium">En attente d’acceptation — détermine le circuit</span>
        <button onclick="instruireRespond('<?= e($r['transmissionId']) ?>','accept',this)" class="inline-flex items-center gap-1 rounded-lg px-3 py-1 text-xs font-medium bg-emerald-600 text-white hover:bg-emerald-700"><i data-lucide="check" class="w-3 h-3"></i> Accepter → En cours</button>
        <button onclick="instruireReject('<?= e($r['transmissionId']) ?>',this)" class="inline-flex items-center gap-1 rounded-lg px-3 py-1 text-xs font-medium bg-white border border-red-200 text-red-600 hover:bg-red-50"><i data-lucide="x" class="w-3 h-3"></i> Rejeter</button>
      <?php elseif($isNew): ?><a href="view.php?id=<?= e($r['id']) ?>" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-medium bg-yellow-100 text-yellow-700 hover:bg-yellow-200"><i data-lucide="user-check" class="w-3 h-3"></i> Prendre en charge</a>
      <?php else: ?>
        <a href="view.php?id=<?= e($r['id']) ?>" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs text-slate-500 hover:bg-blue-50 hover:text-blue-600"><i data-lucide="message-square" class="w-3 h-3"></i> Annoter</a>
        <a href="view.php?id=<?= e($r['id']) ?>" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs text-slate-500 hover:bg-teal-50 hover:text-teal-600"><i data-lucide="signature" class="w-3 h-3"></i> Viser</a>
        <a href="view.php?id=<?= e($r['id']) ?>" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs text-slate-500 hover:bg-orange-50 hover:text-orange-600"><i data-lucide="info" class="w-3 h-3"></i> Demander infos</a>
      <?php endif; ?>
      <div class="flex-1"></div><a href="view.php?id=<?= e($r['id']) ?>" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-medium bg-[var(--primary)] text-white hover:bg-[var(--primary-light)]"><i data-lucide="eye" class="w-3 h-3"></i> Ouvrir</a>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php if($pages>1): ?><div class="flex items-center justify-center gap-1 mt-4 text-sm"><?php for($p=1;$p<=$pages;$p++): ?><a href="?filter=instruire&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&page=<?= $p ?>" class="px-3 py-1.5 rounded-lg <?= $p==$page?'bg-[var(--primary-dark)] text-white':'border hover:bg-zinc-50' ?>"><?= $p ?></a><?php endfor; ?></div><?php endif; ?>

<?php else: ?>
<div class="flex items-center justify-between mb-4">
  <div><h1 class="text-2xl font-bold text-slate-900"><?= e($headerTitle) ?> <span class="text-sm font-normal text-slate-400">(<?= $total ?>)</span></h1><p class="text-sm text-slate-500"><?= e($headerSub) ?></p></div>
  <a href="<?= BASE_URL ?>/dashboard/dossiers/nouveau.php" class="btn-primary hidden md:inline-flex">+ Nouveau dossier</a>
</div>
<form method="GET" class="card p-3 flex gap-2 mb-4">
  <?php if($filter): ?><input type="hidden" name="filter" value="<?= e($filter) ?>"><?php endif; ?>
  <div class="relative flex-1 max-w-sm"><i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i><input name="q" value="<?= e($search) ?>" placeholder="Rechercher..." class="input pl-9"></div>
  <select name="status" class="input w-40"><option value="ALL" <?= $status==='ALL'||$status===''?'selected':'' ?>>Tous statuts</option><?php foreach(['NOUVEAU','EN_COURS','EN_ATTENTE','CLOS','ANNULE','REJETE'] as $s): ?><option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
  <button class="btn-primary">Filtrer</button>
</form>
<div class="card overflow-x-auto">
<table class="w-full text-sm">
<thead><tr class="border-b text-left text-slate-500 bg-zinc-50"><th class="px-4 py-3">Référence</th><th class="px-4 py-3">Titre</th><th class="px-4 py-3">Catégorie</th><th class="px-4 py-3">Service</th><th class="px-4 py-3">Statut</th><th class="px-4 py-3">Priorité</th><th class="px-4 py-3 text-center">Fichiers</th><th class="px-4 py-3">Créé</th><th class="px-4 py-3">Actions</th></tr></thead>
<tbody>
<?php foreach($rows as $r): ?>
<tr class="border-b hover:bg-zinc-50">
  <td class="px-4 py-3 font-medium"><a href="view.php?id=<?= e($r['id']) ?>" class="text-[var(--primary)] hover:underline"><?= e($r['reference']) ?></a></td>
  <td class="px-4 py-3 truncate max-w-[260px]"><a href="view.php?id=<?= e($r['id']) ?>" class="hover:text-[var(--primary)]"><?= e($r['title']) ?></a></td>
  <td class="px-4 py-3"><?= e($r['catName']??'—') ?></td>
  <td class="px-4 py-3"><?= e($r['serviceName']??'—') ?></td>
  <td class="px-4 py-3"><?= statusBadge($r['status']) ?></td>
  <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs" style="background:<?= e($r['priorityColor']??'#e5e7eb') ?>20;color:<?= e($r['priorityColor']??'#64748b') ?>"><?= e($r['priorityName']??'—') ?></span></td>
  <td class="px-4 py-3 text-center"><a href="view.php?id=<?= e($r['id']) ?>#documents" class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium <?= ((int)($r['docCount']??0)>0)?'bg-blue-50 text-blue-700 border border-blue-200':'bg-zinc-100 text-zinc-500 border border-zinc-200' ?>"><i data-lucide="file-text" class="w-3 h-3"></i> <?= (int)($r['docCount']??0) ?></a></td>
  <td class="px-4 py-3 text-slate-500"><?= date('d/m/Y',strtotime($r['createdAt'])) ?></td>
  <td class="px-4 py-3 flex gap-1"><a href="view.php?id=<?= e($r['id']) ?>" class="text-[var(--primary)] text-xs border px-2 py-1 rounded-lg hover:bg-zinc-50">Voir</a> <a href="edit.php?id=<?= e($r['id']) ?>" class="text-zinc-600 text-xs border px-2 py-1 rounded-lg hover:bg-zinc-50">Éditer</a></td>
</tr>
<?php endforeach; ?>
<?php if(empty($rows)): ?><tr><td colspan="9" class="px-4 py-8 text-center text-slate-400"><?= $isSuivi?'Aucun dossier en cours de traitement':'Aucun dossier trouvé' ?></td></tr><?php endif; ?>
</tbody>
</table>
</div>
<?php if($pages>1): ?>
<div class="flex items-center justify-center gap-1 mt-4 text-sm">
  <?php for($p=1;$p<=$pages;$p++): ?><a href="?<?= $filter?'filter='.urlencode($filter).'&':'' ?>q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&page=<?= $p ?>" class="px-3 py-1.5 rounded-lg <?= $p==$page?'bg-[var(--primary-dark)] text-white':'border hover:bg-zinc-50' ?>"><?= $p ?></a><?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>
<script>
function instruireRespond(tid,act,btn){
  const fd=new FormData(); fd.append('transmissionId',tid); fd.append('action',act);
  btn.disabled=true; btn.textContent=act==='accept'?'Acceptation…':'Rejet…';
  fetch('<?= BASE_URL ?>/api/transmission_respond.php',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(j=>{
    const ok=j.ok!==false;
    const t=document.createElement('div'); t.className='fixed top-4 right-4 z-50 px-4 py-3 rounded-xl text-sm font-medium shadow border '+(ok?'bg-emerald-600 text-white border-emerald-700':'bg-red-600 text-white'); t.textContent=j.msg||(ok?'OK':'Erreur'); document.body.appendChild(t); setTimeout(()=>t.remove(),3000);
    if(ok) setTimeout(()=>location.reload(),700);
    else btn.disabled=false;
  }).catch(()=>{ btn.disabled=false; alert('Erreur réseau'); });
}
function instruireReject(tid,btn){
  const reason=prompt('Motif du rejet (obligatoire) :');
  if(reason===null) return; const r=reason.trim(); if(!r){ alert('Motif obligatoire'); return; }
  const fd=new FormData(); fd.append('transmissionId',tid); fd.append('action','reject'); fd.append('rejectReason',r);
  btn.disabled=true;
  fetch('<?= BASE_URL ?>/api/transmission_respond.php',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(j=>{
    const ok=j.ok!==false;
    const t=document.createElement('div'); t.className='fixed top-4 right-4 z-50 px-4 py-3 rounded-xl text-sm font-medium shadow border '+(ok?'bg-emerald-600 text-white':'bg-red-600 text-white'); t.textContent=j.msg||(ok?'Rejeté — retour envoyeur':'Erreur'); document.body.appendChild(t); setTimeout(()=>t.remove(),3000);
    if(ok) setTimeout(()=>location.reload(),700);
    else btn.disabled=false;
  });
}
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
