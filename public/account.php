<?php
require_once __DIR__ . '/../config.php';

use inc\classes\CSRFToken;
use inc\classes\Auth;
use Controller\MagnusBilling;
use Illuminate\Database\Capsule\Manager as DB;
use inc\classes\RKValidator as Validator;

if (!Auth::check()) {
    header("Location: {$config->pUrl}/login.php");
    exit;
}

try {
    $magnusBilling = new MagnusBilling(env('MAGNUS_API_KEY'), env('MAGNUS_API_SECRET'));
    $magnusBilling->public_url = env('MAGNUS_PUBLIC_URL');
    error_log("MagnusBilling initialized successfully in account.php");
} catch (Exception $e) {
    error_log("MagnusBilling initialization error: " . $e->getMessage());
    $magnusBilling = null;
}


try {
    // Handle password update form submission
    $error = '';
    $success = '';

    DB::connection();
    error_log("Database connected successfully in account.php using Illuminate DB");

    // Fetch username, MagnusBilling user details, and sip_id from database
    $user = DB::table('users')
        ->select('username', 'magnus_user_id', 'sip_id')
        ->where('id', $_SESSION['user_id'])
        ->first();

    if ($user) {
        // Eloquent returns object, not array
        $display_username = isset($user->username) ? htmlspecialchars($user->username) : "Unknown User";
        $_SESSION['username'] = $display_username; // Store username in session for modal
        $magnus_user_id = $user?->magnus_user_id;
        $sip_id = $user?->sip_id ?? 'Not available';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['currentPassword'])) {
            // Validate user input using Validator
            $validator = new Validator;
            $rules = [
                'currentPassword' => 'required|string',
                'newPassword' => 'required|string|min:8',
                'confirmPassword' => 'required|string|same:newPassword'
            ];
            $messages = [
                'required' => ':attribute is required.',
                'string' => ':attribute must be a string.',
                'min' => ':attribute must be at least :min characters long.',
                'same' => 'New password and confirmation do not match.'
            ];
            $validate = $validator->validate($_POST, $rules, $messages);
            $validate->setAliases([
                'currentPassword' => 'Current Password',
                'newPassword' => 'New Password',
                'confirmPassword' => 'Confirm Password'
            ]);
            if ($validate->fails()) {
                $error = implode(' ', $validate->errors()->all());
            } else {
                $currentPassword = $validate->getValue('currentPassword');
                $newPassword = $validate->getValue('newPassword');
                $confirmPassword = $validate->getValue('confirmPassword');

                // Fetch current hashed password using DB
                $userRow = DB::table('users')->select('password')->where('id', $_SESSION['user_id'])->first();
                if ($userRow && isset($userRow->password) && password_verify($currentPassword, $userRow->password)) {
                    try {
                        DB::beginTransaction();
                        error_log("Started transaction for password update for user ID: {$_SESSION['user_id']}");

                        // Hash the new password for myapp
                        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                        error_log("Hashed new password for myapp user ID: {$_SESSION['user_id']}");

                        // Update password in the local database using DB
                        DB::table('users')->where('id', $_SESSION['user_id'])
                            ->update(['password' => $newPasswordHash, 'magnus_password' => $newPassword]);
                        error_log("Updated local database password for user ID: {$_SESSION['user_id']}");

                        // ...existing MagnusBilling update logic...
                        // Update user and SIP password in MagnusBilling if magnus_user_id exists
                        if ($magnusBilling && $magnus_user_id) {
                            error_log("Attempting to update MagnusBilling user and SIP password for user ID: $magnus_user_id");
                            $maxRetries = 1;
                            $retryCount = 0;
                            $retryDelay = 2; // Seconds
                            $magnusUserUpdated = false;
                            $magnusSipUpdated = false;

                            // Update MagnusBilling user password
                            while ($retryCount <= $maxRetries && !$magnusUserUpdated) {
                                try {
                                    $userUpdateResult = $magnusBilling->update('user', $magnus_user_id, ['password' => $newPassword]);
                                    error_log("MagnusBilling user update raw response for user ID $magnus_user_id: " . print_r($userUpdateResult, true));

                                    // Always convert MagnusBilling response to array
                                    if (is_string($userUpdateResult)) {
                                        error_log("User update returned string: $userUpdateResult");
                                        $decodedResult = json_decode($userUpdateResult, true);
                                        $userUpdateResult = (json_last_error() === JSON_ERROR_NONE) ? $decodedResult : [];
                                    }

                                    if (is_object($userUpdateResult)) {
                                        $userUpdateResult = (array)$userUpdateResult;
                                    }

                                    // Check response structure
                                    if (is_array($userUpdateResult) && isset($userUpdateResult['success']) && $userUpdateResult['success']) {
                                        $magnusUserUpdated = true;
                                        error_log("MagnusBilling user password updated successfully for user ID: $magnus_user_id");
                                    } else {
                                        error_log("Failed to update MagnusBilling user password for user ID: $magnus_user_id on retry $retryCount. Response: " . json_encode($userUpdateResult, JSON_PRETTY_PRINT));
                                        if ($retryCount < $maxRetries) {
                                            sleep($retryDelay);
                                            $retryCount++;
                                        }
                                    }
                                } catch (Exception $e) {
                                    error_log("User update attempt $retryCount failed: " . $e->getMessage());
                                    if ($retryCount < $maxRetries) {
                                        sleep($retryDelay);
                                        $retryCount++;
                                    }
                                }
                            }

                            // Reset retry count for SIP update
                            $retryCount = 0;

                            // Update SIP password using sip_id if available
                            if ($sip_id) {
                                error_log("Using sip_id: $sip_id for MagnusBilling user ID: $magnus_user_id");
                                while ($retryCount <= $maxRetries && !$magnusSipUpdated) {
                                    try {
                                        $sipUpdateResult = $magnusBilling->update('sip', $sip_id, ['secret' => $newPassword]);
                                        error_log("MagnusBilling SIP update raw response for SIP ID $sip_id: " . print_r($sipUpdateResult, true));

                                        // Always convert MagnusBilling response to array
                                        if (is_string($sipUpdateResult)) {
                                            $decodedSipUpdateResult = json_decode($sipUpdateResult, true);
                                            $sipUpdateResult = (json_last_error() === JSON_ERROR_NONE) ? $decodedSipUpdateResult : [];
                                        }
                                        if (is_object($sipUpdateResult)) {
                                            $sipUpdateResult = (array)$sipUpdateResult;
                                        }

                                        // Check response structure
                                        if (is_array($sipUpdateResult) && isset($sipUpdateResult['success']) && $sipUpdateResult['success']) {
                                            $magnusSipUpdated = true;
                                            error_log("MagnusBilling SIP password updated successfully for SIP ID: $sip_id");
                                        } else {
                                            error_log("Failed to update MagnusBilling SIP password for SIP ID: $sip_id on retry $retryCount. Response: " . json_encode($sipUpdateResult, JSON_PRETTY_PRINT));
                                            if ($retryCount < $maxRetries) {
                                                sleep($retryDelay);
                                                $retryCount++;
                                            }
                                        }
                                    } catch (Exception $e) {
                                        error_log("SIP update attempt $retryCount failed: " . $e->getMessage());
                                        if ($retryCount < $maxRetries) {
                                            sleep($retryDelay);
                                            $retryCount++;
                                        }
                                    }
                                }
                            } else {
                                // Fallback: Try to fetch SIP user ID using read method
                                error_log("No sip_id found in users table; attempting to fetch SIP user for user ID: $magnus_user_id");
                                try {
                                    // Adjust filter format to avoid error (assuming MagnusBilling expects a different format)
                                    $magnusBilling->setFilter('id_user', $magnus_user_id, 'eq', 'integer');
                                    $sipResult = $magnusBilling->read('sip');
                                    $magnusBilling->clearFilter();
                                    error_log("MagnusBilling SIP read raw response for user ID $magnus_user_id: " . print_r($sipResult, true));

                                    // Always convert MagnusBilling response to array
                                    if (is_string($sipResult)) {
                                        $decodedSipResult = json_decode($sipResult, true);
                                        $sipResult = (json_last_error() === JSON_ERROR_NONE) ? $decodedSipResult : [];
                                    }
                                    if (is_object($sipResult)) {
                                        $sipResult = (array)$sipResult;
                                    }

                                    // Check if SIP account exists
                                    if (is_array($sipResult) && !empty($sipResult) && isset($sipResult['rows'][0]['id'])) {
                                        $sip_id = $sipResult['rows'][0]['id'];
                                        error_log("Found SIP user ID: $sip_id for MagnusBilling user ID: $magnus_user_id");

                                        // Update SIP password
                                        while ($retryCount <= $maxRetries && !$magnusSipUpdated) {
                                            try {
                                                $sipUpdateResult = $magnusBilling->update('sip', $sip_id, ['secret' => $newPassword]);
                                                error_log("MagnusBilling SIP update raw response for SIP ID $sip_id: " . print_r($sipUpdateResult, true));

                                                // Always convert MagnusBilling response to array
                                                if (is_string($sipUpdateResult)) {
                                                    $decodedSipUpdateResult = json_decode($sipUpdateResult, true);
                                                    $sipUpdateResult = (json_last_error() === JSON_ERROR_NONE) ? $decodedSipUpdateResult : [];
                                                }
                                                if (is_object($sipUpdateResult)) {
                                                    $sipUpdateResult = (array)$sipUpdateResult;
                                                }

                                                // Check response structure
                                                if (is_array($sipUpdateResult) && isset($sipUpdateResult['success']) && $sipUpdateResult['success']) {
                                                    $magnusSipUpdated = true;
                                                    error_log("MagnusBilling SIP password updated successfully for SIP ID: $sip_id");
                                                } else {
                                                    error_log("Failed to update MagnusBilling SIP password for SIP ID: $sip_id on retry $retryCount. Response: " . json_encode($sipUpdateResult, JSON_PRETTY_PRINT));
                                                    if ($retryCount < $maxRetries) {
                                                        sleep($retryDelay);
                                                        $retryCount++;
                                                    }
                                                }
                                            } catch (Exception $e) {
                                                error_log("SIP update attempt $retryCount failed: " . $e->getMessage());
                                                if ($retryCount < $maxRetries) {
                                                    sleep($retryDelay);
                                                    $retryCount++;
                                                }
                                            }
                                        }
                                    } else {
                                        error_log("No SIP account found for MagnusBilling user ID: $magnus_user_id");
                                        // Proceed without failing the transaction
                                    }
                                } catch (Exception $e) {
                                    error_log("Failed to fetch SIP user for user ID: $magnus_user_id. Error: " . $e->getMessage());
                                    // Proceed without failing the transaction
                                }
                            }

                            if (!$magnusUserUpdated) {
                                throw new Exception("Failed to update MagnusBilling user password after $maxRetries retries");
                            }
                            if (!$magnusSipUpdated && $sip_id) {
                                throw new Exception("Failed to update MagnusBilling SIP password after $maxRetries retries");
                            }
                        } elseif (!$magnus_user_id) {
                            error_log("No MagnusBilling user ID found for user ID: {$_SESSION['user_id']}; skipping MagnusBilling updates");
                        } elseif (!$magnusBilling) {
                            error_log("MagnusBilling not initialized; skipping user and SIP password updates for user ID: {$_SESSION['user_id']}");
                        }

                        DB::commit();
                        error_log("Committed transaction for password update for user ID: {$_SESSION['user_id']}");
                        $success = "Password updated successfully!";
                    } catch (Exception $e) {
                        DB::rollBack();
                        error_log("Rolled back transaction due to error: " . $e->getMessage());
                        $error = "Failed to update password. Please contact support.";
                    }
                } else {
                    $error = "Current password is incorrect.";
                }
            }
        }
    } else {
        $error = "User data not found";
    }

} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    error_log("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DataCaller - Account Settings</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Custom Styles -->
    <link href="<?= $config->pUrl; ?>/css/page-specific/account.css" rel="stylesheet">
    <?= CSRFToken::getInstance()->renderToken(true); ?>

</head>

<body class="sidebar-mini account">
    <div class="wrapper">
        <!-- Main Header -->
        <header class="main-header">
            <!-- Logo and Sidebar Toggle -->
            <div class="logo-container">
                <a href="#" class="logo">
                    <span class="logo-mini"><img src="<?= $config->pUrl; ?>/img/logo.png" alt="DataCaller"></span>
                </a>
                <!-- Sidebar toggle button -->
                <a href="#" class="sidebar-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></a>
            </div>
            <!-- Navbar Right Menu -->
            <nav class="navbar navbar-static-top">
                <div class="navbar-custom-menu">
                    <ul class="nav navbar-nav">
                        <!-- User Account Menu -->
                        <li class="user user-menu">
                            <div class="user-info">
                                <i class="fas fa-user-circle user-image"></i>
                                <span class="hidden-xs"><?php echo $display_username; ?></span>
                            </div>
                        </li>
                    </ul>
                </div>
            </nav>
        </header>
        <!-- Left side column -->
        <aside class="main-sidebar">
            <section class="sidebar">
                <ul class="sidebar-menu tree" data-widget="tree">
                    <br><br>
                    <li class="header">MAIN</li>
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="contacts.php"><i class="fas fa-address-book"></i> Contacts</a></li>
                    <li><a href="balance.php"><i class="fas fa-wallet"></i> Add Balance</a></li>
                    <li class="header">CALL</li>
                    <li><a href="credentials.php"><i class="fas fa-key"></i> Credentials</a></li>
                    <li><a href="ivr.php"><i class="fas fa-phone-square-alt"></i> IVR</a></li>
                    <li><a href="dtmf.php"><i class="fas fa-chart-bar"></i> DTMF</a></li>
                    <li><a href="cdr.php"><i class="fas fa-file-alt"></i> CDR Reports</a></li>
                    <li class="header">ACCOUNT</li>
                    <li class="active"><a href="#"><i class="fas fa-user-cog"></i> Account Settings</a></li>
                    <li><a href="#" data-toggle="modal" data-target="#logoutModal"><i class="fas fa-sign-out-alt"></i>
                            Logout</a></li>
                </ul>
            </section>
        </aside>
        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Content Header -->
            <section class="content-header">
                <h1>Account Settings</h1>
            </section>
            <!-- Main content -->
            <section class="content container-fluid">
                <!-- User Information -->
                <div class="card">
                    <h4>User Information</h4>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" class="form-control" value="<?php echo $display_username; ?>" readonly>
                    </div>
                </div>
                <!-- Change Password -->
                <div class="card">
                    <h4>Change Password</h4>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    <form id="passwordForm" method="POST" action="account.php">
                        <?= CSRFToken::getInstance()->renderToken(); ?>
                        <div class="form-group">
                            <label for="currentPassword">Current Password</label>
                            <input type="password" class="form-control" id="currentPassword" name="currentPassword"
                                placeholder="Enter current password" required>
                            <div id="currentPasswordError" class="error-message">Current password is required.</div>
                        </div>
                        <div class="form-group">
                            <label for="newPassword">New Password</label>
                            <input type="password" class="form-control" id="newPassword" name="newPassword"
                                placeholder="Enter new password" required>
                            <div id="newPasswordError" class="error-message">New password must be at least 8 characters
                                long.</div>
                        </div>
                        <div class="form-group">
                            <label for="confirmPassword">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirmPassword" name="confirmPassword"
                                placeholder="Confirm new password" required>
                            <div id="confirmPasswordError" class="error-message">Passwords do not match.</div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Update Password</button>
                    </form>
                </div>
                <!-- Logout Modal -->
                <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel"
                    aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content"
                            style="background-color: #2c3e50; color: #fff; border-radius: 8px; font-family: 'Roboto', sans-serif;">
                            <div class="modal-header" style="border-bottom: 1px solid #344450;">
                                <h5 class="modal-title" id="logoutModalLabel" style="font-weight: 500;">Logging Out</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"
                                    style="color: #fff;">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body" style="font-size: 16px;">
                                <p>You have been successfully logged out,
                                    <?php echo htmlspecialchars($_SESSION['username']); ?>!
                                </p>
                                <p>Redirecting you to the login page...</p>
                            </div>
                            <div class="modal-footer" style="border-top: 1px solid #344450;">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal"
                                    style="background-color: #344450; border: none;">Cancel</button>
                                <button type="button" class="btn btn-primary" id="confirmLogout"
                                    style="background-color: #1abc9c; border: none;">OK</button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

    </div>
    <!-- jQuery and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- Account Settings Script -->
    <script>
        $(document).ready(function () {
            // Password form submission (client-side validation)
            $('#passwordForm').on('submit', function (e) {
                // Reset error messages
                $('.error-message').hide();
                let isValid = true;

                const currentPassword = $('#currentPassword').val();
                const newPassword = $('#newPassword').val();
                const confirmPassword = $('#confirmPassword').val();

                // Validate current password
                if (!currentPassword) {
                    $('#currentPasswordError').show();
                    isValid = false;
                }

                // Validate new password (minimum 8 characters)
                if (newPassword.length < 8) {
                    $('#newPasswordError').show();
                    isValid = false;
                }

                // Validate confirm password
                if (newPassword !== confirmPassword) {
                    $('#confirmPasswordError').show();
                    isValid = false;
                }

                // If client-side validation passes, let the form submit to the server
                if (!isValid) {
                    e.preventDefault();
                }
            });

            // Logout modal functionality
            $('#confirmLogout').on('click', function () {
                sessionStorage.removeItem('activeCalls');
                window.location.href = 'logout.php';
            });

            $('#logoutModal').on('shown.bs.modal', function () {
                setTimeout(function () {
                    sessionStorage.removeItem('activeCalls');
                    window.location.href = 'logout.php';
                }, 2000);
            });
        });

        function toggleSidebar() {
            $('.main-sidebar').toggleClass('active');
            $('.content-wrapper').toggleClass('active');
        }
    </script>
</body>

</html>