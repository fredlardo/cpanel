<?php
$pageTitle='Licences';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/licence.php';
$pdo=db(); licenceEnsureTables($pdo);
if(currentUser()['role']!=='SUPERADMIN'){ echo '<div class="card p-10 text-center">Réservé SUPERADMIN</div>'; require_once __DIR__ . '/../../includes/footer.php'; exit; }
$rows=$pdo->query("SELECT e.id as entityId,e.code,e.name, l.licence_key,l.plan,l.status,l.seats,l.max_dossiers,l.expires_at,l.grace_until FROM entities e LEFT JOIN entity_licences l ON l.entityId=e.id ORDER BY e.name")->fetchAll();
?>
<div class="space-y-6">
  <div class="flex items-center justify-between"><div><h1 class="text-2xl font-bold">Licences par entité</h1><p class="text-sm text-slate-500">Vue globale — 1 licence / entité</p></div><a href="<?= BASE_URL ?>/dashboard/entites/index.php" class="border px-3 py-1.5 rounded-xl text-sm bg-white">← Entités</a></div>
  <div class="card overflow-hidden">
    <div class="overflow-x-auto"><table class="w-full text-sm">
      <thead class="bg-zinc-50 text-xs text-slate-500"><tr><th class="text-left px-3 py-2">Entité</th><th class="text-left px-3 py-2">Plan</th><th class="text-left px-3 py-2">Statut</th><th class="text-left px-3 py-2">Clé</th><th class="text-left px-3 py-2">Expire</th><th class="text-left px-3 py-2">Sièges</th><th class="px-3 py-2"></th></tr></thead>
      <tbody class="divide-y">
        <?php foreach($rows as $r): $st=$r['status']?licenceStatus($pdo,$r['entityId']):['status'=>'NONE']; $col=$st['status']==='ACTIVE'?'bg-emerald-100 text-emerald-700':($st['status']==='GRACE'?'bg-amber-100 text-amber-700':'bg-red-100 text-red-700'); ?>
        <tr class="hover:bg-zinc-50">
          <td class="px-3 py-2"><span class="font-medium"><?= e($r['name']) ?></span> <span class="text-xs font-mono text-slate-500">(<?= e($r['code']) ?>)</span></td>
          <td class="px-3 py-2"><?= e($r['plan']??'—') ?></td>
          <td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full text-xs border <?= $col ?>"><?= e($st['status']) ?></span></td>
          <td class="px-3 py-2 font-mono text-xs"><?= e($r['licence_key']?substr($r['licence_key'],0,18).'…':'—') ?></td>
          <td class="px-3 py-2 text-xs"><?= $r['expires_at']?date('d/m/Y', strtotime($r['expires_at'])):'—' ?></td>
          <td class="px-3 py-2 text-xs"><?= $r['seats']??'—' ?></td>
          <td class="px-3 py-2 text-right"><a href="<?= BASE_URL ?>/dashboard/entites/licence.php?id=<?= e($r['entityId']) ?>" class="text-blue-600 hover:underline text-xs">Gérer</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
