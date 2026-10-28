<?php
$pageTitle='Nouveau dossier';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';
$pdo=db();

$cats=$pdo->query("SELECT * FROM categories WHERE isActive=1 ORDER BY name")->fetchAll();
$genres=$pdo->query("SELECT * FROM genres WHERE isActive=1 ORDER BY name")->fetchAll();
$types=$pdo->query("SELECT * FROM dossier_types WHERE isActive=1 ORDER BY name")->fetchAll();
$priorities=$pdo->query("SELECT * FROM priorities ORDER BY level")->fetchAll();
$confLevels=$pdo->query("SELECT * FROM confidentiality_levels ORDER BY level")->fetchAll();
$visLevels=$pdo->query("SELECT * FROM visibility_levels ORDER BY level")->fetchAll();
$services=$pdo->query("SELECT * FROM services WHERE isActive=1 ORDER BY name")->fetchAll();

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
    if(!$title || !$serviceId || !$categoryId || !$genreId || !$dossierTypeId || !$priorityId || !$confidentialityId){
        $err='Veuillez remplir tous les champs obligatoires.';
    } else {
        $id=cuid();
        $ref=generateReference($pdo);
        $deadline = $deadline ? date('Y-m-d H:i:s', strtotime($deadline)) : null;
        $visibilityId = $visibilityId ?: null;
        $stmt=$pdo->prepare("INSERT INTO dossiers (id,title,description,reference,status,deadline,serviceId,categoryId,genreId,dossierTypeId,priorityId,confidentialityId,visibilityId,creatorId) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$id,$title,$description,$ref,'NOUVEAU',$deadline,$serviceId,$categoryId,$genreId,$dossierTypeId,$priorityId,$confidentialityId,$visibilityId, currentUser()['id']]);
        logAction($pdo,$id,'CREATION',"Dossier $ref créé");
        // Gestion upload multiple
        if(!empty($_FILES['documents']['name'][0])){
            $uploadDir= __DIR__ . '/../../uploads/'.$id.'/';
            if(!is_dir($uploadDir)) mkdir($uploadDir,0775,true);
            foreach($_FILES['documents']['name'] as $i=>$name){
                $tmp=$_FILES['documents']['tmp_name'][$i];
                if(!$tmp) continue;
                $orig=basename($name);
                $fname=cuid().'_'.preg_replace('/[^a-zA-Z0-9._-]/','_',$orig);
                $dest=$uploadDir.$fname;
                if(move_uploaded_file($tmp,$dest)){
                    $docId=cuid();
                    $pdo->prepare("INSERT INTO documents (id,fileName,originalName,mimeType,fileSize,filePath,dossierId,uploadedById) VALUES (?,?,?,?,?,?,?,?)")
                        ->execute([$docId,$fname,$orig, mime_content_type($dest) ?: 'application/octet-stream', filesize($dest), 'uploads/'.$id.'/'.$fname, $id, currentUser()['id']]);
                }
            }
        }
        redirect(BASE_URL.'/dashboard/dossiers/view.php?id='.$id);
    }
}
?>
<h1 class="text-xl font-bold mb-4">Nouveau dossier</h1>
<?php if($err): ?><div class="bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded text-sm mb-4"><?= e($err) ?></div><?php endif; ?>
<form method="POST" enctype="multipart/form-data" class="card p-6 space-y-4">
  <div class="grid md:grid-cols-2 gap-4">
    <div class="md:col-span-2"><label class="block text-sm font-medium mb-1">Titre *</label><input name="title" required class="input" placeholder="Objet du dossier"></div>
    <div class="md:col-span-2"><label class="block text-sm font-medium mb-1">Description</label><textarea name="description" rows="3" class="input"></textarea></div>
    <div><label class="block text-sm font-medium mb-1">Service *</label><select name="serviceId" required class="input"><option value="">-- Choisir --</option><?php foreach($services as $s): ?><option value="<?= e($s['id']) ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
    <div><label class="block text-sm font-medium mb-1">Catégorie *</label><select name="categoryId" required class="input"><option value="">--</option><?php foreach($cats as $c): ?><option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <div><label class="block text-sm font-medium mb-1">Genre *</label><select name="genreId" required class="input"><option value="">--</option><?php foreach($genres as $g): ?><option value="<?= e($g['id']) ?>"><?= e($g['name']) ?></option><?php endforeach; ?></select></div>
    <div><label class="block text-sm font-medium mb-1">Type *</label><select name="dossierTypeId" required class="input"><option value="">--</option><?php foreach($types as $t): ?><option value="<?= e($t['id']) ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
    <div><label class="block text-sm font-medium mb-1">Priorité *</label><select name="priorityId" required class="input"><?php foreach($priorities as $p): ?><option value="<?= e($p['id']) ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
    <div><label class="block text-sm font-medium mb-1">Confidentialité *</label><select name="confidentialityId" required class="input"><?php foreach($confLevels as $l): ?><option value="<?= e($l['id']) ?>"><?= e($l['name']) ?> (niv. <?= $l['level'] ?>)</option><?php endforeach; ?></select></div>
    <div><label class="block text-sm font-medium mb-1">Visibilité</label><select name="visibilityId" class="input"><option value="">-- Par défaut --</option><?php foreach($visLevels as $v): ?><option value="<?= e($v['id']) ?>"><?= e($v['name']) ?></option><?php endforeach; ?></select></div>
    <div><label class="block text-sm font-medium mb-1">Échéance</label><input type="date" name="deadline" class="input"></div>
    <div class="md:col-span-2"><label class="block text-sm font-medium mb-1">Documents (multi)</label><input type="file" name="documents[]" multiple class="input"></div>
  </div>
  <div class="flex gap-2"><button type="submit" class="btn-primary">Créer le dossier</button><a href="index.php" class="border px-4 py-2 rounded-lg text-sm">Annuler</a></div>
</form>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
