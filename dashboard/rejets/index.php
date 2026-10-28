<?php
$pageTitle='Dossiers rejetés';
require_once __DIR__.'/../../includes/header.php';
$pdo=db();
$u=currentUser();
$uid=$u['id'];
// rejets où je suis envoyeur (mes envois rejetés) ou admin voit tout
$isAdmin=($u['role']??'')==='ADMIN';
if($isAdmin){
  $sql="SELECT t.*, d.reference,d.title, s.name as senderSvc, ru.firstName as rfn, ru.lastName as rln, rs.name as rejectSvc, su.firstName as sfn, su.lastName as sln
        FROM transmissions t JOIN dossiers d ON d.id=t.dossierId LEFT JOIN users su ON su.id=t.senderId LEFT JOIN services s ON s.id=su.serviceId LEFT JOIN users ru ON ru.id=t.receiverId LEFT JOIN services rs ON rs.id=ru.serviceId
        WHERE t.status='REJECTED' ORDER BY t.respondedAt DESC LIMIT 300";
  $rows=$pdo->query($sql)->fetchAll();
} else {
  $sql="SELECT t.*, d.reference,d.title, ru.firstName as rfn, ru.lastName as rln, rs.name as rejectSvc FROM transmissions t JOIN dossiers d ON d.id=t.dossierId LEFT JOIN users ru ON ru.id=t.receiverId LEFT JOIN services rs ON rs.id=ru.serviceId WHERE t.status='REJECTED' AND t.senderId=? ORDER BY t.respondedAt DESC LIMIT 300";
  $st=$pdo->prepare($sql); $st->execute([$uid]); $rows=$st->fetchAll();
}
?>
<div class="flex items-center justify-between mb-4">
  <h1 class="text-xl font-semibold flex items-center gap-2"><i data-lucide="x-octagon" class="w-5 h-5 text-red-600"></i> Dossiers rejetés <span class="text-xs bg-red-600 text-white px-2 py-1 rounded-full"><?= count($rows) ?></span></h1>
  <span class="text-xs text-slate-400">Service · personne qui a rejeté · motif</span>
</div>
<div class="card p-0 overflow-hidden">
  <div class="overflow-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-xs text-slate-400"><tr><th class="text-left px-3 py-2">Dossier</th><th class="text-left px-3 py-2">Titre</th><th class="text-left px-3 py-2">Envoyé par vous → rejeté par</th><th class="text-left px-3 py-2">Service</th><th class="text-left px-3 py-2">Motif du rejet</th><th class="text-left px-3 py-2">Date rejet</th><th class="px-3 py-2"></th></tr></thead>
      <tbody>
        <?php if(empty($rows)): ?><tr><td colspan="7" class="px-3 py-10 text-center text-slate-400">Aucun dossier rejeté</td></tr><?php endif; ?>
        <?php foreach($rows as $r): ?>
        <tr class="border-t hover:bg-slate-50">
          <td class="px-3 py-2 font-mono text-xs"><?= e($r['reference']) ?></td>
          <td class="px-3 py-2"><a href="<?= BASE_URL ?>/dashboard/dossiers/view.php?id=<?= e($r['dossierId']) ?>" class="text-blue-600 hover:underline"><?= e(mb_strimwidth($r['title'],0,40,'…')) ?></a></td>
          <td class="px-3 py-2 text-xs"><?= e(trim(($r['rfn']??'').' '.($r['rln']??''))) ?></td>
          <td class="px-3 py-2 text-xs"><?= e($r['rejectSvc']??'') ?></td>
          <td class="px-3 py-2 text-xs max-w-[260px]"><?= e($r['rejectReason']??'') ?></td>
          <td class="px-3 py-2 text-xs"><?= $r['respondedAt']?date('d/m/Y H:i',strtotime($r['respondedAt'])):'—' ?></td>
          <td class="px-3 py-2"><a href="<?= BASE_URL ?>/dashboard/dossiers/view.php?id=<?= e($r['dossierId']) ?>" class="text-xs border px-2 py-1 rounded-lg bg-white">Ouvrir</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>
