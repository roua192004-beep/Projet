<?php
session_start(); include 'database.php';
require_once 'picklist_importer.php';
require_login('headworker');
$msg_ok=''; $msg_err='';

if ($_SERVER['REQUEST_METHOD']==='POST'&&isset($_FILES['picklist_file'])) {
    $f=$_FILES['picklist_file'];
    if ($f['error']===0){
        $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
        if (in_array($ext,['xlsx','xls','csv'])){
            $r=import_picklist_file($f['tmp_name'],$pdo,$ext);
            if($r['success']){$msg_ok="Importée : {$r['picklist_code']} — {$r['count']} référence(s)";}
            else{$msg_err="Erreur : ".$r['error'];}
        } else {$msg_err='Format non supporté (.xlsx, .xls, .csv)';}
    } else {$msg_err='Erreur upload (code '.$f['error'].').';}
}
if ($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['create_manual'])){
    $code=trim($_POST['picklist_code']??''); $refs=trim($_POST['references']??'');
    if ($code&&$refs){
        $pdo->prepare("INSERT INTO picklist_header (picklist_code,mapa,uf,ligne_production,code_pfin,status,imported_at) VALUES (?,?,?,?,?,'active',NOW())")
            ->execute([$code,$_POST['mapa']??'',$_POST['uf']??'',$_POST['ligne_production']??'',$_POST['code_pfin']??'']);
        $pid=$pdo->lastInsertId(); $count=0;
        foreach (explode("\n",$refs) as $line){$p=explode(';',trim($line));$ref=trim($p[0]??'');if(!$ref)continue;
            $pdo->prepare("INSERT INTO picklist_lines (picklist_id,reference,emplacement,quantite,status) VALUES (?,?,?,?,'pending')")
                ->execute([$pid,$ref,trim($p[2]??''),max(1,(int)($p[1]??1))]);$count++;}
        $msg_ok="Picklist $code créée : $count références.";
    } else {$msg_err='Code et références requis.';}
}
if ($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['archive_id'])){
    $pdo->prepare("UPDATE picklist_header SET status='delivered' WHERE id=?")->execute([(int)$_POST['archive_id']]);
    $msg_ok='Picklist archivée.';
}
$actives=$pdo->query("SELECT ph.*,COUNT(pl.id) as total,SUM(CASE WHEN pl.status='scanned_ok' THEN 1 ELSE 0 END) as ok FROM picklist_header ph LEFT JOIN picklist_lines pl ON pl.picklist_id=ph.id WHERE ph.status IN ('active','preparing') GROUP BY ph.id ORDER BY ph.imported_at DESC")->fetchAll();
$archives=$pdo->query("SELECT * FROM picklist_header WHERE status='delivered' ORDER BY imported_at DESC LIMIT 30")->fetchAll();
?>
<!DOCTYPE html><html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gestion Picklists — AGV</title>
<?php include '_css.php'; ?>
</head><body>
<nav class="nav">
  <div class="nav-brand"><div class="nav-logo">📋</div><div><div class="nav-title">GESTION PICKLISTS</div></div></div>
  <div class="nav-links">
    <a href="admin_dashboard.php" class="nl">← Dashboard</a>
    <a href="inventory_manager.php" class="nl">📦 Inventaire</a>
    <a href="logout.php" class="nl nl-red">Déconnexion</a>
  </div>
</nav>
<div class="container">
<?php if ($msg_ok): ?><div class="alert a-ok">✅ <?= sanitize($msg_ok) ?></div><?php endif; ?>
<?php if ($msg_err): ?><div class="alert a-err">⚠️ <?= sanitize($msg_err) ?></div><?php endif; ?>

