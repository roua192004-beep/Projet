<?php include 'database.php'; header('Content-Type: application/json');
if(($_GET['key']??'')!==ROBOT_KEY){echo json_encode(['lines'=>[]]);exit();}
$ph=$pdo->query("SELECT * FROM picklist_header WHERE status IN ('active','preparing') ORDER BY imported_at ASC LIMIT 1")->fetch();
if(!$ph){echo json_encode(['lines'=>[],'picklist_code'=>null]);exit();}
$lines=$pdo->prepare("SELECT reference,quantite,emplacement,nbre_us FROM picklist_lines WHERE picklist_id=?");
$lines->execute([$ph['id']]);
$result=array_map(fn($l)=>[...$l,'loaded'=>0,'status'=>'pending'],$lines->fetchAll());
echo json_encode(['picklist_id'=>$ph['id'],'picklist_code'=>$ph['picklist_code'],'lines'=>$result]);
