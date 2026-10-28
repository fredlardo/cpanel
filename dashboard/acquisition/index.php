<?php
$pageTitle='Acquisition';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';
if(!hasModule('acquisition') && currentUser()['role']!=='ADMIN'){ requireModule('acquisition'); }
$pdo=db(); $u=currentUser();
$dossiers=$pdo->query("SELECT id,reference,title FROM dossiers WHERE deletedAt IS NULL ORDER BY createdAt DESC LIMIT 100")->fetchAll();
$imports=$pdo->prepare("SELECT a.*, u.firstName,u.lastName FROM acquisition_imports a LEFT JOIN users u ON u.id=a.userId ORDER BY a.createdAt DESC LIMIT 30");
$imports->execute(); $rows=$imports->fetchAll();
?>
<div class="flex items-center justify-between mb-6">
  <div><h1 class="text-xl font-extrabold text-slate-900 flex items-center gap-2"><i data-lucide="upload-cloud" class="w-5 h-5 text-blue-600"></i> Acquisition</h1><p class="text-sm text-slate-500">Glisser-déposer, scanner, mobile, email, dossiers surveillés et imports massifs</p></div>
  <span class="text-xs bg-slate-900 text-white px-3 py-1 rounded-full"><?= count($rows) ?> imports récents</span>
</div>
<div class="grid lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 space-y-4">
    <div class="card p-5">
      <h3 class="font-semibold text-slate-900 mb-3 flex items-center gap-2"><i data-lucide="monitor-up" class="w-4 h-4"></i> Glisser-déposer & import massif</h3>
      <div id="dropZone" class="border-2 border-dashed border-slate-200 rounded-2xl p-10 text-center bg-slate-50/50 hover:bg-blue-50/50 hover:border-blue-300 transition cursor-pointer">
        <i data-lucide="upload" class="w-10 h-10 mx-auto text-slate-300 mb-3"></i>
        <p class="font-medium text-slate-700">Déposez vos fichiers ici</p>
        <p class="text-xs text-slate-400">PDF, images, Word, Excel — jusqu'à 100 fichiers en lot</p>
        <input type="file" id="fileInput" multiple class="hidden">
        <button id="browseBtn" class="mt-4 bg-blue-600 hover:bg-blue-700 text-white text-sm px-5 py-2 rounded-xl">Parcourir</button>
      </div>
      <div class="mt-4 flex gap-2 items-center">
        <select id="targetDossier" class="input flex-1"><option value="">— Créer imports sans dossier (tri ultérieur) —</option><?php foreach($dossiers as $d): ?><option value="<?= e($d['id']) ?>"><?= e($d['reference']) ?> — <?= e($d['title']) ?></option><?php endforeach; ?></select>
        <select id="sourceSel" class="input w-40"><option value="dragdrop">Drag & drop</option><option value="bulk">Import massif</option><option value="mobile">Mobile</option><option value="email">Email</option><option value="watched">Dossier surveillé</option></select>
      </div>
      <div id="uploadProgress" class="mt-3 hidden"><div class="h-2 bg-slate-100 rounded-full overflow-hidden"><div id="bar" class="h-2 bg-blue-600 w-0 transition-all"></div></div><p id="progressTxt" class="text-xs text-slate-500 mt-1"></p></div>
      <div id="uploadResult" class="mt-3 text-sm"></div>
    </div>
    <div class="grid md:grid-cols-3 gap-3">
      <div class="card p-4">
        <h4 class="font-semibold text-sm flex items-center gap-2"><i data-lucide="scan" class="w-4 h-4 text-violet-600"></i> Scanner</h4>
        <p class="text-xs text-slate-500 mt-1">Acquisition via scanner réseau ou TWAIN.</p>
        <button onclick="alert('Placez le document dans le scanner et utilisez le pilote TWAIN / SANE. Cette interface déclenchera numérisation via l’app desktop Doxera Scan.')" class="mt-3 w-full border rounded-xl py-2 text-sm hover:bg-slate-50">Lancer numérisation</button>
        <div class="mt-2 text-[11px] text-slate-400">Prêt • 300 DPI • PDF/A</div>
      </div>
      <div class="card p-4">
        <h4 class="font-semibold text-sm flex items-center gap-2"><i data-lucide="smartphone" class="w-4 h-4 text-emerald-600"></i> Mobile</h4>
        <p class="text-xs text-slate-500">Scannez le QR pour envoyer depuis le téléphone.</p>
        <div class="mt-3 flex justify-center"><div class="h-28 w-28 bg-slate-900 rounded-xl flex items-center justify-center text-white text-[10px] text-center p-2">QR Upload<br><?= e(BASE_URL) ?>/mobile-upload</div></div>
      </div>
      <div class="card p-4">
        <h4 class="font-semibold text-sm flex items-center gap-2"><i data-lucide="mail" class="w-4 h-4 text-blue-600"></i> Email & surveillés</h4>
        <p class="text-xs text-slate-500">Email dédié + dossiers surveillés (poll 5 min).</p>
        <div class="mt-2 text-xs bg-slate-50 border rounded-lg p-2 font-mono">import@doxera.plus</div>
        <div class="mt-2 text-xs"><span class="inline-flex h-2 w-2 bg-emerald-500 rounded-full"></span> Dossier surveillé actif: <code>/srv/import</code></div>
      </div>
    </div>
  </div>
  <div class="space-y-4">
    <div class="card p-4">
      <h3 class="font-semibold text-sm mb-3">Derniers imports</h3>
      <div class="space-y-2 max-h-[520px] overflow-auto">
        <?php foreach($rows as $r): ?><div class="flex items-center justify-between border rounded-xl px-3 py-2 text-sm bg-white">
          <div><div class="font-medium truncate max-w-[160px]"><?= e($r['fileName']) ?></div><div class="text-[11px] text-slate-400"><?= e($r['source']) ?> • <?= e($r['firstName'].' '.$r['lastName']) ?> • <?= date('d/m H:i',strtotime($r['createdAt'])) ?></div></div>
          <span class="text-[11px] px-2 py-0.5 rounded-full <?= $r['status']==='TRAITE'?'bg-emerald-50 text-emerald-700':'bg-amber-50 text-amber-700' ?>"><?= e($r['status']) ?></span>
        </div><?php endforeach; ?>
        <?php if(empty($rows)): ?><p class="text-sm text-slate-400 text-center py-6">Aucun import</p><?php endif; ?>
      </div>
    </div>
  </div>
