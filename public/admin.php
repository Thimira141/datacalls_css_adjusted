<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../controller/connection.php';

use inc\classes\CSRFToken;
use inc\classes\Auth;
use Illuminate\Database\Capsule\Manager as DB;

if (!Auth::check()) {
    header("Location: {$config->pUrl}/login.php");
    exit;
}

try {
    DB::connection();
    error_log("Database connected successfully in account.php using Illuminate DB");

    // Fetch user details and check if admin
    $user = DB::table('users')
        ->select('username', 'is_admin')
        ->where('id', $_SESSION['user_id'])
        ->first();

    if (!$user) {
        header("Location: {$config->pUrl}/login.php");
        exit;
    }

    $display_username = htmlspecialchars($user->username);

    if (!$user->is_admin) {
        header("Location: {$config->pUrl}/dashboard.php");
        exit;
    }

    // Fetch all users from database
    $users = DB::table('users')->select('id', 'username')->get();

    // Fetch SIP details from MagnusBilling
    $sipData = [];

    foreach ($users as $user) {
        $userId = $user->id;

        $magnusBilling->setFilter('id_user', $userId, 'eq', 'numeric');
        $sipResult = $magnusBilling->read('sip', 1);
        $magnusBilling->clearFilter();

        $sipData[$userId] = [
            'username' => $user->username,
            'sipUsername' => $sipResult['rows'][0]['username'] ?? 'Not available',
            'sipPassword' => $sipResult['rows'][0]['password'] ?? 'Not available',
            'callerId' => $sipResult['rows'][0]['callerid'] ?? 'Not set',
        ];
    }

} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $display_username = "Error fetching username";
    $sipData = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DataCaller - Admin Panel</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="<?= $config->pUrl; ?>/css/page-specific/admin.css" rel="stylesheet">
    <?= CSRFToken::getInstance()->renderToken(true); ?>
</head>

<body class="sidebar-mini admin">
    <div class="wrapper">
        <!-- Main Header -->
        <header class="main-header">
            <div class="logo-container">
                <a href="#" class="logo">
                    <span class="logo-mini"><img src="<?= $config->pUrl; ?>/img/logo.png" alt="DataCaller"></span>
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
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li class="active"><a href="admin.php"><i class="fas fa-user-shield"></i> Admin</a></li>
                    <li><a href="contacts.php"><i class="fas fa-address-book"></i> Contacts</a></li>
                    <li><a href="balance.php"><i class="fas fa-wallet"></i> Add Balance</a></li>
                    <li class="header">CALL</li>
                    <li><a href="credentials.php"><i class="fas fa-key"></i> Credentials</a></li>
                    <li><a href="ivr.php"><i class="fas fa-phone-square-alt"></i> IVR</a></li>
                    <li><a href="dtmf.php"><i class="fas fa-chart-bar"></i> DTMF</a></li>
                    <li><a href="cdr.php"><i class="fas fa-file-alt"></i> CDR Reports</a></li>
                    <li class="header">ACCOUNT</li>
                    <li><a href="account.php"><i class="fas fa-user-cog"></i> Account Settings</a></li>
                    <li><a href="logout.php" onclick="logout()"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </section>
        </aside>
        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <section class="content-header">
                <h1>Admin Panel - User SIP Details</h1>
            </section>
            <section class="content container-fluid">
                <div class="card">
                    <h4>All Users SIP Information</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>SIP Username</th>
                                    <th>SIP Password</th>
                                    <th>Caller ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sipData)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No users found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($sipData as $data): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($data['username']); ?></td>
                                            <td><?php echo htmlspecialchars($data['sipUsername']); ?></td>
                                            <td><?php echo htmlspecialchars($data['sipPassword']); ?></td>
                                            <td><?php echo htmlspecialchars($data['callerId']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function logout() {
            alert('Logged out successfully!');
        }
    </script>
</body>

</html>