<?php
/**
 * obsluha webhooku LA ticket update
 * Cilem je, aby se do ticketu v Helpdesku pripsala message s prilohami, podle message v LA ktera zpusobila hook update
 */
require "config.php";
require "ipex_helpdesk.php";
require "liveagent.php";

//Indexy naimportovanych LA messages jsou ulozeny v 
$customFormFieldId = 77;
$customFormId = 26;

// overeni vstupu
if(!isset($_GET['ticketCode'])) die("Chybi povinny parametr ticketCode.");

//mereni doby behu programu
$time_start = microtime(true);


$searchTicketCode = $_GET['ticketCode'];

$helpdesk  = new \Ipex\Helpdesk\IpexHelpdesk($GLOBALS['config_hlp_url'],$GLOBALS['config_hlp_user'],$GLOBALS['config_hlp_pwd']);
$liveagent = new \Liveagent\Liveagent($GLOBALS['config_api_url'],$GLOBALS['config_api_key']);

// zjistime jake LA message uz jsou v ticketu naimportovany. Indexy naimportovanych LA messages jsou ulozeny v customFormFieldu v ticketu
$ticks=$helpdesk->searchTickets(0,10,"(LA:".$searchTicketCode.")");
$hlpTicket = $helpdesk->getTicket($ticks->Tickets->Items[0]->TicketREF);
$customFormField77 = $helpdesk->getTicketCustomFormFieldById($hlpTicket->CustomForms,$customFormId,$customFormFieldId);
$messagesIndexesImported = explode(',',$customFormField77->TextBoxValue);

$laTickets = $liveagent->getTicket($searchTicketCode);
//print_r($laTickets);
$laMessages = $liveagent->getTicketMessages($laTickets[0]['id']);
//print_r($messages);


    if(isset($laMessages) && $messages['message'] != 'Service Unavailable') {

        $messagesIndexesNew = array();

        //pripravime message do HLP
        foreach($laMessages as $message) {

            // ignorujeme vsechny zpravy, ktere uz ticket v Helpdesku obsahuje
            if(in_array($message['id'],$messagesIndexesImported)) {
                echo "Ignore: ".$message['id']."<BR/>\n";
                continue;
            }

            echo "Save: ".$message['id']."<BR/>\n";
            $messagesIndexesNew[] = $message['id'];

            $isMessageHtml = true;                    //bude vzdy HTML a zdrojove data pripadne z textu prevadime na HTML
            $isPrivate = $liveagent->isInternalType($message['type']);  //privatni jen interni komenty jinak public aby se ukazala tabulka v HTML

            //ktere statusy se maji zpracovat: D - DELETED P - PROMOTED V - VISIBLE S - SPLITTED M - MERGED I - INITIALIZING R - CONNECTING C - CALLING
            $hasAttachments = false;
            $attachmentsArray = null;
            
            //poskladame z jednotlivych casti jako jednu zpravu
            $messageFinal = "LaTime:".$message['datecreated']."<BR/>"; 
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
            $result = $helpdesk->newMessage($ticks->Tickets->Items[0]->TicketREF,0,$messageFinal,$isMessageHtml,$isPrivate,$attachmentsArray);

        }

        //echo "New indexes: ".implode(',',array_merge($messagesIndexesImported,$messagesIndexesNew))."<BR/>\n";
        $res = $helpdesk->updateCustomForm($hlpTicket->TicketId,$customFormId,$hlpTicket->ServiceId,array ( array ("CustomFormFieldId" => $customFormFieldId, "TextBoxValue" => implode(',',array_merge($messagesIndexesImported,$messagesIndexesNew)))));

    }

  
$time_end = microtime(true);

echo "Finished in ".round($time_end - $time_start)." sec";