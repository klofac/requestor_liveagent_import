<?php
require "config.php";
/**
 * Nacte tickety, ktere se zmenily za nejake obdobi a aktualizuje stav a datumy v pomocne DB
 */



/**
 * API Call LiveAgent 
 */
function apicall_LA(string $command) : array {

    $output = array();

    $cht = curl_init($GLOBALS['config_api_url'].$command);
    
    // curl_setopt($cht, CURLOPT_FILE, $fp);
    curl_setopt($cht, CURLOPT_HEADER, 0);
    curl_setopt($cht, CURLOPT_HTTPHEADER, array(
        'Content-type: application/json',
        'apikey:'.$GLOBALS['config_api_key']
    ));
    curl_setopt($cht, CURLOPT_RETURNTRANSFER, true);

    $curloutput = curl_exec($cht);

    if(curl_error($cht)) {
        echo "ERROR: ".curl_error($cht);
    }

    $output = json_decode($curloutput, true);

    curl_close($cht);

    return $output;    
}

/**
 * Vyhodnoti zda se jedna o interni zpravu
 */
function isInternalType($messageType) : string {
    if(isset($messageType)) {
        //ktere typy maji byt interni: M - OFFLINE_LEGACY C - CHAT P - CALL V - OUTGOING_CALL 1 - INTERNAL_CALL I - INTERNAL U - INTERNAL_OFFLINE Z - INTERNAL_COLLAPSED S - STARTINFO T - TRANSFER R - RESOLVE J - POSTPONE X - DELETE B - SPAM G - TAG F - FACEBOOK W - TWITTER Y - RETWEET A - KNOWLEDGEBASE_START K - KNOWLEDGEBASE O - FORWARD Q - FORWARD_REPLY L - SPLITTED 2 - MERGED 3 - INCOMING_EMAIL 4 - OUTGOING_EMAIL 5 - OFFLINE
        switch ($messageType) {
            case 'I':
            case 'U':
            case 'R':
            case 'Z':
            case 'T':
            case 'G':
            case 'J':
            case 'X':
            case 'B':
                $output = 'true';
                break;
            
            default:
                $output = 'false';
                break;
        }
        return $output;
    }
    else {
        return 'false';
    }
}

/**
 * 
 */
function convertDepartmentToService($departmentId) {

    switch ($departmentId) {
        case 'ceb5937b':
            $result = 'Zbraně';
            break;
        
        case 'f417e7e6':
            $result = 'Nákup';
            break;
        
        case 'tv401zrk':
            $result = 'Velkoobchod';
            break;
    
        case 'wuub36n4':
            $result = 'Marketing';
            break;
                    
        default:
            $result = 'Zakázky';
            break;
    }

    return $result;
}


/**
 * 
 * MAIN PROGRAM
 * 
 */

    $ticket_from    = (isset($_GET['from']) ? $_GET['from'] : '0' );
    $ticket_to      = (isset($_GET['to']) ? $_GET['to'] : '1' );

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $mysqli = new mysqli($GLOBALS['config_tmpDB_hostname'], $GLOBALS['config_tmpDB_user'], $GLOBALS['config_tmpDB_pwd'], $GLOBALS['config_tmpDB_db'],3306);
    $mysqli->set_charset('utf8');

/*
    $tMax = $mysqli->query("SELECT max(importIndex) AS ticketIDmax FROM ".$GLOBALS['config_tmpDB_tableLA']);
    $tRes = $tMax->fetch_object();
    $tIDmax = (isset($tRes->ticketIDmax) ? $tRes->ticketIDmax : 0);
*/
    $time_start = microtime(true);

    $command = "tickets?_from=".($ticket_from)."&_to=".($ticket_to)."&_sortField=date_changed&_sortDir=DESC"; //&_sortField=date_created
    echo $command."<BR/>\n";

    // nacteme tickety
    $tickets = apicall_LA($command);  
    //print_r($tickets);

    foreach($tickets as $key => $ticket) {

        //nacteme stav z pomocne DB
        $laTicketDB = $mysqli->query(
            "SELECT * FROM ".$GLOBALS['config_tmpDB_tableLA']." WHERE laTicketID='".$ticket['code']."' LIMIT 1"
        );
        $laTicketRow = $laTicketDB->fetch_object();
        //print_r($laTicketRow);

        if($laTicketRow->ticketChanged === $ticket['date_changed']) {
            echo $ticket['id']
            .",".$ticket['code']
            .",dateChanged ".$ticket['date_changed']
            .", NOT CHANGED"
            ."<BR/>\n";
        }
        else {
            echo $ticket['id']
            .",".$ticket['code']
            .",sluzba ".$laTicketRow->ticketSluzba."->".convertDepartmentToService((isset($ticket['departmentid']) ? $ticket['departmentid'] : ''))
            .",dateChanged ".$laTicketRow->ticketChanged."->".$ticket['date_changed']
            .",status ".$laTicketRow->ticketStatus."->".$ticket['status']
            ."<BR/>\n";

            /*
            echo "UPDATE ".$GLOBALS['config_tmpDB_tableLA']." SET "
            ."ticketChanged='".$ticket['date_changed']."'"
            .",ticketSluzba='".convertDepartmentToService((isset($ticket['departmentid']) ? $ticket['departmentid'] : ''))."'"
            .",ticketStatus='".$ticket['status']."'"
            ." WHERE laTicketID='".$ticket['code']."'"
            ." LIMIT 1\n";
            */

            //provedeme aktualizaci zaznamu v pomocne DB
            $mysqli->query(
                "UPDATE ".$GLOBALS['config_tmpDB_tableLA']." SET "
                ."ticketChanged='".$ticket['date_changed']."'"
                .",ticketSluzba='".convertDepartmentToService((isset($ticket['departmentid']) ? $ticket['departmentid'] : ''))."'"
                .",ticketStatus='".$ticket['status']."'"
                ." WHERE laTicketID='".$ticket['code']."'"
                ." LIMIT 1"
            );

        }
    }

    $time_end = microtime(true);

    echo "Finished in ".round($time_end - $time_start)." sec";
?>
