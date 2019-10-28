<?php

namespace App\Services;
use PDO;
use Ratchet\Http\HttpServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
require './vendor/autoload.php';
header('Content-Type: application/json');

    //Generic database getter function
    //
    // $variable = the name of the database variable
    // returns = the value in the database
    function getDatabaseValue($variable) {
        try {
            $connection = new PDO("sqlite:RemotePhysLab.db");
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $query = $connection->query(sprintf("SELECT Values_ from data WHERE Variables=='%s';",$variable));
            $query_result = $query->fetch();
            $value = $query_result[0];

            $connection=null;
            return $value;
        } catch(PDOException $exception) {
            $connection=null;
            return "Error: " . $exception->getMessage();
        }
    }

    //Generic database setter function
    //
    // $variable = the name of the database variable
    // $value = the value to set in the database
    function setDatabaseValue($variable,$value) {
        try {
            $connection = new PDO("sqlite:RemotePhysLab.db");
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $statement = $connection->prepare("UPDATE data SET Values_=:value WHERE Variables==:variable;");
            $statement->bindParam(':value',$value);
            $statement->bindParam(':variable',$variable);

            $statement->execute();

            $statement=null;
            $connection=null;
        } catch(PDOException $exception) {
            $statement=null;
            $connection=null;
            return "Error: " . $exception->getMessage();
        }
    }

    //Request access to control lab device. This function adds a websocket ID into the queue in the database.
    //
    // $ID = the ID for the websocket client of the requesting computer
    function requestAccess($ID) {
        try {
            $connection = new PDO("sqlite:RemotePhysLab.db");
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            $connection=null;
            return "Error: " . $exception->getMessage();
        }

        try {
            $statement = $connection->prepare("INSERT INTO queue(ID) VALUES (:ID);");
            $statement->bindParam(':ID',$ID);
            $statement->execute();

            $statement=null;
            $connection=null;
        } catch(PDOException $exception) {
            $statement=null;
            $connection=null;
            return "Error: You are already queued";
        }
    }

    //Removes the websocket ID from the queue. If the queue is empty afterwards,
    //this function recreates the database table to reset the QueuePosition auto-increment
    //count back to 1. This is to prevent sqlite from refusing new request because the
    //auto-incremented value has reached its max value. The limiting resource for this
    //program is therefore the max integer in php used for resourceId.
    //
    // $ID = the connection to remove from the request access queue
    function removeAccessRequest($ID) {
        try {
            $connection = new PDO("sqlite:RemotePhysLab.db");
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $statement = $connection->prepare("DELETE FROM queue WHERE ID==:ID;");
            $statement->bindParam(':ID',$ID);
            $statement->execute();

            //Destroy and recreate database table to reset QueuePosition auto-increment
            if(getNumberComputersWaiting()==0) {
                $connection->exec("DROP TABLE queue;");
                $connection->exec("CREATE TABLE queue(QueuePosition INTEGER PRIMARY KEY
                    AUTOINCREMENT,ID INT UNIQUE NOT NULL);");
            }

            $statement=null;
            $connection=null;
        } catch(PDOException $exception) {
            $statement=null;
            $connection=null;
            return "Error: " . $exception->getMessage();
        }
    }

    //Returns the number of computer websockets in the database queue
    function getNumberComputersWaiting() {
        try {
            $connection = new PDO("sqlite:RemotePhysLab.db");
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $query = $connection->query("SELECT count(*) FROM queue;");
            $query_result = $query->fetch();
            $value = $query_result[0];

            $connection=null;
            return $value;
        } catch(PDOException $exception) {
            $connection=null;
            return "Error: " . $exception->getMessage();
        }
    }

    //TODO
    function hasAccess($ID) {
        return true;
    }

    /////////// DATABASE GETTER AND SETTERS ///////////
    function setDefVoltage($voltage) {
        setDatabaseValue("deflectingVoltage",$voltage);
    }
    function getDefVoltage() {
        return getDatabaseValue("deflectingVoltage");
    }
    function setAccVoltage($voltage) {
        setDatabaseValue("acceleratingVoltage",$voltage);
    }
    function getAccVoltage() {
        return getDatabaseValue("acceleratingVoltage");
    }
    function setCurrentAmperage($voltage) {
        setDatabaseValue("magnetizingCurrent",$voltage);
    }
    function getCurrentAmperage() {
        return getDatabaseValue("magnetizingCurrent");
    }
    function setMagneticArc($arc) {
        setDatabaseValue("magneticArc",$arc);
    }
    function getMagneticArc() {
        return getDatabaseValue("magneticArc");
    }
    function setDefVoltagePolarity($polarity) {
        setDatabaseValue("deflectingPolarity",$polarity);
    }
    function getDefVoltagePolarity() {
        return getDatabaseValue("deflectingPolarity");
    }

//////////WEBSOCKET SERVER CLASS//////////
class RatchetServer implements MessageComponentInterface
{
    private $clients;
    private $connectedUsers = [];
    
    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    private function forwardMessage(ConnectionInterface $from, $msg) {
        foreach ($this->clients as $client) {
            if($from !== $client) {
                //Send message to all other connected clients
                $client->send($msg);
            }
        }
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $this->connectedUsers[$conn->resourceId] = $conn;
        echo "New connection! ({$conn->resourceId})\n";

        //Collect all data from the database to initialized client
        $json = array('defVoltage'=> getDefVoltage(),
                'accVoltage'=> getAccVoltage(),
                'currentAmperage'=> getCurrentAmperage(),
                'defVoltagePolarity'=> getDefVoltagePolarity(),
                'magneticArc'=> getMagneticArc(),
                'computersWaiting' => getNumberComputersWaiting());
        
        $conn->send(json_encode($json));
    }

    public function onClose(ConnectionInterface $conn) {
        removeAccessRequest($conn->resourceId);
        $this->clients->detach($conn);
        unset($this->connectedUsers[$conn->resourceId]);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $exception) {
        echo "An error has occurred: {$exception->getMessage()}\n";
        $conn->close();
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        foreach(json_decode($msg,true) as $key => $value) {
            //Update appropriate values in database 
            if(hasAccess($from)) {
                switch($key) {
                case 'defVoltage':
                    setDefVoltage($value);
                    $this->forwardMessage($from,$msg);
                    break;
                case 'accVoltage':
                    setAccVoltage($value);
                    $this->forwardMessage($from,$msg);
                    break;
                case 'currentAmperage':
                    setCurrentAmperage($value);
                    $this->forwardMessage($from,$msg);
                    break;
                case 'defVoltagePolarity':
                    setDefVoltagePolarity($value);
                    $this->forwardMessage($from,$msg);
                    break;                
                case 'magneticArc':
                    setMagneticArc($value);
                    $this->forwardMessage($from,$msg);
                    break;
                case 'requestAccess':
                    $from->send(json_encode(array('error'=> "Error: You already have access")));
                    break;
                default:
                    $from->send(json_encode(array('error'=> "Error: Unknown function")));
                }
            } else if($key=='requestAccess') { //If client does not already have access.
                $result = requestAccess($from->resourceId);
                if($result!==null) {
                    $from->send(json_encode(array('error'=> $result)));
                } else {
                    foreach ($this->clients as $client) {
                        $client->send(json_encode(array('computersWaiting'=> getNumberComputersWaiting())));
                    }
                }
            } else {
                $from->send(json_encode(array('error'=> "Error: You do not have access")));
            }
        }
    }
}

////////////MAIN SCRIPT TO INITIATE SERVER////////////
$server = IoServer::factory(new HttpServer(new WsServer(new RatchetServer())),8000);
$server->run();

?>