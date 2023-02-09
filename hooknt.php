<?php
/**
 * obsluha webhooku LA ticket create
 * Cilem je, aby se do ticketu v Helpdesku vytvoril novy ticket s message a prilohami, podle ticketu v LA
 */
require "config.php";
require "ipex_helpdesk.php";
require "liveagent.php";

date_default_timezone_set('Europe/Prague');

/**
 * 
 */
function mylog($text) {
    $time = date("Y-m-d H:i:s")." ";
    $fp = fopen("hooknt.log", "a");
    fwrite($fp, $time.$text);
    fclose($fp);

    echo $time.nl2br($text);
}

//Indexy naimportovanych LA messages jsou ulozeny v 
$customFormFieldId = $GLOBALS['config_hlp_custom_form_field_id'];
$customFormId = $GLOBALS['config_hlp_custom_form_id'];
$problemsQueueId = $GLOBALS['config_hlp_problems_queue_id'];

// overeni vstupu
if(!isset($_GET['ticketCode'])) die("Chybi povinny parametr ticketCode.");

// todo: sleep pockat az automatizace dodela operace nad novym ticketem a naimportujeme i interni komentare

//mereni doby behu programu
$time_start = microtime(true);

$searchTicketCode = $_GET['ticketCode'];

mylog($searchTicketCode." START \n");

$helpdesk  = new \Ipex\Helpdesk\IpexHelpdesk($GLOBALS['config_hlp_url'],$GLOBALS['config_hlp_user'],$GLOBALS['config_hlp_pwd']);
$liveagent = new \Liveagent\Liveagent($GLOBALS['config_api_url'],$GLOBALS['config_api_key']);

$laTickets = $liveagent->getTicket($searchTicketCode);
//print_r($laTickets);
$messages = $liveagent->getTicketMessages($laTickets[0]['id']);
//print_r($messages);


