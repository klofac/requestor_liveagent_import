<?php
namespace Ipex\Helpdesk;

/**
 * PHP Class for IPEX Helpdesk API
 */
class IpexHelpdesk {
    private string $apiUrl;
    private string $apiLogin;
    private string $apiPassword;

    public function __construct(string $apiUrl, string $apiLogin, string $apiPassword) {
        $this->apiUrl       = $apiUrl;
        $this->apiLogin     = $apiLogin;
        $this->apiPassword  = $apiPassword;
    }

    /**
     * API Call 
     * @param string $method  GET/POST/PUT
     * @param string $command e.g. Tickets/GetTicket/2233
     * @param string $body    JSON
     */
    public function apiCall(string $method="GET", string $command, string $body="") {

        $cht = curl_init($this->apiUrl."api/".$command);
        
        curl_setopt($cht, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cht, CURLOPT_HEADER, 0);
        curl_setopt($cht, CURLOPT_HTTPHEADER, 
            array(
                'Content-type: application/json',
                'Authorization: Basic '.base64_encode($this->apiLogin.":".$this->apiPassword)
            )
        );

        switch ($method) {
            case 'POST':
                    curl_setopt($cht, CURLOPT_POST, true);
                    curl_setopt($cht, CURLOPT_POSTFIELDS, $body);
                break;
            
            case 'PUT':
                    curl_setopt($cht, CURLOPT_PUT, true);
                break;
            
            default:
                break;
        }

        // execute
        $curlOutput = curl_exec($cht);

            if(curl_error($cht)) {
               echo "ERROR: ".curl_error($cht);
            }

        curl_close($cht);

        return json_decode($curlOutput);
    }

    /**
     * getTicket
     * @param string $id Id or TicketREF of the Ticket.
     */
    public function getTicket(string $id) {

        if(isset($id)) {
            return $this->apiCall("GET","Tickets/GetTicket/".$id);
        }
        else {
            return false;
        }
    }

    /**
     * newAnonymousTicket
     */
    public function newAnonymousTicket(string $email, int $ticketType, int $serviceId, string $subject, string $message, bool $isMessageHtml = false) {

        $body["Email"]              =$email;
        $body["InternalGroupId"]    =null;
        $body["Subject"]            =$subject;
        $body["Message"]            =$message;
        $body["IsMessageHtml"]      =$isMessageHtml;
        $body["IsMobileDevice"]     =false;
        $body["TicketType"]         =$ticketType;
        $body["ServiceId"]          =$serviceId;
        $body["Attachments"]        =null;

        return $this->apiCall("POST","Tickets/NewAnonymousTicket",json_encode($body));

    }

    /**
     * newMessage
     * @param string    $ticketRef  (Required) The reference ID of the ticket to which add a new message. You can use TicketId instead.
     * @param int       $ticketId   (Required) The unique ID of the ticket to which add a new message. You can use TicketREF instead.
     * @param string    $message    (Required) The message body
     * @param bool      $isMessageHtml  If true, sent message is in HTML format
     * @param bool      $isPrivate  If true, the message is marked as a private one. Private messages is visible only for Operators and can be send by Operators only.
     */
    public function newMessage(
        string $ticketRef = "", 
        int $ticketId = 0, 
        string $message, 
        bool $isMessageHtml = false, 
        bool $isPrivate = false,
        $attachments = array(
                array(
                    "FileName" => "",
                    "ContentType" => "",
                    "ContentLength" => 0,
                    "Data" => "" 
                )
            )
        ) 
        {
    
        if(isset($attachments)) {
            foreach ($attachments as $file) {
                $attachmentsRes[] = array(
                    "FileName" => $file["FileName"],
                    "ContentType" => $file["ContentType"],
                    "ContentLength" => $file["ContentLength"],
                    "Data" => base64_encode($file["Data"]) 
                );
            }
        }
        else {
            $attachmentsRes = null;
        }

        $body["TicketREF"]      = ($ticketRef == "" ? null : $ticketRef);
        $body["TicketId"]       = ($ticketId == 0 ? null : $ticketId);
        $body["Message"]        = $message;
        $body["Html"]           = $isMessageHtml;
        $body["IsPrivate"]      = $isPrivate;
        $body["IsMobileDevice"] = false;
        $body["Attachments"]    = $attachmentsRes;

        //print_r(json_encode($body));
        return $this->apiCall("POST","Tickets/NewMessage",json_encode($body));

    }

    /**
     * searchTickets
     * @param int $pageIndex Default 0
     * @param int $pageSize Default 10
     * @param string $searchText Required
     */
    public function searchTickets(int $pageIndex = 0, int $pageSize = 10, string $searchText) {
        $body["PageIndex"]      = $pageIndex;
        $body["PageSize"]       = $pageSize;
        $body["Value"]          = $searchText;

        //print_r(json_encode($body));
        return $this->apiCall("POST","Tickets/SearchTickets",json_encode($body));
    }
}

