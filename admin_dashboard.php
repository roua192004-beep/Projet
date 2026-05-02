<?php
session_start();
include 'database.php';
require_once 'picklist_importer.php';
require_login('headworker');

$msg_ok  = '';
$msg_err = '';

// ══════════════════════════════════════════════════════════════
//  UPLOAD MANUEL (bouton dans la page)
// ══════════════════════════════════════════════════════════════
if (isset($_POST['upload_picklist']) && isset($_FILES['picklist_file'])) {
    $file = $_FILES['picklist_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $codes = [1=>'Fichier trop grand (php.ini)',2=>'Fichier trop grand (formulaire)',
                  3=>'Upload partiel',4=>'Aucun fichier',6=>'Dossier tmp manquant',
                  7=>'Impossible d ecrire'];
        $msg_err = 'Erreur upload : ' . ($codes[$file['error']] ?? 'Code '.$file['error']);
    } else {
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['xlsx','xls','csv','txt'];
        if (!in_array($ext, $allowed)) {
            $msg_err = "Format non supporté : .$ext — Acceptés : .xlsx .xls .csv";
        } else {
            $r = import_picklist_file($file['tmp_name'], $pdo, $ext);
            if ($r['success']) {
                $msg_ok = "Picklist <strong>{$r['picklist_code']}</strong> importée — {$r['count']} bobines chargées — Email de notification envoyé";
            } else {
                $msg_err = "Erreur import : " . $r['error'];
            }
        }
    }
}

// ══════════════════════════════════════════════════════════════
//  EXPORT EXCEL
// ══════════════════════════════════════════════════════════════
if (isset($_POST['export_xls'])) {
    $vendor = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($vendor)) {
        die('<p style="font-family:sans-serif;padding:2rem;color:red">Composer non installé. Tapez : <code>composer install</code><br><a href="admin_dashboard.php">Retour</a></p>');
    }
    require $vendor;

    $filter  = $_POST['filter_period'] ?? '24h';
    $allowed = ['8h'=>8,'24h'=>24,'48h'=>48,'168h'=>168];
    $shifts  = ['1','2','3'];

    if (array_key_exists($filter, $allowed)) {
        $st = $pdo->prepare("SELECT ra.*, u.full_name FROM robot_activity ra LEFT JOIN users u ON ra.worker_id=u.id WHERE ra.scanned_at>=DATE_SUB(NOW(),INTERVAL ? HOUR) ORDER BY ra.scanned_at DESC");
        $st->execute([$allowed[$filter]]);
    } elseif (in_array($filter, $shifts)) {
        $st = $pdo->prepare("SELECT ra.*, u.full_name FROM robot_activity ra LEFT JOIN users u ON ra.worker_id=u.id WHERE ra.shift_number=? ORDER BY ra.scanned_at DESC");
        $st->execute([$filter]);
    } else { die('Filtre invalide'); }

    $logs = $st->fetchAll();
    $fill = \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID;
    $ctr  = \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER;
    $sp   = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sh   = $sp->getActiveSheet(); $sh->setTitle('Activite AGV');

    $hdrs = ['Code-barres','Action','Worker','Zone','Poste','Decharge','Date'];
    foreach ($hdrs as $i=>$h) {
        $c = chr(65+$i);
        $sh->setCellValue($c.'1',$h);
        $sh->getStyle($c.'1')->getFont()->setBold(true);
        $sh->getStyle($c.'1')->getFill()->setFillType($fill)->getStartColor()->setRGB('1B3A6B');
        $sh->getStyle($c.'1')->getFont()->getColor()->setRGB('FFFFFF');
        $sh->getStyle($c.'1')->getAlignment()->setHorizontal($ctr);
        $sh->getColumnDimension($c)->setWidth(22);
    }
    $row=2;
    foreach ($logs as $l) {
        $sh->setCellValue('A'.$row,$l['barcode']);
        $sh->setCellValue('B'.$row,$l['action']);
        $sh->setCellValue('C'.$row,$l['worker_name']??($l['full_name']??'Systeme'));
        $sh->setCellValue('D'.$row,($l['zone_from']??'').($l['zone_to']?' -> '.$l['zone_to']:''));
        $sh->setCellValue('E'.$row,'Poste '.($l['shift_number']??''));
        $sh->setCellValue('F'.$row,$l['is_discharged']?'Oui':'Non');
        $sh->setCellValue('G'.$row,$l['scanned_at']);
        if($row%2===0) $sh->getStyle('A'.$row.':G'.$row)->getFill()->setFillType($fill)->getStartColor()->setRGB('f1f5f9');
        $row++;
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="activite_agv_'.date('Y-m-d').'.xlsx"');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sp))->save('php://output');
    exit();
}

