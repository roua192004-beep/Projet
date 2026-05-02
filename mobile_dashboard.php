<?php
session_start(); include 'database.php';
require_login('warehouse_worker','line_worker');
$role=$_SESSION['role']; $full_name=$_SESSION['full_name']; $user_id=$_SESSION['user_id']; $shift=get_current_shift();
$msg_ok=''; $msg_err='';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cmd'])) {
    $cmd=$_POST['cmd'];
    $ok=($role==='warehouse_worker'&&$cmd==='call_robot')||($role==='line_worker'&&in_array($cmd,['confirm_discharge','return_to_zone3'],true));
    if ($ok) {
        $pdo->prepare("INSERT INTO robot_commands (command,created_by) VALUES (?,?)")->execute([$cmd,$user_id]);
        if ($cmd==='confirm_discharge') {
            $ap=$pdo->query("SELECT id,picklist_code FROM picklist_header WHERE status IN ('active','preparing') ORDER BY imported_at ASC LIMIT 1")->fetch();
            if ($ap) {
                $pdo->prepare("UPDATE picklist_header SET status='delivered' WHERE id=?")->execute([$ap['id']]);
                $pdo->prepare("UPDATE picklist_lines SET status='loaded' WHERE picklist_id=? AND status='scanned_ok'")->execute([$ap['id']]);
                // FIFO — vérifier si une picklist suivante attend
                $next=$pdo->query("SELECT picklist_code FROM picklist_header WHERE status IN ('active','preparing') ORDER BY imported_at ASC LIMIT 1")->fetch();
                $msg_ok='✅ Livraison confirmée — '.$ap['picklist_code'];
                if ($next) $msg_ok.=' | ➡️ Picklist suivante : <strong>'.$next['picklist_code'].'</strong>';
                else $msg_ok.=' | Aucune picklist en attente.';
            } else { $msg_err='Aucune picklist active.'; }
        } elseif ($cmd==='return_to_zone3') { $msg_ok='Retour zone 3 déclenché.'; }
        elseif ($cmd==='call_robot')        { $msg_ok='Robot appelé au magasin.'; }
    }
}

$ap=$pdo->query("SELECT ph.*,COUNT(pl.id) as total,SUM(CASE WHEN pl.status='scanned_ok' THEN 1 ELSE 0 END) as scannees FROM picklist_header ph LEFT JOIN picklist_lines pl ON pl.picklist_id=ph.id WHERE ph.status IN ('active','preparing') GROUP BY ph.id ORDER BY ph.imported_at ASC LIMIT 1")->fetch();
$pl_lines=[]; if ($ap){$r=$pdo->prepare("SELECT * FROM picklist_lines WHERE picklist_id=? ORDER BY id");$r->execute([$ap['id']]);$pl_lines=$r->fetchAll();}
$ls=$pdo->query("SELECT * FROM robot_activity WHERE action IN ('scan_ok','scan_error','last_scan_cache') ORDER BY scanned_at DESC LIMIT 1")->fetch();
$rs=$pdo->query("SELECT * FROM robot_status WHERE id=1")->fetch();
$is_em=(bool)($rs['is_emergency']??false); $online=$rs['last_ping']&&(strtotime('now')-strtotime($rs['last_ping']))<10;
$bat=(int)($rs['battery_pct']??0); $mode=$rs['mode']??'auto'; $zone=$rs['zone']??'zone1';

