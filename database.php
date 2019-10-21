<?php 
    header('Content-Type: application/json');

    //Generic database getter function
    //
    // $variable = the name of the database variable
    // returns = the value in the database
    function getDatabaseValue($variable) {
        try {
            $connection = new PDO("sqlite:RemotePhysLab.db");
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            return "Connection failed: " . $exception->getMessage();
        }

        $query = $connection->query(sprintf("SELECT Values_ from data WHERE Variables=='%s';",$variable));
        $query_result = $query->fetch();
        $value = $query_result[0];

        $connection=null;
        
        return $value;
    }

    //Generic database setter function
    //
    // $variable = the name of the database variable
    // $value = the value to set in the database
    function setDatabaseValue($variable,$value) {
        try {
            $connection = new PDO("sqlite:RemotePhysLab.db");
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            return "Connection failed: " . $exception->getMessage();
        }

        $connection->exec(sprintf("UPDATE data SET Values_=%d WHERE Variables=='%s';",$value,$variable));
        $connection=null;
    }

    //Request access to control lab device. This function adds an IP address into the queue in the database.
    //
    // $IP = the ip address of the requesting computer
    function requestAccess($IP) {
        try {
            $connection = new PDO("sqlite:RemotePhysLab.db");
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            $result['error'] = "Connection failed: " . $exception->getMessage();
            return $result;
        }

        try {
            $connection->exec(sprintf("INSERT INTO queue(IP) VALUES ('%s');",$IP));
        } catch(PDOException $exception) {
            //Unique constraint fails indicate request already made
            $result['error'] = "Error: You are already queued";
        }

        $connection=null;
        return $result;
    }

    //Returns the number of computer IP addresses in the database queue
    function getNumberComputersWaiting() {
        try {
            $connection = new PDO("sqlite:RemotePhysLab.db");
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            return "Connection failed: " . $exception->getMessage();
        }

        $query = $connection->query("SELECT count(*) FROM queue;");
        $query_result = $query->fetch();
        $value = $query_result[0];

        $connection=null;
        return $value;
    }

    //TODO
    function hasAccess($IP) {
        return true;
    }



    /////////// Database Getters and Setters //////////
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
    
    //////////// HTTP Request Handlers ////////////
    function handlePostRequest() {
        if(!isset($_POST['function'])) {
            $result['error'] = 'No function name!'; 
        } else if( !isset($_POST['arguments']) ) {
            $result['error'] = 'No function arguments!';
        } else if( hasAccess($_SERVER['REMOTE_ADDR'])) {
            //If the current user has access
            switch($_POST['function']) {
                case 'setDefVoltage':
                    setDefVoltage($_POST['arguments'][0]);
                    break;
                case 'setAccVoltage':
                    setAccVoltage($_POST['arguments'][0]);
                    break;
                case 'setCurrentAmperage':
                    setCurrentAmperage($_POST['arguments'][0]);
                    break;
                case 'setMagneticArc':
                    setMagneticArc($_POST['arguments'][0]);
                    break;
                case 'setDefVoltagePolarity':
                    setDefVoltagePolarity($_POST['arguments'][0]);
                    break;
                default:
                    $result['error'] = 'Not found function '.$_POST['function'].'!';
                    break;
            }
        } else {
            //If user does not have access
            switch($_POST['function']) {
                case 'setDefVoltage':
                case 'setAccVoltage':
                case 'setCurrentAmperage':
                case 'setMagneticArc':
                case 'setDefVoltagePolarity':
                    $result['error'] = 'Access Denied';
                    break;
                default:
                    $result['error'] = 'Not found function '.$_POST['function'].'!';
                    break;
            }
        }

        return $result;
    }
    function handleGetRequest() {
        if(!isset($_GET['function'])) {
            $result['error'] = 'No function name!';
        } else {
            switch($_GET['function']) {
                case 'getDefVoltage':
                    $result['value'] = getDefVoltage();
                    break;
                case 'getAccVoltage':
                    $result['value'] = getAccVoltage();
                    break;
                case 'getCurrentAmperage':
                    $result['value'] = getCurrentAmperage();
                    break;
                case 'getMagneticArc':
                    $result['value'] = getMagneticArc();
                    break;
                case 'getDefVoltagePolarity':
                    $result['value'] = getDefVoltagePolarity();
                    break;
                case 'getNumberComputersWaiting':
                    $result['value'] = getNumberComputersWaiting();
                    break;
                case 'requestAccess':
                    $result = requestAccess($_SERVER['REMOTE_ADDR']);
                    break;
                default:
                    $result['error'] = 'Not found function '.$_GET['function'].'!';
                    break;
            }
        }

        return $result;
    }

    /////////////// MAIN SCRIPT ////////////////
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        echo json_encode(handlePostRequest());
    } else if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        echo json_encode(handleGetRequest());
    }
?>