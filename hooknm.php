<?php
/**
 * obsluha webhooku LA ticket update
 * Cilem je, aby se do ticketu v Helpdesku pripsala message s prilohami, podle message v LA ktera zpusobila hook update
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
    $fp = fopen("hooknm.log", "a");
    fwrite($fp, $time.$text);
    fclose($fp);

    echo $time.nl2br($text);
}

//Indexy naimportovanych LA messages jsou ulozeny v 
$customFormFieldId = $GLOBALS['config_hlp_custom_form_field_id'];
$customFormId = $GLOBALS['config_hlp_custom_form_id'];

// overeni vstupu
if(!isset($_GET['ticketCode'])) {
    mylog("Chybi povinny parametr ticketCode.");
    exit;
}

//mereni doby behu programu
$time_start = microtime(true);

try {

$searchTicketCode = $_GET['ticketCode'];


//pockame nahodne dlouhou pauzu v ramci jedne sekundy
$delayMicroSec = rand(5,1000);

mylog($searchTicketCode." ".$delayMicroSec." START \n");

//musime pockat 2sec na dokonceni zalozeni ticketu, pokud prisel webhook na update drive nez webhook na create
sleep(2);
mylog($searchTicketCode." Delay time: 2sec - waiting for create finishing\n");

mylog($searchTicketCode." ".$delayMicroSec." Delay time: ".$delayMicroSec." micsec \n");
usleep($delayMicroSec);

// zjistime zda je uzamceno jinym procesem
if(file_exists("./lock/tmp_lock_".$searchTicketCode.".lck")) {
    mylog($searchTicketCode." ".$delayMicroSec." Nalezen zamek z jineho procesu pro stejny ticketCode. \n");
    mylog($searchTicketCode." ".$delayMicroSec." FINISH \n");
    exit;
}
// nastavime zamek
if(!is_dir("./lock")) {
     mkdir("./lock", 0777, true);
}
$fp = fopen("./lock/tmp_lock_".$searchTicketCode.".lck", "w");
fwrite($fp, "LOCK");
fclose($fp);
mylog($searchTicketCode." ".$delayMicroSec." Zamek nastaven \n");

$helpdesk  = new \Ipex\Helpdesk\IpexHelpdesk($GLOBALS['config_hlp_url'],$GLOBALS['config_hlp_user'],$GLOBALS['config_hlp_pwd']);
$liveagent = new \Liveagent\Liveagent($GLOBALS['config_api_url'],$GLOBALS['config_api_key']);

// zjistime jake LA message uz jsou v ticketu naimportovany. Indexy naimportovanych LA messages jsou ulozeny v customFormFieldu v ticketu
$ticks=$helpdesk->searchTickets(0,10,"(LA:".$searchTicketCode.")");
if(!isset($ticks->Tickets->Items[0]->TicketREF)) {
    mylog($searchTicketCode." ".$delayMicroSec." V Helpdesku nebyl nalezen ticket obsahujici v subjektu (LA:".$searchTicketCode."), ale zkusime to jeste jednou za 3 sec.\n");
    sleep(3);
    $ticks=$helpdesk->searchTickets(0,10,"(LA:".$searchTicketCode.")");
    if(!isset($ticks->Tickets->Items[0]->TicketREF)) {
        mylog($searchTicketCode." ".$delayMicroSec." V Helpdesku nebyl ani na druhy pokus nalezen ticket obsahujici v subjektu (LA:".$searchTicketCode.")\n");
        // uvolnime zamek
        exec("rm -f "."./lock/tmp_lock_".$searchTicketCode.".lck");
        mylog($searchTicketCode." ".$delayMicroSec." Zamek uvolnen \n");
        mylog($searchTicketCode." ".$delayMicroSec." FINISH \n");
        exit;
    }
    else {
        mylog($searchTicketCode." ".$delayMicroSec." Nalezen ticket: ".$ticks->Tickets->Items[0]->TicketREF." (opakovany pokus)\n");
    }
}
else {
    mylog($searchTicketCode." ".$delayMicroSec." Nalezen ticket: ".$ticks->Tickets->Items[0]->TicketREF." \n");
}

$hlpTicket = $helpdesk->getTicket($ticks->Tickets->Items[0]->TicketREF);
$customFormField77 = $helpdesk->getTicketCustomFormFieldById($hlpTicket->CustomForms,$customFormId,$customFormFieldId);
mylog($searchTicketCode." ".$delayMicroSec." Load indexes: ".$customFormField77->TextBoxValue." \n");
$messagesIndexesImported = explode(',',$customFormField77->TextBoxValue); //seznam jiz naimportovanych messages
$hlpTicketServiceIdOld = $hlpTicket->ServiceId; //ve ktere fronte v HLP je nyni
$hlpTicketStateOld = $hlpTicket->TicketState; //v jakem stavu zustal ticket v HLP
$hlpTicketStateAvailable = $hlpTicket->AvailableTicketActions; //jake stavy mohou byt nastaveny (array)
//print_r($hlpTicket);

// nacteme LA ticket
$laTickets = $liveagent->getTicket($searchTicketCode);
$laTicketServiceIdNew = $liveagent->convertDepartmentToService($laTickets[0]['departmentid']); //ve ktere fronte ma byt podle LA ticketu
$laTicketStateNew = $laTickets[0]['status']; //v jakem stavu je nyni LA ticket
//print_r($laTickets);

//stavy a jejich zmeny v Helpdesku
//301 = In queue 
//  -> 301 ServiceRequestTakeFromQueue (prejde na 305)
//305 = Ready for support
//  -> 307 ServiceRequestSuspend
//  -> 314 ServiceRequestFinishSolutionAndClose (prejde na 312)
//  -> 311 ServiceRequestPostpone (prejde na 308)
//  -> 331 ServiceRequestReturnToQueue (prejde na 301)
//308 = ServiceRequestPostponed
//  -> 312 ServiceRequestCancelPostponement (prejde na 305)
//  -> 331 ServiceRequestReturnToQueue (prejde na 301)
//312 = ServiceRequestClosed
//  -> 319 ServiceRequestReactivateToSolution (prejde na 305)
//
//klicovaci tabulka jak zmenit stavy v HLP podle noveho stavu v LA
//I - init N - new T - chatting P - calling R - resolved X - deleted B - spam A - answered C - open W - postponed
// R/301 -> 301 -> 314
// R/305 -> 314
// R/308 -> 312 -> 314
// R/312 x
//
// A/301 -> 301 -> 314
// A/305 -> 314
// A/308 -> 312 -> 314
// A/312 x
//
// W/301 -> 301 -> 311
// W/305 -> 311
// W/308 x
// W/312 -> 319 -> 311
//
// C/301 x
// C/305 -> 331
// C/308 -> 331
// C/312 -> 319 -> 331
//
// N/301 x
// N/305 -> 331
// N/308 -> 331
// N/312 -> 319 -> 331
$klicovaniStavu["R"][301] = array(301,314);
$klicovaniStavu["R"][305] = array(314);
$klicovaniStavu["R"][308] = array(312,314);
$klicovaniStavu["R"][312] = array();

$klicovaniStavu["A"][301] = array(301,314);
$klicovaniStavu["A"][305] = array(314);
$klicovaniStavu["A"][308] = array(312,314);
$klicovaniStavu["A"][312] = array();

$klicovaniStavu["W"][301] = array(301,311);
$klicovaniStavu["W"][305] = array(311);
$klicovaniStavu["W"][308] = array();
$klicovaniStavu["W"][312] = array(319,311);

$klicovaniStavu["C"][301] = array();
$klicovaniStavu["C"][305] = array(331);
$klicovaniStavu["C"][308] = array(331);
$klicovaniStavu["C"][312] = array(319,331);

$klicovaniStavu["N"][301] = array();
$klicovaniStavu["N"][305] = array(331);
$klicovaniStavu["N"][308] = array(331);
$klicovaniStavu["N"][312] = array(319,331);

//protoze potrebujeme pred prepnutim do finalniho stavu provest nejake ukony, prevedeme ticket nejprve na 305 a po provedeni oprav ho prepneme z 305 do finalniho stavu
//klicovaci tabulka pro prepnuti do 305 (nasledne prepnuti do finalniho stavu probehne podle tabulky vyse, vyuzije se ale jen definice ze stavu 305 dale)
$klicovaniNaStav305[301] = array(301);
$klicovaniNaStav305[305] = array();
$klicovaniNaStav305[308] = array(312);
$klicovaniNaStav305[312] = array(319);

mylog($searchTicketCode." ".$delayMicroSec." Stav HLP ticketu: ".$hlpTicketStateOld."-".$helpdesk->explainTicketState($hlpTicketStateOld)." \n");
mylog($searchTicketCode." ".$delayMicroSec." Stav LA ticketu: ".$laTicketStateNew."-".$liveagent->explainTicketStatus($laTicketStateNew)." \n");

//uvedeme HLP ticket do stavu, kdy je mozne zmenit sluzbu, prepneme ticket do stavu 305
foreach ($klicovaniNaStav305[$hlpTicketStateOld] as $akce) {
    $helpdesk->workflowPush($hlpTicket->TicketId,null,$akce);
    mylog($searchTicketCode." ".$delayMicroSec." Stav HLP ticketu zmenen na 305 prikazem: ".$akce."-".$helpdesk->explainTicketWorkflowAction($akce)." \n");
}

//nyni muzeme delat upravy ticketu

// pokud doslo k presunu ticketu do jine fronty
if($hlpTicketServiceIdOld !== $laTicketServiceIdNew) {
    mylog($searchTicketCode." ".$delayMicroSec." V LA ticketu je jine nastaveni departmentu nez je sluzba v Helpdesku, bude provedena zmena sluzby z ".$hlpTicketServiceIdOld." na ".$laTicketServiceIdNew." \n");
    $res = $helpdesk->ticketChangeService($hlpTicket->TicketId,$laTicketServiceIdNew);

}

$laMessages = $liveagent->getTicketMessages($laTickets[0]['id']);
//print_r($messages);


    if(isset($laMessages) && $messages['message'] != 'Service Unavailable') {

        $messagesIndexesNew = array();

        //pripravime message do HLP
        foreach($laMessages as $message) {

            // ignorujeme vsechny zpravy, ktere uz ticket v Helpdesku obsahuje
            if(in_array($message['id'],$messagesIndexesImported)) {
                mylog($searchTicketCode." ".$delayMicroSec." Ignore message: ".$message['id']."\n");
                continue;
            }

            mylog($searchTicketCode." ".$delayMicroSec." Save message: ".$message['id']."\n");
            $messagesIndexesNew[] = $message['id'];

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
                                "Data" => $liveagent->attachmentDownload($fileMetadata['download_url'],$fileMetadata['size'])
                            );
                    }
                }
            } 
            //print_r($attachmentsArray);
            $result = $helpdesk->newMessage($ticks->Tickets->Items[0]->TicketREF,0,$messageFinal,$isMessageHtml,$isPrivate,$attachmentsArray);

        }

        //zapiseme novy seznam indexu messages ktere byly naimportovany
        mylog($searchTicketCode." ".$delayMicroSec." Save indexes: ".implode(',',array_merge($messagesIndexesImported,$messagesIndexesNew))."\n");
        $res = $helpdesk->updateCustomForm($hlpTicket->TicketId,$customFormId,$hlpTicket->ServiceId,array ( array ("CustomFormFieldId" => $customFormFieldId, "TextBoxValue" => implode(',',array_merge($messagesIndexesImported,$messagesIndexesNew)))));

        //zmenime sluzbu na stejnou jako ma nyni LA ticket
        foreach ($klicovaniStavu[$laTicketStateNew][305] as $akce) {
            $helpdesk->workflowPush($hlpTicket->TicketId,null,$akce);
            mylog($searchTicketCode." ".$delayMicroSec." Stav HLP ticketu zmenen z 305 prikazem: ".$akce."-".$helpdesk->explainTicketWorkflowAction($akce)." \n");
        }
        
    }

    // uvolnime zamek
    exec("rm -f "."./lock/tmp_lock_".$searchTicketCode.".lck");
    mylog($searchTicketCode." ".$delayMicroSec." Zamek uvolnen \n");

    mylog($searchTicketCode." ".$delayMicroSec." FINISH \n");
} catch(Exception $e) {
    mylog($searchTicketCode." ".$delayMicroSec." Error: ".$e->getMessage()." \n");
}
  
$time_end = microtime(true);

echo "Finished in ".round($time_end - $time_start)." sec";