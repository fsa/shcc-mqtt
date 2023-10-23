<?php

namespace FSA\MQTT;

use FSA\SmartHome\DaemonInterface;
use PhpMqtt\Client\MqttClient;

class Daemon implements DaemonInterface
{
    const DAEMON_NAME = 'MQTT';

    private $events_callback;
    private $server;
    private $port;
    private $client_id;
    private $mqtt;
    private $username;
    private $password;
    private $topics;

    public function __construct($events, $params)
    {
        $this->events_callback = $events;
        $this->server = $params['server_ip'];
        $this->port = $params['port'];
        $this->client_id = $params['client_id'];
        $this->username = $params['username'];
        $this->password = $params['password'];
        $this->topics = $params['topics'];
    }

    public function getName()
    {
        return self::DAEMON_NAME;
    }

    public function prepare()
    {
        $this->mqtt = new MqttClient($this->server, $this->port, $this->client_id);
        $connectionSettings = (new \PhpMqtt\Client\ConnectionSettings)
            ->setUsername($this->username)
            ->setPassword($this->password);
        $this->mqtt->connect($connectionSettings, true);
        $closure = function ($topic, $message, $retained, $matchedWildcards) {
            $topic_explode = explode('/', $topic, 3);
            if (count($topic_explode)==3) {
                $callback = $this->events_callback;
                $callback($topic_explode[0].'/'.$topic_explode[1], [$topic_explode[2]=>$message]);
                syslog(LOG_DEBUG, sprintf("Event [%s => %s]: %s\n", $topic_explode[0].'/'.$topic_explode[1], $topic_explode[2], $message));
            } else {
                syslog(LOG_DEBUG, sprintf("Received message on topic [%s]: %s\n", $topic, $message));
            }
        };
        foreach ($this->topics as $subscribe) {
            syslog(LOG_DEBUG, $subscribe);
            $this->mqtt->subscribe($subscribe, $closure, 0);
        }
    }

    public function iteration()
    {
        $this->mqtt->loop(true);
    }

    public function finish()
    {
        $this->mqtt->disconnect();
    }
}
