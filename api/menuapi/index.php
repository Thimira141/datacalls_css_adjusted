<?php
/**
 * MenuAPI index file : focus on information
 * 
 * Role: a small module/index endpoint for the menu API that describes available modules (currently returns sip metadata).
 * Behavior:
 * * Auth: requires Authorization: Bearer <hashed> header where the hashed value must verify against the script's static API key via password_verify.
 * * Loads Asterisk DB connection config from /etc/asterisk/res_config_mysql.conf then opens a PDO connection.
 * * Accepts POST field option; option=get_modules returns JSON metadata.
 * Usage example:
 * * POST option=get_modules with the Authorization header (example in the README).
 *  
 * @Author thimira dilshan <thimirad865@gmail.com>
 * @LastUpdated 2025-11-05
 */
require_once 'function.php';

header('Content-Type: application/json');
// AUTHENTICATION
$apiKey = "dc3c3e74645d2ec7c9b4184827f15778c8b2ce136477e30993b36c69421aa68e";
$headers = getallheaders();
$hashedKey = str_replace('Bearer ', '', $headers['Authorization'] ?? '');

if (!password_verify($apiKey, $hashedKey)) {
    json_error('Unauthorized', 401);
}

// DB connection
$configFile = '/etc/asterisk/res_config_mysql.conf';
if (!file_exists($configFile)) {
    json_error('Config file not found', 500);
}

$array = parse_ini_file($configFile);
if (!$array || !isset($array['dbhost'], $array['dbname'], $array['dbuser'], $array['dbpass'])) {
    json_error('Invalid DB config structure', 500);
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
} catch (PDOException $e) {
    json_error('Database connection failed: ' . $e->getMessage(), 500);
}
// ============= CODE =====================
$option = $_POST['option'] ?? null;

switch ($option) {
    case 'get_modules':
        // table => pkg_sip
        echo json_encode([
            'sip' => [
                'get_sip_user' => [
                    'description' => 'get sip user info',
                    'required fields' => [
                        'field' => ['id','id_user','SIP user','callerid','Username'], 
                        'field_value'
                    ],
                    'return fields' => ['id','id_user','SIP user','callerid','Username'],
                ]
            ]
        ]);
        
        break;
    
    default:
        json_error("Option not found!", 404);
        break;
}

$pdo = null;
exit;