<?php
namespace Liveagent;

/**
 * PHP Class for Liveagent API
 */
class Liveagent {
    private string $apiUrl;
    private string $apiKey;

    public function __construct(string $apiUrl, string $apiKey) {
        $this->apiUrl       = $apiUrl;
        $this->apiKey       = $apiKey;
    }

    /**
     * API Call LiveAgent 
     */
    private function apicall_LA(string $command) : array {

        $output = array();

        $cht = curl_init($this->apiUrl.$command);
        
        // curl_setopt($cht, CURLOPT_FILE, $fp);
        curl_setopt($cht, CURLOPT_HEADER, 0);
        curl_setopt($cht, CURLOPT_HTTPHEADER, array(
            'Content-type: application/json',
            'apikey:'.$this->apiKey
        ));
        curl_setopt($cht, CURLOPT_RETURNTRANSFER, true);

        $curloutput = curl_exec($cht);

        if(curl_error($cht)) {
            echo "ERROR: ".curl_error($cht);
        }

        $output = json_decode($curloutput, true);

        curl_close($cht);

        return $output;    
    }

    /**
     * Vyhodnoti zda se jedna o interni zpravu
     */
    public function isInternalType($messageType) : string {
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
                    $output = true;
                    break;
                
                default:
                    $output = false;
                    break;
            }
            return $output;
        }
        else {
            return false;
        }
    }

    /**
     * 
     */
    public function convertDepartmentToService($departmentId) {

        switch ($departmentId) {
            case 'ceb5937b':
                $result = 6; //'Zbraně';
                break;
            
            case 'f417e7e6':
                $result = 3; //'Nákup';
                break;
            
            case 'tv401zrk':
                $result = 4; //'Velkoobchod';
                break;
        
            case 'wuub36n4':
                $result = 7; //'Marketing';
                break;
                        
            default:
                $result = 5; //'Zakázky';
                break;
        }

        return $result;
    }

    /**
     * getTicket
     * @param string $searchTicketCode
     */
    public function getTicket(string $searchTicketCode): array {

        $ticket = $this->apicall_LA("tickets?_filters={\"code\":\"".$searchTicketCode."\"}");  
        return $ticket;
    }   

    /**
     * getTicketMessages
     * @param string $ticketId
     */
    public function getTicketMessages(string $ticketId): array {

        $messages = $this->apicall_LA("tickets/".$ticketId."/messages?includeQuotedMessages=true&page=1&_perPage=200");  
        return $messages;
    }   


    /**
     * Prevede definici pole ve stringu na assoc pole
     */
    public function attachmentMetaDecode($source) {

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
    public function attachmentDownload($downloadUrl,$expectedFileSize) {
    
        $cht = curl_init(str_replace("/view","/download",str_replace("/api/v3/","",$this->apiUrl.$downloadUrl)));
        // curl_setopt($cht, CURLOPT_FILE, $fp);
        curl_setopt($cht, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($cht, CURLOPT_HEADER, 0);
        curl_setopt($cht, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($cht, CURLOPT_VERBOSE, 1);
        curl_setopt($cht, CURLOPT_HTTPHEADER, array(
            'apikey:'.$this->apiKey
        ));    

        $fileContent = curl_exec($cht);

        if(curl_error($cht)) {
            echo "ERROR: ".curl_error($cht);
        }

        curl_close($cht);

        return $fileContent;

//todo        // oznamime kdyz nesedi stazena a ocekavana delka
/*
        if(filesize("./Import/".$foldername."/".$filename) != $expectedFileSize) {
            echo "Ticket: ".$ticketCode." Corrupted file: "."./Import/".$foldername."/".$filename." Saved: ".filesize("./Import/".$foldername."/".$filename)." bytes, expected:".$expectedFileSize." bytes\n";
            echo $report."<BR/>";
        }
    */
    }    
}