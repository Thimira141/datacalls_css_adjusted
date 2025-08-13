<?php
// Start session to enable session variables
session_start();

// Start output buffering to prevent unwanted output
ob_start();

// Include MagnusBilling class
require_once "./magnusBilling.php";

try {
    $magnusBilling = new MagnusBilling('8x9vqM4JWnxUbDZGJm9HHlqKD8R8vvJ3', 'xJdpyCjiVrSrabu2fnN53BNdGCDc0O6B');
    $magnusBilling->public_url = "http://72.60.25.185/mbilling";
    error_log("MagnusBilling initialized successfully in userapi.php");
} catch (Exception $e) {
    error_log("MagnusBilling initialization error: " . $e->getMessage());
    $magnusBilling = null;
}

// Database configuration
$host = 'localhost';
$dbname = 'datacalls_css_adjusted';
$dbuser = 'root';
$dbpass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("Database connected successfully in userapi.php");
} catch (PDOException $e) {
    error_log('Database connection error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['message' => 'Server error: Database connection failed', 'status' => 'error', 'className' => 'error']);
    exit;
}

// Function to send password reset email using mail()
function sendResetEmail($email, $token)
{
    $from = 'noreply@yourdomain.com'; // Replace with your email
    $subject = 'Password Reset Request';
    $resetUrl = "http://localhost/reset-password.php?email=" . urlencode($email) . "&token=$token";
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
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
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
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                echo json_encode([
                    'message' => 'User info retrieved successfully',
                    'status' => 'success',
                    'username' => $user['username'],
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
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password']; // Plain-text password for MagnusBilling
        $confirm_password = $_POST['confirm-password'];
        $sip_domain = '72.60.25.185'; // Default SIP domain

        error_log("Registration attempt: username=$username, email=$email");

        if (empty($username) || empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode(['message' => 'All fields are required', 'status' => 'error', 'className' => 'error']);
            exit;
        }

        if (!preg_match('/^[A-Za-z0-9]{3,20}$/', $username)) {
            http_response_code(400);
            echo json_encode(['message' => 'Username must be 3-20 characters, letters and numbers only', 'status' => 'error', 'className' => 'error']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid email format', 'status' => 'error', 'className' => 'error']);
            exit;
        }

        if (!preg_match('/^.{8,}$/', $password)) {
            http_response_code(400);
            echo json_encode(['message' => 'Password must be at least 8 characters', 'status' => 'error', 'className' => 'error']);
            exit;
        }
        if ($password !== $confirm_password) {
            http_response_code(400);
            echo json_encode(['message' => 'Passwords do not match', 'status' => 'error', 'className' => 'error']);
            exit;
        }

        try {
            // Check rate limit (max 5 per email per hour)
            error_log("Checking rate limit for email: $email");
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM registration_attempts WHERE email = ? AND created_at > NOW() - INTERVAL 1 HOUR");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 5) {
                http_response_code(429);
                echo json_encode(['message' => 'Too many registration attempts. Try again later.', 'status' => 'error', 'className' => 'error']);
                exit;
            }

            // Log registration attempt
            error_log("Logging registration attempt for email: $email");
            $stmt = $pdo->prepare("INSERT INTO registration_attempts (email, created_at) VALUES (?, NOW())");
            $stmt->execute([$email]);

            // Check if username or email exists in myapp
            error_log("Checking if username or email exists: $username, $email");
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(['message' => 'Username or email already exists', 'status' => 'error', 'className' => 'error']);
                exit;
            }

            // Start transaction
            error_log("Starting database transaction for user: $username");
            $pdo->beginTransaction();

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
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, magnus_password, sip_domain, magnus_user_id, sip_id, magnus_username)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $username,
                $email,
                $hashedPassword,
                $password, // Store plain-text password in magnus_password
                $sip_domain,
                $magnus_user_id, // NULL if not fetched
                $sip_id,         // NULL if not fetched
                $username        // Store MagnusBilling username
            ]);
            $userId = $pdo->lastInsertId();
            error_log("Inserted user into myapp: ID=$userId, username=$username");

            // Commit transaction
            error_log("Committing database transaction for user: $username");
            $pdo->commit();

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
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['message' => 'Error registering: Please try again.', 'status' => 'error', 'className' => 'error']);
            exit;
        }
        exit;
    }

    // Login action (from login.php)
    if (isset($_POST['username'], $_POST['password'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            http_response_code(400);
            echo json_encode(['message' => 'Username and password are required', 'status' => 'error', 'className' => 'error']);
            exit;
        }

        try {
            // Check login attempts (max 30 per hour)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE username = ? AND created_at > NOW() - INTERVAL 1 HOUR");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 30) {
                http_response_code(429);
                echo json_encode(['message' => 'Too many login attempts. Try again later.', 'status' => 'error', 'className' => 'error']);
                exit;
            }

            // Log login attempt
            $stmt = $pdo->prepare("INSERT INTO login_attempts (username, created_at) VALUES (?, NOW())");
            $stmt->execute([$username]);

            // Verify credentials
            $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Debug password verification
            error_log("Login attempt for user: $username, User found: " . ($user ? 'yes' : 'no'));
            if ($user) {
                error_log("Stored hash: " . $user['password']);
                error_log("Password verify result: " . (password_verify($password, $user['password']) ? 'true' : 'false'));
            }

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
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
        $email = trim($_POST['email']);
        if (empty($email)) {
            http_response_code(400);
            echo json_encode(['message' => 'Email is required', 'status' => 'error', 'className' => 'error']);
            exit;
        }
        try {
            // Check reset request attempts (max 5 per hour)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM password_resets WHERE email = ? AND created_at > NOW() - INTERVAL 1 HOUR");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 5) {
                http_response_code(429);
                echo json_encode(['message' => 'Too many reset requests. Try again later.', 'status' => 'error', 'className' => 'error']);
                exit;
            }

            // Check if email exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if (!$stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['message' => 'Email not found', 'status' => 'error', 'className' => 'error']);
                exit;
            }

            // Generate token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store token
            $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$email, $token, $expires]);

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