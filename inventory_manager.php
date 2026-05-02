<?php
session_start(); include 'database.php';
require_login('headworker');
$msg_ok=''; $msg_err='';

if (isset($_POST['delete_all'])&&($_POST['confirm_delete_all']??'')==='CONFIRMER'){
    $n=$pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
    $pdo->exec("DELETE FROM inventory"); $msg_ok="$n référence(s) supprimées.";
}
if (isset($_POST['do_import'])&&isset($_FILES['csv_file'])) {
    $f=$_FILES['csv_file'];
    if ($f['error']===0) {
        $raw=file_get_contents($f['tmp_name']); $enc=mb_detect_encoding($raw,['UTF-8','ISO-8859-1','Windows-1252'],true);
        if ($enc&&$enc!=='UTF-8') $raw=mb_convert_encoding($raw,'UTF-8',$enc);
        $lines=explode("\n",$raw); $first=trim($lines[0]??''); $sep=substr_count($first,';')>=substr_count($first,',') ? ';':',';
        $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
        if (in_array($ext,['xlsx','xls'])) { require_once 'picklist_importer.php'; /* reuse reader */ }
        $count=0; $skip=0;
        $stmt=$pdo->prepare("INSERT INTO inventory (barcode,designation,emplacement,nbre_us,quantite) VALUES (?,?,?,?,0) ON DUPLICATE KEY UPDATE designation=VALUES(designation),emplacement=VALUES(emplacement),nbre_us=VALUES(nbre_us)");
        foreach (array_slice($lines,1) as $line){$p=str_getcsv(trim($line),$sep);$ref=trim($p[0]??'');if(!$ref||strlen($ref)<2){$skip++;continue;}$stmt->execute([$ref,trim($p[1]??''),trim($p[2]??''),(int)($p[3]??0)]);$count++;}
        $msg_ok="$count bobine(s) importées. ($skip ignorées)";
    } else { $msg_err='Erreur upload.'; }
}
if (isset($_POST['save_item'])) {
    $bc=trim($_POST['barcode']??''); $qty=(int)($_POST['quantite']??0);
    if ($bc) { $pdo->prepare("INSERT INTO inventory (barcode,quantite) VALUES (?,?) ON DUPLICATE KEY UPDATE quantite=VALUES(quantite)")->execute([$bc,$qty]); $msg_ok="Enregistré : $bc"; }
    else { $msg_err='Référence obligatoire.'; }
}
if (isset($_POST['delete_item'])) {
    $bc=trim($_POST['barcode']??'');
    if ($bc) { $pdo->prepare("DELETE FROM inventory WHERE barcode=?")->execute([$bc]); $msg_ok="Supprimée : $bc"; }
}
$q=trim($_GET['q']??'');
$items=$q ? ($pdo->prepare("SELECT * FROM inventory WHERE barcode LIKE ? OR designation LIKE ? ORDER BY barcode ASC LIMIT 100") ?: null) : null;
if ($q){$s=$pdo->prepare("SELECT * FROM inventory WHERE barcode LIKE ? OR designation LIKE ? ORDER BY barcode ASC LIMIT 100");$s->execute(["%$q%","%$q%"]);$items=$s->fetchAll();}
else{$items=$pdo->query("SELECT * FROM inventory ORDER BY updated_at DESC LIMIT 200")->fetchAll();}
$total=(int)$pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
?>
<!DOCTYPE html><html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Inventaire Bobines — AGV</title>
<?php include '_css.php'; ?>
</head><body>

<nav class="nav">
  <div class="nav-brand"><div class="nav-logo">📦</div>
  <div><div class="nav-title">INVENTAIRE BOBINES</div></div></div>
  <div class="nav-links">
    <a href="admin_dashboard.php" class="nl">← Dashboard</a>
    <a href="picklist_manager.php" class="nl">📋 Pickliste</a>
    <a href="logout.php" class="nl nl-red">Déconnexion</a>
  </div>
</nav>

<div class="container">
<?php if ($msg_ok): ?><div class="alert a-ok">✅ <?= sanitize($msg_ok) ?></div><?php endif; ?>
<?php if ($msg_err): ?><div class="alert a-err">⚠️ <?= sanitize($msg_err) ?></div><?php endif; ?>

