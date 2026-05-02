<?php
// ============================================================
//  email_daemon.php — Vérificateur d'emails automatique
//  Lance en arrière-plan par LANCER_TOUT_LES_SERVICES.bat
//  Vérifie la boîte Gmail toutes les 30 secondes
//  Importe automatiquement les picklists reçues par email
//
//  Lancement manuel :
//    php email_daemon.php
//
//  Lancement en arrière-plan (Windows) :
//    start /B php email_daemon.php > email_daemon.log 2>&1
// ============================================================

// Pas de timeout
set_time_limit(0);
ini_set('memory_limit', '128M');

// Chemin du projet
define('PROJECT_DIR', __DIR__);

require_once PROJECT_DIR . '/database.php';
require_once PROJECT_DIR . '/picklist_importer.php';

// ── Config ───────────────────────────────────────────────────
define('IMAP_HOST',    '{imap.gmail.com:993/ssl/novalidate-cert}INBOX');
define('IMAP_USER',    'secondtest0002@gmail.com');
define('IMAP_PASS',    'cikj ttan kgrr puww');
define('CHECK_EVERY',  30);   // secondes entre chaque vérification
define('STATUS_FILE',  PROJECT_DIR . '/email_daemon_status.json');

// ── Logging ──────────────────────────────────────────────────
function daemon_log(string $msg): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    echo $line . PHP_EOL;
    // Écrire aussi dans le log fichier
    file_put_contents(PROJECT_DIR . '/email_daemon.log', $line . PHP_EOL, FILE_APPEND);
}

// ── Sauvegarder le statut (lu par email_status.php) ─────────
function save_status(array $data): void {
    $data['updated_at'] = date('Y-m-d H:i:s');
    file_put_contents(STATUS_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// ── Vérification email ────────────────────────────────────────
function check_emails(PDO $pdo): array {
    $result = [
        'checked_at' => date('Y-m-d H:i:s'),
        'found'      => 0,
        'imported'   => 0,
        'errors'     => 0,
        'details'    => [],
    ];

    if (!function_exists('imap_open')) {
        $result['details'][] = 'Extension PHP IMAP non activée';
        return $result;
    }

    $imap = @imap_open(IMAP_HOST, IMAP_USER, IMAP_PASS);
    if (!$imap) {
        $result['details'][] = 'Connexion IMAP échouée : ' . imap_last_error();
        return $result;
    }

    $emails = imap_search($imap, 'UNSEEN');

    if (!$emails) {
        $result['details'][] = 'Aucun email non lu';
        imap_close($imap);
        return $result;
    }

    $result['found'] = count($emails);

    foreach ($emails as $email_id) {
        $info      = imap_headerinfo($imap, $email_id);
        $subject   = imap_utf8($info->subject ?? '(sans objet)');
        $from      = ($info->from[0]->mailbox ?? '?') . '@' . ($info->from[0]->host ?? '?');
        $structure = imap_fetchstructure($imap, $email_id);

        if (!isset($structure->parts)) {
            imap_setflag_full($imap, $email_id, "\\Seen");
            continue;
        }

        $imported_this_email = false;
        foreach ($structure->parts as $part_num => $part) {
            $filename = '';
            foreach (($part->dparameters ?? []) as $dp) {
                if (strtolower($dp->attribute) === 'filename') $filename = $dp->value;
            }
            foreach (($part->parameters ?? []) as $p) {
                if (strtolower($p->attribute) === 'name') $filename = $p->value;
            }
            if (!$filename) continue;

            $filename = imap_utf8($filename);
            $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx','xls','csv','txt'])) continue;

            // Télécharger la pièce jointe
            $raw = imap_fetchbody($imap, $email_id, $part_num + 1);
            if ($part->encoding == 3) $raw = base64_decode($raw);
            if ($part->encoding == 4) $raw = quoted_printable_decode($raw);

            $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'agv_auto_' . time() . '_' . $email_id . '.' . $ext;
            file_put_contents($tmp, $raw);

            // Importer
            $res = import_picklist_file($tmp, $pdo, $ext);
            @unlink($tmp);

            if ($res['success']) {
                $result['imported']++;
                $imported_this_email = true;
                $detail = "✅ Importée depuis {$from} : {$res['picklist_code']} ({$res['count']} bobines)";
                $result['details'][] = $detail;
                daemon_log($detail);
            } else {
                $result['errors']++;
                $detail = "❌ Erreur depuis {$from} [{$filename}] : {$res['error']}";
                $result['details'][] = $detail;
                daemon_log($detail);
            }
        }

        imap_setflag_full($imap, $email_id, "\\Seen");
    }

    imap_close($imap);
    return $result;
}

// ── Boucle principale ────────────────────────────────────────
daemon_log('═══ Email Daemon démarré ═══');
daemon_log('Vérification toutes les ' . CHECK_EVERY . ' secondes');
daemon_log('Boîte : ' . IMAP_USER);

// Statut initial
save_status([
    'running'     => true,
    'pid'         => getmypid(),
    'started_at'  => date('Y-m-d H:i:s'),
    'check_every' => CHECK_EVERY,
    'last_check'  => null,
    'last_result' => null,
    'total_imported' => 0,
    'check_count' => 0,
]);

$totalImported = 0;
$checkCount    = 0;

while (true) {
    $checkCount++;
    daemon_log("Vérification #{$checkCount}...");

    try {
        $result = check_emails($pdo);
        $totalImported += $result['imported'];

        // Sauvegarder le statut pour l'interface web
        save_status([
            'running'        => true,
            'pid'            => getmypid(),
            'started_at'     => date('Y-m-d H:i:s'),
            'check_every'    => CHECK_EVERY,
            'last_check'     => $result['checked_at'],
            'last_result'    => $result,
            'total_imported' => $totalImported,
            'check_count'    => $checkCount,
        ]);

        if ($result['found'] === 0) {
            daemon_log("Aucun email non lu");
        } elseif ($result['imported'] > 0) {
            daemon_log("✅ {$result['imported']} picklist(s) importée(s)");
        }

    } catch (\Exception $e) {
        daemon_log("ERREUR : " . $e->getMessage());
        save_status([
            'running'     => true,
            'pid'         => getmypid(),
            'last_check'  => date('Y-m-d H:i:s'),
            'last_error'  => $e->getMessage(),
            'check_count' => $checkCount,
        ]);
    }

    daemon_log("Prochaine vérification dans " . CHECK_EVERY . "s...");
    sleep(CHECK_EVERY);
}
