<?php
require_once 'vendor/autoload.php';

use PAMI\Client\Impl\ClientImpl;
use PAMI\Message\Action\OriginateAction;
use PAMI\Message\Action\HangupAction;
use PAMI\Message\Action\StatusAction;
use PAMI\Message\Action\SetVarAction;
use PAMI\Message\Response\ResponseMessage;
use PAMI\Message\Event\OriginateResponseEvent;

class AsteriskClient
{
    private $client;

    public function __construct()
    {
        $this->client = new ClientImpl([
            'host' => '127.0.0.1',
            'port' => 5038,
            'username' => 'admin',
            'secret' => 'cxx_68e169ac7d0be',
            'connect_timeout' => 30,
            'read_timeout' => 30
        ]);
        $this->client->open();
    }

    /**
     * originate new Call via dial-plan
     * @param string $tech the channel driver
     * @param string $callerId Caller ID number
     * @param string $callerName Caller ID name
     * @param string $targetNumber endpoint or address you're calling
     * @param string $extension entry point to dial-plan
     * @param string $context dial-plan context where Asterisk looks for the extension.
     * @param array $variables {$key => $value, ...}
     * @return array{ami_response: null, channel: null, debug: string[], error: string, success: bool|array{ami_response: string|string[], channel: mixed, debug: string[], error: string|null, success: bool}}
     * @author Thimira Dilshan <thimirad865@gmail.com>
     */
    public function originateCall(string $tech = 'SIP', string $callerId, string $callerName, string $targetNumber, string $extension = 's', string $context, $variables = [])
    {
        $debug = [];
        // Build channel and originate action
        $channel = (string) $tech . '/' . $targetNumber;
        $action = new OriginateAction($channel);
        $action->setContext($context);
        $action->setExtension($extension);
        $action->setPriority(1);
        $action->setCallerId(sprintf('%s <%s>', $callerName, $callerId));
        $action->setAsync(true);
        $action->setTimeout(30000);

        // Ensure CALL_TAG exists (prefer caller-supplied; otherwise create here)
        if (!isset($variables['CALL_TAG']) || !$variables['CALL_TAG']) {
            $variables['CALL_TAG'] = 'tag_' . uniqid();
            $debug[] = "CALL_TAG not provided, generated {$variables['CALL_TAG']}";
        } else {
            $debug[] = "CALL_TAG provided {$variables['CALL_TAG']}";
        }

        // Set variables on action (call-tag first ensures present at channel create)
        $action->setVariable('CALL_TAG', $variables['CALL_TAG']);
        foreach ($variables as $k => $v) {
            if ($k === 'CALL_TAG')
                continue;
            $action->setVariable($k, $v);
        }
        $debug[] = "OriginateAction prepared: channel={$channel}, context={$context}, extension={$extension}, caller={$callerId}";

        // Send originate - catch any immediate exceptions
        try {
            $response = $this->client->send($action);
            $debug[] = "OriginateAction sent; raw response captured";
        } catch (\Exception $e) {
            $debug[] = "Exception on send(OriginateAction): " . $e->getMessage();
            return ['success' => false, 'ami_response' => null, 'channel' => null, 'error' => $e->getMessage(), 'debug' => $debug];
        }

        // Normalize AMI response for debug
        $amiResp = is_object($response) && method_exists($response, 'getKeys') ? $response->getKeys() : (string) $response;
        $debug[] = 'AMI originate response: ' . json_encode($amiResp);

        // Poll StatusAction for the channel; prefer CALL_TAG match, fallback to caller id / connected line
        $foundChannel = null;
        $callTag = $variables['CALL_TAG'];
        $attempt = 0;
        $maxAttempts = 40; // 40 * 200ms = 8s
        while ($attempt < $maxAttempts && !$foundChannel) {
            $attempt++;
            usleep(200000); // 200ms
            try {
                $statusResponse = $this->client->send(new StatusAction());
            } catch (\Exception $e) {
                $debug[] = "StatusAction send exception at attempt {$attempt}: " . $e->getMessage();
                continue;
            }
            $debug[] = "StatusAction returned events count: " . count($statusResponse->getEvents()) . " (attempt {$attempt})";

            foreach ($statusResponse->getEvents() as $evIndex => $event) {
                // Log event summary for first few events
                if ($attempt <= 2 && $evIndex < 6) {
                    $debug[] = "Event[{$evIndex}] keys: " . json_encode($event->getKeys());
                }

                $evChannel = $event->getKey('Channel');
                // Variables may be exposed in 'Variables' key (array) or as getKeys() entries depending on AMI
                $evVars = $event->getKey('Variables');
                if (!$evVars || !is_array($evVars)) {
                    // fall back to getKeys() but not recommended as canonical
                    $evKeys = $event->getKeys();
                    $evVars = isset($evKeys['Variables']) && is_array($evKeys['Variables']) ? $evKeys['Variables'] : $evKeys;
                }

                // 1) Primary matching: CALL_TAG inside Variables map
                if ($evChannel && is_array($evVars) && isset($evVars['CALL_TAG']) && $evVars['CALL_TAG'] === $callTag) {
                    $foundChannel = $evChannel;
                    $debug[] = "Matched by CALL_TAG on event {$evIndex}: channel={$foundChannel}";
                    break 2;
                }

                // 2) Secondary matching: CallerID / ConnectedLine / Exten
                $evCaller = $event->getKey('CallerIDNum') ?: $event->getKey('CallerIDNum') . '';
                $evConnected = $event->getKey('ConnectedLineNum');
                $evExten = $event->getKey('Exten');

                if ($evChannel && (($evCaller !== null && (string) $evCaller === (string) $callerId) || ($evConnected !== null && (string) $evConnected === (string) $targetNumber) || ($evExten !== null && (string) $evExten === (string) $targetNumber))) {
                    $foundChannel = $evChannel;
                    $debug[] = "Matched by fallback (CallerID/ConnectedLine/Exten) on event {$evIndex}: channel={$foundChannel}; evCaller={$evCaller}; evConnected={$evConnected}; evExten={$evExten}";
                    break 2;
                }
            } // foreach events
        } // while attempts

        if (!$foundChannel) {
            $debug[] = "Channel not found after {$attempt} attempts. Last callTag={$callTag}";
        }

        return [
            'success' => (bool) $foundChannel,
            'ami_response' => $amiResp,
            'channel' => $foundChannel,
            'error' => $foundChannel ? null : 'Channel not discovered',
            'debug' => $debug
        ];
    }


