<?php include 'database.php'; header('Content-Type: application/json');
if(($_POST['key']??'')!==ROBOT_KEY){echo json_encode(['ok'=>false]);exit();}
try{$pdo->prepare("INSERT INTO robot_movements (from_zone,to_zone,status,started_at) VALUES (?,?,?,NOW())")->execute([$_POST['from']??'zone1',$_POST['to']??'zone2','in_progress']);echo json_encode(['ok'=>true]);}
catch(Exception $e){echo json_encode(['ok'=>false]);}
