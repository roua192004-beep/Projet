<?php include 'database.php'; header('Content-Type: application/json');
$rs=$pdo->query("SELECT * FROM robot_status WHERE id=1")->fetch();
echo json_encode($rs?:['mode'=>'auto','zone'=>'zone1','battery_pct'=>0,'is_emergency'=>false]);
