<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/totp.php';

function getMfaRequiredForUser(array $user): bool {
    if((int)($user['mfaEnabled']??0)===1) return true;
    try{
        $pdo=db();
        if(!empty($user['serviceId'])){
            $st=$pdo->prepare("SELECT mfaRequired FROM services WHERE id=?");
            $st->execute([$user['serviceId']]); $r=$st->fetchColumn(); if((int)$r===1) return true;
        }
        // accès multi-services : si un des services force MFA, on l'exige
        $st2=$pdo->prepare("SELECT COUNT(*) FROM user_service_access usa JOIN services s ON s.id=usa.serviceId WHERE usa.userId=? AND s.mfaRequired=1");
        $st2->execute([$user['id']]); if((int)$st2->fetchColumn()>0) return true;
    }catch(Exception $e){}
    return false;
}

function loginUser(string $login, string $password, ?string $totpCode=null): array {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT u.*, s.name as serviceName, s.divisionId, s.mfaRequired as serviceMfaRequired, d.name as divisionName, d.organizationId, o.name as orgName, e.id as entityId, e.name as entityName
        FROM users u
        LEFT JOIN services s ON s.id = u.serviceId
        LEFT JOIN divisions d ON d.id = s.divisionId
        LEFT JOIN organizations o ON o.id = d.organizationId
        LEFT JOIN entities e ON e.id = o.entityId
        WHERE u.login = ? LIMIT 1");
    $stmt->execute([$login]);
    $user = $stmt->fetch();
    if (!$user) return ['success'=>false, 'message'=>'Identifiant ou mot de passe incorrect'];
    if ((int)$user['isActive'] !== 1) return ['success'=>false, 'message'=>'Compte désactivé'];
    if (!password_verify($password, $user['password'])) return ['success'=>false, 'message'=>'Identifiant ou mot de passe incorrect'];

    $mfaRequired = (int)($user['mfaEnabled']??0)===1 || (int)($user['serviceMfaRequired']??0)===1;
    if(!$mfaRequired){
        // vérif multi-services force
        try{ $chk=$pdo->prepare("SELECT COUNT(*) FROM user_service_access usa JOIN services s ON s.id=usa.serviceId WHERE usa.userId=? AND s.mfaRequired=1"); $chk->execute([$user['id']]); if((int)$chk->fetchColumn()>0) $mfaRequired=true; }catch(Exception $e){}
    }
    if($mfaRequired){
        if(empty($user['mfaSecret'])){
            // service force mais pas encore configuré : on laisse passer vers profil pour configurer, mais on marque session pending
            $_SESSION['mfa_pending_setup']=$user['id'];
        } elseif($totpCode===null || $totpCode===''){
            return ['success'=>false, 'mfa_required'=>true, 'userId'=>$user['id'], 'message'=>'Code TOTP requis'];
        } elseif(!totp_verify($user['mfaSecret'], $totpCode)){
            return ['success'=>false, 'mfa_required'=>true, 'userId'=>$user['id'], 'message'=>'Code TOTP invalide'];
        }
    }

    // Charger accès multi-services
    $stmt2 = $pdo->prepare("SELECT serviceId FROM user_service_access WHERE userId = ?");
    $stmt2->execute([$user['id']]);
    $accessIds = array_column($stmt2->fetchAll(), 'serviceId');
    if (empty($accessIds)) $accessIds = [$user['serviceId']];

    $modules = json_decode($user['modules'] ?? '[]', true) ?: [];
    // modules par défaut si vide (évite sidebar vide pour USER/SUPERVISOR seedés sans modules)
    if(empty($modules)){
        if($user['role']==='SUPERADMIN') $modules = ['dashboard','entites','licences','parametrage','administration'];
        elseif($user['role']==='ADMIN') $modules = ['dashboard','dossiers','acquisition','numerisation','recherche','versions','preview','partage','collaboration','workflows','securite','archivage','administration','entites','groupes','parametrage','statistiques','corbeille','journal'];
        elseif($user['role']==='SUPERVISOR') $modules = ['dashboard','dossiers','acquisition','numerisation','recherche','archivage','corbeille','statistiques','journal','modeles'];
        else $modules = ['dashboard','dossiers','acquisition','numerisation','recherche','archivage','corbeille','statistiques','modeles'];
    }
    // SUPERADMIN legacy ['*'] -> restreindre à dashboard+entites+licences+parametrage+administration
    if($user['role']==='SUPERADMIN' && in_array('*', $modules, true)) $modules = ['dashboard','entites','licences','parametrage','administration'];
    $_SESSION['user'] = [
        'id' => $user['id'],
        'email' => $user['email'],
        'login' => $user['login'],
        'firstName' => $user['firstName'],
        'lastName' => $user['lastName'],
        'name' => $user['firstName'].' '.$user['lastName'],
        'role' => $user['role'],
        'serviceId' => $user['serviceId'],
        'serviceName' => $user['serviceName'],
        'divisionName' => $user['divisionName'],
        'organizationName' => $user['orgName'],
        'entityName' => $user['entityName'],
        'entityId' => $user['entityId'] ?? null,
        'confidentialityLevel' => (int)$user['confidentialityLevel'],
        'visibilityLevel' => (int)$user['visibilityLevel'],
        'accessServiceIds' => $accessIds,
        'modules' => $modules,
        'mfaEnabled' => (int)($user['mfaEnabled']??0),
    ];
    unset($_SESSION['mfa_pending_setup']);
    // Log connexion
    return ['success'=>true];
}

function logoutUser(): void {
    session_destroy();
}

function getAccessibleServiceIds(): ?array {
    $u = currentUser();
    if (!$u) return null;
    if (in_array($u['role'], ['ADMIN','SUPERADMIN'], true)) return null; // null = tous
    return $u['accessServiceIds'];
}
