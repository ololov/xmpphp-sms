<?php
/**
 * XMPPHP-SMS: The PHP XMPP Library SMS plugin
 * Copyright (C) 2010  Aleksey B Osipov
 *
 * Based on XMPPHP: The PHP XMPP Library by Nathanael C. Fritz <JID: fritzy@netflint.net>
 * 
 * XMPPHP-SMS is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * XMPPHP-SMS is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with XMPPHP; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @author	 Aleksey B Osipov <JID: lion-simba@jabber.ru>
 * @copyright  2010 Aleksey B Osipov
 */

require_once dirname(__FILE__) . "/XMPP.php";

class XMPP_SMS_Exception extends XMPPHP_Exception {	
}

class XMPP_SMS extends XMPPHP_XMPP {
	
	protected $mrim;
	
	private $number;
	private $text;
	private $translit;
	private $sessionid;
	
	public function __construct($host, $port, $user, $password, $resource, $mrim, $server = null, $printlog = false, $loglevel = null) {
		parent::__construct($host, $port, $user, $password, $resource, $server, $printlog, $loglevel);
		
		$this->mrim = $mrim;
	}
	
	
	const SMSSEND_OK = 0;
	const SMSSEND_TIMEOUT = 1;
	const SMSSEND_ERROR = 2; //generic error
	
	/* is NOT thread safe */
	public function sendsms($to, $text, $autotranslit = 0, $timeout = 5) {
		/* check $to by regexp */
		/* check $text by length */
		
		$this->number = $to;
		$this->text = $text;
		$this->translit = $autotranslit;
			
		$id = $this->getId();
		
		$this->addIdHandler($id, 'sms_command_handler_phase1');
		
		$out = "<iq type=\"set\" from=\"{$this->fulljid}\" to=\"{$this->mrim}\" id=\"$id\">";
		$out .= "<command xmlns=\"http://jabber.org/protocol/commands\" node=\"sms\"/>";
		$out .= "</iq>";
		
		$this->send($out);
		$payloads = $this->processUntil(array('sms_sent'), $timeout);
		if (count($payloads) == 0)
		{
			unset($this->idhandlers[$id]);
			return self::SMSSEND_TIMEOUT;
		}
		
		if (count($payloads) > 1)
			throw new XMPP_SMS_Exception("Multiple sms_sent events");
			
		if ($payloads[0][0] != 'sms_sent')
			throw new XMPP_SMS_Exception("Incorrect event reply");
		
		return $payloads[0][1]['result'];
	}
	
	public function waitformrim($timeout)
	{
		$time_start = time();
		$time_end = $time_start + $timeout;
		$time_left = $time_end - time();
		while ($time_left > 0)
		{			
			$events = $this->processUntil(array('presence'), $time_left);
			if (count($events) == 0)
				return FALSE; //timeout
				
			foreach($events as $event)
			{
				$pl = $event[1];
				if ($event[0] == 'presence' && $pl['from'] == $this->mrim)
					return TRUE; //дождались
			}
			
			$time_left = $time_end - time();
		}
		
		return FALSE; //timeout
	}
	
	public function waitforerrors($to, $timeout) 
	{
		//remove "+" sign
		if (mb_substr($to, 0, 1, 'utf8') == "+")
			$to = mb_substr($to, 1, mb_strlen($to, 'utf8') - 1, 'utf8');
			
		$time_start = time();
		$time_end = $time_start + $timeout;
		$time_left = $time_end - time();
		while ($time_left > 0)
		{			
			$events = $this->processUntil(array('message'), $time_left);
			if (count($events) == 0)
				return FALSE; //no errors
				
			foreach($events as $event)
			{
				$pl = $event[1];
				switch ($event[0])
				{
				case 'message':
					if ($pl['from'] == $this->mrim)
					{
						if (preg_match("/$to/", $pl['body']))
						{
							//скорее всего это сообщение об ошибке, ибо когда всё хорошо,
							//mrim молчит в тряпочку :)
							
							return $pl['body'];
						}
					}
					break;
				}
			}
			
			$time_left = $time_end - time();
		}
		
		return FALSE;
	}
	
