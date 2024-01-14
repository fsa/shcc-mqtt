<?php
namespace ShccPlugin\Mqtt;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use ShccFramework\DaemonEvent;
use ShccFramework\DaemonInterface;

class Daemon implements DaemonInterface
{
    const DAEMON_NAME = 'MQTT';

    private MqttClient $mqtt;
    private string $server = '127.0.0.1';
    private int $port = 1883;
    private string $client_id = 'shcc-host';
    private string $username = 'shcc';
    private string $password = 'password';
    private array $topics = [];

    public function __construct(
        private DaemonEvent $daemonEvent
    ) {
    }

    public function getName(): string
    {
        return self::DAEMON_NAME;
    }

    public function prepare(array $params): void
    {
        foreach(['server'=>'server_addr', 'port'=>'port', 'client_id'=>'client_id', 'username'=>'username', 'password'=>'password', 'topics'=>'topics'] as $property=>$param) {
            if(isset($params[$param])) {
                $this->$property = $params[$param];
            }
        }
        $this->mqtt = new MqttClient($this->server, $this->port, $this->client_id);
        $connectionSettings = (new ConnectionSettings)
            ->setUsername($this->username)
            ->setPassword($this->password);
        $this->mqtt->connect($connectionSettings, true);
        $closure = function ($topic, $message, $retained, $matchedWildcards) {
            $this->daemonEvent->postMessage(self::DAEMON_NAME, [$topic => $message]);
            echo sprintf("Message [%s]: %s", $topic, $message) . PHP_EOL;
        };
        foreach ($this->topics as $subscribe) {
            $this->mqtt->subscribe($subscribe, $closure, 0);
        }
    }

    public function iteration(): ?string
    {
        $this->mqtt->loop(true);
        return null;
    }

    public function finish(): void
    {
        $this->mqtt->disconnect();
    }
}
