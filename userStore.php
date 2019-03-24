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
	
	$channel->exchange_declare('userStoreExchange', 'direct', false, false, false);
	list($queue_name, ,) = $channel->queue_declare('', false, true, false, false);
	$channel->queue_bind($queue_name, 'userStoreExchange', 'userStore_req');

	return array($channel, $connection);
}

// User Update

$userStore_callback = function ($req) {
    $DnDdb = $client->db;
    $charCollection = $DnDdn->characters;
    $userCollection = $DnDdn->users;
    $reqArray = unserialize($req->body);
    $reqStr = $reqArr[0];
    $stuffID = $reqArr[1];
    $document = $reqArr[2];
    $error = "E";
	$success = "S";

    $msg = new AMQPMessage (
        $error,
        array('correlation_id' => $char->get('correlation_id'))
        );
    
    switch ($reqStr) {
        case "updateUser":
            echo "updating user document";
            $updateStuff = $userCollection->updateOne(
            ['_id' => $stuffID],
            [$document],
            ["upsert" => true]
            );
            $msg = new AMQPMessage (
                $success,
                array('correlation_id' => $req->get('correlation_id'))
            );        
            break;
        case "updateCharacter":
            echo "updating character document";
            $updateStuff = $charCollection->updateOne(
            ['_id' => $stuffID],
            [$document],
            ["upsert" => true]
            );
            $msg = new AMQPMessage (
                $success,
                array('correlation_id' => $req->get('correlation_id'))
            );        
        break;
    }

$req->delivery_info['channel']->basic_publish( $msg, '', $req->get('reply_to'));
echo "Sent back Message\n";

};

list($chan, $rabbitConn) = getRabbitMQ();
$chan->basic_qos(null, 1, null);

$userStore_callback = execute();
$chan->basic_consume($queue_name, '', false, true, false, false, $userStore_callback);

while (true) {
	$chan->wait();
}

$chan->close();
$rabbitConn->close();
?>
