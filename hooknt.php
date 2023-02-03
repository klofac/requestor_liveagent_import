<?php
/**
 * obsluha webhooku LA ticket create
 * Cilem je, aby se do ticketu v Helpdesku vytvoril novy ticket s message a prilohami, podle ticketu v LA
 */
require "config.php";
require "ipex_helpdesk.php";
require "liveagent.php";

//Indexy naimportovanych LA messages jsou ulozeny v 
$customFormFieldId = 77;
$customFormId = 26;

// overeni vstupu
if(!isset($_GET['ticketCode'])) die("Chybi povinny parametr ticketCode.");

// todo: sleep pockat az automatizace dodela operace nad novym ticketem a naimportujeme i interni komentare

//mereni doby behu programu
$time_start = microtime(true);

$searchTicketCode = $_GET['ticketCode'];

$helpdesk  = new \Ipex\Helpdesk\IpexHelpdesk($GLOBALS['config_hlp_url'],$GLOBALS['config_hlp_user'],$GLOBALS['config_hlp_pwd']);
$liveagent = new \Liveagent\Liveagent($GLOBALS['config_api_url'],$GLOBALS['config_api_key']);

$laTickets = $liveagent->getTicket($searchTicketCode);
//print_r($laTickets);
$messages = $liveagent->getTicketMessages($laTickets[0]['id']);
//print_r($messages);


foreach($laTickets as $ticket) {
    //vybereme na zpracovani jen emaily
    if($ticket['channel_type'] != 'E') {   
        echo "Ignored ".$ticket['code']." (ticket type=".$ticket['channel_type'].") <BR/>\n";                             
        continue;
    }

    //statusy: I - init N - new T - chatting P - calling R - resolved X - deleted B - spam A - answered C - open W - postponed
    //ignorujeme spam a deleted
    if($ticket['status'] == 'B' || $ticket['status'] == 'X') {          
        echo "Ignored ".$ticket['code']." (ticket status=".$ticket['status'].") <BR/>\n";                             
        continue;
    }

    $is_closed = isset($ticket['date_resolved']);

    if(!isset($ticket['owner_email']) || $ticket['owner_email'] == "") {
       $ticket['owner_email'] = $GLOBALS['config_def_user'];
    }
    else {
        if(!filter_var($ticket['owner_email'], FILTER_VALIDATE_EMAIL)) {
            echo "Excluded ".$ticket['code']." (owner has invalid email address) <BR/>\n";                             
    //todo            writeExcludedTickets("webhook_excludedTickets.csv",$ticket['id'].",".$ticket['code'],$exportExcludedFileName);
            continue;
        }                    
    }

    $subject    = $ticket['subject']." (LA:".$ticket['code'].")";
    $message    = 'Importováno z '.$ticket['code'];
    $email      = $ticket['owner_email'];
    $internalGroupId = null;
    $isMessageHtml = false;
    $ticketType = 3;
    $attachments = null;
    //'OperatorUserName', $GLOBALS['config_def_user']);
    //Source, 'Email');

    //kontrola, ze se podarilo nejake message nacist a kdyz ne tak hodime do jine fronty 
    if(isset($messages) && $messages['message'] != 'Service Unavailable') {
        $serviceId = $liveagent->convertDepartmentToService((isset($ticket['departmentid']) ? $ticket['departmentid'] : ''));
    }
    else {
        $serviceId = 8; //'Kontrola importu z LA'
    }

    // vyvtorime ticket v Helpdesku
    $newHlpTicket = $helpdesk->newAnonymousTicket($email,$ticketType,$serviceId,$subject,$message,$isMessageHtml);
    //print_r($newHlpTicket);    
    
    if(isset($messages) && $messages['message'] != 'Service Unavailable') {

        $messagesIndexesNew = array();

        //pripravime message do HLP
        foreach($messages as $message) {
            
            $messagesIndexesNew[] = $message['id'];

            $isMessageHtml = true;                    //bude vzdy HTML a zdrojove data pripadne z textu prevadime na HTML
            $isPrivate = $liveagent->isInternalType($message['type']);  //privatni jen interni komenty jinak public aby se ukazala tabulka v HTML

            //ktere statusy se maji zpracovat: D - DELETED P - PROMOTED V - VISIBLE S - SPLITTED M - MERGED I - INITIALIZING R - CONNECTING C - CALLING
            $hasAttachments = false;
            $attachmentsArray = null;
            
            //poskladame z jednotlivych casti jako jednu zpravu
            $messageFinal =  "LaTime:".$message['datecreated']."<BR/>"; 
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
                        $messageFinal .= nl2br(htmlentities((isset($messagePart['message']) && $messagePart['message'] != '' ? $messagePart['message'] : '-')))."<BR/>";
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
                                "Data" => $liveagent->attachmentDownload($fileMetadata['view_url'],$fileMetadata['size'])
                            );
                    }
                }
            } 
            //print_r($attachmentsArray);
            $result = $helpdesk->newMessage("",$newHlpTicket->TicketId,$messageFinal,$isMessageHtml,$isPrivate,$attachmentsArray);

        }

        //echo "New indexes: ".implode(',',array_merge($messagesIndexesImported,$messagesIndexesNew))."<BR/>\n";
        $res = $helpdesk->updateCustomForm($newHlpTicket->TicketId,$customFormId,$serviceId,array ( array ("CustomFormFieldId" => $customFormFieldId, "TextBoxValue" => implode(',',$messagesIndexesNew))));

    }
}
  
$time_end = microtime(true);

echo "Finished in ".round($time_end - $time_start)." sec";