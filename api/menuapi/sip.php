<?php
/**
 * MenuAPI -> SIP module
 * Role: SIP-related endpoints backed by Asterisk DB (pkg_sip table).
 * Behavior:
 * * Same auth + DB config handling as index.php.
 * * option=get_sip_user expects id_user in POST and returns the row from pkg_sip (as result).
 * Usage example:
 * * POST option=get_sip_user and id_user=7 with correct Authorization header; response includes id_user, SIP user, callerid, Username.
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
    case 'get_sip_user':
        // table => pkg_sip
        // check required data
        $data = [];
        $cols = ['field', 'field_value'];
        foreach ($cols as $col) {
            $data[$col] = sanitizeText($_POST[$col] ?? null);
            if (!$data[$col] || empty($data[$col])) {
                json_error('Missing required field(s)');
            }
        }
        // check fields validity
        $supportedFields = ['id', 'id_user', 'SIP user', 'callerid', 'Username'];
        if (!in_array($data['field'], $supportedFields)) {
            json_error("Invalid Field");
        }
        // sql query
        try {
            $stmt = $pdo->prepare(query: "SELECT id,id_user,name as `SIP user`,callerid,accountcode as `Username` from pkg_sip WHERE {$data['field']}=:field_value LIMIT 1");
            $stmt->execute(['field_value' => (string) $data['field_value']]);
            $data = (array) $stmt->fetch(PDO::FETCH_ASSOC);
            // send response
            echo json_encode([
                'success' => true,
                'result' => $data
            ]);
        } catch (\Throwable $th) {
            json_error("DB ERROR: " . $th->getMessage(), 500);
        }

        break;

    default:
        json_error("Option not found!", 404);
        break;
}

$pdo = null;
exit;