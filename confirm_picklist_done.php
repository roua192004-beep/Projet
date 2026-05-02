<?php include 'database.php'; header('Content-Type: application/json');
if(($_POST['key']??'')!==ROBOT_KEY){echo json_encode(['ok'=>false]);exit();}
$pid=(int)($_POST['picklist_id']??0);
if($pid){$pdo->prepare("UPDATE picklist_header SET status='delivered',delivered_at=NOW() WHERE id=?")->execute([$pid]);$pdo->prepare("UPDATE picklist_lines SET status='loaded' WHERE picklist_id=? AND status='scanned_ok'")->execute([$pid]);}
echo json_encode(['ok'=>true]);
