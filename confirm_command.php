<?php include 'database.php'; header('Content-Type: application/json');
if(($_POST['key']??'')!==ROBOT_KEY){echo json_encode(['ok'=>false]);exit();}
$id=(int)($_POST['id']??0);
if($id){$pdo->prepare("UPDATE robot_commands SET executed=1,executed_at=NOW() WHERE id=?")->execute([$id]);}
echo json_encode(['ok'=>true]);