</div>
<script>
(function(){
 const dz=document.getElementById('dropZone'), inp=document.getElementById('fileInput'), btn=document.getElementById('browseBtn');
 const bar=document.getElementById('bar'), prog=document.getElementById('uploadProgress'), txt=document.getElementById('progressTxt'), res=document.getElementById('uploadResult');
 btn.addEventListener('click', e=>{e.stopPropagation(); inp.click();});
 dz.addEventListener('click', ()=> inp.click());
 ['dragenter','dragover'].forEach(ev=> dz.addEventListener(ev, e=>{e.preventDefault(); dz.classList.add('bg-blue-50','border-blue-400');}));
 ['dragleave','drop'].forEach(ev=> dz.addEventListener(ev, e=>{e.preventDefault(); dz.classList.remove('bg-blue-50','border-blue-400');}));
 dz.addEventListener('drop', e=>{ if(e.dataTransfer.files.length) upload(e.dataTransfer.files); });
 inp.addEventListener('change', ()=>{ if(inp.files.length) upload(inp.files); });
 function upload(files){
   const fd=new FormData();
   for(let f of files) fd.append('files[]', f);
   fd.append('source', document.getElementById('sourceSel').value);
   fd.append('dossierId', document.getElementById('targetDossier').value);
   prog.classList.remove('hidden'); bar.style.width='15%'; txt.textContent='Envoi '+files.length+' fichier(s)...'; res.innerHTML='';
   $.ajax({url:'<?= BASE_URL ?>/api/acquisition_upload.php', method:'POST', data:fd, processData:false, contentType:false,
     xhr: function(){ const x=$.ajaxSettings.xhr(); if(x.upload) x.upload.addEventListener('progress', e=>{ if(e.lengthComputable) bar.style.width=Math.round(e.loaded/e.total*100)+'%'; }); return x; },
     success: d=>{ bar.style.width='100%'; txt.textContent=d.files? d.files.length+' fichier(s) importé(s)':'Terminé'; res.innerHTML='<div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl p-3">'+(d.files||[]).map(f=>'<div>✓ '+$('<div>').text(f.name).html()+'</div>').join('')+'</div>'; setTimeout(()=>location.reload(),1100); },
     error: xhr=>{ txt.textContent='Erreur'; res.innerHTML='<div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3">'+(xhr.responseText||'Échec')+'</div>'; }
   });
 }
})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