// ══════════════════════════════════════════════════════════════
//  DONNÉES DASHBOARD
// ══════════════════════════════════════════════════════════════
$shift        = get_current_shift();
$robot_status = $pdo->query("SELECT * FROM robot_status WHERE id=1")->fetch();
$robot_online = (strtotime($robot_status['last_ping']??'2000-01-01') > time()-10);

$activity = $pdo->query("SELECT ra.*, u.full_name FROM robot_activity ra
    LEFT JOIN users u ON ra.worker_id=u.id
    WHERE ra.scanned_at>=DATE_SUB(NOW(),INTERVAL 8 HOUR)
    ORDER BY ra.scanned_at DESC LIMIT 100")->fetchAll();

$picklists_active = $pdo->query("SELECT ph.*,
    COUNT(pl.id) as total,
    SUM(CASE WHEN pl.status='scanned_ok' THEN 1 ELSE 0 END) as ok_count,
    TIMESTAMPDIFF(MINUTE, ph.imported_at, NOW()) as age_min
    FROM picklist_header ph
    LEFT JOIN picklist_lines pl ON pl.picklist_id=ph.id
    WHERE ph.status IN ('active','preparing','delivered')
    GROUP BY ph.id ORDER BY ph.imported_at DESC LIMIT 20")->fetchAll();

$movements   = $pdo->query("SELECT * FROM robot_movements ORDER BY started_at DESC LIMIT 50")->fetchAll();
$emergencies = $pdo->query("SELECT * FROM emergency_log ORDER BY created_at DESC LIMIT 20")->fetchAll();

$total_scan  = $pdo->query("SELECT COUNT(*) FROM robot_activity WHERE scanned_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)")->fetchColumn();
$total_del   = $pdo->query("SELECT COUNT(*) FROM picklist_header WHERE status='delivered'")->fetchColumn();
$total_act   = $pdo->query("SELECT COUNT(*) FROM picklist_header WHERE status IN ('active','preparing')")->fetchColumn();
$total_urg   = $pdo->query("SELECT COUNT(*) FROM emergency_log WHERE created_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="30">
<title>Admin — Robot AGV</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
<style>
:root{--bg:#F8FAFC;--sur:#FFFFFF;--sur2:#F1F5F9;--bdr:#E2E8F0;--acc:#1E3A8A;--blue:#3b82f6;--grn:#10b981;--red:#ef4444;--txt:#0F172A;--mut:#64748b;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;}
/* Topbar */
.topbar{background:var(--sur);border-bottom:1px solid var(--bdr);padding:.875rem 2rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;gap:1rem;flex-wrap:wrap;}
.brand{display:flex;align-items:center;gap:.75rem;}
.brand span{font-family:'Space Mono',monospace;font-size:1rem;color:var(--acc);}
.topright{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;}
.shift-b{background:rgba(59,130,246,.15);border:1px solid rgba(59,130,246,.3);color:var(--blue);padding:.3rem .75rem;border-radius:9999px;font-size:.72rem;font-weight:700;}
.rlive{display:flex;align-items:center;gap:.35rem;font-size:.72rem;color:var(--mut);}
.dot{width:7px;height:7px;border-radius:50%;}
.on{background:var(--grn);box-shadow:0 0 5px var(--grn);animation:pulse 2s infinite;}
.off{background:var(--red);}
.em{background:var(--red);animation:blink .5s infinite;}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0}}
.nav-a{color:var(--mut);text-decoration:none;font-size:.75rem;padding:.3rem .65rem;border:1px solid var(--bdr);border-radius:.4rem;}
.nav-a:hover{border-color:var(--acc);color:var(--acc);}
.out-btn{background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#f87171;padding:.35rem .875rem;border-radius:.4rem;text-decoration:none;font-size:.75rem;font-weight:600;}
/* Container */
.container{max-width:1400px;margin:0 auto;padding:2rem;}
/* Banners */
.banner{border-radius:.75rem;padding:.875rem 1.25rem;display:flex;align-items:flex-start;gap:.875rem;margin-bottom:1.25rem;}
.banner-test{background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);}
.banner-test h3{color:#fbbf24;font-size:.875rem;margin-bottom:.2rem;}
.banner-test p{color:var(--mut);font-size:.75rem;line-height:1.6;}
.banner-ok{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.25);}
.banner-ok p{color:#34d399;font-size:.875rem;}
.banner-err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);}
.banner-err p{color:#f87171;font-size:.875rem;}
/* Stats */
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.5rem;}
.sc{background:var(--sur);border:1px solid var(--bdr);border-radius:.875rem;padding:1.25rem;position:relative;overflow:hidden;}
.sc::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:var(--ac,var(--acc));}
.si{font-size:1.4rem;margin-bottom:.35rem;}
.sv{font-family:'Space Mono',monospace;font-size:1.6rem;font-weight:700;}
.sl{color:var(--mut);font-size:.7rem;margin-top:.15rem;text-transform:uppercase;letter-spacing:.05em;}
/* Main grid */
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;}
@media(max-width:900px){.grid2{grid-template-columns:1fr;}}
/* Panels */
.panel{background:var(--sur);border:1px solid var(--bdr);border-radius:.875rem;overflow:hidden;margin-bottom:1.5rem;}
.phdr{padding:1rem 1.5rem;border-bottom:1px solid var(--bdr);display:flex;align-items:center;justify-content:space-between;}
.ptitle{font-weight:700;font-size:.9rem;}
.pbody{padding:1.5rem;}
/* ──── UPLOAD ZONE ──── */
.upload-section{border:2px dashed var(--bdr);border-radius:.875rem;padding:1.75rem 1.5rem;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;position:relative;}
.upload-section:hover,.upload-section.drag{border-color:var(--acc);background:rgba(245,158,11,.04);}
.upload-section input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
.upload-icon{font-size:2.8rem;display:block;margin-bottom:.75rem;}
.upload-title{font-size:1rem;font-weight:700;color:var(--txt);margin-bottom:.35rem;}
.upload-sub{font-size:.78rem;color:var(--mut);line-height:1.7;}
.format-row{display:flex;gap:.5rem;justify-content:center;margin-top:.875rem;flex-wrap:wrap;}
.pill{font-size:.68rem;font-weight:700;padding:.2rem .65rem;border-radius:9999px;border:1px solid;}
.px{background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.35);color:#34d399;}
.pc{background:rgba(59,130,246,.1);border-color:rgba(59,130,246,.35);color:#93c5fd;}
.upload-submit{display:inline-block;margin-top:1rem;padding:.75rem 2rem;background:var(--acc);color:#0d0f14;border:none;border-radius:.5rem;font-weight:700;font-size:.9rem;cursor:pointer;font-family:inherit;transition:background .15s;}
.upload-submit:hover{background:#fbbf24;}
/* Email channel info */
.email-box{background:rgba(59,130,246,.06);border:1px solid rgba(59,130,246,.2);border-radius:.75rem;padding:1rem 1.25rem;font-size:.8rem;line-height:1.7;color:var(--mut);}
.email-box strong{color:#93c5fd;}
.email-box code{background:var(--sur2);padding:.1rem .4rem;border-radius:.25rem;font-size:.75rem;color:var(--txt);}
/* Export */
.exp-row{display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;}
.lbl{color:var(--mut);font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:.35rem;}
select{padding:.6rem .875rem;background:var(--sur2);border:1px solid var(--bdr);border-radius:.4rem;color:var(--txt);font-family:inherit;font-size:.875rem;outline:none;}
.btn-g{padding:.6rem 1.25rem;background:var(--grn);color:#fff;border:none;border-radius:.4rem;font-weight:700;font-size:.875rem;cursor:pointer;font-family:inherit;}
.btn-g:hover{background:#059669;}
/* Picklist mini list */
.pl-row{display:flex;align-items:center;gap:.75rem;padding:.5rem 0;border-bottom:1px solid rgba(42,47,61,.4);}
.pl-row:last-child{border-bottom:none;}
.pl-code{font-family:'Space Mono',monospace;font-size:.75rem;color:var(--txt);font-weight:700;}
.pl-meta{font-size:.68rem;color:var(--mut);}
.mp{height:4px;background:var(--bdr);border-radius:2px;overflow:hidden;margin-top:3px;width:80px;}
.mf{height:100%;background:var(--grn);border-radius:2px;}
/* Tables */
table{width:100%;border-collapse:collapse;font-size:.8rem;}
thead th{padding:.625rem 1rem;text-align:left;color:var(--mut);font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid var(--bdr);}
tbody td{padding:.75rem 1rem;border-bottom:1px solid rgba(42,47,61,.35);}
tbody tr:last-child td{border-bottom:none;}
tbody tr:hover{background:rgba(255,255,255,.02);}
.mono{font-family:'Space Mono',monospace;font-size:.72rem;}
.badge{display:inline-block;padding:.15rem .5rem;border-radius:9999px;font-size:.68rem;font-weight:700;}
.bg{background:rgba(16,185,129,.15);color:#34d399;}
.by{background:rgba(245,158,11,.15);color:#fbbf24;}
.br{background:rgba(239,68,68,.15);color:#f87171;}
.bb{background:rgba(59,130,246,.15);color:#93c5fd;}
.scroll{max-height:340px;overflow-y:auto;}
.scroll::-webkit-scrollbar{width:4px;}
.scroll::-webkit-scrollbar-thumb{background:var(--bdr);border-radius:2px;}
/* Tabs */
.tabs{display:flex;border-bottom:1px solid var(--bdr);padding:0 1.5rem;overflow-x:auto;}
.tbtn{padding:.75rem 1rem;background:none;border:none;border-bottom:2px solid transparent;color:var(--mut);font-family:inherit;font-size:.8rem;font-weight:600;cursor:pointer;margin-bottom:-1px;white-space:nowrap;}
.tbtn.active{color:var(--acc);border-bottom-color:var(--acc);}
.tcon{display:none;padding:1.5rem;}
.tcon.active{display:block;}
/* Mini progress inline */
.prog-w{height:4px;background:var(--bdr);border-radius:2px;overflow:hidden;margin-top:3px;}
.prog-f{height:100%;background:var(--grn);border-radius:2px;}
</style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
  <div class="brand"><span>🤖</span><span>ROBOT AGV — Admin</span></div>
  <div class="topright">
    <span class="shift-b">Poste <?= $shift ?></span>
    <?php $m=$robot_status['mode']??'offline';$dc=$m==='emergency'?'em':($robot_online?'on':'off'); ?>
    <div class="rlive"><div class="dot <?=$dc?>"></div><?=strtoupper($m)?> — <?=sanitize($robot_status['current_zone']??'?')?></div>
    <?php if(!$robot_online):?><span class="badge by" style="font-size:.68rem">MODE TEST</span><?php endif;?>
    <a href="picklist_manager.php" class="nav-a">📋 Picklists</a>
    <a href="inventory_manager.php" class="nav-a">📦 Inventaire</a>
    <a href="emergency_panel.php" class="nav-a" style="color:#f87171;border-color:rgba(239,68,68,.3)">🚨 Urgences</a>
    <a href="logout.php" class="out-btn">Déconnexion</a>
  </div>
</div>

<div class="container">

<!-- Bandeau mode test -->
<?php if(!$robot_online):?>
<div class="banner banner-test">
  <div style="font-size:1.5rem;flex-shrink:0">🧪</div>
  <div>
    <h3>MODE TEST — Robot hors ligne</h3>
    <p>Le robot ne répond pas. Testez l importation des picklists et la détection par caméra
    avec <code>scan_robot_PC.py</code> depuis votre PC. Le système est pleinement fonctionnel
    dès que le Raspberry Pi sera connecté au réseau.</p>
  </div>
</div>
<?php endif;?>

<!-- Messages upload -->
<?php if($msg_ok):?>
<div class="banner banner-ok"><div style="font-size:1.25rem">✅</div><p><?=$msg_ok?></p></div>
<?php endif;?>
<?php if($msg_err):?>
<div class="banner banner-err"><div style="font-size:1.25rem">⚠️</div><p><?=sanitize($msg_err)?></p></div>
<?php endif;?>

<!-- STAT CARDS -->
<div class="stats">
  <div class="sc" style="--ac:#10b981"><div class="si">✅</div><div class="sv"><?=$total_del?></div><div class="sl">Livrées</div></div>
  <div class="sc" style="--ac:#3b82f6"><div class="si">⏳</div><div class="sv"><?=$total_act?></div><div class="sl">Actives</div></div>
  <div class="sc" style="--ac:#ef4444"><div class="si">🚨</div><div class="sv"><?=$total_urg?></div><div class="sl">Urgences 24h</div></div>
</div>

<!-- GRID : UPLOAD + EXPORT -->
<div class="grid2">

  <!-- ── CANAL 1 : UPLOAD MANUEL ──────────────────────────── -->
  <div class="panel">
    <div class="phdr">
      <span class="ptitle">📤 Canal 1 — Upload Manuel</span>
      <span class="badge bg">Admin → Système</span>
    </div>
    <div class="pbody">
      <form method="POST" enctype="multipart/form-data" id="upform">
        <div class="upload-section" id="dropzone">
          <input type="file" name="picklist_file" id="file-inp"
                 accept=".xlsx,.xls,.csv,.txt"
                 onchange="onFileChosen(this)">
          <span class="upload-icon">📋</span>
          <div class="upload-title" id="uplabel">Glissez votre fichier ici</div>
          <div class="upload-sub">
            ou cliquez pour parcourir<br>
            Format Sagemcom : MAPA / PICK LIST / UF / Ligne / Code Pfin
          </div>
          <div class="format-rows" style="display:flex;gap:.5rem;justify-content:center;margin-top:.875rem;flex-wrap:wrap">
            <span class="pill px">📊 .xlsx</span>
            <span class="pill px">📊 .xls</span>
            <span class="pill pc">📄 .csv</span>
          </div>
        </div>
        <div style="text-align:center;margin-top:1rem">
          <button type="submit" name="upload_picklist" class="upload-submit">
            ⬆️ Importer la picklist
          </button>
        </div>
        <p style="font-size:.7rem;color:var(--mut);text-align:center;margin-top:.75rem;line-height:1.6">
          Après import : picklist visible sur l app mobile magasinier<br>
          + notification email envoyée automatiquement
        </p>
      </form>
    </div>
  </div>

  <!-- ── CANAL 2 : EMAIL AUTOMATIQUE (DAEMON 30s) ─────────── -->
  <div class="panel">
    <div class="phdr">
      <span class="ptitle">📧 Canal 2 — Email Automatique</span>
      <span class="badge bb" id="daemon-badge">Chargement...</span>
    </div>
    <div class="pbody">

      <!-- Widget statut daemon -->
      <div id="daemon-widget" style="background:var(--sur2);border:1px solid var(--bdr);border-radius:.75rem;padding:1rem;margin-bottom:1rem">

        <!-- Statut + countdown -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem">
          <div style="display:flex;align-items:center;gap:.6rem">
            <div id="daemon-dot" style="width:10px;height:10px;border-radius:50%;background:#374151;transition:background .3s"></div>
            <span id="daemon-status-txt" style="font-size:.82rem;font-weight:700">Vérification...</span>
          </div>
          <div style="text-align:right">
            <div style="font-size:.65rem;color:var(--mut)">Prochaine vérification dans</div>
            <div id="daemon-countdown" style="font-family:'Space Mono',monospace;font-size:1.1rem;font-weight:700;color:var(--acc)">—</div>
          </div>
        </div>

        <!-- Stats -->
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.5rem;margin-bottom:.75rem">
          <div style="background:var(--bg);border-radius:.4rem;padding:.5rem;text-align:center">
            <div id="stat-checks" style="font-family:'Space Mono',monospace;font-size:.9rem;font-weight:700">—</div>
            <div style="font-size:.6rem;color:var(--mut);text-transform:uppercase">Vérifications</div>
          </div>
          <div style="background:var(--bg);border-radius:.4rem;padding:.5rem;text-align:center">
            <div id="stat-imported" style="font-family:'Space Mono',monospace;font-size:.9rem;font-weight:700;color:var(--grn)">—</div>
            <div style="font-size:.6rem;color:var(--mut);text-transform:uppercase">Importées</div>
          </div>
          <div style="background:var(--bg);border-radius:.4rem;padding:.5rem;text-align:center">
            <div id="stat-last" style="font-size:.68rem;font-weight:700">—</div>
            <div style="font-size:.6rem;color:var(--mut);text-transform:uppercase">Dernière vérif.</div>
          </div>
        </div>

        <!-- Dernier résultat -->
        <div id="daemon-last-result" style="font-size:.72rem;color:var(--mut);line-height:1.6;min-height:1rem"></div>

        <!-- Adresse + actions -->
        <div style="margin-top:.75rem;padding-top:.75rem;border-top:1px solid var(--bdr);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem">
          <div style="font-size:.68rem;color:var(--mut)">
            📬 <span style="color:var(--acc);font-family:'Space Mono',monospace;font-size:.65rem">secondtest0002@gmail.com</span>
          </div>
          <div style="display:flex;gap:.4rem">
            <a href="email_daemon.php" target="_blank"
               style="font-size:.68rem;padding:.28rem .65rem;background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3);color:#fbbf24;border-radius:.35rem;text-decoration:none">
              🔄 Relancer le daemon
            </a>
            <a href="auto_import_picklist.php" target="_blank"
               style="font-size:.68rem;padding:.28rem .65rem;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.3);color:#93c5fd;border-radius:.35rem;text-decoration:none">
              ▶ Test manuel
            </a>
          </div>
        </div>
      </div>

            <!-- Résumé picklists actives -->
      <div style="margin-top:1.5rem">
        <div style="font-size:.72rem;font-weight:700;color:var(--mut);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.75rem">
          Picklists actives (<?=count($picklists_active)?>)
        </div>
        <?php if(empty($picklists_active)):?>
        <p style="font-size:.8rem;color:var(--mut)">Aucune picklist active. Importez un fichier ci-contre ou par email.</p>
        <?php else: foreach($picklists_active as $ph):
          $pct=($ph['total']>0)?round($ph['ok_count']/$ph['total']*100):0;
          $sc=['active'=>'by','preparing'=>'bb','delivered'=>'bg'];
          $rem=max(0,60-(int)$ph['age_min']);
        ?>
        <div class="pl-row">
          <div style="flex:1;min-width:0">
            <div class="pl-code"><?=sanitize($ph['picklist_code'])?></div>
            <div class="pl-meta"><?=sanitize($ph['ligne_production'])?> — <?=sanitize($ph['code_pfin'])?></div>
            <div class="mp"><div class="mf" style="width:<?=$pct?>%"></div></div>
          </div>
          <div style="text-align:right;flex-shrink:0">
            <span class="badge <?=$sc[$ph['status']]??'by'?>"><?=sanitize($ph['status'])?></span>
            <div style="font-size:.65rem;color:var(--mut);margin-top:.2rem"><?=$ph['ok_count']?>/<?=$ph['total']?> — ⏱<?=$rem?>min</div>
          </div>
        </div>
        <?php endforeach; endif;?>
      </div>
    </div>
  </div>

</div><!-- /grid2 -->

<!-- EXPORT EXCEL -->
<div class="panel">
  <div class="phdr"><span class="ptitle">📥 Exporter les données</span></div>
  <div class="pbody">
    <form method="POST" class="exp-row">
      <div>
        <span class="lbl">Période</span>
        <select name="filter_period">
          <option value="8h">Dernières 8h</option>
          <option value="24h" selected>Dernières 24h</option>
          <option value="48h">Dernières 48h</option>
          <option value="168h">7 jours</option>
          <option value="1">Poste 1 (00h-08h)</option>
          <option value="2">Poste 2 (08h-16h)</option>
          <option value="3">Poste 3 (16h-00h)</option>
        </select>
      </div>
      <div><span class="lbl">&nbsp;</span><button type="submit" name="export_xls" class="btn-g">⬇ Télécharger .xlsx</button></div>
    </form>
  </div>
</div>

<!-- HISTORIQUE EN ONGLETS -->
<div class="panel">
  <div class="tabs">
    <button class="tbtn active" onclick="sw('act',this)">Activité (8h)</button>
    <button class="tbtn" onclick="sw('pkl',this)">Picklists</button>
    <button class="tbtn" onclick="sw('mov',this)">Trajets</button>
    <button class="tbtn" onclick="sw('urg',this)">Urgences</button>
  </div>

  <!-- Activité -->
  <div id="tab-act" class="tcon active">
    <div class="scroll"><table>
      <thead><tr><th>Code-barres</th><th>Action</th><th>Worker</th><th>Zone</th><th>Poste</th><th>Date</th></tr></thead>
      <tbody>
      <?php foreach($activity as $a):?>
      <tr>
        <td class="mono"><?=sanitize($a['barcode'])?></td>
        <td><?=sanitize($a['action'])?></td>
        <td><?=sanitize($a['worker_name']??($a['full_name']??'Système'))?></td>
        <td><?=sanitize(($a['zone_from']??'').($a['zone_to']?' → '.$a['zone_to']:''))?></td>
        <td><span class="badge bb">P<?=sanitize($a['shift_number'])?></span></td>
        <td style="color:var(--mut);font-size:.72rem"><?=sanitize($a['scanned_at'])?></td>
      </tr>
      <?php endforeach;?>
      <?php if(empty($activity)):?><tr><td colspan="6" style="text-align:center;color:var(--mut);padding:2rem">Aucune activité</td></tr><?php endif;?>
      </tbody>
    </table></div>
  </div>

  <!-- Picklists -->
  <div id="tab-pkl" class="tcon">
    <div class="scroll"><table>
      <thead><tr><th>Code</th><th>MAPA</th><th>UF</th><th>Ligne</th><th>Pfin</th><th>Bobines</th><th>Statut</th><th>Source</th><th>Importée le</th></tr></thead>
      <tbody>
      <?php foreach($picklists_active as $ph):
        $p2=($ph['total']>0)?round($ph['ok_count']/$ph['total']*100):0;
        $sc=['active'=>'by','preparing'=>'bb','delivered'=>'bg'];
        // Détecter la source (email vs upload manuel) depuis robot_activity
        $src_q=$pdo->prepare("SELECT action FROM robot_activity WHERE picklist_id=? AND action='import_picklist' LIMIT 1");
        $src_q->execute([$ph['id']]);
        $src_action = $src_q->fetchColumn();
        $src_label  = $src_action ? '⬆️ Upload' : '📧 Email';
      ?>
      <tr>
        <td class="mono"><?=sanitize($ph['picklist_code'])?></td>
        <td><?=sanitize($ph['mapa'])?></td>
        <td><?=sanitize($ph['uf'])?></td>
        <td><?=sanitize($ph['ligne_production'])?></td>
        <td><?=sanitize($ph['code_pfin'])?></td>
        <td>
          <span style="font-size:.72rem"><?=$ph['ok_count']?>/<?=$ph['total']?> (<?=$p2?>%)</span>
          <div class="prog-w"><div class="prog-f" style="width:<?=$p2?>%"></div></div>
        </td>
        <td><span class="badge <?=$sc[$ph['status']]??'by'?>"><?=sanitize($ph['status'])?></span></td>
        <td><span style="font-size:.72rem"><?=$src_label?></span></td>
        <td style="color:var(--mut);font-size:.68rem"><?=sanitize($ph['imported_at'])?></td>
      </tr>
      <?php endforeach;?>
      <?php if(empty($picklists_active)):?><tr><td colspan="9" style="text-align:center;color:var(--mut);padding:2rem">Aucune picklist — importez un fichier Excel/CSV</td></tr><?php endif;?>
      </tbody>
    </table></div>
  </div>

  <!-- Trajets -->
  <div id="tab-mov" class="tcon">
    <div class="scroll"><table>
      <thead><tr><th>De</th><th>Vers</th><th>Durée</th><th>Statut</th><th>Début</th><th>Fin</th></tr></thead>
      <tbody>
      <?php foreach($movements as $mv):?>
      <tr>
        <td><?=sanitize($mv['from_zone'])?></td>
        <td><?=sanitize($mv['to_zone'])?></td>
        <td><?=$mv['duration_s']?$mv['duration_s'].'s':'—'?></td>
        <td><?php $mc=['in_progress'=>'bb','completed'=>'bg','aborted'=>'br'];
          echo "<span class='badge ".($mc[$mv['status']]??'by')."'>".sanitize($mv['status'])."</span>";?></td>
        <td style="color:var(--mut);font-size:.72rem"><?=sanitize($mv['started_at'])?></td>
        <td style="color:var(--mut);font-size:.72rem"><?=sanitize($mv['ended_at']??'—')?></td>
      </tr>
      <?php endforeach;?>
      <?php if(empty($movements)):?><tr><td colspan="6" style="text-align:center;color:var(--mut);padding:2rem">Aucun trajet</td></tr><?php endif;?>
      </tbody>
    </table></div>
  </div>

  <!-- Urgences -->
  <div id="tab-urg" class="tcon">
    <div class="scroll"><table>
      <thead><tr><th>Type</th><th>Déclenché par</th><th>Position</th><th>Résolu</th><th>Date</th></tr></thead>
      <tbody>
      <?php foreach($emergencies as $e):?>
      <tr>
        <td><span class="badge br"><?=sanitize($e['trigger_type'])?></span></td>
        <td><?=sanitize($e['triggered_by'])?></td>
        <td><?=sanitize($e['robot_position'])?></td>
        <td><?=$e['resolved_at']?'<span class="badge bg">Oui</span>':'<span class="badge by">Non</span>'?></td>
        <td style="color:var(--mut);font-size:.72rem"><?=sanitize($e['created_at'])?></td>
      </tr>
      <?php endforeach;?>
      <?php if(empty($emergencies)):?><tr><td colspan="5" style="text-align:center;color:var(--mut);padding:2rem">Aucune urgence</td></tr><?php endif;?>
      </tbody>
    </table></div>
  </div>
</div>

</div><!-- /container -->

<script>
function sw(id,btn){
  document.querySelectorAll('.tcon').forEach(t=>t.classList.remove('active'));
  document.querySelectorAll('.tbtn').forEach(b=>b.classList.remove('active'));
  document.getElementById('tab-'+id).classList.add('active');
  btn.classList.add('active');
}

// Drag & drop
const dz   = document.getElementById('dropzone');
const finp = document.getElementById('file-inp');
const lbl  = document.getElementById('uplabel');

['dragenter','dragover'].forEach(ev=>dz.addEventListener(ev,e=>{e.preventDefault();dz.classList.add('drag');}));
['dragleave','drop'].forEach(ev=>dz.addEventListener(ev,e=>{e.preventDefault();dz.classList.remove('drag');}));
dz.addEventListener('drop',e=>{
  const f=e.dataTransfer.files;
  if(f.length){
    // Simuler la sélection du fichier
    const dt=new DataTransfer();
    dt.items.add(f[0]);
    finp.files=dt.files;
    onFileChosen(finp);
  }
});

function onFileChosen(inp){
  if(!inp.files.length) return;
  const f=inp.files[0];
  const ext=f.name.split('.').pop().toLowerCase();
  const icons={xlsx:'📊',xls:'📊',csv:'📄',txt:'📄'};
  lbl.textContent=(icons[ext]||'📋')+' '+f.name+'  ('+( f.size/1024).toFixed(1)+' Ko)';
  lbl.style.color='var(--acc)';
}

// ── Daemon Email — Polling statut toutes les 5s ──────────────
let daemonLastCheck = null;
let daemonCheckEvery = 30;
let countdownInterval = null;

function updateDaemonUI(data) {
  const running = data.running;
  const dot     = document.getElementById('daemon-dot');
  const badge   = document.getElementById('daemon-badge');
  const txt     = document.getElementById('daemon-status-txt');
  const result  = document.getElementById('daemon-last-result');
  const lastEl  = document.getElementById('stat-last');

  // Dot + badge
  if (running) {
    dot.style.background   = '#10b981';
    dot.style.boxShadow    = '0 0 6px #10b981';
    badge.textContent      = '● Actif';
    badge.className        = 'badge bg';
    txt.textContent        = 'Daemon actif — vérification automatique';
    txt.style.color        = '#34d399';
  } else {
    dot.style.background   = '#ef4444';
    dot.style.boxShadow    = 'none';
    badge.textContent      = '○ Arrêté';
    badge.className        = 'badge br';
    txt.textContent        = 'Daemon arrêté — relancez LANCER_TOUT_LES_SERVICES.bat';
    txt.style.color        = '#f87171';
  }

  // Stats
  if (data.check_count !== undefined) {
    document.getElementById('stat-checks').textContent  = data.check_count;
  }
  if (data.total_imported !== undefined) {
    document.getElementById('stat-imported').textContent = data.total_imported;
  }
  if (data.last_check) {
    const t = new Date(data.last_check.replace(' ', 'T'));
    lastEl.textContent = t.toLocaleTimeString('fr-FR');
    daemonLastCheck = t;
    daemonCheckEvery = data.check_every || 30;
  }

  // Dernier résultat
  if (data.last_result) {
    const r = data.last_result;
    if (r.imported > 0) {
      result.innerHTML = '<span style="color:#34d399">✅ ' + r.imported + ' picklist(s) importée(s) à ' + lastEl.textContent + '</span>';
      result.innerHTML += '<br>' + (r.details || []).join('<br>');
    } else if (r.found === 0) {
      result.innerHTML = '<span style="color:var(--mut)">Aucun email non lu à ' + lastEl.textContent + '</span>';
    } else if (r.errors > 0) {
      result.innerHTML = '<span style="color:#f87171">⚠️ ' + r.errors + ' erreur(s) : ' + (r.details || []).join(' / ') + '</span>';
    }
  }

  // Countdown
  if (countdownInterval) clearInterval(countdownInterval);
  if (running && daemonLastCheck) {
    countdownInterval = setInterval(() => {
      const elapsed = (Date.now() - daemonLastCheck.getTime()) / 1000;
      const remaining = Math.max(0, daemonCheckEvery - elapsed);
      const el = document.getElementById('daemon-countdown');
      if (el) el.textContent = Math.ceil(remaining) + 's';
    }, 500);
  } else {
    const el = document.getElementById('daemon-countdown');
    if (el) el.textContent = '—';
  }
}

function pollDaemon() {
  fetch('email_status.php')
    .then(r => r.json())
    .then(data => updateDaemonUI(data))
    .catch(() => {
      const badge = document.getElementById('daemon-badge');
      if (badge) { badge.textContent = '? Inconnu'; badge.className = 'badge by'; }
    });
}

// Poll toutes les 5s
pollDaemon();
setInterval(pollDaemon, 5000);

</script>
</body>
</html>
