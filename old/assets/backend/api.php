<?php
session_start();

// Start output buffering to prevent unwanted output
ob_start();

// Enable error reporting for debugging (disabled in production)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log("Starting api.php");

// Database configuration
$host = 'localhost';
$dbname = 'datacalls_css_adjusted';
$dbuser = 'root';
$dbpass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("Database connection successful");
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    error_log("Database error: " . $e->getMessage());
    exit;
}

// Include MagnusBilling class
require_once "./magnusBilling.php";

try {
    $magnusBilling = new MagnusBilling('8x9vqM4JWnxUbDZGJm9HHlqKD8R8vvJ3', 'xJdpyCjiVrSrabu2fnN53BNdGCDc0O6B');
    $magnusBilling->public_url = "http://72.60.25.185/mbilling";
    error_log("MagnusBilling initialized successfully");
} catch (Exception $e) {
    error_log("MagnusBilling initialization error: " . $e->getMessage());
    $magnusBilling = null;
}

// Check if user is logged in
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    error_log("Unauthorized access - No user_id in session");
    exit;
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']))) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    error_log("Invalid CSRF token for action: $action");
    exit;
}

$user_id = $_SESSION['user_id'];
error_log("Processing action: $action, User ID: $user_id, Session data: " . json_encode($_SESSION));

// Function to update Caller ID in MagnusBilling (for user module)
function updateCallerId($username, $callerId, $magnusBilling)
{
    if (!$magnusBilling) {
        error_log("updateCallerId: MagnusBilling not initialized");
        return false;
    }
    try {
        $userId = $magnusBilling->getId('user', 'firstname', $username);
        if (!$userId) {
            error_log("No user ID found for username: $username");
            return false;
        }
        $result = $magnusBilling->update('user', $userId, ['callerid' => $callerId]);
        if (isset($result['success']) && $result['success']) {
            error_log("Updated Caller ID for username: $username");
            return true;
        } else {
            $errorMsg = isset($result['error']) ? $result['error'] : 'Unknown error';
            error_log("MagnusBilling API Error (updateCallerId): $errorMsg");
            return false;
        }
    } catch (Exception $e) {
        error_log("Exception in updateCallerId: " . $e->getMessage());
        return false;
    }
}

