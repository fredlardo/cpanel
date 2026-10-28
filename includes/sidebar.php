<?php
$current = $_SERVER['REQUEST_URI'] ?? '';
function navActive(string $path): string { return str_contains($_SERVER['REQUEST_URI'] ?? '', $path) ? 'active' : ''; }
$dashActive = str_contains($current,'/dashboard/index') || preg_match('#/dashboard/?(\?.*)?$#',$current);
$u = currentUser();
function can(string $m): bool {
  $cu=currentUser(); if(!$cu) return false;
  if(!in_array($m,['dashboard','ma-licence'],true)){
    try{ require_once __DIR__.'/licence.php'; $pdo=db(); $eid=getUserEntityId($pdo,$cu); if($eid && licenceIsBlocked($pdo,$eid)) return false; }catch(Exception $e){}
  }
  $mods=$cu['modules']??[]; if(in_array('*',$mods,true)) return true; if($cu['role']==='SUPERADMIN') return in_array($m, $mods, true); if($cu['role']==='ADMIN' && in_array($m, ['licences','superadmin'], true)) return false; if($cu['role']==='ADMIN') return true; if(empty($mods)){ $fallback=['dashboard','dossiers','acquisition','numerisation','recherche','archivage','corbeille','journal','statistiques','modeles']; return in_array($m,$fallback,true); } return in_array($m, $mods, true);
}
function isSuperAdminView(): bool { return (currentUser()['role']??'')==='SUPERADMIN'; }
?>
<aside class="sidebar" id="sidebar">
  <div class="topnav">
    <a href="<?= BASE_URL ?>/dashboard/index.php" class="topnav-logo !p-0 hover:!bg-transparent" aria-label="PRAGMA Logistics">
      <img src="<?= BASE_URL ?>/assets/img/pragma-logo-transparent.png?v=1" alt="PRAGMA Logistics" class="h-14 w-auto object-contain">
    </a>
    <nav class="topnav-menu" id="sidebarNav">
    <a href="<?= BASE_URL ?>/dashboard/index.php" class="<?= $dashActive ? 'active' : '' ?>"><i data-lucide="layout-dashboard" class="w-5 h-5 shrink-0"></i> Tableau de bord</a>

    <!-- 1. DOSSIERS -->
    <div class="sidebar-group" data-group="dossiers">
      <button type="button" class="sidebar-group-head" onclick="toggleGroup('dossiers')">
        <span class="flex items-center gap-2"><i data-lucide="folders" class="w-4 h-4"></i> Dossiers</span>
        <i data-lucide="chevron-down" class="w-4 h-4 transition-transform sidebar-chev" data-chev="dossiers"></i>
      </button>
      <div class="sidebar-group-body" data-body="dossiers">
        <?php if(can('dossiers')): ?>
        <a href="<?= BASE_URL ?>/dashboard/dossiers/nouveau.php" class="<?= navActive('/nouveau') ?>"><i data-lucide="folder-plus" class="w-5 h-5 shrink-0"></i> Nouveau dossier</a>
        <a href="<?= BASE_URL ?>/dashboard/dossiers/index.php" class="<?= (str_contains($current,'/dossiers/index') && !str_contains($current,'instruire') && !str_contains($current,'suivi'))?'active':'' ?>"><i data-lucide="folder-open" class="w-5 h-5 shrink-0"></i> Mes dossiers</a>
        <a href="<?= BASE_URL ?>/dashboard/dossiers/index.php?filter=instruire" class="<?= str_contains($current,'instruire')?'active':'' ?>"><i data-lucide="inbox" class="w-5 h-5 shrink-0"></i> Dossiers reçus</a>
        <a href="<?= BASE_URL ?>/dashboard/dossiers/index.php?filter=suivi" class="<?= str_contains($current,'suivi')?'active':'' ?>"><i data-lucide="eye" class="w-5 h-5 shrink-0"></i> Mes dossiers à suivre</a>
        <a href="<?= BASE_URL ?>/dashboard/rejets/index.php" class="<?= navActive('/rejets') ?>"><i data-lucide="x-octagon" class="w-5 h-5 shrink-0"></i> Dossiers rejetés</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- 2. GED / EXPLOITATION -->
    <div class="sidebar-group" data-group="ged">
      <button type="button" class="sidebar-group-head" onclick="toggleGroup('ged')">
        <span class="flex items-center gap-2"><i data-lucide="library" class="w-4 h-4"></i> GED</span>
        <i data-lucide="chevron-down" class="w-4 h-4 transition-transform sidebar-chev" data-chev="ged"></i>
      </button>
      <div class="sidebar-group-body" data-body="ged">
        <?php if(can('acquisition')): ?><a href="<?= BASE_URL ?>/dashboard/acquisition/index.php" class="<?= navActive('/acquisition') ?>"><i data-lucide="upload-cloud" class="w-5 h-5 shrink-0"></i> Acquisition</a><?php endif; ?>
        <?php if(can('numerisation')): ?><a href="<?= BASE_URL ?>/dashboard/numerisation/index.php" class="<?= navActive('/numerisation') ?>"><i data-lucide="scan-line" class="w-5 h-5 shrink-0"></i> Numérisation</a><?php endif; ?>
        <?php if(can('recherche')): ?><a href="<?= BASE_URL ?>/dashboard/recherche/index.php" class="<?= navActive('/recherche') ?>"><i data-lucide="search" class="w-5 h-5 shrink-0"></i> Recherche</a><?php endif; ?>
        <?php if(can('archivage')): ?><a href="<?= BASE_URL ?>/dashboard/archives/index.php" class="<?= navActive('/archives') ?>"><i data-lucide="archive" class="w-5 h-5 shrink-0"></i> Archives</a><?php endif; ?>
        <?php if(can('corbeille')): ?><a href="<?= BASE_URL ?>/dashboard/corbeille/index.php" class="<?= navActive('/corbeille') ?>"><i data-lucide="trash-2" class="w-5 h-5 shrink-0"></i> Corbeille</a><?php endif; ?>
      </div>
    </div>

    <!-- 3. ADMINISTRATION -->
    <div class="sidebar-group" data-group="admin">
      <button type="button" class="sidebar-group-head" onclick="toggleGroup('admin')">
        <span class="flex items-center gap-2"><i data-lucide="shield" class="w-4 h-4"></i> Administration</span>
        <i data-lucide="chevron-down" class="w-4 h-4 transition-transform sidebar-chev" data-chev="admin"></i>
      </button>
      <div class="sidebar-group-body" data-body="admin">
        <?php if(can('administration')): ?><a href="<?= BASE_URL ?>/dashboard/administration/index.php" class="<?= navActive('/administration') ?>"><i data-lucide="users" class="w-5 h-5 shrink-0"></i> Utilisateurs</a><?php endif; ?>
        <?php if(can('groupes')): ?><a href="<?= BASE_URL ?>/dashboard/groupes/index.php" class="<?= navActive('/groupes') ?>"><i data-lucide="users-round" class="w-5 h-5 shrink-0"></i> Groupes</a><?php endif; ?>
        <?php if(isSuperAdmin() || can('entites')): ?><a href="<?= BASE_URL ?>/dashboard/entites/index.php" class="<?= navActive('/entites') ?>"><i data-lucide="network" class="w-5 h-5 shrink-0"></i> Entités</a><?php endif; ?>
        <?php if(isSuperAdminView()): ?><a href="<?= BASE_URL ?>/dashboard/licences/index.php" class="<?= navActive('/licences') ?>"><i data-lucide="key-round" class="w-5 h-5 shrink-0"></i> Licences</a><?php endif; ?>
        <?php if(isSuperAdmin() || can('facturation')): ?><a href="<?= BASE_URL ?>/dashboard/facturation/index.php" class="<?= navActive('/facturation') ?>"><i data-lucide="receipt" class="w-5 h-5 shrink-0"></i> Facturation</a><?php endif; ?>
      </div>
    </div>

    <!-- 4. PILOTAGE & CONFIGURATION -->
    <div class="sidebar-group" data-group="pilotage">
      <button type="button" class="sidebar-group-head" onclick="toggleGroup('pilotage')">
        <span class="flex items-center gap-2"><i data-lucide="sliders-horizontal" class="w-4 h-4"></i> Pilotage & Config</span>
        <i data-lucide="chevron-down" class="w-4 h-4 transition-transform sidebar-chev" data-chev="pilotage"></i>
      </button>
      <div class="sidebar-group-body" data-body="pilotage">
        <?php if(can('modeles')): ?><a href="<?= BASE_URL ?>/dashboard/modeles/index.php" class="<?= navActive('/modeles') ?>"><i data-lucide="file-text" class="w-5 h-5 shrink-0"></i> Modèles</a><?php endif; ?>
        <?php if(can('parametrage')): ?><a href="<?= BASE_URL ?>/dashboard/parametrage/index.php" class="<?= navActive('/parametrage') ?>"><i data-lucide="settings" class="w-5 h-5 shrink-0"></i> Paramétrage</a><?php endif; ?>
        <?php if(can('securite')): ?><a href="<?= BASE_URL ?>/dashboard/securite/index.php" class="<?= navActive('/securite') ?>"><i data-lucide="shield-check" class="w-5 h-5 shrink-0"></i> Sécurité</a><?php endif; ?>
        <?php if(can('statistiques')): ?><a href="<?= BASE_URL ?>/dashboard/statistiques/index.php" class="<?= navActive('/statistiques') ?>"><i data-lucide="bar-chart-3" class="w-5 h-5 shrink-0"></i> Statistiques</a><?php endif; ?>
        <?php if(can('journal')): ?><a href="<?= BASE_URL ?>/dashboard/journal/index.php" class="<?= navActive('/journal') ?>"><i data-lucide="scroll-text" class="w-5 h-5 shrink-0"></i> Journal</a><?php endif; ?>
        <a href="<?= BASE_URL ?>/dashboard/ma-licence.php" class="<?= navActive('/ma-licence') ?>"><i data-lucide="badge-check" class="w-5 h-5 shrink-0"></i> Ma licence</a>
      </div>
    </div>
    </nav>
    <div class="topnav-end">
      <a href="<?= BASE_URL ?>/logout.php"><i data-lucide="log-out" class="w-5 h-5"></i> Déconnexion</a>
    </div>
  </div>
  <script>
  function toggleGroup(id){
    const g=document.querySelector('.sidebar-group[data-group="'+id+'"]');
    if(!g) return;
    const open=!g.classList.contains('open');
    document.querySelectorAll('.sidebar-group.open').forEach(x=>x.classList.remove('open'));
    if(open) g.classList.add('open');
  }
  (function(){
    document.querySelectorAll('.sidebar-group').forEach(g=>{ if(!g.querySelector('.sidebar-group-body a')) g.remove(); });
    document.querySelectorAll('#sidebarNav a.active').forEach(a=>{
      const g=a.closest('.sidebar-group');
      if(g) g.classList.add('has-active');
    });
    document.addEventListener('click',function(ev){
      if(!ev.target.closest('.sidebar-group')) document.querySelectorAll('.sidebar-group.open').forEach(x=>x.classList.remove('open'));
    });
    document.addEventListener('keydown',function(ev){
      if(ev.key==='Escape') document.querySelectorAll('.sidebar-group.open').forEach(x=>x.classList.remove('open'));
    });
    if(window.lucide) lucide.createIcons();
  })();
  </script>
</aside>
