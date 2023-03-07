<?php
require "config.php";

/**
 * Call hooknt.php 
 */
function call_hooknt(string $ticketCode) : string {

    $command = $GLOBALS['config_hooknt_url'].'?ticketCode='.$ticketCode;
    //echo $command."<BR/>\n";

    $cht = curl_init($command);
    
    curl_setopt($cht, CURLOPT_HEADER, 0);
    curl_setopt($cht, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($cht, CURLOPT_HTTPHEADER, array(
        'Content-type: text/html'
    ));

    $curloutput = curl_exec($cht);

    if(curl_error($cht)) {
        echo "ERROR: ".curl_error($cht);
    }

    curl_close($cht);

    return $curloutput;    
}



/**
 * 
 * MAIN PROGRAM
 * 
 */
    $time_start = microtime(true);

    $importLimit = (isset($_GET['limit']) ? ($_GET['limit']+0) : 10) ;

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $mysqli = new mysqli($GLOBALS['config_tmpDB_hostname'], $GLOBALS['config_tmpDB_user'], $GLOBALS['config_tmpDB_pwd'], $GLOBALS['config_tmpDB_db'],3306);
    $mysqli->set_charset('utf8');

    $command = "SELECT laTicketID FROM ".$GLOBALS['config_tmpDB_missingTickets']." WHERE zapsanoDoHLP IS NULL ORDER BY laTicketChanged DESC LIMIT ".$importLimit;
    //echo $command."<BR/>";

    $tickets = $mysqli->query($command);
    
    foreach($tickets as $key => $ticket) {

        $ticketCode = $ticket["laTicketID"];
        $hookntResult = call_hooknt($ticketCode);  

        echo $hookntResult."<BR/>\n";

        if(stripos($hookntResult,'Error: nepodarilo se nacist ticket z LA') || stripos($hookntResult,'Error: vytvoreni ticketu selhalo')) {
            //do datumu dame nuly, ale neni to null, tak pozname co neproslo
            $commandUpdate = "UPDATE ".$GLOBALS['config_tmpDB_missingTickets']." SET zapsanoDoHLP='' WHERE laTicketID='".$ticketCode."' LIMIT 1";
        }
        else {
            $commandUpdate = "UPDATE ".$GLOBALS['config_tmpDB_missingTickets']." SET zapsanoDoHLP=NOW() WHERE laTicketID='".$ticketCode."' LIMIT 1";
        }
        //echo $commandUpdate."<BR/>\n";
        $mysqli->query($commandUpdate);
        sleep(2);
    }
    $time_end = microtime(true);

    echo "Finished in ".round($time_end - $time_start)." sec - total";
?>
