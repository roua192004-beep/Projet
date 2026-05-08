<?php
define('ROBOT_KEY', 'AGV_SAGEMCOM_SECRET_2025');
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'robot_inventaire';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

$is_cloud = ($host !== 'localhost' && $host !== '127.0.0.1');

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_TIMEOUT => 10,
];

// Aiven & cloud MySQL providers require SSL
if ($is_cloud) {
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    $options[PDO::MYSQL_ATTR_SSL_CA] = '';
}

$max_retries = $is_cloud ? 3 : 1;
$pdo = null;

for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        break; // success
    } catch (PDOException $e) {
        if ($attempt === $max_retries) {
            // Provide a useful error depending on environment
            if ($is_cloud) {
                die('<div style="font-family:\'Inter\',sans-serif;max-width:520px;margin:3rem auto;padding:2rem;background:#FEF2F2;color:#991B1B;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.08)">
                    <h3 style="margin:0 0 .8rem">⚠️ Erreur de connexion BDD</h3>
                    <p style="font-size:14px;line-height:1.6;margin:0 0 1rem">'.htmlspecialchars($e->getMessage()).'</p>
                    <details style="font-size:13px;color:#7F1D1D;cursor:pointer">
                        <summary style="font-weight:600">Causes possibles</summary>
                        <ul style="margin:.5rem 0;padding-left:1.2rem;line-height:1.8">
                            <li>Le service MySQL cloud (Aiven) a expiré ou est suspendu</li>
                            <li>Les variables d\'environnement DB_HOST / DB_PORT / DB_NAME / DB_USER / DB_PASS sont incorrectes</li>
                            <li>Le pare-feu du service cloud bloque la connexion</li>
                        </ul>
                        <p style="margin:.5rem 0">Vérifiez votre tableau de bord Aiven / Render et mettez à jour les variables d\'environnement si nécessaire.</p>
                    </details>
                </div>');
            } else {
                die('<div style="font-family:sans-serif;padding:2rem;background:#FEF2F2;color:#991B1B;border-radius:8px;margin:2rem">
                    <b>Erreur BDD</b> : '.htmlspecialchars($e->getMessage()).'<br><br>
                    Vérifier que XAMPP est démarré.<br>
                    <a href="install.php" style="color:#F59E0B">→ install.php</a>
                </div>');
            }
        }
        // Wait before retry (500ms, 1s, 1.5s)
        usleep($attempt * 500000);
    }
}

function sanitize($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function require_login(...$roles){
    if (!isset($_SESSION['role'])){header('Location: login.php');exit();}
    if ($roles && !in_array($_SESSION['role'],$roles,true)){header('Location: login.php');exit();}
}
function get_current_shift(){$h=(int)date('H');if($h>=6&&$h<14)return 1;if($h>=14&&$h<22)return 2;return 3;}
function json_response($data,$code=200){http_response_code($code);header('Content-Type: application/json');echo json_encode($data);exit();}
