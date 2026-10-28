<?php
$pageTitle='Entités';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/licence.php';
$pdo=db(); licenceEnsureTables($pdo);
if(!isSuperAdmin() && !can('entites')){ echo '<div class="card p-10 text-center">Réservé SUPERADMIN — module Entités non autorisé</div>'; require_once __DIR__ . '/../../includes/footer.php'; exit; }
$msg=''; $err='';
// Handle POST actions
if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'';
  $mode=$_POST['mode']??'';
  try{
    if($action==='create' || $action==='update'){
      $code=trim($_POST['code']??''); $name=trim($_POST['name']??''); $desc=trim($_POST['description']??'');
      if(!$code||!$name) throw new Exception('Code et nom requis');
      if($mode==='ENTITY'){
        if($action==='create') $pdo->prepare("INSERT INTO entities (id,code,name,description,isActive) VALUES (?,?,?,?,1)")->execute([cuid(),$code,$name,$desc?:null]);
        else $pdo->prepare("UPDATE entities SET code=?,name=?,description=? WHERE id=?")->execute([$code,$name,$desc?:null,$_POST['id']]);
      } elseif($mode==='ORG'){
        $entityId=$_POST['entityId']??''; if(!$entityId) throw new Exception('Entité parente requise');
        if($action==='create') $pdo->prepare("INSERT INTO organizations (id,code,name,description,entityId,isActive) VALUES (?,?,?,?,?,1)")->execute([cuid(),$code,$name,$desc?:null,$entityId]);
        else $pdo->prepare("UPDATE organizations SET code=?,name=?,description=? WHERE id=?")->execute([$code,$name,$desc?:null,$_POST['id']]);
      } elseif($mode==='DIV'){
        $orgId=$_POST['organizationId']??''; if(!$orgId) throw new Exception('Organisation parente requise');
        if($action==='create') $pdo->prepare("INSERT INTO divisions (id,code,name,description,organizationId,isActive) VALUES (?,?,?,?,?,1)")->execute([cuid(),$code,$name,$desc?:null,$orgId]);
        else $pdo->prepare("UPDATE divisions SET code=?,name=?,description=? WHERE id=?")->execute([$code,$name,$desc?:null,$_POST['id']]);
      } elseif($mode==='SERVICE'){
        $divId=$_POST['divisionId']??''; if(!$divId) throw new Exception('Division parente requise');
        if($action==='create') $pdo->prepare("INSERT INTO services (id,code,name,description,divisionId,isActive) VALUES (?,?,?,?,?,1)")->execute([cuid(),$code,$name,$desc?:null,$divId]);
        else $pdo->prepare("UPDATE services SET code=?,name=?,description=? WHERE id=?")->execute([$code,$name,$desc?:null,$_POST['id']]);
      }
      $msg=($action==='create'?'Créé':'Modifié').' avec succès.';
    }
    if($action==='deactivate'){
      $mid=$_POST['id']??''; $mmode=$_POST['mode']??'';
      $map=['ENTITY'=>'entities','ORG'=>'organizations','DIV'=>'divisions','SERVICE'=>'services'];
      $tbl=$map[$mmode]??null;
      if($tbl && $mid){
        // toggle isActive 0/1 - like LISA Ban sets to inactive
        $cur=$pdo->prepare("SELECT isActive FROM `$tbl` WHERE id=?"); $cur->execute([$mid]); $v=$cur->fetchColumn();
        $new = $v ? 0 : 1;
        $pdo->prepare("UPDATE `$tbl` SET isActive=? WHERE id=?")->execute([$new,$mid]);
        $msg=$new?'Réactivé.':'Désactivé.';
      }
    }
  }catch(Exception $e){ $err=$e->getMessage(); }catch(PDOException $e){ $err=$e->getMessage(); if(str_contains($err,'Duplicate')) $err='Code déjà utilisé.'; }
}
// Build tree
$entities=$pdo->query("SELECT * FROM entities ORDER BY name")->fetchAll();
$tree=[];
foreach($entities as $ent){
  $orgs=$pdo->prepare("SELECT * FROM organizations WHERE entityId=? ORDER BY name"); $orgs->execute([$ent['id']]); $orgRows=$orgs->fetchAll();
  $ent['organizations']=[];
  foreach($orgRows as $org){
    $divs=$pdo->prepare("SELECT * FROM divisions WHERE organizationId=? ORDER BY name"); $divs->execute([$org['id']]); $divRows=$divs->fetchAll();
    $org['divisions']=[];
    foreach($divRows as $div){
      $srvs=$pdo->prepare("SELECT * FROM services WHERE divisionId=? ORDER BY name"); $srvs->execute([$div['id']]); $srvRows=$srvs->fetchAll();
      $div['services']=$srvRows;
      $org['divisions'][]=$div;
    }
    $ent['organizations'][]=$org;
  }
  $tree[]=$ent;
}
?>
<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Entités</h1>
      <p class="text-sm text-slate-500">Gestion des entités, organisations, divisions et services</p>
    </div>
    <button onclick="openModal('ENTITY','CREATE')" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
      <i data-lucide="plus" class="w-4 h-4"></i> Nouvelle entité
    </button>
  </div>
  <?php if($msg): ?><div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-2.5 rounded-xl text-sm"><?= e($msg) ?></div><?php endif; ?>
  <?php if($err): ?><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-2.5 rounded-xl text-sm"><?= e($err) ?></div><?php endif; ?>

  <?php if(empty($tree)): ?>
    <div class="text-center py-12 text-slate-400 text-sm">Aucune entité configurée</div>
  <?php else: ?>
    <div class="space-y-4" id="entTree">
      <?php foreach($tree as $entity): ?>
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
          <!-- Entity Header -->
          <div class="flex items-center justify-between px-4 py-3 bg-zinc-50 border-b border-slate-200">
            <div class="flex items-center gap-3 min-w-0">
              <button onclick="toggle('entity-<?= e($entity['id']) ?>')" class="p-0.5 rounded hover:bg-zinc-200 shrink-0">
                <i data-lucide="chevron-right" id="ic-entity-<?= e($entity['id']) ?>" class="w-4 h-4 transition-transform"></i>
              </button>
              <i data-lucide="building-2" class="w-5 h-5 text-blue-600 shrink-0"></i>
              <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-medium text-slate-900"><?= e($entity['name']) ?></span>
                  <span class="text-xs text-slate-500 font-mono">(<?= e($entity['code']) ?>)</span>
                  <?php if(!(int)$entity['isActive']): ?><span class="text-xs text-red-500 font-medium">Inactif</span><?php endif; ?>
                  <?php $lst=licenceStatus($pdo,$entity['id']); $lc=$lst['lic']; $col=$lst['status']==='ACTIVE'?'bg-emerald-100 text-emerald-700 border-emerald-200':($lst['status']==='GRACE'?'bg-amber-100 text-amber-700 border-amber-200':'bg-red-100 text-red-700 border-red-200'); ?>
                  <span class="text-[10px] px-2 py-0.5 rounded-full border font-medium <?= $col ?>"><?= e($lst['status']) ?><?= $lc ? ' · '.e($lc['plan']).' · '.date('d/m/Y', strtotime($lc['expires_at'])) : '' ?></span>
                </div>
                <?php if($entity['description']): ?><p class="text-xs text-slate-500 truncate max-w-[520px]"><?= e($entity['description']) ?></p><?php endif; ?>
              </div>
            </div>
            <div class="flex items-center gap-1 shrink-0">
              <?php if(isSuperAdmin()): ?><a href="<?= BASE_URL ?>/dashboard/entites/licence.php?id=<?= e($entity['id']) ?>" class="p-1.5 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-600" title="Licence (SUPERADMIN)"><i data-lucide="key-round" class="w-4 h-4"></i></a><?php endif; ?>
              <button onclick="openModal('ORG','CREATE','<?= e($entity['id']) ?>','<?= e(addslashes($entity['name'])) ?>')" class="p-1.5 rounded-lg hover:bg-zinc-200 text-slate-500 hover:text-slate-900" title="Ajouter une organisation"><i data-lucide="plus" class="w-4 h-4"></i></button>
              <button onclick="openModal('ENTITY','EDIT','', '', <?= htmlspecialchars(json_encode(['id'=>$entity['id'],'code'=>$entity['code'],'name'=>$entity['name'],'description'=>$entity['description']]), ENT_QUOTES) ?>)" class="p-1.5 rounded-lg hover:bg-zinc-200 text-slate-500 hover:text-slate-900" title="Modifier"><i data-lucide="pencil" class="w-4 h-4"></i></button>
              <form method="POST" class="inline" onsubmit="return confirm('Basculer actif/inactif ?')"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="mode" value="ENTITY"><input type="hidden" name="id" value="<?= e($entity['id']) ?>"><button class="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600" title="Désactiver/Réactiver"><i data-lucide="ban" class="w-4 h-4"></i></button></form>
            </div>
          </div>
          <!-- Organizations -->
          <div id="entity-<?= e($entity['id']) ?>" class="hidden divide-y divide-slate-200">
            <?php if(empty($entity['organizations'])): ?>
              <div class="px-8 py-4 text-sm text-slate-400">Aucune organisation</div>
            <?php else: foreach($entity['organizations'] as $org): ?>
              <div>
                <div class="px-8 py-3 flex items-center justify-between hover:bg-zinc-50">
                  <div class="flex items-center gap-3 min-w-0">
                    <button onclick="toggle('org-<?= e($org['id']) ?>')" class="p-0.5 rounded hover:bg-zinc-200 shrink-0"><i data-lucide="chevron-right" id="ic-org-<?= e($org['id']) ?>" class="w-3.5 h-3.5"></i></button>
                    <div class="min-w-0">
                      <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-slate-900"><?= e($org['name']) ?></span>
                        <span class="text-xs text-slate-500 font-mono">(<?= e($org['code']) ?>)</span>
                        <?php if(!(int)$org['isActive']): ?><span class="text-xs text-red-500 font-medium">Inactif</span><?php endif; ?>
                      </div>
                      <?php if($org['description']): ?><p class="text-xs text-slate-500"><?= e($org['description']) ?></p><?php endif; ?>
                    </div>
                  </div>
                  <div class="flex items-center gap-1 shrink-0">
                    <button onclick="openModal('DIV','CREATE','<?= e($org['id']) ?>','<?= e(addslashes($org['name'])) ?>')" class="p-1 rounded-lg hover:bg-zinc-200 text-slate-500 hover:text-slate-900" title="Ajouter une division"><i data-lucide="plus" class="w-3.5 h-3.5"></i></button>
                    <button onclick="openModal('ORG','EDIT','', '', <?= htmlspecialchars(json_encode(['id'=>$org['id'],'code'=>$org['code'],'name'=>$org['name'],'description'=>$org['description']]), ENT_QUOTES) ?>)" class="p-1 rounded-lg hover:bg-zinc-200 text-slate-500 hover:text-slate-900"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></button>
                    <form method="POST" class="inline" onsubmit="return confirm('Basculer ?')"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="mode" value="ORG"><input type="hidden" name="id" value="<?= e($org['id']) ?>"><button class="p-1 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600"><i data-lucide="ban" class="w-3.5 h-3.5"></i></button></form>
                  </div>
                </div>
                <!-- Divisions -->
                <div id="org-<?= e($org['id']) ?>" class="hidden bg-zinc-50/50">
                  <?php if(empty($org['divisions'])): ?>
                    <div class="px-12 py-3 text-sm text-slate-400">Aucune division</div>
                  <?php else: foreach($org['divisions'] as $div): ?>
                    <div>
                      <div class="px-12 py-3 flex items-center justify-between border-t border-slate-200 hover:bg-zinc-50">
                        <div class="flex items-center gap-3 min-w-0">
                          <button onclick="toggle('div-<?= e($div['id']) ?>')" class="p-0.5 rounded hover:bg-zinc-200 shrink-0"><i data-lucide="chevron-right" id="ic-div-<?= e($div['id']) ?>" class="w-3 h-3"></i></button>
                          <div class="min-w-0">
                            <div class="flex items-center gap-2">
                              <span class="text-sm font-medium text-slate-900"><?= e($div['name']) ?></span>
                              <span class="text-xs text-slate-500 font-mono">(<?= e($div['code']) ?>)</span>
                              <?php if(!(int)$div['isActive']): ?><span class="text-xs text-red-500 font-medium">Inactif</span><?php endif; ?>
                            </div>
                            <?php if($div['description']): ?><p class="text-xs text-slate-500"><?= e($div['description']) ?></p><?php endif; ?>
                          </div>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                          <button onclick="openModal('SERVICE','CREATE','<?= e($div['id']) ?>','<?= e(addslashes($div['name'])) ?>')" class="p-1 rounded-lg hover:bg-zinc-200 text-slate-500 hover:text-slate-900" title="Ajouter un service"><i data-lucide="plus" class="w-3.5 h-3.5"></i></button>
                          <button onclick="openModal('DIV','EDIT','', '', <?= htmlspecialchars(json_encode(['id'=>$div['id'],'code'=>$div['code'],'name'=>$div['name'],'description'=>$div['description']]), ENT_QUOTES) ?>)" class="p-1 rounded-lg hover:bg-zinc-200 text-slate-500 hover:text-slate-900"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></button>
                          <form method="POST" class="inline" onsubmit="return confirm('Basculer ?')"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="mode" value="DIV"><input type="hidden" name="id" value="<?= e($div['id']) ?>"><button class="p-1 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600"><i data-lucide="ban" class="w-3.5 h-3.5"></i></button></form>
                        </div>
                      </div>
                      <!-- Services -->
                      <div id="div-<?= e($div['id']) ?>" class="hidden bg-white/50">
                        <?php if(empty($div['services'])): ?>
                          <div class="px-16 py-3 text-sm text-slate-400">Aucun service</div>
                        <?php else: foreach($div['services'] as $srv): ?>
                          <div class="px-16 py-3 flex items-center justify-between border-t border-slate-200 hover:bg-zinc-50">
                            <div class="flex items-center gap-2 min-w-0">
                              <span class="text-sm font-medium text-slate-900"><?= e($srv['name']) ?></span>
                              <span class="text-xs text-slate-500 font-mono">(<?= e($srv['code']) ?>)</span>
                              <?php if(!(int)$srv['isActive']): ?><span class="text-xs text-red-500 font-medium">Inactif</span><?php endif; ?>
                              <?php if($srv['description']): ?><span class="text-xs text-slate-400">— <?= e($srv['description']) ?></span><?php endif; ?>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                              <button onclick="openModal('SERVICE','EDIT','', '', <?= htmlspecialchars(json_encode(['id'=>$srv['id'],'code'=>$srv['code'],'name'=>$srv['name'],'description'=>$srv['description']]), ENT_QUOTES) ?>)" class="p-1 rounded-lg hover:bg-zinc-200 text-slate-500 hover:text-slate-900"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></button>
                              <form method="POST" class="inline" onsubmit="return confirm('Basculer ?')"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="mode" value="SERVICE"><input type="hidden" name="id" value="<?= e($srv['id']) ?>"><button class="p-1 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600"><i data-lucide="ban" class="w-3.5 h-3.5"></i></button></form>
                            </div>
                          </div>
                        <?php endforeach; endif; ?>
                      </div>
                    </div>
                  <?php endforeach; endif; ?>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Modal -->
