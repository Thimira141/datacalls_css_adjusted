<?php
namespace inc\classes;
/**
 * File: CallManager.php
 * Description: Handles outbound calls from server -> client via API.
 * @author thimira dilshan <thimirad865@gmail.com>
 */

error_reporting(boolval(env('APP_DEBUG')) ? E_ALL : E_ERROR);

class CallManager
{
    /**
     * Initiate a call via MagnusBilling or external API (using cURL)
     * @param array $data ['id_user', 'id_plan', 'calledstation', 'callerid', 'starttime', 'stoptime', 'sessiontime', 'sessionbill', 'buycost', 'uniqueid']
     * @param string $callerName name display on call
     * @return array ['success'=>bool, 'response'=>mixed, 'error'=>string|null]
     */
    public static function callNumber($data, $callerName = 'support')
    {
        self::x_log('Starting Calling...');
        // ['id_user', 'id_plan', 'calledstation', 'callerid', 'starttime', 'stoptime', 'sessiontime', 'sessionbill', 'buycost', 'uniqueid']
        return self::makeCurl(array_merge(['callerName' => $callerName, 'action' => 'make_call'], $data));
    }

    /**
     * End the initiated call using call channel
     * @param array $data ['stoptime', 'sessiontime', 'sessionbill', 'buycost', 'terminatecauseid', 'uniqueid', 'callChannel']
     * @return array ['success'=>bool, 'response'=>mixed, 'error'=>string|null]
     * @author Thimira Dilshan <thimirad865@gmail.com>
     */
    public static function endCall(array $data)
    {
        self::x_log("Starting Call Ending...");
        return self::makeCurl(array_merge(['action' => 'end_call'], $data));
    }

    /**
     * Retrieve call status form server with dtmf info
     * @param string $callChannel
     * @return array ['success' => bool, 'channel' => string, 'status' => string,
     *  'status_detail' => string | null, 'dtmf_input' => string | null, 'dtmf_updated_at' => string | null ]
     * @author Thimira Dilshan <thimirad865@gmail.com>
     */
    public static function getCallStat(string $callChannel)
    {
        self::x_log("Starting get call stat...");
        return self::makeCurl(['callChannel' => $callChannel, 'action' => 'status_call']);
    }

    /**
     * make a curl request and handle server to client API connection
     * @param array $data data passed to the API
     * @return array
     */
    private static function makeCurl(array $data = [])
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => env('MAGNUS_PUBLIC_URL') . '/api/call/index.php',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . password_hash(env('MAGNUS_TTS_API_KEY'), PASSWORD_DEFAULT)
            ]
        ]);

        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        self::x_log(json_encode($data));
        self::x_log($response);
        self::x_log($curlError);
        self::x_log($httpCode);
        if ($curlError) {
            return [
                'success' => false,
                'response' => null,
                'error' => $curlError
            ];
        }
        if ($httpCode !== 200) {
            return [
                'success' => false,
                'response' => $response,
                'error' => "HTTP Error: $httpCode"
            ];
        }
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'response' => $response,
                'error' => 'Invalid JSON response.'
            ];
        }
        return [
            'success' => isset($result['success']) && $result['success'] == true,
            'response' => $result,
            'error:x:x:' => isset($result['error']) ? $result['error'] : null
        ];
    }

    /**
     * neatly log the notice/errors into log file
     * @param string $message
     * @param int $type message type
     * @return void
     * @author Thimira Dilshan <thimirad865@gmail.com>
     */
    private static function x_log(string $message, $type = 3): void
    {
        error_log(date('[Y-m-d H:i:s] ') . $message . "\n", $type, __DIR__ . "/call_api_connection.log");
    }
}