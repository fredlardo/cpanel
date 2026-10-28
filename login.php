<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) redirect(BASE_URL . '/dashboard/index.php');

$error = ''; $mfaRequired=false; $mfaLogin='';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $totp = trim($_POST['totp'] ?? '');
    // phase 2 : totp seul si mfa_pending
    if(isset($_POST['mfa_step']) && $_POST['mfa_step']==='1'){
        $login = trim($_POST['mfa_login'] ?? $login);
        $res = loginUser($login, $password, $totp);
    } else {
        $res = loginUser($login, $password, $totp ?: null);
    }
    if (($res['success'] ?? false)) {
        redirect(BASE_URL . '/dashboard/index.php');
    } elseif(!empty($res['mfa_required'])){
        $mfaRequired=true; $mfaLogin=$login; $error=$res['message'];
        // on garde password en hidden pour second submit (pas idéal mais évite session)
        $pendingPass=$password;
    } else {
        $error = $res['message'] ?? 'Erreur';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion - PRAGMA</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<style>:root{--primary:#1470B8;--primary-light:#2E88CE;--pragma-yellow:#F8D800}.login-side{background:linear-gradient(135deg,#0E5A96,#1470B8)}</style>
</head>
<body class="bg-white">
<div class="flex min-h-screen">
  <div class="flex flex-1 items-center justify-center px-4 py-12">
    <div class="w-full max-w-sm">
      <h2 class="text-2xl font-semibold mb-1">Connexion</h2>
      <p class="text-zinc-500 mb-6">Connectez-vous à votre espace</p>
      <?php if($mfaRequired): ?>
      <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800 mb-4">Authentification à deux facteurs requise — ouvrez Google Authenticator / Authy et saisissez le code à 6 chiffres.</div>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="mfa_step" value="1">
        <input type="hidden" name="mfa_login" value="<?= e($mfaLogin) ?>">
        <input type="hidden" name="login" value="<?= e($mfaLogin) ?>">
        <input type="hidden" name="password" value="<?= e($pendingPass ?? '') ?>">
        <div>
          <label class="block text-sm font-medium mb-1">Code TOTP (6 chiffres)</label>
          <input name="totp" type="text" inputmode="numeric" pattern="\d{6}" maxlength="6" required autofocus class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm outline-none focus:border-[#1470B8] focus:ring-1 focus:ring-[#1470B8] tracking-widest text-center text-lg" placeholder="000000">
        </div>
        <?php if($error): ?><div class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-600"><?= e($error) ?></div><?php endif; ?>
        <button type="submit" class="w-full rounded-lg bg-[#1470B8] px-4 py-2 text-sm font-medium text-white hover:bg-[#0E5A96]">Vérifier</button>
        <a href="<?= BASE_URL ?>/login.php" class="block text-center text-sm text-[#1470B8] hover:underline">Retour</a>
      </form>
      <?php else: ?>
      <form method="POST" class="space-y-4" id="loginForm">
        <div>
          <label class="block text-sm font-medium mb-1">Identifiant</label>
          <input name="login" type="text" required class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm outline-none focus:border-[#1470B8] focus:ring-1 focus:ring-[#1470B8]" placeholder="Votre identifiant" value="<?= e($_POST['login'] ?? '') ?>">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Mot de passe</label>
          <div class="relative">
            <input id="password" name="password" type="password" required class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 pr-10 text-sm outline-none focus:border-[#1470B8] focus:ring-1 focus:ring-[#1470B8]" placeholder="Votre mot de passe">
            <button type="button" id="togglePwd" class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400">👁</button>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Code TOTP <span class="text-zinc-400 font-normal">(si MFA activé)</span></label>
          <input name="totp" type="text" inputmode="numeric" pattern="\d{6}" maxlength="6" class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm tracking-widest text-center" placeholder="optionnel — 000000">
        </div>
        <?php if($error): ?><div class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-600"><?= e($error) ?></div><?php endif; ?>
        <button type="submit" class="w-full rounded-lg bg-[#1470B8] px-4 py-2 text-sm font-medium text-white hover:bg-[#0E5A96] transition-colors">Se connecter</button>
        <div class="pt-2 text-center"><a href="<?= BASE_URL ?>/forgot-password.php" class="text-sm text-[#1470B8] hover:underline">Mot de passe oublié ?</a></div>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <div class="hidden lg:flex flex-1 login-side items-center justify-center p-12">
    <div class="max-w-md text-white">
      <img src="<?= BASE_URL ?>/assets/img/pragma-logo-transparent.png?v=1" alt="PRAGMA Logistics" class="w-72 max-w-full h-auto mb-8" style="filter:brightness(0) invert(1);opacity:.85">
      <p class="text-lg opacity-90 leading-relaxed">Application de gestion électronique des documents, d'archivage et de travail collaboratif.</p>
      <div class="mt-6 space-y-2 text-sm opacity-80">
        <p>✓ Centralisation sécurisée des documents</p><p>✓ Circuit documentaire simplifié</p><p>✓ Collaboration entre services</p><p>✓ Traçabilité complète</p>
      </div>
    </div>
  </div>
</div>
<script>
$('#togglePwd').on('click', function(){ const i=$('#password'); i.attr('type', i.attr('type')==='password'?'text':'password'); });
</script>
</body>
</html>
