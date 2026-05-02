<?php
// ============================================================
//  api.php — Endpoint appelé par scan_robot.py sur le Pi
//  POST { key, barcode }
//  Compare le code-barres scanné avec picklist_lines.reference
//  Retourne : signal vert (OK) ou rouge (ERREUR)
//
//  NOTE inventaire :
//  La quantité n'est PAS incrémentée ici (scan = vérification).
//  Elle est mise à jour lors du confirm_discharge dans
//  mobile_dashboard.php, avec la vraie quantité de la ligne.
// ============================================================
include 'database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'POST only'], 405);

$key     = $_POST['key']    ?? '';
$barcode = trim($_POST['barcode'] ?? '');

if ($key !== ROBOT_KEY) json_response(['error' => 'Cle invalide'], 403);
if (!$barcode)          json_response(['error' => 'Code-barres vide'], 400);

try {
    // Chercher ce code-barres dans les lignes des picklists actives
    $stmt = $pdo->prepare("
        SELECT pl.*, ph.picklist_code, ph.mapa, ph.ligne_production,
               ph.code_pfin, ph.uf
        FROM   picklist_lines pl
        JOIN   picklist_header ph ON ph.id = pl.picklist_id
        WHERE  pl.reference = ?
        AND    ph.status IN ('active','preparing')
        AND    pl.status IN ('pending','scanned_error')
        LIMIT  1
    ");
    $stmt->execute([$barcode]);
    $line = $stmt->fetch();

    $shift = get_current_shift();

    if ($line) {
        // ── SIGNAL VERT ─────────────────────────────────────────
        $pdo->prepare("UPDATE picklist_lines SET status='scanned_ok', scanned_at=NOW() WHERE id=?")
            ->execute([$line['id']]);

        $pdo->prepare("UPDATE picklist_header SET status='preparing' WHERE id=? AND status='active'")
            ->execute([$line['picklist_id']]);

        // S'assurer que la bobine existe dans l'inventaire (sans modifier la quantité)
        // La quantité sera mise à jour lors du confirm_discharge
        $pdo->prepare("INSERT IGNORE INTO inventory (barcode, quantite) VALUES (?, 0)")
            ->execute([$barcode]);

        $pdo->prepare("INSERT INTO robot_activity
            (action, barcode, picklist_id, worker_id, shift_number, zone_from, zone_to, result)
            VALUES ('scan_ok', ?, ?, 0, ?, 'zone1', 'zone2', 'ok')")
            ->execute([$barcode, $line['picklist_id'], $shift]);

        json_response([
            'signal'        => 'green',
            'result'        => 'ok',
            'message'       => 'Bobine conforme',
            'barcode'       => $barcode,
            'reference'     => $line['reference'],
            'emplacement'   => $line['emplacement'],
            'nbre_us'       => $line['nbre_us'],
            'quantite'      => $line['quantite'],
            'picklist_code' => $line['picklist_code'],
            'ligne'         => $line['ligne_production'],
            'picklist_id'   => $line['picklist_id'],
        ]);

    } else {
        // ── SIGNAL ROUGE ────────────────────────────────────────
        $stmt2 = $pdo->prepare("
            SELECT pl.status, ph.picklist_code, ph.ligne_production
            FROM   picklist_lines pl
            JOIN   picklist_header ph ON ph.id = pl.picklist_id
            WHERE  pl.reference = ?
            AND    ph.status IN ('active','preparing','delivered')
            LIMIT  1
        ");
        $stmt2->execute([$barcode]);
        $already = $stmt2->fetch();

        $reason = 'Absente de la picklist active';
        if ($already && in_array($already['status'], ['scanned_ok','loaded'])) {
            $reason = 'Deja scannee dans cette picklist';
        }

        $pdo->prepare("INSERT INTO robot_activity
            (action, barcode, picklist_id, worker_id, shift_number, result)
            VALUES ('scan_error', ?, 0, 0, ?, 'error')")
            ->execute([$barcode, $shift]);

        json_response([
            'signal'  => 'red',
            'result'  => 'error',
            'message' => $reason,
            'barcode' => $barcode,
        ]);
    }

} catch (PDOException $e) {
    json_response(['error' => $e->getMessage()], 500);
}
