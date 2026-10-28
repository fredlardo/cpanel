<?php
$pageTitle='Profil';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/totp.php';
$u=currentUser();
$pdo=db();
$tab=$_GET['tab']??'infos';
$msg=''; $err='';

// --- profil infos ---
if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["update"])){
  $pdo->prepare("UPDATE users SET firstName=?,lastName=?,phone=? WHERE id=?")->execute([$_POST["firstName"],$_POST["lastName"],$_POST["phone"],$u["id"]]);
  $_SESSION["user"]["firstName"]=$_POST["firstName"]; $_SESSION["user"]["lastName"]=$_POST["lastName"]; $_SESSION["user"]["name"]=$_POST["firstName"]." ".$_POST["lastName"];
  $msg="Profil mis à jour";
}
// --- TOTP : générer secret ---
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['mfa_generate'])){
  $row=$pdo->prepare("SELECT mfaSecret,mfaEnabled FROM users WHERE id=?"); $row->execute([$u['id']]); $cur=$row->fetch();
  $secret=$cur['mfaSecret'] ?: totp_generateSecret();
  $pdo->prepare("UPDATE users SET mfaSecret=? WHERE id=?")->execute([$secret,$u['id']]);
  $msg="Secret TOTP généré — scannez le QR puis validez avec un code.";
  $tab='securite';
}
// --- TOTP : activer ---
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['mfa_enable'])){
  $code=trim($_POST['totp']??'');
  $row=$pdo->prepare("SELECT mfaSecret FROM users WHERE id=?"); $row->execute([$u['id']]); $secret=$row->fetchColumn();
  if(!$secret){ $err="Générez d'abord le secret."; }
  elseif(!totp_verify($secret,$code)){ $err="Code invalide — vérifiez l'heure de votre téléphone."; }
  else { $pdo->prepare("UPDATE users SET mfaEnabled=1 WHERE id=?")->execute([$u['id']]); $_SESSION['user']['mfaEnabled']=1; $msg="MFA activé avec succès."; }
  $tab='securite';
}
// --- TOTP : désactiver ---
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['mfa_disable'])){
  $code=trim($_POST['totp']??'');
  $row=$pdo->prepare("SELECT mfaSecret,mfaEnabled FROM users WHERE id=?"); $row->execute([$u['id']]); $cur=$row->fetch();
  if((int)$cur['mfaEnabled']===0){ $msg="MFA déjà désactivé."; }
  elseif($cur['mfaSecret'] && !totp_verify($cur['mfaSecret'],$code)){ $err="Code requis pour désactiver — code invalide."; }
  else { $pdo->prepare("UPDATE users SET mfaEnabled=0 WHERE id=?")->execute([$u['id']]); $_SESSION['user']['mfaEnabled']=0; $msg="MFA désactivé."; }
  $tab='securite';
}
// --- reset secret ---
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['mfa_reset'])){
  $pdo->prepare("UPDATE users SET mfaSecret=NULL, mfaEnabled=0 WHERE id=?")->execute([$u['id']]); $_SESSION['user']['mfaEnabled']=0; $msg="Secret réinitialisé."; $tab='securite';
}

$row=db()->prepare("SELECT * FROM users WHERE id=?"); $row->execute([$u["id"]]); $me=$row->fetch();
// service force ?
$serviceForce=false; try{ $sf=$pdo->prepare("SELECT mfaRequired FROM services WHERE id=?"); $sf->execute([$me['serviceId']]); $serviceForce=(int)$sf->fetchColumn()===1; $chk=$pdo->prepare("SELECT COUNT(*) FROM user_service_access usa JOIN services s ON s.id=usa.serviceId WHERE usa.userId=? AND s.mfaRequired=1"); $chk->execute([$me['id']]); if((int)$chk->fetchColumn()>0) $serviceForce=true; }catch(Exception $e){}
?>
<div class="flex items-center justify-between mb-4">
  <h1 class="text-xl font-extrabold text-slate-900">Mon profil</h1>
  <div class="flex gap-1 text-sm">
    <a href="?tab=infos" class="px-4 py-2 rounded-xl <?= $tab==='infos'?'bg-slate-900 text-white':'bg-white border hover:bg-slate-50' ?>">Infos</a>
    <a href="?tab=securite" class="px-4 py-2 rounded-xl <?= $tab==='securite'?'bg-slate-900 text-white':'bg-white border hover:bg-slate-50' ?>">Sécurité — 2FA</a>
  </div>
</div>
<?php if($msg): ?><div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-2 rounded-xl text-sm mb-4"><?= e($msg) ?></div><?php endif; ?>
<?php if($err): ?><div class="bg-red-50 border border-red-200 text-red-600 px-4 py-2 rounded-xl text-sm mb-4"><?= e($err) ?></div><?php endif; ?>

<?php if($tab==='infos'): ?>
<div class="card p-6 max-w-lg">
<form method="POST" class="space-y-3">
  <div><label class="text-sm font-medium">Prénom</label><input name="firstName" value="<?= e($me["firstName"]) ?>" class="input"></div>
  <div><label class="text-sm font-medium">Nom</label><input name="lastName" value="<?= e($me["lastName"]) ?>" class="input"></div>
  <div><label class="text-sm font-medium">Email</label><input value="<?= e($me["email"]) ?>" disabled class="input bg-zinc-50"></div>
  <div><label class="text-sm font-medium">Login</label><input value="<?= e($me["login"]) ?>" disabled class="input bg-zinc-50"></div>
  <div><label class="text-sm font-medium">Téléphone</label><input name="phone" value="<?= e($me["phone"]) ?>" class="input"></div>
  <button name="update" value="1" class="btn-primary">Enregistrer</button>
