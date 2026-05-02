<?php include 'database.php'; header('Content-Type: application/json');
if(($_POST['key']??'')!==ROBOT_KEY){echo json_encode(['ok'=>false]);exit();}
$slot=(int)($_POST['slot_id']??0); $occ=(int)($_POST['occupied']??0);
if($slot){try{$pdo->exec("CREATE TABLE IF NOT EXISTS agv_slot_status (id INT AUTO_INCREMENT PRIMARY KEY,slot_id INT,occupied TINYINT DEFAULT 0,updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");$ex=$pdo->prepare("SELECT id FROM agv_slot_status WHERE slot_id=?");$ex->execute([$slot]);if($ex->fetch()){$pdo->prepare("UPDATE agv_slot_status SET occupied=?,updated_at=NOW() WHERE slot_id=?")->execute([$occ,$slot]);}else{$pdo->prepare("INSERT INTO agv_slot_status (slot_id,occupied) VALUES (?,?)")->execute([$slot,$occ]);}}catch(Exception $e){}}
echo json_encode(['ok'=>true]);
