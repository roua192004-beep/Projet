<?php include 'database.php'; header('Content-Type: application/json');
$key=$_POST['key']??$_GET['key']??'';
if($key!==ROBOT_KEY){echo json_encode(['ok'=>false,'message'=>'Cle invalide']);exit();}
$action=$_POST['action']??'ping';
if($action==='ping'){
    $rs=$pdo->query("SELECT * FROM robot_status WHERE id=1")->fetch();
    if(!$rs){$pdo->exec("INSERT INTO robot_status (id,mode,zone,current_zone,battery_pct,wifi_strength,is_emergency) VALUES (1,'auto','zone1','zone1',100,100,0)");$rs=$pdo->query("SELECT * FROM robot_status WHERE id=1")->fetch();}
    $bat=(int)($_POST['battery_pct']??$rs['battery_pct']); $wifi=(int)($_POST['wifi_strength']??$rs['wifi_strength']);
    $mode=$_POST['mode']??$rs['mode']; $zone=$_POST['zone']??$rs['zone'];
    $pdo->prepare("UPDATE robot_status SET battery_pct=?,wifi_strength=?,mode=?,zone=?,current_zone=?,last_ping=NOW() WHERE id=1")->execute([$bat,$wifi,$mode,$zone,$zone]);
    echo json_encode(['ok'=>true,'is_emergency'=>(bool)($rs['is_emergency']??false)]);
} elseif($action==='emergency_stop'){$pdo->exec("UPDATE robot_status SET is_emergency=1 WHERE id=1");echo json_encode(['ok'=>true]);}
elseif($action==='resume'){$pdo->exec("UPDATE robot_status SET is_emergency=0 WHERE id=1");echo json_encode(['ok'=>true]);}
else{echo json_encode(['ok'=>false,'message'=>'Action inconnue']);}
