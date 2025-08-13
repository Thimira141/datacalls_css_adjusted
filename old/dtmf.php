<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ./login.php');
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

    // Fetch username from database
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $display_username = $user ? htmlspecialchars($user['username']) : "Unknown User";
    $_SESSION['username'] = $display_username; // Store username in session for modal
} catch (PDOException $e) {
    $display_username = "Error fetching username";
    error_log("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DataCaller - DTMF</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Custom Styles -->
    <link href="assets/css/page-specific/dtmf.css" rel="stylesheet">
</head>

<body class="sidebar-mini dtmf">
    <div class="wrapper">
        <!-- Main Header -->
        <header class="main-header">
            <div class="logo-container">
                <a href="#" class="logo">
                    <span class="logo-mini"><img src="assets/img/logo.png" alt="DataCaller"></span>
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
                    <li class="active"><a href="dtmf.php"><i class="fas fa-chart-bar"></i> DTMF</a></li>
                    <li><a href="cdr.php"><i class="fas fa-file-alt"></i> CDR Reports</a></li>
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
                <h1>DTMF Inputs</h1>
            </section>
            <section class="content container-fluid">
                <!-- DTMF Inputs -->
                <div class="card">
                    <h4>DTMF Key Inputs</h4>
                    <table class="dtmf-table">
                        <thead>
                            <tr>
                                <th>Phone Number</th>
                                <th>DTMF Keys</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody id="dtmfTableBody">
                            <!-- DTMF data will be populated here -->
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

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
    function toggleSidebar() {
        $('.main-sidebar').toggleClass('active');
        $('.content-wrapper').toggleClass('active');
    }

    let dtmfData = [];
    let currentPage = 1;
    let itemsPerPage = 10;
    let totalPages = 1;
    let lastTimestamp = null;

    // Function to format DTMF keys with slashes
    function formatDTMFKeys(keys) {
        return keys.split('').join('/');
    }

    // Function to fetch DTMF data
    function fetchDTMFData() {
        $.ajax({
            url: './assets/backend/api.php',
            method: 'GET',
            data: {
                action: 'get_dtmf'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Filter new entries based on timestamp
                    const newEntries = response.dtmf_inputs.filter(entry =>
                        !lastTimestamp || new Date(entry.created_at) > new Date(lastTimestamp)
                    );
                    // Prepend new entries to maintain order
                    dtmfData = [...newEntries, ...dtmfData];
                    // Update last timestamp
                    if (response.dtmf_inputs.length > 0) {
                        lastTimestamp = response.dtmf_inputs[0].created_at;
                    }
                    totalPages = Math.ceil(dtmfData.length / itemsPerPage);
                    populateDTMFTable(dtmfData, currentPage, itemsPerPage);
                } else {
                    $('#dtmfTableBody').html(`<tr><td colspan="3">Error: ${response.message}</td></tr>`);
                    console.error('Fetch DTMF Response Error:', response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Fetch DTMF AJAX Error:', status, error, xhr.responseText);
                $('#dtmfTableBody').html(
                    '<tr><td colspan="3">Failed to fetch DTMF records. Check console for details.</td></tr>'
                );
            }
        });
    }

    // Function to populate DTMF table
    function populateDTMFTable(data, page, itemsPerPage) {
        const tableBody = $('#dtmfTableBody');
        tableBody.empty();
        if (data.length === 0) {
            tableBody.append('<tr><td colspan="3">No DTMF records found.</td></tr>');
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
                <td><span class="dtmf-keys">${entry.phone_number}</span></td>
                <td><span class="dtmf-keys">${formatDTMFKeys(entry.dtmf_keys)}</span></td>
                <td>${new Date(entry.created_at).toLocaleString()}</td>
            </tr>
        `);
        });
        $('#pageInfo').text(`Page ${page} of ${totalPages}`);
        $('#prevPage').prop('disabled', page === 1);
        $('#nextPage').prop('disabled', page === totalPages);
    }

    // Function to change page
    function changePage(direction) {
        currentPage += direction;
        if (currentPage < 1) currentPage = 1;
        if (currentPage > totalPages) currentPage = totalPages;
        populateDTMFTable(dtmfData, currentPage, itemsPerPage);
    }

    // Function to update items per page
    function updateItemsPerPage() {
        itemsPerPage = parseInt($('#itemsPerPage').val());
        totalPages = Math.ceil(dtmfData.length / itemsPerPage);
        currentPage = 1; // Reset to first page
        populateDTMFTable(dtmfData, currentPage, itemsPerPage);
    }

    // Initialize table and set up polling
    $(document).ready(function() {
        fetchDTMFData();
        setInterval(fetchDTMFData, 10000); // Poll every 10 seconds

        // Logout modal functionality
        $('#confirmLogout').on('click', function() {
            sessionStorage.removeItem('activeCalls');
            window.location.href = 'logout.php';
        });

        $('#logoutModal').on('shown.bs.modal', function() {
            setTimeout(function() {
                sessionStorage.removeItem('activeCalls');
                window.location.href = 'logout.php';
            }, 2000);
        });
    });
    </script>
</body>

</html>