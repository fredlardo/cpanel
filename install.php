<?php
// install.php - Installation Doxera+ PHP
require_once __DIR__ . '/config/app.php';

$msg = '';
$err = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['install'])) {
    // Connexion SANS base pour pouvoir la créer
    $DB_HOST = '127.0.0.1';
    $DB_USER = 'root';
    $DB_PASS = '';
    $DB_NAME = 'doxera_plus';
    try {
        $pdoRoot = new PDO("mysql:host=$DB_HOST;charset=utf8mb4", $DB_USER, $DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `$DB_NAME` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    } catch(Exception $e){
        $err = "Connexion MySQL échouée: ".$e->getMessage()." - Vérifiez que MySQL/MariaDB est démarré (XAMPP -> Start MySQL)";
        goto render;
    }
    // Import database.sql
    $sql = file_get_contents(__DIR__.'/database.sql');
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        // Split statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach($statements as $stmt){
            if($stmt==='') continue;
            try { $pdo->exec($stmt); } catch(Exception $e){ /* ignore */ }
        }
        // Seed
        $seed = file_get_contents(__DIR__.'/seed.sql');
        $statements = array_filter(array_map('trim', explode(';', $seed)));
        foreach($statements as $stmt){
            if($stmt==='') continue;
            try { $pdo->exec($stmt); } catch(Exception $e){ $err .= $e->getMessage()."<br>"; }
        }
        // Re-hash passwords correctly
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password=?")->execute([$hash]);
        $msg = "✅ Installation réussie ! Base doxera_plus créée.<br>Comptes: admin/admin123, jdupont/admin123, mmartin/admin123";
    } catch(Exception $e){ $err = $e->getMessage(); }
}
render:
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Installation Doxera+</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-zinc-50 p-8">
<div class="max-w-xl mx-auto bg-white rounded-xl border p-6">
<h1 class="text-xl font-bold mb-2">Installation Doxera+ PHP</h1>
<p class="text-sm text-zinc-500 mb-4">Crée la base MySQL <b>doxera_plus</b> et injecte les données de démo. Assurez-vous que MySQL tourne et que l'utilisateur root sans mot de passe a les droits (XAMPP/WAMP).</p>
<?php if($msg): ?><div class="bg-green-50 border border-green-200 text-green-700 p-3 rounded text-sm mb-4"><?= $msg ?></div><?php endif; ?>
<?php if($err): ?><div class="bg-red-50 border border-red-200 text-red-700 p-3 rounded text-sm mb-4"><?= $err ?></div><?php endif; ?>
<form method="POST"><button name="install" value="1" class="w-full bg-blue-900 text-white rounded-lg py-2">Lancer l'installation</button></form>
<p class="text-xs text-zinc-400 mt-4">Après installation, supprimez ou renommez ce fichier.</p>
<a href="login.php" class="text-sm text-blue-700 hover:underline block mt-4">→ Aller à la connexion</a>
</div>
</body></html>
