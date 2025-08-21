<?php
require_once __DIR__ . '/../config.php';

use inc\classes\CSRFToken;
use Illuminate\Database\Capsule\Manager as DB;
use inc\classes\Auth;
use inc\classes\RKValidator as Validator;
use Controller\MagnusBilling;

// Start output buffering to prevent unwanted output
ob_start();
ini_set('display_errors', env('APP_DEBUG') ? E_ALL : 0);
ini_set('log_errors', env('APP_DEBUG') ? E_ALL : 0);
error_log('user_api.php_error_log');

// Include MagnusBilling class
// require_once __DIR__ . "/magnusBilling.php";

try {
    $magnusBilling = new MagnusBilling(env('MAGNUS_API_KEY'), env('MAGNUS_API_SECRET'));
    $magnusBilling->public_url = env('MAGNUS_PUBLIC_URL');
    error_log("MagnusBilling initialized successfully in userapi.php");
} catch (Exception $e) {
    error_log("MagnusBilling initialization error: " . $e->getMessage());
    $magnusBilling = null;
}

// Function to send password reset email using mail()
function sendResetEmail($email, $token)
{
    $from = 'noreply@yourdomain.com'; // Replace with your email
    $subject = 'Password Reset Request';
    $resetUrl = env('APP_URL')."/reset-password.php?email=" . urlencode($email) . "&token=$token";
    $body = "Click this link to reset your password: <a href='$resetUrl'>Reset Password</a>";
    $headers = "From: DataCaller <$from>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    try {
        if (mail($email, $subject, $body, $headers)) {
            return true;
        } else {
            error_log('Email sending error: mail() failed');
            return false;
        }
    } catch (Exception $e) {
        error_log('Email sending error: ' . $e->getMessage());
        return false;
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Log CSRF tokens for debugging
    error_log("Session CSRF token: " . ($_SESSION['csrf_token'] ?? 'unset'));
    error_log("POST CSRF token: " . ($_POST['csrf_token'] ?? 'unset'));

    // Validate CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';

    // check csrf_token
    if (!CSRFToken::getInstance()->validateToken($csrf_token)) {
        // Set the response code to 403 Forbidden
        http_response_code(403);
        echo json_encode(['message' => 'Invalid CSRF token', 'status' => 'error', 'className' => 'error']);
        exit;
    }

    // Get User Info action (from dashboard.php)
    if (isset($_POST['action']) && $_POST['action'] === 'get_user_info') {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['message' => 'Unauthorized: User not logged in', 'status' => 'error', 'className' => 'error']);
            exit;
        }

        try {
            $user = DB::table('users')->where('id', $_SESSION['user_id'])->first(['username']);

            if ($user) {
                echo json_encode([
                    'message' => 'User info retrieved successfully',
                    'status' => 'success',
                    'username' => $user->username??null,
                    'className' => 'success'
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'User not found', 'status' => 'error', 'className' => 'error']);
            }
        } catch (PDOException $e) {
            error_log('Get user info error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'Server error: ' . $e->getMessage(), 'status' => 'error', 'className' => 'error']);
            exit;
        }
        exit;
    }

    // Registration action
    if (isset($_POST['username'], $_POST['email'], $_POST['password'], $_POST['confirm-password'])) {
        // validate data
        $validator = new Validator;
        $rules = [
            'username' => 'required|alpha_num|min:3|max:20|unique:users,username',
            'password' => 'required|min:8',
            'confirm-password' => 'required|min:8|same:password',
            'email' => 'required|email|unique:users,email'
        ];
        $validate = $validator->validate($_POST, $rules);
        if ($validate->fails()) {
            echo json_encode([
                'message' => $validate->errors->all(),
                'status' => 'error',
                'className' => 'error'
            ]);
            exit;
        }
        $username = $validate->getValue('username');
        $email = $validate->getValue('email');
        $password = $validate->getValue('password');
        $confirm_password = $validate->getValue('confirm-password');
        $sip_domain = env('SIP_DOMAIN'); // Default SIP domain

        error_log("Registration attempt: username=$username, email=$email");

        try {
            // Check rate limit (max 5 per email per hour)
            error_log("Checking rate limit for email: $email");
            $attempts = DB::table('registration_attempts')
                ->where('email', $email)
                ->where('created_at', '>', DB::raw('NOW() - INTERVAL 1 HOUR'))
                ->count();

            if ($attempts > 5) {
                http_response_code(429);
                echo json_encode([
                    'message' => 'Too many registration attempts. Try again later.',
                    'status' => 'error',
                    'className' => 'error'
                ]);
                exit;
            }

            // Log registration attempt
            error_log("Logging registration attempt for email: $email");
            DB::table('registration_attempts')->insert([
                'email' => $email,
                'created_at' => DB::raw('NOW()')
            ]);

            // Start transaction
            error_log("Starting database transaction for user: $username");
            DB::beginTransaction();

            // Create user in MagnusBilling
            $magnusCreated = false;
            $sip_id = null;
            $magnus_user_id = null;

            if ($magnusBilling) {
                error_log("Creating MagnusBilling user for username: $username");
                $magnusResult = $magnusBilling->createUser([
                    'username' => $username,
                    'password' => $password,
                    'id_group' => '3',
                    'id_plan' => '1',
                    'active' => '1',
                ]);

                error_log("MagnusBilling createUser response: " . json_encode($magnusResult, JSON_PRETTY_PRINT));

                if ($magnusResult && isset($magnusResult['success']) && $magnusResult['success']) {
                    $magnusCreated = true;
                    error_log("MagnusBilling user created successfully for username: $username");

                    // Fetch SIP details with retry logic
                    $maxRetries = 2;
                    $retryCount = 0;
                    $retryDelay = 2; // Seconds

                    while ($retryCount <= $maxRetries && !$sip_id && !$magnus_user_id) {
                        error_log("Attempting to fetch SIP details for username: $username (retry $retryCount)");
                        $sipResult = $magnusBilling->read('sip');
                        error_log("Raw SIP response for username $username: " . print_r($sipResult, true));

                        // Handle nested response (use 'rows' key)
                        $sipRecords = $sipResult;
                        if (isset($sipResult['rows']) && is_array($sipResult['rows'])) {
                            $sipRecords = $sipResult['rows'];
                            error_log("Using nested 'rows' array from SIP response");
                        }

                        // Find matching SIP account
                        if (!empty($sipRecords) && is_array($sipRecords)) {
                            foreach ($sipRecords as $sip) {
                                $sip_username = null;
                                $matched_field = null;
                                if (isset($sip['name']) && $sip['name'] === $username) {
                                    $sip_username = $sip['name'];
                                    $matched_field = 'name';
                                } elseif (isset($sip['username']) && $sip['username'] === $username) {
                                    $sip_username = $sip['username'];
                                    $matched_field = 'username';
                                } elseif (isset($sip['accountcode']) && $sip['accountcode'] === $username) {
                                    $sip_username = $sip['accountcode'];
                                    $matched_field = 'accountcode';
                                } elseif (isset($sip['callerid']) && $sip['callerid'] === $username) {
                                    $sip_username = $sip['callerid'];
                                    $matched_field = 'callerid';
                                } elseif (isset($sip['idUserusername']) && $sip['idUserusername'] === $username) {
                                    $sip_username = $sip['idUserusername'];
                                    $matched_field = 'idUserusername';
                                }

                                if ($sip_username && isset($sip['id']) && isset($sip['id_user'])) {
                                    $sip_id = $sip['id'];
                                    $magnus_user_id = $sip['id_user'];
                                    error_log("Found SIP ID: $sip_id and User ID: $magnus_user_id for username: $username (matched on field: $matched_field)");
                                    break;
                                }
                            }
                        }

                        if (!$sip_id || !$magnus_user_id) {
                            error_log("No SIP account found for username: $username on retry $retryCount");
                            if ($retryCount < $maxRetries) {
                                sleep($retryDelay);
                                $retryCount++;
                            }
                        } else {
                            break;
                        }
                    }

                    if (!$sip_id || !$magnus_user_id) {
                        error_log("Failed to fetch SIP details for username: $username after $maxRetries retries");
                    }
                } else {
                    error_log("MagnusBilling user creation failed for username: $username");
                    $magnusCreated = false;
                }
            } else {
                error_log("MagnusBilling not initialized; proceeding without MagnusBilling for username=$username");
            }

            // Hash password for myapp authentication
            error_log("Hashing password for myapp user: $username");
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            error_log("Hashed password: " . $hashedPassword);

            // Insert new user into myapp database with magnus_password and sip_domain
            error_log("Preparing to insert user into myapp: username=$username, email=$email, magnus_username=$username, magnus_password=$password, sip_id=" . ($sip_id ?? 'NULL') . ", magnus_user_id=" . ($magnus_user_id ?? 'NULL') . ", sip_domain=$sip_domain");
            $userId = DB::table('users')->insertGetId([
                'username' => $username,
                'email' => $email,
                'password' => $hashedPassword,
                'magnus_password' => $password,       // Plain-text for MagnusBilling
                'sip_domain' => $sip_domain,
                'magnus_user_id' => $magnus_user_id, // May be null
                'sip_id' => $sip_id,         // May be null
                'magnus_username' => $username
            ]);
            error_log("Inserted user into myapp: ID=$userId, username=$username");

            // Commit transaction
            error_log("Committing database transaction for user: $username");
            DB::commit();

            // Set session for immediate login
            error_log("Setting session for user: ID=$userId, username=$username");
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $username;

            // Return success message
            error_log("Registration successful for username: $username");
            if (!$magnusCreated) {
                echo json_encode([
                    'message' => 'Registration successful, but MagnusBilling setup failed. Contact support.',
                    'status' => 'success',
                    'className' => 'success'
                ]);
            } elseif (!$sip_id || !$magnus_user_id) {
                echo json_encode([
                    'message' => 'Registration successful, but SIP details could not be fetched. Contact support.',
                    'status' => 'success',
                    'className' => 'success'
                ]);
            } else {
                echo json_encode(['message' => 'Registration successful', 'status' => 'success', 'className' => 'success']);
            }
        } catch (Exception $e) {
            error_log('Registration error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            DB::rollBack();
            http_response_code(500);
            echo json_encode(['message' => 'Error registering: Please try again.', 'status' => 'error', 'className' => 'error']);
            exit;
        }
        exit;
    }

    // Login action (from login.php)
    if (isset($_POST['username'], $_POST['password'])) {
        // validate data
        $validator = new Validator;
        $rules = [
            'username' => 'required|alpha_num|min:3|max:20',
            'password' => 'required|min:8',
        ];
        $validate = $validator->validate($_POST, $rules);
        if ($validate->fails()) {
            echo json_encode([
                'message' => $validate->errors->all(),
                'status' => 'error',
                'className' => 'error'
            ]);
            exit;
        }

        $username = $validate->getValue('username');
        $password = $validate->getValue('password');

        try {
            // Check login attempts (max 30 per hour)
            $attempts = DB::table('login_attempts')
                ->where('username', $username)
                ->where('created_at', '>', DB::raw('NOW() - INTERVAL 1 HOUR'))
                ->count();

            if ($attempts > 30) {
                http_response_code(429);
                echo json_encode(['message' => 'Too many login attempts. Try again later.', 'status' => 'error', 'className' => 'error']);
                exit;
            }

            // Log login attempt
            DB::table('login_attempts')->insert([
                'username' => $username,
                'created_at' => DB::raw('NOW()')
            ]);

            // Verify credentials
            $user = DB::table('users')
                ->select('id', 'username', 'password')
                ->where('username', $username)
                ->first();

            // Debug password verification
            error_log("Login attempt for user: $username, User found: " . ($user ? 'yes' : 'no'));
            if ($user) {
                error_log("Stored hash: " . $user->password);
                error_log("Password verify result: " . (password_verify($password, $user->password) ? 'true' : 'false'));
            }

            if ($user && password_verify($password, $user->password)) {
                $_SESSION['user_id'] = $user->id;
                $_SESSION['username'] = $user->username;
                // set user id in Auth class
                Auth::setUser($user->id);
                echo json_encode(['message' => 'Login successful', 'status' => 'success', 'className' => 'success']);
            } else {
                http_response_code(401);
                echo json_encode(['message' => 'Invalid username or password', 'status' => 'error', 'className' => 'error']);
            }
        } catch (PDOException $e) {
            error_log('Login error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'Server error: ' . $e->getMessage(), 'status' => 'error', 'className' => 'error']);
            exit;
        }
        exit;
    }

    // Password reset request (from forgot-password.php)
    if (isset($_POST['email']) && !isset($_POST['token'], $_POST['password'])) {
        // validate data
        $validator = new Validator;
        $validate = $validator->validate($_POST, ['email' => 'email|exists:users,email',]);
        if ($validate->fails()) {
            echo json_encode([
                'message' => $validate->errors->all(),
                'status' => 'error',
                'className' => 'error'
            ]);
            exit;
        }
        try {
            // Check reset request attempts (max 5 per hour)
            $resetCount = DB::table('password_resets')
                ->where('email', $email)
                ->where('created_at', '>', DB::raw('NOW() - INTERVAL 1 HOUR'))
                ->count();

            if ($resetCount > 5) {
                http_response_code(429);
                echo json_encode([
                    'message' => 'Too many reset requests. Try again later.',
                    'status' => 'error',
                    'className' => 'error'
                ]);
                exit;
            }

            // Generate token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store token
            DB::table('password_resets')->insert([
                'email' => $email,
                'token' => $token,
                'expires_at' => $expires,
                'created_at' => DB::raw('NOW()'),
            ]);

            // Send reset email
            if (sendResetEmail($email, $token)) {
                echo json_encode(['message' => 'A password reset email has been sent', 'status' => 'success', 'className' => 'success']);
            } else {
                http_response_code(500);
                echo json_encode(['message' => 'Failed to send reset email', 'status' => 'error', 'className' => 'error']);
            }
        } catch (PDOException $e) {
            error_log('Reset request error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['message' => 'Server error: ' . $e->getMessage(), 'status' => 'error', 'className' => 'error']);
            exit;
        }
        exit;
    }

    // Invalid request
    http_response_code(400);
    echo json_encode(['message' => 'Invalid request', 'status' => 'error', 'className' => 'error']);
}

// Clear output buffer
ob_end_clean();