<?php $pageTitle='Facturation'; require_once __DIR__ . '/../../includes/header.php'; $pdo=db();
if(!isSuperAdmin() && !can('facturation')){ echo '<div class="card p-10 text-center">Accès réservé</div>'; require_once __DIR__.'/../../includes/footer.php'; exit; }
$u=currentUser(); $isSuper=isSuperAdmin();
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_invoice'])){
  $eid=$_POST['entityId']??''; $amt=(float)($_POST['amount']??0); $label=trim($_POST['label']??'Abonnement');
  if($eid && $amt>0){ $id=cuid(); $pdo->prepare("INSERT INTO entity_invoices (id,entityId,label,amount,status,period_start,period_end) VALUES (?,?,?,?,?,?,?)")->execute([$id,$eid,$label,$amt,'EMISE', date('Y-m-01'), date('Y-m-t')]); $_SESSION['flash_success']='Facture émise : '.number_format($amt,0,',',' ').' FCFA'; }
}
$filterEntity=$_GET['entityId']??($isSuper?'': (function()use($pdo,$u){ $e=getUserEntityId($pdo,$u); return $e?:''; })());
$sql="SELECT i.*, e.name as entName FROM entity_invoices i JOIN entities e ON e.id=i.entityId WHERE 1=1"; $par=[];
if($filterEntity){ $sql.=" AND i.entityId=?"; $par[]=$filterEntity; }
$sql.=" ORDER BY i.createdAt DESC LIMIT 100";
$s=$pdo->prepare($sql); $s->execute($par); $rows=$s->fetchAll();
$entities=$pdo->query("SELECT id,name,code FROM entities ORDER BY name")->fetchAll();
$total=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM entity_invoices WHERE status!='ANNULEE'".($filterEntity?" AND entityId=?":"")); $filterEntity? $total->execute([$filterEntity]):$total->execute(); $sum=(float)$total->fetchColumn();
?>
<div class="space-y-6">
  <div class="flex items-center justify-between"><div><h1 class="text-2xl font-bold">Facturation</h1><p class="text-sm text-slate-500">Émissions par entité — lot 50M (ARMP 1.55M, Mairies 3M, SNPC 2M...)</p></div><div class="text-right"><div class="text-xs text-slate-500">Total émis</div><div class="text-xl font-bold"><?= number_format($sum,0,',',' ') ?> FCFA</div></div></div>
  <?php if($isSuper): ?>
  <div class="card p-4">
    <form method="POST" class="grid md:grid-cols-5 gap-2 items-end">
      <div><label class="block text-xs font-medium mb-1">Entité</label><select name="entityId" required class="border rounded-xl px-3 py-2 text-sm w-full"><?php foreach($entities as $e): ?><option value="<?= e($e['id']) ?>"><?= e($e['name']) ?></option><?php endforeach; ?></select></div>
      <div><label class="block text-xs font-medium mb-1">Libellé</label><input name="label" value="Abonnement mensuel" class="border rounded-xl px-3 py-2 text-sm w-full"></div>
      <div><label class="block text-xs font-medium mb-1">Montant FCFA</label><input name="amount" type="number" step="1000" required class="border rounded-xl px-3 py-2 text-sm w-full" placeholder="1550000"></div>
      <button name="create_invoice" value="1" class="bg-blue-600 text-white px-4 py-2 rounded-xl text-sm">Émettre</button>
      <a href="?entityId=" class="text-xs text-slate-500">Toutes</a>
    </form>
  </div>
  <?php endif; ?>
  <div class="card overflow-hidden"><div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-zinc-50"><tr><th class="text-left px-3 py-2">Entité</th><th class="px-3 py-2">Libellé</th><th class="px-3 py-2 text-right">Montant</th><th class="px-3 py-2">Statut</th><th class="px-3 py-2">Période</th></tr></thead><tbody class="divide-y">
    <?php foreach($rows as $r): ?><tr><td class="px-3 py-2"><?= e($r['entName']) ?></td><td class="px-3 py-2"><?= e($r['label']) ?></td><td class="px-3 py-2 text-right font-medium"><?= number_format($r['amount'],0,',',' ') ?></td><td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full text-xs <?= $r['status']==='PAYEE'?'bg-emerald-100 text-emerald-700':($r['status']==='EMISE'?'bg-amber-100 text-amber-700':'bg-zinc-100') ?>"><?= e($r['status']) ?></span></td><td class="px-3 py-2 text-xs"><?= e($r['period_start']) ?> → <?= e($r['period_end']) ?></td></tr><?php endforeach; ?>
    <?php if(empty($rows)): ?><tr><td colspan="5" class="text-center py-10 text-slate-400">Aucune facture — émets la première (ex: EROSIS 1 550 000)</td></tr><?php endif; ?>
  </tbody></table></div></div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
