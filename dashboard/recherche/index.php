<?php
$pageTitle='Recherche';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';
if(!hasModule('recherche')) requireModule('recherche');
$pdo=db(); $u=currentUser();
$q=trim($_GET['q']??'');
$cat=$_GET['cat']??''; $genre=$_GET['genre']??''; $prio=$_GET['prio']??''; $status=$_GET['status']??'';
$tag=$_GET['tag']??''; $from=$_GET['from']??''; $to=$_GET['to']??'';
$saveName=trim($_POST['saveName']??'');

// save search
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['saveSearch']) && $q!==''){
  $pdo->prepare("INSERT INTO saved_searches (id,userId,name,query,filters) VALUES (?,?,?,?,?)")->execute([cuid(),$u['id'],$saveName?:$q,$q,json_encode(['cat'=>$cat,'genre'=>$genre,'prio'=>$prio,'status'=>$status,'tag'=>$tag])]);
}
// delete saved
if(isset($_GET['delSaved'])){ $pdo->prepare("DELETE FROM saved_searches WHERE id=? AND userId=?")->execute([$_GET['delSaved'],$u['id']]); }
// log history when searching
if($q!=='' && $_SERVER['REQUEST_METHOD']==='GET'){
  $pdo->prepare("INSERT INTO search_history (id,userId,query,filters) VALUES (?,?,?,?)")->execute([cuid(),$u['id'],$q,json_encode(compact('cat','genre','prio','status','tag','from','to'))]);
}
$cats=$pdo->query("SELECT * FROM categories WHERE isActive=1 ORDER BY name")->fetchAll();
$genres=$pdo->query("SELECT * FROM genres WHERE isActive=1 ORDER BY name")->fetchAll();
$prios=$pdo->query("SELECT * FROM priorities WHERE isActive=1 ORDER BY name")->fetchAll();
$tags=$pdo->query("SELECT * FROM tags ORDER BY name")->fetchAll();
$history=$pdo->prepare("SELECT * FROM search_history WHERE userId=? ORDER BY createdAt DESC LIMIT 8"); $history->execute([$u['id']]); $hist=$history->fetchAll();
$saved=$pdo->prepare("SELECT * FROM saved_searches WHERE userId=? ORDER BY createdAt DESC"); $saved->execute([$u['id']]); $savedRows=$saved->fetchAll();

$results=[]; $total=0;
if($q!=='' || $cat||$genre||$prio||$status||$tag||$from||$to){
  $where="d.deletedAt IS NULL"; $params=[];
  if($q!==''){ $where.=" AND (d.title LIKE ? OR d.reference LIKE ? OR d.description LIKE ? OR d.id LIKE ?)"; $like="%$q%"; array_push($params,$like,$like,$like,$like); }
  if($cat){ $where.=" AND d.categoryId=?"; $params[]=$cat; }
  if($genre){ $where.=" AND d.genreId=?"; $params[]=$genre; }
  if($prio){ $where.=" AND d.priorityId=?"; $params[]=$prio; }
  if($status){ $where.=" AND d.status=?"; $params[]=$status; }
  if($from){ $where.=" AND d.createdAt>=?"; $params[]=$from; }
  if($to){ $where.=" AND d.createdAt<=?"; $params[]=$to.' 23:59:59'; }
  $joinTag=""; if($tag){ $joinTag=" JOIN dossier_tags dt ON dt.dossierId=d.id AND dt.tagId=? "; array_unshift($params,$tag); }
  // also search ocrText in documents if q
  $ocrJoin=""; if($q!==''){ $where.=" OR EXISTS (SELECT 1 FROM documents doc WHERE doc.dossierId=d.id AND doc.ocrText LIKE ?)"; $params[]="%$q%"; }
  $sql="SELECT d.*, c.name as catName, p.name as prioName, p.color as prioColor FROM dossiers d LEFT JOIN categories c ON c.id=d.categoryId LEFT JOIN priorities p ON p.id=d.priorityId $joinTag WHERE $where ORDER BY d.createdAt DESC LIMIT 50";
  $stmt=$pdo->prepare($sql); $stmt->execute($params); $results=$stmt->fetchAll(); $total=count($results);
}
?>
<div class="flex items-center justify-between mb-4">
  <div><h1 class="text-xl font-extrabold text-slate-900 flex items-center gap-2"><i data-lucide="search" class="w-5 h-5 text-blue-600"></i> Recherche</h1><p class="text-sm text-slate-500">Plein texte, multicritère, métadonnées, historique et recherches enregistrées <span class="inline-flex items-center gap-1 ml-2 text-xs bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded-full"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span> automatique</span></p></div>
  <span id="resultCount" class="text-xs bg-slate-900 text-white px-3 py-1 rounded-full"><?= $total ?> résultat(s)</span>
