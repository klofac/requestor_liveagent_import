<?php
require "config.php";
require "ipex_helpdesk.php";


/**
 * 
 * MAIN PROGRAM
 * 
 */

 //mereni doby behu programu
$time_start = microtime(true);

/*
$ticket_from    = (isset($_GET['from']) ? $_GET['from'] : '0' );
$ticket_to      = (isset($_GET['to']) ? $_GET['to'] : '1' );
*/

$helpdesk = new \Ipex\Helpdesk\IpexHelpdesk($GLOBALS['config_hlp_url'],$GLOBALS['config_hlp_user'],$GLOBALS['config_hlp_pwd']);

    $email="klofinator@mailinator.com";
    $internalGroupId = null;
    $subject = "Pokus 2";
    $message = "I dont know why, it was operating yesterday.";
    $isMessageHtml = false;
    $ticketType = 3;
    $serviceId = 45;
    $attachments = null;

//  $ticket = $helpdesk->newAnonymousTicket($email,$ticketType,$serviceId,$subject,$message,$isMessageHtml);
  
//  $result = $helpdesk->newMessage("",$ticket->TicketId,"Další zpráva.",false,true);
    $result = $helpdesk->newMessage("",88788,"Další zpráva.",false,true,
        array(
            array(
                "FileName" => "soubor33.txt",
                "ContentType" => "text/plain",
                "ContentLength" => strlen("Ahoj to je mazec"),
                "Data" => "Ahoj to je mazec" 
            ),
            array(
                "FileName" => "soubor43.txt",
                "ContentType" => "text/plain",
                "ContentLength" => strlen("Ahoj bla bla"),
                "Data" => "Ahoj bla bla" 
            )
        )
    );

/*
        array(
            array(
                "FileName" => "soubor.txt",
                "ContentType" => "text/plain;charset=UTF-8",
                "ContentLength" => 4,
                "Data" => "Ahoj" 
            ),
            array(
                "FileName" => "soubor2.txt",
                "ContentType" => "text/plain;charset=UTF-8",
                "ContentLength" => 5,
                "Data" => "Ahoj2" 
            )
        )
*/
$time_end = microtime(true);

//echo "Finished in ".round($time_end - $time_start)." sec";
?>
