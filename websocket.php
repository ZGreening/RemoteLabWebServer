<?php

namespace App\Services;

use PDO;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

require './vendor/autoload.php';
header('Content-Type: application/json');

//Generic database getter function
//
// $variable = the name of the database variable
// returns = the value in the database
function getDatabaseValue($variable)
{
    try {
        $connection = new PDO("sqlite:RemotePhysLab.db");
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $query = $connection->query(sprintf("SELECT Values_ from data WHERE Variables=='%s';", $variable));
        $query_result = $query->fetch();
        $value = $query_result[0];

        $connection = null;
        return $value;
    } catch (PDOException $exception) {
        $connection = null;
        return "Error: " . $exception->getMessage();
    }
}

//Generic database setter function
//
// $variable = the name of the database variable
// $value = the value to set in the database
function setDatabaseValue($variable, $value)
{
    try {
        $connection = new PDO("sqlite:RemotePhysLab.db");
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $statement = $connection->prepare("UPDATE data SET Values_=:value WHERE Variables==:variable;");
        $statement->bindParam(':value', $value);
        $statement->bindParam(':variable', $variable);

        $statement->execute();

        $statement = null;
        $connection = null;
    } catch (PDOException $exception) {
        $statement = null;
        $connection = null;
        return "Error: " . $exception->getMessage();
    }
}

/////////// DATABASE GETTER AND SETTERS ///////////
function setDefVoltage($voltage)
{
    setDatabaseValue("deflectingVoltage", $voltage);
}
function getDefVoltage()
{
    return getDatabaseValue("deflectingVoltage");
}
function setAccVoltage($voltage)
{
    setDatabaseValue("acceleratingVoltage", $voltage);
}
function getAccVoltage()
{
    return getDatabaseValue("acceleratingVoltage");
}
function setCurrentAmperage($amperage)
{
    setDatabaseValue("magnetizingCurrent", $amperage);
}
function getCurrentAmperage()
{
    return getDatabaseValue("magnetizingCurrent");
}
function setMagneticArc($arc)
{
    setDatabaseValue("magneticArc", $arc);
}
function getMagneticArc()
{
    return getDatabaseValue("magneticArc");
}
function setDefVoltagePolarity($polarity)
{
    setDatabaseValue("deflectingPolarity", $polarity);
}
function getDefVoltagePolarity()
{
    return getDatabaseValue("deflectingPolarity");
}

//////////WEBSOCKET SERVER CLASS//////////
class RatchetServer implements MessageComponentInterface
{
    private $intervalOfControl = 60000; //Milliseconds before next person gains access
    private $inControl; //The client in control of the device
    private $queue; //Clients who have requested access
    private $clients; //All connected clients

    //Constructor
    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->queue = new \SplObjectStorage;
    }

    //Forwards a message from one client to all other connected clients
    private function forwardMessage($from, $msg)
    {
        foreach ($this->clients as $client) {
            if ($from !== $client) {
                //Send message to all other connected clients
                $client->send($msg);
            }
        }
    }

    //Sends the current data to all connected clients.
    //Does not update computersBefore.
    private function updateAllClients() {
        $json = array('defVoltage' => getDefVoltage(),
            'accVoltage' => getAccVoltage(),
            'currentAmperage' => getCurrentAmperage(),
            'defVoltagePolarity' => getDefVoltagePolarity(),
            'magneticArc' => getMagneticArc(),
            'computersWaiting' => count($this->queue),
            'computersConnected' => count($this->clients),
            'controllingId' => $this->inControl->resourceId);

        //Send to all clients
        foreach($this->clients as $client) {
            $json['ownId'] = $client->resourceId; //Get the clients id to send
            $client->send(json_encode($json));
        }
    }

    private function grantAccess($client) {
        //If a client is currently in control, remove their control
        if($this->inControl!=null) {
            $this->inControl->send(json_encode(array('setControlsDisabled'=>true)));
        }

        //Allow control to new client
        $client->send(json_encode(array('setControlsDisabled'=>false)));

        //Remove new client from queue and replace inControl with the new client
        $this->queue->detach($client);
        $this->inControl=$client;

        //Update computers in queue to all the computers who have requested access
        $iii=0;
        foreach($this->queue as $user)
        {
            $user->send(json_encode(array('computersBefore'=>($iii++))));
        }

        //Update all clients data
        $this->updateAllClients();
    } 

    /////////////WEBSOCKET FUNCTIONS/////////////
    public function onOpen(ConnectionInterface $connection)
    {
        $this->clients->attach($connection);
        $this->updateAllClients();
        echo "New connection! ({$connection->resourceId})\n";
    }

    public function onClose(ConnectionInterface $connection)
    {
        $this->clients->detach($connection); //Remove user from clients list
        $this->queue->detach($connection); //Remove user from access queue

        //Update person in control of the device
        if($this->inControl==$connection) {
            if(count($this->queue)>0) {
                $this->grantAccess($this->queue->current()); //New controller from queue
            } else {
                $this->inControl=null; //No one if queue is empty
            }
        }

        //Update computers in queue to all the computers who have requested access
        $iii=0;
        foreach($this->queue as $user)
        {
            $user->send(json_encode(array('computersBefore'=>($iii++))));
        }

        //Update all clients data
        $this->updateAllClients();
        echo "Connection {$connection->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $connection, \Exception $exception)
    {
        echo $exception;
        $connection->close();
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        foreach (json_decode($msg, true) as $key => $value) {
            //Update appropriate values in database if in control
            if ($from == $this->inControl) {
                switch ($key) {
                    case 'defVoltage':
                        setDefVoltage($value);
                        $this->forwardMessage($from, $msg);
                        break;
                    case 'accVoltage':
                        setAccVoltage($value);
                        $this->forwardMessage($from, $msg);
                        break;
                    case 'currentAmperage':
                        setCurrentAmperage($value);
                        $this->forwardMessage($from, $msg);
                        break;
                    case 'defVoltagePolarity':
                        setDefVoltagePolarity($value);
                        $this->forwardMessage($from, $msg);
                        break;
                    case 'magneticArc':
                        setMagneticArc($value);
                        $this->forwardMessage($from, $msg);
                        break;
                    case 'requestAccess':
                        $from->send(json_encode(array('error' => "Error: You already have access")));
                        break;
                    default:
                        $from->send(json_encode(array('error' => "Error: Unknown function")));
                }
            } else if ($key == 'requestAccess') { //If client does not already have access.
                if(count($this->queue)==0 && $this->inControl==null) {
                    $this->grantAccess($from);
                } else {
                    //Display computers currently enqueued
                    $from->send(json_encode(array('computersBefore' => count($this->queue))));
                    $this->queue->attach($from); //enqueue user

                    //Update computers waiting on all clients
                    foreach ($this->clients as $client) {
                        $client->send(json_encode(array('computersWaiting' => count($this->queue))));
                    }
                }
            } else {
                //Should never execute
                $from->send(json_encode(array('error' => "Error: You do not have access")));
            }
        }
    }
}

////////////MAIN SCRIPT TO INITIATE SERVER////////////
$server = IoServer::factory(new HttpServer(new WsServer(new RatchetServer())),8000);
$server->run();