	public function sms_command_handler_phase1($xml) {
		
		if (!$xml->hasSub('command'))
			throw new XMPP_SMS_Exception("No command node at phase1");
		
		$cmd = $xml->sub('command');
				
		if (!isset($cmd->attrs['node']))
			throw new XMPP_SMS_Exception("No node atribute at phase1");
			
		if ($cmd->attrs['node'] != 'sms')
		{
			$pl['result'] = self::SMSSEND_ERROR;
			$pl['reason'] = '"sms" command node in phase1 is not "sms", but "' . $cmd->attrs['node'] . '"';
			$this->event('sms_sent', $pl);
			return;
		}			
		
		if (!isset($cmd->attrs['status']))
			throw new XMPP_SMS_Exception("No status atribute at phase1");
						
		if ($cmd->attrs['status'] != 'executing')
		{
			$pl['result'] = self::SMSSEND_ERROR;
			$pl['reason'] = '"sms" command status in phase1 is not "executing", but "' . $cmd->attrs['status'] . '"';
			$this->event('sms_sent', $pl);
			return;
		}
		
		if (!isset($cmd->attrs['sessionid']))
			throw new XMPP_SMS_Exception("No sessionid atribute at phase1");
		
		$this->sessionid = $cmd->attrs['sessionid'];
		$id = $this->getId();
		
		$this->addIdHandler($id, 'sms_command_handler_phase2');
		
		$out = "<iq type=\"set\" from=\"{$this->fulljid}\" to=\"{$this->mrim}\" id=\"$id\">";
		$out .= "<command xmlns=\"http://jabber.org/protocol/commands\" node=\"sms\" sessionid=\"{$this->sessionid}\">";
		$out .= "<x xmlns=\"jabber:x:data\" type=\"submit\">";
		$out .= "<field type=\"text-single\" var=\"number\">";
		$out .= "<value>{$this->number}</value>";
		$out .= "</field>";
		$out .= "<field type=\"text-multi\" var=\"text\">";
		$out .= "<value>" . htmlspecialchars($this->text) . "</value>";
		$out .= "</field>";
		$out .= "<field type=\"boolean\" var=\"translit\">";
		$out .= "<value>" . (($this->translit > 0) ? 1 : 0) . "</value>";
		$out .= "</field>";
		$out .= "</x>";
		$out .= "</command>";
		$out .= "</iq>";
		
		$this->send($out);
	}
	
	public function sms_command_handler_phase2($xml) {

		if (!isset($xml->attrs['type']))
			throw new XMPP_SMS_Exception("No iq type attribute at phase2");
			
		if ($xml->attrs['type'] == 'error')
		{
			$pl['result'] = self::SMSSEND_ERROR;
			$pl['reason'] = '"iq type" is not "result", but "' . $xml->attrs['type'] . '"';
			$this->event('sms_sent', $pl);
			return;
		}

		if (!$xml->hasSub('command'))
			throw new XMPP_SMS_Exception("No command node at phase2");
		
		$cmd = $xml->sub('command');
				
		if (!isset($cmd->attrs['node']))
			throw new XMPP_SMS_Exception("No node atribute at phase2");
			
		if ($cmd->attrs['node'] != 'sms')
		{
			$pl['result'] = self::SMSSEND_ERROR;
			$pl['reason'] = '"sms" command node in phase2 is not "sms", but "' . $cmd->attrs['node'] . '"';
			$this->event('sms_sent', $pl);
			return;
		}			
		
		if (!isset($cmd->attrs['status']))
			throw new XMPP_SMS_Exception("No status atribute at phase2");
						
		if ($cmd->attrs['status'] != 'completed')
		{
			$pl['result'] = self::SMSSEND_ERROR;
			$pl['reason'] = '"sms" command status in phase2 is not "completed", but "' . $cmd->attrs['status'] . '"';
			$this->event('sms_sent', $pl);
			return;
		}
		
		if (!isset($cmd->attrs['sessionid']))
			throw new XMPP_SMS_Exception("No sessionid atribute at phase2");
		
		$sessionid = $cmd->attrs['sessionid'];
		
		if ($sessionid != $this->sessionid)
		{
			$pl['result'] = self::SMSSEND_ERROR;
			$pl['reason'] = 'phase2 sessionid is differs from phase1 sessionid';
			$this->event('sms_sent', $pl);
			return;
		}
		
		$pl['result'] = self::SMSSEND_OK;
		$pl['reason'] = 'sms sent';
		$this->event('sms_sent', $pl);
	}
}

?>