// Slot cache from Pi4
$sc_data=['overall'=>'red','lines'=>[],'slots'=>[]];
try { $pdo->exec("CREATE TABLE IF NOT EXISTS agv_slot_cache (id INT DEFAULT 1 PRIMARY KEY,payload TEXT,updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)"); $sr=$pdo->query("SELECT payload FROM agv_slot_cache WHERE id=1")->fetch(); if($sr&&$sr['payload']){$d=json_decode($sr['payload'],true);if($d)$sc_data=$d;} } catch(Exception $e){}
$pl_overall=$sc_data['overall']??'red'; $pl_status=[];
foreach (($sc_data['lines']??[]) as $l){$pl_status[$l['reference']]=$l;}
?>
<!DOCTYPE html><html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>AGV — <?= $role==='warehouse_worker'?'Magasin':'Ligne' ?></title>
<?php include '_css.php'; ?>
<style>
body{max-width:600px;margin:0 auto}
.topbar{background:var(--sur);border-bottom:1px solid var(--bdr);padding:.875rem 1.125rem;display:flex;align-items:center;justify-content:space-between}
.glob{display:flex;align-items:center;gap:.75rem;padding:.75rem 1.125rem;border-radius:10px;margin-bottom:1rem;font-weight:700;font-size:.9rem;color:#fff}
.g-red{background:linear-gradient(135deg,#EF4444,#DC2626)}
.g-ora{background:linear-gradient(135deg,#F59E0B,#D97706)}
.g-grn{background:linear-gradient(135deg,#10B981,#059669)}
.crd{background:var(--sur);border:1px solid var(--bdr);border-radius:14px;box-shadow:0 2px 8px rgba(0,0,0,.06);margin-bottom:1rem;overflow:hidden}
.ch{padding:.875rem 1.125rem;border-bottom:1px solid var(--bdr);display:flex;align-items:center;justify-content:space-between}
.ct{font-size:.72rem;font-weight:700;color:var(--txt);text-transform:uppercase;letter-spacing:.06em}
.sb{padding:.875rem 1.125rem;text-align:center}
.bc{font-family:'JetBrains Mono',monospace;font-size:.95rem;color:var(--bdr2);margin-bottom:.625rem;letter-spacing:.05em}
.sok{background:var(--grn-l);border:1.5px solid rgba(16,185,129,.25);border-radius:9999px;padding:.6rem 1.25rem;color:var(--grn);font-weight:700;font-size:.9rem;display:inline-flex;align-items:center;gap:.4rem}
.serr{background:var(--red-l);border:1.5px solid rgba(239,68,68,.2);border-radius:9999px;padding:.6rem 1.25rem;color:var(--red);font-weight:700;font-size:.9rem;display:inline-flex;align-items:center;gap:.4rem}
.swait{color:var(--mut);font-size:.85rem}
.sdate{font-size:.7rem;color:var(--mut);margin-top:.4rem}
.pl-stats{display:grid;grid-template-columns:repeat(3,1fr);border-bottom:1px solid var(--bdr)}
.pl-stat{padding:.875rem .5rem;text-align:center;border-right:1px solid var(--bdr)}
.pl-stat:last-child{border-right:none}
.pl-sv{font-size:1.5rem;font-weight:700;color:var(--txt);line-height:1}
.pl-sl{font-size:.62rem;font-weight:600;color:var(--mut);text-transform:uppercase;letter-spacing:.05em;margin-top:.2rem}
.ps-g .pl-sv{color:var(--grn)} .ps-o .pl-sv{color:var(--acc)}
.pl-info{padding:.875rem 1.125rem;border-bottom:1px solid #F1F5F9}
.pl-row{display:flex;align-items:baseline;gap:.5rem;margin-bottom:.3rem;font-size:.82rem}
.pl-row:last-child{margin-bottom:0}
.pl-lbl{color:var(--mut);font-size:.75rem;font-weight:500;min-width:80px}
.pl-val{color:var(--txt2);font-weight:600}
.pl-prog{padding:.625rem 1.125rem;border-bottom:1px solid var(--bdr);font-size:.75rem;color:var(--mut);text-align:right}
.bline{padding:.875rem 1.125rem;border-bottom:1px solid #F1F5F9;display:flex;align-items:center;justify-content:space-between;gap:.75rem}
.bline:last-child{border-bottom:none}
.bleft{display:flex;align-items:center;gap:.625rem;flex:1;min-width:0}
.bbar{width:4px;height:44px;border-radius:2px;flex-shrink:0;transition:background .3s}
.bb-r{background:var(--red)} .bb-o{background:var(--acc)} .bb-g{background:var(--grn)}
.bref{font-family:'JetBrains Mono',monospace;font-size:.8rem;font-weight:600;color:var(--txt2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bsub{font-size:.7rem;color:var(--mut);margin-top:.15rem}
.btag{padding:.2rem .65rem;border-radius:9999px;font-size:.65rem;font-weight:700;border:1px solid;white-space:nowrap}
.bt-r{background:var(--red-l);color:var(--red);border-color:rgba(239,68,68,.25)}
.bt-o{background:var(--acc-l);color:var(--acc-d);border-color:rgba(245,158,11,.3)}
.bt-g{background:var(--grn-l);color:var(--grn-d);border-color:rgba(16,185,129,.25)}
.act{background:var(--sur);border:1px solid var(--bdr);border-radius:14px;box-shadow:0 2px 8px rgba(0,0,0,.06);padding:1rem;margin-bottom:1rem}
.act-t{font-size:.72rem;font-weight:700;color:var(--txt);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.875rem}
.bb-btn{width:100%;padding:.875rem 1.25rem;border:none;border-radius:10px;font-family:inherit;font-size:.95rem;font-weight:700;cursor:pointer;transition:all .15s;display:flex;align-items:center;justify-content:center;gap:.625rem;margin-bottom:.625rem}
.bb-btn:last-child{margin-bottom:0}
.bb-btn:disabled{opacity:.4;cursor:not-allowed}
.bbo{background:linear-gradient(135deg,var(--acc),var(--acc-d));color:#fff}
.bbg{background:linear-gradient(135deg,var(--grn),var(--grn-d));color:#fff}
.notif{display:flex;align-items:center;gap:.875rem;padding:1rem 1.125rem;border-radius:14px;border:2px solid;margin-bottom:1rem}
.nw{background:var(--sur2);border-color:var(--bdr)} .np{background:var(--acc-l);border-color:rgba(245,158,11,.3)} .ng{background:var(--grn-l);border-color:rgba(16,185,129,.4)}
.ni{font-size:1.75rem;flex-shrink:0}
.nt{font-size:.875rem;font-weight:700}
.nw .nt{color:var(--mut)} .np .nt{color:var(--acc-d)} .ng .nt{color:var(--grn-d)}
.ns{font-size:.72rem;color:var(--mut);margin-top:.15rem}
.lbtn{width:100%;padding:1.25rem 1.5rem;border:none;border-radius:12px;font-family:inherit;font-size:1rem;font-weight:700;cursor:pointer;transition:all .15s;display:flex;align-items:center;gap:1rem;margin-bottom:.875rem}
.lbtn:disabled{opacity:.4;cursor:not-allowed}
.lb-g{background:linear-gradient(135deg,var(--grn),var(--grn-d));color:#fff;box-shadow:0 4px 14px rgba(16,185,129,.3)}
.lb-b{background:linear-gradient(135deg,var(--blu),var(--blu-d));color:#fff;box-shadow:0 4px 14px rgba(59,130,246,.3)}
.li{font-size:1.4rem;flex-shrink:0}
</style>
</head>
<body>
<header class="topbar">
  <div><div style="font-size:1rem;font-weight:700;color:var(--txt)">Bonjour, <?= sanitize($full_name) ?></div>
  <div style="font-size:.72rem;color:var(--mut)">Poste <?= $shift ?> · <?= date('H:i') ?></div></div>
  <div style="display:flex;align-items:center;gap:.5rem">
    <span class="badge <?= $role==='warehouse_worker'?'bo':'bb' ?>"><?= $role==='warehouse_worker'?'MAGASIN':'LIGNE' ?></span>
    <a href="logout.php" class="btn btn-ghost btn-sm">Quitter</a>
  </div>
</header>

<div style="padding:.875rem 1rem">

<!-- Status bar -->
<div style="background:var(--sur);border:1px solid var(--bdr);border-radius:10px;padding:.5rem 1rem;display:flex;justify-content:space-between;align-items:center;margin-bottom:.875rem;font-size:.78rem">
  <span><span class="dot <?= $online?'don':'dof' ?>" style="margin-right:.35rem"></span>Robot: <strong><?= strtoupper(sanitize($mode)) ?></strong> · <?= sanitize($zone) ?></span>
  <span style="color:var(--grn);font-weight:700">🔋 <?= $bat ?>%</span>
</div>

<?php if ($msg_ok): ?><div class="alert a-ok">✅ <?= sanitize($msg_ok) ?></div><?php endif; ?>
<?php if ($msg_err): ?><div class="alert a-err">⚠️ <?= sanitize($msg_err) ?></div><?php endif; ?>

<?php if ($role==='warehouse_worker'): ?>

  <!-- Barre globale -->
  <?php $gc=match($pl_overall){'green'=>'g-grn','orange'=>'g-ora',default=>'g-red'}; $gi=match($pl_overall){'green'=>'✅','orange'=>'🟡',default=>'⏳'};
  $gt=match($pl_overall){'green'=>'CHARGEMENT COMPLET — Prêt à envoyer','orange'=>'CHARGEMENT EN COURS — Continuer...', default=>'EN ATTENTE — Poser les bobines'}; ?>
  <div class="glob <?= $gc ?>"><span style="font-size:1.4rem"><?= $gi ?></span><span><?= $gt ?></span></div>

  <!-- Dernier scan -->
  <div class="crd">
    <div class="ch"><span class="ct">📷 DERNIER SCAN QR CODE</span>
    <span style="font-size:.72rem;color:var(--mut)" id="stime"><?= $ls?date('H:i:s',strtotime($ls['scanned_at'])):'' ?></span></div>
    <div class="sb" id="sarea">
      <?php if ($ls): ?>
        <div class="bc"><?= sanitize($ls['barcode']) ?></div>
        <?php if ($ls['result']==='ok'): ?><div class="sok">✅ Bobine correcte</div>
        <?php else: ?><div class="serr">✖ Bobine non confirmée — Retirer</div><?php endif; ?>
        <div class="sdate"><?= sanitize($ls['scanned_at']??'') ?></div>
      <?php else: ?><div class="swait">En attente d'un scan...</div><?php endif; ?>
    </div>
  </div>

  <!-- Picklist active -->
  <?php if ($ap): $total=(int)($ap['total']??0);$scannees=(int)($ap['scannees']??0);$restantes=max(0,$total-$scannees);$pct=$total>0?round($scannees/$total*100):0; ?>
  <div class="crd">
    <div class="ch"><span class="ct">📋 PICKLIST ACTIVE</span>
    <span class="badge bo">active</span></div>
    <div class="pl-stats">
      <div class="pl-stat"><div class="pl-sv"><?= $total ?></div><div class="pl-sl">Total</div></div>
      <div class="pl-stat ps-g"><div class="pl-sv"><?= $scannees ?></div><div class="pl-sl">Scannées</div></div>
      <div class="pl-stat ps-o"><div class="pl-sv"><?= $restantes ?></div><div class="pl-sl">Restantes</div></div>
    </div>
    <div class="pl-info">
      <div class="pl-row"><span class="pl-lbl">Picklist</span><span class="pl-val"><?= sanitize($ap['picklist_code']??'') ?></span></div>
      <div class="pl-row"><span class="pl-lbl">MAPA</span><span class="pl-val"><?= sanitize($ap['mapa']??'—') ?></span></div>
      <div class="pl-row"><span class="pl-lbl">UF</span><span class="pl-val"><?= sanitize($ap['uf']??'—') ?></span></div>
      <div class="pl-row"><span class="pl-lbl">Ligne</span><span class="pl-val"><?= sanitize($ap['ligne_production']??'—') ?></span></div>
      <div class="pl-row"><span class="pl-lbl">Code Pfin</span><span class="pl-val"><?= sanitize($ap['code_pfin']??'—') ?></span></div>
    </div>
    <div class="pl-prog"><?= $pct ?>% — <?= $scannees ?>/<?= $total ?> bobines</div>
    <?php foreach ($pl_lines as $line):
      $ref=$line['reference']; $qty=(int)($line['quantite']??1); $nus=(int)($line['nbre_us']??1); $emp=$line['emplacement']??''; $st=$line['status']??'pending';
      if (isset($pl_status[$ref])){$pist=$pl_status[$ref]['status'];$loaded=(int)($pl_status[$ref]['loaded']??0);}
      else{$pist=$st==='scanned_ok'?'complete':'pending';$loaded=$st==='scanned_ok'?$qty:0;}
      [$bc,$tc,$tt]=match($pist){'complete'=>['bb-g','bt-g','COMPLET ✅'],'partial'=>['bb-o','bt-o','EN COURS 🟡'],default=>['bb-r','bt-r','EN ATTENTE']};
    ?>
    <div class="bline">
      <div class="bleft">
        <div class="bbar <?= $bc ?>"></div>
        <div>
          <div class="bref"><?= sanitize($ref) ?></div>
          <div class="bsub"><?php if($emp):?>📍<?= sanitize($emp) ?> · <?php endif;?>Qt:<?= $qty ?> · Chargé:<?= $loaded ?>/<?= $qty ?></div>
        </div>
      </div>
      <span class="btag <?= $tc ?>"><?= $tt ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="crd"><div style="padding:1.5rem;text-align:center;color:var(--mut)">Aucune picklist active</div></div>
  <?php endif; ?>

  <!-- Actions -->
  <div class="act">
    <div class="act-t">⚡ ACTIONS</div>
    <form method="POST"><input type="hidden" name="cmd" value="call_robot">
    <button type="submit" class="bb-btn bbo" <?= $is_em?'disabled':'' ?>>🤖 Appeler le robot au magasin</button></form>
    <?php if ($ap&&$pl_overall==='green'): ?>
    <form method="POST" onsubmit="return confirm('Confirmer le chargement complet ?')">
    <input type="hidden" name="cmd" value="confirm_discharge">
    <button type="submit" class="bb-btn bbg" <?= $is_em?'disabled':'' ?>>✅ Confirmer chargement complet</button></form>
    <?php endif; ?>
  </div>

  <script>
  setInterval(()=>{
    fetch('get_last_scan.php').then(r=>r.json()).then(d=>{
      if(!d||!d.barcode)return;
      const a=document.getElementById('sarea'),t=document.getElementById('stime'),ok=d.signal==='green';
      a.innerHTML='<div class="bc">'+d.barcode+'</div>'+(ok?'<div class="sok">✅ Bobine correcte</div>':'<div class="serr">✖ Bobine non confirmée — Retirer</div>')+'<div class="sdate">'+(d.scanned_at||'')+'</div>';
      if(t)t.textContent=(d.scanned_at||'').substring(11,19);
    }).catch(()=>{});
  },3000);
  setInterval(()=>window.location.reload(),15000);
  </script>

<?php elseif ($role==='line_worker'): ?>
  <?php $er=$ap&&(int)($ap['scannees']??0)>0; ?>
  <?php if ($er): ?>
  <div class="notif ng"><span class="ni">🚛</span><div><div class="nt">Préparez-vous, le robot arrive !</div>
  <div class="ns">Picklist <strong><?= sanitize($ap['picklist_code']) ?></strong> · <?= (int)($ap['scannees']??0) ?>/<?= (int)($ap['total']??0) ?> bobines</div></div></div>
  <?php elseif ($ap): ?>
  <div class="notif np"><span class="ni">📦</span><div><div class="nt">Chargement en cours au magasin</div>
  <div class="ns">Le chariot sera bientôt en route</div></div></div>
  <?php else: ?>
  <div class="notif nw"><span class="ni">⏳</span><div><div class="nt">En attente d'une livraison</div>
  <div class="ns">Le magasinier prépare le chargement…</div></div></div>
  <?php endif; ?>
  <form method="POST" onsubmit="return confirm('Confirmer le déchargement ?')"><input type="hidden" name="cmd" value="confirm_discharge">
  <button type="submit" class="lbtn lb-g" <?= ($is_em||!$ap)?'disabled':'' ?>><span class="li">✅</span><span>J'ai déchargé le chariot</span></button></form>
  <form method="POST" onsubmit="return confirm('Envoyer vers Zone 3 ?')"><input type="hidden" name="cmd" value="return_to_zone3">
  <button type="submit" class="lbtn lb-b" <?= $is_em?'disabled':'' ?>><span class="li">↩️</span><span>J'ai un retour — Envoyer zone 3</span></button></form>
  <script>setInterval(()=>window.location.reload(),10000);</script>
<?php endif; ?>
</div></body></html>
