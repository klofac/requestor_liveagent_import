<?php
require "config.php";

/**
 * API Call LiveAgent 
 */
function apicall_LA(string $command) : array 
{

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
function isInternalType($messageType) : string
{
    if(isset($messageType)) {
        switch ($messageType) {
            case 'I':
            case 'U':
            case 'R':
            case 'Z':
            case 'T':
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

// main program
$users = array();       //zde posbirame uzivatele pouzite v ticketech a na zaver se je pokusime naimportovat do RQ aby pri spusteni XML importu uz vzdy byli dostupni

// definice XML
$xw = xmlwriter_open_memory();
xmlwriter_set_indent($xw, 1);
$res = xmlwriter_set_indent_string($xw, ' ');
xmlwriter_start_document($xw, '1.0', 'UTF-8');

    //-- element
    xmlwriter_start_element($xw, 'RequestorImport');
        //-- atributes
        xmlwriter_start_attribute($xw, 'xmlns:xsd');
        xmlwriter_text($xw, 'http://www.w3.org/2001/XMLSchema');
        xmlwriter_end_attribute($xw);
        xmlwriter_start_attribute($xw, 'xmlns:xsi');
        xmlwriter_text($xw, 'http://www.w3.org/2001/XMLSchema-instance');
        xmlwriter_end_attribute($xw);

        //-- element
        xmlwriter_start_element($xw, 'FormatVersion');
            xmlwriter_text($xw, '2');
        xmlwriter_end_element($xw); // FormatVersion

        xmlwriter_write_comment($xw, 'Sekce Users zrusena. Uzivatele musi byt navedeni pres API RQ pred spustenim XML importu a tedy budou vzdy existovat.');

        //-- element
        xmlwriter_start_element($xw, 'Tickets');

            $tickets = array();

            // nacteme tickety
            $tickets = apicall_LA("tickets?_from=30&_to=31");
//print_r($tickets);

            foreach($tickets as $ticket)
            {
                //todo: nasmerovat do spravne sluzby ServiceName  [departmentid] => wuub36n4

                //vybereme na zpracovani jen emaily
                if($ticket['channel_type'] != 'E') {                                
                    continue;
                }

                //ignorujeme spam a deleted
                if($ticket['status'] == 'B' || $ticket['status'] == 'X') {          
                    continue;
                }

                // schovame si uzivatele, abychom ho pak na konci naimportili do RQ pres API
                $users[$ticket['owner_contactid']]['email'] = $ticket['owner_email']; 
                $users[$ticket['owner_contactid']]['name']  = $ticket['owner_name']; 

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

                    //-- element
                    xmlwriter_start_element($xw, 'ClosedUTC');
                        //-- atributes
                        /*
                        xmlwriter_start_attribute($xw, 'xsi:nil');
                            xmlwriter_text($xw, 'true');
                        xmlwriter_end_attribute($xw);
                        */
                        xmlwriter_text($xw, date_format(DateTimeImmutable::createFromFormat("Y-m-d H:i:s", $ticket['date_resolved']), 'Y-m-d\TH:i:sP')); 
                    xmlwriter_end_element($xw); // ClosedUTC

                    //-- element
                    xmlwriter_start_element($xw, 'ServiceName');
                        xmlwriter_text($xw, '02 NOC');
                    xmlwriter_end_element($xw); // ServiceName

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
                        xmlwriter_text($xw, 'admin@ipex.cz');
                    xmlwriter_end_element($xw); // OperatorUserName

                    //-- element
                    xmlwriter_start_element($xw, 'Source');
                        xmlwriter_text($xw, 'Email');
                    xmlwriter_end_element($xw); // Source

                    //-- element
                    xmlwriter_start_element($xw, 'TicketState');
                        xmlwriter_text($xw, 'ServiceRequestClosed');
                    xmlwriter_end_element($xw); // TicketState

                    //-- element
                    xmlwriter_start_element($xw, 'Messages');
                        // nacteme messages k ticketu
                        $messages = apicall_LA("tickets/".$ticket['id']."/messages?includeQuotedMessages=true");
//print_r($messages);

                        foreach($messages as $message)
                        {
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
                                    xmlwriter_text($xw, isInternalType($message['type']));                    //todo: privatni jen interni komenty jinak public aby se ukazala tabulka v HTML
                                xmlwriter_end_element($xw); // MessageIsHtml

                                //-- element
                                xmlwriter_start_element($xw, 'Message');
                                    foreach($message['messages'] as $messagePart)
                                    {
                                        if($messagePart['message'] != "" )  //&& ($messagePart['type']=='H' || $messagePart['type']=='M' || $messagePart['type']=='Q')
                                        {
                                            if($messagePart['type']=='Q')
                                            {
                                                
                                                //xmlwriter_write_cdata($xw, $messagePart['message']);
                                                //xmlwriter_text($xw, "<BR/>");
                                                xmlwriter_write_cdata($xw, html_entity_decode($messagePart['message']));
                                            } 
                                            else
                                            {
                                                if($messagePart['format']=='H')
                                                {
                                                    xmlwriter_write_cdata($xw, $messagePart['message']."<BR/>");
                                                }
                                                else
                                                {
                                                    xmlwriter_write_cdata($xw, nl2br(htmlentities($messagePart['message']))."<BR/>");
                                                }
                                            }
                                        }
                                    }                            
                                xmlwriter_end_element($xw); // Message

                            xmlwriter_end_element($xw); // Message


                        }





                    xmlwriter_end_element($xw); // Messages



                xmlwriter_end_element($xw); // Ticket
            }

            /*
                    // CDATA
                    xmlwriter_start_element($xw, 'testc');
                        xmlwriter_write_cdata($xw, "This is cdata content");
                    xmlwriter_end_element($xw); // testc
            */

        xmlwriter_end_element($xw); // Tickets
    xmlwriter_end_element($xw); // RequestorImport

xmlwriter_end_document($xw);

$xml = xmlwriter_output_memory($xw);

print_r($xml);
//print_r($users);

?>