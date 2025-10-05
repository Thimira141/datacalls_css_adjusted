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

    public function originateCall($userId, $callerId, $callerName, $targetNumber) {
        $action = new OriginateAction('SIP/' . $targetNumber);
        // FIXME:$action->setContext('from-user')
        //        ->setExtension($targetNumber)
        //        ->setPriority(1)
        //        ->setCallerId("$callerName <$callerId>")
        //        ->setAsync(true)
        //        ->setVariable('id_user', $userId);

        $response = $this->client->send($action);
        return $response;
    }

    public function hangupChannel($channel) {
        $action = new HangupAction($channel);
        return $this->client->send($action);
    }

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
