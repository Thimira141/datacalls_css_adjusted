<?php
require_once __DIR__ . '/../config.php';

use inc\classes\CSRFToken;
use inc\classes\Auth;
use Illuminate\Database\Capsule\Manager as DB;

if (!Auth::check()) {
    header("Location: {$config->pUrl}/login.php");
    exit;
}

// Enable error logging for debugging
ini_set('display_errors', 0); // Set to 1 temporarily for debugging
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("Session user_id not set, redirecting to login.php");
    header('Location: ./login.php');
    exit;
}

try {
    // Fetch user data using DB facade
    $user = DB::table('users')
        ->select('username', 'magnus_user_id')
        ->where('id', $_SESSION['user_id'])
        ->first();

    if (!$user || empty($user->magnus_user_id)) {
        error_log("No magnus_user_id found for user_id: {$_SESSION['user_id']}");
        http_response_code(404);
        exit("MagnusBilling user ID not found for this account.");
    }

    // Sanitize and store in session
    $_SESSION['username'] = htmlspecialchars($user->username ?? "Unknown User", ENT_QUOTES, 'UTF-8');
    $magnus_user_id = $_SESSION['magnus_user_id'] = $user->magnus_user_id;

    // CSRF token generation
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

} catch (Throwable $e) {
    error_log("Error in cdr.php: " . $e->getMessage());
    exit("An unexpected error occurred. Please contact support.");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DataCaller - CDR Reports</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="<?= $config->pUrl; ?>/css/page-specific/cdr.css" rel="stylesheet">
    <?= CSRFToken::getInstance()->renderToken(true); ?>
</head>

<body class="sidebar-mini cdr">
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
                    <li><a href="balance.php"><i class="fas fa-wallet"></i> Add Balance</a></li>
                    <li class="header">CALL</li>
                    <li><a href="credentials.php"><i class="fas fa-key"></i> Credentials</a></li>
                    <li><a href="ivr.php"><i class="fas fa-phone-square-alt"></i> IVR</a></li>
                    <li><a href="dtmf.php"><i class="fas fa-chart-bar"></i> DTMF</a></li>
                    <li class="active"><a href="cdr.php"><i class="fas fa-file-alt"></i> CDR Reports</a></li>
                    <li class="header">ACCOUNT</li>
                    <li><a href="account.php"><i class="fas fa-user-cog"></i> Account Settings</a></li>
                    <li><a href="#" data-toggle="modal" data-target="#logoutModal"><i class="fas fa-sign-out-alt"></i>
                            Logout</a></li>
                </ul>
            </section>
        </aside>

        <!-- Content -->
        <div class="content-wrapper">
            <section class="content-header">
                <h1>CDR Reports</h1>
            </section>
            <section class="content container-fluid">
                <div class="card">
                    <h4>Call Detail Records</h4>
                    <div class="bulk-actions">
                        <input type="checkbox" id="selectAll" class="select-all-checkbox">
                        <label for="selectAll" class="mt-2 pt-1">Select All</label>
                        <button class="btn btn-danger btn-sm ml-2" id="bulkDelete" disabled>
                            <i class="fas fa-trash"></i> Delete Selected
                        </button>
                    </div>
                    <table class="cdr-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="masterCheckbox"></th>
                                <th>Number Called</th>
                                <th>Caller ID</th>
                                <th>Date Time</th>
                                <th>Duration</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="cdrTableBody">
                            <tr>
                                <td colspan="6">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="pagination-controls">
                        <button onclick="changePage(-1)" id="prevPage"><i class="fas fa-chevron-left"></i></button>
                        <span id="pageInfo">Page 1 of 1</span>
                        <button onclick="changePage(1)" id="nextPage"><i class="fas fa-chevron-right"></i></button>
                        <select id="itemsPerPage" onchange="updateItemsPerPage()">
                            <option value="10">Show 10</option>
                            <option value="20">Show 20</option>
                            <option value="30">Show 30</option>
                            <option value="40">Show 40</option>
                            <option value="50">Show 50</option>
                        </select>
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
                <!-- Delete Confirmation Modal -->
                <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content" style="background-color: #2c3e50; color: #fff; border-radius: 8px;">
                            <div class="modal-header" style="border-bottom: 1px solid #344450;">
                                <h5 class="modal-title">Confirm Deletion</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"
                                    style="color: #fff;">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to delete <span id="deleteCount">this record</span>?</p>
                            </div>
                            <div class="modal-footer" style="border-top: 1px solid #344450;">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function toggleSidebar() {
            $('.main-sidebar').toggleClass('active');
            $('.content-wrapper').toggleClass('active');
        }

        let cdrData = [];
        let currentPage = 1;
        let itemsPerPage = 10;
        let totalPages = 1;
        let selectedRecords = [];

        // Function to format duration (seconds to MM:SS)
        function formatDuration(seconds) {
            if (!seconds) return '0:00';
            const minutes = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${minutes}:${secs.toString().padStart(2, '0')}`;
        }

        // Function to escape HTML for XSS prevention
        function htmlspecialchars(str) {
            if (!str) return 'N/A';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        // Function to fetch CDR data
        function fetchCDRData() {
            $.ajax({
                url: '<?=$config->app->url;?>/controller/cdr_api.php',
                method: 'GET',
                data: {
                    action: 'get_calls',
                    magnus_user_id: '<?php echo $magnus_user_id; ?>'
                },
                dataType: 'json',
                beforeSend: function () {
                    $('#cdrTableBody').html('<tr><td colspan="6">Loading...</td></tr>');
                },
                success: function (response) {
                    if (response.success && response.data && Array.isArray(response.data)) {
                        cdrData = response.data.map(item => ({
                            id: item.id,
                            calledstation: item.calledstation,
                            callerid: item.callerid,
                            starttime: item.starttime,
                            sessiontime: item.sessiontime
                        }));
                        totalPages = Math.ceil(cdrData.length / itemsPerPage);
                        populateCDRTable(cdrData, currentPage, itemsPerPage);
                    } else {
                        $('#cdrTableBody').html(
                            `<tr><td colspan="6">${response.message || 'No call records found'}</td></tr>`
                        );
                        $('#pageInfo').text('Page 1 of 1');
                        $('#prevPage').prop('disabled', true);
                        $('#nextPage').prop('disabled', true);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Fetch CDR AJAX Error:', status, error, xhr.responseText);
                    $('#cdrTableBody').html(
                        '<tr><td colspan="6">Failed to fetch call records. Please try again later.</td></tr>'
                    );
                }
            });
        }

        // Function to delete CDR records
        function deleteCDR(id, isBulk = false) {
            const idsToDelete = isBulk ? selectedRecords : [id];
            $.ajax({
                url: '<?=$config->app->url;?>/controller/cdr_api.php',
                method: 'POST',
                data: {
                    action: 'delete_call',
                    magnus_user_id: '<?php echo $magnus_user_id; ?>',
                    ids: JSON.stringify(idsToDelete),
                    csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        fetchCDRData();
                        $('#deleteModal').modal('hide');
                        selectedRecords = [];
                        updateBulkDeleteButton();
                        alert('Record(s) deleted successfully');
                    } else {
                        alert('Error deleting record(s): ' + (response.message || 'Unknown error'));
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Delete CDR AJAX Error:', status, error, xhr.responseText);
                    alert('Failed to delete record(s). Please try again later.');
                }
            });
        }

        // Function to populate CDR table
        function populateCDRTable(data, page, itemsPerPage) {
            const tableBody = $('#cdrTableBody');
            tableBody.empty();
            if (data.length === 0) {
                tableBody.append('<tr><td colspan="6">No call records found.</td></tr>');
                $('#pageInfo').text('Page 1 of 1');
                $('#prevPage').prop('disabled', true);
                $('#nextPage').prop('disabled', true);
                return;
            }
            const start = (page - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            const paginatedData = data.slice(start, end);
            paginatedData.forEach(entry => {
                tableBody.append(`
                <tr>
                    <td><input type="checkbox" class="cdr-checkbox" value="${entry.id}"></td>
                    <td><span class="cdr-number">${htmlspecialchars(entry.calledstation)}</span></td>
                    <td><span class="cdr-number">${htmlspecialchars(entry.callerid)}</span></td>
                    <td>${entry.starttime ? new Date(entry.starttime).toLocaleString() : 'N/A'}</td>
                    <td>${formatDuration(entry.sessiontime)}</td>
                    <td class="action-buttons">
                        <button class="btn btn-danger btn-sm delete-btn" data-id="${entry.id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `);
            });
            $('#pageInfo').text(`Page ${page} of ${totalPages}`);
            $('#prevPage').prop('disabled', page === 1);
            $('#nextPage').prop('disabled', page === totalPages);

            // Attach event listeners
            $('.cdr-checkbox').on('change', updateBulkDeleteButton);
            $('.delete-btn').on('click', function () {
                const id = $(this).data('id');
                $('#deleteCount').text('this record');
                $('#deleteModal').modal('show');
                $('#confirmDelete').off('click').on('click', () => deleteCDR(id));
            });
        }

        // Function to update bulk delete button state
        function updateBulkDeleteButton() {
            selectedRecords = $('.cdr-checkbox:checked').map(function () {
                return $(this).val();
            }).get();
            $('#bulkDelete').prop('disabled', selectedRecords.length === 0);
        }

        // Function to change page
        function changePage(direction) {
            currentPage += direction;
            if (currentPage < 1) currentPage = 1;
            if (currentPage > totalPages) currentPage = totalPages;
            populateCDRTable(cdrData, currentPage, itemsPerPage);
            $('#masterCheckbox').prop('checked', false);
            updateBulkDeleteButton();
        }

        // Function to update items per page
        function updateItemsPerPage() {
            itemsPerPage = parseInt($('#itemsPerPage').val());
            totalPages = Math.ceil(cdrData.length / itemsPerPage);
            currentPage = 1;
            populateCDRTable(cdrData, currentPage, itemsPerPage);
            $('#masterCheckbox').prop('checked', false);
            updateBulkDeleteButton();
        }

        // Initialize
        $(document).ready(function () {
            fetchCDRData();
            setInterval(fetchCDRData, 30000); // Poll every 30 seconds

            // Logout modal
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

            // Select all checkbox
            $('#selectAll, #masterCheckbox').on('change', function () {
                const isChecked = $(this).prop('checked');
                $('.cdr-checkbox').prop('checked', isChecked);
                updateBulkDeleteButton();
            });

            // Bulk delete
            $('#bulkDelete').on('click', function () {
                if (selectedRecords.length > 0) {
                    $('#deleteCount').text(
                        `${selectedRecords.length} record${selectedRecords.length > 1 ? 's' : ''}`
                    );
                    $('#deleteModal').modal('show');
                    $('#confirmDelete').off('click').on('click', () => deleteCDR(null, true));
                }
            });
        });
    </script>
</body>

</html>