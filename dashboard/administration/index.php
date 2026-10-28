<?php
$pageTitle='Utilisateurs';
require_once __DIR__ . '/../../includes/header.php';
$pdo=db();
$me=currentUser();
if(!isAdmin()){
  echo '<div class="card p-10 text-center"><p class="font-semibold">Accès réservé aux administrateurs</p><a href="'.BASE_URL.'/dashboard/index.php" class="text-blue-600 text-sm">Retour</a></div>';
  require_once __DIR__ . '/../../includes/footer.php'; exit;
}
$msg=''; $err='';

// --- Données référence --- tenant isolation
require_once __DIR__ . '/../../includes/licence.php';
licenceEnsureTables($pdo);
$myEntId=getUserEntityId($pdo,$me);
$isSuper=isSuperAdmin();
if($isSuper){
  $entities=$pdo->query("SELECT * FROM entities WHERE isActive=1 ORDER BY name")->fetchAll();
  $allServices=$pdo->query("SELECT s.*, d.organizationId, o.entityId, d.name as divName, o.name as orgName, e.name as entName FROM services s LEFT JOIN divisions d ON d.id=s.divisionId LEFT JOIN organizations o ON o.id=d.organizationId LEFT JOIN entities e ON e.id=o.entityId WHERE s.isActive=1 ORDER BY e.name, o.name, s.name")->fetchAll();
}else{
  $entities=$pdo->prepare("SELECT * FROM entities WHERE id=? AND isActive=1"); $entities->execute([$myEntId]); $entities=$entities->fetchAll();
  $allServices=$pdo->prepare("SELECT s.*, d.organizationId, o.entityId, d.name as divName, o.name as orgName, e.name as entName FROM services s LEFT JOIN divisions d ON d.id=s.divisionId LEFT JOIN organizations o ON o.id=d.organizationId LEFT JOIN entities e ON e.id=o.entityId WHERE s.isActive=1 AND o.entityId=? ORDER BY e.name, o.name, s.name"); $allServices->execute([$myEntId]); $allServices=$allServices->fetchAll();
  if(empty($allServices)){$err='Aucun service pour votre entité JD BUSINESS — contactez le SUPERADMIN.';}
}

// modules disponibles
$ALL_MODULES=[
  'dashboard'=>'Tableau de bord',
  'dossiers'=>'Dossiers',
  'acquisition'=>'Acquisition',
  'numerisation'=>'Numérisation',
  'recherche'=>'Recherche',
  'versions'=>'Gestion des versions',
  'preview'=>'Prévisualisation',
  'partage'=>'Partage',
  'collaboration'=>'Collaboration',
  'workflows'=>'Workflows',
  'securite'=>'Sécurité',
  'archivage'=>'Archivage',
  'administration'=>'Administration (users)',
  'entites'=>'Entités',
  'groupes'=>'Groupes',
  'parametrage'=>'Paramétrage',
  'statistiques'=>'Statistiques',
  'corbeille'=>'Corbeille',
  'journal'=>'Journal',
];

