<?php
require_once __DIR__ . '/../config.php';

use inc\classes\CSRFToken;
use inc\classes\Auth;
use controller\MagnusBilling;
use Illuminate\Database\Capsule\Manager as DB;

if (!Auth::check()) {
    header("Location: {$config->pUrl}/login.php");
    exit;
}

try {
    // Fetch username and magnus_user_id from users table using Eloquent
    $user = DB::table('users')
        ->select('username', 'magnus_user_id')
        ->where('id', $_SESSION['user_id'])
        ->first();

    if ($user && isset($user->username)) {
        $display_username = htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8');
        $_SESSION['username'] = $user->username; // Store username in session
        $magnus_user_id = isset($user->magnus_user_id) ? $user->magnus_user_id : null;
        error_log("Fetched username: {$user->username} and magnus_user_id: {$magnus_user_id} for user_id: {$_SESSION['user_id']}");
    } else {
        // Fallback if user not found
        $display_username = "Unknown User";
        $magnus_user_id = null;
        error_log("User not found for user_id: {$_SESSION['user_id']}");
    }
} catch (Exception $e) {
    // Handle database errors
    $display_username = "Error fetching username";
    $magnus_user_id = null;
    error_log("Database error in dashboard.php: " . $e->getMessage());
}

// Fetch balance using MagnusBilling API
// require_once __DIR__ "<?=$config->app->url;/controller/magnusBilling.php";

try {
    $magnusBilling = new MagnusBilling(env('MAGNUS_API_KEY'), env('MAGNUS_API_SECRET'));
    $magnusBilling->public_url = env('MAGNUS_PUBLIC_URL');
    error_log("MagnusBilling initialized successfully in dashboard.php");
} catch (Exception $e) {
    error_log("MagnusBilling initialization error in dashboard.php: " . $e->getMessage());
    $balance = "0.00";
    $magnusBilling = null;
}

