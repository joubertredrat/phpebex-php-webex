<?php
/**
 * Simple class to integrate with Cisco Webex XML API.
 * Built on version 5.9 of Reference Guide.
 * Based on Webex class by Joshua McGinnis (http://joshuamcginnis.com/2010/07/12/webex-api-php-sdk).
 *
 * @author Joubert Guimarães de Assis "RedRat" <joubert@redrat.com.br>
 * @copyright Copyright (c) 2013, RedRat Consultoria
 * @license GPL version 2
 * @see Github, animes and mangás, cute girls and PHP, much PHP
 * @link http://developer.cisco.com/documents/4733862/4736722/xml_api_5+9.pdf
 * @link https://github.com/joubertredrat/phpebex-php-webex
 */

class Webex {
	
	private $username;
	private $password;
	private $siteID;
	private $partnerID;

	private $url_prefix;
	private $url_host;

	private $send_mode;

	private $data;

	private $action;

	const SEND_CURL 			= 'curl';
	const SEND_FSOCKS 			= 'fsocks';
	const PREFIX_HTTP 			= 'http';
	const PREFIX_HTTPS 			= 'https';
	const SUFIX_XML_API 		= 'WBXService/XMLService';
	const WEBEX_DOMAIN 			= 'webex.com';
	const XML_VERSION			= '1.0';
	const XML_ENCODING			= 'UTF-8';
	const USER_AGENT			= 'PHPebEx - WebEx PHP API (https://github.com/joubertredrat/phpebex-php-webex)';

	const API_SCHEMA_MEETING	= 'http://www.webex.com/schemas/2002/06/service/meeting';
	const API_SCHEMA_SERVICE	= 'http://www.webex.com/schemas/2002/06/service';

	const DATA_SENDER				= 'sender';
	const DATA_SENDER_POST_HEADER 	= 'post_header';
	const DATA_SENDER_POST_BODY 	= 'post_body';
	const DATA_SENDER_XML 			= 'xml';
	const DATA_RESPONSE				= 'response';
	const DATA_RESPONSE_XML			= 'xml';
	const DATA_RESPONSE_DATA		= 'data';

	/**
	 * Constructor of class.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->action = 0;
		$this->response = array();
		$this->send_mode = in_array(self::SEND_CURL, get_loaded_extensions()) ? self::SEND_CURL : self::SEND_FSOCKS;
	}

	/**
	 * Get a possible modes to send a POST data.
	 *
	 * @return array Returns a list of send modes.
	 */
	public static function get_sendmode() {
		return array(self::SEND_CURL, self::SEND_FSOCKS);
	}

	/**
	 * Validates a customer webex domain.
	 *
	 * @param string $url Url to validate.
	 * @return bool Return true if a valid url or false if not.
	 */
	public static function validate_url($url) {
		$regex = "/^(http|https):\/\/(([A-Z0-9][A-Z0-9_-]*)+.(" . self::WEBEX_DOMAIN . ")$)/i";
		return (bool) preg_match($regex, $url);
	}

	/**
	 * Get port used by API.
	 *
	 * @param string $prefix Prefix to get a port.
	 * @return int Return a port.
	 */
	public static function get_port($prefix) {
		switch($prefix) {
			case self::PREFIX_HTTP:
				return 80;
			break;
			case self::PREFIX_HTTPS:
				return 443;
			break;
			default:
				exit(__CLASS__ . ' error report: Wrong prefix');
			break;
		}
	}

	/**
	 * Set a url to integrate to webex.
	 *
	 * @param string $url Customer url.
	 * @return void
	 */
	public function set_url($url) {
		if(!self::validate_url($url))
			exit(__CLASS__ . ' error report: Wrong webex url');
		list($this->url_prefix, $this->url_host) = preg_split("$://$", $url);
	}

	/**
	 * Mode to send data.
	 *
	 * @param string mode to send.
	 * @return void
	 */
	public function set_sendmode($mode) {
		if(!in_array($mode, self::get_sendmode()))
			exit(__CLASS__ . ' error report: Wrong send mode');
		$this->send_mode = $mode;
	}

	/**
	 * Auth data to integrate with API.
	 *
	 * @param string $username Username to auth.
	 * @param string $password Password to auth.
	 * @param string $siteID Customer site id.
	 * @param string $partnerID Customer partnerID id.
	 * @return void
	 */
	public function set_auth($username, $password, $siteID, $partnerID) {
		$this->username 	= $username;
		$this->password 	= $password;
		$this->siteID 		= $siteID;
		$this->partnerID 	= $partnerID;
	}