<div class="g2">
  <div class="panel">
    <div class="ph"><span class="pt">📋 Importer un fichier</span>
    <div style="display:flex;gap:.3rem"><span class="pill px">📊 .xlsx</span><span class="pill pc">📊 .xls</span><span class="pill pv">📄 .csv</span></div></div>
    <div class="pb">
      <form method="POST" enctype="multipart/form-data">
        <div class="upz" onclick="document.getElementById('fi2').click()">
          <input type="file" id="fi2" name="picklist_file" accept=".csv,.xlsx,.xls" style="display:none" onchange="document.getElementById('fn2').textContent='📎 '+this.files[0].name">
          <div style="font-size:2rem;margin-bottom:.5rem">📋</div>
          <div style="font-weight:700;color:var(--txt);margin-bottom:.25rem" id="fn2">Glissez votre fichier ici</div>
          <div style="font-size:.75rem;color:var(--mut);margin-bottom:.375rem">ou cliquez pour parcourir</div>
          <div style="font-size:.7rem;color:var(--mut)">MAPA · <strong>PICK LIST</strong> · UF · Ligne · Code Pfin · <strong>Emplacements</strong> · <strong>Reference</strong> · Nbre US · <strong>Quantite</strong></div>
        </div>
        <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-top:.875rem">
          <button type="submit" class="btn btn-o">⬆️ Importer</button>
          <span style="font-size:.72rem;color:var(--mut)">Même PICK LIST sur plusieurs lignes = 1 picklist groupée</span>
        </div>
      </form>
    </div>
  </div>

  <div class="panel">
    <div class="ph"><span class="pt">✏️ Créer manuellement</span></div>
    <div class="pb">
      <form method="POST">
        <div class="g2" style="gap:.75rem">
          <div class="field"><label class="flbl">Code Picklist *</label><input class="inp" type="text" name="picklist_code" placeholder="PB260984020"></div>
          <div class="field"><label class="flbl">MAPA</label><input class="inp" type="text" name="mapa" placeholder="MA7"></div>
          <div class="field"><label class="flbl">UF</label><input class="inp" type="text" name="uf" placeholder="AVS"></div>
          <div class="field"><label class="flbl">Ligne Production</label><input class="inp" type="text" name="ligne_production" placeholder="LIGNE07EZR"></div>
        </div>
        <div class="field"><label class="flbl">Code Pfin</label><input class="inp" type="text" name="code_pfin" placeholder="TEST PANA"></div>
        <div class="field"><label class="flbl">Références (une par ligne) *</label>
        <textarea class="inp" name="references" rows="5" placeholder="LPB189060674;2;ATT.M14 et A15.05.10&#10;LPB188127206;1;D03.09&#10;&#10;Format: Reference;Quantite;Emplacement"></textarea></div>
        <button type="submit" name="create_manual" class="btn btn-o">Créer la picklist</button>
      </form>
    </div>
  </div>
</div>

<div class="panel">
  <div class="pb" style="padding-bottom:0">
    <div class="tabs">
      <button class="tab on" onclick="sw('a',this)">Actives <span class="tc"><?= count($actives) ?></span></button>
      <button class="tab" onclick="sw('b',this)">Archive <span class="tc"><?= count($archives) ?></span></button>
    </div>
  </div>
  <div id="tp-a" class="tp on">
    <?php if (empty($actives)): ?><div class="tbl-empty">Aucune picklist active — Importez un fichier ci-dessus.</div>
    <?php else: ?><div class="tbl-wrap"><table>
      <thead><tr><th>Code</th><th>MAPA</th><th>UF</th><th>Ligne</th><th>Progression</th><th>Statut</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($actives as $a): $tot=max(1,(int)($a['total']??1));$ok=(int)($a['ok']??0);$pct=round($ok/$tot*100); ?>
        <tr>
          <td class="mono"><?= sanitize($a['picklist_code']) ?></td>
          <td><?= sanitize($a['mapa']??'') ?></td><td><?= sanitize($a['uf']??'') ?></td>
          <td><?= sanitize($a['ligne_production']??'') ?></td>
          <td><div style="display:flex;align-items:center;gap:.5rem"><div class="prog" style="width:80px"><div class="prog-bar" style="width:<?=$pct?>%"></div></div><span style="font-size:.72rem;color:var(--mut)"><?=$ok?>/<?=$tot?></span></div></td>
          <td><span class="badge bg"><?= sanitize($a['status']) ?></span></td>
          <td style="font-size:.72rem;color:var(--mut)"><?= sanitize($a['imported_at']??'') ?></td>
          <td><form method="POST" onsubmit="return confirm('Archiver ?')"><input type="hidden" name="archive_id" value="<?= $a['id'] ?>"><button type="submit" class="btn btn-ghost btn-sm">Archiver</button></form></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div><?php endif; ?>
  </div>
  <div id="tp-b" class="tp">
    <?php if (empty($archives)): ?><div class="tbl-empty">Aucune picklist archivée.</div>
    <?php else: ?><div class="tbl-wrap"><table>
      <thead><tr><th>Code</th><th>MAPA</th><th>Ligne</th><th>Date</th></tr></thead>
      <tbody>
      <?php foreach ($archives as $a): ?>
        <tr><td class="mono"><?= sanitize($a['picklist_code']) ?></td><td><?= sanitize($a['mapa']??'') ?></td>
        <td><?= sanitize($a['ligne_production']??'') ?></td><td style="font-size:.72rem;color:var(--mut)"><?= sanitize($a['imported_at']??'') ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div><?php endif; ?>
  </div>
</div>
</div>
<script>function sw(id,el){document.querySelectorAll('.tp').forEach(t=>t.classList.remove('on'));document.querySelectorAll('.tab').forEach(t=>t.classList.remove('on'));document.getElementById('tp-'+id).classList.add('on');el.classList.add('on');}</script>
</body></html>
