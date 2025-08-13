<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ./login.php');
    exit;
}

// Generate CSRF token for form submissions
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

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
    $_SESSION['username'] = $user ? $user['username'] : null;
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
    <title>DataCaller - Contacts</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Custom Styles -->
    <link href="assets/css/page-specific/contacts.css" rel="stylesheet">

</head>

<body class="sidebar-mini contacts">
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
                    <li class="active"><a href="contacts.php"><i class="fas fa-address-book"></i> Contacts</a></li>
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

        <!-- Content -->
        <div class="content-wrapper">
            <section class="content-header">
                <h1>Contacts Management</h1>
            </section>
            <section class="content container-fluid">
                <!-- Toast Container -->
                <div class="position-fixed top-0 end-0 p-3" style="z-index: 1200;">
                    <div id="toastNotification" class="toast" role="alert" aria-live="assertive" aria-atomic="true"
                        data-delay="3000">
                        <div class="toast-header">
                            <strong class="mr-auto" id="toastTitle"></strong>
                            <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="toast-body" id="toastMessage"></div>
                    </div>
                </div>

                <!-- Delete Confirmation Modal -->
                <div class="modal fade delete-modal" id="deleteConfirmModal" tabindex="-1" role="dialog"
                    aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="deleteConfirmModalLabel">Confirm Deletion</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <p id="deleteConfirmMessage"></p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <!-- Tabs -->
                    <ul class="nav nav-tabs" id="contactTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="contacts-tab" data-toggle="tab" href="#contacts" role="tab"
                                aria-controls="contacts" aria-selected="true"><i
                                    class="fas fa-address-book mr-2"></i>Contacts</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="institutions-tab" data-toggle="tab" href="#institutions" role="tab"
                                aria-controls="institutions" aria-selected="false"><i
                                    class="fas fa-university mr-2"></i>Institutions</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="merchants-tab" data-toggle="tab" href="#merchants" role="tab"
                                aria-controls="merchants" aria-selected="false"><i
                                    class="fas fa-store mr-2"></i>Merchants</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="ivr-profiles-tab" data-toggle="tab" href="#ivr-profiles" role="tab"
                                aria-controls="ivr-profiles" aria-selected="false"><i
                                    class="fas fa-headset mr-2"></i>IVR Profiles</a>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content" id="contactTabsContent">
                        <!-- Contacts Tab -->
                        <div class="tab-pane fade show active" id="contacts" role="tabpanel"
                            aria-labelledby="contacts-tab">
                            <div class="row">
                                <div class="col-md-4">
                                    <h5>Add New Contact</h5>
                                    <form id="contactForm">
                                        <input type="hidden" name="csrf_token"
                                            value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <div class="form-group">
                                            <label for="contactName">Name</label>
                                            <input type="text" class="form-control" id="contactName"
                                                placeholder="Enter name" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="contactNumber">Phone Number</label>
                                            <input type="tel" class="form-control" id="contactNumber"
                                                placeholder="e.g. 12025550123" required pattern="1\d{9,13}"
                                                title="Enter a valid phone number starting with 1 (e.g., 12025550123)">
                                        </div>
                                        <button type="submit" class="btn btn-primary">Add Contact</button>
                                    </form>
                                </div>
                                <div class="col-md-8">
                                    <div class="search-container">
                                        <input type="text" class="form-control" id="contactSearch"
                                            placeholder="Search contacts...">
                                    </div>
                                    <div class="table-container">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Phone Number</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="contactList"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Institutions Tab -->
                        <div class="tab-pane fade" id="institutions" role="tabpanel" aria-labelledby="institutions-tab">
                            <div class="row">
                                <div class="col-md-4">
                                    <h5>Add New Institution</h5>
                                    <form id="institutionForm">
                                        <input type="hidden" name="csrf_token"
                                            value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <div class="form-group">
                                            <label for="institutionName">Institution Name</label>
                                            <input type="text" class="form-control" id="institutionName"
                                                placeholder="e.g. USAA" required>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Add Institution</button>
                                    </form>
                                </div>
                                <div class="col-md-8">
                                    <div class="search-container">
                                        <input type="text" class="form-control" id="institutionSearch"
                                            placeholder="Search institutions...">
                                    </div>
                                    <div class="table-container">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="institutionList"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Merchants Tab -->
                        <div class="tab-pane fade" id="merchants" role="tabpanel" aria-labelledby="merchants-tab">
                            <div class="row">
                                <div class="col-md-4">
                                    <h5>Add New Merchant</h5>
                                    <form id="merchantForm">
                                        <input type="hidden" name="csrf_token"
                                            value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <div class="form-group">
                                            <label for="merchantName">Merchant Name</label>
                                            <input type="text" class="form-control" id="merchantName"
                                                placeholder="e.g. Target" required>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Add Merchant</button>
                                    </form>
                                </div>
                                <div class="col-md-8">
                                    <div class="search-container">
                                        <input type="text" class="form-control" id="merchantSearch"
                                            placeholder="Search merchants...">
                                    </div>
                                    <div class="table-container">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="merchantList"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- IVR Profiles Tab -->
                        <div class="tab-pane fade" id="ivr-profiles" role="tabpanel" aria-labelledby="ivr-profiles-tab">
                            <div class="row">
                                <div class="col-md-4">
                                    <h5>Add New IVR Profile</h5>
                                    <form id="ivrProfileForm">
                                        <input type="hidden" name="csrf_token"
                                            value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <div class="form-group">
                                            <label for="ivrProfileName">Profile Name</label>
                                            <input type="text" class="form-control" id="ivrProfileName"
                                                placeholder="e.g. USAA Payment Profile" required
                                                style="border-radius: 6px;">
                                        </div>
                                        <div class="form-group">
                                            <label for="ivrInstitutionName">Institution Name</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="ivrInstitutionName"
                                                    placeholder="e.g. USAA" required>
                                                <div class="input-group-append">
                                                    <button class="btn dropdown-toggle" type="button"
                                                        data-toggle="dropdown" aria-haspopup="true"
                                                        aria-expanded="false">
                                                        <i class="fas fa-university"></i>
                                                    </button>
                                                    <div class="dropdown-menu" id="institutionDropdown">
                                                        <!-- Institutions will be populated here -->
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="ivrCallerId">Caller ID</label>
                                            <div class="input-group">
                                                <input type="tel" class="form-control" id="ivrCallerId"
                                                    placeholder="e.g. 12025550123" required pattern="1\d{9,13}"
                                                    title="Enter a valid phone number starting with 1 (e.g., 12025550123)">
                                                <div class="input-group-append">
                                                    <button class="btn dropdown-toggle" type="button"
                                                        data-toggle="dropdown" aria-haspopup="true"
                                                        aria-expanded="false">
                                                        <i class="fas fa-address-book"></i>
                                                    </button>
                                                    <div class="dropdown-menu" id="callerIdDropdown">
                                                        <!-- Contacts will be populated here -->
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="ivrCallbackNumber">Callback Number</label>
                                            <div class="input-group">
                                                <input type="tel" class="form-control" id="ivrCallbackNumber"
                                                    placeholder="e.g. 12025550123" required pattern="1\d{9,13}"
                                                    title="Enter a valid phone number starting with 1 (e.g., 12025550123)">
                                                <div class="input-group-append">
                                                    <button class="btn dropdown-toggle" type="button"
                                                        data-toggle="dropdown" aria-haspopup="true"
                                                        aria-expanded="false">
                                                        <i class="fas fa-address-book"></i>
                                                    </button>
                                                    <div class="dropdown-menu" id="callbackNumberDropdown">
                                                        <!-- Contacts will be populated here -->
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="ivrMerchantName">Merchant Name</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="ivrMerchantName"
                                                    placeholder="e.g. Target" required>
                                                <div class="input-group-append">
                                                    <button class="btn dropdown-toggle" type="button"
                                                        data-toggle="dropdown" aria-haspopup="true"
                                                        aria-expanded="false">
                                                        <i class="fas fa-store"></i>
                                                    </button>
                                                    <div class="dropdown-menu" id="merchantDropdown">
                                                        <!-- Merchants will be populated here -->
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="ivrAmount">Amount</label>
                                            <input type="number" step="0.01" class="form-control" id="ivrAmount"
                                                placeholder="e.g. 100.00" required style="border-radius: 6px;">
                                        </div>
                                        <button type="submit" class="btn btn-primary">Add IVR Profile</button>
                                    </form>
                                </div>
                                <div class="col-md-8">
                                    <div class="search-container">
                                        <input type="text" class="form-control" id="ivrProfileSearch"
                                            placeholder="Search IVR profiles...">
                                    </div>
                                    <div class="table-container">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Institution</th>
                                                    <th>Caller ID</th>
                                                    <th>Callback Number</th>
                                                    <th>Merchant</th>
                                                    <th>Amount</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="ivrProfileList"></tbody>
                                        </table>
                                    </div>
                                </div>
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
    // Sidebar toggle
    function toggleSidebar() {
        $('.main-sidebar').toggleClass('active');
        $('.content-wrapper').toggleClass('active');
    }

    // Show Toast Notification
    function showToast(title, message, type = 'success') {
        const toast = $('#toastNotification');
        const toastTitle = $('#toastTitle');
        const toastMessage = $('#toastMessage');

        toastTitle.text(title);
        toastMessage.text(message);

        $('.toast-header').removeClass('success error');
        $('.toast-header').addClass(type);

        toast.toast('show');
    }

    // Render contact list
    function renderContactList(contacts, filter = '') {
        const contactList = $('#contactList');
        contactList.empty();
        const filteredContacts = contacts.filter(contact =>
            contact.name.toLowerCase().includes(filter.toLowerCase()) ||
            contact.phone_number.toLowerCase().includes(filter.toLowerCase())
        );
        if (filteredContacts.length === 0) {
            contactList.append('<tr><td colspan="3">No contacts found.</td></tr>');
            return;
        }
        filteredContacts.forEach(contact => {
            contactList.append(`
                        <tr>
                            <td>${contact.name}</td>
                            <td>${contact.phone_number}</td>
                            <td><button class="btn btn-danger btn-sm" onclick="showDeleteConfirm('contact', ${contact.id}, '${contact.name}')">Delete</button></td>
                        </tr>
                    `);
        });
    }

    // Render institution list
    function renderInstitutionList(institutions, filter = '') {
        const institutionList = $('#institutionList');
        institutionList.empty();
        const filteredInstitutions = institutions.filter(institution =>
            institution.name.toLowerCase().includes(filter.toLowerCase())
        );
        if (filteredInstitutions.length === 0) {
            institutionList.append('<tr><td colspan="2">No institutions found.</td></tr>');
            return;
        }
        filteredInstitutions.forEach(institution => {
            institutionList.append(`
                        <tr>
                            <td>${institution.name}</td>
                            <td><button class="btn btn-danger btn-sm" onclick="showDeleteConfirm('institution', ${institution.id}, '${institution.name}')">Delete</button></td>
                        </tr>
                    `);
        });
    }

    // Render merchant list
    function renderMerchantList(merchants, filter = '') {
        const merchantList = $('#merchantList');
        merchantList.empty();
        const filteredMerchants = merchants.filter(merchant =>
            merchant.name.toLowerCase().includes(filter.toLowerCase())
        );
        if (filteredMerchants.length === 0) {
            merchantList.append('<tr><td colspan="2">No merchants found.</td></tr>');
            return;
        }
        filteredMerchants.forEach(merchant => {
            merchantList.append(`
                        <tr>
                            <td>${merchant.name}</td>
                            <td><button class="btn btn-danger btn-sm" onclick="showDeleteConfirm('merchant', ${merchant.id}, '${merchant.name}')">Delete</button></td>
                        </tr>
                    `);
        });
    }

    // Render IVR profile list
    function renderIvrProfileList(ivrProfiles, filter = '') {
        const ivrProfileList = $('#ivrProfileList');
        ivrProfileList.empty();
        const filteredIvrProfiles = ivrProfiles.filter(profile =>
            (profile.profile_name || '').toLowerCase().includes(filter.toLowerCase()) ||
            (profile.institution_name || '').toLowerCase().includes(filter.toLowerCase()) ||
            (profile.caller_id || '').toLowerCase().includes(filter.toLowerCase()) ||
            (profile.callback_number || '').toLowerCase().includes(filter.toLowerCase()) ||
            (profile.merchant_name || '').toLowerCase().includes(filter.toLowerCase()) ||
            (profile.amount || '').toString().toLowerCase().includes(filter.toLowerCase())
        );
        if (filteredIvrProfiles.length === 0) {
            ivrProfileList.append('<tr><td colspan="7">No IVR profiles found.</td></tr>');
            return;
        }
        filteredIvrProfiles.forEach(profile => {
            ivrProfileList.append(`
                        <tr>
                            <td>${profile.profile_name || ''}</td>
                            <td>${profile.institution_name || ''}</td>
                            <td>${profile.caller_id || ''}</td>
                            <td>${profile.callback_number || ''}</td>
                            <td>${profile.merchant_name || ''}</td>
                            <td>${profile.amount || ''}</td>
                            <td><button class="btn btn-danger btn-sm" onclick="showDeleteConfirm('ivr_profile', ${profile.id}, '${profile.profile_name || ''}')">Delete</button></td>
                        </tr>
                    `);
        });
    }

    // Populate dropdowns with saved data
    function populateDropdowns(contacts, institutions, merchants) {
        const institutionDropdown = $('#institutionDropdown');
        const callerIdDropdown = $('#callerIdDropdown');
        const callbackNumberDropdown = $('#callbackNumberDropdown');
        const merchantDropdown = $('#merchantDropdown');

        // Clear existing options
        institutionDropdown.empty();
        callerIdDropdown.empty();
        callbackNumberDropdown.empty();
        merchantDropdown.empty();

        // Debugging: Log the data received
        console.log('Populating dropdowns with:', {
            contacts,
            institutions,
            merchants
        });

        // Populate institutions
        if (institutions && institutions.length > 0) {
            institutions.forEach(institution => {
                institutionDropdown.append(
                    `<a class="dropdown-item" href="#" data-name="${institution.name}">${institution.name}</a>`
                );
            });
        } else {
            institutionDropdown.append(
                '<a class="dropdown-item disabled" href="#">No institutions saved</a>'
            );
        }

        // Populate contacts for caller ID and callback number
        if (contacts && contacts.length > 0) {
            contacts.forEach(contact => {
                const item =
                    `<a class="dropdown-item" href="#" data-number="${contact.phone_number}">${contact.name} - ${contact.phone_number}</a>`;
                callerIdDropdown.append(item);
                callbackNumberDropdown.append(item);
            });
        } else {
            callerIdDropdown.append(
                '<a class="dropdown-item disabled" href="#">No contacts saved</a>'
            );
            callbackNumberDropdown.append(
                '<a class="dropdown-item disabled" href="#">No contacts saved</a>'
            );
        }

        // Populate merchants
        if (merchants && merchants.length > 0) {
            merchants.forEach(merchant => {
                merchantDropdown.append(
                    `<a class="dropdown-item" href="#" data-name="${merchant.name}">${merchant.name}</a>`
                );
            });
        } else {
            merchantDropdown.append(
                '<a class="dropdown-item disabled" href="#">No merchants saved</a>'
            );
        }

        // Initialize Bootstrap dropdowns
        $('.dropdown-toggle').dropdown();
    }

    // Fetch and render contacts
    function fetchContacts() {
        $.ajax({
            url: './assets/backend/api.php',
            method: 'GET',
            data: {
                action: 'get_contacts'
            },
            dataType: 'json',
            success: function(response) {
                console.log('get_contacts response:', response);
                if (response.success) {
                    renderContactList(response.contacts, $('#contactSearch').val());
                    fetchInstitutions(response.contacts);
                } else {
                    showToast('Error', response.message, 'error');
                    fetchInstitutions([]); // Proceed with empty contacts
                }
            },
            error: function(xhr, status, error) {
                console.error('Fetch Contacts Error:', status, error, xhr.responseText);
                showToast('Error', 'Failed to fetch contacts.', 'error');
                fetchInstitutions([]); // Proceed with empty contacts
            }
        });
    }

    // Fetch and render institutions
    function fetchInstitutions(contacts) {
        $.ajax({
            url: './assets/backend/api.php',
            method: 'GET',
            data: {
                action: 'get_institutions'
            },
            dataType: 'json',
            success: function(response) {
                console.log('get_institutions response:', response);
                if (response.success) {
                    renderInstitutionList(response.institutions, $('#institutionSearch').val());
                    fetchMerchants(contacts, response.institutions);
                } else {
                    showToast('Error', response.message, 'error');
                    fetchMerchants(contacts, []); // Proceed with empty institutions
                }
            },
            error: function(xhr, status, error) {
                console.error('Fetch Institutions Error:', status, error, xhr.responseText);
                showToast('Error', 'Failed to fetch institutions.', 'error');
                fetchMerchants(contacts, []); // Proceed with empty institutions
            }
        });
    }

    // Fetch and render merchants
    function fetchMerchants(contacts, institutions) {
        $.ajax({
            url: './assets/backend/api.php',
            method: 'GET',
            data: {
                action: 'get_merchants'
            },
            dataType: 'json',
            success: function(response) {
                console.log('get_merchants response:', response);
                if (response.success) {
                    renderMerchantList(response.merchants, $('#merchantSearch').val());
                    fetchIvrProfiles(contacts, institutions, response.merchants);
                } else {
                    showToast('Error', response.message, 'error');
                    fetchIvrProfiles(contacts, institutions, []); // Proceed with empty merchants
                }
            },
            error: function(xhr, status, error) {
                console.error('Fetch Merchants Error:', status, error, xhr.responseText);
                showToast('Error', 'Failed to fetch merchants.', 'error');
                fetchIvrProfiles(contacts, institutions, []); // Proceed with empty merchants
            }
        });
    }

    // Fetch and render IVR profiles
    function fetchIvrProfiles(contacts, institutions, merchants) {
        $.ajax({
            url: './assets/backend/api.php',
            method: 'GET',
            data: {
                action: 'get_ivr_profiles'
            },
            dataType: 'json',
            success: function(response) {
                console.log('get_ivr_profiles response:', response);
                if (response.success) {
                    renderIvrProfileList(response.ivr_profiles, $('#ivrProfileSearch').val());
                    populateDropdowns(contacts || [], institutions || [], merchants || []);
                } else {
                    showToast('Error', response.message, 'error');
                    populateDropdowns(contacts || [], institutions || [], merchants || []);
                }
            },
            error: function(xhr, status, error) {
                console.error('Fetch IVR Profiles Error:', status, error, xhr.responseText);
                showToast('Error', 'Failed to fetch IVR profiles.', 'error');
                populateDropdowns(contacts || [], institutions || [], merchants || []);
            }
        });
    }

    // Add new contact
    $('#contactForm').on('submit', function(e) {
        e.preventDefault();
        const name = $('#contactName').val();
        const phone_number = $('#contactNumber').val();
        const phonePattern = /^1\d{9,13}$/;

        if (!name || !phone_number) {
            showToast('Error', 'Please fill in all fields.', 'error');
            return;
        }

        if (!phonePattern.test(phone_number)) {
            showToast('Error', 'Please enter a valid phone number starting with 1 (e.g., 12025550123).',
                'error');
            return;
        }

        $.ajax({
            url: './assets/backend/api.php',
            method: 'POST',
            data: {
                action: 'add_contact',
                name,
                phone_number,
                csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
            },
            dataType: 'json',
            success: function(response) {
                console.log('add_contact response:', response);
                if (response.success) {
                    fetchContacts();
                    $('#contactForm')[0].reset();
                    showToast('Success', 'Contact added successfully!', 'success');
                } else {
                    showToast('Error', response.message, 'error');
                }
            },
            error: function(xhr) {
                console.error('Add Contact AJAX Error:', xhr.responseText);
                const message = xhr.responseJSON?.message ||
                    'Failed to add contact. Please try again.';
                showToast('Error', message, 'error');
            }
        });
    });

    // Add new institution
    $('#institutionForm').on('submit', function(e) {
        e.preventDefault();
        const name = $('#institutionName').val();

        if (!name) {
            showToast('Error', 'Please enter an institution name.', 'error');
            return;
        }

        $.ajax({
            url: './assets/backend/api.php',
            method: 'POST',
            data: {
                action: 'add_institution',
                name,
                csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
            },
            dataType: 'json',
            success: function(response) {
                console.log('add_institution response:', response);
                if (response.success) {
                    fetchInstitutions();
                    $('#institutionForm')[0].reset();
                    showToast('Success', 'Institution added successfully!', 'success');
                } else {
                    showToast('Error', response.message, 'error');
                }
            },
            error: function(xhr) {
                console.error('Add Institution AJAX Error:', xhr.responseText);
                const message = xhr.responseJSON?.message ||
                    'Failed to add institution. Please try again.';
                showToast('Error', message, 'error');
            }
        });
    });

    // Add new merchant
    $('#merchantForm').on('submit', function(e) {
        e.preventDefault();
        const name = $('#merchantName').val();

        if (!name) {
            showToast('Error', 'Please enter a merchant name.', 'error');
            return;
        }

        $.ajax({
            url: './assets/backend/api.php',
            method: 'POST',
            data: {
                action: 'add_merchant',
                name,
                csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
            },
            dataType: 'json',
            success: function(response) {
                console.log('add_merchant response:', response);
                if (response.success) {
                    fetchMerchants();
                    $('#merchantForm')[0].reset();
                    showToast('Success', 'Merchant added successfully!', 'success');
                } else {
                    showToast('Error', response.message, 'error');
                }
            },
            error: function(xhr) {
                console.error('Add Merchant AJAX Error:', xhr.responseText);
                const message = xhr.responseJSON?.message ||
                    'Failed to add merchant. Please try again.';
                showToast('Error', message, 'error');
            }
        });
    });

    // Add new IVR profile
    $('#ivrProfileForm').on('submit', function(e) {
        e.preventDefault();
        const profileName = $('#ivrProfileName').val();
        const institutionName = $('#ivrInstitutionName').val();
        const callerId = $('#ivrCallerId').val();
        const callbackNumber = $('#ivrCallbackNumber').val();
        const merchantName = $('#ivrMerchantName').val();
        const amount = $('#ivrAmount').val();
        const phonePattern = /^1\d{9,13}$/;

        if (!profileName || !institutionName || !callerId || !callbackNumber || !merchantName || !amount) {
            showToast('Error', 'Please fill in all fields.', 'error');
            return;
        }

        if (!phonePattern.test(callerId) || !phonePattern.test(callbackNumber)) {
            showToast('Error', 'Please enter valid phone numbers starting with 1 (e.g., 12025550123).',
                'error');
            return;
        }

        $.ajax({
            url: './assets/backend/api.php',
            method: 'POST',
            data: {
                action: 'add_ivr_profile',
                profile_name: profileName,
                institution_name: institutionName,
                caller_id: callerId,
                callback_number: callbackNumber,
                merchant_name: merchantName,
                amount,
                csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
            },
            dataType: 'json',
            success: function(response) {
                console.log('add_ivr_profile response:', response);
                if (response.success) {
                    fetchIvrProfiles();
                    $('#ivrProfileForm')[0].reset();
                    showToast('Success', 'IVR Profile added successfully!', 'success');
                } else {
                    showToast('Error', response.message, 'error');
                }
            },
            error: function(xhr) {
                console.error('Add IVR Profile AJAX Error:', xhr.responseText);
                const message = xhr.responseJSON?.message ||
                    'Failed to add IVR profile. Please try again.';
                showToast('Error', message, 'error');
            }
        });
    });

    // Show Delete Confirmation Modal
    function showDeleteConfirm(type, id, name) {
        const modal = $('#deleteConfirmModal');
        const message = $('#deleteConfirmMessage');
        const confirmButton = $('#confirmDelete');

        message.text(`Are you sure you want to delete this ${type}: "${name}"? This action cannot be undone.`);

        confirmButton.off('click').on('click', function() {
            if (type === 'contact') {
                deleteContact(id);
            } else if (type === 'institution') {
                deleteInstitution(id);
            } else if (type === 'merchant') {
                deleteMerchant(id);
            } else if (type === 'ivr_profile') {
                deleteIvrProfile(id);
            }
            modal.modal('hide');
        });

        modal.modal('show');
    }

    // Delete contact
    function deleteContact(id) {
        $.ajax({
            url: './assets/backend/api.php',
            method: 'POST',
            data: {
                action: 'delete_contact',
                id,
                csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
            },
            dataType: 'json',
            success: function(response) {
                console.log('delete_contact response:', response);
                if (response.success) {
                    fetchContacts();
                    showToast('Success', 'Contact deleted successfully!', 'success');
                } else {
                    showToast('Error', response.message, 'error');
                }
            },
            error: function(xhr) {
                console.error('Delete Contact AJAX Error:', xhr.responseText);
                const message = xhr.responseJSON?.message || 'Failed to delete contact. Please try again.';
                showToast('Error', message, 'error');
            }
        });
    }

    // Delete institution
    function deleteInstitution(id) {
        $.ajax({
            url: './assets/backend/api.php',
            method: 'POST',
            data: {
                action: 'delete_institution',
                id,
                csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
            },
            dataType: 'json',
            success: function(response) {
                console.log('delete_institution response:', response);
                if (response.success) {
                    fetchInstitutions();
                    showToast('Success', 'Institution deleted successfully!', 'success');
                } else {
                    showToast('Error', response.message, 'error');
                }
            },
            error: function(xhr) {
                console.error('Delete Institution AJAX Error:', xhr.responseText);
                const message = xhr.responseJSON?.message ||
                    'Failed to delete institution. Please try again.';
                showToast('Error', message, 'error');
            }
        });
    }

    // Delete merchant
    function deleteMerchant(id) {
        $.ajax({
            url: './assets/backend/api.php',
            method: 'POST',
            data: {
                action: 'delete_merchant',
                id,
                csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
            },
            dataType: 'json',
            success: function(response) {
                console.log('delete_merchant response:', response);
                if (response.success) {
                    fetchMerchants();
                    showToast('Success', 'Merchant deleted successfully!', 'success');
                } else {
                    showToast('Error', response.message, 'error');
                }
            },
            error: function(xhr) {
                console.error('Delete Merchant AJAX Error:', xhr.responseText);
                const message = xhr.responseJSON?.message || 'Failed to delete merchant. Please try again.';
                showToast('Error', message, 'error');
            }
        });
    }

    // Delete IVR profile
    function deleteIvrProfile(id) {
        $.ajax({
            url: './assets/backend/api.php',
            method: 'POST',
            data: {
                action: 'delete_ivr_profile',
                id,
                csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
            },
            dataType: 'json',
            success: function(response) {
                console.log('delete_ivr_profile response:', response);
                if (response.success) {
                    fetchIvrProfiles();
                    showToast('Success', 'IVR Profile deleted successfully!', 'success');
                } else {
                    showToast('Error', response.message, 'error');
                }
            },
            error: function(xhr) {
                console.error('Delete IVR Profile AJAX Error:', xhr.responseText);
                const message = xhr.responseJSON?.message ||
                    'Failed to delete IVR profile. Please try again.';
                showToast('Error', message, 'error');
            }
        });
    }

    // Search functionality
    $('#contactSearch').on('input', function() {
        fetchContacts();
    });

    $('#institutionSearch').on('input', function() {
        fetchInstitutions();
    });

    $('#merchantSearch').on('input', function() {
        fetchMerchants();
    });

    $('#ivrProfileSearch').on('input', function() {
        fetchIvrProfiles();
    });

    // Handle dropdown item selection
    $(document).on('click', '.dropdown-menu .dropdown-item:not(.disabled)', function(e) {
        e.preventDefault();
        const number = $(this).data('number');
        const name = $(this).data('name');
        const input = $(this).closest('.input-group').find('input');

        console.log('Dropdown item clicked:', {
            number,
            name,
            inputId: input.attr('id')
        });

        if (number) {
            input.val(number);
        } else if (name) {
            input.val(name);
        }

        // Close the dropdown
        $(this).closest('.dropdown-menu').dropdown('toggle');
    });

    // Initialize
    $(document).ready(function() {
        $('#toastNotification').toast({
            delay: 3000
        });

        // Ensure dropdowns are initialized
        $('.dropdown-toggle').dropdown();

        fetchContacts();

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