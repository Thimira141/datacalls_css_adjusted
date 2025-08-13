<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ./login.php');
    exit;
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Database connection
$host = 'localhost';
$dbname = 'datacalls_css_adjusted';
$dbuser = 'root';
$dbpass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch username from database for session
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $display_username = $user ? htmlspecialchars($user['username']) : 'Unknown User';
    $_SESSION['username'] = $user ? $user['username'] : null;
} catch (PDOException $e) {
    $display_username = 'Error fetching credentials';
    error_log("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DataCaller - Credentials</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="assets/css/page-specific/credentials.css" rel="stylesheet">

</head>

<body class="sidebar-mini credentials">
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
                    <li class="active"><a href="#"><i class="fas fa-key"></i> Credentials</a></li>
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

        <!-- Content -->
        <div class="content-wrapper">
            <section class="content-header">
                <h1>Credentials</h1>
            </section>
            <section class="content container-fluid">
                <!-- Caller ID -->
                <div class="card">
                    <h4>Caller ID</h4>
                    <form id="callerIdForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <div class="input-group">
                                <input type="text" class="form-control" id="callerId" name="callerid"
                                    placeholder="e.g. 12025550123" required pattern="\+?[1-9]\d{1,14}"
                                    title="Enter a valid phone number (e.g., +12025550123)">
                                <div class="input-group-append">
                                    <button class="btn dropdown-toggle" type="button" data-toggle="dropdown"
                                        aria-haspopup="true" aria-expanded="false">
                                        <i class="fas fa-address-book"></i>
                                    </button>
                                    <div class="dropdown-menu" id="callerIdDropdown">
                                        <!-- Contacts will be populated here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Update Caller ID</button>
                    </form>
                </div>

                <!-- SIP Information -->
                <div class="card">
                    <h4>SIP Information</h4>
                    <div class="form-group">
                        <label>SIP Domain</label>
                        <div class="copy-group">
                            <input type="text" class="form-control" id="sipDomain" value="Loading..." readonly>
                            <button class="btn btn-outline-secondary copy-btn">Copy</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>SIP Username</label>
                        <div class="copy-group">
                            <input type="text" class="form-control" id="sipUsername" value="Loading..." readonly>
                            <button class="btn btn-outline-secondary copy-btn">Copy</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>SIP Password</label>
                        <div class="copy-group input-group">
                            <input type="text" class="form-control" id="sipPassword" value="Loading..." readonly>
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary password-copy-btn" type="button">Copy</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Route and MoH -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <h4>Route</h4>
                            <p><strong>Existing Route:</strong> Moscow <em>(USA)</em></p>
                            <div class="form-group d-flex">
                                <select class="form-control mr-2">
                                    <option>Moscow</option>
                                </select>
                                <button class="btn btn-primary">Save</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <h4>MoH (Music on Hold)</h4>
                            <p><strong>Existing MoH:</strong> Default Music on Hold</p>
                            <div class="form-group d-flex">
                                <input type="file" class="form-control mr-2" id="mohFile" accept=".wav"
                                    style="display: none;">
                                <input type="text" class="form-control mr-2" id="mohFileName" readonly
                                    placeholder="No file selected">
                                <button type="button" class="btn btn-outline-secondary" id="browseBtn">Browse</button>
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

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <script>
    function toggleSidebar() {
        $('.main-sidebar').toggleClass('active');
        $('.content-wrapper').toggleClass('active');
    }

    $(document).ready(function() {
        // Load SIP information
        function loadSipInfo() {
            $.ajax({
                url: './assets/backend/api.php',
                method: 'GET',
                data: {
                    action: 'get_sip_info'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#sipDomain').val(response.sip_domain || 'Not Available');
                        $('#sipUsername').val(response.sip_username || 'Not Available');
                        $('#sipPassword').val(response.sip_password || 'Not Available');
                    } else {
                        $('#sipDomain').val('Error fetching SIP info');
                        $('#sipUsername').val('Error fetching SIP info');
                        $('#sipPassword').val('Error fetching SIP info');
                        console.error('SIP Info Error:', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    $('#sipDomain').val('Error fetching SIP info');
                    $('#sipUsername').val('Error fetching SIP info');
                    $('#sipPassword').val('Error fetching SIP info');
                    console.error('SIP Info AJAX Error:', status, error);
                }
            });
        }

        // Load contacts into dropdown
        function loadContacts() {
            $.ajax({
                url: './assets/backend/api.php',
                method: 'GET',
                data: {
                    action: 'get_contacts'
                },
                dataType: 'json',
                success: function(response) {
                    const callerIdDropdown = $('#callerIdDropdown');
                    callerIdDropdown.empty();
                    if (response.success && response.contacts.length > 0) {
                        response.contacts.forEach(contact => {
                            callerIdDropdown.append(
                                `<a class="dropdown-item" href="#" data-number="${contact.phone_number}">${contact.name} - ${contact.phone_number}</a>`
                            );
                        });
                    } else {
                        callerIdDropdown.append(
                            '<a class="dropdown-item" href="#">No contacts saved</a>'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Load Contacts Error:', status, error);
                    $('#callerIdDropdown').append(
                        '<a class="dropdown-item" href="#">Error loading contacts</a>'
                    );
                }
            });
        }

        // Handle dropdown item selection
        $('#callerIdDropdown').on('click', '.dropdown-item', function(e) {
            e.preventDefault();
            const number = $(this).data('number');
            if (number) {
                $('#callerId').val(number);
            }
        });

        // Form submission with styled success/error message and validation
        $('#callerIdForm').on('submit', function(e) {
            e.preventDefault();
            const callerId = $('#callerId').val();
            const phonePattern = /^\+?[1-9]\d{1,14}$/;

            // Client-side validation
            if (!callerId) {
                const alertHtml = `
                    <div id="callerIdMessage" class="alert alert-danger alert-dismissible fade show" role="alert">
                        Caller ID is required
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                `;
                $('#callerIdForm .form-group').prepend(alertHtml);
                setTimeout(() => {
                    $('#callerIdMessage').alert('close');
                }, 3000);
                return;
            }

            if (!phonePattern.test(callerId)) {
                const alertHtml = `
                    <div id="callerIdMessage" class="alert alert-danger alert-dismissible fade show" role="alert">
                        Please enter a valid phone number (e.g., +12025550123)
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                `;
                $('#callerIdForm .form-group').prepend(alertHtml);
                setTimeout(() => {
                    $('#callerIdMessage').alert('close');
                }, 3000);
                return;
            }

            // Remove any existing alerts
            $('#callerIdMessage').remove();

            // Make AJAX call to update Caller ID
            $.ajax({
                url: './assets/backend/api.php',
                type: 'POST',
                data: {
                    action: 'update_caller_id',
                    callerid: callerId,
                    csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    const alertHtml = `
                        <div id="callerIdMessage" class="alert alert-${response.success ? 'success' : 'danger'} alert-dismissible fade show" role="alert">
                            ${response.message}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    `;
                    $('#callerIdForm .form-group').prepend(alertHtml);

                    // Auto-dismiss after 3 seconds
                    setTimeout(() => {
                        $('#callerIdMessage').alert('close');
                    }, 3000);
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message ||
                        'Failed to update Caller ID. Please try again.';
                    const alertHtml = `
                        <div id="callerIdMessage" class="alert alert-danger alert-dismissible fade show" role="alert">
                            ${message}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    `;
                    $('#callerIdForm .form-group').prepend(alertHtml);
                    setTimeout(() => {
                        $('#callerIdMessage').alert('close');
                    }, 3000);
                    console.error('Caller ID AJAX Error:', xhr.responseText);
                }
            });
        });

        // Copy button functionality for SIP Domain and SIP Username
        $('.copy-btn, .password-copy-btn').on('click', function() {
            var input = $(this).closest('.copy-group').find('input');
            input[0].select();
            try {
                document.execCommand("copy");
                $(this).text("Copied").prop('disabled', true);
                setTimeout(() => {
                    $(this).text("Copy").prop('disabled', false);
                }, 2000);
            } catch (err) {
                console.error('Copy failed:', err);
            }
        });

        // Trigger file input click when Browse button is clicked
        $('#browseBtn').on('click', function() {
            $('#mohFile').click();
        });

        // Update text input with selected file name
        $('#mohFile').on('change', function() {
            var fileName = this.files.length > 0 ? this.files[0].name : "No file selected";
            $('#mohFileName').val(fileName);
        });

        // Logout modal functionality
        $('#confirmLogout').on('click', function() {
            window.location.href = 'logout.php';
        });

        $('#logoutModal').on('shown.bs.modal', function() {
            setTimeout(function() {
                window.location.href = 'logout.php';
            }, 2000);
        });

        // Load contacts and SIP info on page load
        loadContacts();
        loadSipInfo();
    });
    </script>
</body>

</html>