<?php
namespace inc\classes;
/**
 * MenuAPI
 * Client wrapper for menuapi in magnusbilliong server
 * 
 * @author thimira dilshan <thimirad865@gmail.com>
 * @LastUpdated 2025-11-05
 */

require_once __DIR__ . '/../../config.php';

error_reporting(boolval(env('APP_DEBUG')) ? E_ALL : E_ERROR);

final class MenuAPI
{
    /**
     * Get modules information from server
     * @return array
     * @author Thimira Dilshan <thimirad865@gmail.com>
     */
    public static function getModules()
    {
        return self::makeCurl('index', ['option'=>'get_modules']);
    }

    /**
     * Get SIP user information from server
     * @param string $id_user
     * @return array
     * @author Thimira Dilshan <thimirad865@gmail.com>
     */
    public static function getSIPUser(string $id_user)
    {
        return self::makeCurl('sip', ['id_user' => $id_user, 'option'=>'get_sip_user']);
    }

    /**
     * make a curl request and handle server to client API connection
     * @param string $module module name(php script file name)
     * @param array $data data passed to the API
     * @return array
     */
    private static function makeCurl($module, array $data = [])
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => env('MAGNUS_PUBLIC_URL') . '/api/menuapi/'.$module.'.php',
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
            'error' => isset($result['error']) ? $result['error'] : null
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
        error_log(date('[Y-m-d H:i:s] ') . $message . "\n", $type, __DIR__ . "/menu_api_remote.log");
    }
}
