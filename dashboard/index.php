<?php
$pageTitle = 'Tableau de bord';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db();
$user = currentUser();
$isAdmin = $user['role'] === 'ADMIN';
$ids = $isAdmin ? null : $user['accessServiceIds'];

$today = date('Y-m-d');
$nextWeek = date('Y-m-d', strtotime('+7 days'));

$baseWhere = "d.deletedAt IS NULL AND d.archivedAt IS NULL AND d.creatorId = ?";
$params = [$user['id']];
function countWhere(PDO $pdo, string $baseWhere, array $params, string $extra): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM dossiers d WHERE $baseWhere $extra");
    $stmt->execute($params);
    return (int)$stmt->fetch()['c'];
}
$total = countWhere($pdo,$baseWhere,$params,"");
$nouveaux = countWhere($pdo,$baseWhere,$params," AND status='NOUVEAU'");
$enCours = countWhere($pdo,$baseWhere,$params," AND status='EN_COURS'");
$enAttente = countWhere($pdo,$baseWhere,$params," AND status='EN_ATTENTE'");
$clos = countWhere($pdo,$baseWhere,$params," AND status='CLOS'");
$echeances7j = countWhere($pdo,$baseWhere,$params," AND deadline <= '$nextWeek' AND deadline >= '$today' AND status NOT IN ('CLOS','ANNULE','REJETE')");
$enRetard = countWhere($pdo,$baseWhere,$params," AND deadline < '$today' AND status NOT IN ('CLOS','ANNULE','REJETE')");
$rejetes = countWhere($pdo,$baseWhere,$params," AND status='REJETE'");
$totalUsers = (int)$pdo->query("SELECT COUNT(*) as c FROM users")->fetch()['c'];
// Entités : visible seulement si SUPERADMIN ou module entites (pour afficher le module sur le dashboard lui-même)
$totalEntities = 0;
try {
    if (isSuperAdmin() || in_array('entites', $user['modules'] ?? [], true)) {
        $totalEntities = (int)$pdo->query("SELECT COUNT(*) as c FROM entities WHERE isActive=1")->fetchColumn();
    }
} catch(Exception $e) { $totalEntities = 0; }

