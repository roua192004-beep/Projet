<?php include 'database.php'; header('Content-Type: application/json');
if(($_POST['key']??'')!==ROBOT_KEY){echo json_encode(['ok'=>false]);exit();}
try{
    $pdo->exec("CREATE TABLE IF NOT EXISTS agv_slot_cache (id INT DEFAULT 1 PRIMARY KEY,payload TEXT,updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
    $payload=json_encode(['slots'=>json_decode($_POST['slots']??'[]',true),'overall'=>$_POST['picklist_overall']??'red','lines'=>json_decode($_POST['picklist_lines']??'[]',true)]);
    $ex=$pdo->query("SELECT id FROM agv_slot_cache WHERE id=1")->fetch();
    if($ex){$pdo->prepare("UPDATE agv_slot_cache SET payload=?,updated_at=NOW() WHERE id=1")->execute([$payload]);}
    else{$pdo->prepare("INSERT INTO agv_slot_cache (id,payload) VALUES (1,?)")->execute([$payload]);}
    echo json_encode(['ok'=>true]);
}catch(Exception $e){echo json_encode(['ok'=>false]);}
