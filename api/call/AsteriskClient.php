<?php
require_once 'vendor/autoload.php';
require_once 'ChannelRegistry.php';

use PAMI\Client\Impl\ClientImpl;
use PAMI\Message\Action\OriginateAction;
use PAMI\Message\Action\HangupAction;
use PAMI\Message\Action\StatusAction;

class AsteriskClient
{
    private $client;
    private $originateMap = []; // actionId => ['channel' => ..., 'uniqueid' => ...]

    public function __construct()
    {
        $this->client = new ClientImpl([
            'host' => '127.0.0.1',
            'port' => 5038,
            'username' => 'admin',
            'secret' => 'cxx_68e169ac7d0be',
            'connect_timeout' => 10,
            'read_timeout' => 10
        ]);
        $this->client->open();

        // Listen for OriginateResponse events
        $this->client->registerEventListener(function ($event) {
            if ($event->getName() === 'OriginateResponse') {
                $actionId = $event->getKey('ActionID');
                $channel = $event->getKey('Channel');
                $uniqueid = $event->getKey('Uniqueid');
                $status = $event->getKey('Response');
                $reason = $event->getKey('Reason');

                // Store in instance map (optional)
                $this->originateMap[$actionId] = [
                    'channel' => $channel,
                    'uniqueid' => $uniqueid,
                    'status' => $status,
                    'reason' => $reason
                ];

                // Also register globally
                ChannelRegistry::register($actionId, $channel, $uniqueid, $status, $reason);
            }
        });
    }

    public function originateCall($userId, $callerId, $callerName, $targetNumber, $context, $callbackDest, $customerName, $customerNum)
    {
        $action = new OriginateAction("Local/$targetNumber@$context");
        $action->setContext($context);
        $action->setExtension($targetNumber);
        $action->setPriority(1);
        $action->setCallerId("$callerName <$callerId>");
        $action->setAsync(true);

        // pass variables
        $action->setVariable('id_user', $userId);
        $action->setVariable('callback_destination', $callbackDest);
        $action->setVariable('customer_name', $customerName);
        $action->setVariable('customer_num', $customerNum);

        $response = $this->client->send($action);
        return [
            'actionID' => $response->getActionID(),
            'Message' => $response->getKey('Message'),
            'Response' => $response->getKey('Response')
        ]; // return ActionID immediately
    }

    //  Function to resolve ActionID → Channel/Uniqueid
    public function getChannelByActionId($actionId, $timeout = 3)
    {
        $deadline = microtime(true) + $timeout;
        while (microtime(true) < $deadline) {
            $this->client->process(); // dispatch events
            if (isset($this->originateMap[$actionId])) {
                return $this->originateMap[$actionId]['channel'];
            }
            usleep(100000);
        }
        return null; // still not known
    }

    public function hangupChannel($channel)
    {
        $action = new HangupAction($channel);
        return $this->client->send($action);
    }

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

    public function close()
    {
        $this->client->close();
    }
}
