<?php
require "config.php";

/**
 * API Call Helpdesk 
 */
function apicall_HLP(string $command) {


    $cht = curl_init($GLOBALS['config_hlp_url']."api/".$command);
    
    // curl_setopt($cht, CURLOPT_FILE, $fp);
    curl_setopt($cht, CURLOPT_HEADER, 0);
    curl_setopt($cht, CURLOPT_HTTPHEADER, 
        array(
            'Content-type: application/json',
            'Authorization: Basic '.base64_encode($GLOBALS['config_hlp_user'].":".$GLOBALS['config_hlp_pwd'])
        )
    );
    curl_setopt($cht, CURLOPT_RETURNTRANSFER, true);

    $curloutput = curl_exec($cht);

    if(curl_error($cht)) {
        echo "ERROR: ".curl_error($cht);
    }

    $output = json_decode($curloutput);

    curl_close($cht);
    return $output;    
}

/**
 * 
 */
function getTicketLA($messages) {
    $output = "";

    if(isset($messages)) {
        
        $indexMin = $messages[0]->Id;
        $firstMessageKey = 0;
        //echo "START firstMessageKey=".$firstMessageKey.", indexMin=".$indexMin."\n";

        //najdeme message s nejnizsim ID, to bude first message
        foreach($messages as $key => $message) {
            if($message->Id < $indexMin ) {
                $indexMin = $message->Id;
                $firstMessageKey = $key;
            }
         //   echo "key=".$key.", index=".$message->Id.", firstMessageKey=".$firstMessageKey.", indexMin=".$indexMin." ".($indexMin != $messages[0]->Id ? "POZOR" : "" )."\n";
        }
        $output = htmlentities(substr(str_replace("<p>","",str_replace("</p>","",str_replace("Importováno z ","",$messages[$firstMessageKey]->Body))),0,25));

    } else {
        $output = "Nezjisteno";
    }    
    return($output);
}

/**
 * MAIN
 */
    $nacistTicketu = $GLOBALS['config_hlp_nacist_ticketu'];
    
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $mysqli = new mysqli($GLOBALS['config_tmpDB_hostname'], $GLOBALS['config_tmpDB_user'], $GLOBALS['config_tmpDB_pwd'], $GLOBALS['config_tmpDB_db'],3306);
    $mysqli->set_charset('utf8');

    $tMax = $mysqli->query("SELECT max(ticketID) AS ticketIDmax FROM ".$GLOBALS['config_tmpDB_table']);
    $tRes = $tMax->fetch_object();
    $tIDmax = (isset($tRes->ticketIDmax) ? $tRes->ticketIDmax : 1);

    $time_start = microtime(true);

    for($i=$tIDmax+1;$i<$tIDmax+$nacistTicketu+1;$i++) {
        $ticket = apicall_HLP("Tickets/GetTicket/".$i);
        //print_r($ticket);
        //pojistka az narazime na konec
        if(isset($ticket->UserMessages)) {
            $laTicketID = getTicketLA($ticket->UserMessages);
            echo $ticket->TicketId.",".$ticket->TicketREF.",".$ticket->ServiceName.",".$ticket->CreatedUTC.",".$laTicketID."<BR/>\n";
            $mysqli->query("INSERT INTO ".$GLOBALS['config_tmpDB_table']." SET ticketID ='".$i."', ticketREF='".$ticket->TicketREF."', ticketSluzba='".$ticket->ServiceName."', ticketSluzbaID='".$ticket->ServiceId."', ticketCreated='".$ticket->CreatedUTC."', laTicketID='".$laTicketID."'");
        }
    }

    $time_end = microtime(true);

    echo "Finished in ".round($time_end - $time_start)." sec";