// dossiers récents (comme capture : 6 colonnes, tri récent)
$stmt = $pdo->prepare("SELECT d.*, u.firstName, u.lastName, s.name as serviceName, p.name as priorityName, p.color as priorityColor
    FROM dossiers d
    LEFT JOIN users u ON u.id=d.creatorId
    LEFT JOIN services s ON s.id=d.serviceId
    LEFT JOIN priorities p ON p.id=d.priorityId
    WHERE $baseWhere ORDER BY d.createdAt DESC LIMIT 10");
$stmt->execute($params);
$recent = $stmt->fetchAll();

function priorityPill(string $name, ?string $color): string {
    $name = $name ?: '—';
    $c = strtolower($name);
    // mapping couleurs comme capture : Urgente/Haute rouge/jaune, Critique rouge
    if (str_contains($c,'urgente')) return '<span class="pill bg-red-50 text-red-600 border border-red-100">Urgente</span>';
    if (str_contains($c,'critique')) return '<span class="pill bg-rose-50 text-rose-600 border border-rose-100">Critique</span>';
    if (str_contains($c,'haute')) return '<span class="pill bg-amber-50 text-amber-600 border border-amber-100">Haute</span>';
    if (str_contains($c,'moyenne')) return '<span class="pill bg-blue-50 text-blue-600 border border-blue-100">Moyenne</span>';
    if (str_contains($c,'faible')||str_contains($c,'basse')) return '<span class="pill bg-slate-50 text-slate-600 border border-slate-200">Faible</span>';
    $col = $color ?: '#64748b';
    return '<span class="pill" style="background:'.e($col).'14;color:'.e($col).';border:1px solid '.e($col).'20">'.e($name).'</span>';
}
?>
<div class="space-y-6">
  <!-- titre + recherche comme capture -->
  <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
    <div>
      <h1 class="text-[22px] font-extrabold text-slate-900 tracking-tight">Tableau de bord</h1>
      <p class="text-[13px] text-slate-400 mt-1">Bienvenue, <?= e($user['name']) ?></p>
    </div>
    <div class="relative w-full lg:w-[320px]">
      <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
      <input id="globalSearch" placeholder="Rechercher un dossier..." class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-slate-200 bg-white text-sm placeholder:text-slate-400 focus:outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-50">
      <div id="searchResults" class="hidden absolute mt-2 w-full bg-white border border-slate-200 rounded-xl shadow-xl overflow-hidden z-20"></div>
    </div>
  </div>

  <!-- grille stats : 3 + 3 + 2 comme capture -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <!-- ligne 1 -->
    <div class="bg-white border border-slate-200 rounded-2xl px-5 py-4 flex items-center gap-4">
      <div class="h-11 w-11 rounded-xl bg-blue-50 flex items-center justify-center shrink-0"><i data-lucide="file-text" class="w-5 h-5 text-blue-600"></i></div>
      <div><div class="text-[22px] font-extrabold leading-none text-slate-900"><?= $nouveaux ?></div><div class="text-[13px] text-slate-400 mt-1">Nouveaux dossiers</div></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-2xl px-5 py-4 flex items-center gap-4">
      <div class="h-11 w-11 rounded-xl bg-amber-50 flex items-center justify-center shrink-0"><i data-lucide="clock" class="w-5 h-5 text-amber-600"></i></div>
      <div><div class="text-[22px] font-extrabold leading-none text-slate-900"><?= $enCours ?></div><div class="text-[13px] text-slate-400 mt-1">En cours</div></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-2xl px-5 py-4 flex items-center gap-4">
      <div class="h-11 w-11 rounded-xl bg-orange-50 flex items-center justify-center shrink-0"><i data-lucide="alert-triangle" class="w-5 h-5 text-orange-500"></i></div>
      <div><div class="text-[22px] font-extrabold leading-none text-slate-900"><?= $enAttente ?></div><div class="text-[13px] text-slate-400 mt-1">En attente</div></div>
    </div>
    <!-- ligne 2 -->
    <div class="bg-white border border-slate-200 rounded-2xl px-5 py-4 flex items-center gap-4">
      <div class="h-11 w-11 rounded-xl bg-emerald-50 flex items-center justify-center shrink-0"><i data-lucide="check-circle" class="w-5 h-5 text-emerald-600"></i></div>
      <div><div class="text-[22px] font-extrabold leading-none text-slate-900"><?= $clos ?></div><div class="text-[13px] text-slate-400 mt-1">Clos</div></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-2xl px-5 py-4 flex items-center gap-4">
      <div class="h-11 w-11 rounded-xl bg-indigo-50 flex items-center justify-center shrink-0"><i data-lucide="folder-open" class="w-5 h-5 text-indigo-600"></i></div>
      <div><div class="text-[22px] font-extrabold leading-none text-slate-900"><?= $total ?></div><div class="text-[13px] text-slate-400 mt-1">Total dossiers</div></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-2xl px-5 py-4 flex items-center gap-4">
      <div class="h-11 w-11 rounded-xl bg-rose-50 flex items-center justify-center shrink-0"><i data-lucide="calendar" class="w-5 h-5 text-rose-600"></i></div>
      <div><div class="text-[22px] font-extrabold leading-none text-slate-900"><?= $echeances7j ?></div><div class="text-[13px] text-slate-400 mt-1">Échéances (7j)</div></div>
    </div>
    <!-- ligne 3 -->
    <div class="bg-white border border-slate-200 rounded-2xl px-5 py-4 flex items-center gap-4">
      <div class="h-11 w-11 rounded-xl bg-red-50 flex items-center justify-center shrink-0"><i data-lucide="alert-triangle" class="w-5 h-5 text-red-600"></i></div>
      <div><div class="text-[22px] font-extrabold leading-none text-slate-900"><?= $enRetard ?></div><div class="text-[13px] text-slate-400 mt-1">En retard</div></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-2xl px-5 py-4 flex items-center gap-4">
      <div class="h-11 w-11 rounded-xl bg-violet-50 flex items-center justify-center shrink-0"><i data-lucide="users" class="w-5 h-5 text-violet-600"></i></div>
      <div><div class="text-[22px] font-extrabold leading-none text-slate-900"><?= $totalUsers ?></div><div class="text-[13px] text-slate-400 mt-1">Utilisateurs</div></div>
    </div>
    <a href="<?= BASE_URL ?>/dashboard/rejets/index.php" class="bg-white border border-slate-200 rounded-2xl px-5 py-4 flex items-center gap-4 hover:border-red-200 hover:bg-red-50/50 transition">
      <div class="h-11 w-11 rounded-xl bg-red-50 border border-red-100 flex items-center justify-center shrink-0"><i data-lucide="x-octagon" class="w-5 h-5 text-red-600"></i></div>
      <div><div class="text-[22px] font-extrabold leading-none text-slate-900"><?= $rejetes ?></div><div class="text-[13px] text-slate-500 mt-1">Dossiers rejetés</div></div>
    </a>
    <?php if(isSuperAdmin() || in_array('entites', $user['modules'] ?? [], true)): ?>
    <a href="<?= BASE_URL ?>/dashboard/entites/index.php" class="bg-white border border-slate-200 rounded-2xl px-5 py-4 flex items-center gap-4 hover:border-blue-200 hover:bg-blue-50/40 transition">
      <div class="h-11 w-11 rounded-xl bg-sky-50 border border-sky-100 flex items-center justify-center shrink-0"><i data-lucide="network" class="w-5 h-5 text-sky-600"></i></div>
      <div><div class="text-[22px] font-extrabold leading-none text-slate-900"><?= $totalEntities ?></div><div class="text-[13px] text-slate-500 mt-1">Entités actives</div></div>
    </a>
    <?php endif; ?>
  </div>

  <!-- Dossiers récents : table exacte capture -->
  <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
    <div class="px-5 py-4">
      <h2 class="font-bold text-[15px] text-slate-900">Dossiers récents</h2>
    </div>
    <div class="border-t border-slate-200 overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50/60 text-[12px] text-slate-400 font-medium border-b border-slate-200">
            <th class="text-left font-medium px-5 py-3 whitespace-nowrap">Référence</th>
            <th class="text-left font-medium px-5 py-3">Titre</th>
            <th class="text-left font-medium px-5 py-3 whitespace-nowrap">Créateur</th>
            <th class="text-left font-medium px-5 py-3 whitespace-nowrap">Service</th>
            <th class="text-left font-medium px-5 py-3">Priorité</th>
            <th class="text-left font-medium px-5 py-3 whitespace-nowrap">Date</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach($recent as $d): ?>
          <tr class="hover:bg-slate-50/60">
            <td class="px-5 py-3.5 whitespace-nowrap"><a href="<?= BASE_URL ?>/dashboard/dossiers/view.php?id=<?= e($d['id']) ?>" class="font-semibold text-blue-600 hover:text-blue-700"><?= e($d['reference']) ?></a></td>
            <td class="px-5 py-3.5 font-medium text-slate-900 max-w-[260px] truncate"><?= e($d['title']) ?></td>
            <td class="px-5 py-3.5 text-slate-700 whitespace-nowrap"><?= e(trim(($d['firstName']??'').' '.($d['lastName']??'')) ?: '—') ?></td>
            <td class="px-5 py-3.5 text-slate-700 whitespace-nowrap"><?= e($d['serviceName'] ?? '—') ?></td>
            <td class="px-5 py-3.5 whitespace-nowrap"><?= priorityPill($d['priorityName'] ?? '', $d['priorityColor'] ?? null) ?></td>
            <td class="px-5 py-3.5 text-slate-400 whitespace-nowrap"><?= $d['createdAt'] ? date('d/m/Y', strtotime($d['createdAt'])) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($recent)): ?>
          <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">Aucun dossier pour le moment — <a href="<?= BASE_URL ?>/dashboard/dossiers/nouveau.php" class="text-blue-600 hover:underline">Créer un dossier</a></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>const BASE_URL="<?= BASE_URL ?>";</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
