<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once 'databases/AuthDB.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use databases\AuthDB;

$dbconn = (new AuthDB())->getConnection();

function getChannel() {
	include 'configs/rabbitmq/server.php';
	include 'configs/rabbitmq/auth_credentials.php';
	
	$connection = new AMQPStreamConnection($rmq_host, $rmq_port, $rmq_user, $rmq_pass, $rmq_vhost);
	$channel  = $connection->channel();
	
	$channel->exchange_declare('LoginExchange', 'direct', false, false, false);
	list($queue_name, ,) = $channel->queue_declare('', false, true, false, false);
	$channel->queue_bind($queue_name, 'LoginExchange', 'login_req');

	return array($channel, $connection);
}

function execute($req) {
	echo "Logging in User...\n";
	$result = explode('~', $req->body);
	echo "This is body: ", $req->body, "\n";
	echo "This is 0: ", $result[0], "\n";
	echo "This is 1: ", $result[1], "\n";
	$user = $result[0];
	$pass = $result[1];
	$error = "E";
	$success = "S";

	$msg = new AMQPMessage (
		$error,
		array('correlation_id' => $req->get('correlation_id'))
	);


	try {
		// include 'configs/mysql/server.php';
		// include 'configs/mysql/auth_credentials.php';

		// $pdo = new PDO("mysql:host=$mysql_host;dbname=$mysql_db", $mysql_user, $mysql_pass);
		// $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		// calling stored procedure command
		$sql = "CALL getPassword(?)";

		// prepare for execution of the stored procedure
		$stmt = $dbconn->prepare($sql);

		// pass value to the command
		$stmt->bindParam(1, $user, PDO::PARAM_STR);
		
		// execute the stored procedure
		$isSuccessful = $stmt->execute();

		$db_response = $stmt->fetch(PDO::FETCH_ASSOC);
		print_r($db_response);

			if (isset($db_response))
			{
				echo "In IF\n", "pass in DB is: '", $db_response['password'], "'\n";
				echo $pass, "\n";
				$passver = password_verify($pass, $db_response['password']);

				if($passver){
					$msg = new AMQPMessage (
						$success,
						array('correlation_id' => $req->get('correlation_id'))
					);
					echo "Success\n";
				}
			}

	} catch (PDOException $e) {
		echo "Error occurred:" . $e->getMessage();
	}

	$req->delivery_info['channel']->basic_publish( $msg, '', $req->get('reply_to'));
	echo "Sent back Message\n";

};


list($chan, $rabbitConn) = getRabbitMQ();
$chan->basic_qos(null, 1, null);

$login_callback = execute();
$chan->basic_consume($queue_name, '', false, true, false, false, $login_callback);

while (true) {
	$chan->wait();
}

$chan->close();
$rabbitConn->close();
?>
