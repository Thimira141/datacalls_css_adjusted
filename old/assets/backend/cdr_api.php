<?php
session_start();
ini_set('display_errors', 0); // Set to 1 for debugging
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Log to a file for debugging
$logFile = __DIR__ . '/cdr_api_debug.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Request: " . json_encode($_REQUEST) . "\n", FILE_APPEND);

require_once "./magnusBilling.php"; // Adjust path if needed

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Unauthorized access attempt\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Database connection
$host = 'localhost';
$dbname = 'datacalls_css_adjusted';
$dbuser = 'root';
$dbpass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Database connection successful\n", FILE_APPEND);

    // Fetch magnus_user_id and username
    $stmt = $pdo->prepare("SELECT username, magnus_user_id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !$user['magnus_user_id']) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - No magnus_user_id for user_id: {$_SESSION['user_id']}\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'MagnusBilling user ID not found']);
        exit;
    }
    $magnus_user_id = $user['magnus_user_id'];
    $username = $user['username'];
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Fetched magnus_user_id: $magnus_user_id, username: $username\n", FILE_APPEND);
} catch (PDOException $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Database error: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Initialize MagnusBilling API
try {
    $magnusBilling = new MagnusBilling('8x9vqM4JWnxUbDZGJm9HHlqKD8R8vvJ3', 'xJdpyCjiVrSrabu2fnN53BNdGCDc0O6B');
    $magnusBilling->public_url = "http://72.60.25.185/mbilling";
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - MagnusBilling API initialized\n", FILE_APPEND);
} catch (Exception $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - API initialization failed: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'API initialization failed: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'get_calls') {
    try {
        $callRecords = [];

        // Try id_user filter
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Trying id_user filter: $magnus_user_id\n", FILE_APPEND);
        $result = $magnusBilling->read('call', ['filter' => [['id_user', '=', $magnus_user_id]]]);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - id_user response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

        if (isset($result['rows']) && is_array($result['rows'])) {
            $callRecords = $result['rows'];
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Using nested 'rows' from id_user filter\n", FILE_APPEND);
        } elseif (is_array($result)) {
            $callRecords = $result;
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Using flat array from id_user filter\n", FILE_APPEND);
        }

        // Fallback to username filter
        if (empty($callRecords)) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - No records with id_user, trying username: $username\n", FILE_APPEND);
            $result = $magnusBilling->read('call', ['filter' => [['username', '=', $username]]]);
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - username response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

            if (isset($result['rows']) && is_array($result['rows'])) {
                $callRecords = $result['rows'];
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Using nested 'rows' from username filter\n", FILE_APPEND);
            } elseif (is_array($result)) {
                $callRecords = $result;
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Using flat array from username filter\n", FILE_APPEND);
            }
        }

        // Fallback to no filter
        if (empty($callRecords)) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - No records with filters, fetching all CDRs\n", FILE_APPEND);
            $result = $magnusBilling->read('call', []);
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - No filter response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

            if (isset($result['rows']) && is_array($result['rows'])) {
                $callRecords = array_filter($result['rows'], function ($row) use ($magnus_user_id, $username) {
                    return (isset($row['id_user']) && $row['id_user'] == $magnus_user_id) ||
                        (isset($row['username']) && $row['username'] == $username);
                });
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Filtered " . count($callRecords) . " records client-side\n", FILE_APPEND);
            } elseif (is_array($result)) {
                $callRecords = array_filter($result, function ($row) use ($magnus_user_id, $username) {
                    return (isset($row['id_user']) && $row['id_user'] == $magnus_user_id) ||
                        (isset($row['username']) && $row['username'] == $username);
                });
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Filtered " . count($callRecords) . " records client-side from flat array\n", FILE_APPEND);
            }
        }

        if (!empty($callRecords)) {
            $callRecords = array_map(function ($row) {
                return [
                    'id' => $row['id'] ?? null,
                    'calledstation' => $row['calledstation'] ?? 'N/A',
                    'callerid' => $row['callerid'] ?? 'N/A',
                    'starttime' => $row['starttime'] ?? null,
                    'sessiontime' => $row['sessiontime'] ?? 0
                ];
            }, $callRecords);
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Returning " . count($callRecords) . " records\n", FILE_APPEND);
            echo json_encode([
                'success' => true,
                'data' => array_values($callRecords),
                'message' => 'Records fetched successfully'
            ]);
        } else {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - No records found\n", FILE_APPEND);
            echo json_encode(['success' => false, 'message' => 'No call records found']);
        }
    } catch (Exception $e) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - API error: " . $e->getMessage() . "\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch records: ' . $e->getMessage()]);
    }
} else {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Invalid action: $action\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}