<?php
/**
 * CLI safe dtmf logger for dial-plan
 * @author Thimira Dilshan <thimirad865@gmail.com>
 * @version 1.0.0
 * @since 2025-09-22
 */
$channel = $argv[1] ?? '';
$dtmf = $argv[2] ?? '';

if (!$channel || !$dtmf) {
    exit(1);
}

// Load DB config from Asterisk's res_config_mysql.conf
$configFile = '/etc/asterisk/res_config_mysql.conf';
if (!file_exists($configFile)) {
    exit(1);
}

$array = parse_ini_file($configFile);
if (!$array || !isset($array['dbhost'], $array['dbname'], $array['dbuser'], $array['dbpass'])) {
    exit(1);
}

try {
    $pdo = new PDO(
        "mysql:host={$array['dbhost']};dbname={$array['dbname']}",
        $array['dbuser'],
        $array['dbpass'],
        [
            PDO::MYSQL_ATTR_LOCAL_INFILE => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
        ]
    );
    // --- Check and Create Table if Missing ---
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS call_tracking (
            channel VARCHAR(255) PRIMARY KEY,
            dtmf_input VARCHAR(10),
            updated_at DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // --- Insert or Update DTMF ---
    $stmt = $pdo->prepare("
        INSERT INTO call_tracking (channel, dtmf_input, updated_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE dtmf_input = VALUES(dtmf_input), updated_at = NOW()
    ");

    $stmt->execute([$channel, $dtmf]);
    exit(0);
} catch (PDOException $e) {
    exit(1);
}
