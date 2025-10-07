<?php
/**
 * IVR Audio Upload & Conversion Endpoint
 *
 * This script handles secure upload of custom IVR audio files (MP3), converts them to Asterisk-compatible WAV format,
 * updates the corresponding IVR configuration in the database, and reloads the dialplan to apply changes.
 *
 * Key Features:
 * - Authenticated access via hashed API key
 * - Validates and stores uploaded MP3 files
 * - Converts MP3 to WAV using ffmpeg (8000 Hz, mono)
 * - Sets correct file ownership and permissions for Asterisk
 * - Updates `pkg_ivr.option_0` with new playback sequence
 * - Reloads Asterisk dialplan via shell script
 *
 * Expected POST fields:
 * - customer_number: Target customer identifier
 * - user_id: Uploading user ID
 * - ivr_id: IVR configuration ID (default: 1)
 * - audio_file: Uploaded MP3 file (via multipart/form-data)
 *
 * Dependencies:
 * - ffmpeg (for audio conversion)
 * - /usr/local/bin/reload_dialplan.sh (for dialplan reload)
 * - MySQL config from /etc/asterisk/res_config_mysql.conf
 *
 * @Author thimira dilshan <thimirad865@gmail.com>
 * @LastUpdated 2025-10-07
 */

// CONFIG
define('UPLOAD_DIR', '/var/www/html/mbilling/api/tts/storage/audio/');
define('ASTERISK_DIR', '/var/lib/asterisk/sounds/en/');


header('Content-Type: application/json');

function json_error($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    error_log("[ERROR] [$code] [" . date("Y-m-d H-i-s") . "]" . $message);
    exit;
}
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

// VALIDATE INPUT
$customerNumber = $_POST['customer_number'] ?? null;
$userId = $_POST['user_id'] ?? null;
$ivrId = $_POST['ivr_id'] ?? '1';
$file = $_FILES['audio_file'] ?? null;

if (!$customerNumber || !$userId || !$file) {
    json_error('Missing required fields');
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    json_error('File upload error: ' . $file['error']);
}

try {
    // SAVE MP3
    $mp3Name = (string) "ivr_custom.mp3";
    $mp3Path = UPLOAD_DIR . $mp3Name;

    if (!is_writable(dirname($mp3Path))) {
        json_error('Directory still not writable: ' . dirname($mp3Path));
    }

    // $mp3Path = realpath(__DIR__ . '/../storage/audio/') . '/' . $mp3Name;
    if (!isset($_FILES['audio_file']) || $_FILES['audio_file']['error'] !== UPLOAD_ERR_OK) {
        json_error('No valid file uploaded');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        json_error('Temporary file is not valid');
    }

    if (!is_writable(dirname($mp3Path))) {
        json_error('Target directory is not writable: ' . $mp3Path);
    }

    if (!move_uploaded_file($file['tmp_name'], $mp3Path)) {
        json_error('Failed to save uploaded file: ' . json_encode($file) . '; mp3Path: ' . $mp3Path);
    }

    // CONVERT TO WAV
    $wavName = str_replace('.mp3', '.wav', $mp3Name);
    $wavPath = ASTERISK_DIR . $wavName;
    $ffmpegCmd = "ffmpeg -y -i " . escapeshellarg($mp3Path) . " -ar 8000 -ac 1 " . escapeshellarg($wavPath);
    exec($ffmpegCmd, $output, $returnVar);
    if ($returnVar !== 0) {
        json_error('Audio conversion failed');
    }
    // wav set file owner
    exec("chown asterisk:asterisk " . escapeshellarg($wavPath), $output, $returnVar);
    if ($returnVar !== 0) {
        json_error('Failed to set file owner');
    }
    // wav set file permissions
    exec("chmod 644 " . escapeshellarg($wavPath), $output, $returnVar);
    if ($returnVar !== 0) {
        json_error('Failed to set file permissions');
    }
} catch (Exception $e) {
    json_error('File processing error: ' . $e->getMessage(), 500);
}

// LOG (optional)
error_log("[TTS Upload] User $userId uploaded audio for customer $customerNumber, IVR $ivrId");

try {
    // Check if IVR ID exists
    $check = $pdo->prepare("SELECT COUNT(*) FROM pkg_ivr WHERE id = ?");
    $check->execute([$ivrId]);
    if ($check->fetchColumn() == 0) {
        json_error('Invalid IVR ID');
    }

    // Update IVR audio
    $stmt = $pdo->prepare("UPDATE pkg_ivr SET option_0 = :option_0 WHERE id = :ivrId");
    $option_0 = "custom|$wavName,s,1;custom|custom-unmute,s,1";
    if (!$stmt->execute(['option_0' => $option_0, 'ivrId' => $ivrId])) {
        json_error('Failed to update IVR audio');
    }
    // Reload dial-plan
    exec("sudo /usr/local/bin/reload_dialplan.sh", $output, $returnVar);
    if ($returnVar !== 0) {
        json_error('Failed to reload Asterisk dialplan' . implode("\n", $output) . '; rtVar: ' . $returnVar);
    }
} catch (PDOException $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    json_error('Unexpected error: ' . $e->getMessage(), 500);
}

// RESPONSE
echo json_encode([
    'success' => true,
    'message' => 'Audio uploaded and converted',
    'filename' => $wavName,
    'ivr_id' => $ivrId
]);
