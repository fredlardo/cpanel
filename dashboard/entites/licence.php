<?php
$pageTitle='Licence entité';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/licence.php';
$pdo=db(); licenceEnsureTables($pdo);
if(currentUser()['role']!=='SUPERADMIN'){ echo '<div class="card p-10 text-center">Réservé SUPERADMIN</div>'; require_once __DIR__ . '/../../includes/footer.php'; exit; }
$id=$_GET['id']??''; if(!$id) die('id manquant');
$ent=$pdo->prepare("SELECT * FROM entities WHERE id=?"); $ent->execute([$id]); $e=$ent->fetch(); if(!$e) die('Entité introuvable');
$msg=''; $err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $act=$_POST['act']??'';
  try{
    if($act==='generate'){
      $plan=$_POST['plan']??'STANDARD'; $days=(int)($_POST['days']??365); $seats=(int)($_POST['seats']??50);
      $maxDoss=(int)($_POST['max_dossiers']??10000); $maxStor=(int)($_POST['max_storage_mb']??10240);
      [$key,$exp,$grace,$sig]=licenceGenerate($pdo,$id,$plan,$days,$seats,$maxDoss,$maxStor);
      $pdo->prepare("INSERT INTO entity_licences (id,entityId,licence_key,plan,status,seats,max_dossiers,max_storage_mb,expires_at,grace_until,signature,issued_by,issued_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE licence_key=VALUES(licence_key),plan=VALUES(plan),status='ACTIVE',seats=VALUES(seats),max_dossiers=VALUES(max_dossiers),max_storage_mb=VALUES(max_storage_mb),expires_at=VALUES(expires_at),grace_until=VALUES(grace_until),signature=VALUES(signature),issued_by=VALUES(issued_by),updatedAt=NOW()")->execute([cuid(),$id,$key,$plan,'ACTIVE',$seats,$maxDoss,$maxStor,$exp,$grace,$sig,currentUser()['id']]);
      $pdo->prepare("INSERT INTO entity_licence_history (id,entityId,action,detail,userId) VALUES (?,?,?,?,?)")->execute([cuid(),$id,'GENERATE',"$plan $days j seats=$seats",$id]);
      $msg="Licence $plan générée jusqu'au ".date('d/m/Y H:i', strtotime($exp))." — clé $key";
    } elseif($act==='suspend'){ $pdo->prepare("UPDATE entity_licences SET status='SUSPENDED' WHERE entityId=?")->execute([$id]); $msg='Licence suspendue.'; $pdo->prepare("INSERT INTO entity_licence_history (id,entityId,action,userId) VALUES (?,?,?,?)")->execute([cuid(),$id,'SUSPEND',currentUser()['id']]);
    } elseif($act==='reactivate'){ $pdo->prepare("UPDATE entity_licences SET status='ACTIVE' WHERE entityId=?")->execute([$id]); $msg='Licence réactivée.';
    } elseif($act==='revoke'){ $pdo->prepare("UPDATE entity_licences SET status='REVOKED' WHERE entityId=?")->execute([$id]); $msg='Licence révoquée.';
    } elseif($act==='verify'){ $st=licenceStatus($pdo,$id); $pdo->prepare("UPDATE entity_licences SET last_check_at=NOW() WHERE entityId=?")->execute([$id]); $msg='Vérification: '.$st['status'].' — '.$st['msg'];
    } elseif($act==='save_key'){ $k=trim($_POST['licence_key']??''); if(!$k) throw new Exception('Clé vide'); $pdo->prepare("UPDATE entity_licences SET licence_key=?,updatedAt=NOW() WHERE entityId=?")->execute([$k,$id]); $msg='Clé mise à jour.';
    }
  }catch(Exception $ex){ $err=$ex->getMessage(); }
}
$lic=getEntityLicence($pdo,$id); $st=licenceStatus($pdo,$id);
$hist=$pdo->prepare("SELECT * FROM entity_licence_history WHERE entityId=? ORDER BY createdAt DESC LIMIT 20"); $hist->execute([$id]); $hRows=$hist->fetchAll();
// usage
$usage=['users'=>0,'dossiers'=>0]; try{
  $usage['users']=$pdo->prepare("SELECT COUNT(*) FROM users u JOIN services s ON s.id=u.serviceId JOIN divisions d ON d.organizationId=s.divisionId JOIN organizations o ON o.id=d.organizationId WHERE o.entityId=? AND u.isActive=1"); $usage['users']->execute([$id]); $usage['users']=(int)$usage['users']->fetchColumn();
  $usage['dossiers']=$pdo->prepare("SELECT COUNT(*) FROM dossiers d JOIN services s ON s.id=d.serviceId JOIN divisions dv ON dv.id=s.divisionId JOIN organizations o ON o.id=dv.organizationId WHERE o.entityId=? AND d.deletedAt IS NULL"); $usage['dossiers']->execute([$id]); $usage['dossiers']=(int)$usage['dossiers']->fetchColumn();
}catch(Exception $e){}
?>
<div class="space-y-6">
  <a href="<?= BASE_URL ?>/dashboard/entites/index.php" class="text-sm text-slate-500 hover:text-slate-900">← Retour entités</a>
  <div class="flex items-start justify-between gap-4">
    <div><h1 class="text-2xl font-bold"><?= e($e['name']) ?> <span class="text-sm font-mono text-slate-500">(<?= e($e['code']) ?>)</span></h1><p class="text-sm text-slate-500">Licence par entité — plan, siège, expiration, vérification signature</p></div>
    <span class="px-3 py-1 rounded-full text-xs font-medium border <?= $st['status']==='ACTIVE'?'bg-emerald-100 text-emerald-700':'bg-amber-100 text-amber-700' ?><?= $st['status']==='EXPIRED'?'bg-red-100 text-red-700':'' ?>"><?= e($st['status']) ?></span>
  </div>
  <?php if($msg): ?><div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-2 rounded-xl text-sm"><?= e($msg) ?></div><?php endif; ?>
  <?php if($err): ?><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-2 rounded-xl text-sm"><?= e($err) ?></div><?php endif; ?>

  <div class="grid lg:grid-cols-3 gap-4">
    <div class="card p-5 lg:col-span-2 space-y-3">
      <h3 class="font-semibold flex items-center gap-2"><i data-lucide="key-round" class="w-4 h-4"></i> Licence actuelle</h3>
      <?php if(!$lic): ?><p class="text-sm text-slate-400">Aucune licence — générez-en une.</p><?php else: ?>
      <div class="grid grid-cols-2 gap-3 text-sm">
        <div><span class="text-slate-500">Clé</span><div class="font-mono font-medium break-all"><?= e($lic['licence_key']) ?></div></div>
        <div><span class="text-slate-500">Plan</span><div class="font-medium"><?= e($lic['plan']) ?> · <?= e($lic['status']) ?></div></div>
        <div><span class="text-slate-500">Expire</span><div><?= date('d/m/Y H:i', strtotime($lic['expires_at'])) ?> (grâce <?= $lic['grace_until']?date('d/m/Y', strtotime($lic['grace_until'])):'—' ?>)</div></div>
        <div><span class="text-slate-500">Siège / Dossiers / Stockage</span><div><?= (int)$lic['seats'] ?> sièges · <?= (int)$lic['max_dossiers'] ?> dossiers · <?= (int)$lic['max_storage_mb'] ?> Mo</div></div>
        <div><span class="text-slate-500">Usage actuel</span><div><?= $usage['users'] ?> users / <?= $usage['dossiers'] ?> dossiers <?= $lic['seats']? '('.round($usage['users']/$lic['seats']*100).'%)' : '' ?></div></div>
        <div><span class="text-slate-500">Signature</span><div class="font-mono text-xs break-all"><?= e(substr($lic['signature'],0,32)) ?>…</div><span class="text-xs <?= $st['status']==='INVALID'?'text-red-600':'text-emerald-600' ?>"><?= e($st['msg']) ?></span></div>
      </div>
      <form method="POST" class="flex gap-2 pt-2">
        <input type="hidden" name="act" value="verify"><button class="border px-3 py-1.5 rounded-xl text-xs bg-white">Vérifier signature</button>
        <input type="hidden" name="act" value="save_key">
      </form>
      <form method="POST" class="flex gap-2"><input type="hidden" name="act" value="save_key"><input name="licence_key" placeholder="Coller une clé externe" class="input text-xs flex-1"><button class="bg-slate-900 text-white px-3 py-1.5 rounded-xl text-xs">Enregistrer clé</button></form>
      <?php endif; ?>
    </div>
    <div class="card p-5 space-y-3">
      <h3 class="font-semibold">Actions</h3>
      <form method="POST" class="space-y-2">
        <input type="hidden" name="act" value="generate">
        <label class="block text-xs font-medium">Plan</label><select name="plan" class="input text-sm w-full"><option>DEMO</option><option selected>STANDARD</option><option>PRO</option><option>ENTERPRISE</option></select>
        <label class="block text-xs font-medium">Durée</label><select name="days" class="input text-sm w-full"><option value="30">30 jours</option><option value="90">90 jours</option><option value="365" selected>1 an</option><option value="730">2 ans</option></select>
        <div class="grid grid-cols-3 gap-2"><div><label class="block text-xs">Sièges</label><input name="seats" type="number" value="50" class="input text-sm w-full"></div><div><label class="block text-xs">Max dossiers</label><input name="max_dossiers" type="number" value="10000" class="input text-sm w-full"></div><div><label class="block text-xs">Stockage Mo</label><input name="max_storage_mb" type="number" value="10240" class="input text-sm w-full"></div></div>
        <button class="w-full bg-blue-600 text-white py-2 rounded-xl text-sm">Générer / Renouveler</button>
      </form>
      <div class="flex gap-2">
        <form method="POST" class="flex-1"><input type="hidden" name="act" value="suspend"><button class="w-full border py-1.5 rounded-xl text-xs">Suspendre</button></form>
        <form method="POST" class="flex-1"><input type="hidden" name="act" value="reactivate"><button class="w-full border py-1.5 rounded-xl text-xs">Réactiver</button></form>
        <form method="POST" class="flex-1" onsubmit="return confirm('Révoquer ?')"><input type="hidden" name="act" value="revoke"><button class="w-full border border-red-200 text-red-600 py-1.5 rounded-xl text-xs">Révoquer</button></form>
      </div>
    </div>
  </div>
  <div class="card p-5">
    <h3 class="font-semibold mb-3">Historique (20)</h3>
    <?php if(empty($hRows)): ?><p class="text-sm text-slate-400">Aucun événement</p><?php else: foreach($hRows as $h): ?>
      <div class="flex justify-between text-xs border-b py-1.5"><span><?= e($h['action']) ?> — <?= e($h['detail']??'') ?></span><span class="text-slate-400"><?= date('d/m/Y H:i', strtotime($h['createdAt'])) ?></span></div>
    <?php endforeach; endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
