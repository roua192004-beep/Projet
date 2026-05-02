<?php include 'database.php'; header('Content-Type: application/json');
$r=$pdo->query("SELECT barcode,result,scanned_at FROM robot_activity WHERE action IN ('scan_ok','scan_error','last_scan_cache') ORDER BY scanned_at DESC LIMIT 1")->fetch();
if(!$r){echo json_encode(['barcode'=>null]);exit();}
echo json_encode(['barcode'=>$r['barcode'],'signal'=>$r['result']==='ok'?'green':'red','scanned_at'=>$r['scanned_at']]);