// Handle API requests
switch ($action) {
    case 'get_sip_info':
        try {
            error_log("get_sip_info requested for user_id: $user_id");
            $stmt = $pdo->prepare("SELECT username, magnus_password, sip_domain FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $response = [
                    'success' => true,
                    'sip_domain' => $user['sip_domain'] ?: 'Not Available',
                    'sip_username' => $user['username'] ?: 'Not Available',
                    'sip_password' => $user['magnus_password'] ?: 'Not Available'
                ];
                echo json_encode($response);
                error_log("Fetched SIP info for user_id: $user_id, username: " . ($user['username'] ?? 'none') . ", response: " . json_encode($response));
            } else {
                echo json_encode(['success' => false, 'message' => 'User not found']);
                error_log("Get SIP info error: User not found for user_id: $user_id");
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch SIP info: ' . $e->getMessage()]);
            error_log("Get SIP info error: " . $e->getMessage() . ", user_id: $user_id");
        }
        break;

    case 'get_contacts':
        try {
            $stmt = $pdo->prepare("SELECT id, name, phone_number FROM contacts WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Fetched " . count($contacts) . " contacts for user_id: $user_id");
            echo json_encode(['success' => true, 'contacts' => $contacts]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch contacts: ' . $e->getMessage()]);
            error_log("Get contacts error: " . $e->getMessage());
        }
        break;

    case 'add_contact':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            exit;
        }
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'User not logged in']);
            exit;
        }
        $name = trim($_POST['name'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        if (empty($name) || empty($phone_number)) {
            echo json_encode(['success' => false, 'message' => 'Name and phone number are required']);
            exit;
        }
        if (!preg_match('/^1\d{9,13}$/', $phone_number)) {
            echo json_encode(['success' => false, 'message' => 'Phone number must start with 1 and be 10-14 digits (e.g., 12025550123)']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO contacts (user_id, name, phone_number) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $name, $phone_number]);
            echo json_encode(['success' => true, 'message' => 'Contact added successfully']);
        } catch (PDOException $e) {
            error_log("Add contact error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to add contact']);
        }
        break;

    case 'delete_contact':
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $user_id]);
                if ($stmt->rowCount() > 0) {
                    error_log("Deleted contact ID: $id for user_id: $user_id");
                    echo json_encode(['success' => true, 'message' => 'Contact deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Contact not found or not authorized']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to delete contact: ' . $e->getMessage()]);
                error_log("Delete contact error: " . $e->getMessage());
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid contact ID']);
        }
        break;

    case 'get_institutions':
        try {
            $stmt = $pdo->prepare("SELECT id, name FROM institutions WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $institutions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Fetched " . count($institutions) . " institutions for user_id: $user_id");
            echo json_encode(['success' => true, 'institutions' => $institutions]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch institutions: ' . $e->getMessage()]);
            error_log("Get institutions error: " . $e->getMessage());
        }
        break;

    case 'add_institution':
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        if ($name) {
            try {
                $stmt = $pdo->prepare("INSERT INTO institutions (user_id, name) VALUES (?, ?)");
                $stmt->execute([$user_id, $name]);
                error_log("Added institution: $name for user_id: $user_id");
                echo json_encode(['success' => true, 'message' => 'Institution added successfully']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to add institution: ' . $e->getMessage()]);
                error_log("Add institution error: " . $e->getMessage());
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Institution name is required']);
        }
        break;

    case 'delete_institution':
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM institutions WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $user_id]);
                if ($stmt->rowCount() > 0) {
                    error_log("Deleted institution ID: $id for user_id: $user_id");
                    echo json_encode(['success' => true, 'message' => 'Institution deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Institution not found or not authorized']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to delete institution: ' . $e->getMessage()]);
                error_log("Delete institution error: " . $e->getMessage());
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid institution ID']);
        }
        break;

    case 'get_merchants':
        try {
            $stmt = $pdo->prepare("SELECT id, name FROM merchants WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $merchants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Fetched " . count($merchants) . " merchants for user_id: $user_id");
            echo json_encode(['success' => true, 'merchants' => $merchants]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch merchants: ' . $e->getMessage()]);
            error_log("Get merchants error: " . $e->getMessage());
        }
        break;

    case 'add_merchant':
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        if ($name) {
            try {
                $stmt = $pdo->prepare("INSERT INTO merchants (user_id, name) VALUES (?, ?)");
                $stmt->execute([$user_id, $name]);
                error_log("Added merchant: $name for user_id: $user_id");
                echo json_encode(['success' => true, 'message' => 'Merchant added successfully']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to add merchant: ' . $e->getMessage()]);
                error_log("Add merchant error: " . $e->getMessage());
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Merchant name is required']);
        }
        break;

    case 'delete_merchant':
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM merchants WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $user_id]);
                if ($stmt->rowCount() > 0) {
                    error_log("Deleted merchant ID: $id for user_id: $user_id");
                    echo json_encode(['success' => true, 'message' => 'Merchant deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Merchant not found or not authorized']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to delete merchant: ' . $e->getMessage()]);
                error_log("Delete merchant error: " . $e->getMessage());
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid merchant ID']);
        }
        break;

    case 'get_ivr_profiles':
        try {
            $stmt = $pdo->prepare(
                "SELECT id, profile_name, institution_name, caller_id, callback_number, merchant_name, amount, magnus_ivr_id 
                 FROM ivr_profiles 
                 WHERE user_id = ?"
            );
            $stmt->execute([$user_id]);
            $ivr_profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Fetched " . count($ivr_profiles) . " IVR profiles for user_id: $user_id");
            echo json_encode(['success' => true, 'ivr_profiles' => $ivr_profiles]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch IVR profiles: ' . $e->getMessage()]);
            error_log("Get IVR profiles error: " . $e->getMessage());
        }
        break;

    case 'add_ivr_profile':
        $profile_name = filter_input(INPUT_POST, 'profile_name', FILTER_SANITIZE_STRING);
        $institution_name = filter_input(INPUT_POST, 'institution_name', FILTER_SANITIZE_STRING);
        $caller_id = filter_input(INPUT_POST, 'caller_id', FILTER_SANITIZE_STRING);
        $callback_number = filter_input(INPUT_POST, 'callback_number', FILTER_SANITIZE_STRING);
        $merchant_name = filter_input(INPUT_POST, 'merchant_name', FILTER_SANITIZE_STRING);
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);

        if ($profile_name && $institution_name && $caller_id && $callback_number && $merchant_name && $amount !== false && $amount > 0) {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO ivr_profiles (user_id, profile_name, institution_name, caller_id, callback_number, merchant_name, amount) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$user_id, $profile_name, $institution_name, $caller_id, $callback_number, $merchant_name, $amount]);
                error_log("Added IVR profile: $profile_name for user_id: $user_id");
                echo json_encode(['success' => true, 'message' => 'IVR Profile added successfully']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to add IVR profile: ' . $e->getMessage()]);
                error_log("Add IVR profile error: " . $e->getMessage());
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'All fields are required and amount must be valid']);
        }
        break;

    case 'delete_ivr_profile':
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM ivr_profiles WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $user_id]);
                if ($stmt->rowCount() > 0) {
                    error_log("Deleted IVR profile ID: $id for user_id: $user_id");
                    echo json_encode(['success' => true, 'message' => 'IVR Profile deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'IVR Profile not found or not authorized']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to delete IVR profile: ' . $e->getMessage()]);
                error_log("Delete IVR profile error: " . $e->getMessage());
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid profile ID']);
        }
        break;

    case 'get_calls':
        try {
            $stmt = $pdo->prepare(
                "SELECT id, customer_number AS number_called, caller_id, created_at, duration, institution_name, merchant_name, amount
                 FROM calls
                 WHERE user_id = ?
                 ORDER BY created_at DESC"
            );
            $stmt->execute([$user_id]);
            $calls = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Fetched " . count($calls) . " calls for user_id: $user_id");
            echo json_encode(['success' => true, 'calls' => $calls]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch call records: ' . $e->getMessage()]);
            error_log("Get calls error: " . $e->getMessage());
        }
        break;

    case 'delete_call':
        $ids = isset($_POST['ids']) ? json_decode($_POST['ids'], true) : [];
        if (!empty($ids)) {
            try {
                $valid_ids = array_filter($ids, function ($id) {
                    return filter_var($id, FILTER_VALIDATE_INT) !== false;
                });
                if (count($valid_ids) !== count($ids)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid call IDs provided']);
                    break;
                }
                $placeholders = implode(',', array_fill(0, count($valid_ids), '?'));
                $stmt = $pdo->prepare("DELETE FROM calls WHERE id IN ($placeholders) AND user_id = ?");
                $params = array_merge($valid_ids, [$user_id]);
                $stmt->execute($params);
                if ($stmt->rowCount() > 0) {
                    error_log("Deleted " . $stmt->rowCount() . " call records for user_id: $user_id");
                    echo json_encode(['success' => true, 'message' => 'Call record(s) deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'No call records found or not authorized']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to delete call record(s): ' . $e->getMessage()]);
                error_log("Delete call error: " . $e->getMessage());
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'No call IDs provided']);
        }
        break;

    case 'receive_cdr':
        $call_id = filter_input(INPUT_POST, 'call_id', FILTER_VALIDATE_INT);
        $customer_number = filter_input(INPUT_POST, 'customer_number', FILTER_SANITIZE_STRING);
        $caller_id = filter_input(INPUT_POST, 'caller_id', FILTER_SANITIZE_STRING);
        $duration = filter_input(INPUT_POST, 'duration', FILTER_VALIDATE_INT);
        $created_at = filter_input(INPUT_POST, 'created_at', FILTER_SANITIZE_STRING);

        if ($call_id && $customer_number && $caller_id && $duration !== false && $created_at) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM calls WHERE id = ? AND user_id = ?");
                $stmt->execute([$call_id, $user_id]);
                if ($stmt->fetch()) {
                    $stmt = $pdo->prepare(
                        "UPDATE calls SET customer_number = ?, caller_id = ?, duration = ?, created_at = ? WHERE id = ?"
                    );
                    $stmt->execute([$customer_number, $caller_id, $duration, $created_at, $call_id]);
                    error_log("Recorded CDR for call_id: $call_id");
                    echo json_encode(['success' => true, 'message' => 'CDR recorded']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid or unauthorized call ID']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to record CDR: ' . $e->getMessage()]);
                error_log("Receive CDR error: " . $e->getMessage());
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Missing or invalid fields']);
        }
        break;

    case 'initiate_call':
        if (!$magnusBilling) {
            echo json_encode(['success' => false, 'message' => 'MagnusBilling not initialized']);
            error_log("initiate_call: MagnusBilling not initialized");
            break;
        }
        $institution_name = filter_input(INPUT_POST, 'institution_name', FILTER_SANITIZE_STRING);
        $customer_name = filter_input(INPUT_POST, 'customer_name', FILTER_SANITIZE_STRING);
        $customer_number = filter_input(INPUT_POST, 'customer_number', FILTER_SANITIZE_STRING);
        $caller_id = filter_input(INPUT_POST, 'caller_id', FILTER_SANITIZE_STRING);
        $callback_method = filter_input(INPUT_POST, 'callback_method', FILTER_SANITIZE_STRING);
        $callback_number = filter_input(INPUT_POST, 'callback_number', FILTER_SANITIZE_STRING);
        $merchant_name = filter_input(INPUT_POST, 'merchant_name', FILTER_SANITIZE_STRING);
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
        $magnus_ivr_id = filter_input(INPUT_POST, 'magnus_ivr_id', FILTER_VALIDATE_INT) ?: 1;

        if ($institution_name && $customer_name && $customer_number && $caller_id && $callback_method && $merchant_name && $amount !== false && $amount > 0) {
            try {
                $stmt = $pdo->prepare("SELECT username, magnus_password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$user) {
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                    break;
                }

                $callback_destination = $callback_method === 'softphone' ? $user['username'] : $callback_number;

                if (!updateCallerId($user['username'], $caller_id, $magnusBilling)) {
                    echo json_encode(['success' => false, 'message' => 'Failed to update Caller ID']);
                    break;
                }

                $tts_script = "Hello, this is $institution_name calling for $customer_name regarding a security matter with your account. We’ve detected a recent transaction of $$amount at $merchant_name that may be unauthorized. If you recognize and authorized this transaction, please press 1. If you did not authorize this transaction or would like to speak with a representative, please press 2 now. To repeat this message, press 3.";
                $callData = [
                    'destination' => 'custom-ivr-call,s,1',
                    'callerid' => $caller_id,
                    'callback' => $callback_destination,
                    'id_user' => $magnusBilling->getId('user', 'firstname', $user['username']),
                    'ivr_id' => $magnus_ivr_id,
                    'customer_number' => $customer_number,
                    'tts_script' => $tts_script
                ];
                $result = $magnusBilling->create('call', $callData);
                if (!isset($result['success']) || !$result['success']) {
                    echo json_encode(['success' => false, 'message' => 'Failed to initiate call: ' . ($result['error'] ?? 'Unknown error')]);
                    error_log("MagnusBilling API Error (initiate_call): " . json_encode($result));
                    break;
                }

                $stmt = $pdo->prepare(
                    "INSERT INTO calls (user_id, customer_number, caller_id, callback_method, callback_number, magnus_call_id, institution_name, merchant_name, amount, tts_script) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $magnus_call_id = $result['id'] ?? $pdo->lastInsertId();
                $stmt->execute([$user_id, $customer_number, $caller_id, $callback_method, $callback_destination, $magnus_call_id, $institution_name, $merchant_name, $amount, $tts_script]);
                $call_id = $pdo->lastInsertId();

                error_log("Initiated call: call_id=$call_id, magnus_call_id=$magnus_call_id, customer_number=$customer_number, callback_method=$callback_method");
                echo json_encode(['success' => true, 'message' => 'Call initiated successfully', 'call_id' => $call_id]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to initiate call: ' . $e->getMessage()]);
                error_log("Initiate call error: " . $e->getMessage());
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'All fields are required and amount must be valid']);
        }
        break;

    case 'end_call':
        if (!$magnusBilling) {
            echo json_encode(['success' => false, 'message' => 'MagnusBilling not initialized']);
            error_log("end_call: MagnusBilling not initialized");
            break;
        }
        $call_id = filter_input(INPUT_POST, 'call_id', FILTER_VALIDATE_INT);
        if ($call_id) {
            try {
                $stmt = $pdo->prepare("SELECT magnus_call_id FROM calls WHERE id = ? AND user_id = ?");
                $stmt->execute([$call_id, $user_id]);
                $call = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($call && $call['magnus_call_id']) {
                    $result = $magnusBilling->destroy('call', $call['magnus_call_id']);
                    if (!isset($result['success']) || !$result['success']) {
                        echo json_encode(['success' => false, 'message' => 'Failed to end call: ' . ($result['error'] ?? 'Unknown error')]);
                        error_log("MagnusBilling API Error (end_call): " . json_encode($result));
                        break;
                    }
                    error_log("Ended call: call_id=$call_id, magnus_call_id={$call['magnus_call_id']}");
                    echo json_encode(['success' => true, 'message' => 'Call ended successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid or unauthorized call ID']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to end call: ' . $e->getMessage()]);
                error_log("End call error: " . $e->getMessage());
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid call ID']);
        }
        break;

    case 'get_call_status':
        if (!$magnusBilling) {
            echo json_encode(['success' => false, 'message' => 'MagnusBilling not initialized']);
            error_log("get_call_status: MagnusBilling not initialized");
            break;
        }
        $call_id = filter_input(INPUT_GET, 'call_id', FILTER_VALIDATE_INT);
        if ($call_id) {
            try {
                $stmt = $pdo->prepare("SELECT magnus_call_id FROM calls WHERE id = ? AND user_id = ?");
                $stmt->execute([$call_id, $user_id]);
                $call = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($call && $call['magnus_call_id']) {
                    $magnusBilling->setFilter('id', $call['magnus_call_id'], 'eq', 'integer');
                    $result = $magnusBilling->read('callOnLine');
                    $magnusBilling->clearFilter();
                    if (isset($result['rows'][0])) {
                        $status = strtolower($result['rows'][0]['status'] ?? 'calling');
                        error_log("Call status for call_id=$call_id: $status");
                        echo json_encode(['success' => true, 'status' => $status]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Call not found in MagnusBilling']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid or unauthorized call ID']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to fetch call status: ' . $e->getMessage()]);
                error_log("Get call status error: " . $e->getMessage());
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid call ID']);
        }
        break;

    case 'check_dtmf':
        if (!$magnusBilling) {
            echo json_encode(['success' => false, 'message' => 'MagnusBilling not initialized']);
            error_log("check_dtmf: MagnusBilling not initialized");
            break;
        }
        $call_id = filter_input(INPUT_GET, 'call_id', FILTER_VALIDATE_INT);
        if ($call_id) {
            try {
                $stmt = $pdo->prepare("SELECT magnus_call_id, customer_number, tts_script FROM calls WHERE id = ? AND user_id = ?");
                $stmt->execute([$call_id, $user_id]);
                $call = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($call && $call['magnus_call_id']) {
                    $result = $magnusBilling->query([
                        'module' => 'callOnLine',
                        'action' => 'getDtmf',
                        'id' => $call['magnus_call_id']
                    ]);
                    if (isset($result['success']) && $result['success'] && isset($result['dtmf'])) {
                        $dtmf = $result['dtmf'];
                        $stmt = $pdo->prepare("INSERT INTO dtmf_inputs (call_id, phone_number, dtmf_keys) VALUES (?, ?, ?)");
                        $stmt->execute([$call_id, $call['customer_number'], $dtmf]);
                        error_log("DTMF '$dtmf' recorded for call_id: $call_id");
                        if ($dtmf === '1') {
                            $magnusBilling->query([
                                'module' => 'callOnLine',
                                'action' => 'playAudio',
                                'id' => $call['magnus_call_id'],
                                'audio' => 'thank_you.wav'
                            ]);
                            $magnusBilling->destroy('call', $call['magnus_call_id']);
                            echo json_encode(['success' => true, 'dtmf' => '1']);
                        } elseif ($dtmf === '2') {
                            $magnusBilling->query([
                                'module' => 'callOnLine',
                                'action' => 'playAudio',
                                'id' => $call['magnus_call_id'],
                                'audio' => 'hold_music.wav'
                            ]);
                            echo json_encode(['success' => true, 'dtmf' => '2']);
                        } elseif ($dtmf === '3') {
                            $magnusBilling->query([
                                'module' => 'callOnLine',
                                'action' => 'playTts',
                                'id' => $call['magnus_call_id'],
                                'tts_script' => $call['tts_script']
                            ]);
                            echo json_encode(['success' => true, 'dtmf' => '3']);
                        } else {
                            echo json_encode(['success' => true, 'dtmf' => null]);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'No DTMF input found']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid or unauthorized call ID']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to check DTMF: ' . $e->getMessage()]);
                error_log("Check DTMF error: " . $e->getMessage());
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid call ID']);
        }
        break;

    case 'toggle_mute':
        if (!$magnusBilling) {
            echo json_encode(['success' => false, 'message' => 'MagnusBilling not initialized']);
            error_log("toggle_mute: MagnusBilling not initialized");
            break;
        }
        $call_id = filter_input(INPUT_POST, 'call_id', FILTER_VALIDATE_INT);
        $mute = filter_input(INPUT_POST, 'mute', FILTER_VALIDATE_BOOLEAN);
        if ($call_id && isset($mute)) {
            try {
                $stmt = $pdo->prepare("SELECT magnus_call_id, callback_method, customer_number FROM calls WHERE id = ? AND user_id = ?");
                $stmt->execute([$call_id, $user_id]);
                $call = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($call && $call['magnus_call_id']) {
                    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$user) {
                        echo json_encode(['success' => false, 'message' => 'User not found']);
                        break;
                    }
                    $result = $magnusBilling->query([
                        'module' => 'callOnLine',
                        'action' => 'toggleMute',
                        'id' => $call['magnus_call_id'],
                        'sipuser' => $user['username'],
                        'mute' => $mute ? '1' : '0'
                    ]);
                    if (isset($result['success']) && $result['success']) {
                        error_log("Mute toggled for call_id: $call_id, mute: " . ($mute ? 'on' : 'off'));
                        echo json_encode(['success' => true, 'message' => 'Mute toggled successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to toggle mute: ' . ($result['error'] ?? 'Unknown error')]);
                        error_log("MagnusBilling API Error (toggle_mute): " . json_encode($result));
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid or unauthorized call ID']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to toggle mute: ' . $e->getMessage()]);
                error_log("Toggle mute error: " . $e->getMessage());
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid call ID or mute parameter']);
        }
        break;

    case 'get_dtmf':
        try {
            $stmt = $pdo->prepare(
                "SELECT di.phone_number, di.dtmf_keys, di.created_at
                 FROM dtmf_inputs di
                 JOIN calls c ON di.call_id = c.id
                 WHERE c.user_id = ?
                 ORDER BY di.created_at DESC"
            );
            $stmt->execute([$user_id]);
            $dtmf_inputs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Fetched " . count($dtmf_inputs) . " DTMF inputs for user_id: $user_id");
            echo json_encode(['success' => true, 'dtmf_inputs' => $dtmf_inputs]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch DTMF inputs: ' . $e->getMessage()]);
            error_log("Get DTMF error: " . $e->getMessage());
        }
        break;

    case 'receive_dtmf':
        $call_id = filter_input(INPUT_POST, 'call_id', FILTER_VALIDATE_INT);
        $phone_number = filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_STRING);
        $dtmf_keys = filter_input(INPUT_POST, 'dtmf_keys', FILTER_SANITIZE_STRING);

        if ($call_id && $phone_number && $dtmf_keys && preg_match('/^[0-9*#]+$/', $dtmf_keys)) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM calls WHERE id = ?");
                $stmt->execute([$call_id]);
                if ($stmt->fetch()) {
                    $stmt = $pdo->prepare("INSERT INTO dtmf_inputs (call_id, phone_number, dtmf_keys) VALUES (?, ?, ?)");
                    $stmt->execute([$call_id, $phone_number, $dtmf_keys]);
                    error_log("Recorded DTMF input for call_id: $call_id, dtmf_keys: $dtmf_keys");
                    echo json_encode(['success' => true, 'message' => 'DTMF input recorded']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid call ID']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to record DTMF input: ' . $e->getMessage()]);
                error_log("Receive DTMF error: " . $e->getMessage());
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Missing or invalid fields']);
        }
        break;

    case 'updateCallerId':
        $caller_id = filter_input(INPUT_POST, 'callerid', FILTER_SANITIZE_STRING);
        if ($caller_id) {
            try {
                $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    if (updateCallerId($user['username'], $caller_id, $magnusBilling)) {
                        echo json_encode(['success' => true, 'message' => 'Caller ID updated successfully']);
                        error_log("Caller ID updated to $caller_id for user_id: $user_id");
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update Caller ID in MagnusBilling']);
                        error_log("Failed to update Caller ID for user_id: $user_id");
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                    error_log("User not found for user_id: $user_id");
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to update Caller ID: ' . $e->getMessage()]);
                error_log("Update Caller ID error: " . $e->getMessage());
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Caller ID is required']);
        }
        break;

    case 'update_caller_id':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            error_log("update_caller_id error: Invalid request method, user_id: $user_id");
            break;
        }

        $caller_id = filter_input(INPUT_POST, 'callerid', FILTER_SANITIZE_STRING);
        if (!$caller_id || !preg_match('/^\+?[1-9]\d{1,14}$/', $caller_id)) {
            echo json_encode(['success' => false, 'message' => 'Valid Caller ID (E.164 format) is required']);
            error_log("update_caller_id error: Invalid or missing caller_id, input: " . ($caller_id ?? 'none') . ", user_id: $user_id");
            break;
        }

        if (!$magnusBilling) {
            echo json_encode(['success' => false, 'message' => 'MagnusBilling not initialized']);
            error_log("update_caller_id error: MagnusBilling not initialized, user_id: $user_id");
            break;
        }

        try {
            $stmt = $pdo->prepare("SELECT sip_id, username FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'User not found']);
                error_log("update_caller_id error: User not found for user_id: $user_id");
                break;
            }

            if (empty($user['sip_id'])) {
                echo json_encode(['success' => false, 'message' => 'No SIP ID associated with this user']);
                error_log("update_caller_id error: No sip_id found for user_id: $user_id, username: {$user['username']}");
                break;
            }

            $result = $magnusBilling->update('sip', $user['sip_id'], ['callerid' => $caller_id]);

            if (isset($result['success']) && $result['success']) {
                $stmt = $pdo->prepare("UPDATE users SET caller_id = ? WHERE id = ?");
                $stmt->execute([$caller_id, $user_id]);
                echo json_encode(['success' => true, 'message' => 'Caller ID updated successfully']);
                error_log("update_caller_id success: Caller ID updated to $caller_id for user_id: $user_id, sip_id: {$user['sip_id']}, username: {$user['username']}");
            } else {
                $errorMsg = isset($result['error']) ? $result['error'] : 'Unknown error';
                echo json_encode(['success' => false, 'message' => 'Failed to update Caller ID: ' . $errorMsg]);
                error_log("update_caller_id error: MagnusBilling API error: $errorMsg, user_id: $user_id, sip_id: {$user['sip_id']}");
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            error_log("update_caller_id error: Database error: " . $e->getMessage() . ", user_id: $user_id");
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'MagnusBilling error: ' . $e->getMessage()]);
            error_log("update_caller_id error: MagnusBilling error: " . $e->getMessage() . ", user_id: $user_id");
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        error_log("Invalid action: $action");
        break;
}

// Clear output buffer
ob_end_flush();