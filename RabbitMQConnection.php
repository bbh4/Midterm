<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;

class RabbitMQConnection {

	private $connection;
	private $channel;

	public function __construct($exchange) {
		include 'configs/rabbitServer.php';
		include 'configs/rabbitCredentials.php';
		$connection = new AMQPStreamConnection($host, $port, $user, $pass, $vhost);
		$this->channel  = $connection->channel();

		$this->channel->exchange_declare($exchange, 'direct', false, false, false);
		list($queue_name, ,) = $this->channel->queue_declare('', false, true, false, false);
		$this->channel->queue_bind($queue_name, $exchange, $exchange . '_req');
	}

	public function getChannel() {
		return $this->channel;
	}

	public function closeChannel() {
		$this->channel->close();
	}

	public function closeConnection() {
		$this->connection->close();
	}
}