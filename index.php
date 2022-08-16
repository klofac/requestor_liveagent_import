<?php
require "config.php";

function apicall(string $command) : array 
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

// main program
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

        //xmlwriter_write_comment($xw, 'this is a comment.');

        xmlwriter_start_element($xw, 'Tickets');

            $tickets = array();

            // nacteme tickety
            $tickets = apicall("tickets?_from=0&_to=1");

            foreach($tickets as $ticket)
            {
                //-- element
                xmlwriter_start_element($xw, 'Ticket');
                    //-- element
                    xmlwriter_start_element($xw, 'Type');
                        xmlwriter_text($xw, 'ServiceRequest');
                    xmlwriter_end_element($xw); // Type

                    //-- element
                    xmlwriter_start_element($xw, 'ClosedUTC');
                        //-- atributes
                        xmlwriter_start_attribute($xw, 'xsi:nil');
                            xmlwriter_text($xw, 'true');
                        xmlwriter_end_attribute($xw);
                    xmlwriter_end_element($xw); // ClosedUTC

                    //-- element
                    xmlwriter_start_element($xw, 'Subject');
                        xmlwriter_text($xw, $ticket['subject']);
                    xmlwriter_end_element($xw); // Subject

                    // nacteme messages k ticketu
                    $messages = apicall("tickets/".$ticket['id']."/messages");
                    print_r($messages);



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

?>