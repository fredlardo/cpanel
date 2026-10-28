<?php
// includes/functions.php
function statusBadge(string $status): string {
    $map = [
        'NOUVEAU' => ['bg-blue-50 text-blue-600','Nouveau'],
        'EN_COURS' => ['bg-yellow-50 text-yellow-600','En cours'],
        'EN_ATTENTE' => ['bg-orange-50 text-orange-600','En attente'],
        'CLOS' => ['bg-green-50 text-green-600','Clos'],
        'ANNULE' => ['bg-zinc-100 text-zinc-600','Annulé'],
        'REJETE' => ['bg-red-50 text-red-600','Rejeté'],
    ];
    [$cls,$label] = $map[$status] ?? ['bg-zinc-50 text-zinc-600', $status];
    return '<span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium '.$cls.'">'.$label.'</span>';
}

function logAction(PDO $pdo, string $dossierId, string $action, ?string $desc = null): void {
    $uid = $_SESSION['user']['id'] ?? null;
    if (!$uid) return;
    try{
      $prev=$pdo->query("SELECT hash_chain FROM action_logs ORDER BY createdAt DESC, id DESC LIMIT 1")->fetchColumn() ?: str_repeat('0',64);
      $payload=$prev.'|'.$dossierId.'|'.$action.'|'.($desc??'').'|'.$uid.'|'.time();
      $hash=hash('sha256', $payload);
      $pdo->prepare("INSERT INTO action_logs (id, action, description, dossierId, userId, prev_hash, hash_chain) VALUES (?,?,?,?,?,?,?)")
          ->execute([cuid(), $action, $desc, $dossierId, $uid, $prev, $hash]);
    }catch(Exception $e){
      $pdo->prepare("INSERT INTO action_logs (id, action, description, dossierId, userId) VALUES (?,?,?,?,?)")
          ->execute([cuid(), $action, $desc, $dossierId, $uid]);
    }
}

function hasModule(string $mod): bool {
    $u=currentUser(); if(!$u) return false;
    if($u['role']==='ADMIN') return true;
    return in_array($mod, $u['modules']??[], true);
}
function requireModule(string $mod): void {
    if(!hasModule($mod)){
        http_response_code(403);
        echo '<div class="card p-10 text-center"><p class="font-semibold">Accès non autorisé au module '.e($mod).'</p><p class="text-sm text-slate-500">Contactez votre administrateur.</p><a href="'.BASE_URL.'/dashboard/index.php" class="text-blue-600 text-sm">Retour</a></div>';
        require_once __DIR__ . '/footer.php'; exit;
    }
}
function getStats(PDO $pdo): array {
    $user = currentUser();
    $isAdmin = $user && $user['role']==='ADMIN';
    $ids = $isAdmin ? null : ($user['accessServiceIds'] ?? []);

    $base = "FROM dossiers WHERE deletedAt IS NULL";
    $params = [];
    if ($ids !== null && !empty($ids)) {
        $in = implode(',', array_fill(0,count($ids),'?'));
        $base .= " AND serviceId IN ($in)";
        $params = $ids;
    }
    // confidentialité / visibilité simplifiée
    $counts = [];
    $counts['total'] = (int)db()->prepare("SELECT COUNT(*) $base")->execute($params) ? 0:0; // placeholder

    // Requêtes réelles
    $q = function($where) use ($base,$params){
        $sql = "SELECT COUNT(*) as c $base $where";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetch()['c'];
    };
    return [
        'total' => $q(""),
        'nouveau' => $q(" AND status='NOUVEAU'"),
        'encours' => $q(" AND status='EN_COURS'"),
        'attente' => $q(" AND status='EN_ATTENTE'"),
        'clos' => $q(" AND status='CLOS'"),
    ];
}
