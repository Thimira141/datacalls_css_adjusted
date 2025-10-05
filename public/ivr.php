<?php
require_once __DIR__ . '/../config.php';

use inc\classes\CSRFToken;
use inc\classes\Auth;
use Illuminate\Database\Capsule\Manager as DB;

if (!Auth::check()) {
    header("Location: {$config->pUrl}/login.php");
    exit;
}

try {
    // Fetch username using DB facade
    $user = DB::table('users')
        ->select('username')
        ->where('id', $_SESSION['user_id'])
        ->first();

    $display_username = $user ? htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') : "Unknown User";
    $_SESSION['username'] = $user ? $user->username : null;

} catch (Throwable $e) {
    $display_username = "Error fetching username";
    error_log("Database error: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DataCaller - IVR</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Custom Styles -->
    <link href="<?= $config->pUrl; ?>/css/page-specific/ivr.css" rel="stylesheet">
    <?= CSRFToken::getInstance()->renderToken(true); ?>
</head>

<body class="sidebar-mini ivr">
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
                    <li class="active"><a href="ivr.php"><i class="fas fa-phone-square-alt"></i> IVR</a></li>
                    <li><a href="dtmf.php"><i class="fas fa-chart-bar"></i> DTMF</a></li>
                    <li><a href="cdr.php"><i class="fas fa-file-alt"></i> CDR Reports</a></li>
                    <li class="header">ACCOUNT</li>
                    <li><a href="account.php"><i class="fas fa-user-cog"></i> Account Settings</a></li>
                    <li><a href="#" data-toggle="modal" data-target="#logoutModal"><i class="fas fa-sign-out-alt"></i>
                            Logout</a></li>
                </ul>
            </section>
        </aside>

        <!-- Live Call Indicator -->
        <div class="live-call-indicator" id="liveCallIndicator">
            <i class="fas fa-phone"></i>
            <span class="call-count" id="callCount">0</span>
        </div>

        <!-- Live Call Window -->
        <div class="live-call-window" id="liveCallWindow">
            <!-- Mini call cards will be populated here -->
        </div>

        <!-- Content -->
        <div class="content-wrapper">
            <section class="content-header">
                <h1>IVR System</h1>
            </section>
            <section class="content container-fluid">
                <!-- Call Initiator -->
                <div class="call-initiator">
                    <h4>
                        Initiate IVR Call
                        <div class="ivr-profile-dropdown">
                            <button class="btn dropdown-toggle" type="button" data-toggle="dropdown"
                                aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-headset"></i> Load IVR Profile
                            </button>
                            <div class="dropdown-menu" id="ivrProfileDropdown">
                                <!-- IVR Profiles will be populated here -->
                            </div>
                        </div>
                    </h4>
                    <form id="ivrForm">
                        <input type="hidden" name="csrf_token"
                            value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Institution Name</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="institutionName" placeholder="e.g. USAA"
                                        required>
                                    <div class="input-group-append">
                                        <button class="btn dropdown-toggle" type="button" data-toggle="dropdown"
                                            aria-haspopup="true" aria-expanded="false">
                                            <i class="fas fa-university"></i>
                                        </button>
                                        <div class="dropdown-menu" id="institutionDropdown">
                                            <!-- Institutions will be populated here -->
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Customer Name</label>
                                <input type="text" class="form-control" id="customerName" placeholder="e.g. John Smith"
                                    required>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Customer Number</label>
                                <input type="tel" class="form-control" id="customerNumber"
                                    placeholder="e.g. 19234567890" required>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Caller ID</label>
                                <div class="input-group">
                                    <input type="tel" class="form-control" id="callerId" placeholder="e.g. 18005318722"
                                        required>
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
                            <div class="col-md-4 form-group">
                                <label>Callback Method</label>
                                <div class="input-group">
                                    <select class="form-control" id="callbackMethod" required>
                                        <option value="phone">Phone Number</option>
                                        <option value="softphone">Softphone</option>
                                    </select>
                                    <input type="tel" class="form-control" id="callbackNumber"
                                        placeholder="e.g. 19234567890">
                                    <div class="input-group-append">
                                        <button class="btn dropdown-toggle" type="button" data-toggle="dropdown"
                                            aria-haspopup="true" aria-expanded="false">
                                            <i class="fas fa-address-book"></i>
                                        </button>
                                        <div class="dropdown-menu" id="callbackNumberDropdown">
                                            <!-- Contacts will be populated here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Merchant Name</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="merchantName" placeholder="e.g. Target"
                                        required>
                                    <div class="input-group-append">
                                        <button class="btn dropdown-toggle" type="button" data-toggle="dropdown"
                                            aria-haspopup="true" aria-expanded="false">
                                            <i class="fas fa-store"></i>
                                        </button>
                                        <div class="dropdown-menu" id="merchantDropdown">
                                            <!-- Merchants will be populated here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Amount</label>
                                <input type="number" class="form-control" id="amount" placeholder="350.85" step="0.01"
                                    required>
                            </div>
                            <input type="hidden" id="magnus_ivr_id" name="magnus_ivr_id">
                            <div class="col-md-4 form-group d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-block" id="callButton">Call</button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- IVR Calls Section -->
                <div class="ivr-calls">
                    <h4>IVR Calls</h4>
                    <div class="call-grid" id="callGrid">
                        <!-- Call cards will be populated here -->
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

        $(document).ready(function () {
            const ivrForm = $('#ivrForm');
            const callButton = $('#callButton');
            const institutionInput = $('#institutionName');
            const callerIdInput = $('#callerId');
            const callbackMethodSelect = $('#callbackMethod');
            const callbackNumberInput = $('#callbackNumber');
            const callbackNumberDropdown = $('#callbackNumberDropdown');
            const merchantInput = $('#merchantName');
            const customerNameInput = $('#customerName');
            const customerNumberInput = $('#customerNumber');
            const amountInput = $('#amount');
            const institutionDropdown = $('#institutionDropdown');
            const callerIdDropdown = $('#callerIdDropdown');
            const merchantDropdown = $('#merchantDropdown');
            const ivrProfileDropdown = $('#ivrProfileDropdown');
            const callGrid = $('#callGrid');
            let activeCalls = JSON.parse(sessionStorage.getItem('activeCalls')) || {};

            // Update live call indicator
            function updateLiveCallIndicator() {
                const callCount = Object.keys(activeCalls).length;
                $('#callCount').text(callCount);
                $('#liveCallIndicator').toggleClass('active', callCount > 0);
                updateLiveCallWindow();
            }

            // Update live call window
            function updateLiveCallWindow() {
                const liveCallWindow = $('#liveCallWindow');
                liveCallWindow.empty();
                let activeCalls = JSON.parse(sessionStorage.getItem('activeCalls')) || {};

                if (Object.keys(activeCalls).length > 0) {
                    Object.entries(activeCalls).forEach(([callId, callData]) => {
                        const miniCardHtml = `
                        <div class="mini-call-card" data-call-id="${callId}">
                            <h6>Call to ${callData.customerNumber}</h6>
                            <div class="status ${callData.status}">${callData.status.charAt(0).toUpperCase() + callData.status.slice(1)}</div>
                            <h6 class="dtmf_input">DTMF: ${callData.dtmf_input??'N/A'}</h6>
                            <div class="buttons">
                                <button class="btn btn-mute ${callData.muted ? 'muted' : ''}" data-call-id="${callId}">${callData.muted ? 'Mute' : 'Unmute'}</button>
                                <button class="btn btn-danger" data-call-id="${callId}" data-call-channel="${callData.CallChannel}" data-cdr-unique-id="${callData.CDRUniqueID}">End Call</button>
                            </div>
                        </div>
                    `;
                        liveCallWindow.append(miniCardHtml);
                    });
                    liveCallWindow.addClass('active');
                } else {
                    liveCallWindow.removeClass('active');
                }
            }

            // Toggle live call window visibility
            $('#liveCallIndicator').on('click', function () {
                $('#liveCallWindow').toggleClass('active');
            });

            // Load contacts into dropdowns
            function loadContacts() {
                $.ajax({
                    url: '<?= $config->app->url; ?>/controller/api.php',
                    method: 'GET',
                    data: {
                        action: 'get_contacts'
                    },
                    dataType: 'json',
                    success: function (response) {
                        callerIdDropdown.empty();
                        callbackNumberDropdown.empty();
                        if (response.success && response.contacts.length > 0) {
                            response.contacts.forEach(contact => {
                                const item =
                                    `<a class="dropdown-item" href="#" data-number="${contact.phone_number}">${contact.name} - ${contact.phone_number}</a>`;
                                callerIdDropdown.append(item);
                                callbackNumberDropdown.append(item);
                            });
                        } else {
                            callerIdDropdown.append(
                                '<a class="dropdown-item" href="#">No contacts saved</a>');
                            callbackNumberDropdown.append(
                                '<a class="dropdown-item" href="#">No contacts saved</a>');
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Load Contacts Error:', status, error);
                        callerIdDropdown.append(
                            '<a class="dropdown-item" href="#">Error loading contacts</a>');
                        callbackNumberDropdown.append(
                            '<a class="dropdown-item" href="#">Error loading contacts</a>');
                    }
                });
            }

            // Load institutions into dropdown
            function loadInstitutions() {
                $.ajax({
                    url: '<?= $config->app->url; ?>/controller/api.php',
                    method: 'GET',
                    data: {
                        action: 'get_institutions'
                    },
                    dataType: 'json',
                    success: function (response) {
                        institutionDropdown.empty();
                        if (response.success && response.institutions.length > 0) {
                            response.institutions.forEach(institution => {
                                const item =
                                    `<a class="dropdown-item" href="#" data-name="${institution.name}">${institution.name}</a>`;
                                institutionDropdown.append(item);
                            });
                        } else {
                            institutionDropdown.append(
                                '<a class="dropdown-item" href="#">No institutions saved</a>');
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Load Institutions Error:', status, error);
                        institutionDropdown.append(
                            '<a class="dropdown-item" href="#">Error loading institutions</a>');
                    }
                });
            }

            // Load merchants into dropdown
            function loadMerchants() {
                $.ajax({
                    url: '<?= $config->app->url; ?>/controller/api.php',
                    method: 'GET',
                    data: {
                        action: 'get_merchants'
                    },
                    dataType: 'json',
                    success: function (response) {
                        merchantDropdown.empty();
                        if (response.success && response.merchants.length > 0) {
                            response.merchants.forEach(merchant => {
                                const item =
                                    `<a class="dropdown-item" href="#" data-name="${merchant.name}">${merchant.name}</a>`;
                                merchantDropdown.append(item);
                            });
                        } else {
                            merchantDropdown.append(
                                '<a class="dropdown-item" href="#">No merchants saved</a>');
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Load Merchants Error:', status, error);
                        merchantDropdown.append(
                            '<a class="dropdown-item" href="#">Error loading merchants</a>');
                    }
                });
            }

            // Load IVR profiles into dropdown
            function loadIvrProfiles() {
                $.ajax({
                    url: '<?= $config->app->url; ?>/controller/api.php',
                    method: 'GET',
                    data: {
                        action: 'get_ivr_profiles'
                    },
                    dataType: 'json',
                    success: function (response) {
                        ivrProfileDropdown.empty();
                        if (response.success && response.ivr_profiles.length > 0) {
                            response.ivr_profiles.forEach(profile => {
                                const item =
                                    `<a class="dropdown-item" href="#" 
                            data-profile-name="${profile.profile_name}"
                            data-institution-name="${profile.institution_name}"
                            data-caller-id="${profile.caller_id}"
                            data-callback-number="${profile.callback_number}"
                            data-merchant-name="${profile.merchant_name}"
                            data-amount="${profile.amount}"
                            data-magnus-ivr-id="${profile.magnus_ivr_id || ''}">${profile.profile_name}</a>`;
                                ivrProfileDropdown.append(item);
                            });
                        } else {
                            ivrProfileDropdown.append(
                                '<a class="dropdown-item" href="#">No IVR profiles saved</a>');
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Load IVR Profiles Error:', status, error);
                        ivrProfileDropdown.append(
                            '<a class="dropdown-item" href="#">Error loading IVR profiles</a>');
                    }
                });
            }

            $('.dropdown-menu').on('click', '.dropdown-item', function (e) {
                e.preventDefault();
                const profileName = $(this).data('profile-name');
                const institutionName = $(this).data('institution-name');
                const callerId = $(this).data('caller-id');
                const callbackNumber = $(this).data('callback-number');
                const merchantName = $(this).data('merchant-name');
                const amount = $(this).data('amount');
                const magnusIvrId = $(this).data('magnus-ivr-id');
                const number = $(this).data('number');
                const name = $(this).data('name');
                const input = $(this).closest('.input-group').find('input');

                if (profileName) {
                    institutionInput.val(institutionName || '');
                    customerNameInput.val('');
                    customerNumberInput.val('');
                    callerIdInput.val(callerId || '');
                    callbackNumberInput.val(callbackNumber || '');
                    callbackMethodSelect.val('phone');
                    callbackNumberInput.show().prop('required', true);
                    callbackNumberInput.closest('.input-group').find('.input-group-append').show();
                    merchantInput.val(merchantName || '');
                    amountInput.val(amount || '');
                    $('#magnus_ivr_id').val(magnusIvrId || '');
                } else if (number) {
                    input.val(number);
                } else if (name) {
                    input.val(name);
                }
            });

            // Handle callback method change
            callbackMethodSelect.on('change', function () {
                if ($(this).val() === 'softphone') {
                    callbackNumberInput.hide().val('softphone').prop('required', false);
                    callbackNumberInput.closest('.input-group').find('.input-group-append').hide();
                } else {
                    callbackNumberInput.show().val('').prop('required', true);
                    callbackNumberInput.closest('.input-group').find('.input-group-append').show();
                }
            });

            // Handle dropdown item selection
            $('.dropdown-menu').on('click', '.dropdown-item', function (e) {
                e.preventDefault();
                const profileName = $(this).data('profile-name');
                const institutionName = $(this).data('institution-name');
                const callerId = $(this).data('caller-id');
                const callbackNumber = $(this).data('callback-number');
                const merchantName = $(this).data('merchant-name');
                const amount = $(this).data('amount');
                const number = $(this).data('number');
                const name = $(this).data('name');
                const input = $(this).closest('.input-group').find('input');

                if (profileName) {
                    institutionInput.val(institutionName || '');
                    customerNameInput.val('');
                    customerNumberInput.val('');
                    callerIdInput.val(callerId || '');
                    callbackNumberInput.val(callbackNumber || '');
                    callbackMethodSelect.val('phone'); // Default to phone for profiles
                    callbackNumberInput.show().prop('required', true);
                    callbackNumberInput.closest('.input-group').find('.input-group-append').show();
                    merchantInput.val(merchantName || '');
                    amountInput.val(amount || '');
                } else if (number) {
                    input.val(number);
                } else if (name) {
                    input.val(name);
                }
            });

            // Add call card to grid
            function addCallCard(callId, CallChannel, CDRUniqueID, callData) {
                const callbackDisplay = callData.callbackMethod === 'softphone' ? 'Softphone' : callData
                    .callbackNumber;
                const cardHtml = `
                <div class="call-card" data-call-id="${callId}" data-call-channel="${CallChannel}" data-cdr-unique-id="${CDRUniqueID}">
                    <h5><i class="fas fa-phone"></i> Call to ${callData.customerNumber} <span class="live-icon" style="color: #28a745; margin-left: 10px;"><i class="fas fa-circle"></i></span></h5>
                    <div class="status ${callData.status || 'calling'}">${callData.status ? callData.status.charAt(0).toUpperCase() + callData.status.slice(1) : 'Calling...'}</div>
                    <div class="details">
                        <p><strong>Institution:</strong> ${callData.institutionName}</p>
                        <p><strong>Customer:</strong> ${callData.customerName}</p>
                        <p><strong>Callback:</strong> ${callbackDisplay}</p>
                        <p><strong>Merchant:</strong> ${callData.merchantName}</p>
                        <p><strong>Amount:</strong> $${parseFloat(callData.amount).toFixed(2)}</p>
                    </div>
                    <h4 class="dtmf_input text-center">DTMF: ${callData.dtmf_input??'N/A'}</h4>
                    <div class="buttons">
                        <button class="btn btn-mute ${callData.muted !== false ? 'muted' : ''}" data-call-id="${callId}">${callData.muted !== false ? 'Mute' : 'Unmute'}</button>
                        <button class="btn btn-danger" data-call-id="${callId}" data-call-channel="${CallChannel}" data-cdr-unique-id="${CDRUniqueID}" >End Call</button>
                    </div>
                </div>
            `;
                callGrid.append(cardHtml);
                activeCalls[callId] = {
                    ...callData,
                    status: callData.status || 'calling',
                    muted: callData.muted !== undefined ? callData.muted : true
                };
                sessionStorage.setItem('activeCalls', JSON.stringify(activeCalls));
                updateLiveCallIndicator();
                pollCallStatus(callData); // Start polling for real-time updates
            }

            // Poll call status
            function pollCallStatus(callData) {
                let activeCalls = JSON.parse(sessionStorage.getItem('activeCalls')) || {};
                const interval = setInterval(() => {
                    const callId = callData.callId;
                    if (!activeCalls[callId] || ['end', 'ended'].includes(callData.status)) {
                        clearInterval(interval);
                        return;
                    }
                    $.ajax({
                        url: '<?= $config->app->url; ?>/controller/api.php',
                        method: 'POST',
                        data: {
                            action: 'get_call_status',
                            call_id: callId,
                            callChannel: callData.CallChannel,
                            cdr_uniqueid: callData.CDRUniqueID,
                            csrf_token: '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>'
                        },
                        dataType: 'json',
                        success: function (main_response) {
                            const response = main_response.response;
                            if (response.success && activeCalls[callId]) {
                                activeCalls[callId].status = response.status;
                                activeCalls[callId].dtmf_input = response.dtmf_input??'N/A';
                                $(`.call-card[data-call-id="${callId}"] .status`)
                                    .addClass(response.status)
                                    .text(response.status.charAt(0).toUpperCase() + response
                                        .status.slice(1));
                                // dtmf write
                                $(`.call-card[data-call-id="${callId}"] .dtmf_input`)
                                    .text(`DTMF: ${response.dtmf_input??'N/A'}`);
                                $(`.mini-call-card[data-call-id="${callId}"] .status`)
                                    .addClass(response.status)
                                    .text(response.status.charAt(0).toUpperCase() + response
                                        .status.slice(1));
                                // dtmf write
                                $(`.mini-call-card[data-call-id="${callId}"] .dtmf_input`)
                                    .text(`DTMF: ${response.dtmf_input??'N/A'}`);
                                sessionStorage.setItem('activeCalls', JSON.stringify(
                                    activeCalls));
                                if (['answered', 'up'].includes(response.status)) {
                                    // pollDtmfInput(callData);
                                    // alert('call picked up');
                                }
                                // call ended TODO: fix when call ended clear interval
                                if (['ended'].includes(response.status)) {
                                    alert("Call ended! channel:");
                                    clearInterval(interval);
                                    return;
                                }
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('Poll Call Status Error:', status, error);
                        }
                    });
                }, 2000);
            }

            // TODO: Poll for DTMF input

            // function pollDtmfInput(callData) {
            //     const interval = setInterval(() => {
            //         const callId = callData.callId;
            //         if (!activeCalls[callId] || activeCalls[callId].status !== 'answered') {
            //             clearInterval(interval);
            //             return;
            //         }
            //         $.ajax({                        
            //             url: '<?= $config->app->url; ?>/controller/api.php',
            //             method: 'POST',
            //             data: {
            //                 action: 'check_dtmf',
            //                 call_id: callId,
            //                 callChannel: callData.CallChannel,
            //                 cdr_uniqueid: callData.CDRUniqueID,
            //                 csrf_token: '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>'
            //             },
            //             dataType: 'json',
            //             success: function (response) {
            //                 if (response.success && response.dtmf === '2' && activeCalls[
            //                     callId]) {
            //                     $(`.call-card[data-call-id="${callId}"] .status`)
            //                         .append(
            //                             ' <span style="color: #1abc9c;">(DTMF 2 Received - Waiting for Unmute)</span>'
            //                         );
            //                     $(`.mini-call-card[data-call-id="${callId}"] .status`)
            //                         .append(
            //                             ' <span style="color: #1abc9c;">(DTMF 2 Received - Waiting for Unmute)</span>'
            //                         );
            //                     clearInterval(interval);
            //                 }
            //             },
            //             error: function (xhr, status, error) {
            //                 console.error('Check DTMF Error:', status, error);
            //             }
            //         });
            //     }, 2000);
            // }
            // Handle form submission
            ivrForm.on('submit', function (e) {
                e.preventDefault();
                const institutionName = institutionInput.val();
                const customerName = customerNameInput.val();
                let customerNumber = customerNumberInput.val().replace(/\D/g, ''); // Remove non-digits
                const callerId = callerIdInput.val();
                const callbackMethod = callbackMethodSelect.val();
                const callbackNumber = callbackMethod === 'softphone' ? 'softphone' : callbackNumberInput
                    .val();
                const merchantName = merchantInput.val();
                const amount = amountInput.val();
                const magnusIvrId = $('#magnus_ivr_id').val();

                if (!institutionName || !customerName || !customerNumber || !callerId ||
                    (callbackMethod === 'phone' && !callbackNumber) || !merchantName || !amount) {
                    alert('Please fill in all required fields.');
                    return;
                }

                // Validate 10 or 11 digit phone number
                // if (!customerNumber.match(/^\d{10,11}$/)) {
                //     alert('Customer number must be 10 or 11 digits (e.g., 12017838927)');
                //     return;
                // }

                // Prepend +1 for API if not already present
                // if (!customerNumber.startsWith('+1')) {
                //     customerNumber = '+1' + customerNumber;
                // }

                $.ajax({
                    url: '<?= $config->app->url; ?>/controller/api.php',
                    method: 'POST',
                    data: {
                        action: 'initiate_call',
                        institution_name: institutionName,
                        customer_name: customerName,
                        customer_number: customerNumber,
                        caller_id: callerId,
                        callback_method: callbackMethod,
                        callback_number: callbackNumber,
                        merchant_name: merchantName,
                        amount: amount,
                        magnus_ivr_id: magnusIvrId,
                        csrf_token: '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>'
                    },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            const callId = response.call_id;
                            const CallChannel = response.callChannel;
                            const CDRUniqueID = response.cdr_uniqueid;
                            addCallCard(callId, CallChannel, CDRUniqueID, {
                                institutionName,
                                customerName,
                                customerNumber,
                                callerId,
                                callbackMethod,
                                callbackNumber,
                                merchantName,
                                amount,
                                muted: true,
                                CallChannel,
                                CDRUniqueID,
                                callId
                            });
                            ivrForm[0].reset();
                            callbackMethodSelect.val('phone');
                            callbackNumberInput.show().prop('required', true);
                            callbackNumberInput.closest('.input-group').find(
                                '.input-group-append').show();
                            $('#magnus_ivr_id').val('');
                            customerNameInput.val('');
                            customerNumberInput.val('');
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Initiate Call Error:', status, error);
                        alert('Failed to initiate call.');
                    }
                });
            });

            // Handle mute/unmute for call grid
            callGrid.on('click', '.btn-mute', function () {
                const callId = $(this).data('call-id');
                if (activeCalls[callId]) {
                    const mute = !activeCalls[callId].muted;
                    $.ajax({
                        url: '<?= $config->app->url; ?>/controller/api.php',
                        method: 'POST',
                        data: {
                            action: 'toggle_mute',
                            call_id: callId,
                            mute: mute,
                            csrf_token: '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>'
                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                activeCalls[callId].muted = mute;
                                $(`.call-card[data-call-id="${callId}"] .btn-mute`)
                                    .toggleClass('muted').text(mute ? 'Mute' : 'Unmute');
                                $(`.mini-call-card[data-call-id="${callId}"] .btn-mute`)
                                    .toggleClass('muted').text(mute ? 'Mute' : 'Unmute');
                                sessionStorage.setItem('activeCalls', JSON.stringify(
                                    activeCalls));
                            } else {
                                alert('Error toggling mute: ' + response.message);
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('Toggle Mute Error:', status, error);
                            alert('Failed to toggle mute.');
                        }
                    });
                }
            });

            // Handle end call for call grid
            callGrid.on('click', '.btn-danger', function () {
                const callId = $(this).data('call-id');
                const callChannel = $(this).data('call-channel');
                const CDRUniqueID = $(this).data('cdr-unique-id');
                if (activeCalls[callId]) {
                    $.ajax({
                        url: '<?= $config->app->url; ?>/controller/api.php',
                        method: 'POST',
                        data: {
                            action: 'end_call',
                            call_id: callId,
                            callChannel: callChannel,
                            cdr_uniqueid: CDRUniqueID,
                            csrf_token: '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>'
                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                $(`.call-card[data-call-id="${callId}"]`).remove();
                                $(`.mini-call-card[data-call-id="${callId}"]`).remove();
                                delete activeCalls[callId];
                                sessionStorage.setItem('activeCalls', JSON.stringify(
                                    activeCalls));
                                updateLiveCallIndicator();
                            } else {
                                alert('Error ending call: ' + response.message);
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('End Call Error:', status, error);
                            alert('Failed to end call.');
                        }
                    });
                }
            });

            // Handle mute/unmute for live call window
            $('#liveCallWindow').on('click', '.btn-mute', function () {
                const callId = $(this).data('call-id');
                if (activeCalls[callId]) {
                    const mute = !activeCalls[callId].muted;
                    $.ajax({
                        url: '<?= $config->app->url; ?>/controller/api.php',
                        method: 'POST',
                        data: {
                            action: 'toggle_mute',
                            call_id: callId,
                            mute: mute
                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                activeCalls[callId].muted = mute;
                                $(`.call-card[data-call-id="${callId}"] .btn-mute`)
                                    .toggleClass('muted').text(mute ? 'Mute' : 'Unmute');
                                $(`.mini-call-card[data-call-id="${callId}"] .btn-mute`)
                                    .toggleClass('muted').text(mute ? 'Mute' : 'Unmute');
                                sessionStorage.setItem('activeCalls', JSON.stringify(
                                    activeCalls));
                            } else {
                                alert('Error toggling mute: ' + response.message);
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('Toggle Mute Error:', status, error);
                            alert('Failed to toggle mute.');
                        }
                    });
                }
            });

            // Handle end call for live call window
            $('#liveCallWindow').on('click', '.btn-danger', function () {
                const callId = $(this).data('call-id');
                const callChannel = $(this).data('call-channel');
                const CDRUniqueID = $(this).data('cdr-unique-id');
                if (activeCalls[callId]) {
                    $.ajax({
                        url: '<?= $config->app->url; ?>/controller/api.php',
                        method: 'POST',
                        data: {
                            action: 'end_call',
                            call_id: callId,
                            callChannel: callChannel,
                            cdr_uniqueid: CDRUniqueID,
                            csrf_token: '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>'
                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                $(`.call-card[data-call-id="${callId}"]`).remove();
                                $(`.mini-call-card[data-call-id="${callId}"]`).remove();
                                delete activeCalls[callId];
                                sessionStorage.setItem('activeCalls', JSON.stringify(
                                    activeCalls));
                                updateLiveCallIndicator();
                            } else {
                                alert('Error ending call: ' + response.message);
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('End Call Error:', status, error);
                            alert('Failed to end call.');
                        }
                    });
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

            // Load existing calls on page load
            function loadExistingCalls() {
                Object.entries(activeCalls).forEach(([callId, CallChannel, CDRUniqueID, callData]) => {
                    addCallCard(callId, CallChannel, CDRUniqueID, callData);
                });
            }

            // Load data on page load
            loadContacts();
            loadInstitutions();
            loadMerchants();
            loadIvrProfiles();
            loadExistingCalls();
            updateLiveCallIndicator();
            callbackMethodSelect.val('phone'); // Initialize to phone
            callbackNumberInput.show().prop('required', true);
            callbackNumberInput.closest('.input-group').find('.input-group-append').show();
        });
    </script>
</body>

</html>