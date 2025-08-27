<?php
namespace inc\classes\GoogleTTS;
/**
 * File: TtsAudioApiConnection.php
 * Description: Handles audio file uploads to Google TTS API.
 * @author thimira dilshan <thimirad865@gmail.com>
 */

require_once dirname(__DIR__, 3) . '/config.php';

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
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => env('MAGNUS_PUBLIC_URL').'/api/tts',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
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

        return [
            'success' => true,
            'response' => $response,
            'error' => null
        ];
    }
}