// ---- Actions POST ----
if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'';
  if($action==='create' || $action==='update'){
    $id=trim($_POST['id']??'') ?: cuid();
    $firstName=trim($_POST['firstName']??'');
    $lastName=trim($_POST['lastName']??'');
    $email=trim($_POST['email']??'');
    $login=trim($_POST['login']??'');
    $phone=trim($_POST['phone']??'');
    $role=$_POST['role']??'USER';
    $serviceId=$_POST['serviceId']??'';
    $isActive=isset($_POST['isActive'])?1:0;
    $password=$_POST['password']??'';
    $modulesArr=$_POST['modules']??[];
    if(!is_array($modulesArr)) $modulesArr=[];
    // filtrer modules valides
    $modulesArr=array_values(array_intersect($modulesArr, array_keys($ALL_MODULES)));
    $modulesJson=json_encode($modulesArr, JSON_UNESCAPED_UNICODE);
    $extraSids=$_POST['extraServiceIds']??[];
if(!is_array($extraSids)) $extraSids=[];
    // tenant: service doit appartenir à mon entité sauf SUPERADMIN
    if(!$isSuper && $serviceId){
      $chk=$pdo->prepare("SELECT 1 FROM services s JOIN divisions d ON d.id=s.divisionId JOIN organizations o ON o.id=d.organizationId WHERE s.id=? AND o.entityId=?"); $chk->execute([$serviceId,$myEntId]); if(!$chk->fetchColumn()){ $err='Service hors de votre entité JD BUSINESS.'; goto skipCreateUser; }
      foreach($extraSids as $es){ $c2=$pdo->prepare("SELECT 1 FROM services s JOIN divisions d ON d.id=s.divisionId JOIN organizations o ON o.id=d.organizationId WHERE s.id=? AND o.entityId=?"); $c2->execute([$es,$myEntId]); if(!$c2->fetchColumn()){ $err='Service additionnel hors entité.'; goto skipCreateUser; } }
    }
// valider service primaire
    if(!$firstName||!$lastName||!$email||!$login||!$serviceId){
      $err='Champs obligatoires manquants (nom, email, login, service/entité).';
    } elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)){
      $err='Email invalide.';
    } else {
      try{
        if($action==='create'){
          if(!$password) $password='admin123';
          $hash=password_hash($password,PASSWORD_BCRYPT);
          $pdo->prepare("INSERT INTO users (id,email,login,password,firstName,lastName,phone,role,serviceId,isActive,modules) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
              ->execute([$id,$email,$login,$hash,$firstName,$lastName,$phone?:null,$role,$serviceId,$isActive,$modulesJson]);
          // accès multi-services : primaire + extras
          $allSids=array_unique(array_merge([$serviceId], $extraSids));
          foreach($allSids as $sid){
            if(!$sid) continue;
            $pdo->prepare("INSERT IGNORE INTO user_service_access (id,userId,serviceId) VALUES (?,?,?)")->execute([cuid(),$id,$sid]);
          }
          $msg="Utilisateur $login créé (mdp: $password) • ".count($modulesArr)." modules • ".count($allSids)." service(s)";
        } else {
          $origId=$_POST['orig_id']??$id;
          if($password!==''){
            $hash=password_hash($password,PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET firstName=?,lastName=?,email=?,login=?,phone=?,role=?,serviceId=?,isActive=?,password=?,modules=? WHERE id=?")
                ->execute([$firstName,$lastName,$email,$login,$phone?:null,$role,$serviceId,$isActive,$hash,$modulesJson,$origId]);
          } else {
            $pdo->prepare("UPDATE users SET firstName=?,lastName=?,email=?,login=?,phone=?,role=?,serviceId=?,isActive=?,modules=? WHERE id=?")
                ->execute([$firstName,$lastName,$email,$login,$phone?:null,$role,$serviceId,$isActive,$modulesJson,$origId]);
          }
          // maj accès services : reset puis insert
          $pdo->prepare("DELETE FROM user_service_access WHERE userId=?")->execute([$origId]);
          $allSids=array_unique(array_merge([$serviceId], $extraSids));
          foreach($allSids as $sid){
            if(!$sid) continue;
            $pdo->prepare("INSERT IGNORE INTO user_service_access (id,userId,serviceId) VALUES (?,?,?)")->execute([cuid(),$origId,$sid]);
          }
          $msg="Utilisateur $login mis à jour — ".count($modulesArr)." modules, ".count($allSids)." service(s).";
        }
      }catch(PDOException $e){
        $err= "Erreur: ".$e->getMessage();
        if(str_contains($e->getMessage(),'Duplicate')) $err="Email ou login déjà utilisé.";
      }
      skipCreateUser: ;
    }
  }
  if($action==='delete'){
    $uid=$_POST['uid']??'';
    if($uid===$me['id']){ $err="Vous ne pouvez pas vous supprimer."; }
    else {
      $cnt=$pdo->prepare("SELECT COUNT(*) FROM dossiers WHERE creatorId=?"); $cnt->execute([$uid]); $has=$cnt->fetchColumn();
      if($has>0){
        $pdo->prepare("UPDATE users SET isActive=0 WHERE id=?")->execute([$uid]);
        $msg="Utilisateur désactivé (possède $has dossiers).";
      } else {
        $pdo->prepare("DELETE FROM user_service_access WHERE userId=?")->execute([$uid]);
        $pdo->prepare("DELETE FROM group_members WHERE userId=?")->execute([$uid]);
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
        $msg="Utilisateur supprimé.";
      }
    }
  }
  if($action==='toggle'){
    $uid=$_POST['uid']??''; $pdo->prepare("UPDATE users SET isActive=1-isActive WHERE id=?")->execute([$uid]);
    $msg="Statut modifié.";
  }
}

