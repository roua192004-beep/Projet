<?php include 'database.php';
echo '<pre style="font-family:monospace;padding:2rem;background:#f8fafc;color:#0f172a;line-height:1.8;font-size:13px">';
echo "=== INSTALLATION BASE DE DONNÉES AGV ===\n\n";
$tables=["CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY,username VARCHAR(50) UNIQUE NOT NULL,password VARCHAR(255) NOT NULL,role ENUM('headworker','warehouse_worker','line_worker') DEFAULT 'line_worker',full_name VARCHAR(100),created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
"CREATE TABLE IF NOT EXISTS picklist_header (id INT AUTO_INCREMENT PRIMARY KEY,picklist_code VARCHAR(50) UNIQUE NOT NULL,mapa VARCHAR(50),uf VARCHAR(50),ligne_production VARCHAR(100),code_pfin VARCHAR(100),status ENUM('active','preparing','delivered','archived') DEFAULT 'active',imported_at DATETIME DEFAULT CURRENT_TIMESTAMP,delivered_at DATETIME NULL)",
"CREATE TABLE IF NOT EXISTS picklist_lines (id INT AUTO_INCREMENT PRIMARY KEY,picklist_id INT NOT NULL,reference VARCHAR(100) NOT NULL,designation VARCHAR(200),quantite INT DEFAULT 1,emplacement VARCHAR(200),nbre_us INT DEFAULT 0,status ENUM('pending','scanned_ok','loaded','error') DEFAULT 'pending',scanned_at DATETIME NULL,FOREIGN KEY (picklist_id) REFERENCES picklist_header(id) ON DELETE CASCADE)",
"CREATE TABLE IF NOT EXISTS inventory (id INT AUTO_INCREMENT PRIMARY KEY,barcode VARCHAR(100) UNIQUE NOT NULL,designation VARCHAR(200),emplacement VARCHAR(100),nbre_us INT DEFAULT 0,quantite INT DEFAULT 0,updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)",
"CREATE TABLE IF NOT EXISTS robot_status (id INT DEFAULT 1 PRIMARY KEY,mode VARCHAR(20) DEFAULT 'auto',zone VARCHAR(20) DEFAULT 'zone1',current_zone VARCHAR(20) DEFAULT 'zone1',battery_pct INT DEFAULT 100,wifi_strength INT DEFAULT 100,is_emergency TINYINT DEFAULT 0,last_ping DATETIME NULL)",
"CREATE TABLE IF NOT EXISTS robot_commands (id INT AUTO_INCREMENT PRIMARY KEY,command VARCHAR(50) NOT NULL,created_by INT DEFAULT 0,executed TINYINT DEFAULT 0,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,executed_at DATETIME NULL)",
"CREATE TABLE IF NOT EXISTS robot_activity (id INT AUTO_INCREMENT PRIMARY KEY,action VARCHAR(50),barcode VARCHAR(100),picklist_id INT DEFAULT 0,worker_id INT DEFAULT 0,worker_name VARCHAR(100),shift_number INT DEFAULT 1,zone_from VARCHAR(20),zone_to VARCHAR(20),is_discharged TINYINT DEFAULT 0,result VARCHAR(20) DEFAULT 'ok',scanned_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
"CREATE TABLE IF NOT EXISTS emergency_log (id INT AUTO_INCREMENT PRIMARY KEY,trigger_type VARCHAR(50),triggered_by VARCHAR(100),robot_position VARCHAR(50),resolved_at DATETIME NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
"CREATE TABLE IF NOT EXISTS robot_movements (id INT AUTO_INCREMENT PRIMARY KEY,from_zone VARCHAR(20),to_zone VARCHAR(20),duration_s INT NULL,status VARCHAR(20) DEFAULT 'in_progress',started_at DATETIME DEFAULT CURRENT_TIMESTAMP,ended_at DATETIME NULL)",
"CREATE TABLE IF NOT EXISTS agv_slot_cache (id INT DEFAULT 1 PRIMARY KEY,payload TEXT,updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)",
"INSERT IGNORE INTO robot_status (id,mode,zone,current_zone,battery_pct,wifi_strength,is_emergency) VALUES (1,'auto','zone1','zone1',100,100,0)"];
foreach($tables as $sql){try{$pdo->exec($sql);echo "✅ OK\n";}catch(Exception $e){echo "⚠️ ".$e->getMessage()."\n";}}
echo "\n✅ Installation terminée !\n";
echo '<a href="reset_passwords.php" style="color:#F59E0B;font-weight:bold;font-size:14px">→ Étape 2 : Créer les comptes utilisateurs</a>';
echo '</pre>';
