<?php
$pageTitle='Corbeille';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';
$pdo=db();
if(isset($_GET['restore'])){ $pdo->prepare("UPDATE dossiers SET deletedAt=NULL WHERE id=?")->execute([$_GET['restore']]); $pdo->prepare("INSERT INTO action_logs (id,action,description,dossierId,userId) VALUES (?,?,?,?,?)")->execute([cuid(),'RESTAURATION_CORBEILLE','Restauration corbeille',$_GET['restore'],currentUser()['id']]); header('Location: index.php'); exit; }
if(isset($_GET['delete'])){ $did=$_GET['delete']; $pdo->prepare("DELETE FROM documents WHERE dossierId=?")->execute([$did]); $pdo->prepare("DELETE FROM dossiers WHERE id=?")->execute([$did]); header('Location: index.php'); exit; }
$rows=$pdo->query("SELECT d.*, u.firstName,u.lastName FROM dossiers d LEFT JOIN users u ON u.id=d.creatorId WHERE d.deletedAt IS NOT NULL ORDER BY d.deletedAt DESC")->fetchAll();
?>
<div class="flex items-center justify-between mb-4">
  <div><h1 class="text-xl font-extrabold text-slate-900 flex items-center gap-2"><i data-lucide="trash-2" class="w-5 h-5"></i> Corbeille</h1><p class="text-sm text-slate-500"><?= count($rows) ?> élément(s) supprimé(s) — purge définitive après 30 jours</p></div>
</div>
<div class="card overflow-hidden">
  <table class="w-full text-sm">
    <thead><tr class="bg-slate-50 border-b text-left text-slate-500"><th class="px-4 py-2">Réf / Titre</th><th class="px-3 py-2">Supprimé le</th><th class="px-3 py-2 text-right">Actions</th></tr></thead>
    <tbody class="divide-y">
      <?php foreach($rows as $r): ?>
        <tr class="hover:bg-slate-50"><td class="px-4 py-2"><div class="font-medium"><?= e($r['reference']) ?></div><div class="text-xs text-slate-500"><?= e($r['title']) ?></div></td>
        <td class="px-3 py-2 text-xs"><?= date('d/m/Y H:i',strtotime($r['deletedAt'])) ?> • <?= e($r['firstName'].' '.$r['lastName']) ?></td>
        <td class="px-3 py-2 text-right flex justify-end gap-1"><a href="index.php?restore=<?= e($r['id']) ?>" class="border px-2 py-1 rounded-xl text-xs hover:bg-emerald-50">Restaurer</a><a href="index.php?delete=<?= e($r['id']) ?>" onclick="return confirm('Suppression définitive ?')" class="border px-2 py-1 rounded-xl text-xs text-red-600">Supprimer définitivement</a></td></tr>
      <?php endforeach; ?>
      <?php if(empty($rows)): ?><tr><td colspan="3" class="px-4 py-10 text-center text-slate-400">Corbeille vide</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
