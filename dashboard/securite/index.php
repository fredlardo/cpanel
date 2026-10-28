<?php
$pageTitle='Sécurité';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';
$pdo=db(); $me=currentUser();
$msg=''; $err='';
$isAdmin=$me['role']==='ADMIN';

// ADMIN force MFA par service
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['force_mfa_service']) && $isAdmin){
  $sid=$_POST['serviceId']??''; $on=isset($_POST['mfaRequired'])?1:0;
  if($sid){ $pdo->prepare("UPDATE services SET mfaRequired=? WHERE id=?")->execute([$on,$sid]); $msg=($on?'MFA forcé':'MFA non forcé')." pour le service."; }
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['force_mfa_global']) && $isAdmin){
  $on=isset($_POST['globalOn'])?1:0;
  $pdo->exec("UPDATE services SET mfaRequired=".(int)$on);
  $msg=$on?'MFA forcé pour tous les services':'MFA levé pour tous les services';
}

$logs=$pdo->prepare("SELECT l.*, u.firstName,u.lastName FROM action_logs l LEFT JOIN users u ON u.id=l.userId ORDER BY l.createdAt DESC LIMIT 20"); $logs->execute(); $rows=$logs->fetchAll();
$totalUsers=$pdo->query("SELECT COUNT(*) FROM users WHERE isActive=1")->fetchColumn();
$mfaCount=$pdo->query("SELECT COUNT(*) FROM users WHERE mfaEnabled=1")->fetchColumn();
$services=$pdo->query("SELECT s.id,s.name,s.mfaRequired,d.name as divName FROM services s LEFT JOIN divisions d ON d.id=s.divisionId ORDER BY s.name")->fetchAll();
?>
<div class="flex items-center justify-between mb-6">
  <div><h1 class="text-xl font-extrabold text-slate-900 flex items-center gap-2"><i data-lucide="shield-check" class="w-5 h-5 text-emerald-600"></i> Sécurité</h1><p class="text-sm text-slate-500">MFA TOTP, forçage par service (ADMIN), chiffrement et journalisation</p></div>
  <a href="<?= BASE_URL ?>/dashboard/profil.php?tab=securite" class="border bg-white px-4 py-2 rounded-xl text-sm hover:bg-slate-50">Configurer mon MFA →</a>
</div>
<?php if($msg): ?><div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-2 rounded-xl text-sm mb-4"><?= e($msg) ?></div><?php endif; ?>
<?php if($err): ?><div class="bg-red-50 border border-red-200 text-red-600 px-4 py-2 rounded-xl text-sm mb-4"><?= e($err) ?></div><?php endif; ?>

