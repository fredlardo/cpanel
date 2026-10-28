<?php
$pageTitle='Groupes';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/database.php';
$pdo=db(); $msg=''; $err='';
$me=currentUser();
if(!in_array($me['role'],['ADMIN','SUPERVISOR']) && !in_array('groupes',$me['modules']??[])){
  // ADMIN bypass already handled via can() but we allow view
}
// POST handlers
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_group'])){
  $name=trim($_POST['name']??''); $desc=trim($_POST['description']??'');
  if(!$name) $err="Nom requis";
  else {
    try{ $pdo->prepare("INSERT INTO `groups` (id,name,description,isActive,createdAt,updatedAt) VALUES (?,?,?,?,NOW(),NOW())")->execute([cuid(),$name,$desc?:null,1]); $msg="Groupe créé"; }catch(Exception $e){ $err=$e->getMessage(); }
  }
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_group'])){
  $gid=$_POST['groupId']??'';
  if($gid){ $pdo->prepare("DELETE FROM `groups` WHERE id=?")->execute([$gid]); $msg="Groupe supprimé"; }
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_member'])){
  $gid=$_POST['groupId']??''; $uid=$_POST['userId']??'';
  if($gid && $uid){
    try{ $pdo->prepare("INSERT INTO group_members (id,groupId,userId) VALUES (?,?,?)")->execute([cuid(),$gid,$uid]); $msg="Membre ajouté"; }catch(Exception $e){ $err="Déjà membre ou erreur"; }
  }
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['remove_member'])){
  $gid=$_POST['groupId']??''; $uid=$_POST['userId']??'';
  if($gid && $uid){ $pdo->prepare("DELETE FROM group_members WHERE groupId=? AND userId=?")->execute([$gid,$uid]); $msg="Membre retiré"; }
}

// load
$groups=$pdo->query("SELECT g.*, (SELECT COUNT(*) FROM group_members gm WHERE gm.groupId=g.id) as memberCount FROM `groups` g ORDER BY g.createdAt DESC")->fetchAll();
$allUsers=$pdo->query("SELECT id,firstName,lastName,email FROM users WHERE isActive=1 ORDER BY firstName")->fetchAll();
$detailId=$_GET['detail']??''; $detail=null; $detailMembers=[];
if($detailId){
  $st=$pdo->prepare("SELECT * FROM `groups` WHERE id=?"); $st->execute([$detailId]); $detail=$st->fetch();
  if($detail){ $m=$pdo->prepare("SELECT gm.id, u.id as uid, u.firstName,u.lastName,u.email FROM group_members gm JOIN users u ON u.id=gm.userId WHERE gm.groupId=? ORDER BY u.firstName"); $m->execute([$detailId]); $detailMembers=$m->fetchAll(); }
}
?>
<div class="flex items-center justify-between mb-6">
  <div><h1 class="text-2xl font-bold">Groupes</h1><p class="text-sm text-slate-500">Gestion des groupes d'utilisateurs</p></div>
  <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-black"><i data-lucide="plus" class="w-4 h-4"></i> Nouveau groupe</button>
</div>
<?php if($msg): ?><div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-2 rounded-xl text-sm mb-4"><?= e($msg) ?></div><?php endif; ?>
<?php if($err): ?><div class="bg-red-50 border border-red-200 text-red-600 px-4 py-2 rounded-xl text-sm mb-4"><?= e($err) ?></div><?php endif; ?>

<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
<?php foreach($groups as $g): ?>
  <div class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-sm transition-shadow">
    <div class="flex items-start justify-between mb-3">
      <a href="?detail=<?= e($g['id']) ?>" class="flex items-center gap-2 hover:text-blue-600"><i data-lucide="users" class="w-5 h-5 text-slate-900"></i><span class="font-semibold"><?= e($g['name']) ?></span></a>
      <form method="POST" onsubmit="return confirm('Supprimer ce groupe ?')"><input type="hidden" name="groupId" value="<?= e($g['id']) ?>"><button name="delete_group" value="1" class="p-1.5 rounded-lg hover:bg-red-50 text-slate-400 hover:text-red-600"><i data-lucide="trash-2" class="w-4 h-4"></i></button></form>
    </div>
    <?php if($g['description']): ?><p class="text-sm text-slate-500 mb-3 line-clamp-2"><?= e($g['description']) ?></p><?php endif; ?>
    <p class="text-xs text-slate-400"><?= (int)$g['memberCount'] ?> membre<?= (int)$g['memberCount']>1?'s':'' ?></p>
    <a href="?detail=<?= e($g['id']) ?>" class="text-xs text-blue-600 hover:underline mt-2 inline-block">Voir détails →</a>
  </div>
<?php endforeach; ?>
<?php if(empty($groups)): ?><div class="col-span-full text-center py-12 text-slate-400">Aucun groupe pour le moment</div><?php endif; ?>
</div>

