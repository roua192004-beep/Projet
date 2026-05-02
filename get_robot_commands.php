<?php include 'database.php'; header('Content-Type: application/json');
if(($_GET['key']??'')!==ROBOT_KEY){echo json_encode(['command'=>null]);exit();}
$c=$pdo->query("SELECT * FROM robot_commands WHERE executed=0 ORDER BY created_at ASC LIMIT 1")->fetch();
if(!$c){echo json_encode(['command'=>'none']);exit();}
echo json_encode(['id'=>$c['id'],'command'=>$c['command']]);