<div class="grid lg:grid-cols-3 gap-6">
  <div class="space-y-4">
    <div class="card p-5">
      <h3 class="font-semibold">Mon MFA (TOTP)</h3>
      <p class="text-xs text-slate-500 mt-1">Configuration dans <a href="<?= BASE_URL ?>/dashboard/profil.php?tab=securite" class="text-blue-600 underline">Profil → Sécurité</a> : générez le secret, scannez le QR (Google Authenticator), validez avec un code à 6 chiffres.</p>
      <div class="mt-3 text-xs bg-slate-50 border rounded-xl p-3">
        <div>Utilisateurs actifs: <?= (int)$totalUsers ?> — MFA activé: <?= (int)$mfaCount ?></div>
        <div class="text-slate-400 mt-1">Votre service <?= e($me['serviceName']??'') ?> : <?= $isAdmin?'ADMIN — peut forcer':'—' ?></div>
      </div>
      <a href="<?= BASE_URL ?>/dashboard/profil.php?tab=securite" class="mt-3 inline-flex items-center gap-2 bg-slate-900 text-white px-4 py-2 rounded-xl text-sm">Configurer mon TOTP</a>
    </div>
    <div class="card p-5">
      <h3 class="font-semibold">Chiffrement</h3>
      <p class="text-sm text-slate-600 mt-2">Au repos: AES-256 (volume uploads chiffré) • En transit: TLS 1.3 • Mots de passe: bcrypt</p>
      <p class="text-xs text-slate-400 mt-2">Clés via ENCRYPTION_KEY (non versionnée).</p>
    </div>
  </div>
  <div class="lg:col-span-2 space-y-4">
    <div class="card p-5">
      <h3 class="font-semibold flex items-center gap-2"><i data-lucide="shield" class="w-4 h-4"></i> Forcer MFA par service — ADMIN uniquement</h3>
      <p class="text-xs text-slate-500 mt-1">Si activé, tout utilisateur du service devra avoir configuré TOTP et saisir le code à chaque connexion. Sinon, MFA reste optionnel.</p>
      <?php if(!$isAdmin): ?><p class="text-xs text-amber-600 mt-2">Réservé aux ADMIN.</p><?php else: ?>
      <form method="POST" class="mt-3 flex gap-2 items-center text-sm">
        <input type="hidden" name="force_mfa_global" value="1">
        <label class="flex items-center gap-2"><input type="checkbox" name="globalOn" value="1"> Forcer pour tous</label>
        <button class="bg-slate-900 text-white px-3 py-1.5 rounded-xl text-sm">Appliquer global</button>
        <button name="force_mfa_global" value="1" onclick="this.form.globalOn.checked=false" class="border bg-white px-3 py-1.5 rounded-xl text-sm">Lever global</button>
      </form>
      <div class="mt-4 space-y-2 max-h-96 overflow-auto pr-1">
        <?php foreach($services as $s): ?>
        <form method="POST" class="flex items-center justify-between border rounded-xl px-3 py-2 bg-white">
          <div><div class="text-sm font-medium"><?= e($s['name']) ?> <span class="text-xs text-slate-400"><?= e($s['divName']) ?></span></div><div class="text-xs <?= (int)$s['mfaRequired']?'text-emerald-600':'text-slate-400' ?>"><?= (int)$s['mfaRequired']?'MFA forcé':'MFA optionnel' ?></div></div>
          <label class="flex items-center gap-2 text-sm"><input type="hidden" name="serviceId" value="<?= e($s['id']) ?>"><input type="checkbox" name="mfaRequired" value="1" <?= (int)$s['mfaRequired']?'checked':'' ?> onchange="this.form.submit()"> Forcer</label>
          <input type="hidden" name="force_mfa_service" value="1">
        </form>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <div class="card p-5">
      <h3 class="font-semibold">Rôles & modules</h3>
      <p class="text-xs text-slate-500">Administration → Utilisateurs : entité/service + liste modules (ADMIN = tous). Contrôle via <code>hasModule()</code>.</p>
      <div class="mt-3 text-xs">
        <div class="font-medium">Droits par dossier/document</div>
        <p class="text-slate-500">Service ∈ user_service_access ET confidentialité ≥ niveau dossier.</p>
      </div>
    </div>
    <div class="card p-5">
      <h3 class="font-semibold flex items-center gap-2"><i data-lucide="scroll-text" class="w-4 h-4"></i> Journal d'audit — 20 dernières actions</h3>
      <div class="mt-3 divide-y text-sm">
        <?php foreach($rows as $r): ?><div class="py-2 flex justify-between gap-2"><div><span class="font-medium"><?= e($r['action']) ?></span> <span class="text-slate-400">par <?= e($r['firstName'].' '.$r['lastName']) ?></span><div class="text-xs text-slate-500"><?= e($r['description']??'') ?></div></div><span class="text-xs text-slate-400 shrink-0"><?= date('d/m H:i',strtotime($r['createdAt'])) ?></span></div><?php endforeach; ?>
        <?php if(empty($rows)): ?><p class="text-xs text-slate-400">Aucun log</p><?php endif; ?>
      </div>
      <a href="<?= BASE_URL ?>/dashboard/journal/index.php" class="text-xs text-blue-600 hover:underline mt-2 inline-block">Voir tout le journal →</a>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