<div class="gs">
  <!-- Import -->
  <div class="panel">
    <div class="ph"><span class="pt">⬆️ Importer un fichier</span>
    <div style="display:flex;gap:.3rem"><span class="pill px">📊 .xlsx</span><span class="pill pc">📊 .xls</span><span class="pill pv">📄 .csv</span></div></div>
    <div class="pb">
      <form method="POST" enctype="multipart/form-data">
        <div class="upz" onclick="document.getElementById('fi').click()">
          <input type="file" id="fi" name="csv_file" accept=".csv,.xlsx,.xls" style="display:none" onchange="document.getElementById('fn').textContent='📎 '+this.files[0].name">
          <div style="font-size:2rem;margin-bottom:.5rem">📋</div>
          <div style="font-weight:700;color:var(--txt);margin-bottom:.25rem" id="fn">Glissez votre fichier ici</div>
          <div style="font-size:.75rem;color:var(--mut);margin-bottom:.5rem">ou cliquez pour parcourir — CSV, Excel .xlsx ou .xls</div>
          <div style="display:flex;gap:.3rem;justify-content:center"><span class="pill px">📊 .xlsx</span><span class="pill pc">📊 .xls</span><span class="pill pv">📄 .csv</span></div>
        </div>
        <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-top:.875rem">
          <button type="submit" name="do_import" class="btn btn-o">⬆️ Importer</button>
          <div style="font-size:.72rem;color:var(--mut);line-height:1.6">
            Détection automatique du format et de l'encodage<br>
            <strong>Format CSV :</strong> <code>barcode,designation,emplacement,nbre_us</code><br>
            <strong>Format Excel :</strong> première ligne = en-tête avec colonnes <code>barcode</code> / <code>designation</code> / <code>emplacement</code> / <code>nbre_us</code><br>
            Seule la colonne <code>barcode</code> est obligatoire. Si la bobine existe déjà → mise à jour automatique.
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Ajouter + Danger -->
  <div>
    <div class="panel">
      <div class="ph"><span class="pt">+ Ajouter / Modifier</span></div>
      <div class="pb">
        <form method="POST">
          <div class="field"><label class="flbl">QR / Référence *</label><input class="inp" type="text" name="barcode" placeholder="Ex: LPB189060674" required></div>
          <div class="field"><label class="flbl">Quantité</label><input class="inp" type="number" name="quantite" value="0" min="0"></div>
          <button type="submit" name="save_item" class="btn btn-b btn-fw">Enregistrer</button>
        </form>
      </div>
    </div>
    <div class="panel">
      <div class="ph"><span class="pt" style="color:var(--red)">🗑️ VIDER L'INVENTAIRE</span></div>
      <div class="pb">
        <form method="POST">
          <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <input class="inp" style="flex:1;min-width:120px;border-color:rgba(239,68,68,.3)" type="text" name="confirm_delete_all" placeholder="Tapez CONFIRMER">
            <button type="submit" name="delete_all" class="btn btn-r btn-sm">Vider</button>
          </div>
          <div style="font-size:.72rem;color:var(--mut);margin-top:.375rem">Action irréversible — <?= number_format($total) ?> référence(s) supprimées.</div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Catalogue -->
<div class="panel">
  <div class="ph">
    <span class="pt">📚 Catalogue bobines</span>
    <div style="display:flex;align-items:center;gap:.5rem">
      <span class="badge bo"><?= number_format($total) ?> références</span>
      <?php if ($q): ?><a href="inventory_manager.php" class="btn btn-ghost btn-sm">✕ Effacer</a><?php endif; ?>
    </div>
  </div>
  <div style="padding:.75rem 1.25rem;border-bottom:1px solid var(--bdr)">
    <form method="GET" class="srow">
      <input type="text" name="q" value="<?= sanitize($q) ?>" placeholder="Rechercher par code-barres ou désignation...">
      <button type="submit" class="btn btn-o btn-sm">🔍</button>
    </form>
  </div>
  <div class="tbl-wrap"><table>
    <thead><tr><th>Code-barres</th><th>Date de création</th><th>Quantité</th><th>Action</th></tr></thead>
    <tbody>
    <?php if (empty($items)): ?>
      <tr><td colspan="4" class="tbl-empty"><?= $q?"Aucun résultat pour \"".sanitize($q)."\"":"Inventaire vide" ?></td></tr>
    <?php else: foreach ($items as $it): ?>
      <tr>
        <td class="mono"><?= sanitize($it['barcode']) ?></td>
        <td style="font-size:.78rem;color:var(--mut)"><?= sanitize(substr($it['updated_at']??'',0,10)) ?></td>
        <td><span style="font-weight:700;color:<?= (int)$it['quantite']===0?'var(--red)':'var(--grn-d)' ?>"><?= (int)$it['quantite'] ?></span></td>
        <td><form method="POST" onsubmit="return confirm('Supprimer <?= addslashes(sanitize($it['barcode'])) ?> ?')">
          <input type="hidden" name="barcode" value="<?= sanitize($it['barcode']) ?>">
          <button type="submit" name="delete_item" class="btn btn-r btn-sm">Suppr.</button></form></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table></div>
  <?php if (!$q&&count($items)>=200): ?>
  <div class="tbl-foot">Affichage de 200 références sur <?= number_format($total) ?> — utilisez la recherche.</div>
  <?php endif; ?>
</div>
</div>
</body></html>
