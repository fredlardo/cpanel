<?php
$pageTitle='Ma licence';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/licence.php';
$pdo=db(); licenceEnsureTables($pdo);
$me=currentUser();
$entId=getUserEntityId($pdo,$me);
if(!$entId){ echo '<div class="card p-10 text-center text-slate-500">Aucune entité liée à votre compte (serviceId manquant).</div>'; require_once __DIR__ . '/../includes/footer.php'; exit; }
$ent=$pdo->prepare("SELECT * FROM entities WHERE id=?"); $ent->execute([$entId]); $e=$ent->fetch();
$lic=getEntityLicence($pdo,$entId); $st=licenceStatus($pdo,$entId);
$usage=['users'=>0,'dossiers'=>0]; try{
  $q=$pdo->prepare("SELECT COUNT(*) FROM users u JOIN services s ON s.id=u.serviceId JOIN divisions d ON d.id=s.divisionId JOIN organizations o ON o.id=d.organizationId WHERE o.entityId=? AND u.isActive=1"); $q->execute([$entId]); $usage['users']=(int)$q->fetchColumn();
  $q=$pdo->prepare("SELECT COUNT(*) FROM dossiers d JOIN services s ON s.id=d.serviceId JOIN divisions dv ON dv.id=s.divisionId JOIN organizations o ON o.id=dv.organizationId WHERE o.entityId=? AND d.deletedAt IS NULL"); $q->execute([$entId]); $usage['dossiers']=(int)$q->fetchColumn();
}catch(Exception $ex){}
$col=$st['status']==='ACTIVE'?'bg-emerald-100 text-emerald-700 border-emerald-200':($st['status']==='GRACE'?'bg-amber-100 text-amber-700 border-amber-200':'bg-red-100 text-red-700 border-red-200');
?>
<div class="space-y-6">
  <div><h1 class="text-2xl font-bold">Ma licence</h1><p class="text-sm text-slate-500"><?= e($e['name']??$entId) ?> <span class="font-mono text-xs">(<?= e($e['code']??'') ?>)</span> — lecture seule</p></div>
  <div class="card p-6 space-y-4">
    <div class="flex items-center gap-2"><span class="px-3 py-1 rounded-full text-xs border <?= $col ?>"><?= e($st['status']) ?></span><span class="text-xs text-slate-500"><?= e($st['msg']) ?></span></div>
    <?php if(!$lic): ?><p class="text-sm text-slate-400">Aucune licence — contactez le SUPERADMIN.</p><?php else: ?>
    <div class="grid md:grid-cols-2 gap-4 text-sm">
      <div><span class="text-slate-500">Plan</span><div class="font-medium"><?= e($lic['plan']) ?></div></div>
      <div><span class="text-slate-500">Statut</span><div class="font-medium"><?= e($lic['status']) ?></div></div>
      <div><span class="text-slate-500">Expire</span><div><?= date('d/m/Y H:i', strtotime($lic['expires_at'])) ?> <span class="text-xs text-slate-400">(grâce <?= $lic['grace_until']?date('d/m/Y',strtotime($lic['grace_until'])):'—' ?>)</span></div></div>
      <div><span class="text-slate-500">Clé (masquée)</span><div class="font-mono text-xs"><?= e(substr($lic['licence_key'],0,8)) ?>••••<?= e(substr($lic['licence_key'],-4)) ?></div></div>
      <div><span class="text-slate-500">Quotas</span><div><?= (int)$lic['seats'] ?> sièges · <?= (int)$lic['max_dossiers'] ?> dossiers · <?= (int)$lic['max_storage_mb'] ?> Mo</div></div>
      <div><span class="text-slate-500">Usage</span><div><?= $usage['users'] ?> / <?= (int)$lic['seats'] ?> users (<?= $lic['seats']?round($usage['users']/$lic['seats']*100):0 ?>%) · <?= $usage['dossiers'] ?> / <?= (int)$lic['max_dossiers'] ?> dossiers</div></div>
    </div>
    <p class="text-xs text-slate-400">Gestion (renouvellement, suspension) réservée au SUPERADMIN — <span class="font-mono">superadmin / SuperAdmin2026!</span></p>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
