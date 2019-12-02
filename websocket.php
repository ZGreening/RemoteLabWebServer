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
    private $controlDuration = 30000; //Milliseconds before control is returned
    private $queue = []; //Clients who have requested access
    private $inControl; //The client in control of the device
    private $clients; //All connected clients

    //Constructor
    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
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

    //Sends the message to all connected computers
    private function sendToAll($msg)
    {
        foreach ($this->clients as $client) {
            $client->send($msg);
        }
    }

    //Updates how many computers are before each one in the queue
    private function updateComputersBefore()
    {
        $iii = 0;
        foreach ($this->queue as $user) {
            $user->send(json_encode(array('computersBefore' => ($iii++))));
        }
    }

    //Updates Computers Connected, Computers Waiting, and the Controlling id on all computers
    private function updateConnectionInfo()
    {
        $array = array('computersConnected' => count($this->clients),
            'computersWaiting' => count($this->queue), 'controllingId' => $this->inControl->resourceId);

        if ($this->inControl == null) {
            $array['setControlDurationSliderDisabled'] = false;
        } else {
            $array['setControlDurationSliderDisabled'] = true;
        }

        $this->sendToAll(json_encode($array));
    }

    //Removes control from the current user and gives it to the next user in the queue
    private function grantAccessToNext()
    {
        //Remove new client from queue and replace inControl with the new client
        $this->inControl = array_shift($this->queue);

        //Remove control and update computer info
        $this->sendToAll(json_encode(array('setControlsDisabled' => true)));
        $this->updateComputersBefore();
        $this->updateConnectionInfo();

        //Allow control to new client
        if ($this->inControl != null) {
            $this->inControl->send(json_encode(array('setControlsDisabled' => false)));

            //Limit control duration only if more clients are in the queue
            if (count($this->queue) > 0) {
                $this->inControl->send(json_encode(array('setControlDuration' => $this->controlDuration)));
            }
        }
    }

    /////////////WEBSOCKET FUNCTIONS/////////////
    public function onOpen(ConnectionInterface $connection)
    {
        $this->clients->attach($connection);
        echo "New connection! ({$connection->resourceId})\n";

        $json = array('defVoltage' => getDefVoltage(),
            'accVoltage' => getAccVoltage(),
            'currentAmperage' => getCurrentAmperage(),
            'defVoltagePolarity' => getDefVoltagePolarity(),
            'magneticArc' => getMagneticArc(),
            'controlDuration' => $this->controlDuration,
            'ownId' => $connection->resourceId);

        $connection->send(json_encode($json));
        $this->updateConnectionInfo();
    }

    public function onClose(ConnectionInterface $connection)
    {
        $this->clients->detach($connection); //Remove user from clients list
        unset($this->queue[$connection->resourceId]); //Remove user from access queue
        echo "Connection {$connection->resourceId} has disconnected\n";

        //Update person in control of the device
        if ($this->inControl == $connection) {
            $this->grantAccessToNext();
        }

        //Update computer info
        $this->updateComputersBefore();
        $this->updateConnectionInfo();
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
                    case 'returnControl':
                        $this->grantAccessToNext();
                        break;
                    default:
                        $from->send(json_encode(array('error' => "Error: Unknown function")));
                }
            } else if ($key == 'requestAccess') {
                $this->queue[$from->resourceId] = $from;

                //If no one is currently controlling the device, go ahead and grant access
                if ($this->inControl == null) {
                    $this->grantAccessToNext();
                } else if (count($this->queue) == 1) {
                    //Only send duration setter if this is first client added to queue
                    $this->inControl->send(json_encode(array('setControlDuration' => $this->controlDuration)));
                }

                //Update info on all other computers
                $this->updateComputersBefore();
                $this->updateConnectionInfo();
            } else if ($key == 'setControlDuration' && $this->inControl == null) { //Don't need to check queue
                $this->controlDuration = $value;
                $this->forwardMessage($from,$msg);
            }
        }
    }
}

////////////MAIN SCRIPT TO INITIATE SERVER////////////
$server = IoServer::factory(new HttpServer(new WsServer(new RatchetServer())), 8000);
$server->run();
echo "WebSocket listening on port 8000";
