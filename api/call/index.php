<?php
/**
 * Asterisk Call Control API
 *
 * This script provides a secure HTTP interface for initiating, managing, and tracking VoIP calls
 * via Asterisk. It supports call origination, termination, status checks, and DTMF logging.
 *
 * Key Features:
 * - Authenticated access using hashed API keys
 * - Dynamic call creation via shell script execution
 * - Real-time channel status via AMI (Asterisk Manager Interface)
 * - DTMF input capture and database logging
 * - CDR (Call Detail Record) creation and update
 *
 * Dependencies:
 * - AsteriskClient.php: AMI wrapper for call control
 * - function.php: Utility functions (e.g., sanitization, error handling)
 * - Composer autoload: Includes PAMI and other dependencies
 *
 * Expected POST actions:
 * - make_call: Initiates a call and logs CDR
 * - end_call: Terminates a call and updates CDR
 * - status_call: Retrieves channel status and DTMF input
 * - hold_call: (Reserved for future use)
 *
 * @Author thimira dilshan <thimirad865@gmail.com>
 * @LastUpdated 2025-10-07
 */

require_once 'function.php';
require_once 'AsteriskClient.php';
require_once './vendor/autoload.php';
// set up the script validation data
header('Content-Type: application/json');

// AUTHENTICATION
$apiKey = "dc3c3e74645d2ec7c9b4184827f15778c8b2ce136477e30993b36c69421aa68e";
$headers = getallheaders();
$hashedKey = str_replace('Bearer ', '', $headers['Authorization'] ?? '');

if (!password_verify($apiKey, $hashedKey)) {
    json_error('Unauthorized', 401);
}

// DB connection
$configFile = '/etc/asterisk/res_config_mysql.conf';
if (!file_exists($configFile)) {
    json_error('Config file not found', 500);
}

$array = parse_ini_file($configFile);
if (!$array || !isset($array['dbhost'], $array['dbname'], $array['dbuser'], $array['dbpass'])) {
    json_error('Invalid DB config structure', 500);
}