	/**
	 * Generates a XML to send a data to API.
	 *
	 * @param array $data Data to insert in XML in format:
	 *		$data['service']	= 'meeting';
	 *		$data['xml_header'] = '<item><subitem>data</subitem></item>';
	 *		$data['xml_body']	= '<item><subitem>data</subitem></item>';
	 * @return string Returns a XML generated.
	 */
	private function get_xml($data) {
		$xml 	= array();
		$xml[]	= '<?xml version="' . self::XML_VERSION . '" encoding="' . self::XML_ENCODING . '"?>';
		$xml[]	= 	'<serv:message xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';
		$xml[]	= 		'<header>';
		$xml[]	= 			'<securityContext>';
		$xml[]	= 				'<webExID>' . $this->username . '</webExID>';
		$xml[]	= 				'<password>' . $this->password . '</password>';
		$xml[]	= 				'<siteID>' . $this->siteID . '</siteID>';
		$xml[]	= 				'<partnerID>' . $this->partnerID . '</partnerID>';
		if(isset($data['xml_header']))
			$xml[]	= 			$data['xml_header'];
		$xml[]	= 			'</securityContext>';
		$xml[]	= 		'</header>';
		$xml[]	= 		'<body>';
		$xml[]	= 			'<bodyContent xsi:type="java:com.webex.service.binding.' . $data['service'] . '">';
		$xml[]	= 				$data['xml_body'];
		$xml[]	= 			'</bodyContent>';				
		$xml[]	= 		'</body>';
		$xml[]	= 	'</serv:message>';
		return implode('', $xml);
	}

	/**
	 * Test if have a auth data to use a API.
	 *
	 * @return bool Returns true if have data and false if not.
	 */
	public function has_auth() {
		return (bool) $this->username && $this->password && $this->siteID && $this->partnerID;
	}

	/**
	 * Generates a header and a body to send data to API.
	 *
	 * @return string Returns a response from API.
	 */
	private function send($xml) {
		$post_data['UID'] = $this->username;
		$post_data['PWD'] = $this->password;
		$post_data['SID'] = $this->siteID;
		$post_data['PID'] = $this->partnerID;
		$post_data['XML'] = $xml;

		// Really I dont know why xml api give a error on http_build_query :(
		$post_string = '';
		foreach ($post_data as $variable => $value) {
			$post_string .= '' . $variable . '=' . urlencode($value) . '&';
		}

		$post_header 		= array();
		$post_header[] 		= 'POST /' . self::SUFIX_XML_API . ' HTTP/1.0';
		$post_header[] 		= 'Host: ' . $this->url_host;
		$post_header[] 		= 'User-Agent: ' . self::USER_AGENT;
		if($this->send_mode == self::SEND_FSOCKS) {
			$post_header[] 	= 'Content-Type: application/xml';
			$post_header[] 	= 'Content-Length: ' . strlen($xml);
		}
		$data = array();
		$data['post_header'] = $post_header;
		$data['post_string'] = $post_string;

		$this->data[$this->action][self::DATA_SENDER][self::DATA_SENDER_POST_HEADER] 	= $post_header;
		$this->data[$this->action][self::DATA_SENDER][self::DATA_SENDER_POST_BODY] 		= $post_header;
		$this->data[$this->action][self::DATA_SENDER][self::DATA_SENDER_XML] 			= $xml;

		return $this->{'send_' . $this->send_mode}($data);
	}