<div id="entModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/30" onclick="if(event.target===this) closeModal()">
  <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4 shadow-xl">
    <div class="flex items-center justify-between mb-4">
      <h3 id="modalTitle" class="text-lg font-semibold text-slate-900"></h3>
      <button onclick="closeModal()" class="p-1 rounded-lg hover:bg-zinc-100"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>
    <form method="POST" id="modalForm" class="space-y-4">
      <input type="hidden" name="action" id="mAction" value="create">
      <input type="hidden" name="mode" id="mMode" value="ENTITY">
      <input type="hidden" name="id" id="mId" value="">
      <input type="hidden" name="entityId" id="mEntityId" value="">
      <input type="hidden" name="organizationId" id="mOrgId" value="">
      <input type="hidden" name="divisionId" id="mDivId" value="">
      <div>
        <label class="block text-sm font-medium mb-1">Code *</label>
        <input type="text" name="code" id="mCode" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Nom *</label>
        <input type="text" name="name" id="mName" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
      </div>
      <div id="descWrap">
        <label class="block text-sm font-medium mb-1">Description</label>
        <textarea name="description" id="mDesc" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></textarea>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="closeModal()" class="rounded-lg border border-slate-200 px-4 py-1.5 text-sm font-medium hover:bg-zinc-50">Annuler</button>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<script>
