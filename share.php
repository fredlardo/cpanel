<?php
require_once __DIR__.'/config/app.php';
require_once __DIR__.'/config/database.php';
$pdo=db();
$token=$_GET['token']??''; if(!$token) die('Token manquant');
$stmt=$pdo->prepare("SELECT s.*, d.title, d.reference, doc.originalName, doc.filePath FROM share_links s LEFT JOIN dossiers d ON d.id=s.dossierId LEFT JOIN documents doc ON doc.id=s.documentId WHERE s.token=?");
$stmt->execute([$token]); $link=$stmt->fetch(); if(!$link) die('Lien invalide');
if($link['expiresAt'] && strtotime($link['expiresAt']) < time()) die('Lien expiré');
if($link['maxViews'] && (int)$link['views'] >= (int)$link['maxViews']) die('Nombre de vues dépassé');
if($_SERVER['REQUEST_METHOD']==='POST' && $link['password']){
  if(!password_verify($_POST['password']??'', $link['password'])) { $err='Mot de passe incorrect'; }
  else { $ok=true; }
} elseif($link['password'] && empty($ok)) {
  // show password form
  echo '<!doctype html><meta charset="UTF-8"><script src="https://cdn.tailwindcss.com"></script><div class="min-h-screen flex items-center justify-center bg-slate-50"><form method="POST" class="card p-8 w-full max-w-sm"><h1 class="font-bold mb-4">Lien protégé</h1><input type="password" name="password" placeholder="Mot de passe" class="input w-full mb-3" required><button class="bg-blue-600 text-white px-4 py-2 rounded-xl w-full">Accéder</button>'.(isset($err)?'<p class="text-red-600 text-sm mt-2">'.htmlspecialchars($err).'</p>':'').'</form></div>'; exit;
}
$pdo->prepare("UPDATE share_links SET views=views+1 WHERE id=?")->execute([$link['id']]);
?>
<!doctype html><meta charset="UTF-8"><script src="https://cdn.tailwindcss.com"></script>
<div class="min-h-screen bg-slate-50 p-8">
  <div class="max-w-2xl mx-auto card p-6">
    <h1 class="font-bold text-lg"><?= htmlspecialchars($link['title']??$link['originalName']??'Partage Doxera+') ?></h1>
    <p class="text-sm text-slate-500"><?= htmlspecialchars($link['reference']??'') ?></p>
    <?php if($link['documentId']): ?>
      <div class="mt-4 border rounded-xl p-4 flex items-center justify-between">
        <div><?= htmlspecialchars($link['originalName']) ?></div>
        <?php if((int)$link['allowDownload']): ?><a href="<?= BASE_URL ?>/<?= htmlspecialchars($link['filePath']) ?>" class="bg-blue-600 text-white px-4 py-2 rounded-xl text-sm">Télécharger</a><?php else: ?><span class="text-xs text-slate-400">Téléchargement désactivé — prévisualisation seule</span><?php endif; ?>
      </div>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/dashboard/dossiers/view.php?id=<?= htmlspecialchars($link['dossierId']) ?>" class="inline-block mt-4 text-blue-600 text-sm">Voir le dossier (si connecté)</a>
    <?php endif; ?>
    <p class="text-xs text-slate-400 mt-4">Lien sécurisé — expire le <?= $link['expiresAt']?date('d/m/Y H:i',strtotime($link['expiresAt'])):'jamais' ?> — vues: <?= (int)$link['views']+1 ?></p>
  </div>
</div>
