<?php
$pageTitle='Modèles';
require_once __DIR__ . '/../../includes/header.php';
$pdo=db(); $msg=''; $err='';
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_tpl'])){
  $name=trim($_POST['name']??''); $desc=trim($_POST['description']??''); $cat=$_POST['categoryId']??''; $genre=$_POST['genreId']??''; $type=$_POST['dossierTypeId']??''; $prio=$_POST['priorityId']??'';
  if(!$name||!$cat||!$genre||!$type||!$prio) $err="Champs requis manquants";
  else {
    try{ $pdo->prepare("INSERT INTO dossier_templates (id,name,description,categoryId,genreId,dossierTypeId,priorityId,createdAt,updatedAt) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())")->execute([cuid(),$name,$desc?:null,$cat,$genre,$type,$prio]); $msg="Modèle créé"; }catch(Exception $e){ $err=$e->getMessage(); }
  }
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_tpl'])){
  $id=$_POST['tplId']??''; if($id){ $pdo->prepare("DELETE FROM dossier_templates WHERE id=?")->execute([$id]); $msg="Modèle supprimé"; }
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_step'])){
  $tpl=$_POST['tplId']??''; $sname=trim($_POST['stepName']??''); $sdesc=trim($_POST['stepDescription']??''); $order=(int)($_POST['stepOrder']??0);
  if($tpl && $sname){ if($order<=0){ $cnt=$pdo->prepare("SELECT COUNT(*) FROM dossier_template_steps WHERE templateId=?"); $cnt->execute([$tpl]); $order=(int)$cnt->fetchColumn()+1; } $pdo->prepare("INSERT INTO dossier_template_steps (id,name,description,`order`,templateId) VALUES (?,?,?,?,?)")->execute([cuid(),$sname,$sdesc?:null,$order,$tpl]); $msg="Étape ajoutée"; }
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_step'])){
  $sid=$_POST['stepId']??''; $sname=trim($_POST['stepName']??''); $sdesc=trim($_POST['stepDescription']??''); $order=(int)($_POST['stepOrder']??0);
  if($sid && $sname){ $pdo->prepare("UPDATE dossier_template_steps SET name=?,description=?,`order`=? WHERE id=?")->execute([$sname,$sdesc?:null,$order,$sid]); $msg="Étape mise à jour"; }
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_step'])){
  $sid=$_POST['stepId']??''; if($sid){ $pdo->prepare("DELETE FROM dossier_template_steps WHERE id=?")->execute([$sid]); $msg="Étape supprimée"; }
}
$cats=$pdo->query("SELECT id,name FROM categories WHERE isActive=1 ORDER BY name")->fetchAll();
$genres=$pdo->query("SELECT id,name FROM genres WHERE isActive=1 ORDER BY name")->fetchAll();
$types=$pdo->query("SELECT id,name FROM dossier_types WHERE isActive=1 ORDER BY name")->fetchAll();
$prios=$pdo->query("SELECT id,name,color FROM priorities WHERE isActive=1 ORDER BY name")->fetchAll();
$templates=$pdo->query("SELECT t.*, c.name as catName, g.name as genreName, dt.name as typeName, p.name as prioName, p.color as prioColor FROM dossier_templates t LEFT JOIN categories c ON c.id=t.categoryId LEFT JOIN genres g ON g.id=t.genreId LEFT JOIN dossier_types dt ON dt.id=t.dossierTypeId LEFT JOIN priorities p ON p.id=t.priorityId ORDER BY t.createdAt DESC")->fetchAll();
$selectedId=$_GET['tpl']??''; $selectedSteps=[];
if($selectedId){ $st=$pdo->prepare("SELECT * FROM dossier_template_steps WHERE templateId=? ORDER BY `order` ASC"); $st->execute([$selectedId]); $selectedSteps=$st->fetchAll(); }
?>
<div class="max-w-5xl mx-auto space-y-6">
  <div class="flex items-center justify-between">
    <div><h1 class="text-2xl font-bold">Modèles de dossiers</h1><p class="text-sm text-slate-500">Gérez les modèles pour la création rapide de dossiers</p></div>
    <button onclick="document.getElementById('createTplModal').classList.remove('hidden')" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-black"><i data-lucide="plus" class="w-4 h-4"></i> Nouveau modèle</button>
  </div>
  <?php if($msg): ?><div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-2 rounded-xl text-sm"><?= e($msg) ?></div><?php endif; ?>
  <?php if($err): ?><div class="bg-red-50 border border-red-200 text-red-600 px-4 py-2 rounded-xl text-sm"><?= e($err) ?></div><?php endif; ?>

  <?php if(empty($templates)): ?>
    <div class="bg-white rounded-xl border p-12 text-center"><i data-lucide="file-text" class="w-8 h-8 mx-auto mb-3 text-slate-300"></i><p class="text-sm text-slate-500">Aucun modèle pour le moment</p></div>
  <?php else: ?>
    <div class="grid gap-4">
    <?php foreach($templates as $tpl):
      $cnt=$pdo->prepare("SELECT COUNT(*) FROM dossier_template_steps WHERE templateId=?"); $cnt->execute([$tpl['id']]); $stepCount=(int)$cnt->fetchColumn();
      $isSel=$selectedId===$tpl['id'];
    ?>
      <div class="bg-white rounded-xl border <?= $isSel?'border-slate-900 ring-1 ring-slate-900':'' ?> transition-all">
        <div class="p-4 flex items-start justify-between">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1"><i data-lucide="file-text" class="w-4 h-4 text-slate-400"></i><span class="font-medium"><?= e($tpl['name']) ?></span><span class="text-xs text-slate-400">(<?= $stepCount ?> étape<?= $stepCount>1?'s':'' ?>)</span></div>
            <?php if($tpl['description']): ?><p class="text-xs text-slate-500 mb-2"><?= e($tpl['description']) ?></p><?php endif; ?>
            <div class="flex flex-wrap gap-1.5">
              <span class="bg-zinc-50 px-2 py-0.5 text-[10px] text-slate-500 rounded"><?= e($tpl['catName']) ?></span>
              <span class="bg-zinc-50 px-2 py-0.5 text-[10px] text-slate-500 rounded"><?= e($tpl['genreName']) ?></span>
              <span class="bg-zinc-50 px-2 py-0.5 text-[10px] text-slate-500 rounded"><?= e($tpl['typeName']) ?></span>
              <span class="bg-zinc-50 px-2 py-0.5 text-[10px] rounded" style="color:<?= e($tpl['prioColor']?:'#64748b') ?>"><?= e($tpl['prioName']) ?></span>
            </div>
          </div>
          <div class="flex items-center gap-1 shrink-0 ml-3">
            <a href="?tpl=<?= e($tpl['id']) ?>" class="p-1.5 rounded-lg hover:bg-zinc-50 text-slate-400"><i data-lucide="chevron-right" class="w-4 h-4 <?= $isSel?'rotate-90':'' ?> transition-transform"></i></a>
            <button onclick="openSteps('<?= e($tpl['id']) ?>','<?= e(addslashes($tpl['name'])) ?>')" class="p-1.5 rounded-lg hover:bg-blue-50 text-slate-400 hover:text-blue-600"><i data-lucide="edit-3" class="w-4 h-4"></i></button>
            <form method="POST" onsubmit="return confirm('Supprimer ?')"><input type="hidden" name="tplId" value="<?= e($tpl['id']) ?>"><button name="delete_tpl" value="1" class="p-1.5 rounded-lg hover:bg-red-50 text-slate-400 hover:text-red-600"><i data-lucide="trash-2" class="w-4 h-4"></i></button></form>
          </div>
        </div>
        <?php if($isSel && $stepCount>0):
          if(empty($selectedSteps)){ $st=$pdo->prepare("SELECT * FROM dossier_template_steps WHERE templateId=? ORDER BY `order` ASC"); $st->execute([$tpl['id']]); $selectedSteps=$st->fetchAll(); }
        ?>
        <div class="border-t px-4 py-3 space-y-1.5"><p class="text-[10px] tracking-wider font-medium text-slate-400 uppercase">Étapes</p>
          <?php foreach($selectedSteps as $idx=>$step): ?>
            <div class="flex items-center gap-2 text-sm"><span class="w-5 h-5 rounded-full bg-zinc-100 text-[10px] flex items-center justify-center"><?= $idx+1 ?></span><span class="font-medium"><?= e($step['name']) ?></span><?php if($step['description']): ?><span class="text-xs text-slate-400">— <?= e($step['description']) ?></span><?php endif; ?></div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="border-t px-4 py-2 flex justify-end"><a href="<?= BASE_URL ?>/dashboard/dossiers/nouveau.php?modele=<?= e($tpl['id']) ?>" class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline"><i data-lucide="check" class="w-3 h-3"></i> Appliquer ce modèle</a></div>
      </div>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Create Modal -->
