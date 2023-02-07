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
     * @param array     $attachments array("FileName" => "", "ContentType" => "", "ContentLength" => 0, "Data" => "")
     */
    public function newMessage(
        string $ticketRef = null, 
        int $ticketId = null, 
        string $message, 
        bool $isMessageHtml = false, 
        bool $isPrivate = false,
        array $attachments = null
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

    /**
     * ticketChangeService
     * @param int $ticketId     (Required) The ticket Id where to change the service.
     * @param int $serviceId    Target Service Id.
     */
    public function ticketChangeService(int $ticketId, int $serviceId) {
        $body["TicketId"]   = $ticketId;
        $body["ServiceId"]  = $serviceId;

        //print_r(json_encode($body));
        return $this->apiCall("POST","Tickets/ChangeService",json_encode($body));
 
    }

    /**
     * explainTicketState
     * @param int   $ticketState The actual workflow state of the Ticket specific to the type of the Ticket (TIC, INC, REQ, ...)
     */
    public function explainTicketState(int $ticketState) {
        $state[101]="TicketInQueue";
        $state[102]="TicketClosed";
        $state[201]="IncidentInQueue";
        $state[202]="IncidentAssigned";
        $state[203]="IncidentRefusedAssigning";
        $state[204]="IncidentConfirmed";
        $state[205]="IncidentAnalyzed";
        $state[206]="IncidentSuspended";
        $state[207]="IncidentCompleted";
        $state[212]="IncidentPostponed";
        $state[208]="IncidentSolved";
        $state[209]="IncidentRejected";
        $state[210]="IncidentAccepted";
        $state[211]="IncidentClosed";
        $state[301]="ServiceRequestInQueue";
        $state[302]="ServiceRequestAssigned";
        $state[303]="ServiceRequestRefusedAssigning";
        $state[304]="ServiceRequestConfirmed";
        $state[305]="ServiceRequestAnalyzed";
        $state[306]="ServiceRequestSuspended";
        $state[307]="ServiceRequestCompleted";
        $state[308]="ServiceRequestPostponed";
        $state[309]="ServiceRequestSolved";
        $state[310]="ServiceRequestRejected";
        $state[311]="ServiceRequestAccepted";
        $state[312]="ServiceRequestClosed";
        $state[401]="ProblemRegistered";
        $state[402]="ProblemAssigned";
        $state[403]="ProblemInvestigated";
        $state[404]="ProblemPostponed";
        $state[405]="ProblemResolved";
        $state[406]="ProblemRejected";
        $state[407]="ProblemAccepted";
        $state[408]="ProblemChangePending";
        $state[409]="ProblemClosed";
        $state[501]="ChangeRegistered";
        $state[502]="ChangeAssigned";
        $state[503]="ChangePreparation";
        $state[504]="ChangePreparationDone";
        $state[505]="ChangePreparationRejected";
        $state[506]="ChangePreparationAccepted";
        $state[507]="ChangeImplementation";
        $state[508]="ChangeImplementationDone";
        $state[512]="ChangeTesting";
        $state[513]="ChangeTestFailed";
        $state[509]="ChangeImplementationRejected";
        $state[510]="ChangeImplementationAccepted";

        return $state[$ticketState];
    }

    /**
     * explainTicketWorkflowAction
     * @param int $ticketAction   Workflow action for the Ticket
     */
    public function explainTicketWorkflowAction(int $ticketAction) {
        $action[101]="TicketClose";
        $action[102]="TicketConvertToIncident";
        $action[103]="TicketConvertToServiceRequest";
        $action[201]="IncidentTakeFromQueue";
        $action[231]="IncidentReturnToQueue";
        $action[202]="IncidentAssign";
        $action[203]="IncidentRefuseAssigning";
        $action[204]="IncidentAssignOther";
        $action[205]="IncidentConfirmAssigning";
        $action[206]="IncidentAnalyze";
        $action[207]="IncidentSuspend";
        $action[208]="IncidentComplete";
        $action[209]="IncidentSuspendAgain";
        $action[210]="IncidentConfirmCompletion";
        $action[226]="IncidentPostpone";
        $action[227]="IncidentCancelPostponement";
        $action[229]="IncidentCancelPostponementByAutomation";
        $action[211]="IncidentFinishSolution";
        $action[221]="IncidentFinishSolutionCancel";
        $action[212]="IncidentFinishSolutionAndClose";
        $action[222]="IncidentFinishSolutionFromSuspended";
        $action[223]="IncidentFinishSolutionAndCloseFromSuspended";
        $action[213]="IncidentRefuseSolution";
        $action[214]="IncidentAnalyzeAgain";
        $action[215]="IncidentAcceptSolution";
        $action[216]="IncidentClose";
        $action[224]="IncidentCloseFromConfirmed";
        $action[217]="IncidentReactivateToSolution";
        $action[218]="IncidentReactivateToBeginning";
        $action[219]="IncidentFunctionEscalate";
        $action[220]="IncidentFunctionEscalatedTakeFromQueue";
        $action[225]="IncidentFinishFunctionEscalation";
        $action[301]="ServiceRequestTakeFromQueue";
        $action[331]="ServiceRequestReturnToQueue";
        $action[302]="ServiceRequestAssign";
        $action[303]="ServiceRequestRefuseAssigning";
        $action[304]="ServiceRequestAssignOther";
        $action[305]="ServiceRequestConfirmAssigning";
        $action[306]="ServiceRequestAnalyze";
        $action[307]="ServiceRequestSuspend";
        $action[308]="ServiceRequestComplete";
        $action[309]="ServiceRequestSuspendAgain";
        $action[310]="ServiceRequestConfirmCompletion";
        $action[311]="ServiceRequestPostpone";
        $action[312]="ServiceRequestCancelPostponement";
        $action[329]="ServiceRequestCancelPostponementByAutomation";
        $action[313]="ServiceRequestFinishSolution";
        $action[321]="ServiceRequestFinishSolutionCancel";
        $action[314]="ServiceRequestFinishSolutionAndClose";
        $action[322]="ServiceRequestFinishSolutionFromSuspended";
        $action[323]="ServiceRequestFinishSolutionAndCloseFromSuspended";
        $action[315]="ServiceRequestRefuseSolution";
        $action[316]="ServiceRequestAnalyzeAgain";
        $action[317]="ServiceRequestAcceptSolution";
        $action[318]="ServiceRequestClose";
        $action[324]="ServiceRequestCloseFromConfirmed";
        $action[319]="ServiceRequestReactivateToSolution";
        $action[320]="ServiceRequestReactivateToBeginning";
        $action[326]="ServiceRequestFunctionEscalate";
        $action[327]="ServiceRequestFunctionEscalatedTakeFromQueue";
        $action[328]="ServiceRequestFinishFunctionEscalation";
        $action[401]="ProblemAssign";
        $action[402]="ProblemInvestigate";
        $action[403]="ProblemPostpone";
        $action[404]="ProblemCancelPostponement";
        $action[414]="ProblemCancelPostponementByAutomation";
        $action[405]="ProblemReasonFound";
        $action[406]="ProblemRefuseReason";
        $action[407]="ProblemInvestigateAgain";
        $action[408]="ProblemAcceptReason";
        $action[409]="ProblemCreateRFC";
        $action[410]="ProblemFinishChangeManagement";
        $action[411]="ProblemClose";
        $action[412]="ProblemReactivateToSolution";
        $action[413]="ProblemReactivateToBeginning";
        $action[501]="ChangeAssign";
        $action[502]="ChangeTakeFromQueue";
        $action[503]="ChangeStartPreparation";
        $action[504]="ChangeFinishPreparation";
        $action[505]="ChangeRejectPreparation";
        $action[506]="ChangeStartPreparationAgain";
        $action[507]="ChangeAcceptPreparation";
        $action[508]="ChangeStartImplementation";
        $action[509]="ChangeStartImplementationNoPreparation";
        $action[510]="ChangeFinishImplementation";
        $action[518]="ChangeRejectTest";
        $action[519]="ChangeStartImplementationAgainAfterTestFailed";
        $action[520]="ChangeAcceptTest";
        $action[511]="ChangeRejectImplementation";
        $action[512]="ChangeStartImplementationAgain";
        $action[513]="ChangeAcceptImplementation";
        $action[514]="ChangeClose";
        $action[515]="ChangeCloseNoApproval";
        $action[516]="ChangeReactivateToSolution";

        return $action[$ticketAction];
        
    }
}

