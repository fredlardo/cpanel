<?php
$pageTitle='Nouveau dossier';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/licence.php';
$pdo=db(); licenceEnsureTables($pdo);
requireLogin();
$cu=currentUser(); $eidChk=getUserEntityId($pdo,$cu);
if($eidChk && licenceIsBlocked($pdo,$eidChk)){
  $_SESSION['flash_error']='Création bloquée : licence '.licenceStatus($pdo,$eidChk)['status'].' — contactez l\'administrateur.';
  redirect(BASE_URL.'/dashboard/ma-licence.php');
}
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';
$cats=$pdo->query("SELECT * FROM categories WHERE isActive=1 ORDER BY name")->fetchAll();
$genres=$pdo->query("SELECT * FROM genres WHERE isActive=1 ORDER BY name")->fetchAll();
$types=$pdo->query("SELECT * FROM dossier_types WHERE isActive=1 ORDER BY name")->fetchAll();
$priorities=$pdo->query("SELECT * FROM priorities ORDER BY level")->fetchAll();
$confLevels=$pdo->query("SELECT * FROM confidentiality_levels ORDER BY level")->fetchAll();
$visLevels=$pdo->query("SELECT * FROM visibility_levels ORDER BY level")->fetchAll();
$services=$pdo->query("SELECT s.*, e.name as entName FROM services s LEFT JOIN divisions d ON d.id=s.divisionId LEFT JOIN organizations o ON o.id=d.organizationId LEFT JOIN entities e ON e.id=o.entityId WHERE s.isActive=1 ORDER BY e.name,s.name")->fetchAll();
$tags=$pdo->query("SELECT * FROM tags ORDER BY name")->fetchAll();
$metaFields=$pdo->query("SELECT * FROM metadata_fields WHERE isActive=1 ORDER BY name")->fetchAll();
$parents=$pdo->query("SELECT id,reference,title FROM dossiers WHERE deletedAt IS NULL AND archivedAt IS NULL ORDER BY createdAt DESC LIMIT 100")->fetchAll();
$msg=''; $err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $title=trim($_POST['title']??'');
    $description=trim($_POST['description']??'');
    $serviceId=$_POST['serviceId']??'';
    $categoryId=$_POST['categoryId']??'';
    $genreId=$_POST['genreId']??'';
    $dossierTypeId=$_POST['dossierTypeId']??'';
    $priorityId=$_POST['priorityId']??'';
    $confidentialityId=$_POST['confidentialityId']??'';
    $visibilityId=$_POST['visibilityId']?? null;
    $deadline=$_POST['deadline']?? null;
    $parentId=$_POST['parentId']?? null; if($parentId==='') $parentId=null;
    $tagIds=$_POST['tagIds']??[];
    if(!$title || !$serviceId || !$categoryId || !$genreId || !$dossierTypeId || !$priorityId || !$confidentialityId){
        $err='Veuillez remplir tous les champs obligatoires.';
    } else {
        $licOk=true;
        try{
          $entForSvc=$pdo->prepare("SELECT e.id FROM entities e JOIN organizations o ON o.entityId=e.id JOIN divisions d ON d.organizationId=o.id JOIN services s ON s.divisionId=d.id WHERE s.id=? LIMIT 1");
          $entForSvc->execute([$serviceId]); $eidLic=$entForSvc->fetchColumn();
          if($eidLic) licenceEnforce($pdo,$eidLic,'write');
          $licChk=getEntityLicence($pdo,$eidLic);
          if($licChk){ $cntD=$pdo->prepare("SELECT COUNT(*) FROM dossiers d JOIN services s2 ON s2.id=d.serviceId JOIN divisions dv2 ON dv2.id=s2.divisionId JOIN organizations o2 ON o2.id=dv2.organizationId WHERE o2.entityId=? AND d.deletedAt IS NULL"); $cntD->execute([$eidLic]); if((int)$cntD->fetchColumn() >= (int)$licChk['max_dossiers']) throw new Exception('Quota dossiers atteint pour cette entité ('.$licChk['max_dossiers'].').'); }
        }catch(Exception $licEx){ if(str_contains($licEx->getMessage(),'Quota')||str_contains($licEx->getMessage(),'Licence')){ $err=$licEx->getMessage(); $licOk=false; } else { $err=$licEx->getMessage(); $licOk=false; } }
        if(!$licOk){ /* skip create */ } else {
        $id=cuid(); $ref=generateReference($pdo);
        $deadline = $deadline ? date('Y-m-d H:i:s', strtotime($deadline)) : null;
        $visibilityId = $visibilityId ?: null;
        $stmt=$pdo->prepare("INSERT INTO dossiers (id,title,description,reference,status,deadline,serviceId,categoryId,genreId,dossierTypeId,priorityId,confidentialityId,visibilityId,parentId,creatorId) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$id,$title,$description,$ref,'NOUVEAU',$deadline,$serviceId,$categoryId,$genreId,$dossierTypeId,$priorityId,$confidentialityId,$visibilityId,$parentId, currentUser()['id']]);
        logAction($pdo,$id,'CREATION',"Dossier $ref créé".($parentId?" (sous-dossier de $parentId)":""));
        // tags
        foreach((array)$tagIds as $tid){ if($tid) $pdo->prepare("INSERT IGNORE INTO dossier_tags (id,dossierId,tagId) VALUES (?,?,?)")->execute([cuid(),$id,$tid]); }
        // metadata
        foreach($metaFields as $f){ $k='meta_'.$f['id']; $v=trim($_POST[$k]??''); if($v!==''){ $pdo->prepare("INSERT INTO dossier_metadata (id,dossierId,fieldId,value) VALUES (?,?,?,?)")->execute([cuid(),$id,$f['id'],$v]); } }
        // docs
        if(!empty($_FILES['documents']['name'][0])){
            $uploadDir= __DIR__ . '/../../uploads/'.$id.'/'; if(!is_dir($uploadDir)) mkdir($uploadDir,0775,true);
            foreach($_FILES['documents']['name'] as $i=>$name){
                $tmp=$_FILES['documents']['tmp_name'][$i]; if(!$tmp) continue;
                $orig=basename($name); $fname=cuid().'_'.preg_replace('/[^a-zA-Z0-9._-]/','_',$orig); $dest=$uploadDir.$fname;
                if(move_uploaded_file($tmp,$dest)){
                    $docId=cuid();
                    $pdo->prepare("INSERT INTO documents (id,fileName,originalName,mimeType,fileSize,filePath,dossierId,uploadedById) VALUES (?,?,?,?,?,?,?,?)")->execute([$docId,$fname,$orig, mime_content_type($dest) ?: 'application/octet-stream', filesize($dest), 'uploads/'.$id.'/'.$fname, $id, currentUser()['id']]);
                }
            }
        }
        redirect(BASE_URL.'/dashboard/dossiers/view.php?id='.$id);
        } // fin licOk
    }
}
?>
<h1 class="text-xl font-extrabold text-slate-900 mb-1">Nouveau dossier</h1>
<p class="text-sm text-slate-500 mb-4">Organisation : plan de classement, dossier parent, tags et métadonnées personnalisées</p>
<?php if($err): ?><div class="bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded-xl text-sm mb-4"><?= e($err) ?></div><?php endif; ?>
<form method="POST" enctype="multipart/form-data" class="space-y-4">
  <div class="card p-6 space-y-4">
    <div class="grid md:grid-cols-2 gap-4">
      <div class="md:col-span-2"><label class="block text-sm font-medium mb-1">Titre *</label><input name="title" required class="input" placeholder="Objet du dossier"></div>
      <div class="md:col-span-2"><label class="block text-sm font-medium mb-1">Description</label><textarea name="description" rows="3" class="input" placeholder="Contexte, enjeux..."></textarea></div>
      <div><label class="block text-sm font-medium mb-1">Service *</label><select name="serviceId" required class="input"><option value="">-- Choisir --</option><?php foreach($services as $s): ?><option value="<?= e($s['id']) ?>"><?= e($s['entName']) ?> › <?= e($s['name']) ?></option><?php endforeach; ?></select></div>
      <div><label class="block text-sm font-medium mb-1">Dossier parent (sous-dossier)</label><select name="parentId" class="input"><option value="">— Racine —</option><?php foreach($parents as $p): ?><option value="<?= e($p['id']) ?>"><?= e($p['reference']) ?> — <?= e($p['title']) ?></option><?php endforeach; ?></select><p class="text-xs text-slate-400">Laisse vide pour dossier principal</p></div>
      <div><label class="block text-sm font-medium mb-1">Catégorie *</label><select name="categoryId" required class="input"><option value="">--</option><?php foreach($cats as $c): ?><option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
      <div><label class="block text-sm font-medium mb-1">Genre *</label><select name="genreId" required class="input"><option value="">--</option><?php foreach($genres as $g): ?><option value="<?= e($g['id']) ?>"><?= e($g['name']) ?></option><?php endforeach; ?></select></div>
      <div><label class="block text-sm font-medium mb-1">Type *</label><select name="dossierTypeId" required class="input"><option value="">--</option><?php foreach($types as $t): ?><option value="<?= e($t['id']) ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
      <div><label class="block text-sm font-medium mb-1">Priorité *</label><select name="priorityId" required class="input"><?php foreach($priorities as $p): ?><option value="<?= e($p['id']) ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
      <div><label class="block text-sm font-medium mb-1">Confidentialité *</label><select name="confidentialityId" required class="input"><?php foreach($confLevels as $l): ?><option value="<?= e($l['id']) ?>"><?= e($l['name']) ?> (niv. <?= $l['level'] ?>)</option><?php endforeach; ?></select></div>
      <div><label class="block text-sm font-medium mb-1">Visibilité</label><select name="visibilityId" class="input"><option value="">-- Par défaut --</option><?php foreach($visLevels as $v): ?><option value="<?= e($v['id']) ?>"><?= e($v['name']) ?></option><?php endforeach; ?></select></div>
      <div><label class="block text-sm font-medium mb-1">Échéance</label><input type="date" name="deadline" class="input"></div>
    </div>
  </div>
  <div class="card p-6">
    <h3 class="font-semibold text-slate-900 flex items-center gap-2"><i data-lucide="tag" class="w-4 h-4"></i> Tags</h3>
    <p class="text-xs text-slate-500 mb-2">Étiquettes transverses pour recherche et organisation</p>
    <div class="flex flex-wrap gap-2">
      <?php foreach($tags as $t): ?><label class="inline-flex items-center gap-2 border rounded-full px-3 py-1.5 text-sm cursor-pointer hover:bg-slate-50" style="border-color:<?= e($t['color']) ?>40"><input type="checkbox" name="tagIds[]" value="<?= e($t['id']) ?>" class="rounded"><span class="h-2 w-2 rounded-full" style="background:<?= e($t['color']) ?>"></span><?= e($t['name']) ?></label><?php endforeach; ?>
    </div>
  </div>
  <div class="card p-6">
    <h3 class="font-semibold text-slate-900 flex items-center gap-2"><i data-lucide="list-plus" class="w-4 h-4"></i> Métadonnées personnalisées</h3>
    <div class="grid md:grid-cols-3 gap-4 mt-3">
      <?php foreach($metaFields as $f): ?>
        <div><label class="block text-sm font-medium mb-1"><?= e($f['name']) ?> <?= $f['isRequired']?'*':'' ?></label>
          <?php if($f['type']==='date'): ?><input type="date" name="meta_<?= e($f['id']) ?>" class="input">
          <?php elseif($f['type']==='number'): ?><input type="number" step="0.01" name="meta_<?= e($f['id']) ?>" class="input" placeholder="0">
          <?php else: ?><input name="meta_<?= e($f['id']) ?>" class="input" placeholder="<?= e($f['code']) ?>"><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="text-xs text-slate-400 mt-2">Gérez les champs dans Paramétrage → Métadonnées</p>
  </div>
  <div class="card p-6">
    <label class="block text-sm font-medium mb-1">Documents (multi) — prévisualisation & OCR automatique à l'upload</label>
    <input type="file" name="documents[]" multiple class="input">
    <p class="text-xs text-slate-400 mt-1">Glissez aussi depuis Acquisition pour un import massif</p>
  </div>
  <div class="flex gap-2"><button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium">Créer le dossier</button><a href="index.php" class="border px-6 py-2.5 rounded-xl text-sm">Annuler</a></div>
</form>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
