<?php

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

        $connection->exec(sprintf("UPDATE data SET Values_=%s WHERE Variables=='%s';",$value,$variable));
        $connection=null;
    }

    function setDeflectingVoltage($voltage) {
        setDatabaseValue("deflectingVoltage",$voltage);
    }
    function getDeflectingVoltage() {
        return getDatabaseValue("deflectingVoltage");
    }
    function setAcceleratingVoltage($voltage) {
        setDatabaseValue("acceleratingVoltage",$voltage);
    }
    function getAcceleratingVoltage() {
        return getDatabaseValue("acceleratingVoltage");
    }
    function setCurrentAmperage($voltage) {
        setDatabaseValue("magnetizingCurrent",$voltage);
    }
    function getCurrentAmperage() {
        return getDatabaseValue("magnetizingCurrent");
    }


    /////////// START OF MAIN SCRIPT ///////////
    header('Content-Type: application/json');
    $result = array();

    //Handle HTTP Post requests
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if(!isset($_POST['function'])) {
            $result['error'] = 'No function name!'; 
        } else if( !isset($_POST['arguments']) ) {
            $result['error'] = 'No function arguments!';
        } else {
            switch($_POST['function']) {
                case 'setDeflectingVoltage':
                    setDeflectingVoltage($_POST['arguments'][0]);
                    break;
                case 'setAcceleratingVoltage':
                    setAcceleratingVoltage($_POST['arguments'][0]);
                    break;
                case 'setCurrentAmperage':
                    setCurrentAmperage($_POST['arguments'][0]);
                    break;
                default:
                    $result['error'] = 'Not found function '.$_POST['function'].'!';
                    break;
            }
        }
    }
    
    //Handle HTTP Get requests
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        if(!isset($_GET['function'])) {
            $result['error'] = 'No function name!';
        } else {
            switch($_GET['function']) {
                case 'getDeflectingVoltage':
                    $result['value'] = getDeflectingVoltage();
                    break;
                case 'getAcceleratingVoltage':
                    $result['value'] = getAcceleratingVoltage();
                    break;
                case 'getCurrentAmperage':
                    $result['value'] = getCurrentAmperage();
                    break;
                default:
                    $result['error'] = 'Not found function '.$_GET['function'].'!';
                    break;
            }
        }
    }

    echo json_encode($result);

?>