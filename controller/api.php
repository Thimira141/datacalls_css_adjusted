<?php
require_once __DIR__ . '/../config.php';

use Illuminate\Database\Capsule\Manager as DB;
use inc\classes\CSRFToken;
use inc\classes\RKValidator as Validator;
use Controller\MagnusBilling;
use \inc\classes\GoogleTTS\GoogleTTSService;

// Start output buffering to prevent unwanted output
ob_start();

// Enable error reporting for debugging (disabled in production)
ini_set('display_errors', env('APP_DEBUG') ? E_ALL : 0);
ini_set('log_errors', env('APP_DEBUG') ? E_ALL : 0);
error_log("Starting api.php");

try {
    DB::connection();
    error_log("Database connection successful");
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    error_log("Database error: " . $e->getMessage());
    exit;
}

// Include MagnusBilling class
// require_once __DIR__ . "/magnusBilling.php";

try {
    $magnusBilling = new MagnusBilling(env('MAGNUS_API_KEY'), env('MAGNUS_API_SECRET'));
    $magnusBilling->public_url = env('MAGNUS_PUBLIC_URL');
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !CSRFToken::getInstance()->validateToken($_POST['csrf_token'] ?? '')) {
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
            $user = DB::table('users')->where('id', $user_id)->first(['username', 'magnus_password', 'sip_domain']);
            if ($user) {
                $response = [
                    'success' => true,
                    'sip_domain' => $user->sip_domain ?: 'Not Available',
                    'sip_username' => $user->username ?: 'Not Available',
                    'sip_password' => $user->magnus_password ?: 'Not Available'
                ];
                echo json_encode($response);
                error_log("Fetched SIP info for user_id: $user_id, username: " . ($user->username ?? 'none') . ", response: " . json_encode($response));
            } else {
                echo json_encode(['success' => false, 'message' => 'User not found']);
                error_log("Get SIP info error: User not found for user_id: $user_id");
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch SIP info: ' . $e->getMessage()]);
            error_log("Get SIP info error: " . $e->getMessage() . ", user_id: $user_id");
        }
        break;

    case 'get_contacts':
        try {
            $contacts = DB::table('contacts')->where('user_id', $user_id)->get()->toArray();
            error_log("Fetched " . count($contacts) . " contacts for user_id: $user_id");
            echo json_encode(['success' => true, 'contacts' => $contacts]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch contacts: ' . $e->getMessage()]);
            error_log("Get contacts error: " . $e->getMessage());
        }
        break;

    case 'add_contact':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            exit;
        }
        if (!CSRFToken::getInstance()->validateToken($_POST['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'User not logged in']);
            exit;
        }

        $validator = new Validator;
        $rules = [
            'name' => 'required|string',
            'phone_number' => 'required|regex:/^1\d{9,13}$/'
        ];
        $messages = [
            'regex' => ':attribute must start with 1 and be 10–14 digits (e.g., 12025550123)'
        ];
        $validate = $validator->validate($_POST, $rules, $messages = []);
        $validate->setAlias('phone_number', 'Phone Number');

        if ($validate->fails()) {
            echo json_encode([
                'success' => false,
                'message' => $validate->errors()->all(),
                'status' => 'error',
                'className' => 'error'
            ]);
            exit;
        }
        try {
            $query = DB::table('contacts')->insert([
                'user_id' => $_SESSION['user_id'],
                'name' => $validate->getValue('name'),
                'phone_number' => $validate->getValue('phone_number'),
            ]);
            if ($query) {
                echo json_encode(['success' => true, 'message' => 'Contact added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Contact added failed']);
            }
        } catch (Exception $e) {
            error_log("Add contact error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to add contact']);
        }
        break;

    case 'delete_contact':
        // validate data
        $validator = new Validator;
        $rules = ['id' => 'required|integer'];
        $messages = ['integer' => ':attribute must be a valid integer',];
        $validate = $validator->validate($_POST, $rules, $messages);
        if ($validate->fails()) {
            echo json_encode([
                'success' => false,
                'message' => $validate->errors()->all()
            ]);
            exit;
        }
        $id = $validate->getValue('id');
        try {
            $delete = DB::table('contacts')->where('id', $id)->where('user_id', $user_id)->delete();
            if ($delete > 0) {
                error_log("Deleted contact ID: $id for user_id: $user_id");
                echo json_encode(['success' => true, 'message' => 'Contact deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Contact not found or not authorized']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to delete contact: ' . $e->getMessage()]);
            error_log("Delete contact error: " . $e->getMessage());
        }
        break;

    case 'get_institutions':
        try {
            $institutions = DB::table('institutions')->where('user_id', $user_id)->get(['id', 'name'])->toArray();
            error_log("Fetched " . count($institutions) . " institutions for user_id: $user_id");
            echo json_encode(['success' => true, 'institutions' => $institutions]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch institutions: ' . $e->getMessage()]);
            error_log("Get institutions error: " . $e->getMessage());
        }
        break;

    case 'add_institution':
        // validate data
        $validator = new Validator;
        $rules = ['name' => 'required|string'];
        $messages = ['string' => ':attribute must be a valid string',];
        $validate = $validator->validate($_POST, $rules, $messages);
        if ($validate->fails()) {
            echo json_encode([
                'success' => false,
                'message' => $validate->errors()->all()
            ]);
            exit;
        }
        $name = $validate->getValue('name');
        try {
            $query = DB::table('institutions')->insert([
                'user_id' => $user_id,
                'name' => $name
            ]);
            if ($query) {
                error_log("Added institution: $name for user_id: $user_id");
                echo json_encode(['success' => true, 'message' => 'Institution added successfully']);
            } else {
                error_log("Added institution Failed: $name for user_id: $user_id");
                echo json_encode(['success' => false, 'message' => 'Institution added Failed']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to add institution: ' . $e->getMessage()]);
            error_log("Add institution error: " . $e->getMessage());
        }
        break;

    case 'delete_institution':
        // validate data
        $validator = new Validator;
        $rules = ['id' => 'required|integer'];
        $messages = ['integer' => ':attribute must be a valid integer',];
        $validate = $validator->validate($_POST, $rules, $messages);
        if ($validate->fails()) {
            echo json_encode([
                'success' => false,
                'message' => $validate->errors()->all()
            ]);
            exit;
        }
        $id = $validate->getValue('id');
        try {
            $query = DB::table('institutions')->where('id', $id)->where('user_id', $user_id)->delete();
            if ($query > 0) {
                error_log("Deleted institution ID: $id for user_id: $user_id");
                echo json_encode(['success' => true, 'message' => 'Institution deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Institution not found or not authorized']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to delete institution: ' . $e->getMessage()]);
            error_log("Delete institution error: " . $e->getMessage());
        }
        break;

    case 'get_merchants': //FIXME::failed to get merchants
        try {
            $merchants = DB::table('merchants')->where('user_id', $user_id)->get(['id', 'name'])->toArray();
            error_log("Fetched " . count($merchants) . " merchants for user_id: $user_id");
            echo json_encode(['success' => true, 'merchants' => $merchants]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch merchants: ' . $e->getMessage()]);
            error_log("Get merchants error: " . $e->getMessage());
        }
        break;

    case 'add_merchant':
        // validate data
        $validator = new Validator;
        $rules = ['name' => 'required|string'];
        $messages = ['string' => ':attribute must be a valid string',];
        $validate = $validator->validate($_POST, $rules, $messages);
        if ($validate->fails()) {
            echo json_encode([
                'success' => false,
                'message' => $validate->errors()->all()
            ]);
            exit;
        }
        $name = $validate->getValue('name');
        try {
            $query = DB::table('merchants')->insert([
                'user_id' => $user_id,
                'name' => $name
            ]);
            if ($query) {
                error_log("Added merchant: $name for user_id: $user_id");
                echo json_encode(['success' => true, 'message' => 'Merchant added successfully']);
            } else {
                error_log("Add merchant failed: $name for user_id: $user_id");
                echo json_encode(['success' => false, 'message' => 'Merchant added failed']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to add merchant: ' . $e->getMessage()]);
            error_log("Add merchant error: " . $e->getMessage());
        }
        break;

    case 'delete_merchant':
        // validate data
        $validator = new Validator;
        $rules = ['id' => 'required|integer'];
        $messages = ['integer' => ':attribute must be a valid integer',];
        $validate = $validator->validate($_POST, $rules, $messages);
        if ($validate->fails()) {
            echo json_encode([
                'success' => false,
                'message' => $validate->errors()->all()
            ]);
            exit;
        }
        $id = $validate->getValue('id');
        try {
            $query = DB::table('merchants')->where('id', $id)->where('user_id', $user_id)->delete();
            if ($query > 0) {
                error_log("Deleted merchant ID: $id for user_id: $user_id");
                echo json_encode(['success' => true, 'message' => 'Merchant deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Merchant not found or not authorized']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to delete merchant: ' . $e->getMessage()]);
            error_log("Delete merchant error: " . $e->getMessage());
        }
        break;

    case 'get_ivr_profiles':
        try {
            $ivr_profiles = DB::table('ivr_profiles')->where('user_id', $user_id)->get()->toArray();
            error_log("Fetched " . count($ivr_profiles) . " IVR profiles for user_id: $user_id");
            echo json_encode(['success' => true, 'ivr_profiles' => $ivr_profiles]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch IVR profiles: ' . $e->getMessage()]);
            error_log("Get IVR profiles error: " . $e->getMessage());
        }
        break;

    case 'add_ivr_profile':
        // Validate data using Validator
        $validator = new Validator;
        $rules = [
            'profile_name' => 'required|string',
            'institution_name' => 'required|string',
            'caller_id' => 'required|string',
            'callback_number' => 'required|string',
            'merchant_name' => 'required|string',
            'amount' => 'required|numeric|min:0'
        ];
        $messages = [
            'required' => ':attribute is required',
            'string' => ':attribute must be a valid string',
            'numeric' => ':attribute must be a valid number',
            'min' => ':attribute must be at least :min',
            'max' => ':attribute must not exceed :max characters'
        ];
        $validate = $validator->validate($_POST, $rules, $messages);
        $validate->setAliases([
            'profile_name' => 'Profile Name',
            'institution_name' => 'Institution Name',
            'caller_id' => 'Caller ID',
            'callback_number' => 'Callback Number',
            'merchant_name' => 'Merchant Name',
            'amount' => 'Amount'
        ]);
        if ($validate->fails()) {
            echo json_encode([
                'success' => false,
                'message' => $validate->errors()->all(),
                'status' => 'error',
                'className' => 'error'
            ]);
            exit;
        }
        try {
            $data = $validate->getValidData();
            $data['user_id'] = $user_id;
            $query = DB::table('ivr_profiles')->insert($data);
            if ($query) {
                error_log("Added IVR profile: {$data['profile_name']} for user_id: $user_id");
                echo json_encode(['success' => true, 'message' => 'IVR Profile added successfully']);
            } else {
                error_log("Add IVR profile failed: {$data['profile_name']} for user_id: $user_id");
                echo json_encode(['success' => false, 'message' => 'IVR Profile add failed']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to add IVR profile: ' . $e->getMessage()]);
            error_log("Add IVR profile error: " . $e->getMessage());
        }
        break;

    case 'delete_ivr_profile':
        // Validate IVR profile ID
        $validator = new Validator;
        $rules = ['id' => 'required|integer'];
        $messages = ['integer' => ':attribute must be a valid integer'];
        $validate = $validator->validate($_POST, $rules, $messages);
        $validate->setAlias('id', 'IVR Profile ID');
        if ($validate->fails()) {
            echo json_encode([
                'success' => false,
                'message' => $validate->errors()->all()
            ]);
            exit;
        }
        $id = $validate->getValue('id');
        try {
            $deleted = DB::table('ivr_profiles')->where('id', $id)->where('user_id', $user_id)->delete();
            if ($deleted > 0) {
                error_log("Deleted IVR profile ID: $id for user_id: $user_id");
                echo json_encode(['success' => true, 'message' => 'IVR Profile deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'IVR Profile not found or not authorized']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to delete IVR profile: ' . $e->getMessage()]);
            error_log("Delete IVR profile error: " . $e->getMessage());
        }
        break;

    case 'get_calls':
        try {
            $calls = DB::table('calls')
                ->where('user_id', $user_id)
                ->orderBy('created_at', 'desc')
                ->get([
                    'id',
                    DB::raw('customer_number AS number_called'),
                    'caller_id',
                    'created_at',
                    'duration',
                    'institution_name',
                    'merchant_name',
                    'amount'
                ])
                ->toArray();

            error_log("Fetched " . count($calls) . " calls for user_id: $user_id");
            echo json_encode([
                'success' => true,
                'calls' => $calls
            ]);
        } catch (Exception $e) {
            error_log("Get calls error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Failed to fetch call records: ' . $e->getMessage()
            ]);
        }
        break;

    case 'delete_call':
        // Validate input
        $validator = new Validator;
        $rules = ['ids' => 'required|array'];
        $messages = ['array' => ':attribute must be an array of IDs'];
        $validate = $validator->validate($_POST, $rules, $messages);
        $validate->setAlias('ids', 'Call IDs');

        if ($validate->fails()) {
            echo json_encode([
                'success' => false,
                'message' => $validate->errors()->all()
            ]);
            exit;
        }

        $ids = $validate->getValue('ids');
        // Ensure all IDs are integers
        $valid_ids = array_filter($ids, function ($id) {
            return filter_var($id, FILTER_VALIDATE_INT) !== false;
        });

        if (count($valid_ids) !== count($ids)) {
            echo json_encode(['success' => false, 'message' => 'Invalid call IDs provided']);
            exit;
        }

        try {
            $deleted = DB::table('calls')
                ->where('user_id', $user_id)
                ->whereIn('id', $valid_ids)
                ->delete();

            if ($deleted > 0) {
                error_log("Deleted $deleted call records for user_id: $user_id");
                echo json_encode(['success' => true, 'message' => 'Call record(s) deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'No call records found or not authorized']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to delete call record(s): ' . $e->getMessage()]);
            error_log("Delete call error: " . $e->getMessage());
        }
        break;

    case 'receive_cdr':
        // Validate input
        $validator = new Validator;
        $rules = [
            'call_id' => 'required|integer',
            'customer_number' => 'required|string',
            'caller_id' => 'required|string',
            'duration' => 'required|integer',
            'created_at' => 'required|string'
        ];
        $messages = [
            'required' => ':attribute is required',
            'integer' => ':attribute must be a valid integer',
            'string' => ':attribute must be a valid string'
        ];
        $validate = $validator->validate($_POST, $rules, $messages);
        $validate->setAliases([
            'call_id' => 'Call ID',
            'customer_number' => 'Customer Number',
            'caller_id' => 'Caller ID',
            'duration' => 'Duration',
            'created_at' => 'Created At'
        ]);
        if ($validate->fails()) {
            echo json_encode([
                'success' => false,
                'message' => $validate->errors()->all()
            ]);
            exit;
        }
        try {
            $call = DB::table('calls')->where('id', $call_id)->where('user_id', $user_id)->first();
            if ($call) {
                $updated = DB::table('calls')->where('id', $call_id)->update($validate->getValidData());
                error_log("Recorded CDR for call_id: $call_id");
                echo json_encode(['success' => true, 'message' => 'CDR recorded']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid or unauthorized call ID']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to record CDR: ' . $e->getMessage()]);
            error_log("Receive CDR error: " . $e->getMessage());
        }
        break;

    case 'initiate_call':
        if (!$magnusBilling) {
            echo json_encode(['success' => false, 'message' => 'MagnusBilling not initialized']);
            error_log("initiate_call: MagnusBilling not initialized");
            break;
        }
        // data validation
        $validator = new Validator;
        $rules = [
            'institution_name' => 'required|string|max:255',
            'customer_name' => 'required|string|max:255',
            'customer_number' => 'required|string|max:20',
            'caller_id' => 'required|string|max:20',
            'callback_method' => 'required|string|in:call,sms,email', // adjust options as needed
            'callback_number' => 'required|string|max:20',
            'merchant_name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'magnus_ivr_id' => 'required|integer|min:1'
        ];
        $messages = [
            'required' => ':attribute is required',
            'string' => ':attribute must be a valid string',
            'numeric' => ':attribute must be a valid number',
            'integer' => ':attribute must be a valid integer',
            'min' => ':attribute must be at least :min',
            'max' => ':attribute must not exceed :max characters',
            'in' => ':attribute must be one of: :allowed'
        ];
        $validate = $validator->validate($_POST, $rules, $messages);
        $validate->setAliases([
            'institution_name' => 'Institution Name',
            'customer_name' => 'Customer Name',
            'customer_number' => 'Customer Number',
            'caller_id' => 'Caller ID',
            'callback_method' => 'Callback Method',
            'callback_number' => 'Callback Number',
            'merchant_name' => 'Merchant Name',
            'amount' => 'Amount',
            'magnus_ivr_id' => 'Magnus IVR ID'
        ]);
        if ($validate->fails()) {
            echo json_encode([
                'success' => false,
                'message' => implode('<br>', $validate->errors()->all()),
                'status' => 'error',
                'className' => 'error'
            ]);
            exit;
        }

        $institution_name = $validate->getValue('institution_name');
        $customer_name = $validate->getValue('customer_name');
        $customer_number = $validate->getValue('customer_number');
        $caller_id = $validate->getValue('caller_id');
        $callback_method = $validate->getValue('callback_method');
        $callback_number = $validate->getValue('callback_number');
        $merchant_name = $validate->getValue('merchant_name');
        $amount = $validate->getValue('amount');
        $magnus_ivr_id = $validate->getValue('magnus_ivr_id');

        try {

            $user = DB::table('users')
                ->select('username', 'magnus_password')
                ->where('id', $user_id)
                ->first();
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
            // google tts api
            $googleTTS = new GoogleTTSService;
            $ssml_script = $googleTTS->buildSSML($institution_name, $customer_name, $amount, $merchant_name);
            try {
                $synthesize = $googleTTS->synthesize($ssml_script);
                if ($synthesize) {
                    $tts_audio_url = $googleTTS->getFileURL();
                    // close the google tts
                    $googleTTS->close();
                    // call data
                    $callData = [
                        'destination' => 'custom-ivr-call,s,1',
                        'callerid' => $caller_id,
                        'callback' => $callback_destination,
                        'id_user' => $magnusBilling->getId('user', 'firstname', $user['username']),
                        'ivr_id' => $magnus_ivr_id,
                        'customer_number' => $customer_number,
                        'tts_script' => $tts_script,
                        'tts_audio_url' =>$tts_audio_url
                    ];
                    $result = $magnusBilling->create('call', $callData);
                    if (!isset($result['success']) || !$result['success']) {
                        echo json_encode(['success' => false, 'message' => 'Failed to initiate call: ' . ($result['error'] ?? 'Unknown error')]);
                        error_log("MagnusBilling API Error (initiate_call): " . json_encode($result));
                        break;
                    }

                    $magnus_call_id = $result['id'] ?? DB::getPdo()->lastInsertId();

                    $call_id = DB::table('calls')->insertGetId([
                        'user_id' => $user_id,
                        'customer_number' => $customer_number,
                        'caller_id' => $caller_id,
                        'callback_method' => $callback_method,
                        'callback_number' => $callback_destination,
                        'magnus_call_id' => $magnus_call_id,
                        'institution_name' => $institution_name,
                        'merchant_name' => $merchant_name,
                        'amount' => $amount,
                        'tts_script' => (string) $tts_script . "\n\n tts_audio_url:" .$tts_audio_url
                    ]);

                    if ((bool) $call_id) {
                        error_log("Initiated call: call_id=$call_id, magnus_call_id=$magnus_call_id, customer_number=$customer_number, callback_method=$callback_method");
                        echo json_encode(['success' => true, 'message' => 'Call initiated successfully', 'call_id' => $call_id]);
                    } else {
                        error_log("Initiated call failed: call_id=$call_id, magnus_call_id=$magnus_call_id, customer_number=$customer_number, callback_method=$callback_method");
                        echo json_encode(['success' => false, 'message' => 'Call initiated false', 'call_id' => $call_id]);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to TTS synthesize']);
                    error_log("TTS synthesize failed ");
                    exit;
                }
            } catch (\Throwable $th) {
                echo json_encode(['success' => false, 'message' => 'Failed to TTS synthesize: ' . $th->getMessage()]);
                error_log("TTS synthesize error: " . $th->getMessage());
                exit;
            }

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to initiate call: ' . $e->getMessage()]);
            error_log("Initiate call error: " . $e->getMessage());
        }
        break;

    case 'end_call':
        if (!$magnusBilling) {
            echo json_encode(['success' => false, 'message' => 'MagnusBilling not initialized']);
            error_log("end_call: MagnusBilling not initialized");
            break;
        }
        // Validate input
        $validator = new Validator;

        $rules = ['call_id' => 'required|integer|min:1'];
        $messages = [
            'required' => ':attribute is required',
            'integer' => ':attribute must be a valid integer',
            'min' => ':attribute must be at least :min'
        ];

        $validate = $validator->validate($_POST, $rules, $messages);
        $validate->setAlias('call_id', 'Call ID');

        if (!$validate->passes()) {
            echo json_encode([
                'success' => false,
                'message' => implode('<br>', $validate->errors()->all())
            ]);
            exit;
        }

        $call_id = $validate->getValue('call_id');

        try {
            $call = DB::table('calls')
                ->select('magnus_call_id')
                ->where('id', $call_id)
                ->where('user_id', $user_id)
                ->first();

            if ($call && $call->magnus_call_id) {
                $result = $magnusBilling->destroy('call', $call->magnus_call_id);

                if (!isset($result['success']) || !$result['success']) {
                    error_log("MagnusBilling API Error (end_call): " . json_encode($result));
                    echo json_encode([
                        'success' => false,
                        'message' => 'Failed to end call: ' . ($result['error'] ?? 'Unknown error')
                    ]);
                    exit;
                }

                error_log("Ended call: call_id=$call_id, magnus_call_id={$call->magnus_call_id}");
                echo json_encode(['success' => true, 'message' => 'Call ended successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid or unauthorized call ID']);
            }
        } catch (Exception $e) {
            error_log("End call error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to end call: ' . $e->getMessage()]);
        }
        break;

    case 'get_call_status':
        if (!$magnusBilling) {
            echo json_encode(['success' => false, 'message' => 'MagnusBilling not initialized']);
            error_log("get_call_status: MagnusBilling not initialized");
            break;
        }
        // Validate input
        $validator = new Validator;

        $rules = ['call_id' => 'required|integer|min:1'];
        $messages = [
            'required' => ':attribute is required',
            'integer' => ':attribute must be a valid integer',
            'min' => ':attribute must be at least :min'
        ];

        $validate = $validator->validate($_GET, $rules, $messages);
        $validate->setAlias('call_id', 'Call ID');

        if (!$validate->passes()) {
            echo json_encode([
                'success' => false,
                'message' => implode('<br>', $validate->errors()->all())
            ]);
            exit;
        }

        $call_id = $validate->getValue('call_id');

        try {
            $call = DB::table('calls')
                ->select('magnus_call_id')
                ->where('id', $call_id)
                ->where('user_id', $user_id)
                ->first();

            if ($call && $call->magnus_call_id) {
                $magnusBilling->setFilter('id', $call->magnus_call_id, 'eq', 'integer');
                $result = $magnusBilling->read('callOnLine');
                $magnusBilling->clearFilter();

                if (!empty($result['rows'][0])) {
                    $status = strtolower($result['rows'][0]['status'] ?? 'calling');
                    error_log("Call status for call_id=$call_id: $status");
                    echo json_encode(['success' => true, 'status' => $status]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Call not found in MagnusBilling']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid or unauthorized call ID']);
            }
        } catch (Exception $e) {
            error_log("Get call status error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to fetch call status: ' . $e->getMessage()]);
        }
        break;

    case 'check_dtmf':
        if (!$magnusBilling) {
            echo json_encode(['success' => false, 'message' => 'MagnusBilling not initialized']);
            error_log("check_dtmf: MagnusBilling not initialized");
            break;
        }
        // Validate input
        $validator = new Validator;

        $rules = ['call_id' => 'required|integer|min:1'];
        $messages = [
            'required' => ':attribute is required',
            'integer' => ':attribute must be a valid integer',
            'min' => ':attribute must be at least :min'
        ];

        $validate = $validator->validate($_GET, $rules, $messages);
        $validate->setAlias('call_id', 'Call ID');

        if (!$validate->passes()) {
            echo json_encode([
                'success' => false,
                'message' => implode('<br>', $validate->errors()->all())
            ]);
            exit;
        }

        $call_id = $validate->getValue('call_id');

        try {
            $call = DB::table('calls')
                ->select('magnus_call_id', 'customer_number', 'tts_script')
                ->where('id', $call_id)
                ->where('user_id', $user_id)
                ->first();

            if ($call && $call->magnus_call_id) {
                $result = $magnusBilling->query([
                    'module' => 'callOnLine',
                    'action' => 'getDtmf',
                    'id' => $call->magnus_call_id
                ]);

                if (!empty($result['success']) && !empty($result['dtmf'])) {
                    $dtmf = $result['dtmf'];

                    DB::table('dtmf_inputs')->insert([
                        'call_id' => $call_id,
                        'phone_number' => $call->customer_number,
                        'dtmf_keys' => $dtmf
                    ]);

                    error_log("DTMF '$dtmf' recorded for call_id: $call_id");

                    switch ($dtmf) {
                        case '1':
                            $magnusBilling->query([
                                'module' => 'callOnLine',
                                'action' => 'playAudio',
                                'id' => $call->magnus_call_id,
                                'audio' => 'thank_you.wav'
                            ]);
                            $magnusBilling->destroy('call', $call->magnus_call_id);
                            break;

                        case '2':
                            $magnusBilling->query([
                                'module' => 'callOnLine',
                                'action' => 'playAudio',
                                'id' => $call->magnus_call_id,
                                'audio' => 'hold_music.wav'
                            ]);
                            break;

                        case '3':
                            $magnusBilling->query([
                                'module' => 'callOnLine',
                                'action' => 'playTts',
                                'id' => $call->magnus_call_id,
                                'tts_script' => $call->tts_script
                            ]);
                            break;
                    }
                    echo json_encode(['success' => true, 'dtmf' => $dtmf]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'No DTMF input found']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid or unauthorized call ID']);
            }
        } catch (Exception $e) {
            error_log("Check DTMF error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to check DTMF: ' . $e->getMessage()]);
        }
        break;

    case 'toggle_mute':
        if (!$magnusBilling) {
            echo json_encode(['success' => false, 'message' => 'MagnusBilling not initialized']);
            error_log("toggle_mute: MagnusBilling not initialized");
            break;
        }
        // Validate input
        $validator = new Validator;

        $rules = [
            'call_id' => 'required|integer|min:1',
            'mute' => 'required|boolean'
        ];

        $messages = [
            'required' => ':attribute is required',
            'integer' => ':attribute must be a valid integer',
            'boolean' => ':attribute must be true or false',
            'min' => ':attribute must be at least :min'
        ];

        $validate = $validator->validate($_POST, $rules, $messages);
        $validate->setAliases([
            'call_id' => 'Call ID',
            'mute' => 'Mute Flag'
        ]);

        if (!$validate->passes()) {
            echo json_encode([
                'success' => false,
                'message' => implode('<br>', $validate->errors()->all())
            ]);
            exit;
        }

        $call_id = $validate->getValue('call_id');
        $mute = $validate->getValue('mute');

        try {
            $call = DB::table('calls')
                ->select('magnus_call_id', 'callback_method', 'customer_number')
                ->where('id', $call_id)
                ->where('user_id', $user_id)
                ->first();

            if (!$call || !$call->magnus_call_id) {
                echo json_encode(['success' => false, 'message' => 'Invalid or unauthorized call ID']);
                exit;
            }

            $user = DB::table('users')
                ->select('username')
                ->where('id', $user_id)
                ->first();

            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit;
            }

            $result = $magnusBilling->query([
                'module' => 'callOnLine',
                'action' => 'toggleMute',
                'id' => $call->magnus_call_id,
                'sipuser' => $user->username,
                'mute' => $mute ? '1' : '0'
            ]);

            if (!empty($result['success'])) {
                error_log("Mute toggled for call_id: $call_id, mute: " . ($mute ? 'on' : 'off'));
                echo json_encode(['success' => true, 'message' => 'Mute toggled successfully']);
            } else {
                error_log("MagnusBilling API Error (toggle_mute): " . json_encode($result));
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to toggle mute: ' . ($result['error'] ?? 'Unknown error')
                ]);
            }
        } catch (Exception $e) {
            error_log("Toggle mute error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to toggle mute: ' . $e->getMessage()]);
        }
        break;

    case 'get_dtmf':
        try {
            $dtmf_inputs = DB::table('dtmf_inputs')
                ->join('calls', 'dtmf_inputs.call_id', '=', 'calls.id')
                ->where('calls.user_id', $user_id)
                ->orderBy('dtmf_inputs.created_at', 'desc')
                ->get(['dtmf_inputs.phone_number', 'dtmf_inputs.dtmf_keys', 'dtmf_inputs.created_at'])
                ->toArray();
            error_log("Fetched " . count($dtmf_inputs) . " DTMF inputs for user_id: $user_id");
            echo json_encode(['success' => true, 'dtmf_inputs' => $dtmf_inputs]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch DTMF inputs: ' . $e->getMessage()]);
            error_log("Get DTMF error: " . $e->getMessage());
        }
        break;

    case 'receive_dtmf':
        // Validate input
        $validator = new Validator;
        $rules = [
            'call_id' => 'required|integer',
            'phone_number' => 'required|string',
            'dtmf_keys' => 'required|string|regex:/^[0-9*#]+$/'
        ];
        $messages = [
            'required' => ':attribute is required',
            'integer' => ':attribute must be a valid integer',
            'string' => ':attribute must be a valid string',
            'regex' => ':attribute must contain only digits, * or #'
        ];
        $validate = $validator->validate($_POST, $rules, $messages);
        $validate->setAliases([
            'call_id' => 'Call ID',
            'phone_number' => 'Phone Number',
            'dtmf_keys' => 'DTMF Keys'
        ]);
        if ($validate->fails()) {
            echo json_encode([
                'success' => false,
                'message' => $validate->errors()->all()
            ]);
            exit;
        }
        $call_id = $validate->getValue('call_id');
        $phone_number = $validate->getValue('phone_number');
        $dtmf_keys = $validate->getValue('dtmf_keys');
        try {
            $call = DB::table('calls')->where('id', $call_id)->first();
            if ($call) {
                $inserted = DB::table('dtmf_inputs')->insert([
                    'call_id' => $call_id,
                    'phone_number' => $phone_number,
                    'dtmf_keys' => $dtmf_keys
                ]);
                error_log("Recorded DTMF input for call_id: $call_id, dtmf_keys: $dtmf_keys");
                echo json_encode(['success' => true, 'message' => 'DTMF input recorded']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid call ID']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to record DTMF input: ' . $e->getMessage()]);
            error_log("Receive DTMF error: " . $e->getMessage());
        }
        break;

    case 'updateCallerId':
        // Validate input
        $validator = new Validator;

        $rules = ['callerid' => 'required|string'];
        $messages = [
            'required' => ':attribute is required',
            'string' => ':attribute must be a valid string',
            'max' => ':attribute must not exceed :max characters'
        ];

        $validate = $validator->validate($_POST, $rules, $messages);
        $validate->setAlias('callerid', 'Caller ID');

        if (!$validate->passes()) {
            echo json_encode([
                'success' => false,
                'message' => implode('<br>', $validate->errors()->all())
            ]);
            exit;
        }

        $caller_id = $validate->getValue('callerid');

        try {
            $user = DB::table('users')
                ->select('username')
                ->where('id', $user_id)
                ->first();

            if (!$user) {
                error_log("User not found for user_id: $user_id");
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit;
            }

            if (updateCallerId($user->username, $caller_id, $magnusBilling)) {
                error_log("Caller ID updated to $caller_id for user_id: $user_id");
                echo json_encode(['success' => true, 'message' => 'Caller ID updated successfully']);
            } else {
                error_log("Failed to update Caller ID for user_id: $user_id");
                echo json_encode(['success' => false, 'message' => 'Failed to update Caller ID in MagnusBilling']);
            }
        } catch (Exception $e) {
            error_log("Update Caller ID error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to update Caller ID: ' . $e->getMessage()]);
        }
        break;

    case 'update_caller_id':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            error_log("update_caller_id error: Invalid request method, user_id: $user_id");
            break;
        }

        // Validate input
        $validator = new Validator;

        $rules = ['callerid' => 'required|string|regex:/^\+?[1-9]\d{1,14}$/'];
        $messages = [
            'required' => ':attribute is required',
            'string' => ':attribute must be a valid string',
            'regex' => ':attribute must be in valid E.164 format'
        ];

        $validate = $validator->validate($_POST, $rules, $messages);
        $validate->setAlias('callerid', 'Caller ID');

        if (!$validate->passes()) {
            $caller_id = $_POST['callerid'] ?? 'none';
            error_log("update_caller_id error: Invalid or missing caller_id, input: $caller_id, user_id: $user_id");
            echo json_encode([
                'success' => false,
                'message' => implode('<br>', $validate->errors()->all())
            ]);
            exit;
        }

        $caller_id = $validate->getValue('callerid');

        // Check MagnusBilling instance
        if (!$magnusBilling) {
            error_log("update_caller_id error: MagnusBilling not initialized, user_id: $user_id");
            echo json_encode(['success' => false, 'message' => 'MagnusBilling not initialized']);
            exit;
        }

        try {
            $user = DB::table('users')
                ->select('sip_id', 'username')
                ->where('id', $user_id)
                ->first();

            if (!$user) {
                error_log("update_caller_id error: User not found for user_id: $user_id");
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit;
            }

            if (empty($user->sip_id)) {
                error_log("update_caller_id error: No sip_id found for user_id: $user_id, username: {$user->username}");
                echo json_encode(['success' => false, 'message' => 'No SIP ID associated with this user']);
                exit;
            }

            $result = $magnusBilling->update('sip', $user->sip_id, ['callerid' => $caller_id]);

            if (!empty($result['success'])) {
                DB::table('users')
                    ->where('id', $user_id)
                    ->update(['caller_id' => $caller_id]);

                error_log("update_caller_id success: Caller ID updated to $caller_id for user_id: $user_id, sip_id: {$user->sip_id}, username: {$user->username}");
                echo json_encode(['success' => true, 'message' => 'Caller ID updated successfully']);
            } else {
                $errorMsg = $result['error'] ?? 'Unknown error';
                error_log("update_caller_id error: MagnusBilling API error: $errorMsg, user_id: $user_id, sip_id: {$user->sip_id}");
                echo json_encode(['success' => false, 'message' => 'Failed to update Caller ID: ' . $errorMsg]);
            }
        } catch (Exception $e) {
            error_log("update_caller_id error: Exception: " . $e->getMessage() . ", user_id: $user_id");
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }

        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        error_log("Invalid action: $action");
        break;
}

// Clear output buffer
ob_end_flush();