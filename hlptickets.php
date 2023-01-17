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
        foreach($messages as $key => $message) {
            if($key == 0) {
                $output = substr(str_replace("<p>","",str_replace("</p>","",str_replace("Importováno z ","",$message->Body))),0,25);
                break;
            }
        }
    } else {
        $output = "Nezjisteno";
    }    
    return($output);
}

/**
 * MAIN
 */
    $nacistTicketu = 30;
    
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $mysqli = new mysqli($GLOBALS['config_tmpDB_hostname'], $GLOBALS['config_tmpDB_user'], $GLOBALS['config_tmpDB_pwd'], $GLOBALS['config_tmpDB_db'],3306);
    $mysqli->set_charset('utf8');

    $tMax = $mysqli->query("SELECT max(ticketID) AS ticketIDmax FROM ".$GLOBALS['config_tmpDB_table']);
    $tRes = $tMax->fetch_object();
    $tIDmax = (isset($tRes->ticketIDmax) ? $tRes->ticketIDmax : 1);

    for($i=$tIDmax+1;$i<$tIDmax+$nacistTicketu+1;$i++) {
        $ticket = apicall_HLP("Tickets/GetTicket/".$i);
        //print_r($ticket);
        $laTicketID = getTicketLA($ticket->UserMessages);
        echo $laTicketID.",".$ticket->TicketREF.",".$ticket->TicketId.",".$ticket->ServiceName.",".$ticket->CreatedUTC."<BR/>\n";
        $mysqli->query("INSERT INTO ".$GLOBALS['config_tmpDB_table']." SET ticketID ='".$i."', ticketREF='".$ticket->TicketREF."', ticketSluzba='".$ticket->ServiceName."', ticketSluzbaID='".$ticket->ServiceId."', ticketCreated='".$ticket->CreatedUTC."', laTicketID='".$laTicketID."'");
    }