</form>
</div>
<?php else: ?>
<div class="grid lg:grid-cols-2 gap-6 max-w-4xl">
  <div class="card p-6">
    <h3 class="font-semibold flex items-center gap-2"><i data-lucide="shield-check" class="w-5 h-5 text-emerald-600"></i> Authentification à deux facteurs (TOTP)</h3>
    <p class="text-xs text-slate-500 mt-1">Compatible Google Authenticator, Authy, Microsoft Authenticator. Code à 6 chiffres, fenêtre 30s.</p>
    <?php if($serviceForce): ?><div class="mt-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-3 py-2 text-xs">Votre service exige le MFA — vous devez l'activer. (ADMIN a forcé MFA pour ce service)</div><?php endif; ?>
    <div class="mt-4 flex items-center gap-2 text-sm"><span class="h-2 w-2 rounded-full <?= (int)$me['mfaEnabled']?'bg-emerald-500':'bg-slate-300' ?>"></span> Statut : <b><?= (int)$me['mfaEnabled']?'Activé':'Désactivé' ?></b> <?php if($me['mfaSecret']): ?><span class="text-xs text-slate-400">— secret configuré</span><?php endif; ?></div>

    <?php if(empty($me['mfaSecret'])): ?>
      <form method="POST" class="mt-4"><button name="mfa_generate" value="1" class="bg-slate-900 text-white px-4 py-2 rounded-xl text-sm">Générer le secret TOTP</button></form>
      <p class="text-xs text-slate-400 mt-2">Génère un secret Base32 et affiche le QR à scanner.</p>
    <?php else: ?>
      <div class="mt-4 border rounded-xl p-4 bg-slate-50 text-center">
        <img src="<?= e(totp_qr_url($me['email'],$me['mfaSecret'])) ?>" alt="QR TOTP" class="mx-auto h-48 w-48 bg-white p-2 rounded-xl border">
        <p class="text-xs text-slate-600 mt-2 font-mono break-all"><?= e($me['mfaSecret']) ?></p>
        <p class="text-xs text-slate-400 mt-1">Scannez avec votre appli, puis validez ci-dessous.</p>
        <p class="text-xs text-slate-500 mt-1">URI : <code class="bg-white border px-1 rounded text-[11px] break-all">otpauth://totp/Doxera+:<?= e($me['email']) ?>?secret=<?= e($me['mfaSecret']) ?>&issuer=Doxera%2B</code></p>
      </div>
      <div class="mt-4 grid gap-3">
        <?php if(!(int)$me['mfaEnabled']): ?>
        <form method="POST" class="flex gap-2 items-end">
          <div class="flex-1"><label class="block text-xs font-medium mb-1">Code à 6 chiffres</label><input name="totp" inputmode="numeric" pattern="\d{6}" maxlength="6" required placeholder="000000" class="input tracking-widest text-center"></div>
          <button name="mfa_enable" value="1" class="bg-emerald-600 text-white px-5 py-2 rounded-xl text-sm">Activer MFA</button>
        </form>
        <?php else: ?>
        <form method="POST" class="flex gap-2 items-end">
          <div class="flex-1"><label class="block text-xs font-medium mb-1">Code pour désactiver</label><input name="totp" inputmode="numeric" pattern="\d{6}" maxlength="6" required placeholder="000000" class="input tracking-widest text-center"></div>
          <button name="mfa_disable" value="1" class="bg-red-600 text-white px-5 py-2 rounded-xl text-sm">Désactiver MFA</button>
        </form>
        <?php endif; ?>
        <form method="POST" class="flex gap-2">
          <button name="mfa_generate" value="1" class="border bg-white px-4 py-2 rounded-xl text-sm">Régénérer secret</button>
          <button name="mfa_reset" value="1" onclick="return confirm('Réinitialiser le secret ?')" class="border bg-white px-4 py-2 rounded-xl text-sm text-red-600">Réinitialiser</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
  <div class="space-y-4">
    <div class="card p-5">
      <h4 class="font-semibold text-sm">Comment ça marche</h4>
      <ol class="text-xs text-slate-600 mt-2 list-decimal pl-4 space-y-1">
        <li>Générez le secret et scannez le QR dans Google Authenticator.</li>
        <li>Saisissez le code à 6 chiffres pour activer.</li>
        <li>À la prochaine connexion, le code TOTP vous sera demandé après le mot de passe.</li>
        <li>ADMIN peut forcer MFA pour tout un service (onglet Sécurité).</li>
      </ol>
    </div>
    <div class="card p-5">
      <h4 class="font-semibold text-sm">Sécurité du compte</h4>
      <div class="text-xs text-slate-600 mt-2 space-y-1">
        <div>Login : <b><?= e($me['login']) ?></b></div>
        <div>Email : <?= e($me['email']) ?></div>
        <div>Service : <?= e($me['serviceId']) ?> <?php if($serviceForce): ?><span class="bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">MFA forcé</span><?php endif; ?></div>
        <div>Rôle : <?= e($me['role']) ?></div>
      </div>
      <a href="<?= BASE_URL ?>/dashboard/securite/index.php" class="text-xs text-blue-600 hover:underline mt-3 inline-block">Aller à Sécurité (vue ADMIN) →</a>
    </div>
  </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
