<?php
session_start(); include 'database.php';
require_login('headworker');
$msg=''; $mt='ok';
if ($_SERVER['REQUEST_METHOD']==='POST'){
    $a=$_POST['action']??'';
    if ($a==='emergency_stop'){$pdo->exec("UPDATE robot_status SET is_emergency=1 WHERE id=1");$pdo->exec("INSERT INTO emergency_log (trigger_type,triggered_by) VALUES ('manual','admin')");$pdo->prepare("INSERT INTO robot_commands (command,created_by) VALUES ('emergency_stop',0)")->execute();$msg="Arrêt d'urgence activé.";$mt='err';}
    elseif ($a==='resume'){$pdo->exec("UPDATE robot_status SET is_emergency=0 WHERE id=1");$pdo->exec("UPDATE emergency_log SET resolved_at=NOW() WHERE resolved_at IS NULL");$pdo->prepare("INSERT INTO robot_commands (command,created_by) VALUES ('resume',0)")->execute();$msg='Robot remis en fonctionnement normal.';}
    elseif (in_array($a,['manual_forward','manual_backward','manual_left','manual_right','manual_stop'],true)){$pdo->prepare("INSERT INTO robot_commands (command,created_by) VALUES (?,0)")->execute([$a]);$msg='Commande envoyée : '.$a;}
}
$rs=$pdo->query("SELECT * FROM robot_status WHERE id=1")->fetch();
$is_em=(bool)($rs['is_emergency']??false); $online=$rs['last_ping']&&(strtotime('now')-strtotime($rs['last_ping']))<10;
$urgences=$pdo->query("SELECT * FROM emergency_log ORDER BY created_at DESC LIMIT 20")->fetchAll();
?>
<!DOCTYPE html><html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Panneau d'Urgence — AGV</title>
<?php include '_css.php'; ?>
<style>
.ub{width:100%;padding:1.25rem;border:none;border-radius:var(--r);font-family:inherit;font-size:1.1rem;font-weight:800;cursor:pointer;transition:all .15s;display:flex;align-items:center;justify-content:center;gap:.75rem;margin-bottom:.75rem}
.u-stop{background:var(--red);color:#fff;box-shadow:0 4px 16px rgba(239,68,68,.35)}
.u-stop:hover{background:var(--red-d)}
.u-res{background:var(--grn);color:#fff;box-shadow:0 4px 14px rgba(16,185,129,.3)}
.u-res:hover{background:var(--grn-d)}
.joy{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.5rem;max-width:220px;margin:1rem auto 0}
.jb{background:var(--sur2);border:1.5px solid var(--bdr);border-radius:var(--rsm);padding:.625rem;font-size:1.2rem;cursor:pointer;transition:all .15s;text-align:center}
.jb:hover{background:var(--acc-l);border-color:var(--acc)}
.smi{background:var(--sur2);border:1px solid var(--bdr);border-radius:var(--rsm);padding:.875rem;text-align:center}
.sv{font-size:1.2rem;font-weight:700;color:var(--txt)}.sl{font-size:.65rem;color:var(--mut);text-transform:uppercase;letter-spacing:.04em;margin-top:.2rem}
</style>
</head><body>
<nav class="nav">
  <div class="nav-brand"><div class="nav-logo">🚨</div><div><div class="nav-title">PANNEAU D'URGENCE</div></div></div>
  <div class="nav-links"><a href="admin_dashboard.php" class="nl">← Retour</a></div>
</nav>
<div class="container" style="max-width:800px">
<?php if ($msg): ?><div class="alert <?= $mt==='ok'?'a-ok':'a-err' ?>"><?= $mt==='ok'?'✅':'🚨' ?> <?= sanitize($msg) ?></div><?php endif; ?>

<div class="panel">
  <div class="ph"><span class="pt">🤖 État du robot</span>
  <span style="font-size:.72rem;color:var(--mut)">Dernier ping : <?= $rs['last_ping']?date('H:i:s',strtotime($rs['last_ping'])):'—' ?></span></div>
  <div class="pb"><div class="g4">
    <div class="smi"><div class="sv" style="color:<?= $is_em?'var(--red)':($online?'var(--grn)':'var(--mut)') ?>"><?= $is_em?'URGENCE':strtoupper(sanitize($rs['mode']??'auto')) ?></div><div class="sl">Mode</div></div>
    <div class="smi"><div class="sv"><?= sanitize($rs['zone']??'zone1') ?></div><div class="sl">Zone</div></div>
    <div class="smi"><div class="sv">🔋 <?= (int)($rs['battery_pct']??0) ?>%</div><div class="sl">Batterie</div></div>
    <div class="smi"><div class="sv">📶 <?= (int)($rs['wifi_strength']??0) ?>%</div><div class="sl">WiFi</div></div>
  </div></div>
</div>

<div class="panel">
  <div class="ph"><span class="pt">🔴 Contrôle d'urgence</span></div>
  <div class="pb">
    <form method="POST"><input type="hidden" name="action" value="emergency_stop">
    <button type="submit" class="ub u-stop" onclick="return confirm('Déclencher l\'arrêt d\'urgence ?')">⊘ ARRÊT D'URGENCE</button></form>
    <form method="POST"><input type="hidden" name="action" value="resume">
    <button type="submit" class="ub u-res" onclick="return confirm('Reprendre le fonctionnement normal ?')">✅ Reprendre le fonctionnement normal</button></form>
  </div>
</div>

<div class="panel">
  <div class="ph"><span class="pt">🕹️ Contrôle manuel</span></div>
  <div class="pb">
    <p style="font-size:.8rem;color:var(--mut);margin-bottom:.5rem">Contrôle direct du déplacement du robot</p>
    <div class="joy">
      <div></div>
      <form method="POST"><input type="hidden" name="action" value="manual_forward"><button type="submit" class="jb">⬆️</button></form>
      <div></div>
      <form method="POST"><input type="hidden" name="action" value="manual_left"><button type="submit" class="jb">⬅️</button></form>
      <form method="POST"><input type="hidden" name="action" value="manual_stop"><button type="submit" class="jb">⏹️</button></form>
      <form method="POST"><input type="hidden" name="action" value="manual_right"><button type="submit" class="jb">➡️</button></form>
      <div></div>
      <form method="POST"><input type="hidden" name="action" value="manual_backward"><button type="submit" class="jb">⬇️</button></form>
      <div></div>
    </div>
  </div>
</div>

<div class="panel">
  <div class="ph"><span class="pt">📋 Historique urgences</span></div>
  <div class="tbl-wrap"><table>
    <thead><tr><th>Type</th><th>Déclenché par</th><th>Position</th><th>Résolu</th><th>Date</th></tr></thead>
    <tbody>
    <?php if (empty($urgences)): ?><tr><td colspan="5" class="tbl-empty">Aucune urgence</td></tr>
    <?php else: foreach ($urgences as $u): ?>
      <tr>
        <td><span class="badge br"><?= sanitize($u['trigger_type']??'') ?></span></td>
        <td><?= sanitize($u['triggered_by']??'') ?></td>
        <td><?= sanitize($u['robot_position']??'—') ?></td>
        <td><?= $u['resolved_at']?'<span class="badge bg">Résolu</span>':'<span class="badge bo">En cours</span>' ?></td>
        <td style="font-size:.72rem;color:var(--mut)"><?= sanitize($u['created_at']??'') ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table></div>
</div>
</div>
<script>setInterval(()=>window.location.reload(),5000);</script>
</body></html>