try {
    $pdo = new PDO(
        "mysql:host={$array['dbhost']};dbname={$array['dbname']}",
        $array['dbuser'],
        $array['dbpass'],
        [
            PDO::MYSQL_ATTR_LOCAL_INFILE => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
        ]
    );
} catch (PDOException $e) {
    json_error('Database connection failed: ' . $e->getMessage(), 500);
}
// check action
switch ($_POST['action']) {
    case 'make_call':
        $data = [];
        $cols = ['id_user', 'id_plan', 'calledstation', 'callerid', 'starttime', 'sessiontime', 'sessionbill', 'buycost', 'uniqueid', 'callback_method', 'callback_destination', 'customer_name', 'customer_number', 'originate_tech'];
        foreach ($cols as $col) {
            $data[$col] = sanitizeText($_POST[$col] ?? null);
            if (!$data[$col] || empty($data[$col])) {
                json_error('Missing required field(s)');
            }
        }
        $data['src'] = $data['callerid'];
        // decode & validate url inputs
        $userId = $data['id_user'];
        $callerId = $data['callerid'];
        $callerName = sanitizeText($_POST['callerName'] ?? 'Support');
        $targetNumber = $data['calledstation'];
        $customer_name = $data['customer_name'] ?: 'customer_name';
        $customer_number = $data['customer_number'] ?: 'customer_number';
        // $callback_destination = "SIP/" . ($data['callback_method'] == "softphone" ? "" : "Telnum/") . $data['callback_destination'];
        $callback_destination = $data['callback_destination'];
        // originateTechArray
        $originateTechs = [
            'sip' => 'SIP',
            'telnum' => 'SIP/Telnum'
        ];
        if (!array_key_exists($data['originate_tech'], $originateTechs)) {
            json_error('Invalid Channel Driver!', 400);
        }
        $originateTechIvrConfirm = $originateTechs[$data['originate_tech']];
        $originateTechSupportWait = $data['callback_method'] == 'softphone' ? 'SIP' : 'SIP/Telnum';
        // remove unwanted parts for db
        unset($data['callback_method'], $data['callback_destination'], $data['customer_name'], $data['customer_number'], $data['originate_tech']);
        // new PAMI instance
        try {
            //code...
            $ami = new AsteriskClient();
            // set asterisk variables
            // TODO: run and test the code
            // originate call via new function
            $originateTwoLegConditionalCall = $ami->twoLegConditionalCall(
                $originateTechSupportWait,
                $customer_number,
                $customer_name,
                $callback_destination,
                's',
                'support-wait',
                [],
                $originateTechIvrConfirm,
                $callerId,
                $callerName,
                $targetNumber,
                's',
                'ivr-confirm',
                []
            );
            if ($originateTwoLegConditionalCall['success']) {
                $channel = $originateTwoLegConditionalCall['confirm']['channel'] ?? NULL;
                $db_output = cdr_create_record($pdo, $data);
                if (!$db_output['success']) {
                    // CDR db failed, then cancel the call
                    $call_end = call_end($channel);
                    $message = 'CDR DB Failed!' . $db_output['message'] . " ---- ";
                    $message .= ($call_end['status'] !== 0) ? "Shell Execute Failed! : " . json_encode($call_end['output']) : null;
                    json_error($message);
                }
                // send result
                echo json_encode([
                    'success' => true,
                    'output' => $originateTwoLegConditionalCall,
                    'channel' => $channel,
                    'channels_array' => $originateTwoLegConditionalCall['channels_array'],
                    'cdr_uniqueid' => $data['uniqueid']
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'output' => $originateTwoLegConditionalCall,
                    'error' => '$originateTwoLegConditionalCall->failed'
                ]);
            }
            // ami close
            $ami->close();
        } catch (\Throwable $th) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'output' => $th->__tostring(),
                'error' => $th->getMessage()
            ]);
        }
        // $astDBkeys = [
        //     // 'CID' => $callerId,
        //     // 'CNAME' => $callerName,
        //     // 'CUSTOMER_NUM' => $customer_number,
        //     // 'CUSTOMER_NAME' => $customer_name,
        //     // 'CALLBACK_DESTINATION' => $callback_destination,
        // ];
        // originate call for customer support
        // $originateSupportWait = $ami->originateCall($originateTechSupportWait, $customer_number, $customer_name, $callback_destination, 's', 'support-wait');
        // process call output


        // if ($originateSupportWait['success']) {
        //     // capture channel and send it back as response
        //     $channelWait = $originateSupportWait['channel'] ?? NULL;
        //     if ($channelWait) {
        //         // originate call for customer
        //         $originateIvrConfirm = $ami->originateCall($originateTechIvrConfirm, $callerId, $callerName, $targetNumber, 's', 'ivr-confirm', ['SUPPORT_CHANNEL' => $channel]);
        //         if ($originateIvrConfirm['success']) {
        //             $channelConfirm = $originateIvrConfirm['channel'] ?? NULL;
        //             // db CDR insert
        //             $db_output = cdr_create_record($pdo, $data);
        //             if (!$db_output['success']) {
        //                 // CDR db failed, then cancel the call
        //                 $call_end = call_end($channel);
        //                 $message = 'CDR DB Failed!' . $db_output['message'] . " ---- ";
        //                 $message .= ($call_end['status'] !== 0) ? "Shell Execute Failed! : " . json_encode($call_end['output']) : null;
        //                 json_error($message);
        //             }
        //             // send result
        //             echo json_encode([
        //                 'success' => true,
        //                 'output' => $originateIvrConfirm,
        //                 'channel' => $channelConfirm,
        //                 'cdr_uniqueid' => $data['uniqueid']
        //             ]);
        //         } else {
        //             // todo: hangup the SupportWait channel
        //             http_response_code(503);
        //             echo json_encode([
        //                 'success' => false,
        //                 'output' => $originateIvrConfirm,
        //                 'error' => '$originateIvrConfirm->Failed!'
        //             ]);
        //         }
        //     } else {
        //         http_response_code(503);
        //         echo json_encode([
        //             'success' => false,
        //             'output' => $originateSupportWait,
        //             'error' => '$originateSupportWait->success, channel capture failed!'
        //         ]);
        //     }
        // todo: clear unsuccessful calls that originate in this session
        // // ami close
        // $ami->close();
        // } else {
        //     http_response_code(503);
        //     echo json_encode([
        //         'success' => false,
        //         'output' => $originateSupportWait,
        //         'error' => '$originateSupportWait->Failed!'
        //     ]);
        // }
        break;

    case 'bridge_permit':
        $data = [];
        $cols = ['callChannel', 'supportChannel', 'permit'];
        foreach ($cols as $col) {
            $data[$col] = sanitizeText($_POST[$col] ?? null);
            if (!$data[$col] || empty($data[$col])) {
                json_error('Missing required field(s)');
            }
        }

        $callChannel = str_replace('\\/', '/', $data['callChannel']);
        $supportChannel = str_replace('\\/', '/', $data['supportChannel']);

        $ami = new AsteriskClient();

        // Optional: check both channels are still alive
        $statusA = $ami->getChannelStatus($callChannel);
        $statusB = $ami->getChannelStatus($supportChannel);

        if ($statusA === 'ended' || $statusB === 'ended') {
            $ami->close();
            json_error("One or both channels are no longer active");
        }

        // Perform AMI Bridge
        $bridgeResult = $ami->bridgeChannels($callChannel, $supportChannel);
        $ami->close();

        if ((bool) $bridgeResult['success']) {
            echo json_encode(['success' => true, 'output' => $bridgeResult]);
        } else {
            json_error("Bridge failed: " . $bridgeResult['output'], 500);
        }
        break;

    case 'end_call':
        // decode and validate url inputs
        $data = [];
        $cols = ['sessiontime', 'sessionbill', 'buycost', 'terminatecauseid', 'uniqueid', 'callChannel', 'supportChannel'];
        foreach ($cols as $col) {
            $data[$col] = sanitizeText($_POST[$col] ?? null);
            if (!$data[$col] || empty($data[$col])) {
                json_error('Missing required field(s)');
            }
        }
        $callChannel = $data['callChannel'];
        $supportChannel = $data['supportChannel'];
        unset($data['callChannel'], $data['supportChannel']);
        // shell execute
        // $call_end = call_end($callChannel);
        // // check execution
        // if ($call_end['status'] !== 0) {
        //     json_error("Shell script failed: " . json_encode($call_end['output']));
        // }
        // use AMI interface for call hangup
        $ami = new AsteriskClient();
        $collector = [];
        foreach (['callChannel' => $callChannel, 'supportChannel' => $supportChannel] as $key => $channel) {
            $status = $ami->getChannelStatus($channel);
            $hangup = $ami->hangupChannel($channel);
            if ($hangup->getKey('Response') != 'Success' && $status != 'ended') {
                $collector[] = "$key - Response: {$hangup->getKey('Response')} | Message: {$hangup->getKey('Message')}";
            }
        }
        $ami->close();
        if (!empty($collector)) {
            json_error(implode(", ", $collector));
        }
        // db update
        $db_output = cdr_update_data($pdo, $data);
        if (!$db_output['success']) {
            json_error($db_output['message']);
        }
        echo json_encode(['success' => true, 'output' => $hangup->getKey('Message')]);
        break;

    // case 'hold_call':
        // // IMPORTANT: don't overcomplicated stuff let the native VOIP Application to handle call hold/resume function
    //     break;

    case 'status_call':
        // decode and validate url inputs
        $fullChannel = sanitizeText($_POST['callChannel'] ?? null);
        if (!$fullChannel || empty($fullChannel)) {
            json_error('Missing required field(s)');
        }
        $channel = (string) explode(';', $fullChannel)[0]; // Strip the ;2 or ;1 suffix
        try {
            // use AMI php interface
            $ami = new AsteriskClient();
            $status = $ami->getChannelStatus($fullChannel);
            $ami->close();
            // status details
            $statusDetails = [
                'ring' => "Call is ringing at the destination (not yet answered)",
                'ringing' => "Call is ringing at the destination (not yet answered)",
                'up' => "Call is active and answered - media is flowing",
                'dialing' => "Asterisk is attempting to dial the destination",
                'busy' => "Destination is busy",
                'hangup' => "Call is in the process of being torn down",
                'ended' => "Channel no longer exists",
                'unknown' => "Couldn't parse state - fallback case",
            ];
            // Get DTMF from DB
            $stmt = $pdo->prepare("SELECT dtmf_input, updated_at FROM call_tracking WHERE channel LIKE :channel");
            $stmt->execute(['channel' => (string) $channel . '%']);
            $dtmf = (array) $stmt->fetch(PDO::FETCH_ASSOC);

            // Return JSON
            echo json_encode([
                'success' => true,
                'channel' => $channel,
                'status' => $status,
                'status_detail' => $statusDetails[$status] ?? null,
                'dtmf_input' => $dtmf['dtmf_input'] ?? null,
                'dtmf_updated_at' => $dtmf['updated_at'] ?? null,
                'dtmf_array' => $dtmf
            ]);
            break;
        } catch (\Throwable $th) {
            json_error("Error:x: " . $th->getMessage() . ' Line: ' . $th->getLine() . ' File:' . $th->getFile());
        }

    default:
        json_error("Invalid Action!", 403);
        break;
}

$pdo = null;
exit;
