<?php
require_once __DIR__ . '/../config.php';

use inc\classes\CSRFToken;
use inc\classes\Auth;
use Illuminate\Database\Capsule\Manager as DB;

if (!Auth::check()) {
    header("Location: {$config->pUrl}/login.php");
    exit;
}

// Database connection for username

try {
    // Fetch username from database
    $user = DB::table('users')
        ->select('username')
        ->where('id', $_SESSION['user_id'])
        ->first();

    $display_username = $user ? htmlspecialchars($user->username) : 'Unknown User';
    $_SESSION['username'] = $user?->username;
} catch (Exception $e) {
    $display_username = "Error fetching username";
    error_log("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DataCaller - Add Balance</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="<?= $config->pUrl; ?>/css/page-specific/balance.css" rel="stylesheet">
    <?= CSRFToken::getInstance()->renderToken(true); ?>
</head>

<body class="sidebar-mini balance">
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

        <!-- Sidebar -->
        <aside class="main-sidebar">
            <section class="sidebar">
                <ul class="sidebar-menu tree" data-widget="tree">
                    <br><br>
                    <li class="header">MAIN</li>
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="contacts.php"><i class="fas fa-address-book"></i> Contacts</a></li>
                    <li class="active"><a href="balance.php"><i class="fas fa-wallet"></i> Add Balance</a></li>
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
            <section class="content-header">
                <h1>Add Balance</h1>
            </section>
            <section class="content container-fluid">
                <!-- Add Balance -->
                <div class="row mt-4 justify-content-center">
                    <div class="col-md-6 col-lg-4">
                        <div class="card">
                            <h4>Add Balance</h4>
                            <form id="addBalanceForm">
                                <div class="form-group">
                                    <label for="amount">Amount (USD)</label>
                                    <input type="number" class="form-control" id="amount" placeholder="e.g., 30"
                                        step="0.01" required>
                                </div>
                                <div class="form-group">
                                    <label for="balanceType">Balance Type</label>
                                    <select class="form-control" id="balanceType">
                                        <option value="call">Call Balance</option>
                                    </select>
                                </div>
                                <div class="payment-option">
                                    <button type="submit" class="btn btn-primary btn-block" id="payWithBitcoin">
                                        <i class="fab fa-bitcoin"></i> Pay with Bitcoin
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Invoices Table -->
                <div class="row mt-5">
                    <div class="col-12">
                        <div class="card">
                            <h4>Invoices</h4>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped" id="invoicesTable">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Payment ID</th>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Created Time</th>
                                        </tr>
                                    </thead>
                                    <tbody id="invoicesBody">
                                        <!-- Invoices will be populated here -->
                                    </tbody>
                                </table>
                            </div>
                            <div class="pagination-controls">
                                <button type="button" class="btn btn-outline-secondary mr-2" id="prevPage" disabled>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor"
                                        viewBox="0 0 256 256">
                                        <path
                                            d="M165.66,202.34a8,8,0,0,1-11.32,11.32l-80-80a8,8,0,0,1,0-11.32l80-80a8,8,0,0,1,11.32,11.32L91.31,128Z">
                                        </path>
                                    </svg>
                                </button>
                                <span id="pageInfo">Page 1 of 1</span>
                                <button type="button" class="btn btn-outline-secondary ml-2" id="nextPage" disabled>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor"
                                        viewBox="0 0 256 256">
                                        <path
                                            d="M181.66,133.66l-80,80a8,8,0,0,1-11.32-11.32L164.69,128,90.34,53.66a8,8,0,0,1,11.32-11.32l80,80A8,8,0,0,1,181.66,133.66Z">
                                        </path>
                                    </svg>
                                </button>
                                <select class="form-control ml-4" id="pageSize" style="width: 100px;">
                                    <option value="10">Show 10</option>
                                    <option value="20">Show 20</option>
                                    <option value="30">Show 30</option>
                                    <option value="40">Show 40</option>
                                    <option value="50">Show 50</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Modal -->
                <div class="modal fade" id="paymentModal" tabindex="-1" role="dialog"
                    aria-labelledby="paymentModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content"
                            style="background-color: #2c3e50; color: #fff; border-radius: 8px; font-family: 'Roboto', sans-serif;">
                            <div class="modal-header" style="border-bottom: 1px solid #344450;">
                                <h5 class="modal-title" id="paymentModalLabel" style="font-weight: 500;">Pay with
                                    Bitcoin</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"
                                    style="color: #fff;">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body" style="font-size: 16px; text-align: center;">
                                <div id="paymentModalBody">
                                    <!-- QR code or payment link will be inserted here -->
                                </div>
                            </div>
                            <div class="modal-footer" style="border-top: 1px solid #344450;">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal"
                                    style="background-color: #344450; border: none;">Cancel</button>
                                <a href="#" id="paymentLink" class="btn btn-primary"
                                    style="background-color: #1abc9c; border: none;">Open Payment Page</a>
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

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            $('.main-sidebar').toggleClass('active');
            $('.content-wrapper').toggleClass('active');
        }

        function clearSessionStorage() {
            sessionStorage.removeItem('pendingAmount');
            sessionStorage.removeItem('pendingBalanceType');
        }

        $(document).ready(function () {
            let currentPage = 1;
            let pageSize = 10;

            // Load invoices dynamically
            function loadInvoices(page, size) {
                $.ajax({
                    url: '<?=$config->app->url;?>/controller/api.php',
                    method: 'GET',
                    data: {
                        action: 'get_invoices',
                        page: page,
                        page_size: size
                    },
                    dataType: 'json',
                    success: function (response) {
                        $('#invoicesBody').empty();
                        if (response.success && response.invoices.length > 0) {
                            response.invoices.forEach(invoice => {
                                $('#invoicesBody').append(`
                                <tr>
                                    <td>${invoice.payment_id}</td>
                                    <td><span class="badge badge-info">${invoice.type.toUpperCase()}</span></td>
                                    <td>$${parseFloat(invoice.amount).toFixed(2)}</td>
                                    <td><span class="badge badge-${invoice.status === 'CONFIRMED' ? 'success' : 'warning'}">${invoice.status}</span></td>
                                    <td><span class="badge badge-light text-success">${invoice.created_time}</span></td>
                                </tr>
                            `);
                            });
                            $('#pageInfo').text(`Page ${response.page} of ${response.total_pages}`);
                            $('#prevPage').prop('disabled', response.page === 1);
                            $('#nextPage').prop('disabled', response.page === response.total_pages);
                            currentPage = response.page;
                        } else {
                            $('#invoicesBody').append(
                                '<tr><td colspan="5">No invoices found</td></tr>');
                            $('#pageInfo').text('Page 1 of 1');
                            $('#prevPage').prop('disabled', true);
                            $('#nextPage').prop('disabled', true);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Load Invoices Error:', status, error);
                        $('#invoicesBody').append(
                            '<tr><td colspan="5">Error loading invoices</td></tr>');
                    }
                });
            }

            // Handle form submission (triggers Bitcoin payment)
            $('#addBalanceForm').on('submit', function (e) {
                e.preventDefault();
                const amount = $('#amount').val();
                const balanceType = $('#balanceType').val();

                if (!amount || amount <= 0) {
                    $('#addBalanceForm').prepend(`
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        Please enter a valid amount.
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                `);
                    setTimeout(() => $('.alert').alert('close'), 3000);
                    return;
                }

                // Store amount and type for Bitcoin payment
                sessionStorage.setItem('pendingAmount', amount);
                sessionStorage.setItem('pendingBalanceType', balanceType);

                // Trigger Bitcoin payment logic
                $.ajax({
                    url: '<?=$config->app->url;?>/controller/api.php',
                    method: 'POST',
                    data: {
                        action: 'create_bitpay_invoice',
                        amount: amount,
                        balance_type: balanceType,
                        user_id: '<?php echo $_SESSION['user_id']; ?>'
                    },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            $('#paymentModalBody').html(`
                            <p>Scan the QR code or click the button to pay ${amount} USD with Bitcoin.</p>
                            <img src="${response.qr_code_url || 'https://via.placeholder.com/150?text=QR+Code'}" alt="QR Code" style="max-width: 200px; margin: 10px auto; display: block;">
                        `);
                            $('#paymentLink').attr('href', response.payment_url);
                            $('#paymentModal').modal('show');
                        } else {
                            $('#addBalanceForm').prepend(`
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                ${response.message}
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        `);
                            setTimeout(() => $('.alert').alert('close'), 3000);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Create BitPay Invoice Error:', status, error);
                        $('#addBalanceForm').prepend(`
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            Failed to create Bitcoin payment invoice.
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    `);
                        setTimeout(() => $('.alert').alert('close'), 3000);
                    }
                });
            });

            // Pagination controls
            $('#prevPage').on('click', function () {
                if (currentPage > 1) {
                    loadInvoices(currentPage - 1, pageSize);
                }
            });

            $('#nextPage').on('click', function () {
                loadInvoices(currentPage + 1, pageSize);
            });

            $('#pageSize').on('change', function () {
                pageSize = parseInt($(this).val());
                loadInvoices(1, pageSize);
            });

            // Logout modal functionality
            $('#confirmLogout').on('click', function () {
                clearSessionStorage();
                window.location.href = 'logout.php';
            });

            $('#logoutModal').on('shown.bs.modal', function () {
                setTimeout(function () {
                    clearSessionStorage();
                    window.location.href = 'logout.php';
                }, 2000);
            });

            // Load invoices on page load
            loadInvoices(currentPage, pageSize);
        });
    </script>
</body>

</html>