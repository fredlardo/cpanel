<?php
$pageTitle='Paramétrage';
require_once __DIR__ . '/../../includes/header.php';
$pdo=db();
if(!isAdmin()){ echo '<div class="card p-10 text-center">Réservé ADMIN</div>'; require_once __DIR__ . '/../../includes/footer.php'; exit; }
$tab=$_GET['tab']??'categories';
$allowed=['categories','genres','types','priorities','confidentiality','visibility','roles','tags','retentions','metadata'];
if(!in_array($tab,$allowed)) $tab='categories';
$msg=''; $err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'';
  try{
    if($tab==='categories'){
      if($action==='create') $pdo->prepare("INSERT INTO categories (id,code,name,description,isActive) VALUES (?,?,?,?,?)")->execute([cuid(),trim($_POST['code']),trim($_POST['name']),trim($_POST['description']),isset($_POST['isActive'])?1:0]);
      if($action==='update') $pdo->prepare("UPDATE categories SET code=?,name=?,description=?,isActive=? WHERE id=?")->execute([trim($_POST['code']),trim($_POST['name']),trim($_POST['description']),isset($_POST['isActive'])?1:0,$_POST['id']]);
      if($action==='delete') $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$_POST['id']]);
    }
    if($tab==='genres'){
      if($action==='create') $pdo->prepare("INSERT INTO genres (id,code,name,description,isActive) VALUES (?,?,?,?,?)")->execute([cuid(),trim($_POST['code']),trim($_POST['name']),trim($_POST['description']),isset($_POST['isActive'])?1:0]);
      if($action==='update') $pdo->prepare("UPDATE genres SET code=?,name=?,description=?,isActive=? WHERE id=?")->execute([trim($_POST['code']),trim($_POST['name']),trim($_POST['description']),isset($_POST['isActive'])?1:0,$_POST['id']]);
      if($action==='delete') $pdo->prepare("DELETE FROM genres WHERE id=?")->execute([$_POST['id']]);
    }
    if($tab==='types'){
      if($action==='create') $pdo->prepare("INSERT INTO dossier_types (id,code,name,description,isActive) VALUES (?,?,?,?,?)")->execute([cuid(),trim($_POST['code']),trim($_POST['name']),trim($_POST['description']),isset($_POST['isActive'])?1:0]);
      if($action==='update') $pdo->prepare("UPDATE dossier_types SET code=?,name=?,description=?,isActive=? WHERE id=?")->execute([trim($_POST['code']),trim($_POST['name']),trim($_POST['description']),isset($_POST['isActive'])?1:0,$_POST['id']]);
      if($action==='delete') $pdo->prepare("DELETE FROM dossier_types WHERE id=?")->execute([$_POST['id']]);
    }
    if($tab==='priorities'){
      if($action==='create') $pdo->prepare("INSERT INTO priorities (id,code,name,level,color,description,isActive) VALUES (?,?,?,?,?,?,?)")->execute([cuid(),trim($_POST['code']),trim($_POST['name']),(int)$_POST['level'],trim($_POST['color']),trim($_POST['description']),isset($_POST['isActive'])?1:0]);
      if($action==='update') $pdo->prepare("UPDATE priorities SET code=?,name=?,level=?,color=?,description=?,isActive=? WHERE id=?")->execute([trim($_POST['code']),trim($_POST['name']),(int)$_POST['level'],trim($_POST['color']),trim($_POST['description']),isset($_POST['isActive'])?1:0,$_POST['id']]);
      if($action==='delete') $pdo->prepare("DELETE FROM priorities WHERE id=?")->execute([$_POST['id']]);
    }
    if($tab==='confidentiality'){
      if($action==='create') $pdo->prepare("INSERT INTO confidentiality_levels (id,level,name,description,color,isActive) VALUES (?,?,?,?,?,?)")->execute([cuid(),(int)$_POST['level'],trim($_POST['name']),trim($_POST['description']),trim($_POST['color']),isset($_POST['isActive'])?1:0]);
      if($action==='update') $pdo->prepare("UPDATE confidentiality_levels SET level=?,name=?,description=?,color=?,isActive=? WHERE id=?")->execute([(int)$_POST['level'],trim($_POST['name']),trim($_POST['description']),trim($_POST['color']),isset($_POST['isActive'])?1:0,$_POST['id']]);
      if($action==='delete') $pdo->prepare("DELETE FROM confidentiality_levels WHERE id=?")->execute([$_POST['id']]);
    }
    if($tab==='visibility'){
      if($action==='create') $pdo->prepare("INSERT INTO visibility_levels (id,level,name,description,isActive) VALUES (?,?,?,?,?)")->execute([cuid(),(int)$_POST['level'],trim($_POST['name']),trim($_POST['description']),isset($_POST['isActive'])?1:0]);
      if($action==='update') $pdo->prepare("UPDATE visibility_levels SET level=?,name=?,description=?,isActive=? WHERE id=?")->execute([(int)$_POST['level'],trim($_POST['name']),trim($_POST['description']),isset($_POST['isActive'])?1:0,$_POST['id']]);
      if($action==='delete') $pdo->prepare("DELETE FROM visibility_levels WHERE id=?")->execute([$_POST['id']]);
    }
    if($tab==='roles'){
      if($action==='create') $pdo->prepare("INSERT INTO person_roles (id,code,name,description,isActive) VALUES (?,?,?,?,?)")->execute([cuid(),trim($_POST['code']),trim($_POST['name']),trim($_POST['description']),isset($_POST['isActive'])?1:0]);
      if($action==='update') $pdo->prepare("UPDATE person_roles SET code=?,name=?,description=?,isActive=? WHERE id=?")->execute([trim($_POST['code']),trim($_POST['name']),trim($_POST['description']),isset($_POST['isActive'])?1:0,$_POST['id']]);
      if($action==='delete') $pdo->prepare("DELETE FROM person_roles WHERE id=?")->execute([$_POST['id']]);
    }
    if($tab==='tags'){
      if($action==='create') $pdo->prepare("INSERT INTO tags (id,code,name,color) VALUES (?,?,?,?)")->execute([cuid(),trim($_POST['code']),trim($_POST['name']),trim($_POST['color'])]);
      if($action==='update') $pdo->prepare("UPDATE tags SET code=?,name=?,color=? WHERE id=?")->execute([trim($_POST['code']),trim($_POST['name']),trim($_POST['color']),$_POST['id']]);
      if($action==='delete') $pdo->prepare("DELETE FROM tags WHERE id=?")->execute([$_POST['id']]);
    }
    if($tab==='retentions'){
      if($action==='create') $pdo->prepare("INSERT INTO retention_policies (id,code,name,durationDays,action,isActive) VALUES (?,?,?,?,?,?)")->execute([cuid(),trim($_POST['code']),trim($_POST['name']),(int)$_POST['durationDays'],$_POST['action'],isset($_POST['isActive'])?1:0]);
      if($action==='update') $pdo->prepare("UPDATE retention_policies SET code=?,name=?,durationDays=?,action=?,isActive=? WHERE id=?")->execute([trim($_POST['code']),trim($_POST['name']),(int)$_POST['durationDays'],$_POST['action'],isset($_POST['isActive'])?1:0,$_POST['id']]);
      if($action==='delete') $pdo->prepare("DELETE FROM retention_policies WHERE id=?")->execute([$_POST['id']]);
    }
    if($tab==='metadata'){
      if($action==='create') $pdo->prepare("INSERT INTO metadata_fields (id,code,name,type,isRequired,isActive) VALUES (?,?,?,?,?,?)")->execute([cuid(),trim($_POST['code']),trim($_POST['name']),$_POST['type'],isset($_POST['isRequired'])?1:0,isset($_POST['isActive'])?1:0]);
      if($action==='update') $pdo->prepare("UPDATE metadata_fields SET code=?,name=?,type=?,isRequired=?,isActive=? WHERE id=?")->execute([trim($_POST['code']),trim($_POST['name']),$_POST['type'],isset($_POST['isRequired'])?1:0,isset($_POST['isActive'])?1:0,$_POST['id']]);
      if($action==='delete') $pdo->prepare("DELETE FROM metadata_fields WHERE id=?")->execute([$_POST['id']]);
    }
    $msg='Opération réussie.';
  }catch(PDOException $e){ $err=$e->getMessage(); if(str_contains($err,'Duplicate')) $err='Code/niveau déjà utilisé.'; if(str_contains($err,'foreign')) $err='Élément lié à des dossiers — impossible de supprimer.'; }
}
// lists
$data=[];
$data['categories']=$pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$data['genres']=$pdo->query("SELECT * FROM genres ORDER BY name")->fetchAll();
$data['types']=$pdo->query("SELECT * FROM dossier_types ORDER BY name")->fetchAll();
$data['priorities']=$pdo->query("SELECT * FROM priorities ORDER BY level")->fetchAll();
$data['confidentiality']=$pdo->query("SELECT * FROM confidentiality_levels ORDER BY level")->fetchAll();
$data['visibility']=$pdo->query("SELECT * FROM visibility_levels ORDER BY level")->fetchAll();
$data['roles']=$pdo->query("SELECT * FROM person_roles ORDER BY name")->fetchAll();
$data['tags']=$pdo->query("SELECT * FROM tags ORDER BY name")->fetchAll();
$data['retentions']=$pdo->query("SELECT * FROM retention_policies ORDER BY durationDays")->fetchAll();
$data['metadata']=$pdo->query("SELECT * FROM metadata_fields ORDER BY name")->fetchAll();