    /**
     * Store a value in Asterisk AstDB (database put)
     * @param mixed $family
     * @param mixed $key
     * @param mixed $value
     * @return array{error: null, response: string|string[], success: bool|array{error: string, response: null, success: bool}}
     * @author Thimira Dilshan <thimirad865@gmail.com>
     */
    public function astdbPut($family, $key, $value)
    {
        try {
            // Use AMI CommandAction to execute 'database put' which is universally supported
            $command = sprintf("database put %s %s %s", $family, $key, $value);
            $action = new \PAMI\Message\Action\CommandAction($command);
            $response = $this->client->send($action);

            // Normalize response
            if (is_object($response) && method_exists($response, 'getKeys')) {
                $resp = $response->getKeys();
            } else {
                $resp = (string) $response;
            }

            return ['success' => true, 'response' => $resp, 'error' => null];
        } catch (\Exception $e) {
            return ['success' => false, 'response' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Retrieve a value from Asterisk AstDB (database get)
     * @param mixed $family
     * @param mixed $key
     * @return array{error: null, response: string, success: bool, value: mixed|array{error: string, response: null, success: bool, value: null}}
     * @author Thimira Dilshan <thimirad865@gmail.com>
     */
    public function astdbGet($family, $key)
    {
        try {
            $command = sprintf('database get %s %s', $family, $key);
            $action = new \PAMI\Message\Action\CommandAction($command);
            $response = $this->client->send($action);

            $raw = is_object($response) && method_exists($response, 'getMessage') ? $response->getMessage() : (string) $response;

            // Try to parse "Response: Success\nValue: <value>" style messages
            $value = null;
            if (preg_match('/Value:\s*(.*)$/m', $raw, $m)) {
                $value = $m[1];
            }

            return ['success' => true, 'value' => $value, 'response' => $raw, 'error' => null];
        } catch (\Exception $e) {
            return ['success' => false, 'value' => null, 'response' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete a key from Asterisk AstDB (database del)
     * @param mixed $family
     * @param mixed $key
     * @return array{error: null, response: string, success: bool|array{error: string, response: null, success: bool}}
     * @author Thimira Dilshan <thimirad865@gmail.com>
     */
    public function astdbDel($family, $key)
    {
        try {
            $command = sprintf('database del %s %s', $family, $key);
            $action = new \PAMI\Message\Action\CommandAction($command);
            $response = $this->client->send($action);
            $resp = is_object($response) && method_exists($response, 'getMessage') ? $response->getMessage() : (string) $response;
            return ['success' => true, 'response' => $resp, 'error' => null];
        } catch (\Exception $e) {
            return ['success' => false, 'response' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * hangup call using chanel
     * @param string $channel
     * @return PAMI\Message\Response\ResponseMessage
     * @author Thimira Dilshan <thimirad865@gmail.com>
     */
    public function hangupChannel($channel)
    {
        $action = new HangupAction($channel);
        return $this->client->send($action);
    }

    /**
     * get call status using channel
     * @param string $channel
     * @return string
     * @author Thimira Dilshan <thimirad865@gmail.com>
     */
    public function getChannelStatus($channel)
    {
        $response = $this->client->send(new StatusAction());
        foreach ($response->getEvents() as $event) {
            if ($event->getKey('Channel') === $channel) {
                return strtolower($event->getKey('ChannelStateDesc'));
            }
        }
        return 'ended';
    }

    /**
     * Summary of twoLegConditionalCall
     * @param string $techSupport the channel driver
     * @param string $callerIdSupport Caller ID number
     * @param string $callerNameSupport Caller ID name
     * @param string $targetNumberSupport endpoint or address you're calling
     * @param string $extensionSupport entry point to dial-plan
     * @param string $contextSupport dial-plan context where Asterisk looks for the extension.
     * @param array $variablesSupport {$key => $value, ...}
     * @param string $techConfirm the channel driver
     * @param string $callerIdConfirm Caller ID number
     * @param string $callerNameConfirm Caller ID name
     * @param string $targetNumberConfirm endpoint or address you're calling
     * @param string $extensionConfirm entry point to dial-plan
     * @param string $contextConfirm dial-plan context where Asterisk looks for the extension.
     * @param array $variablesConfirm {$key => $value, ...}
     * @return array{channels_array: array{confirm_channel: mixed, support_channel: mixed, confirm: array{ami_response: array|string, channel: null, error: null, success: array|bool}|array{debug: mixed}, debug: string[], error: string|null, success: array, support: array{ami_response: array|string, channel: null, error: null, success: array|bool}|array{debug: mixed}}|array{confirm: null, debug: string[], error: string, success: bool, support: array{ami_response: array|string, channel: null, error: null, success: array|bool}|array{debug: mixed}}}
     * @author Thimira Dilshan <thimirad865@gmail.com>
     */
    public function twoLegConditionalCall(
        string $techSupport = 'SIP',
        string $callerIdSupport,
        string $callerNameSupport,
        string $targetNumberSupport,
        string $extensionSupport = 's',
        string $contextSupport,
        array $variablesSupport = [],
        string $techConfirm = 'SIP',
        string $callerIdConfirm,
        string $callerNameConfirm,
        string $targetNumberConfirm,
        string $extensionConfirm = 's',
        string $contextConfirm,
        array $variablesConfirm = []
    ) {
        $debug = [];

        // Step 1: Originate support leg only
        $variablesSupport['CALL_TAG'] = 'tag_support_' . uniqid();
        $debug[] = "Support originate: CALL_TAG={$variablesSupport['CALL_TAG']}";
        $supportResult = $this->originateCall(
            $techSupport,
            $callerIdSupport,
            $callerNameSupport,
            $targetNumberSupport,
            $extensionSupport,
            $contextSupport,
            $variablesSupport
        );
        if (isset($supportResult['debug'])) {
            $debug = array_merge($debug, $supportResult['debug']);
        }

        // Step 2: Discover support channel by pattern
        $supportChannel = null;
        $elapsed = 0;
        $maxWait = 8000; // ms
        $interval = 250;
        $debug[] = "Waiting for support channel (SIP/ldata-)";

        while ($elapsed < $maxWait && !$supportChannel) {
            usleep($interval * 1000);
            $elapsed += $interval;

            try {
                $statusResponse = $this->client->send(new StatusAction());
            } catch (\Exception $e) {
                $debug[] = "StatusAction exception: " . $e->getMessage();
                continue;
            }

            foreach ($statusResponse->getEvents() as $event) {
                $channel = $event->getKey('Channel');
                if ($channel && strpos($channel, 'SIP/ldata-') === 0) {
                    $supportChannel = $channel;
                    $debug[] = "Matched support channel: {$supportChannel}";
                    break;
                }
            }
        }

        if (!$supportChannel) {
            $debug[] = "Support channel not found after {$elapsed}ms";
            return [
                'success' => false,
                'error' => 'Support leg channel not found',
                'support' => $supportResult,
                'confirm' => null,
                'debug' => $debug
            ];
        }

        // Step 3: Wait for support agent to answer
        $maxWaitSec = 60;
        $polled = 0;
        $status = 'unknown';
        while ($polled < $maxWaitSec) {
            $polled += 2;
            sleep(2);
            try {
                $status = $this->getChannelStatus($supportChannel);
            } catch (\Exception $e) {
                $debug[] = "getChannelStatus exception: " . $e->getMessage();
                $status = 'error';
            }
            $debug[] = "Polled support channel state (after {$polled}s): {$status}";
            if ($status === 'up')
                break;
        }

        if ($status !== 'up') {
            $debug[] = "Support agent did not answer, hanging up supportChannel={$supportChannel}";
            try {
                $this->hangupChannel($supportChannel);
                $debug[] = "Hangup sent to {$supportChannel}";
            } catch (\Exception $e) {
                $debug[] = "Hangup exception: " . $e->getMessage();
            }
            return [
                'success' => false,
                'error' => 'Support agent did not answer in time',
                'support' => $supportResult,
                'confirm' => null,
                'debug' => $debug
            ];
        }

        // Step 4: Originate customer leg after support is ready
        $variablesConfirm['SUPPORT_CHANNEL'] = $supportChannel;
        $variablesConfirm['CALL_TAG'] = 'tag_confirm_' . uniqid();
        $debug[] = "Customer originate: CALL_TAG={$variablesConfirm['CALL_TAG']}, SUPPORT_CHANNEL={$supportChannel}";
        $confirmResult = $this->originateCall(
            $techConfirm,
            $callerIdConfirm,
            $callerNameConfirm,
            $targetNumberConfirm,
            $extensionConfirm,
            $contextConfirm,
            $variablesConfirm
        );
        if (isset($confirmResult['debug'])) {
            $debug = array_merge($debug, $confirmResult['debug']);
        }

        // Step 5: Discover customer channel by pattern
        $confirmChannel = null;
        $elapsed = 0;
        $debug[] = "Waiting for confirm channel (SIP/2002-)";

        while ($elapsed < $maxWait && !$confirmChannel) {
            usleep($interval * 1000);
            $elapsed += $interval;

            try {
                $statusResponse = $this->client->send(new StatusAction());
            } catch (\Exception $e) {
                $debug[] = "StatusAction exception: " . $e->getMessage();
                continue;
            }

            foreach ($statusResponse->getEvents() as $event) {
                $channel = $event->getKey('Channel');
                if ($channel && strpos($channel, 'SIP/2002-') === 0) {
                    $confirmChannel = $channel;
                    $debug[] = "Matched confirm channel: {$confirmChannel}";
                    break;
                }
            }
        }

        // Step 6: Safety check
        if ($confirmChannel === $supportChannel) {
            $debug[] = "Safety check failed: confirm channel equals support channel ({$supportChannel})";
            $confirmChannel = null;
            $confirmResult['success'] = false;
            $confirmResult['error'] = 'Customer leg matched support leg - invalid';
        }

        $debug[] = "Flow complete: support_channel={$supportChannel}, confirm_channel={$confirmChannel}";
        return [
            'success' => $confirmResult['success'] ?? false,
            'support' => array_merge($supportResult, ['channel' => $supportChannel]),
            'confirm' => array_merge($confirmResult, ['channel' => $confirmChannel]),
            'channels_array' => [
                'confirm_channel' => $confirmChannel,
                'support_channel' => $supportChannel
            ],
            'error' => $confirmResult['success'] ? null : 'Customer leg failed',
            'debug' => $debug
        ];
    }



    /**
     * set var in active dial-plan
     * @param mixed $channel
     * @return array{output: string, success: bool}
     * @author Thimira Dilshan <thimirad865@gmail.com>
     */
    public function setVarInAction($channel)
    {
        try {
            $action = new SetVarAction($channel, 'BRIDGE_PERMIT', 'true');
            $response = $this->client->send($action);

            if ($response instanceof ResponseMessage && $response->isSuccess()) {
                // Optional: log success
                return ['success' => true, 'output' => "SetVar success for channel: $channel"];
            } else {
                // Optional: log failure reason
                return ['success' => false, 'output' => "SetVar failed for channel: $channel. Message: " . $response->getMessage()];
            }
        } catch (\Exception $e) {
            // Log exception details
            return ['success' => false, 'output' => "SetVar exception for channel: $channel. Error: " . $e->getMessage()];
        }
    }

    /**
     * Summary of bridgeChannels
     * @param string $channel1
     * @param string $channel2
     * @return array{output: string, success: bool}
     * @author Thimira Dilshan <thimirad865@gmail.com>
     */
    public function bridgeChannels(string $channel1, string $channel2): array
    {
        $channel1 = str_replace('\\/', '/', $channel1);
        $channel2 = str_replace('\\/', '/', $channel2);

        try {
            $action = new \PAMI\Message\Action\BridgeAction($channel1, $channel2);
            $response = $this->client->send($action);

            if ($response->isSuccess()) {
                return ['success' => true, 'output' => 'Bridge succeeded'];
            }

            // Retry once if timeout
            if (stripos($response->getMessage(), 'timeout') !== false) {
                usleep(500000); // wait 0.5s
                $retry = $this->client->send($action);

                if ($retry->isSuccess()) {
                    return ['success' => true, 'output' => 'Bridge succeeded on retry'];
                }

                return ['success' => false, 'output' => 'Bridge retry failed: ' . $retry->getMessage()];
            }

            return ['success' => false, 'output' => 'Bridge failed: ' . $response->getMessage()];
        } catch (\Exception $e) {
            return ['success' => false, 'output' => 'Bridge exception: ' . $e->getMessage()];
        }
    }



    /**
     * close PAMI instance
     * @return void
     * @author Thimira Dilshan <thimirad865@gmail.com>
     */
    public function close()
    {
        $this->client->close();
    }
}
