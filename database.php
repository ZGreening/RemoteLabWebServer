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
        }
    }
    
    //Handle HTTP Get requests
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
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
                default:
                    $result['error'] = 'Not found function '.$_GET['function'].'!';
                    break;
            }
        }
    }

    echo json_encode($result);

?>