<!-- Create Modal -->
<div id="createModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/30" onclick="if(event.target===this) this.classList.add('hidden')">
  <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4 shadow-xl">
    <div class="flex items-center justify-between mb-4"><h3 class="text-lg font-semibold">Nouveau groupe</h3><button onclick="document.getElementById('createModal').classList.add('hidden')" class="p-1 rounded-lg hover:bg-zinc-50"><i data-lucide="x" class="w-5 h-5"></i></button></div>
    <form method="POST" class="space-y-4">
      <div><label class="block text-sm font-medium mb-1">Nom *</label><input name="name" required class="input w-full"></div>
      <div><label class="block text-sm font-medium mb-1">Description</label><textarea name="description" rows="3" class="input w-full"></textarea></div>
      <div class="flex justify-end gap-2"><button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="border px-3 py-1.5 rounded-xl text-sm">Annuler</button><button name="create_group" value="1" class="bg-slate-900 text-white px-4 py-1.5 rounded-xl text-sm">Créer le groupe</button></div>
    </form>
  </div>
</div>

<?php if($detail): ?>
<div id="detailModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30" onclick="if(event.target===this) location.href='index.php'">
  <div class="bg-white rounded-xl p-6 w-full max-w-lg mx-4 shadow-xl max-h-[80vh] overflow-y-auto">
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-2"><a href="index.php" class="p-1 rounded-lg hover:bg-zinc-50"><i data-lucide="chevron-left" class="w-5 h-5"></i></a><h3 class="text-lg font-semibold"><?= e($detail['name']) ?></h3></div>
      <form method="POST" onsubmit="return confirm('Supprimer ?')"><input type="hidden" name="groupId" value="<?= e($detail['id']) ?>"><button name="delete_group" value="1" class="p-1.5 rounded-lg hover:bg-red-50 text-slate-400 hover:text-red-600"><i data-lucide="trash-2" class="w-4 h-4"></i></button></form>
    </div>
    <?php if($detail['description']): ?><p class="text-sm text-slate-500 mb-4"><?= e($detail['description']) ?></p><?php endif; ?>
    <div class="flex items-center justify-between mb-3"><p class="text-sm font-medium">Membres (<?= count($detailMembers) ?>)</p><button onclick="document.getElementById('addMemberModal').classList.remove('hidden')" class="inline-flex items-center gap-1 text-sm text-blue-600 hover:underline"><i data-lucide="user-plus" class="w-4 h-4"></i> Ajouter</button></div>
    <div class="space-y-2">
      <?php foreach($detailMembers as $m): ?>
      <div class="flex items-center justify-between border rounded-xl px-3 py-2">
        <div><p class="text-sm font-medium"><?= e($m['firstName'].' '.$m['lastName']) ?></p><p class="text-xs text-slate-400"><?= e($m['email']) ?></p></div>
        <form method="POST"><input type="hidden" name="groupId" value="<?= e($detail['id']) ?>"><input type="hidden" name="userId" value="<?= e($m['uid']) ?>"><button name="remove_member" value="1" class="p-1.5 rounded-lg hover:bg-red-50 text-slate-400 hover:text-red-600"><i data-lucide="user-minus" class="w-4 h-4"></i></button></form>
      </div>
      <?php endforeach; ?>
      <?php if(empty($detailMembers)): ?><p class="text-sm text-slate-400 text-center py-4">Aucun membre</p><?php endif; ?>
    </div>
    <a href="index.php" class="mt-4 inline-block text-sm text-slate-600 hover:underline">Fermer</a>
  </div>
</div>
<!-- Add member sub-modal -->
<div id="addMemberModal" class="hidden fixed inset-0 z-[60] flex items-center justify-center bg-black/30" onclick="if(event.target===this) this.classList.add('hidden')">
  <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4 shadow-xl max-h-[70vh] overflow-y-auto">
    <div class="flex items-center justify-between mb-4"><h3 class="font-semibold">Ajouter un membre</h3><button onclick="document.getElementById('addMemberModal').classList.add('hidden')" class="p-1 rounded-lg hover:bg-zinc-50"><i data-lucide="x" class="w-5 h-5"></i></button></div>
    <div class="space-y-2">
      <?php
        $memberIds=array_column($detailMembers,'uid');
        $avail=array_filter($allUsers,function($u) use($memberIds){ return !in_array($u['id'],$memberIds); });
        foreach($avail as $u): ?>
      <form method="POST" class="flex items-center justify-between border rounded-xl px-3 py-2 hover:bg-zinc-50">
        <input type="hidden" name="groupId" value="<?= e($detail['id']) ?>"><input type="hidden" name="userId" value="<?= e($u['id']) ?>">
        <div><p class="text-sm font-medium"><?= e($u['firstName'].' '.$u['lastName']) ?></p><p class="text-xs text-slate-400"><?= e($u['email']) ?></p></div>
        <button name="add_member" value="1" class="text-blue-600"><i data-lucide="user-plus" class="w-4 h-4"></i></button>
      </form>
      <?php endforeach; ?>
      <?php if(empty($avail)): ?><p class="text-sm text-slate-400 text-center py-4">Tous les utilisateurs sont déjà membres</p><?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
