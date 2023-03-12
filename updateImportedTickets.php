<?php
require "config.php";
require "ipex_helpdesk.php";
date_default_timezone_set('Europe/Prague');

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
 * Call hooknm.php 
 */
function call_hooknm(string $ticketCode) : string {

    $command = $GLOBALS['config_hooknm_url'].'?ticketCode='.$ticketCode.'&fastMode=1';
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
 */
function mylog($text) {
    $time = date("Y-m-d H:i:s")." ";
    $fp = fopen("updateImportedTickets.log", "a");
    fwrite($fp, $time.$text);
    fclose($fp);

    echo $time.nl2br($text);
}

/**
 * 
 * MAIN PROGRAM
 * updatovany tickety od 2023-03-12 01:52:41
 */
    mylog("START BATCH \n");
    $time_start = microtime(true);

    $importLimit = (isset($_GET['limit']) ? ($_GET['limit']+0) : 1) ;

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $mysqli = new mysqli($GLOBALS['config_tmpDB_hostname'], $GLOBALS['config_tmpDB_user'], $GLOBALS['config_tmpDB_pwd'], $GLOBALS['config_tmpDB_db'],3306);
    $mysqli->set_charset('utf8');

    $helpdesk  = new \Ipex\Helpdesk\IpexHelpdesk($GLOBALS['config_hlp_url'],$GLOBALS['config_hlp_user'],$GLOBALS['config_hlp_pwd']);

    $command = "SELECT datum_update FROM ".$GLOBALS['config_tmpDB_ticketsToUpdateCounter'];
    //echo $command."<BR/>";
    $lastUpdate = $mysqli->query($command)->fetch_row();

    $command = "SELECT * FROM ".$GLOBALS['config_tmpDB_ticketsToUpdate']." WHERE ticketChanged < '".$lastUpdate['0']."' ORDER BY ticketChanged DESC LIMIT ".$importLimit;
    //echo $command."<BR/>";
    $tickets = $mysqli->query($command);
    
    foreach($tickets as $key => $ticket) {

        $ticketCode = $ticket["laTicketID"];
        mylog($ticketCode."\n");
        if($ticket['ticketChanged'] === $ticket['ticketCreated']) {
            mylog($ticketCode." Neni zjistena zmena date Changed = Created. Ukazatel nastaven na ".$ticket['ticketChanged']."\n");
            //posuneme ukazatel uz updatovanych
            $commandUpdate = "UPDATE ".$GLOBALS['config_tmpDB_ticketsToUpdateCounter']." SET datum_update='".$ticket["ticketChanged"]."'";
            //echo $commandUpdate."<BR/>\n";
            $mysqli->query($commandUpdate);
            continue;
        }

        $hooknmResult = call_hooknm($ticketCode);  
        mylog($hooknmResult."\n");

        if(stripos($hooknmResult,'V Helpdesku nebyl') ) {
            mylog($ticketCode." Hledam ticket importovany pres XML\n");

            $command2 = "SELECT * FROM ".$GLOBALS['config_tmpDB_HlpTickets']." WHERE laTicketID = '".$ticketCode."' LIMIT 1";
            //echo $command2."<BR/>\n";
            $oldTicket = $mysqli->query($command2)->fetch_row();
            $oldTicketId = $oldTicket[0];

            if(isset($oldTicketId)) {
                mylog($ticketCode." V pomocne DB nalezen stary ticket ".$oldTicket[4]." (".$oldTicketId.")\n");

                $helpdesk->addTicketTag($oldTicket[0],7);  //7=tag smazat
                mylog($ticketCode." Oznacen stary ticket ".$oldTicket[4]." tagem ke smazani\n");

                mylog($ticketCode." Import pres Hooknt\n");
                $hookntResult = call_hooknt($ticketCode);  
                mylog($hookntResult."\n");
            } 
            else {
                mylog($ticketCode." V pomocne DB nebyl nalezen stary ticket, neni mozne ho oznacit tagem na Smazat. Ignorujeme\n");
            }
        }
        else {
        }

        //posuneme ukazatel uz updatovanych
        mylog($ticketCode." Nastaven ukazatel na ".$ticket['ticketChanged']."\n");
        $commandUpdate = "UPDATE ".$GLOBALS['config_tmpDB_ticketsToUpdateCounter']." SET datum_update='".$ticket["ticketChanged"]."'";
        //echo $commandUpdate."<BR/>\n";
        $mysqli->query($commandUpdate);
        sleep(1);
    }
    $time_end = microtime(true);

    mylog("Finished in ".round($time_end - $time_start)." sec - total\n");
    mylog("FINISH BATCH \n");
?>