if ($magnusBilling && $display_username !== "Unknown User" && $display_username !== "Error fetching username") {
    try {
        // Retry logic for fetching user data
        $maxRetries = 2;
        $retryCount = 0;
        $retryDelay = 2; // Seconds
        $balance = "0.00";

        while ($retryCount <= $maxRetries && $balance === "0.00") {
            error_log("Attempting to fetch user data for username: {$display_username} (retry $retryCount)");
            $result = $magnusBilling->read('user'); // Fetch all user records
            error_log("Raw user API response: " . print_r($result, true));

            // Always convert MagnusBilling response to array
            if (is_string($result)) {
                $decodedResult = json_decode($result, true);
                $result = (json_last_error() === JSON_ERROR_NONE) ? $decodedResult : [];
            }
            if (is_object($result)) {
                $result = (array)$result;
            }

            // Handle nested response (use 'rows' key)
            $userRecords = $result;
            if (isset($result['rows']) && is_array($result['rows'])) {
                $userRecords = $result['rows'];
                error_log("Using nested 'rows' array from user API response");
            }

            // Find matching user record
            if (!empty($userRecords) && is_array($userRecords)) {
                foreach ($userRecords as $user_data) {
                    // If user_data is object, convert to array
                    if (is_object($user_data)) {
                        $user_data = (array)$user_data;
                    }
                    $user_username = null;
                    $matched_field = null;
                    if (isset($user_data['username']) && $user_data['username'] === $display_username) {
                        $user_username = $user_data['username'];
                        $matched_field = 'username';
                    } elseif (isset($user_data['name']) && $user_data['name'] === $display_username) {
                        $user_username = $user_data['name'];
                        $matched_field = 'name';
                    } elseif (isset($user_data['accountcode']) && $user_data['accountcode'] === $display_username) {
                        $user_username = $user_data['accountcode'];
                        $matched_field = 'accountcode';
                    } elseif (isset($user_data['callerid']) && $user_data['callerid'] === $display_username) {
                        $user_username = $user_data['callerid'];
                        $matched_field = 'callerid';
                    } elseif (isset($user_data['idUserusername']) && $user_data['idUserusername'] === $display_username) {
                        $user_username = $user_data['idUserusername'];
                        $matched_field = 'idUserusername';
                    }

                    if ($user_username && isset($user_data['credit'])) {
                        $balance = number_format($user_data['credit'] ?? 0.00, 2);
                        error_log("Found balance: $balance for username: {$display_username} (matched on field: $matched_field)");
                        break 2; // Exit both loops
                    }
                }

                // Fallback: Match by magnus_user_id if username fails
                if ($magnus_user_id && $balance === "0.00") {
                    error_log("Username match failed, attempting to match magnus_user_id: {$magnus_user_id}");
                    foreach ($userRecords as $user_data) {
                        if (is_object($user_data)) {
                            $user_data = (array)$user_data;
                        }
                        if (isset($user_data['id']) && $user_data['id'] == $magnus_user_id) {
                            $balance = number_format($user_data['credit'] ?? 0.00, 2);
                            error_log("Found balance: $balance for magnus_user_id: {$magnus_user_id}");
                            break 2; // Exit both loops
                        }
                    }
                }
            }

            error_log("No user record found for username: {$display_username} or magnus_user_id: {$magnus_user_id} on retry $retryCount");
            if ($retryCount < $maxRetries) {
                sleep($retryDelay);
                $retryCount++;
            } else {
                break;
            }
        }

        if ($balance === "0.00") {
            error_log("Failed to fetch balance for username: {$display_username} or magnus_user_id: {$magnus_user_id} after $maxRetries retries");
        }
    } catch (Exception $e) {
        $balance = "0.00";
        error_log("MagnusBilling API error in dashboard.php: " . $e->getMessage());
    }
} else {
    $balance = "0.00";
    error_log("MagnusBilling not initialized or invalid username in dashboard.php");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DataCaller - Dashboard</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Custom Styles -->
    <link href="<?=$config->pUrl;?>/css/page-specific/dashboard.css" rel="stylesheet">
    <?=CSRFToken::getInstance()->renderToken(true);?>
</head>

<body class="sidebar-mini dashboard">
    <div class="wrapper">
        <!-- Main Header -->
        <header class="main-header">
            <div class="logo-container">
                <a href="#" class="logo">
                    <span class="logo-mini"><img src="<?=$config->pUrl;?>/img/logo.png" alt="DataCaller"></span>
                </a>
                <a href="#" class="sidebar-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></a>
            </div>
            <nav class="navbar navbar-static-top">
                <div class="navbar-custom-menu">
                    <ul class="nav navbar-nav">
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
                    <li class="active"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="contacts.php"><i class="fas fa-address-book"></i> Contacts</a></li>
                    <li><a href="balance.php"><i class="fas fa-wallet"></i> Add Balance</a></li>
                    <li class="header">CALL</li>
                    <li><a href="credentials.php"><i class="fas fa-key"></i> Credentials</a></li>
                    <li><a href="ivr.php"><i class="fas fa-phone-square-alt"></i> IVR</a></li>
                    <li><a href="dtmf.php"><i class="fas fa-chart-bar"></i> DTMF</a></li>
                    <li><a href="cdr.php"><i class="fas fa-file-alt"></i> CDR Reports</a></li>
                    <li class="header">ACCOUNT</li>
                    <li><a href="account.php"><i class="fas fa-user-cog"></i> Account Settings</a></li>
                    <li><a href="#" data-toggle="modal" data-target="#logoutModal"><i class="fas fa-sign-out-alt"></i>
                            Logout</a></li>
                </ul>
            </section>
        </aside>
        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Content Header -->
            <section class="content-header">
                <h1>Welcome, <?php echo $display_username; ?>!</h1>
            </section>
            <!-- Main content -->
            <section class="content container-fluid">
                <div class="row">
                    <div class="col-lg-4 col-xs-6">
                        <div class="media-box">
                            <div class="media-icon"><i class="fas fa-user"></i></div>
                            <div class="media-info">
                                <h5>Information</h5>
                                <h3><?php echo $display_username; ?></h3>
                                <h3>Username</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-xs-6">
                        <div class="media-box bg-sea">
                            <div class="media-icon"><i class="fas fa-calendar"></i></div>
                            <div class="media-info">
                                <h5 class="text-white">Today</h5>
                                <h3><?php echo date('H:i | l'); ?></h3>
                                <h3><?php echo date('d/m/Y'); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-xs-6">
                        <div class="media-box bg-blue">
                            <div class="media-icon"><i class="fas fa-balance-scale"></i></div>
                            <div class="media-info">
                                <h5>Balance</h5>
                                <h3>$<?php echo $balance; ?></h3>
                                <h3>Call Balance</h3>
                            </div>
                        </div>
                    </div>
                </div>
                <section class="content-header">
                    <h1>Quick View</h1>
                </section>
                <div class="row">
                    <div class="col-lg-4 col-xs-6">
                        <div class="media-box bg-sea">
                            <div class="media-icon"><i class="fas fa-phone"></i></div>
                            <div class="media-info">
                                <h5 class="text-white">Call Credentials</h5>
                                <h3 class="text-white mb-2">Credentials</h3>
                                <a href="./credentials.php" class="btn btn-primary">View</a>
                            </div>
                        </div>
                    </div>
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
                                    <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
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
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <!-- Sidebar Toggle and Logout Script -->
    <script>
    function toggleSidebar() {
        $('.main-sidebar').toggleClass('active');
        $('.content-wrapper').toggleClass('active');
    }

    $(document).ready(function() {
        // Logout modal functionality
        $('#confirmLogout').on('click', function() {
            window.location.href = 'logout.php';
        });

        $('#logoutModal').on('shown.bs.modal', function() {
            setTimeout(function() {
                window.location.href = 'logout.php';
            }, 2000);
        });
    });
    </script>
</body>

</html>