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

    /**
     * getTicketCustomFormFieldById
     * @param array $ticketCustomFormFieldArray     Custom Form Field Array from getTicket result (e.g. $hlpTicket->CustomForms[0]->CustomFormFieldsData)
     * @param int   $customFormId                   ID of Custom Form
     * @param int   $customFormFieldId              ID of Custom Form Field which you are searching
     */
    public function getTicketCustomFormFieldById(array $ticketCustomFormFieldArray, int $customFormId, int $customFormFieldId) {
        foreach ($ticketCustomFormFieldArray as $formKey => $formArray) {
            if($formArray->CustomFormId === $customFormId) {
                foreach ($formArray->CustomFormFieldsData as $key => $value) {
                    if($value->CustomFormFieldId === $customFormFieldId) {
                        return $value;
                        break;    
                    }
                }
            }
        }
    }

    /**
     * updateCustomForm Updates ticket's custom form fields. You can send one or more ticket custom form field values to update. If you need to get the custom form definition first, use GetCustomFormsForTicket.
     * @param int   $ticketId                The ticket Id.
     * @param int   $customFormId            The custom form Id.
     * @param int   $serviceId               The Service id must be filled or category item id.
     * @param int   $categoryItemId          The Service id must be filled or category item id.
     * @param array $customFormFieldsData    The data. If you don't send the custom form field record at all, the field won't be edited.
     *         {
     *           "CustomFormFieldId": int,
     *           "TextBoxValue": string,
     *           "DecimalValue": decimal number,
     *           "IntegerValue": int,
     *           "CheckBoxValue": boolean,
     *           "DateValue": date,
     *           "SelectedCustomFormFieldItemId": integer,
     *           "CustomFormFieldItems": [      //CheckBoxList
     *             { 
     *              "CustomFormFieldItemId": int
     *              "CheckBoxValue": boolean
     *             }
     *           ]
     *         }
     */
    public function updateCustomForm(int $ticketId, int $customFormId = 0, int $serviceId = 0, array $customFormFieldsData) {
        {
            $body["TicketId"]               = $ticketId;
            $body["CustomFormId"]           = $customFormId;
            $body["ServiceId"]              = $serviceId;
            $body["CategoryItemId"]         = $categoryItemId;
            $body["CustomFormFieldsData"]   = $customFormFieldsData;

            //print_r(json_encode($body));
            return $this->apiCall("POST","Tickets/UpdateCustomForm",json_encode($body));
        }        
    }

    /**
     * WorkflowPush
     * @param int       $ticketId   	(Required TicketId OR TicketREF) The ID of the ticket
     * @param string    $ticketREF      (Required TicketId OR TicketREF) The reference ID of the ticket
     * @param int       $action         (Required) Right workflow action for the Ticket's state is needed. Get a list of available actions using GetTicket method.
     * @param string    $userProviderKey Optional parameter. Only some Actions need to specify this parameter. These are IncidentAssign, IncidentAssignOther, IncidentFunctionEscalate, ServiceRequestAssign, ServiceRequestAssignOther, ProblemAssign and ChangeAssign.
     * @param int       $serviceId      Optional parametr. Only some Actions need to specify this parameter. There are TicketConvertToIncident, TicketConvertToServiceRequest.
     */
    public function workflowPush(int $ticketId = null, string $ticketREF = null, int $action, string $userProviderKey = null, int $serviceId = null) {
        $body["TicketId"] =  $ticketId;
        $body["TicketREF"] = $ticketREF;
        $body["Action"] = $action;
        $body["UserProviderKey"] = $userProviderKey;
        $body["ServiceId"] = $serviceId;

        //print_r(json_encode($body));
        return $this->apiCall("POST","Tickets/WorkflowPush",json_encode($body));
    }
}

