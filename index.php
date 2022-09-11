<?php
require "config.php";

/**
 * API Call LiveAgent 
 */
function apicall_LA(string $command) : array {

    $output = array();

    $cht = curl_init($GLOBALS['config_api_url'].$command);
    // $fp = fopen("api_result.txt", "w");
    
    // curl_setopt($cht, CURLOPT_FILE, $fp);
    curl_setopt($cht, CURLOPT_HEADER, 0);
    curl_setopt($cht, CURLOPT_HTTPHEADER, array(
        'Content-type: application/json',
        'apikey:'.$GLOBALS['config_api_key']
    ));
    curl_setopt($cht, CURLOPT_RETURNTRANSFER, true);

    $curloutput = curl_exec($cht);

    if(curl_error($cht)) {
       // fwrite($fp, curl_error($cht));
        echo "ERROR: ".curl_error($cht);
    }

    $output = json_decode($curloutput, true);

    curl_close($cht);
    // fclose($fp);
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
 * Prevede definici pole ve stringu na assoc pole
 */
function attachmentMetaDecode($source) {

    $sourceArray = json_decode($source);
    array_shift($sourceArray); // odmazeme prvni radek s hlavickami sloupcu

    foreach($sourceArray as $row) {
        $output[$row['0']] = $row['1']; // preformatujeme na assoc pole
    }

    return $output;
}

/**
 * Stahne prilohu do archivu do domluvene struktury
 */
function attachmentDownload($foldername,$filename,$downloadUrl) {
    $cht = curl_init(str_replace("//","https://",stripslashes($downloadUrl)));

    if(!is_dir("./Import/".$foldername)) {
        mkdir("./Import/".$foldername, 0777, true);
    }

    $fp = fopen("./Import/".$foldername."/".$filename, "w");
    
    curl_setopt($cht, CURLOPT_FILE, $fp);
    curl_setopt($cht, CURLOPT_HEADER, 0);

    curl_exec($cht);

    if(curl_error($cht)) {
        fwrite($fp, curl_error($cht));
        echo "ERROR: ".curl_error($cht);
    }

    curl_close($cht);
    fclose($fp);
    return $output; 

}

function createCsvMissingUsers($usersImportFileName) {

    // RQ users
    $row = 1;
    $RQusers = [];

    if (($fp = fopen("./MirrorUsers/ExportUsersRQ.csv", "r")) !== FALSE) {
        while (($data = fgetcsv($fp, 1000, ";")) !== FALSE) {
            if($row == 1) {  // nezpracujeme prvni radek s hlavickou csv
                $row++;
                continue;
            }

            $RQusers[$data[10]] = $data[10];
            //print_r($data) . "<br />\n";
            //echo $data[10] . "<br />\n";
            $row++;

            //if($row > 5) break;
        }
        fclose($fp);


    }
    echo "ExportUsersRQ.csv nacteno radku: ".($row-1)." <BR/>";
    echo "Nalezeno uzivatelu RQ: ".count($RQusers)." <BR/>";

    // LA users
    $row = 1;
    $LAusers = [];

    if (($fp = fopen("./MirrorUsers/full_customers_LA.csv", "r")) !== FALSE) {
        while (($data = fgetcsv($fp, 1000, ",")) !== FALSE) {
            if($row == 1) {  // nezpracujeme prvni radek s hlavickou csv
                $row++;
                continue;
            }

            $LAusers[$data[4]] = $data[4];
            //print_r($data) . "<br />\n";
            //echo $data[4] . "<br />\n";
            $row++;

            //if($row > 5) break;
        }
        fclose($fp);

    }
    echo "Full_customers_LA.csv nacteno radku: ".($row-1)." <BR/>";
    echo "Nalezeno uzivatelu LA: ".count($LAusers)." <BR/>";

    $rozdil = array_diff($LAusers,$RQusers);
    $rozdilCount = count($rozdil);
    echo "Chybejicich uzivatelu uzivatelu LA v RQ: ".$rozdilCount." <BR/>";

    if($rozdilCount > 0) {
        $fpRQimport = fopen($usersImportFileName, "w");

        sort($rozdil);

        $row = 1;
        foreach($rozdil as $missingUser) {
            //print_r($missingUser);
            if($missingUser !== '') {
                fwrite($fpRQimport,$missingUser."\n");
            }
            $row++;
            //if($row > 5) break;
        }
        
        fclose($fpRQimport);
    }


}

/**
 * 
 */
function writeExcludedTickets($fileName,$row) {
    
    $fp = fopen($fileName, "a");
        fwrite($fp,$row."\n");
    fclose($fp);

    // zapis casti velkeho excluded listu
    $fp = fopen($exportExcludedFileName."part", "w");
        fwrite($fp,$row."\n");
    fclose($fp);

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
 * slouci xmlBody do xmlDoc do finalniho souboru
 */
function prepareFinalXmlDoc($fnameFinal,$exportAllFileName) {
    $xmlDoc     = file_get_contents($exportAllFileName.".xmlDoc");
    $xmlBody    = file_get_contents($exportAllFileName.".xmlBody");
    $xml        = str_replace('<magic>abracadabra</magic>',$xmlBody,$xmlDoc);
    
    // ulozime xml do finalniho souboru
    $fp = fopen($fnameFinal, "w");
    fwrite($fp, $xml);
    fclose($fp);
    
    // uklidime
    exec("rm -f ".$exportAllFileName.".xmlBody");
    exec("rm -f ".$exportAllFileName.".xmlDoc");
}

/**
 * slouci xmlBody do xmlDoc do finalniho souboru
 */
function commitDataToCumulDocs($exportXmlAllFileName,$exportExcludedFileName,$exportMissingRqUsersFileName) {

    // xml import ticketu
    if(file_exists($exportXmlAllFileName."part")) {
        $xml     = file_get_contents($exportXmlAllFileName."part");
        
        $fp = fopen($exportXmlAllFileName, "a");
        fwrite($fp, $xml);
        fclose($fp);

        exec("rm -f ".$exportXmlAllFileName."part");
    }   

    // import useru
    if(file_exists($exportMissingRqUsersFileName."part")) {
        $xml     = file_get_contents($exportMissingRqUsersFileName."part");
        
        $fp = fopen($exportMissingRqUsersFileName, "a");
        fwrite($fp, $xml);
        fclose($fp);

        exec("rm -f ".$exportMissingRqUsersFileName."part");
    }   

    // excluded tickety
    if(file_exists($exportExcludedFileName."part")) {
        $xml     = file_get_contents($exportExcludedFileName."part");
        
        $fp = fopen($exportExcludedFileName, "a");
        fwrite($fp, $xml);
        fclose($fp);

        exec("rm -f ".$exportExcludedFileName."part");
    }   
}

/**
 * 
 * MAIN PROGRAM
 * 
 */
//mereni doby behu programu
$time_start = microtime(true);


$users = array();       //zde posbirame uzivatele pouzite v ticketech a na zaver se je pokusime naimportovat do RQ aby pri spusteni XML importu uz vzdy byli dostupni

$ticket_from    = (isset($_GET['from']) ? $_GET['from'] : '0' );
$ticket_to      = (isset($_GET['to']) ? $_GET['to'] : '1' );
$userCompareOn  = (isset($_GET['userCompareOn']) ? true : false );
$searchTicketByCode = (isset($_GET['ticketCode']) ? true : false );
$searchTicketCode   = $_GET['ticketCode'];
$downloadXML        = (isset($_GET['download']) && $_GET['download'] == 'XML' ? true : false );
$downloadFiles      = (isset($_GET['download']) && $_GET['download'] == 'FILES' ? true : false );
$commitDataToCumulFiles = (isset($_GET['commitData']) ? true : false );
$exportFilename     = "ImportXML/".($searchTicketByCode ? "export_ticket_".$searchTicketCode : "export_from".$ticket_from."_to".$ticket_to);
$exportAllFileName  = "ImportXML/export_ticket_all";
$exportExcludedFileName = "ImportXML/excluded_tickets_all.csv";
$exportMissingRqUsersFileName = "ImportXML/missingRqUsersAll.csv";

//porovna vsechny uzivatele RQ a LA a pripravi csv pro import chybejicich pokud je v url pozadovano
if($userCompareOn) {
   createCsvMissingUsers("exportAllMissingRqUsers.csv");
}
  
// vrati zip XML
if($downloadXML) {
    $fname = "ImportXML_from".$ticket_from."_to".$ticket_to.".zip";
    prepareFinalXmlDoc("ImportXML/ImportXML_from".$ticket_from."_to".$ticket_to."_all.xml",$exportAllFileName);
    exec("zip -P ".$GLOBALS['config_zip_pwd']." -r ".$fname." ImportXML");
    exec("rm -f ImportXML/*");
    // send the right headers
    $fp = fopen($fname, 'rb');
    header("Content-Type: application/zip");
    header("Content-Length: " . filesize($fname));
    header('Content-Disposition: attachment; filename="'.$fname.'"');
    fpassthru($fp);
    exec("rm ".$fname);
    exit;    
}

// vrati zip Files
if($downloadFiles) {
    $fname = "ImportFiles_from".$ticket_from."_to".$ticket_to.".zip";
    exec("zip -P ".$GLOBALS['config_zip_pwd']." -r ".$fname." Import");
    exec("rm -f -r Import/*");
    // send the right headers
    $fp = fopen($fname, 'rb');
    header("Content-Type: application/zip");
    header("Content-Length: " . filesize($fname));
    header('Content-Disposition: attachment; filename="'.$fname.'"');
    fpassthru($fp);
    exec("rm ".$fname);
    exit;    
}

// prida cast dat do kumulovanych souboru
if($commitDataToCumulFiles) {
    commitDataToCumulDocs($exportAllFileName.".xmlBody",$exportExcludedFileName,$exportMissingRqUsersFileName);
    exit;
}

// Vytvorime importni XML
// nacteme RQ users, abchom mohli porovnavat zda reporter ticketu je v RQ k dispozici
$row = 1;
$RQusers = [];
$RQusersToImport = [];

if (($fp = fopen("./MirrorUsers/ExportUsersRQ.csv", "r")) !== FALSE) {
    while (($data = fgetcsv($fp, 1000, ";")) !== FALSE) {
        if($row == 1) {  // nezpracujeme prvni radek s hlavickou csv
            $row++;
            continue;
        }

        $RQusers[$data[10]] = $data[10];
        //print_r($data) . "<br />\n";
        //echo $data[10] . "<br />\n";
        $row++;

        //if($row > 5) break;
    }
    fclose($fp);
}


// definice XML
$xw = xmlwriter_open_memory();
xmlwriter_set_indent($xw, 1);
$res = xmlwriter_set_indent_string($xw, ' ');

$xwDoc = xmlwriter_open_memory();
xmlwriter_set_indent($xwDoc, 1);
$res2 = xmlwriter_set_indent_string($xwDoc, ' ');

 xmlwriter_start_document($xwDoc, '1.0', 'UTF-8');

    //-- element
    xmlwriter_start_element($xwDoc, 'RequestorImport');
        //-- atributes
        xmlwriter_start_attribute($xwDoc, 'xmlns:xsd');
        xmlwriter_text($xwDoc, 'http://www.w3.org/2001/XMLSchema');
        xmlwriter_end_attribute($xwDoc);
        xmlwriter_start_attribute($xwDoc, 'xmlns:xsi');
        xmlwriter_text($xwDoc, 'http://www.w3.org/2001/XMLSchema-instance');
        xmlwriter_end_attribute($xwDoc);

        //-- element
        xmlwriter_start_element($xwDoc, 'FormatVersion');
            xmlwriter_text($xwDoc, '2');
        xmlwriter_end_element($xwDoc); // FormatVersion

        xmlwriter_write_comment($xwDoc, 'Sekce Users zrusena. Uzivatele musi byt navedeni do RQ pred spustenim XML importu. Budou tedy pro import vzdy existovat.');

        //-- element
        xmlwriter_start_element($xwDoc, 'Tickets');
            //-- element
            xmlwriter_start_element($xwDoc, 'magic');
                xmlwriter_text($xwDoc, 'abracadabra');
            xmlwriter_end_element($xwDoc); // magic element bude nahrazen xmlBody


            $tickets = array();
            
            // nacteme tickety
            if($searchTicketByCode) {
                $tickets = apicall_LA("tickets?_filters={\"code\":\"".$searchTicketCode."\"}");
            }
            else {
                $tickets = apicall_LA("tickets?_from=".$ticket_from."&_to=".$ticket_to);  
            }
//print_r($tickets);

            foreach($tickets as $ticket) {
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

                //neimportovat otevrene, zapamatovat si index a doimportovat pozdeji
                if(!$is_closed) {
                    echo "Excluded ".$ticket['code']." (ticket is OPEN) <BR/>\n";                             
                    writeExcludedTickets($exportFilename."_excludedTickets.csv",$ticket['id'].",".$ticket['code']);
                    continue;
                }

                if(!isset($ticket['owner_email']) || $ticket['owner_email'] == "") {
                   $ticket['owner_email'] = $GLOBALS['config_def_user'];
                }

                // schovame si uzivatele, abychom ho pak na konci naimportili do RQ pres API
                $users[$ticket['owner_contactid']]['email'] = $ticket['owner_email']; 
                $users[$ticket['owner_contactid']]['name']  = $ticket['owner_name']; 

                if(!isset($RQusers[$ticket['owner_email']])) {
                    $RQusersToImport[$ticket['owner_email']] = $ticket['owner_email'];
                }

                //-- element
                xmlwriter_start_element($xw, 'Ticket');
                    //-- element
                    xmlwriter_start_element($xw, 'Type');
                        xmlwriter_text($xw, 'ServiceRequest');
                    xmlwriter_end_element($xw); // Type

                    //-- element
                    xmlwriter_start_element($xw, 'CreatedUTC');
                        xmlwriter_text($xw, date_format(DateTimeImmutable::createFromFormat("Y-m-d H:i:s", $ticket['date_created']), 'Y-m-d\TH:i:sP')); 
                    xmlwriter_end_element($xw); // CreatedUTC

                    if($is_closed) {
                        //-- element
                        xmlwriter_start_element($xw, 'ClosedUTC');
                            xmlwriter_text($xw, date_format(DateTimeImmutable::createFromFormat("Y-m-d H:i:s", $ticket['date_resolved']), 'Y-m-d\TH:i:sP')); 
                        xmlwriter_end_element($xw); // ClosedUTC
                    }
                    else {
                        //-- element
                        xmlwriter_start_element($xw, 'ClosedUTC');
                            //-- atributes
                            xmlwriter_start_attribute($xw, 'xsi:nil');
                                xmlwriter_text($xw, 'true');
                            xmlwriter_end_attribute($xw);
                        xmlwriter_end_element($xw); // ClosedUTC
                    }

                    //-- element
                    xmlwriter_start_element($xw, 'Subject');
                        xmlwriter_text($xw, $ticket['subject']);
                    xmlwriter_end_element($xw); // Subject

                    //-- element
                    xmlwriter_start_element($xw, 'FirstMessage');
                        xmlwriter_text($xw, 'Importováno z '.$ticket['code']);
                    xmlwriter_end_element($xw); // FirstMessage

                    //-- element
                    xmlwriter_start_element($xw, 'FirstMessageIsHtml');
                        xmlwriter_text($xw, 'false');
                    xmlwriter_end_element($xw); // FirstMessageIsHtml

                    //-- element
                    xmlwriter_start_element($xw, 'ReportedByUserName');
                        xmlwriter_text($xw, $ticket['owner_email']);
                    xmlwriter_end_element($xw); // ReportedByUserName

                    //-- element
                    xmlwriter_start_element($xw, 'OperatorUserName');
                        xmlwriter_text($xw, $GLOBALS['config_def_user']);
                    xmlwriter_end_element($xw); // OperatorUserName

                    //-- element
                    xmlwriter_start_element($xw, 'Source');
                        xmlwriter_text($xw, 'Email');
                    xmlwriter_end_element($xw); // Source

                    //-- element
                    xmlwriter_start_element($xw, 'TicketState');
                        xmlwriter_text($xw, ($is_closed ? 'ServiceRequestClosed' : 'ServiceRequestInQueue'));
                    xmlwriter_end_element($xw); // TicketState

                    // nacteme messages k ticketu
                    $messages = apicall_LA("tickets/".$ticket['id']."/messages?includeQuotedMessages=true&page=1&_perPage=200");
//print_r($messages);
                    //kontrola, ze se podarilo nejake message nacist a kdyz ne tak hodime do jine fronty 
                    if(isset($messages) && $messages['message'] != 'Service Unavailable') {
                        //-- element
                        xmlwriter_start_element($xw, 'ServiceName');
                            //nasmerovat do spravne sluzby ServiceName podle [departmentid]
                            xmlwriter_text($xw, convertDepartmentToService((isset($ticket['departmentid']) ? $ticket['departmentid'] : '')));
                        xmlwriter_end_element($xw); // ServiceName
                    }
                    else {
                        //-- element
                        xmlwriter_start_element($xw, 'ServiceName');
                            //nasmerovat do sluzby pro vadne tickety
                            xmlwriter_text($xw, 'Kontrola importu z LA');
                        xmlwriter_end_element($xw); // ServiceName
                    }

                    if(isset($messages) && $messages['message'] != 'Service Unavailable') {
                        //-- element
                        xmlwriter_start_element($xw, 'Messages');

                            foreach($messages as $message) {
                                //-- element
                                xmlwriter_start_element($xw, 'Message');
                                    //-- element
                                    xmlwriter_start_element($xw, 'CreatedUTC');
                                        xmlwriter_text($xw, date_format(DateTimeImmutable::createFromFormat("Y-m-d H:i:s", $message['datecreated']), 'Y-m-d\TH:i:sP')); 
                                    xmlwriter_end_element($xw); // CreatedUTC

                                    //-- element
                                    xmlwriter_start_element($xw, 'MessageIsHtml'); 
                                        xmlwriter_text($xw, 'true');                    //bude vzdy HTML a zdrojove data pripadne z textu prevadime na HTML
                                    xmlwriter_end_element($xw); // MessageIsHtml

                                    //-- element
                                    xmlwriter_start_element($xw, 'IsPrivate');
                                        xmlwriter_text($xw, isInternalType($message['type']));  //privatni jen interni komenty jinak public aby se ukazala tabulka v HTML
                                    xmlwriter_end_element($xw); // MessageIsHtml

                                    //ktere statusy se maji zpracovat: D - DELETED P - PROMOTED V - VISIBLE S - SPLITTED M - MERGED I - INITIALIZING R - CONNECTING C - CALLING
                                    //-- element
                                    xmlwriter_start_element($xw, 'Message');
                                        $hasAttachments = false;

                                        foreach($message['messages'] as $messagePart) {
                                            if($messagePart['message'] != "" ) { 
                                            
                                                // odstranime CDATA v datech, aby se to nervalo s nasim CDATA
                                                $messagePart['message'] = str_replace('<![CDATA[','',$messagePart['message']);
                                                $messagePart['message'] = str_replace(']]>','',$messagePart['message']);

                                                if($messagePart['type']=='Q') {
                                                    
                                                    //xmlwriter_write_cdata($xw, $messagePart['message']);
                                                    //xmlwriter_text($xw, "<BR/>");
                                                    xmlwriter_write_cdata($xw, html_entity_decode($messagePart['message']));
                                                } 
                                                elseif($messagePart['type']=='F') {
                                                    $hasAttachments = true;
                                                }
                                                elseif($messagePart['format']=='H') {
                                                        xmlwriter_write_cdata($xw, $messagePart['message']."<BR/>");
                                                }
                                                else {
                                                        xmlwriter_write_cdata($xw, nl2br(htmlentities($messagePart['message']))."<BR/>");
                                                }
                                            }
                                        }                            

                                    xmlwriter_end_element($xw); // Message

                                    if($hasAttachments ) { 
                                        //-- element
                                        xmlwriter_start_element($xw, 'Attachments');
                                            foreach($message['messages'] as $messagePart) {
                                                if($messagePart['type']=='F') {

                                                    $filemetadata = attachmentMetaDecode($messagePart['message']);
                                                    attachmentDownload($ticket['id'],$filemetadata['id'],$filemetadata['download_url']); 

                                                    //-- element
                                                    xmlwriter_start_element($xw, 'ImportTicketMessageAttachment');
                                                        //-- element
                                                        xmlwriter_start_element($xw, 'Path');
                                                                xmlwriter_write_cdata($xw, "C:\\Import\\".$ticket['id']."\\".$filemetadata['id']); 
                                                        xmlwriter_end_element($xw); // Path
                                                        //-- element
                                                        xmlwriter_start_element($xw, 'FileName');
                                                                xmlwriter_text($xw, $filemetadata['name']); 
                                                        xmlwriter_end_element($xw); // FileName
                                                        //-- element
                                                        xmlwriter_start_element($xw, 'ContentType');
                                                                xmlwriter_text($xw, $filemetadata['type']); 
                                                        xmlwriter_end_element($xw); // ContentType
                                                        //-- element
                                                        xmlwriter_start_element($xw, 'ContentLength');
                                                                xmlwriter_text($xw, $filemetadata['size']); 
                                                        xmlwriter_end_element($xw); // ContentLength

                                                    xmlwriter_end_element($xw); // ImportTicketMessageAttachment
                                                }
                                            }

                                        xmlwriter_end_element($xw); // Attachments
                                    }                            

                                xmlwriter_end_element($xw); // Message
                            }

                        xmlwriter_end_element($xw); // Messages
                    }

                xmlwriter_end_element($xw); // Ticket
            }

        xmlwriter_end_element($xwDoc); // Tickets

    xmlwriter_end_element($xwDoc); // RequestorImport

xmlwriter_end_document($xwDoc);

$xmlBody    = xmlwriter_output_memory($xw);
$xmlDoc     = xmlwriter_output_memory($xwDoc);
$xml        = str_replace('<magic>abracadabra</magic>',$xmlBody,$xmlDoc);

//print_r($xml);
//print_r($users);

// ulozime xml do souboru
$fp = fopen($exportFilename.".xml", "w");
fwrite($fp, $xml);
fclose($fp);
echo "Tickety vyexportovany do ".$exportFilename.".xml <BR/>\n";

// ulozime body XML do velkeho souboru, hlavicku a paticku dostane az pri downloadu
$fp = fopen($exportAllFileName.".xmlBody"."part", "w");
fwrite($fp, $xmlBody);
fclose($fp);
$fp = fopen($exportAllFileName.".xmlDoc", "w");
fwrite($fp, $xmlDoc);
fclose($fp);

//zapiseme uzivatele chybejici v RQ a pouzite v prave exportovanem xml
if(count($RQusersToImport) > 0) {
    $fpRQimport = fopen($exportFilename."_missingRqUsers.csv", "w");

    sort($RQusersToImport);
    $missingUser = implode("\n",$RQusersToImport);

    fwrite($fpRQimport,$missingUser);
    fclose($fpRQimport);
    echo "Chybejici uzivatele vyexportovani do ".$exportFilename."_missingRqUsers.csv <BR/>";

    // zapiseme do soupisky za vsechny exporty dokud neprobehne download XML
    $fpRQimport = fopen($exportMissingRqUsersFileName."part", "w");
    fwrite($fpRQimport,$missingUser."\n");
    fclose($fpRQimport);

}

$time_end = microtime(true);

echo "Finished in ".round($time_end - $time_start)." sec";
?>
