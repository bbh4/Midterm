<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once 'databases/ForumsDB.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use databases\ForumsDB;

// TODO Rename this file

$dbconn = (new ForumsDB())->getConnection();

function getChannel(){
	include '../configs/rabbitmq/server.php';
	include '../configs/rabbitmq/auth_credentials.php';

    $connection = new AMQPStreamConnection($rmq_host, $rmq_port, $rmq_user, $rmq_pass, $rmq_vhost);
    $channel  = $connection->channel();

    $channel->exchange_declare('createStuffExchange', 'direct', false, false, false);
    list($queue_name, ,) = $channel->queue_declare('', false, true, false, false);
    $channel->queue_bind($queue_name, 'createStuffExchange', 'createStuff_req');

    return array($channel, $connection);
}

//Create Stuff
$createStuff_callback = function ($req) {
    echo "Creating stuff for User...\n";
    $reqReq = unserialize($req->body);
    $reqStr = $reqReq[0];
    // $forumID = $reqReq[1];
    // $name = $reqReq[2];
    // $forumID = $reqReq[3];
    // $forumID = $reqReq[4];
    $success = "S";
    $error = "E";

    $msg = new AMQPMessage (
        $error,
        array('correlation_id' => $req->get('correlation_id'))
    );
        
        include 'databaseAuth.php';
        
        $pdo = (new ForumsDB())->getConnection();

        echo "connected to forum database\n";

        switch($reqStr){
            case "createThread":
                $forumID = $reqReq[1];
                $name = $reqReq[2];
                $content = $reqReq[3];
                $user = $reqReq[4];
                $db = "CALL createThread(?,?,?,?)";
                $stmt = $pdo->prepare($db);
                $stmt->bindParam(1, $forumID, PDO::PARAM_INT);
                $stmt->bindParam(2, $name, PDO::PARAM_STR);
                $stmt->bindParam(3, $content, PDO::PARAM_STR);
                $stmt->bindParam(4, $user, PDO::PARAM_STR);
                $stmt->execute();
                $msg = new AMQPMessage (
                    $success,
                    array('correlation_id' => $req->get('correlation_id'))
                );
                echo "Created Thread";
                break;
            case "createReply":
                $threadID = $reqReq[1];
                $content = $reqReq[2];
                $user = $reqReq[3];
                $db = "CALL createReply(?,?,?)";
                $stmt = $pdo->prepare($db);
                $stmt->bindParam(1, $threadID, PDO::PARAM_STR);
                $stmt->bindParam(2, $content, PDO::PARAM_INT);
                $stmt->bindParam(3, $user, PDO::PARAM_INT);
                $stmt->execute();
                $msg = new AMQPMessage (
                    $success,
                    array('correlation_id' => $req->get('correlation_id'))
                );
                echo "Created Reply";
                break;
        }
        echo "Request Created\n";
    
    $req->delivery_info['channel']->basic_publish( $msg, '', $req->get('reply_to'));
    echo "Delivered Message\n";
};

$channel->basic_qos(null, 1, null);
$channel->basic_consume($queue_name, '', false, true, false, false, $createStuff_callback);

while (count($channel->callbacks)) {
    $channel->wait();
}
$channel->close();
$connection->close();
?>