// Divisions pour le formulaire
$allDivisions=$pdo->query("SELECT d.*, o.entityId, o.name as orgName, e.name as entName FROM divisions d LEFT JOIN organizations o ON o.id=d.organizationId LEFT JOIN entities e ON e.id=o.entityId WHERE d.isActive=1 ORDER BY e.name, o.name, d.name")->fetchAll();

// ---- Recherche / pagination ---- tenant
$q=trim($_GET['q']??''); $roleFilter=$_GET['role']??'ALL'; $entityFilter=$_GET['entity']??'ALL'; $page=max(1,(int)($_GET['page']??1)); $limit=12; $offset=($page-1)*$limit;
$where="1=1"; $params=[];
if(!$isSuper){ $where.=" AND e.id=?"; $params[]=$myEntId; $entityFilter=$myEntId; }
if($q!==''){ $where.=" AND (u.firstName LIKE ? OR u.lastName LIKE ? OR u.email LIKE ? OR u.login LIKE ?)"; $like="%$q%"; array_push($params,$like,$like,$like,$like); }
if($roleFilter!=='ALL'){ $where.=" AND u.role=?"; $params[]=$roleFilter; }
if($isSuper && $entityFilter!=='ALL'){ $where.=" AND e.id=?"; $params[]=$entityFilter; }
$stmt=$pdo->prepare("SELECT COUNT(*) FROM users u LEFT JOIN services s ON s.id=u.serviceId LEFT JOIN divisions d ON d.id=s.divisionId LEFT JOIN organizations o ON o.id=d.organizationId LEFT JOIN entities e ON e.id=o.entityId WHERE $where"); $stmt->execute($params); $total=(int)$stmt->fetchColumn(); $pages=max(1,ceil($total/$limit));
$stmt=$pdo->prepare("SELECT u.*, s.name as serviceName, e.name as entityName, e.code as entityCode FROM users u LEFT JOIN services s ON s.id=u.serviceId LEFT JOIN divisions d ON d.id=s.divisionId LEFT JOIN organizations o ON o.id=d.organizationId LEFT JOIN entities e ON e.id=o.entityId WHERE $where ORDER BY u.createdAt DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params); $rows=$stmt->fetchAll();

// edit data
$editRow=null; $editModules=[]; $editExtraSids=[];
$view=$_GET['view']??'';
if($view==='edit' && isset($_GET['id'])){
  $s=$pdo->prepare("SELECT * FROM users WHERE id=?"); $s->execute([$_GET['id']]); $editRow=$s->fetch();
  if($editRow){
    $editModules=json_decode($editRow['modules']??'[]', true) ?: [];
    $st=$pdo->prepare("SELECT serviceId FROM user_service_access WHERE userId=?"); $st->execute([$editRow['id']]); $editExtraSids=array_column($st->fetchAll(),'serviceId');
    // retirer primaire de extra pour affichage
    $editExtraSids=array_values(array_diff($editExtraSids, [$editRow['serviceId']]));
  }
}
$isForm = $view==='create' || $view==='edit';
// pour create, modules par défaut selon rôle
if($view==='create' && empty($editModules)){
  $editModules=['dashboard','dossiers','recherche','preview','collaboration'];
}
?>
<div class="space-y-4">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div><h1 class="text-xl font-extrabold text-slate-900">Utilisateurs</h1><p class="text-sm text-slate-500"><?= $total ?> compte<?= $total>1?'s':'' ?> • Rôles, entités et accès modules</p></div>
    <a href="?view=create" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-xl"><i data-lucide="user-plus" class="w-4 h-4"></i> Nouvel utilisateur</a>
  </div>
  <?php if($msg): ?><div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-2.5 rounded-xl text-sm"><?= e($msg) ?></div><?php endif; ?>
  <?php if($err): ?><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-2.5 rounded-xl text-sm"><?= e($err) ?></div><?php endif; ?>

  <?php if($isForm): ?>
  <div class="card p-6">
    <div class="flex items-center justify-between mb-5"><h2 class="font-bold text-slate-900"><?= $editRow?'Modifier':'Créer' ?> un utilisateur</h2><a href="index.php" class="text-sm text-slate-500 hover:text-slate-700">← Retour liste</a></div>
    <form method="POST" id="userForm" class="space-y-6">
      <input type="hidden" name="action" value="<?= $editRow?'update':'create' ?>">
      <?php if($editRow): ?><input type="hidden" name="orig_id" value="<?= e($editRow['id']) ?>"><?php endif; ?>

      <!-- Identité -->
      <div class="grid md:grid-cols-2 gap-4">
        <div><label class="text-sm font-medium">Prénom *</label><input name="firstName" required value="<?= e($editRow['firstName']??'') ?>" class="input mt-1"></div>
        <div><label class="text-sm font-medium">Nom *</label><input name="lastName" required value="<?= e($editRow['lastName']??'') ?>" class="input mt-1"></div>
        <div><label class="text-sm font-medium">Email *</label><input type="email" name="email" required value="<?= e($editRow['email']??'') ?>" class="input mt-1"></div>
        <div><label class="text-sm font-medium">Login *</label><input name="login" required value="<?= e($editRow['login']??'') ?>" class="input mt-1" placeholder="jdupont"></div>
        <div><label class="text-sm font-medium">Téléphone</label><input name="phone" value="<?= e($editRow['phone']??'') ?>" class="input mt-1"></div>
        <div><label class="text-sm font-medium">Rôle *</label>
          <select name="role" id="roleSelect" class="input mt-1">
            <option value="USER" <?= ($editRow['role']??'USER')==='USER'?'selected':'' ?>>USER — Standard</option>
            <option value="SUPERVISOR" <?= ($editRow['role']??'')==='SUPERVISOR'?'selected':'' ?>>SUPERVISOR — Chef de service</option>
            <option value="ADMIN" <?= ($editRow['role']??'')==='ADMIN'?'selected':'' ?>>ADMIN — Administrateur</option>
          </select>
          <p class="text-xs text-slate-400 mt-1">ADMIN = tous les modules, SUPERVISOR = gestion d'équipe</p>
        </div>
        <div><label class="text-sm font-medium">Mot de passe <?= $editRow?' (laisser vide pour conserver)':'*' ?></label><input type="password" name="password" <?= $editRow?'':'required' ?> placeholder="••••••••" class="input mt-1"></div>
        <div class="flex items-center gap-2 pt-6"><label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" name="isActive" value="1" <?= ($editRow? (int)$editRow['isActive']:1)?'checked':'' ?> class="rounded"> Compte actif</label></div>
      </div>

      <!-- Entité, Direction & Service principal -->
      <div class="border-t pt-5">
        <h3 class="font-semibold text-slate-900 flex items-center gap-2"><i data-lucide="building-2" class="w-4 h-4"></i> Entité & affectation</h3>
        <p class="text-xs text-slate-500 mb-3">Sélectionnez l'entité, puis la direction, puis le service.</p>
        <div class="grid md:grid-cols-3 gap-4">
          <div>
            <label class="text-sm font-medium">Entité *</label>
            <select id="entityFilter" class="input mt-1">
              <option value="">-- Choisir une entité --</option>
              <?php
              $editEntityId='';
              $editDivisionId='';
              if($editRow && isset($editRow['serviceId'])){
                $svcInfo=$pdo->prepare("SELECT s.entityId, d.id as divisionId FROM services s LEFT JOIN divisions d ON d.id=s.divisionId WHERE s.id=?");
                $svcInfo->execute([$editRow['serviceId']]);
                $svcRow=$svcInfo->fetch();
                if($svcRow){ $editEntityId=$svcRow['entityId']; $editDivisionId=$svcRow['divisionId']; }
              }
              ?>
              <?php foreach($entities as $ent): ?>
                <option value="<?= e($ent['id']) ?>" <?= $editEntityId===$ent['id']?'selected':'' ?>><?= e($ent['name']) ?> (<?= e($ent['code']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="text-sm font-medium">Direction / Division *</label>
            <select id="divisionFilter" class="input mt-1">
              <option value="">-- Choisir une direction --</option>
              <?php foreach($allDivisions as $div): ?>
                <option value="<?= e($div['id']) ?>" data-entity="<?= e($div['entityId']) ?>" <?= $editDivisionId===$div['id']?'selected':'' ?>>
                  <?= e($div['name']) ?> (<?= e($div['code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="text-sm font-medium">Service principal *</label>
            <select name="serviceId" id="serviceSelect" required class="input mt-1">
              <option value="">-- Choisir un service --</option>
              <?php foreach($allServices as $s): ?>
                <option value="<?= e($s['id']) ?>" data-entity="<?= e($s['entityId']) ?>" data-division="<?= e($s['divisionId']) ?>" <?= ($editRow['serviceId']??'')===$s['id']?'selected':'' ?>>
                  <?= e($s['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <!-- Services additionnels -->
        <div class="mt-4">
          <label class="text-sm font-medium">Services additionnels (accès multi-services)</label>
          <p class="text-xs text-slate-400 mb-2">L'utilisateur aura accès aux dossiers de ces services en plus de son service principal.</p>
          <div id="extraServicesBox" class="grid md:grid-cols-2 lg:grid-cols-3 gap-2 max-h-48 overflow-y-auto border rounded-xl p-3 bg-slate-50/50">
            <?php foreach($allServices as $s): ?>
              <label class="flex items-center gap-2 text-sm bg-white border rounded-lg px-3 py-2 hover:bg-slate-50 cursor-pointer extra-opt" data-entity="<?= e($s['entityId']) ?>">
                <input type="checkbox" name="extraServiceIds[]" value="<?= e($s['id']) ?>" <?= in_array($s['id'], $editExtraSids)?'checked':'' ?> class="rounded">
                <span class="truncate"><?= e($s['name']) ?> <span class="text-xs text-slate-400">— <?= e($s['entName']) ?></span></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Modules / habilitations -->
      <div class="border-t pt-5">
        <div class="flex items-center justify-between">
          <h3 class="font-semibold text-slate-900 flex items-center gap-2"><i data-lucide="shield-check" class="w-4 h-4"></i> Habilitations — accès aux modules</h3>
          <div class="flex gap-1 text-xs">
            <button type="button" onclick="document.querySelectorAll('[name=&quot;modules[]&quot;]').forEach(c=>c.checked=true)" class="border px-2 py-1 rounded-lg hover:bg-slate-50">Tout</button>
            <button type="button" onclick="document.querySelectorAll('[name=&quot;modules[]&quot;]').forEach(c=>c.checked=false)" class="border px-2 py-1 rounded-lg hover:bg-slate-50">Aucun</button>
          </div>
        </div>
        <p class="text-xs text-slate-400 mb-3">ADMIN a automatiquement tous les modules. Pour les autres rôles, cochez les modules autorisés.</p>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-2">
          <?php foreach($ALL_MODULES as $key=>$label): ?>
            <label class="flex items-center gap-2.5 border rounded-xl px-3 py-2.5 bg-white hover:bg-slate-50 cursor-pointer">
              <input type="checkbox" name="modules[]" value="<?= e($key) ?>" <?= in_array($key,$editModules)?'checked':'' ?> class="rounded">
              <span class="text-sm"><?= e($label) ?></span>
              <span class="ml-auto text-[10px] font-mono text-slate-400"><?= e($key) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="flex gap-2 pt-2"><button class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium"><?= $editRow?'Enregistrer':'Créer l\'utilisateur' ?></button><a href="index.php" class="border px-6 py-2.5 rounded-xl text-sm">Annuler</a></div>
    </form>
  </div>
  <script>
  (function(){
    const entityFilter=document.getElementById('entityFilter');
    const divisionFilter=document.getElementById('divisionFilter');
    const serviceSelect=document.getElementById('serviceSelect');
    const extraOpts=document.querySelectorAll('.extra-opt');

    // pré-sélection entité selon service actuel
    const currentSid=serviceSelect.value;
    if(currentSid){
      const opt=serviceSelect.querySelector('option[value="'+currentSid+'"]');
      if(opt){
        entityFilter.value=opt.dataset.entity||'';
        // pré-sélection division
        if(opt.dataset.division){
          divisionFilter.value=opt.dataset.division;
        }
      }
    }

    function filterDivisions(){
      const ent=entityFilter.value;
      let visible=0;
      [...divisionFilter.options].forEach(o=>{
        if(!o.value){ o.hidden=false; o.disabled=false; return; }
        const show=!ent || o.dataset.entity===ent;
        o.hidden=!show; o.disabled=!show;
        if(show) visible++;
      });
      // Si une seule division, la sélectionner automatiquement
      const visibleOpts=[...divisionFilter.options].filter(o=>o.value && !o.hidden);
      if(visibleOpts.length===1){
        divisionFilter.value=visibleOpts[0].value;
      }
      // helper message
      let hint=document.getElementById('divHint');
      if(!hint){ hint=document.createElement('p'); hint.id='divHint'; hint.className='text-xs mt-1'; divisionFilter.parentNode.appendChild(hint); }
      if(visible===0){ hint.textContent='Aucune direction pour cette entité'; hint.className='text-xs mt-1 text-amber-600'; }
      else { hint.textContent=visible+' direction(s) disponible(s)'; hint.className='text-xs mt-1 text-slate-400'; }
    }

    function filterServices(){
      const ent=entityFilter.value;
      const div=divisionFilter.value;
      let visible=0;
      [...serviceSelect.options].forEach(o=>{
        if(!o.value){ o.hidden=false; o.disabled=false; return; }
        const matchEnt=!ent || o.dataset.entity===ent;
        const matchDiv=!div || o.dataset.division===div;
        const show=matchEnt && matchDiv;
        o.hidden=!show; o.disabled=!show;
        if(show) visible++;
      });
      // Si un seul service, le sélectionner automatiquement
      const visibleOpts=[...serviceSelect.options].filter(o=>o.value && !o.hidden);
      if(visibleOpts.length===1){
        serviceSelect.value=visibleOpts[0].value;
      }
      // helper message
      let hint=document.getElementById('svcHint');
      if(!hint){ hint=document.createElement('p'); hint.id='svcHint'; hint.className='text-xs mt-1'; serviceSelect.parentNode.appendChild(hint); }
      if(visible===0){ hint.textContent='Aucun service pour cette direction — créez-le dans Entités'; hint.className='text-xs mt-1 text-amber-600'; }
      else { hint.textContent=visible+' service(s) disponible(s)'; hint.className='text-xs mt-1 text-slate-400'; }
    }

    function filterExtra(){
      const ent=entityFilter.value;
      const div=divisionFilter.value;
      let extraVisible=0;
      extraOpts.forEach(l=>{
        const matchEnt=!ent || l.dataset.entity===ent;
        // pour les extras, on filtre aussi par division si disponible
        const show=matchEnt;
        l.style.display=show?'':'none';
        if(show) extraVisible++;
      });
      let eh=document.getElementById('extraHint');
      if(!eh){ eh=document.createElement('p'); eh.id='extraHint'; eh.className='text-xs mt-2'; document.getElementById('extraServicesBox').parentNode.appendChild(eh); }
      eh.textContent=extraVisible===0?'Aucun service additionnel pour ce filtre.':'';
      eh.className='text-xs mt-2 '+(extraVisible===0?'text-amber-600':'text-slate-400');
    }

    entityFilter.addEventListener('change', ()=>{ filterDivisions(); filterServices(); filterExtra(); });
    divisionFilter.addEventListener('change', ()=>{ filterServices(); filterExtra(); });

    // Initial filter
    filterDivisions();
    filterServices();
    filterExtra();

    // ADMIN auto-check tous modules
    const roleSel=document.getElementById('roleSelect');
    roleSel.addEventListener('change', ()=>{
      if(roleSel.value==='ADMIN'){
        document.querySelectorAll('[name="modules[]"]').forEach(c=>c.checked=true);
      }
    });
  })();
  </script>
  <?php else: ?>
  <form method="GET" class="card p-3 flex flex-wrap gap-2 items-center">
    <div class="relative flex-1 min-w-[200px]"><i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i><input name="q" value="<?= e($q) ?>" placeholder="Rechercher nom, email, login..." class="input pl-9"></div>
    <select name="role" class="input w-36"><option value="ALL">Tous rôles</option><option value="ADMIN" <?= $roleFilter==='ADMIN'?'selected':'' ?>>ADMIN</option><option value="SUPERVISOR" <?= $roleFilter==='SUPERVISOR'?'selected':'' ?>>SUPERVISOR</option><option value="USER" <?= $roleFilter==='USER'?'selected':'' ?>>USER</option></select>
    <select name="entity" class="input w-48"><option value="ALL">Toutes entités</option><?php foreach($entities as $ent): ?><option value="<?= e($ent['id']) ?>" <?= $entityFilter===$ent['id']?'selected':'' ?>><?= e($ent['name']) ?></option><?php endforeach; ?></select>
    <button class="bg-slate-900 text-white px-4 py-2 rounded-xl text-sm">Filtrer</button>
    <?php if($q||$roleFilter!=='ALL'||$entityFilter!=='ALL'): ?><a href="index.php" class="text-sm text-slate-500">Réinitialiser</a><?php endif; ?>
  </form>
  <div class="card overflow-hidden">
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead><tr class="bg-slate-50 border-b text-left text-slate-500"><th class="px-4 py-3 font-medium">Utilisateur</th><th class="px-4 py-3 font-medium">Entité / Service</th><th class="px-4 py-3 font-medium">Rôle</th><th class="px-4 py-3 font-medium">Modules</th><th class="px-4 py-3 font-medium">Statut</th><th class="px-4 py-3 font-medium text-right">Actions</th></tr></thead>
      <tbody class="divide-y">
      <?php foreach($rows as $u): 
        $mods=json_decode($u['modules']??'[]', true)?:[];
      ?>
        <tr class="hover:bg-slate-50/50">
          <td class="px-4 py-3"><div class="font-medium text-slate-900"><?= e($u['firstName'].' '.$u['lastName']) ?></div><div class="text-xs text-slate-500"><?= e($u['email']) ?> • <span class="font-mono"><?= e($u['login']) ?></span></div></td>
          <td class="px-4 py-3"><div class="text-sm text-slate-700"><?= e($u['entityName']??'—') ?></div><div class="text-xs text-slate-500"><?= e($u['serviceName']??'—') ?></div></td>
          <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold <?= $u['role']==='ADMIN'?'bg-violet-50 text-violet-700':($u['role']==='SUPERVISOR'?'bg-blue-50 text-blue-700':'bg-slate-100 text-slate-600') ?>"><?= e($u['role']) ?></span></td>
          <td class="px-4 py-3"><div class="flex flex-wrap gap-1 max-w-[220px]"><?php if(empty($mods)): ?><span class="text-xs text-slate-400">—</span><?php else: foreach(array_slice($mods,0,4) as $m): ?><span class="inline-flex px-1.5 py-0.5 rounded-full bg-slate-100 text-[10px]"><?= e($m) ?></span><?php endforeach; if(count($mods)>4): ?><span class="text-xs text-slate-400">+<?= count($mods)-4 ?></span><?php endif; endif; ?></div></td>
          <td class="px-4 py-3"><?php if((int)$u['isActive']): ?><span class="inline-flex items-center gap-1 text-emerald-600 text-xs"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Actif</span><?php else: ?><span class="text-slate-400 text-xs">Inactif</span><?php endif; ?></td>
          <td class="px-4 py-3">
            <div class="flex justify-end gap-1">
              <a href="?view=edit&id=<?= e($u['id']) ?>" class="h-7 w-7 inline-flex items-center justify-center rounded-lg border hover:bg-white bg-slate-50" title="Éditer"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></a>
              <form method="POST" onsubmit="return confirm('Basculer actif/inactif ?')" class="inline"><input type="hidden" name="action" value="toggle"><input type="hidden" name="uid" value="<?= e($u['id']) ?>"><button class="h-7 w-7 inline-flex items-center justify-center rounded-lg border hover:bg-white bg-slate-50" title="Activer/Désactiver"><i data-lucide="power" class="w-3.5 h-3.5"></i></button></form>
              <form method="POST" onsubmit="return confirm('Supprimer/désactiver cet utilisateur ?')" class="inline"><input type="hidden" name="action" value="delete"><input type="hidden" name="uid" value="<?= e($u['id']) ?>"><button class="h-7 w-7 inline-flex items-center justify-center rounded-lg border hover:bg-red-50 text-red-600" title="Supprimer"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button></form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if(empty($rows)): ?><tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucun utilisateur trouvé</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
    <?php if($pages>1): ?><div class="border-t px-4 py-3 flex justify-center gap-1 text-sm"><?php for($p=1;$p<=$pages;$p++): ?><a href="?q=<?= urlencode($q) ?>&role=<?= e($roleFilter) ?>&entity=<?= e($entityFilter) ?>&page=<?= $p ?>" class="px-3 py-1.5 rounded-lg <?= $p===$page?'bg-blue-600 text-white':'border hover:bg-slate-50' ?>"><?= $p ?></a><?php endfor; ?></div><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
