<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
$pdo=db();
$msg=''; $err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $entityId=$_POST['entityId']??''; $title=trim($_POST['title']??''); $email=trim($_POST['email']??''); $phone=trim($_POST['phone']??'');
  if(!$entityId||!$title||!$email) $err='Entité, titre, email requis.';
  else {
    $cnt=$pdo->prepare("SELECT COUNT(*) FROM public_deposits WHERE entityId=? AND DATE(createdAt)=CURDATE()"); $cnt->execute([$entityId]); if((int)$cnt->fetchColumn()>=200) $err='Quota journalier atteint.';
    else {
      $id=cuid(); $dir=__DIR__.'/../uploads/public/'.$id.'/'; if(!is_dir($dir)) mkdir($dir,0775,true);
      $files=[]; if(!empty($_FILES['files']['name'][0])){ foreach($_FILES['files']['name'] as $i=>$n){ $tmp=$_FILES['files']['tmp_name'][$i]; if(!$tmp) continue; $orig=basename($n); $fname=cuid().'_'.preg_replace('/[^a-zA-Z0-9._-]/','_',$orig); move_uploaded_file($tmp,$dir.$fname); $files[]=['original'=>$orig,'path'=>'uploads/public/'.$id.'/'.$fname]; } }
      if(empty($files)) $err='Au moins un fichier requis.';
      else {
        $dossierId=null;
        // create dossier in first service of entity
        $svc=$pdo->prepare("SELECT s.id FROM services s JOIN divisions d ON d.id=s.divisionId JOIN organizations o ON o.id=d.organizationId WHERE o.entityId=? LIMIT 1"); $svc->execute([$entityId]); $svcId=$svc->fetchColumn();
        if($svcId){
          $dossierId=cuid(); $ref='DEP-'.date('Ymd').'-'.substr($dossierId,-4);
          $pdo->prepare("INSERT INTO dossiers (id,title,description,reference,status,serviceId,categoryId,genreId,dossierTypeId,priorityId,confidentialityId,creatorId) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")->execute([$dossierId,$title,'Dépôt public '.$email,$ref,'NOUVEAU',$svcId,$pdo->query("SELECT id FROM categories LIMIT 1")->fetchColumn(),$pdo->query("SELECT id FROM genres LIMIT 1")->fetchColumn(),$pdo->query("SELECT id FROM dossier_types LIMIT 1")->fetchColumn(),$pdo->query("SELECT id FROM priorities LIMIT 1")->fetchColumn(),$pdo->query("SELECT id FROM confidentiality_levels LIMIT 1")->fetchColumn(),'system']);
          foreach($files as $f){ $pdo->prepare("INSERT INTO documents (id,fileName,originalName,mimeType,fileSize,filePath,dossierId) VALUES (?,?,?,?,?,?,?)")->execute([cuid(), basename($f['path']), $f['original'], 'application/octet-stream', 0, $f['path'], $dossierId]); }
        }
        $pdo->prepare("INSERT INTO public_deposits (id,entityId,email,phone,title,files,ip_address,dossierId) VALUES (?,?,?,?,?,?,?,?)")->execute([$id,$entityId,$email,$phone,$title, json_encode($files), $_SERVER['REMOTE_ADDR']??'', $dossierId]);
        $msg='Dépôt reçu — référence '.($dossierId? $ref : $id).'. Vous recevrez un accusé.';
      }
    }
  }
}
$entities=$pdo->query("SELECT id,name,code FROM entities WHERE isActive=1 ORDER BY name")->fetchAll();
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dépôt public — Doxera+</title><script src="https://cdn.tailwindcss.com"></script></head><body class="bg-slate-50">
<div class="max-w-2xl mx-auto p-6">
  <h1 class="text-2xl font-bold">Dépôt public</h1><p class="text-sm text-slate-500 mb-4">Déposez vos pièces — sans compte. Traitement < 24h.</p>
  <?php if($msg): ?><div class="bg-emerald-50 border border-emerald-200 text-emerald-700 p-3 rounded-xl text-sm mb-3"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if($err): ?><div class="bg-red-50 border border-red-200 text-red-700 p-3 rounded-xl text-sm mb-3"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <form method="POST" enctype="multipart/form-data" class="bg-white card p-6 space-y-3">
    <label class="block text-sm font-medium">Entité destinataire *</label><select name="entityId" required class="input w-full border rounded-xl px-3 py-2"><option value="">— Choisir —</option><?php foreach($entities as $e): ?><option value="<?= htmlspecialchars($e['id']) ?>"><?= htmlspecialchars($e['name']) ?> (<?= htmlspecialchars($e['code']) ?>)</option><?php endforeach; ?></select>
    <label class="block text-sm font-medium">Objet *</label><input name="title" required class="input w-full border rounded-xl px-3 py-2">
    <div class="grid grid-cols-2 gap-3"><div><label class="block text-sm font-medium">Email *</label><input name="email" type="email" required class="input w-full border rounded-xl px-3 py-2"></div><div><label class="block text-sm font-medium">Téléphone</label><input name="phone" class="input w-full border rounded-xl px-3 py-2"></div></div>
    <label class="block text-sm font-medium">Fichiers * (multi)</label><input type="file" name="files[]" multiple required class="input w-full border rounded-xl px-3 py-2">
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" required> J'accepte le traitement de mes données</label>
    <button class="w-full bg-blue-600 text-white py-2.5 rounded-xl">Envoyer le dépôt</button>
  </form>
</div></body></html>
