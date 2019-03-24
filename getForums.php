z<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once 'databases/ForumsDB.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use databases\ForumsDB;

$dbconn = (new ForumsDB())->getConnection();

function getChannel(){
	include 'configs/rabbitmq/server.php';
	include 'configs/rabbitmq/auth_credentials.php';

    $connection = new AMQPStreamConnection($rmq_host, $rmq_port, $rmq_user, $rmq_pass, $rmq_vhost);
    $channel  = $connection->channel();

    $channel->exchange_declare('getForumExchange', 'direct', false, false, false);
    list($queue_name, ,) = $channel->queue_declare('', false, false, true, false);
    $channel->queue_bind($queue_name, 'getForumExchange', 'getForum_req');

    return array($channel, $connection);
}

//get forums

$GetForums_callback = function ($req) {
    echo "Getting forums for User...\n";
    $reqReq = unserialize($req->body);
    $reqStr = $reqReq[0];
    $reqParam = $reqReq[1];
    $error = "E";
    
    $msg = new AMQPMessage (
        $error,
        array('correlation_id' => $req->get('correlation_id'))
    );
    try {

        switch($reqStr){
            case "getForums":
                $userReq = "CALL getForums()";
                $stmt = $dbconn->prepare($userReq);
                echo "getting forums for user";
                break;
            case "getThreads":
                $userReq = "CALL getThreads(?)";
                $stmt = $dbconn->prepare($userReq);
                $stmt->bindParam(1, $reqParam, PDO::PARAM_STR);
                echo "getting threads for user";
                break;
            case "getThread":
                $userReq = "CALL getThread()";
                $stmt = $dbconn->prepare($userReq);
                $stmt->bindParam(1, $reqParam, PDO::PARAM_STR);
                echo "getting thread for user";
                break;
            case "getReplies":
                $userReq = "CALL getReplies(?)";
                $stmt = $dbconn->prepare($userReq);
                $stmt->bindParam(1, $reqParam, PDO::PARAM_STR);
                echo "getting replies for user";
                break;
        }
     
        $stmt->execute();
    
        $db_response = $stmt->fetchAll();
    
        $serialized_array=serialize($db_response);
    
        $msg = new AMQPMessage (
            $serialized_array,
            array('correlation_id' => $req->get('correlation_id'))
        );
    
        } catch (PDOException $e) {
            echo "Error occurred:" . $e->getMessage();
        }
    
        $req->delivery_info['channel']->basic_publish( $msg, '', $req->get('reply_to'));
        echo "Sent back Message\n";
    };

    
$channel->basic_qos(null, 1, null);
$channel->basic_consume($queue_name, '', false, true, false, false, $GetForums_callback);

while (true) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>