<div id="createTplModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/30" onclick="if(event.target===this) this.classList.add('hidden')">
  <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4 shadow-xl">
    <div class="flex items-center justify-between mb-4"><h3 class="font-semibold">Nouveau modèle</h3><button onclick="document.getElementById('createTplModal').classList.add('hidden')" class="p-1 hover:bg-zinc-50 rounded-lg"><i data-lucide="x" class="w-5 h-5"></i></button></div>
    <form method="POST" class="space-y-3">
      <div><label class="text-sm font-medium">Nom *</label><input name="name" required class="input w-full"></div>
      <div><label class="text-sm font-medium">Description</label><textarea name="description" rows="2" class="input w-full"></textarea></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium">Catégorie *</label><select name="categoryId" required class="input w-full"><option value="">...</option><?php foreach($cats as $c): ?><option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
        <div><label class="text-sm font-medium">Genre *</label><select name="genreId" required class="input w-full"><option value="">...</option><?php foreach($genres as $g): ?><option value="<?= e($g['id']) ?>"><?= e($g['name']) ?></option><?php endforeach; ?></select></div>
        <div><label class="text-sm font-medium">Type *</label><select name="dossierTypeId" required class="input w-full"><option value="">...</option><?php foreach($types as $t): ?><option value="<?= e($t['id']) ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
        <div><label class="text-sm font-medium">Priorité *</label><select name="priorityId" required class="input w-full"><option value="">...</option><?php foreach($prios as $p): ?><option value="<?= e($p['id']) ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="flex justify-end gap-2 pt-2"><button type="button" onclick="document.getElementById('createTplModal').classList.add('hidden')" class="border px-3 py-1.5 rounded-xl text-sm">Annuler</button><button name="create_tpl" value="1" class="bg-slate-900 text-white px-4 py-1.5 rounded-xl text-sm">Créer</button></div>
    </form>
  </div>
