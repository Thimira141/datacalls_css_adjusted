<?php
namespace inc\classes\GoogleTTS;

require_once dirname(__DIR__, 3) . '/config.php';

use Google\Cloud\TextToSpeech\V1\AudioConfig;
use Google\Cloud\TextToSpeech\V1\AudioEncoding;
use Google\Cloud\TextToSpeech\V1\SynthesisInput;
use Google\Cloud\TextToSpeech\V1\Client\TextToSpeechClient;
use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;
use Google\Cloud\TextToSpeech\V1\SynthesizeSpeechRequest;

error_reporting(E_ALL);

class GoogleTTSService
{
    private TextToSpeechClient $client;
    protected string $filename;

    public function __construct()
    {
        $credentialsPath = __DIR__ . '/Authentication/google-tts-api-credentials.json';
        putenv("GOOGLE_APPLICATION_CREDENTIALS={$credentialsPath}");
        $this->client = new TextToSpeechClient();
    }

    /**
     * build the SSML for tts using template
     * @param string $institution
     * @param string $customer
     * @param float $amount
     * @param string $merchant
     * @return string
     * @author Thimira Dilshan <thimirad865@gmail.com>
     */
    public function buildSSML(string $institution, string $customer, float $amount, string $merchant)
    {
        return (string) "<speak>" .
            "Hello, this is <emphasis level=\"moderate\">$institution</emphasis> calling for " .
            "<emphasis level=\"moderate\">$customer</emphasis> regarding a " .
            "<emphasis level=\"strong\">security matter</emphasis> with your account. " .
            "<break time=\"500ms\"/>" .
            "We’ve detected a recent transaction of <say-as interpret-as=\"currency\" language=\"en-US\">\$$amount</say-as> at " .
            "<emphasis level=\"moderate\">$merchant</emphasis> that may be <emphasis level=\"strong\">unauthorized</emphasis>. " .
            "<break time=\"700ms\"/>" .
            "If you recognize and authorized this transaction, please press <say-as interpret-as=\"digits\">1</say-as>. " .
            "<break time=\"500ms\"/>" .
            "If you did not authorize this transaction or would like to speak with a representative, please press <say-as interpret-as=\"digits\">2</say-as> now. " .
            "<break time=\"500ms\"/>" .
            "To repeat this message, press <say-as interpret-as=\"digits\">3</say-as>." .
            "</speak>"
        ;
    }

    /**
     * get the audio file url
     * @return string
     * @author Thimira Dilshan <thimirad865@gmail.com>
     */
    public function getFileURL()
    {
        global $config;

        return $config->app->url . '/storage/audio/' . $this->filename;
    }

    /**
     * Summary of synthesize
     * @param string $ssml_script SSML script
     * @throws \RuntimeException
     * @return bool
     * @author Thimira Dilshan <thimirad865@gmail.com>
     */
    public function synthesize(string $ssml_script): bool
    {
        // create file name
        $outputDir = dirname(__DIR__, 3) . '/storage/audio';
        $this->filename = 'tts__' . date('Y_m_d_H_i_s') . '__' . uniqid() . '.mp3';
        $fullFilePath = (string) $outputDir . '/' . $this->filename;

        try {
            // file destination
            if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true)) {
                $this->tts_log("[ERROR] Failed to create output directory: $outputDir");
                throw new \RuntimeException("Failed to create output directory: $outputDir");
            }

            $this->tts_log("[NOTICE] Starting synthesis process...");

            // SSML input set.
            $input = new SynthesisInput();
            $input->setSsml($ssml_script);
            $this->tts_log("[NOTICE] SSML input set.");

            // Voice parameters set: en-US-Wavenet-D.
            $voice = new VoiceSelectionParams();
            $voice->setLanguageCode('en-US');
            $voice->setName('en-US-Wavenet-D');
            $this->tts_log("[NOTICE] Voice parameters set: en-US-Wavenet-D.");

            // Audio config set: MP3 encoding.
            $audioConfig = new AudioConfig();
            $audioConfig->setAudioEncoding(AudioEncoding::MP3);
            $this->tts_log("[NOTICE] Audio config set: MP3 encoding.");

            // SynthesizeSpeechRequest built.
            $request = (new SynthesizeSpeechRequest())
                ->setInput($input)
                ->setVoice($voice)
                ->setAudioConfig($audioConfig);
            $this->tts_log("[NOTICE] SynthesizeSpeechRequest built.");

            // Speech synthesized, response received.
            $response = $this->client->synthesizeSpeech($request);
            $this->tts_log("[NOTICE] Speech synthesized, response received.");

            // Audio content return check
            if (empty($response->getAudioContent())) {
                $this->tts_log("[ERROR] Synthesis failed: empty response.");
                throw new \RuntimeException("No audio content returned from Google TTS.");
            }
            $this->tts_log("[NOTICE] Audio content returned from Google TTS.");

            // Write audio file: {$fullFilePath}
            if (file_put_contents($fullFilePath, $response->getAudioContent()) === false) {
                $this->tts_log("[ERROR] Failed to write audio file: {$fullFilePath}");
                throw new \RuntimeException("Failed to write audio file: {$fullFilePath}");
            }
            $this->tts_log("[NOTICE] Write audio file: {$fullFilePath}");

            // return file exists state
            return file_exists($fullFilePath);
        } catch (\Throwable $e) {
            $this->tts_log("[ERROR] Synthesis failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * close connection
     * @return void
     * @author Thimira Dilshan <thimirad865@gmail.com>
     */
    public function close(): void
    {
        $this->client->close();
    }

    /**
     * neatly log the notice/errors into log file
     * @param string $message
     * @return void
     * @author Thimira Dilshan <thimirad865@gmail.com>
     */
    private function tts_log(string $message): void
    {
        error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, __DIR__ . "/tts_log.log");
    }

}