	/**
	 * Send a data to Webex API using PHP curl.
	 *
	 * @param array $data Data to send to API in format:
	 * 		$data['post_header'] = "blablabla";
	 * 		$data['post_header'] = "post_string";
	 * @return string Returns a response from API.
	 */
	private function send_curl($data) {
		extract($data);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->url_prefix . '://' . $this->url_host . '/' . self::SUFIX_XML_API);
		curl_setopt($ch, CURLOPT_PORT, self::get_port($this->url_prefix));
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $post_header);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$response = curl_exec($ch);
		if($response === false)
			exit(__CLASS__ . ' error report: Curl error - ' . curl_error($ch));
		curl_close($ch);
		return $response;
	}

	/**
	 * Send a data to Webex API using PHP file functions.
	 *
	 * @param array $data Data to send to API in format:
	 * 		$data['post_header'] = "blablabla";
	 * 		$data['post_header'] = "post_string";
	 * @return string Returns a response from API.
	 * @todo haha, I need to test this :)
	 */
	private function send_fsocks($data) {
		extract($data);
		$post_data = implode("\n", $post_header) . "\n\n" . $post_string . "\n";
		$fp = fsockopen($this->url_host, self::get_port($this->url_prefix), $errno, $error);
		if($fp) {
			fwrite($fp, $post_data);
			$response = '';
			while(!feof($fp)) {
				$response .= fgets($fp, 1024);
			}
			return $response;
		}
		else
			exit(__CLASS__ . ' error report: Fsocks error - (' . $errno . ') ' . $error);
	}

	/**
	 * Get response from a API.
	 *
	 * @param string $type Type of data to be requested.
	 * @param int $number number of sender to be requested.
	 * @return string|object Return a response.
	 */
	public function get_response($type = self::DATA_RESPONSE_DATA, $number = null) {
		if(isset($number) && is_int($number)) {
			if($number < 1 || $number > ($this->action - 1))
				exit(__CLASS__ . ' error report: Invalid response number');
			$number--;
		}
		else
			$number = ($this->action - 1);
		var_dump($number);
		switch($type) {
			case self::DATA_RESPONSE_XML:
			case self::DATA_RESPONSE_DATA:
				return $this->data[$number][self::DATA_RESPONSE][$type];
			break;
			default:
				exit(__CLASS__ . ' error report: I don\'t undestood that data you needs');
			break;
		}
	}

	/**
	 * @todo documentate in future.
	 */
	public function meeting_LstsummaryMeeting($maximumNum = 5) {
		if(!$this->has_auth())
			exit(__CLASS__ . ' error report: Auth data not found');
		$xml_body 	= array();
		$xml_body[] = '<listControl>';
		$xml_body[] = 	'<startFrom/>';
		$xml_body[] = 		'<maximumNum>' . $maximumNum . '</maximumNum>';
		$xml_body[] = '</listControl>';
		$xml_body[] = '<order>';
		$xml_body[] = 	'<orderBy>STARTTIME</orderBy>';
		$xml_body[] = '</order>';
		$xml_body[] = '<dateScope>';
		$xml_body[] = '</dateScope>';

		$data['xml_body'] 	= implode('', $xml_body);
		$data['service'] 	= str_replace("_", ".", __FUNCTION__);
		$xml 				= $this->get_xml($data);
		$response 			= $this->send($xml);

		$xml 						= simplexml_load_string($response);
		$Data 						= new stdClass;
		$Data->header 				= new stdClass;
		$Data->header->response 	= new stdClass;
		$Data->bodyContent 			= array();

		$node 								= $xml->children(self::API_SCHEMA_SERVICE);
		$Data->header->response->result 	= (string) $node[0]->response->result;
		$Data->header->response->gsbStatus 	= (string) $node[0]->response->gsbStatus;

		$node_meeting = $node[1]->bodyContent;

		foreach($node_meeting->children(self::API_SCHEMA_MEETING) as $meeting) {
			if($meeting->meetingKey) {
				$MeetingData 						= new stdClass;
				$MeetingData->meetingKey 			= (string) $meeting->meetingKey;
				$MeetingData->confName 				= (string) $meeting->confName;
				$MeetingData->meetingType 			= (string) $meeting->meetingType;
				$MeetingData->hostWebExID 			= (string) $meeting->hostWebExID;
				$MeetingData->otherHostWebExID 		= (string) $meeting->otherHostWebExID;
				$MeetingData->timeZoneID 			= (string) $meeting->timeZoneID;
				$MeetingData->timeZone 				= (string) $meeting->timeZone;
				$MeetingData->status 				= (string) $meeting->status;
				$MeetingData->startDate 			= (string) $meeting->startDate;
				$MeetingData->duration 				= (string) $meeting->duration;
				$MeetingData->listStatus 			= (string) $meeting->listStatus;
				$MeetingData->hostJoined 			= (string) $meeting->hostJoined;
				$MeetingData->participantsJoined 	= (string) $meeting->participantsJoined;
				$MeetingData->telePresence 			= (string) $meeting->telePresence;
				$Data->bodyContent[] 				= $MeetingData;
			}
		}

		$this->data[$this->action][self::DATA_RESPONSE][self::DATA_RESPONSE_DATA] 	= $Data;
		$this->data[$this->action][self::DATA_RESPONSE][self::DATA_RESPONSE_XML] 	= $response;
		$this->action++;
	}

	//public function user_AuthenticateUser()
	//public function user_CreateUser()
	//public function user_DelUser()
	//public function user_DelSessionTemplates()
	//public function user_GetloginTicket()
	//public function user_GetloginurlUser()
	//public function user_GetlogouturlUser()
	//public function user_GetUser()
	//public function user_LstsummaryUser()
	//public function user_SetUser()
	//public function user_UploadPMRIImage()

	//public function meeting_CreateMeeting()
	//public function meeting_CreateTeleconferenceSession()
	//public function meeting_DelMeeting()
	//public function meeting_GethosturlMeeting()
	//public function meeting_GetMeeting()
	//public function meeting_GetTeleconferenceSession()
	//public function meeting_LstsummaryMeeting()
	//public function meeting_SetMeeting()
	//public function meeting_SetTeleconferenceSession()
	//public function meeting_GetjoinurlMeeting()

	//public function event_CreateEvent()
	//public function event_DelEvent()
	//public function event_GetEvent()
	//public function event_LstRecordedEvent()
	//public function event_LstsummaryProgram()
	//public function event_SendInvitationEmail()
	//public function event_SetEvent()
	//public function event_UploadEventImage()
	//public function event_LstsummaryEvent()

	//public function attendee_CreateMeetingAttendee()
	//public function attendee_LstMeetingAttendee()
	//public function attendee_RegisterMeetingAttendee()
	//public function history_LstmeetingattendeeHistory()
}