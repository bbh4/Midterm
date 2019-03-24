<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once 'databases/MongoDB.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use MongoDB\Client;

$client = (new MongoDB())->getConnection();

function getChannel() {
	include 'configs/rabbitmq/server.php';
	include 'configs/rabbitmq/auth_credentials.php';
	
	$connection = new AMQPStreamConnection($rmq_host, $rmq_port, $rmq_user, $rmq_pass, $rmq_vhost);
	$channel  = $connection->channel();
	
	$channel->exchange_declare('userRetrieveExchange', 'direct', false, false, false);
	list($queue_name, ,) = $channel->queue_declare('', false, true, false, false);
	$channel->queue_bind($queue_name, 'userRetrieveExchange', 'userRetrieve_req');

	return array($channel, $connection);
}

// User Retrieve

$userRetrieve_callback = function ($req) {
    $DnDdb = $client->db;
    $charCollection = $DnDdn->characters;
    $userCollection = $DnDdn->users;
    $reqArray = unserialize($req->body);
    $reqStr = $reqArr[0];
    $reqDoc = $reqArr[1];
    $error = "E";

    $msg = new AMQPMessage (
        $error,
        array('correlation_id' => $char->get('correlation_id'))
        );

      switch($req){
        case "userDoc":
            $foundUserDoc = $userCollection->find(['username' => $reqDoc]);
            $msg = new AMQPMessage (
                $foundUserDoc,
                array('correlation_id' => $char->get('correlation_id'))
                );
            break;
        case "charDoc":
            $foundChar = $charCollection->find(['_id' => $reqDoc]);
            $msg = new AMQPMessage (
                $foundChar,
                array('correlation_id' => $char->get('correlation_id'))
                );
            break;
      }  

$req->delivery_info['channel']->basic_publish( $msg, '', $req->get('reply_to'));
echo "Sent back Message\n";
};

list($chan, $rabbitConn) = getRabbitMQ();
$chan->basic_qos(null, 1, null);

$userRetrieve_callback = execute();
$chan->basic_consume($queue_name, '', false, true, false, false, $userRetrieve_callback);

while (true) {
	$chan->wait();
}

$chan->close();
$rabbitConn->close();
?>
