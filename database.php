<?php
define('ROBOT_KEY', 'AGV_SAGEMCOM_SECRET_2025');
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'robot_inventaire';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:2rem;background:#FEF2F2;color:#991B1B;border-radius:8px;margin:2rem">
        <b>Erreur BDD</b> : '.htmlspecialchars($e->getMessage()).'<br><br>
        Vérifier que XAMPP est démarré.<br>
        <a href="install.php" style="color:#F59E0B">→ install.php</a>
    </div>');
}
function sanitize($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function require_login(...$roles){
    if (!isset($_SESSION['role'])){header('Location: login.php');exit();}
    if ($roles && !in_array($_SESSION['role'],$roles,true)){header('Location: login.php');exit();}
}
function get_current_shift(){$h=(int)date('H');if($h>=6&&$h<14)return 1;if($h>=14&&$h<22)return 2;return 3;}
function json_response($data,$code=200){http_response_code($code);header('Content-Type: application/json');echo json_encode($data);exit();}
