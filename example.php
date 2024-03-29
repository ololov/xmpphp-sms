<?php

//include our plugin
//you don't need to include 'XMPP.php' explicitly
require_once('XMPP_SMS.php');

//create a sender
//parameters are:
//- xmpp server address or IP
//- xmpp server port
//- xmpp user id (JID)
//- xmpp user password
//- xmpp resource (may leave as is)
//- mrim transport JID (important!)
$smssender = new XMPP_SMS('xmpp.example.com', 5222, 'xmppuser@example.com', 'xmpppassword', 'xmpp-sms', 'mrim.example.com');

try 
{
	//first the same as XMPPHP_XMPP
	$smssender->connect();
	$smssender->processUntil('session_start');
	$smssender->presence();
	
	//wait for mrim transport to become online for maximum 10 seconds
	if (!$smssender->waitformrim(10))
	{			
		echo "Timeout waiting for mrim become online";		
		die();
	}
	
	//the third parameter is optional and enable auto transliteration if set to TRUE
	$res = $smssender->sendsms('+12341234567', 'Hello from XMPPHP-SMS!', FALSE);
	
	//check the return value, possible values are
	// - XMPP_SMS::SMSSEND_OK - sms send attempt is succedded
	// - XMPP_SMS::SMSSEND_TIMEOUT - sms send failed because of timeout
	// - XMPP_SMS::SMSSEND_ERROR - sms send failed  		
	if ($res != XMPP_SMS::SMSSEND_OK)
	{
		echo "Error occured during send: " . $res;
		die();
	}
	
	//optional, but recommended step: waiting for possible error report messages from mrim transport
	//first parameter is a destination phone number
	//second paramter is a maximum wait time in seconds (30-60 seconds is optimal)

	//if returned value is FALSE, then no errors was recieved, but this doesn't guarantee the delivery of a message!
	//if returned value isn't FALSE, then the return value is a string error message, recieved from mrim transport.
	
	$res = $smssender->waitforerrors('+12341234567', 30);
	if ($res !== FALSE)
	{
		echo "Error from mrim is recieved: " . $res;
		die();
	}
	
	//disconnect from server	
	$smssender->disconnect();	
} 
catch(XMPPHP_Exception $e) 
{
	//Exceptions are really exceptions and shouldn't happen in general work flow
	
	echo "Something terrible happen: " . $e->getMessage();
	die();
}

?>
