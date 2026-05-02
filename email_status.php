<?php header('Content-Type: application/json');
$f=__DIR__.'/email_daemon_status.json';
echo file_exists($f)?file_get_contents($f):json_encode(['running'=>false,'check_count'=>0,'total_imported'=>0,'last_check'=>null]);
