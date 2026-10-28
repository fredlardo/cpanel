<?php
// includes/licence.php — licence par entité
require_once __DIR__ . '/../config/database.php';

function licenceTableExists(PDO $pdo): bool {
  try { $pdo->query("SELECT 1 FROM entity_licences LIMIT 0"); return true; } catch(Exception $e){ return false; }
}
function licenceEnsureTables(PDO $pdo): void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS entity_licences (
    id VARCHAR(30) PRIMARY KEY,
    entityId VARCHAR(30) NOT NULL UNIQUE,
    licence_key VARCHAR(64) NOT NULL UNIQUE,
    plan ENUM('DEMO','STANDARD','PRO','ENTERPRISE') DEFAULT 'STANDARD',
    status ENUM('ACTIVE','GRACE','EXPIRED','SUSPENDED','REVOKED') DEFAULT 'ACTIVE',
    seats INT DEFAULT 50,
    max_dossiers INT DEFAULT 10000,
    max_storage_mb INT DEFAULT 10240,
    features JSON NULL,
    issued_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    grace_until DATETIME NULL,
    last_check_at DATETIME NULL,
    signature VARCHAR(128) NOT NULL,
    issued_by VARCHAR(30) NULL,
    notes TEXT NULL,
    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (entityId) REFERENCES entities(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS entity_licence_history (
    id VARCHAR(30) PRIMARY KEY,
    entityId VARCHAR(30) NOT NULL,
    action VARCHAR(30) NOT NULL,
    detail TEXT NULL, userId VARCHAR(30) NULL, createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(entityId) REFERENCES entities(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  // seed DEMO 30j si vide
  try{
    $ents=$pdo->query("SELECT id,code FROM entities")->fetchAll();
    foreach($ents as $ent){
      $chk=$pdo->prepare("SELECT id FROM entity_licences WHERE entityId=?"); $chk->execute([$ent['id']]); if($chk->fetch()) continue;
      $exp=date('Y-m-d H:i:s', strtotime('+30 days'));
      $grace=date('Y-m-d H:i:s', strtotime($exp.' +'.LICENCE_GRACE_DAYS.' days'));
      $plan='DEMO'; $sig=hash_hmac('sha256', $ent['id'].'|'.$exp.'|'.$plan, LICENCE_SECRET);
      $key='DXR-'.strtoupper(substr(md5($ent['id'].$exp.LICENCE_SECRET.microtime()),0,4).'-'.substr(md5($ent['id'].$plan, false),0,4).'-'.substr(md5($exp, false),0,4).'-'.substr($sig,0,4));
      $pdo->prepare("INSERT INTO entity_licences (id,entityId,licence_key,plan,status,expires_at,grace_until,signature,issued_at) VALUES (?,?,?,?,?,?,?,?,NOW())")->execute([cuid(),$ent['id'],$key,$plan,'ACTIVE',$exp,$grace,$sig]);
    }
  }catch(Exception $e){}
}

function licenceSignature(string $entityId, string $expires_at, string $plan): string {
  return hash_hmac('sha256', $entityId.'|'.$expires_at.'|'.$plan, LICENCE_SECRET);
}
function licenceGenerate(PDO $pdo, string $entityId, string $plan='STANDARD', int $days=365, int $seats=50, int $maxDoss=10000, int $maxStor=10240): array {
  $exp=date('Y-m-d H:i:s', strtotime("+$days days"));
  $grace=date('Y-m-d H:i:s', strtotime($exp.' +'.LICENCE_GRACE_DAYS.' days'));
  $sig=licenceSignature($entityId,$exp,$plan);
  $key='DXR-'.strtoupper(substr(md5($entityId.$exp.LICENCE_SECRET.microtime()),0,4).'-'.substr(md5($entityId.$plan, false),0,4).'-'.substr(md5($exp, false),0,4).'-'.substr($sig,0,4));
  return [$key,$exp,$grace,$sig];
}
function getUserEntityId(PDO $pdo, array $user): ?string {
  if(empty($user['serviceId'])) return null;
  $q=$pdo->prepare("SELECT e.id FROM entities e JOIN organizations o ON o.entityId=e.id JOIN divisions d ON d.organizationId=o.id JOIN services s ON s.divisionId=d.id WHERE s.id=? LIMIT 1");
  $q->execute([$user['serviceId']]); return $q->fetchColumn() ?: null;
}
function getEntityLicence(PDO $pdo, string $entityId): ?array {
  if(!licenceTableExists($pdo)) return null;
  $s=$pdo->prepare("SELECT * FROM entity_licences WHERE entityId=?"); $s->execute([$entityId]); return $s->fetch() ?: null;
}
function licenceStatus(PDO $pdo, string $entityId): array {
  $lic=getEntityLicence($pdo,$entityId);
  if(!$lic) return ['status'=>'NONE','lic'=>null,'msg'=>'Aucune licence'];
  if(in_array($lic['status'],['SUSPENDED','REVOKED'])) return ['status'=>$lic['status'],'lic'=>$lic,'msg'=>$lic['status']];
  $now=time(); $exp=strtotime($lic['expires_at']); $grace=$lic['grace_until']?strtotime($lic['grace_until']):$exp+LICENCE_GRACE_DAYS*86400;
  $sigOk = hash_equals($lic['signature'], licenceSignature($entityId,$lic['expires_at'],$lic['plan']));
  if(!$sigOk) return ['status'=>'INVALID','lic'=>$lic,'msg'=>'Signature invalide'];
  if($now <= $exp) return ['status'=>'ACTIVE','lic'=>$lic,'msg'=>'Active'];
  if($now <= $grace) return ['status'=>'GRACE','lic'=>$lic,'msg'=>'Grâce jusqu\'au '.date('d/m/Y', $grace)];
  return ['status'=>'EXPIRED','lic'=>$lic,'msg'=>'Expirée le '.date('d/m/Y', $exp)];
}
function licenceEnforce(PDO $pdo, ?string $entityId, string $action='write'): void {
  if(!$entityId) return;
  $st=licenceStatus($pdo,$entityId);
  if(in_array($st['status'],['EXPIRED','SUSPENDED','REVOKED','INVALID'])){
    http_response_code(403);
    if(($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='XMLHttpRequest' || ($_SERVER['HTTP_ACCEPT']??'')==='application/json' || isset($_POST['ajax'])){
      header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>'Licence '.$st['status'].' : '.$st['msg'].' — contactez l\'administrateur.']); exit;
    }
    // non-SUPERADMIN → Ma licence (lecture seule), SUPERADMIN → gestion
    $isSuper=(currentUser()['role']??'')==='SUPERADMIN';
    $_SESSION['flash_error']='Licence '.$st['status'].' : '.$st['msg'].' — accès limité.';
    redirect($isSuper ? BASE_URL.'/dashboard/entites/licence.php?id='.urlencode($entityId) : BASE_URL.'/dashboard/ma-licence.php');
  }
}
function licenceIsBlocked(PDO $pdo, ?string $entityId): bool {
  if(!$entityId) return false;
  $st=licenceStatus($pdo,$entityId);
  return in_array($st['status'],['SUSPENDED','REVOKED','EXPIRED','INVALID'], true);
}