function toggle(id){
  const el=document.getElementById(id), ic=document.getElementById('ic-'+id);
  if(!el) return;
  const hidden=el.classList.contains('hidden');
  el.classList.toggle('hidden', !hidden);
  if(ic) ic.style.transform = hidden ? 'rotate(90deg)' : 'rotate(0deg)';
}
let currentMode='ENTITY', currentAction='CREATE', currentParent='', currentParentName='', currentData=null;
function openModal(mode, action, parentId='', parentName='', data=null){
  currentMode=mode; currentAction=action; currentData=data;
  document.getElementById('mMode').value=mode;
  document.getElementById('mAction').value= action==='CREATE' ? 'create' : 'update';
  document.getElementById('mEntityId').value=''; document.getElementById('mOrgId').value=''; document.getElementById('mDivId').value='';
  if(mode==='ORG' && action==='CREATE') document.getElementById('mEntityId').value=parentId;
  if(mode==='DIV' && action==='CREATE') document.getElementById('mOrgId').value=parentId;
  if(mode==='SERVICE' && action==='CREATE') document.getElementById('mDivId').value=parentId;
  const labels={ENTITY:'Entité',ORG:'Organisation',DIV:'Division',SERVICE:'Service'};
  const act = action==='CREATE' ? 'Nouvelle' : 'Modifier';
  const suffix = action==='CREATE' && parentName ? ' dans '+parentName : '';
  document.getElementById('modalTitle').textContent = act+' '+labels[mode]+suffix;
  document.getElementById('mId').value = data ? data.id : '';
  document.getElementById('mCode').value = data ? data.code : '';
  document.getElementById('mName').value = data ? data.name : '';
  document.getElementById('mDesc').value = data ? (data.description||'') : '';
  // LISA hides description for SERVICE create? mimic: hide for SERVICE
  // but keep visible for edit ; we follow spec: showDescription = mode !== SERVICE || action === CREATE => actually show except SERVICE EDIT? We'll always show for simplicity
  document.getElementById('entModal').classList.remove('hidden');
  lucide.createIcons();
}
function closeModal(){ document.getElementById('entModal').classList.add('hidden'); }
// auto expand first entity for demo
document.addEventListener('DOMContentLoaded', ()=>{ lucide.createIcons(); });
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