</div>
<div class="grid lg:grid-cols-4 gap-6">
  <div class="lg:col-span-3 space-y-4">
    <form method="GET" id="autoSearchForm" class="card p-4 space-y-3">
      <div class="flex gap-2">
        <div class="relative flex-1"><i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i><input id="autoQ" name="q" value="<?= e($q) ?>" autocomplete="off" placeholder="Plein texte : titre, référence, description, contenu OCR..." class="input pl-9 w-full"><span id="liveSpinner" class="hidden absolute right-3 top-1/2 -translate-y-1/2"><i data-lucide="loader-2" class="w-4 h-4 text-blue-500 animate-spin"></i></span></div>
        <button class="bg-blue-600 hover:bg-blue-700 text-white px-6 rounded-xl text-sm font-medium hidden md:inline-flex items-center gap-1"><i data-lucide="search" class="w-4 h-4"></i> Rechercher</button>
      </div>
      <div class="grid md:grid-cols-3 lg:grid-cols-6 gap-2">
        <select name="cat" class="input"><option value="">Catégorie</option><?php foreach($cats as $c): ?><option value="<?= e($c['id']) ?>" <?= $cat===$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
        <select name="genre" class="input"><option value="">Genre</option><?php foreach($genres as $g): ?><option value="<?= e($g['id']) ?>" <?= $genre===$g['id']?'selected':'' ?>><?= e($g['name']) ?></option><?php endforeach; ?></select>
        <select name="prio" class="input"><option value="">Priorité</option><?php foreach($prios as $p): ?><option value="<?= e($p['id']) ?>" <?= $prio===$p['id']?'selected':'' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select>
        <select name="status" class="input"><option value="">Statut</option><?php foreach(['NOUVEAU','EN_COURS','EN_ATTENTE','CLOS'] as $s): ?><option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
        <select name="tag" class="input"><option value="">Tag</option><?php foreach($tags as $t): ?><option value="<?= e($t['id']) ?>" <?= $tag===$t['id']?'selected':'' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select>
        <input type="date" name="from" value="<?= e($from) ?>" class="input" title="Du">
      </div>
      <div class="flex gap-2 items-center text-xs">
        <input type="date" name="to" value="<?= e($to) ?>" class="input w-40" title="Au">
        <?php if($q||$cat||$genre||$prio||$status||$tag||$from||$to): ?><a href="index.php" class="text-slate-500 hover:text-slate-700">Réinitialiser</a><?php endif; ?>
        <span class="ml-auto text-slate-400">Recherche sur titre, référence, description et OCR documents</span>
      </div>
    </form>
    <div id="liveResults">
    <?php if(!empty($results)): ?>
    <div class="card overflow-hidden">
      <table class="w-full text-sm">
        <thead><tr class="bg-slate-50 border-b text-left text-slate-500"><th class="px-4 py-2">Réf / Titre</th><th class="px-3 py-2">Cat</th><th class="px-3 py-2">Priorité</th><th class="px-3 py-2">Statut</th><th class="px-3 py-2">Date</th></tr></thead>
        <tbody class="divide-y">
        <?php foreach($results as $r): ?>
          <tr class="hover:bg-slate-50"><td class="px-4 py-2"><a href="<?= BASE_URL ?>/dashboard/dossiers/view.php?id=<?= e($r['id']) ?>" class="font-medium text-blue-700 hover:underline"><?= e($r['reference']) ?></a><div class="text-slate-600 truncate max-w-[360px]"><?= e($r['title']) ?></div></td><td class="px-3 py-2 text-xs"><?= e($r['catName']??'—') ?></td><td class="px-3 py-2"><span class="text-xs px-2 py-0.5 rounded-full" style="background:<?= e($r['prioColor']??'#e5e7eb') ?>20;color:<?= e($r['prioColor']??'#64748b') ?>"><?= e($r['prioName']??'—') ?></span></td><td class="px-3 py-2"><?= statusBadge($r['status']) ?></td><td class="px-3 py-2 text-xs text-slate-500"><?= date('d/m/Y',strtotime($r['createdAt'])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php elseif($q!=='' || $cat): ?><div class="card p-8 text-center text-slate-400 text-sm">Aucun résultat</div><?php endif; ?>
    </div>
    <?php if($q!=='' && $total>0): ?>
    <form method="POST" class="flex gap-2 items-center card p-3">
      <input type="hidden" name="q" value="<?= e($q) ?>">
      <input name="saveName" placeholder="Nom de cette recherche..." class="input flex-1" required>
      <button name="saveSearch" value="1" class="bg-slate-900 text-white px-4 py-2 rounded-xl text-sm">Enregistrer recherche</button>
    </form>
    <?php endif; ?>
  </div>
  <div class="space-y-4">
    <div class="card p-4">
      <h3 class="font-semibold text-sm mb-2 flex items-center gap-2"><i data-lucide="bookmark" class="w-4 h-4"></i> Recherches enregistrées</h3>
      <?php if(empty($savedRows)): ?><p class="text-xs text-slate-400">Aucune</p><?php endif; ?>
      <?php foreach($savedRows as $s): $f=json_decode($s['filters']??'{}',true); ?>
        <div class="border rounded-xl px-3 py-2 mb-2 text-sm bg-white flex justify-between items-center">
          <div><div class="font-medium"><?= e($s['name']) ?></div><div class="text-xs text-slate-400"><?= e($s['query']) ?></div></div>
          <div class="flex gap-1"><a href="?q=<?= urlencode($s['query']) ?>" class="h-7 w-7 flex items-center justify-center border rounded-lg hover:bg-slate-50"><i data-lucide="search" class="w-3.5 h-3.5"></i></a><a href="?delSaved=<?= e($s['id']) ?>" onclick="return confirm('Supprimer ?')" class="h-7 w-7 flex items-center justify-center border rounded-lg hover:bg-red-50 text-red-600"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></a></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="card p-4">
      <h3 class="font-semibold text-sm mb-2 flex items-center gap-2"><i data-lucide="history" class="w-4 h-4"></i> Historique</h3>
      <?php foreach($hist as $h): ?><div class="text-sm border-b py-2"><div class="font-medium"><?= e($h['query']) ?></div><div class="text-xs text-slate-400"><?= date('d/m H:i',strtotime($h['createdAt'])) ?> • <a href="?q=<?= urlencode($h['query']) ?>" class="text-blue-600">Relancer</a></div></div><?php endforeach; ?>
      <?php if(empty($hist)): ?><p class="text-xs text-slate-400">Vide</p><?php endif; ?>
    </div>
  </div>
</div>
<script>
(function(){
  const form=document.getElementById('autoSearchForm');
  const qInput=document.getElementById('autoQ');
  const spinner=document.getElementById('liveSpinner');
  const wrap=document.getElementById('liveResults');
  const countEl=document.getElementById('resultCount');
  const baseUrl='<?= BASE_URL ?>';
  let timer=null, ctrl=null;
  function debounce(fn,ms){ return function(){ clearTimeout(timer); timer=setTimeout(()=>fn.apply(this,arguments),ms); }; }
  function buildParams(){
    const fd=new FormData(form);
    const p=new URLSearchParams();
    for(const [k,v] of fd.entries()) if(v!=='') p.set(k,v);
    return p;
  }
  function render(data){
    countEl.textContent=data.total+' résultat(s)';
    if(data.total===0){
      const hasQuery=[...buildParams().keys()].length>0;
      wrap.innerHTML = hasQuery ? '<div class="card p-8 text-center text-slate-400 text-sm">Aucun résultat</div>' : '';
      return;
    }
    let html='<div class="card overflow-hidden"><table class="w-full text-sm"><thead><tr class="bg-slate-50 border-b text-left text-slate-500"><th class="px-4 py-2">Réf / Titre</th><th class="px-3 py-2">Cat</th><th class="px-3 py-2">Priorité</th><th class="px-3 py-2">Statut</th><th class="px-3 py-2">Date</th></tr></thead><tbody class="divide-y">';
    data.rows.forEach(r=>{
      const prioColor=r.prioColor||'#e5e7eb';
      const safe = s => (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
      html+='<tr class="hover:bg-slate-50"><td class="px-4 py-2"><a href="'+baseUrl+'/dashboard/dossiers/view.php?id='+encodeURIComponent(r.id)+'" class="font-medium text-blue-700 hover:underline">'+safe(r.reference)+'</a><div class="text-slate-600 truncate max-w-[360px]">'+safe(r.title)+'</div></td><td class="px-3 py-2 text-xs">'+safe(r.catName||'—')+'</td><td class="px-3 py-2"><span class="text-xs px-2 py-0.5 rounded-full" style="background:'+safe(prioColor)+'20;color:'+safe(prioColor)+'">'+safe(r.prioName||'—')+'</span></td><td class="px-3 py-2">'+(r.badge||'')+'</td><td class="px-3 py-2 text-xs text-slate-500">'+safe(r.date)+'</td></tr>';
    });
    html+='</tbody></table></div>';
    wrap.innerHTML=html;
  }
  async function liveSearch(){
    const p=buildParams();
    // history sync without reload
    const qs=p.toString();
    history.replaceState(null,'','?'+qs);
    // if empty filters -> clear
    if([...p.keys()].length===0){ wrap.innerHTML=''; countEl.textContent='0 résultat(s)'; return; }
    if(p.get('q') && p.get('q').length>0 && p.get('q').length<2){ return; }
    if(ctrl) ctrl.abort();
    ctrl=new AbortController();
    spinner.classList.remove('hidden');
    try{
      const res=await fetch(baseUrl+'/api/recherche_live.php?'+qs,{signal:ctrl.signal, headers:{'X-Requested-With':'XMLHttpRequest'}});
      const data=await res.json();
      render(data);
    }catch(e){ if(e.name!=='AbortError') console.error(e); }
    finally{ spinner.classList.add('hidden'); }
  }
  const debouncedLive=debounce(liveSearch,350);
  qInput.addEventListener('input', debouncedLive);
  form.querySelectorAll('select,input[type=date]').forEach(el=> el.addEventListener('change', liveSearch));
  // prevent full submit, use live instead (but keep fallback)
  form.addEventListener('submit', e=>{ e.preventDefault(); liveSearch(); });
})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
