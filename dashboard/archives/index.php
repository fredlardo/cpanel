<?php
$pageTitle='Archives';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';
if(!hasModule('archivage')) requireModule('archivage');
$pdo=db();
if(isset($_GET['restore'])){ $pdo->prepare("UPDATE dossiers SET archivedAt=NULL, status='EN_COURS' WHERE id=?")->execute([$_GET['restore']]); $pdo->prepare("INSERT INTO action_logs (id,action,description,dossierId,userId) VALUES (?,?,?,?,?)")->execute([cuid(),'RESTAURATION_ARCHIVE','Restauration archive',$_GET['restore'], currentUser()['id']]); header('Location: index.php'); exit; }
if(isset($_GET['purge'])){ $pdo->prepare("UPDATE dossiers SET deletedAt=NOW() WHERE id=?")->execute([$_GET['purge']]); header('Location: index.php'); exit; }
$rows=$pdo->query("SELECT d.*, u.firstName,u.lastName, rp.name as retentionName FROM dossiers d LEFT JOIN users u ON u.id=d.creatorId LEFT JOIN retention_policies rp ON rp.id=d.retentionPolicyId WHERE d.archivedAt IS NOT NULL AND d.deletedAt IS NULL ORDER BY d.archivedAt DESC")->fetchAll();
?>
<div class="flex items-center justify-between mb-4">
  <div><h1 class="text-xl font-extrabold text-slate-900 flex items-center gap-2"><i data-lucide="archive" class="w-5 h-5"></i> Archives</h1><p class="text-sm text-slate-500"><?= count($rows) ?> dossier(s) archivé(s) • rétention & gel juridique gérés depuis chaque fiche</p></div>
</div>
<div class="card overflow-hidden">
  <table class="w-full text-sm">
    <thead><tr class="bg-slate-50 border-b text-left text-slate-500"><th class="px-4 py-2">Réf / Titre</th><th class="px-3 py-2">Archivé le</th><th class="px-3 py-2">Rétention</th><th class="px-3 py-2">Gel</th><th class="px-3 py-2 text-right">Actions</th></tr></thead>
    <tbody class="divide-y">
      <?php foreach($rows as $r): ?>
        <tr class="hover:bg-slate-50"><td class="px-4 py-2"><div class="font-medium"><?= e($r['reference']) ?></div><div class="text-xs text-slate-500"><?= e($r['title']) ?> • <?= e($r['firstName'].' '.$r['lastName']) ?></div></td>
        <td class="px-3 py-2 text-xs"><?= date('d/m/Y H:i',strtotime($r['archivedAt'])) ?></td>
        <td class="px-3 py-2 text-xs"><?= e($r['retentionName']??'—') ?> <?= $r['retentionUntil']?'<div class="text-slate-400">jusqu\'au '.date('d/m/Y',strtotime($r['retentionUntil'])).'</div>':'' ?></td>
        <td class="px-3 py-2"><?= $r['legalHold']?'<span class="bg-red-50 text-red-600 px-2 py-0.5 rounded-full text-xs">Gel</span>':'<span class="text-slate-400 text-xs">—</span>' ?></td>
        <td class="px-3 py-2 text-right flex justify-end gap-1">
          <a href="index.php?restore=<?= e($r['id']) ?>" onclick="return confirm('Restaurer ?')" class="border px-2 py-1 rounded-xl text-xs hover:bg-emerald-50">Restaurer</a>
          <a href="<?= BASE_URL ?>/dashboard/dossiers/view.php?id=<?= e($r['id']) ?>" class="border px-2 py-1 rounded-xl text-xs">Voir</a>
          <?php if(!$r['legalHold']): ?><a href="index.php?purge=<?= e($r['id']) ?>" onclick="return confirm('Mettre en corbeille ?')" class="border px-2 py-1 rounded-xl text-xs text-red-600">Corbeille</a><?php endif; ?>
        </td></tr>
      <?php endforeach; ?>
      <?php if(empty($rows)): ?><tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Aucune archive</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<p class="text-xs text-slate-400 mt-3">Cycle : Clos → Archivé (PDF/A) → Rétention (5/10 ans) → Destruction/Transfert. Le gel juridique bloque toute purge.</p>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
