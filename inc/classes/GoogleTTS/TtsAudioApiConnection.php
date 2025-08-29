<?php
namespace inc\classes\GoogleTTS;
/**
 * File: TtsAudioApiConnection.php
 * Description: Handles audio file uploads to Google TTS API.
 * @author thimira dilshan <thimirad865@gmail.com>
 */

require_once dirname(__DIR__, 3) . '/config.php';

error_reporting(boolval(env('APP_DEBUG')) ? E_ALL : E_ERROR);

class TtsAudioApiConnection
{
    /**
     * Uploads an audio file to the TTS API.
     *
     * @param string $customer_number
     * @param string $user_id
     * @param string $ivr_id
     * @param string $localMp3Path
     * @return array ['success' => bool, 'response' => mixed, 'error' => string|null]
     */
    public static function uploadAudio($customer_number, $user_id, $ivr_id, $localMp3Path)
    {
        self::tts_log('Starting Audio Upload', E_NOTICE);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => env('MAGNUS_PUBLIC_URL').'/api/tts/index.php',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data = [
                'customer_number' => $customer_number,
                'user_id' => $user_id,
                'ivr_id' => $ivr_id,
                'audio_file' => new \CURLFile($localMp3Path)
            ],
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . password_hash(env('MAGNUS_TTS_API_KEY'), PASSWORD_DEFAULT)
            ]
        ]);

        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        self::tts_log('Curl data' . json_encode($data), E_NOTICE);
        self::tts_log('Curl complete', E_NOTICE);
        if ($curlError) {
            self::tts_log('Curl error: ' . json_encode($curlError), E_ERROR);
            return [
                'success' => false,
                'response' => null,
                'error' => $curlError
            ];
        }

        if ($httpCode !== 200) {
            self::tts_log('error http: '. $httpCode . ': ' . json_encode($response), E_ERROR);
            return [
                'success' => false,
                'response' => $response,
                'error' => "HTTP Error: $httpCode"
            ];
        }

        self::tts_log('success: ' . json_encode($response), E_NOTICE);
        return [
            'success' => true,
            'response' => $response,
            'error' => null
        ];
    }

    /**
     * neatly log the notice/errors into log file
     * @param string $message
     * @param int $type message type
     * @return void
     * @author Thimira Dilshan <thimirad865@gmail.com>
     */
    private static function tts_log(string $message, $type=3): void
    {
        error_log(date('[Y-m-d H:i:s] ') . $message . "\n", $type, __DIR__ . "/tts_api_connection.log");
    }
}