</div>

<!-- Steps Modal -->
<div id="stepsModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/30" onclick="if(event.target===this) closeSteps()">
  <div class="bg-white rounded-xl p-6 w-full max-w-lg mx-4 shadow-xl max-h-[85vh] overflow-y-auto">
    <div class="flex items-center justify-between mb-4"><h3 class="font-semibold">Étapes — <span id="stepsTplName"></span></h3><button onclick="closeSteps()" class="p-1 hover:bg-zinc-50 rounded-lg"><i data-lucide="x" class="w-5 h-5"></i></button></div>
    <div id="stepsList" class="space-y-2 mb-4"></div>
    <form method="POST" id="addStepForm" class="border-t pt-4 space-y-3">
      <input type="hidden" name="tplId" id="stepsTplId">
      <p class="text-sm font-medium">Ajouter une étape</p>
      <div class="grid grid-cols-3 gap-2">
        <div class="col-span-1"><label class="text-xs text-slate-500">Nom *</label><input name="stepName" required class="input w-full text-sm"></div>
        <div class="col-span-1"><label class="text-xs text-slate-500">Description</label><input name="stepDescription" class="input w-full text-sm"></div>
        <div class="col-span-1"><label class="text-xs text-slate-500">Ordre</label><input name="stepOrder" type="number" class="input w-full text-sm" placeholder="auto"></div>
      </div>
      <div class="flex justify-end gap-2"><button type="button" onclick="closeSteps()" class="border px-3 py-1.5 rounded-xl text-sm">Fermer</button><button name="add_step" value="1" class="bg-slate-900 text-white px-3 py-1.5 rounded-xl text-sm">Ajouter l'étape</button></div>
    </form>
  </div>
</div>
<script>
let allTemplates = <?= json_encode($templates) ?>;
async function openSteps(id,name){
  document.getElementById('stepsTplName').textContent=name;
  document.getElementById('stepsTplId').value=id;
  const list=document.getElementById('stepsList');
  list.innerHTML='<p class="text-sm text-slate-400">Chargement...</p>';
  document.getElementById('stepsModal').classList.remove('hidden');
  try{
    const res=await fetch('<?= BASE_URL ?>/api/template_steps.php?tpl='+encodeURIComponent(id));
    const steps=await res.json();
    if(!Array.isArray(steps) || steps.length===0){ list.innerHTML='<p class="text-sm text-slate-400 text-center py-4">Aucune étape</p>'; return; }
    list.innerHTML=steps.map((s,i)=>`
      <div class="flex items-center gap-2 p-2 rounded-lg bg-zinc-50 text-sm">
        <span class="w-5 text-center text-slate-400">\${i+1}.</span>
        <span class="flex-1 font-medium">\${s.name}</span>
        \${s.description?`<span class="text-xs text-slate-500">\${s.description}</span>`:''}
        <span class="text-[10px] text-slate-400">Ordre \${s.order}</span>
        <form method="POST" onsubmit="return confirm('Supprimer ?')" class="inline"><input type="hidden" name="stepId" value="\${s.id}"><button name="delete_step" value="1" class="p-1 hover:bg-red-50 rounded text-slate-400 hover:text-red-600"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button></form>
      </div>`).join('');
    if(window.lucide) lucide.createIcons();
  }catch(e){ list.innerHTML='<p class="text-sm text-red-600">Erreur chargement</p>'; }
}
function closeSteps(){ document.getElementById('stepsModal').classList.add('hidden'); }
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