foreach($laTickets as $ticket) {
    //vybereme na zpracovani jen emaily
    if($ticket['channel_type'] != 'E') {   
        mylog($searchTicketCode." Ignored ".$ticket['code']." (ticket type=".$ticket['channel_type'].")\n");                             
        continue;
    }

    //statusy: I - init N - new T - chatting P - calling R - resolved X - deleted B - spam A - answered C - open W - postponed
    //ignorujeme spam a deleted
    if($ticket['status'] == 'B' || $ticket['status'] == 'X') {          
        mylog($searchTicketCode." Ignored ".$ticket['code']." (ticket status=".$ticket['status'].")\n");                             
        continue;
    }

    $is_closed = isset($ticket['date_resolved']);

    if(!isset($ticket['owner_email']) || $ticket['owner_email'] == "") {
       $ticket['owner_email'] = $GLOBALS['config_def_user'];
    }
    else {
        if(!filter_var($ticket['owner_email'], FILTER_VALIDATE_EMAIL)) {
            mylog($searchTicketCode." Excluded ".$ticket['code']." (owner has invalid email address) \n");                             
            continue;
        }                    
    }

    $subject    = $ticket['subject']." (LA:".$ticket['code'].")";
    $message    = 'Importováno z '.$ticket['code'];
    $email      = (!isset($ticket['owner_email']) || $ticket['owner_email'] === "" ? $GLOBALS['config_def_user'] : $ticket['owner_email'] );
    $internalGroupId = null;
    $isMessageHtml = false;
    $ticketType = 3;
    $attachments = null;

    //kontrola, ze se podarilo nejake message nacist a kdyz ne tak hodime do jine fronty 
    if(isset($messages) && $messages['message'] != 'Service Unavailable') {
        $serviceId = $liveagent->convertDepartmentToService((isset($ticket['departmentid']) ? $ticket['departmentid'] : ''));
    }
    else {
        $serviceId = $problemsQueueId; //'Kontrola importu z LA'
    }

    // -------------------------------------
    //TODO Odstranit po spusteni do produkce, jen pro testy na ipex-test
    $serviceId = 45;//jen pro ipex-test, na produkci zrusit
    // -------------------------------------
    
    // vyvtorime ticket v Helpdesku
    $newHlpTicket = $helpdesk->newAnonymousTicket($email,$ticketType,$serviceId,$subject,$message,$isMessageHtml);
    //print_r($newHlpTicket);    
    mylog($searchTicketCode." Vytvoren ticket: ".$newHlpTicket->TicketREF." ve sluzbe: ".$serviceId." \n");

    if(isset($messages) && $messages['message'] != 'Service Unavailable') {

        $messagesIndexesNew = array();

        //pripravime message do HLP
        foreach($messages as $message) {
            
            $messagesIndexesNew[] = $message['id'];
            //mylog($searchTicketCode." Save message: ".$message['id']."\n");

            $isMessageHtml = true;                    //bude vzdy HTML a zdrojove data pripadne z textu prevadime na HTML
            $isPrivate = $liveagent->isInternalType($message['type']);  //privatni jen interni komenty jinak public aby se ukazala tabulka v HTML

            //ktere statusy se maji zpracovat: D - DELETED P - PROMOTED V - VISIBLE S - SPLITTED M - MERGED I - INITIALIZING R - CONNECTING C - CALLING
            $hasAttachments = false;
            $attachmentsArray = null;

            //upravime cas pro zonu Europe/Prague
            $dateLa = DateTime::createFromFormat("Y-m-d H:i:s", $message['datecreated'],new DateTimeZone('America/Phoenix'));
            $dateLa->setTimezone(new DateTimeZone('Europe/Prague'));
            $messageFinal = "LaTime:".date_format($dateLa, 'Y-m-d H:i:s')."<BR/>\n"; 
            
            //poskladame z jednotlivych casti jako jednu zpravu
            foreach($message['messages'] as $messagePart) {
                if($messagePart['message'] != "" ) { 
                
                    if($messagePart['type']=='Q') {
                        $messageFinal .= $messagePart['message'];
                    } 
                    elseif($messagePart['type']=='F') {
                        $hasAttachments = true;
                    }
                    elseif($messagePart['format']=='H') {
                        $messageFinal .= $messagePart['message']."<BR/>";
                    }
                    else {
                        $messageFinal .= nl2br(htmlentities((isset($messagePart['message']) && $messagePart['message'] !== '' ? $messagePart['message'] : '-')))."<BR/>";
                    }
                }
            }                            
            //print_r($messageFinal);

            if($hasAttachments ) { 
                foreach($message['messages'] as $messagePart) {
                    if($messagePart['type']=='F') {

                        $fileMetadata = $liveagent->attachmentMetaDecode($messagePart['message']); 

                        $attachmentsArray[] = 
                            array(
                                "FileName" => $fileMetadata['name'],
                                "ContentType" => $fileMetadata['type'],
                                "ContentLength" => $fileMetadata['size'],
                                "Data" => $liveagent->attachmentDownload($fileMetadata['download_url'],$fileMetadata['size'])
                            );
                    }
                }
            } 
            //print_r($attachmentsArray);
            $result = $helpdesk->newMessage(null,$newHlpTicket->TicketId,$messageFinal,$isMessageHtml,$isPrivate,$attachmentsArray);

        }

        //echo "New indexes: ".implode(',',array_merge($messagesIndexesImported,$messagesIndexesNew))."<BR/>\n";
        $res = $helpdesk->updateCustomForm($newHlpTicket->TicketId,$customFormId,$serviceId,array ( array ("CustomFormFieldId" => $customFormFieldId, "TextBoxValue" => implode(',',$messagesIndexesNew))));
        mylog($searchTicketCode." Save indexes: ".implode(',',$messagesIndexesNew)." \n");

    }

    // pokud je LA ticket Closed tak zavreme i HLP ticket
    if ($is_closed) {
        $res1 = $helpdesk->workflowPush($newHlpTicket->TicketId,null,301,null,null); //vezmeme z fronty
        $res2 = $helpdesk->workflowPush($newHlpTicket->TicketId,null,314,null,null); //zavreme
        mylog($searchTicketCode." Stav HLP ticketu zmenen prikazem: 301-".$helpdesk->explainTicketWorkflowAction(301)." \n");
        mylog($searchTicketCode." Stav HLP ticketu zmenen prikazem: 314-".$helpdesk->explainTicketWorkflowAction(314)." \n");
    }
    else {
        mylog($searchTicketCode." Stav HLP ticketu zustava: Ve fronte \n");
    }
}

mylog($searchTicketCode." FINISH \n");

$time_end = microtime(true);

echo "Finished in ".round($time_end - $time_start)." sec";