$edit=null; $view=$_GET['view']??'';
if($view==='edit' && isset($_GET['id'])){
  $id=$_GET['id'];
  $map=['categories'=>'categories','genres'=>'genres','types'=>'dossier_types','priorities'=>'priorities','confidentiality'=>'confidentiality_levels','visibility'=>'visibility_levels','roles'=>'person_roles','tags'=>'tags','retentions'=>'retention_policies','metadata'=>'metadata_fields'];
  $tbl=$map[$tab]??'categories';
  $st=$pdo->prepare("SELECT * FROM `$tbl` WHERE id=?"); $st->execute([$id]); $edit=$st->fetch();
}
$isForm=$view==='create'||$view==='edit';
function ptab($cur,$id,$label,$cnt){ $a=$cur===$id?'bg-slate-900 text-white':'bg-white border hover:bg-slate-50 text-slate-600'; return '<a href="?tab='.$id.'" class="px-3 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap '.$a.'">'.$label.'<span class="opacity-60"> '.$cnt.'</span></a>'; }
?>
<div class="space-y-4">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div><h1 class="text-xl font-extrabold text-slate-900">Paramétrage</h1><p class="text-sm text-slate-500">Tables de référence — catégories, genres, types, priorités, confidentialité...</p></div>
    <?php if(!$isForm): ?><a href="?tab=<?= e($tab) ?>&view=create" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-xl inline-flex items-center gap-2"><i data-lucide="plus" class="w-4 h-4"></i> Nouveau</a><?php endif; ?>
  </div>
  <?php if($msg): ?><div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-2.5 rounded-xl text-sm"><?= e($msg) ?></div><?php endif; ?>
  <?php if($err): ?><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-2.5 rounded-xl text-sm"><?= e($err) ?></div><?php endif; ?>
  <div class="flex gap-1.5 overflow-x-auto pb-1">
    <?= ptab($tab,'categories','Catégories',count($data['categories'])) ?>
    <?= ptab($tab,'genres','Genres',count($data['genres'])) ?>
    <?= ptab($tab,'types','Types',count($data['types'])) ?>
    <?= ptab($tab,'priorities','Priorités',count($data['priorities'])) ?>
    <?= ptab($tab,'confidentiality','Confidentialités',count($data['confidentiality'])) ?>
    <?= ptab($tab,'visibility','Visibilités',count($data['visibility'])) ?>
    <?= ptab($tab,'roles','Rôles personne',count($data['roles'])) ?>
    <?= ptab($tab,'tags','Tags',count($data['tags'])) ?>
    <?= ptab($tab,'retentions','Rétentions',count($data['retentions'])) ?>
    <?= ptab($tab,'metadata','Métadonnées',count($data['metadata'])) ?>
  </div>

  <?php if($isForm): ?>
  <div class="card p-6">
    <div class="flex items-center justify-between mb-4"><h2 class="font-bold"><?= $edit?'Modifier':'Nouveau' ?> — <?= e($tab) ?></h2><a href="?tab=<?= e($tab) ?>" class="text-sm text-slate-500">← Retour</a></div>
    <form method="POST" class="grid md:grid-cols-2 gap-4">
      <input type="hidden" name="action" value="<?= $edit?'update':'create' ?>">
      <?php if($edit): ?><input type="hidden" name="id" value="<?= e($edit['id']) ?>"><?php endif; ?>
      <?php if(in_array($tab,['categories','genres','types','priorities','roles','tags','retentions','metadata'])): ?>
        <div><label class="text-sm font-medium">Code *</label><input name="code" required value="<?= e($edit['code']??'') ?>" class="input mt-1"></div>
      <?php endif; ?>
      <div><label class="text-sm font-medium">Nom *</label><input name="name" required value="<?= e($edit['name']??'') ?>" class="input mt-1"></div>
      <?php if(in_array($tab,['priorities','confidentiality','visibility','retentions'])): ?><div><label class="text-sm font-medium"><?= $tab==='retentions'?'Durée (jours)':'Niveau' ?> *</label><input type="number" name="<?= $tab==='retentions'?'durationDays':'level' ?>" required value="<?= e($edit[$tab==='retentions'?'durationDays':'level']??($tab==='retentions'?1825:1)) ?>" class="input mt-1"></div><?php endif; ?>
      <?php if($tab==='retentions'): ?><div><label class="text-sm font-medium">Action</label><select name="action" class="input mt-1"><option value="ARCHIVER" <?= ($edit['action']??'')==='ARCHIVER'?'selected':'' ?>>ARCHIVER</option><option value="DETRUIRE" <?= ($edit['action']??'')==='DETRUIRE'?'selected':'' ?>>DETRUIRE</option><option value="TRANSFERER" <?= ($edit['action']??'')==='TRANSFERER'?'selected':'' ?>>TRANSFERER</option></select></div><?php endif; ?>
      <?php if($tab==='metadata'): ?><div><label class="text-sm font-medium">Type</label><select name="type" class="input mt-1"><option value="text" <?= ($edit['type']??'')==='text'?'selected':'' ?>>text</option><option value="number" <?= ($edit['type']??'')==='number'?'selected':'' ?>>number</option><option value="date" <?= ($edit['type']??'')==='date'?'selected':'' ?>>date</option><option value="select" <?= ($edit['type']??'')==='select'?'selected':'' ?>>select</option></select></div><div class="flex items-center gap-2 pt-6"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="isRequired" value="1" <?= ($edit? (int)$edit['isRequired']:0)?'checked':'' ?>> Requis</label></div><?php endif; ?>
      <?php if(in_array($tab,['priorities','confidentiality','tags'])): ?><div><label class="text-sm font-medium">Couleur</label><input type="color" name="color" value="<?= e($edit['color']??'#3b82f6') ?>" class="input mt-1 h-10 p-1"></div><?php endif; ?>
      <?php if(!in_array($tab,['tags','retentions','metadata'])): ?><div class="md:col-span-2"><label class="text-sm font-medium">Description</label><textarea name="description" rows="2" class="input mt-1"><?= e($edit['description']??'') ?></textarea></div><?php endif; ?>
      <div class="flex items-center gap-2 pt-2"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="isActive" value="1" <?= ($edit? (int)$edit['isActive']:1)?'checked':'' ?>> Actif</label></div>
      <div class="md:col-span-2 flex gap-2"><button class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-xl text-sm"><?= $edit?'Enregistrer':'Créer' ?></button><a href="?tab=<?= e($tab) ?>" class="border px-6 py-2 rounded-xl text-sm">Annuler</a></div>
    </form>
  </div>
  <?php else: ?>
    <?php if(in_array($tab,['categories','genres','types','roles'])): ?>
      <div class="card overflow-hidden"><table class="w-full text-sm"><thead><tr class="bg-slate-50 border-b text-slate-500 text-left"><th class="px-4 py-3">Code</th><th class="px-4 py-3">Nom</th><th class="px-4 py-3">Description</th><th class="px-4 py-3">Statut</th><th class="px-4 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y"><?php foreach($data[$tab] as $r): ?><tr class="hover:bg-slate-50/50"><td class="px-4 py-3 font-mono text-xs"><?= e($r['code']) ?></td><td class="px-4 py-3 font-medium"><?= e($r['name']) ?></td><td class="px-4 py-3 text-slate-500 truncate max-w-[300px]"><?= e($r['description']??'—') ?></td><td class="px-4 py-3"><?= (int)$r['isActive']?'<span class="text-emerald-600 text-xs">Actif</span>':'<span class="text-slate-400 text-xs">Inactif</span>' ?></td><td class="px-4 py-3 flex justify-end gap-1"><a href="?tab=<?= e($tab) ?>&view=edit&id=<?= e($r['id']) ?>" class="h-7 w-7 inline-flex items-center justify-center rounded-lg border bg-slate-50"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></a><form method="POST" onsubmit="return confirm('Supprimer ?')" class="inline"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($r['id']) ?>"><button class="h-7 w-7 inline-flex items-center justify-center rounded-lg border text-red-600 bg-slate-50"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button></form></td></tr><?php endforeach; ?></tbody></table></div>
    <?php elseif($tab==='priorities'): ?>
      <div class="card overflow-hidden"><table class="w-full text-sm"><thead><tr class="bg-slate-50 border-b text-slate-500 text-left"><th class="px-4 py-3">Code</th><th class="px-4 py-3">Nom</th><th class="px-4 py-3">Niveau</th><th class="px-4 py-3">Couleur</th><th class="px-4 py-3">Statut</th><th class="px-4 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y"><?php foreach($data['priorities'] as $r): ?><tr class="hover:bg-slate-50/50"><td class="px-4 py-3 font-mono text-xs"><?= e($r['code']) ?></td><td class="px-4 py-3 font-medium"><?= e($r['name']) ?></td><td class="px-4 py-3"><?= e($r['level']) ?></td><td class="px-4 py-3"><span class="inline-flex items-center gap-2"><span class="h-4 w-4 rounded-full border" style="background:<?= e($r['color']) ?>"></span><?= e($r['color']) ?></span></td><td class="px-4 py-3"><?= (int)$r['isActive']?'<span class="text-emerald-600 text-xs">Actif</span>':'<span class="text-slate-400 text-xs">Inactif</span>' ?></td><td class="px-4 py-3 flex justify-end gap-1"><a href="?tab=priorities&view=edit&id=<?= e($r['id']) ?>" class="h-7 w-7 inline-flex items-center justify-center rounded-lg border bg-slate-50"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></a><form method="POST" onsubmit="return confirm('Supprimer ?')" class="inline"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($r['id']) ?>"><button class="h-7 w-7 inline-flex items-center justify-center rounded-lg border text-red-600 bg-slate-50"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button></form></td></tr><?php endforeach; ?></tbody></table></div>
    <?php elseif($tab==='confidentiality'): ?>
      <div class="card overflow-hidden"><table class="w-full text-sm"><thead><tr class="bg-slate-50 border-b text-slate-500 text-left"><th class="px-4 py-3">Niveau</th><th class="px-4 py-3">Nom</th><th class="px-4 py-3">Couleur</th><th class="px-4 py-3">Statut</th><th class="px-4 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y"><?php foreach($data['confidentiality'] as $r): ?><tr class="hover:bg-slate-50/50"><td class="px-4 py-3 font-mono"><?= e($r['level']) ?></td><td class="px-4 py-3 font-medium"><?= e($r['name']) ?></td><td class="px-4 py-3"><span class="h-4 w-4 rounded-full inline-block border" style="background:<?= e($r['color']) ?>"></span> <?= e($r['color']) ?></td><td class="px-4 py-3"><?= (int)$r['isActive']?'<span class="text-emerald-600 text-xs">Actif</span>':'<span class="text-slate-400 text-xs">Inactif</span>' ?></td><td class="px-4 py-3 flex justify-end gap-1"><a href="?tab=confidentiality&view=edit&id=<?= e($r['id']) ?>" class="h-7 w-7 inline-flex items-center justify-center rounded-lg border bg-slate-50"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></a><form method="POST" onsubmit="return confirm('Supprimer ?')" class="inline"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($r['id']) ?>"><button class="h-7 w-7 inline-flex items-center justify-center rounded-lg border text-red-600 bg-slate-50"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button></form></td></tr><?php endforeach; ?></tbody></table></div>
    <?php elseif($tab==='visibility'): ?>
      <div class="card overflow-hidden"><table class="w-full text-sm"><thead><tr class="bg-slate-50 border-b text-slate-500 text-left"><th class="px-4 py-3">Niveau</th><th class="px-4 py-3">Nom</th><th class="px-4 py-3">Description</th><th class="px-4 py-3">Statut</th><th class="px-4 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y"><?php foreach($data['visibility'] as $r): ?><tr class="hover:bg-slate-50/50"><td class="px-4 py-3 font-mono"><?= e($r['level']) ?></td><td class="px-4 py-3 font-medium"><?= e($r['name']) ?></td><td class="px-4 py-3 text-slate-500"><?= e($r['description']??'—') ?></td><td class="px-4 py-3"><?= (int)$r['isActive']?'<span class="text-emerald-600 text-xs">Actif</span>':'<span class="text-slate-400 text-xs">Inactif</span>' ?></td><td class="px-4 py-3 flex justify-end gap-1"><a href="?tab=visibility&view=edit&id=<?= e($r['id']) ?>" class="h-7 w-7 inline-flex items-center justify-center rounded-lg border bg-slate-50"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></a><form method="POST" onsubmit="return confirm('Supprimer ?')" class="inline"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($r['id']) ?>"><button class="h-7 w-7 inline-flex items-center justify-center rounded-lg border text-red-600 bg-slate-50"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button></form></td></tr><?php endforeach; ?></tbody></table></div>
    <?php elseif($tab==='tags'): ?>
      <div class="card overflow-hidden"><table class="w-full text-sm"><thead><tr class="bg-slate-50 border-b text-slate-500 text-left"><th class="px-4 py-3">Code</th><th class="px-4 py-3">Nom</th><th class="px-4 py-3">Couleur</th><th class="px-4 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y"><?php foreach($data['tags'] as $r): ?><tr class="hover:bg-slate-50/50"><td class="px-4 py-3 font-mono text-xs"><?= e($r['code']) ?></td><td class="px-4 py-3 font-medium flex items-center gap-2"><span class="h-3 w-3 rounded-full" style="background:<?= e($r['color']) ?>"></span><?= e($r['name']) ?></td><td class="px-4 py-3"><?= e($r['color']) ?></td><td class="px-4 py-3 flex justify-end gap-1"><a href="?tab=tags&view=edit&id=<?= e($r['id']) ?>" class="h-7 w-7 inline-flex items-center justify-center rounded-lg border bg-slate-50"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></a><form method="POST" onsubmit="return confirm('Supprimer ?')" class="inline"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($r['id']) ?>"><button class="h-7 w-7 inline-flex items-center justify-center rounded-lg border text-red-600 bg-slate-50"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button></form></td></tr><?php endforeach; ?></tbody></table></div>
    <?php elseif($tab==='retentions'): ?>
      <div class="card overflow-hidden"><table class="w-full text-sm"><thead><tr class="bg-slate-50 border-b text-slate-500 text-left"><th class="px-4 py-3">Code</th><th class="px-4 py-3">Nom</th><th class="px-4 py-3">Durée</th><th class="px-4 py-3">Action</th><th class="px-4 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y"><?php foreach($data['retentions'] as $r): ?><tr class="hover:bg-slate-50/50"><td class="px-4 py-3 font-mono text-xs"><?= e($r['code']) ?></td><td class="px-4 py-3 font-medium"><?= e($r['name']) ?></td><td class="px-4 py-3"><?= (int)$r['durationDays'] ?> j (<?= round($r['durationDays']/365,1) ?> ans)</td><td class="px-4 py-3"><?= e($r['action']) ?></td><td class="px-4 py-3 flex justify-end gap-1"><a href="?tab=retentions&view=edit&id=<?= e($r['id']) ?>" class="h-7 w-7 inline-flex items-center justify-center rounded-lg border bg-slate-50"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></a><form method="POST" onsubmit="return confirm('Supprimer ?')" class="inline"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($r['id']) ?>"><button class="h-7 w-7 inline-flex items-center justify-center rounded-lg border text-red-600 bg-slate-50"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button></form></td></tr><?php endforeach; ?></tbody></table></div>
    <?php else: // metadata ?>
      <div class="card overflow-hidden"><table class="w-full text-sm"><thead><tr class="bg-slate-50 border-b text-slate-500 text-left"><th class="px-4 py-3">Code</th><th class="px-4 py-3">Nom</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Requis</th><th class="px-4 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y"><?php foreach($data['metadata'] as $r): ?><tr class="hover:bg-slate-50/50"><td class="px-4 py-3 font-mono text-xs"><?= e($r['code']) ?></td><td class="px-4 py-3 font-medium"><?= e($r['name']) ?></td><td class="px-4 py-3"><?= e($r['type']) ?></td><td class="px-4 py-3"><?= (int)$r['isRequired']?'Oui':'Non' ?></td><td class="px-4 py-3 flex justify-end gap-1"><a href="?tab=metadata&view=edit&id=<?= e($r['id']) ?>" class="h-7 w-7 inline-flex items-center justify-center rounded-lg border bg-slate-50"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></a><form method="POST" onsubmit="return confirm('Supprimer ?')" class="inline"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($r['id']) ?>"><button class="h-7 w-7 inline-flex items-center justify-center rounded-lg border text-red-600 bg-slate-50"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button></form></td></tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
