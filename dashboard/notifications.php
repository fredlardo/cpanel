<?php
$pageTitle='Notifications';
require_once __DIR__ . '/../includes/header.php';
$pdo=db();
$user=currentUser();
if(isset($_GET['read'])){
  $pdo->prepare("UPDATE notifications SET isRead=1 WHERE id=? AND userId=?")->execute([$_GET['read'],$user['id']]);
  redirect(BASE_URL.'/dashboard/notifications.php');
}
if(isset($_GET['all_read'])){
  $pdo->prepare("UPDATE notifications SET isRead=1 WHERE userId=? AND isRead=0")->execute([$user['id']]);
  redirect(BASE_URL.'/dashboard/notifications.php');
}
if(isset($_GET['clear'])){
  $pdo->prepare("DELETE FROM notifications WHERE userId=?")->execute([$user['id']]);
  redirect(BASE_URL.'/dashboard/notifications.php');
}
$stmt=$pdo->prepare("SELECT n.*, d.reference, d.title as dTitle FROM notifications n LEFT JOIN dossiers d ON d.id=n.dossierId WHERE n.userId=? ORDER BY n.createdAt DESC LIMIT 100");
$stmt->execute([$user['id']]);
$rows=$stmt->fetchAll();
$unread=$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE userId=? AND isRead=0"); $unread->execute([$user['id']]); $unreadCount=(int)$unread->fetchColumn();
?>
<div class="flex items-center justify-between mb-6">
  <h1 class="text-xl font-extrabold">Notifications <span class="text-sm font-normal text-slate-400"><?= $unreadCount ?> non lue(s) / <?= count($rows) ?> total</span></h1>
  <div class="flex gap-2">
    <?php if($unreadCount>0): ?><a href="?all_read=1" class="border bg-white px-4 py-2 rounded-xl text-sm hover:bg-slate-50">Tout marquer lu</a><?php endif; ?>
    <a href="?clear=1" onclick="return confirm('Supprimer toutes les notifications ?')" class="border bg-white px-4 py-2 rounded-xl text-sm text-red-600 hover:bg-red-50">Effacer tout</a>
  </div>
</div>
<?php if(empty($rows)): ?>
  <div class="card p-10 text-center text-slate-400 text-sm">Aucune notification — les transferts/envois de dossiers apparaîtront ici.</div>
<?php else: ?>
  <div class="space-y-3">
  <?php foreach($rows as $n): ?>
    <div class="card p-4 flex items-start justify-between gap-4 <?= $n['isRead']?'bg-white':'bg-blue-50/60 border-blue-200' ?>">
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2">
          <span class="h-8 w-8 rounded-full <?= $n['isRead']?'bg-slate-100':'bg-blue-600' ?> flex items-center justify-center text-white shrink-0"><i data-lucide="bell" class="w-4 h-4"></i></span>
          <div>
            <p class="font-semibold text-sm"><?= e($n['title']) ?></p>
            <p class="text-xs text-slate-500"><?= date('d/m/Y H:i',strtotime($n['createdAt'])) ?> • <?= e($n['type']) ?> <?= $n['reference']?'• '.e($n['reference']):'' ?></p>
          </div>
          <?php if(!$n['isRead']): ?><span class="bg-blue-600 text-white text-[10px] px-2 py-0.5 rounded-full">Nouveau</span><?php endif; ?>
        </div>
        <p class="text-sm text-slate-700 mt-2"><?= nl2br(e($n['message'])) ?></p>
        <?php if($n['dossierId']): ?><a href="<?= BASE_URL ?>/dashboard/dossiers/view.php?id=<?= e($n['dossierId']) ?>" class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline mt-2">Ouvrir dossier <?= e($n['dTitle']??'') ?> <i data-lucide="external-link" class="w-3 h-3"></i></a><?php endif; ?>
      </div>
      <div class="flex flex-col gap-1 shrink-0">
        <?php if(!$n['isRead']): ?><a href="?read=<?= e($n['id']) ?>" class="border bg-white px-3 py-1.5 rounded-xl text-xs hover:bg-slate-50">Marquer lu</a><?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
