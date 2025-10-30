<?php
require_once 'vendor/autoload.php';

use PAMI\Client\Impl\ClientImpl;
use PAMI\Message\Action\OriginateAction;
use PAMI\Message\Action\HangupAction;
use PAMI\Message\Action\StatusAction;

class AsteriskClient {
    private $client;

    public function __construct() {
        $this->client = new ClientImpl([
            'host' => '127.0.0.1',
            'port' => 5038,
            'username' => 'admin',
            'secret' => 'cxx_68e169ac7d0be',
            'connect_timeout' => 10,
            'read_timeout' => 10
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
     * @return array{ami_response: array|string, channel: null, error: null, success: bool|array{ami_response: array|string, channel: string|null, error: null, success: bool}|array{ami_response: null, channel: null, error: string, success: bool}|array{ami_response: string[], channel: null, error: null, success: bool}}
     * @author Thimira Dilshan <thimirad865@gmail.com>
     */
    public function originateCall(string $tech = 'SIP', string $callerId, string $callerName, string $targetNumber, string $extension = 's', string $context)
    {
        try {
            // Build originate action correctly
            $channel = (string) $tech .'/'. $targetNumber;
            $action = new OriginateAction($channel);
            // Set a context/extension/priority that matches your dialplan for outbound calls
            // Set originate parameters (separate calls to avoid analyzer issues)
            $action->setContext($context);
            if (method_exists($action, 'setExtension')) {
                $action->setExtension($extension);
            } elseif (method_exists($action, 'setExten')) {
                $m = 'setExten';
                $action->$m($targetNumber);
            }
            if (method_exists($action, 'setPriority')) {
                $action->setPriority(1);
            }
            if (method_exists($action, 'setCallerId')) {
                $action->setCallerId(sprintf('%s <%s>', $callerName, $callerId));
            } elseif (method_exists($action, 'setCallerID')) {
                $m = 'setCallerID';
                $action->$m(sprintf('%s <%s>', $callerName, $callerId));
            }
            if (method_exists($action, 'setAsync')) {
                $action->setAsync(true);
            }
            if (method_exists($action, 'setTimeout')) {
                $action->setTimeout(30000);
            }
            if (method_exists($action, 'setActionId')) {
                $action->setActionId('orig_' . uniqid());
            }

            $response = $this->client->send($action);

            // Try to discover the channel created for this originate. We'll poll StatusAction briefly.
            $foundChannel = null;
            $attempts = 8;
            for ($i = 0; $i < $attempts; $i++) {
                usleep(200000); // 200ms
                $statusResponse = $this->client->send(new StatusAction());
                foreach ($statusResponse->getEvents() as $event) {
                    // Try several keys that may identify the channel
                    $evChannel = $event->getKey('Channel');
                    $evCallerNum = $event->getKey('CallerIDNum');
                    $evCallerName = $event->getKey('CallerIDName');
                    $evConnectedLine = $event->getKey('ConnectedLineNum');
                    $evExten = $event->getKey('Exten');

                    if ($evChannel) {
                        // match by caller id OR connected line OR extension
                        if ((string)$evCallerNum === (string)$callerId || (string)$evConnectedLine === (string)$targetNumber || (string)$evExten === (string)$targetNumber) {
                            $foundChannel = $evChannel;
                            break 2;
                        }
                        // match by caller name + number
                        if ($evCallerName && strpos($evCallerName, $callerName) !== false && $evCallerNum == $callerId) {
                            $foundChannel = $evChannel;
                            break 2;
                        }
                    }
                }
            }

            // Normalize AMI response
            $amiResp = null;
            if (is_object($response) && method_exists($response, 'getKeys')) {
                $amiResp = $response->getKeys();
            } elseif (is_array($response)) {
                $amiResp = $response;
            } else {
                $amiResp = (string)$response;
            }

            return [
                'success' => true,
                'ami_response' => $amiResp,
                'channel' => $foundChannel,
                'error' => null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'ami_response' => null,
                'channel' => null,
                'error' => $e->getMessage()
            ];
        }
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
            $command = sprintf('database put %s %s %s', $family, $key, $value);
            $action = new \PAMI\Message\Action\CommandAction($command);
            $response = $this->client->send($action);

            // Normalize response
            if (is_object($response) && method_exists($response, 'getKeys')) {
                $resp = $response->getKeys();
            } else {
                $resp = (string)$response;
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

            $raw = is_object($response) && method_exists($response, 'getMessage') ? $response->getMessage() : (string)$response;

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
            $resp = is_object($response) && method_exists($response, 'getMessage') ? $response->getMessage() : (string)$response;
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
    public function hangupChannel($channel) {
        $action = new HangupAction($channel);
        return $this->client->send($action);
    }

    /**
     * get call status using channel
     * @param string $channel
     * @return string
     * @author Thimira Dilshan <thimirad865@gmail.com>
     */
    public function getChannelStatus($channel) {
        $response = $this->client->send(new StatusAction());
        foreach ($response->getEvents() as $event) {
            if ($event->getKey('Channel') === $channel) {
                return strtolower($event->getKey('ChannelStateDesc'));
            }
        }
        return 'ended';
    }

    public function close() {
        $this->client->close();
    }
}
