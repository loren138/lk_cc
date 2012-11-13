<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once PATH_THIRD . 'lk_cc/config.php';

/**
 * LK Popular to fetch popular posts
 * Plugin Info at bottom
 */

class Lk_cc {

	/* config - required */
	private $cc_api_key;
	private $cc_username;
	private $cc_password;

	public $template;
	public $type;
	
	public $EE;
	public $ext;


	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct()
	{
		$this->EE =& get_instance();
		if ( ! class_exists('CC_Utility',false))
		{
			require_once(PATH_THIRD . 'lk_cc/cc_class.php');
		}
		// have we already initialised once?
		if ( ! isset($this->EE->session->cache['lk_cc']['settings']))
		{
			// Get settings with help of our extension
			if ( ! class_exists('Lk_cc_ext'))
			{
				require_once(PATH_THIRD . 'lk_cc/ext.lk_cc.php');
			}
			$this->ext = new Lk_cc_ext();
			$this->ext->get_settings();
		}

		// grab settings
		$this->cc_api_key = $this->EE->session->cache['lk_cc']['settings']['cc_api_key'];
		$this->cc_username = $this->EE->session->cache['lk_cc']['settings']['cc_username'];
		$this->cc_password = $this->EE->session->cache['lk_cc']['settings']['cc_password'];
		
		$this->_flightcheck();
	}
	// END

    /**
     * subscribe
     *
     * Takes email and list input and handles the subscription
     *
     * @return string
     */
    public function subscribe() {
		$this->EE->lang->load('lk_cc', '', FALSE, TRUE, PATH_THIRD.'lk_cc/');
		$tagdata = $this->EE->TMPL->tagdata;
		$default = array();
		$show = false;
		
		if (isset($this->EE->TMPL->tagparams['default_list'])) {
			$default = explode("|",$this->EE->TMPL->tagparams['default_list']);
			foreach ($default as $k => $v) {
				$default[$k] = trim($v);
			}
		}

        if (isset($this->EE->TMPL->tagparams['show_list'])) {
			$show = explode("|",$this->EE->TMPL->tagparams['show_list']);
			foreach ($show as $k => $v) {
				$show[$k] = trim($v);
			}
		}
		
		$variables = array( 
			array(
				'error_message' => '',
				'email' => '',
				'first_name' => '',
				'last_name' => '',
				'lists' => array(
				),
				'success' => false,
			)
		);
		// Add in the existing lists
		$ccListOBJ = new CC_List($this->cc_username,$this->cc_password, $this->cc_api_key); 
		$allLists = $ccListOBJ->getLists();
		$return = array();
		foreach ($allLists as $list) {
			$key = explode('/',$list['id']);
			$key = array_pop($key); // Get the id off the end of the string
			$check = '';
			if (in_array($key, $default)) { // Is it in the list to be checked by default?
				$check = 'checked';
			}
			$variables[0]['lists'][] = 
					array('value' => $key, 'name' => $list['title'], 'checked' => $check, 'id' => $list['id']);
		}
		if ($show) { // fix the ordering and filter
			$lists = $variables[0]['lists'];
			$variables[0]['lists'] = array();
			foreach ($show as $v2) {
				foreach ($lists as $v) {
					if ($v['value'] == $v2) { $variables[0]['lists'][] = $v; }
				}
			}
		}
		
		$_POST	= ee()->security->xss_clean( $_POST );
		if (isset($_POST['email'])) { // Process the Post if the email was set
			$variables[0]['email'] = trim($_POST['email']);
			if (isset($_POST['first_name'])) $variables[0]['first_name'] = trim($_POST['first_name']);
			if (isset($_POST['last_name'])) $variables[0]['last_name'] = trim($_POST['last_name']);
			if (isset($_POST['lists'])) {
				// We have lists from the post so unset all of the defaults
				foreach ($variables[0]['lists'] as $k => $v2) {
						$variables[0]['lists'][$k]['checked'] = '';
				}
				foreach ($_POST['lists'] as $v) {
					foreach ($variables[0]['lists'] as $k => $v2) {
						if ($v2['value'] == $v) {
							$variables[0]['lists'][$k]['checked'] = 'checked';
						}
					}
				}
			}
		}
		if ($variables[0]['email'] != "" && !$this->validEmail($variables[0]['email'])) {
			$variables[0]['error_message'] = $this->EE->lang->line('invalid_email');
			$variables[0]['email'] = '';
		}
		if ($variables[0]['email'] != "") {
			// Find lists to subscribe to
			$subscribe = array();
			foreach ($variables[0]['lists'] as $k => $v2) {
				if ($v2['checked'] != '') {
					$subscribe[] = $v2['id'];
				}
			}
			if (count($subscribe) == 0) {
				$variables[0]['error_message'] = $this->EE->lang->line('pick_list');
				$variables[0]['email'] = '';
			} else {
				// Try to subscribe them
				$success = false;
				$ccContactOBJ = new CC_Contact($this->cc_username,$this->cc_password, $this->cc_api_key);
				if ($ccContactOBJ->subscriberExists(urlencode($variables[0]['email'])) === false) {
					// New Contact
					$postFields = array();
					$postFields["email_address"] = $variables[0]['email'];
					$postFields["first_name"] = $variables[0]['first_name'];
					$postFields["last_name"] = $variables[0]['last_name'];
					if(isset($_POST["mail_type"]))
					{
						$postFields["mail_type"] = $_POST["mail_type"];
					}
					else
					{
						$postFields["mail_type"] = "HTML";
					}
					$postFields["lists"] = $subscribe;
					// If we are confirming, this will subscribe the contact to the selected lists/default (if no selection) lists
					$contactXML = $ccContactOBJ->createContactXML(null,$postFields);

					if (!$ccContactOBJ->addSubscriber($contactXML)) {
						$variables[0]['error_message'] = $this->EE->lang->line('unknown_error');
					} else {
						$success = true;
					}
				} else {
					// Existing Contact
					$contact = $ccContactOBJ->getSubscriberDetails(urlencode($variables[0]['email']));
					
					// Don't overwrite changes
					if (!isset($_POST['first_name'])) $variables[0]['first_name'] = $contact['first_name'];
					if (!isset($_POST['last_name'])) $variables[0]['last_name'] = $contact['last_name'];
					// Lists
					if (!isset($_POST['lists'])) {
						// Do some merging to get the defaults and existing checked
						foreach ($variables[0]['lists'] as $k => $v2) {
							if (in_array($v2['id'],$contact['lists'])) {
								$variables[0]['lists'][$k]['checked'] = 'checked';
							}
						}
					}
					// Rebuild the subscribe array with the newly checked lists
					// Find lists to subscribe to
					$subscribe = array();
					foreach ($variables[0]['lists'] as $k => $v2) {
						if ($v2['checked'] != '') {
							$subscribe[] = $v2['id'];
						}
					}
					// Merge back in the lists they are subscribed to that might not be listed so we don't remove them
					foreach ($contact['lists'] as $v) {
						if (!in_array($v,$subscribe)) {
							// Find the item number
							$key = explode('/',$v);
							$key = array_pop($key); // Get the id off the end of the string
							if (is_array($show) && !in_array($key, $show)) {
								// Are we showing selectively and not showing this list?
								$subscribe[] = $v;
							}
						}
					}
					
					// Edit Contact
					$postFields = array();
					$postFields["email_address"] = $variables[0]['email'];
					$postFields["first_name"] = $variables[0]['first_name'];
					$postFields["last_name"] = $variables[0]['last_name'];
					if(isset($_POST["mail_type"]))
					{
						$postFields["mail_type"] = $_POST["mail_type"];
					}
					else
					{
						$postFields["mail_type"] = "HTML";
					}
					$postFields["lists"] = $subscribe;
					$contactXML = $ccContactOBJ->createContactXML($contact['id'],$postFields);
					if (!$ccContactOBJ->editSubscriber($contact['id'],$contactXML)) {
						$variables[0]['error_message'] = $this->EE->lang->line('unknown_error');
					} else {
						$success = true;
					}
				}
				if ($success == true && !isset($_POST['confirm'])) {
					$variables[0]['success'] = true;
				}
			}
		}
		
		$str = $this->EE->TMPL->parse_variables($tagdata, $variables);
		return $str;
    }


	/**
	 * Flightcheck - make some basic config checks before proceeding
	 *
	 * @return void
	 */
	private function _flightcheck()
	{
	
		// Flightcheck: determine if we can continue or disable permanently
		switch ('flightcheck') :

			case ( ! $this->cc_username || !$this->cc_password || !$this->cc_api_key) :
				throw new Exception('Not configured: Please set the Constant Contact Username, Password, and API Key.');
			break;

			default :
				$this->EE->TMPL->log_item('LK CC has passed flightcheck.');
			break;

		endswitch;
	}
	
	/**
	Validate an email address.
	Provide email address (raw input)
	Returns true if the email address has the email 
	address format and the domain exists.
	*/
	function validEmail($email)
	{
	   	$isValid = true;
	   	$atIndex = strrpos($email, "@");
	   	if (is_bool($atIndex) && !$atIndex)
	   	{
	      $isValid = false;
	   	}
	    else
	    {
	      $domain = substr($email, $atIndex+1);
	      $local = substr($email, 0, $atIndex);
	      $localLen = strlen($local);
	      $domainLen = strlen($domain);
	      if ($localLen < 1 || $localLen > 64)
	      {
	         // local part length exceeded
	         $isValid = false;
	      }
	      else if ($domainLen < 1 || $domainLen > 255)
	      {
	         // domain part length exceeded
	         $isValid = false;
	      }
	      else if ($local[0] == '.' || $local[$localLen-1] == '.')
	      {
	         // local part starts or ends with '.'
	         $isValid = false;
	      }
	      else if (preg_match('/\\.\\./', $local))
	      {
	         // local part has two consecutive dots
	         $isValid = false;
	      }
	      else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain))
	      {
	         // character not valid in domain part
	         $isValid = false;
	      }
	      else if (preg_match('/\\.\\./', $domain))
	      {
	         // domain part has two consecutive dots
	         $isValid = false;
	      }
	      else if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/',
                 str_replace("\\\\","",$local)))
	      {
	         // character not valid in local part unless 
	         // local part is quoted
	         if (!preg_match('/^"(\\\\"|[^"])+"$/',
	             str_replace("\\\\","",$local)))
	         {
	            $isValid = false;
	         }
	      }
	      if ($isValid && !(checkdnsrr($domain,"MX") || checkdnsrr($domain,"A")))
	      {
	         // domain not found in DNS
	         $isValid = false;
	      }
	   }
	   return $isValid;
	}
	// END

    /**
     * campaign
     *
     * Creates a new ConstantContact Campaign
     *
     * @return string
     */
    public function campaign() {
    	// We need to parse at the end so we add a shutdown function to php
    	if ( empty( $this->EE->session->cache[ 'Lk_cc' ][ 'shutdown_is_registered' ] ) )
		{
			$this->EE->session->cache[ 'Lk_cc' ][ 'shutdown_is_registered' ] = TRUE;

			//register the shutdown function
			register_shutdown_function( array( $this, 'shut_it_down' ) );
		}
		return $this->EE->TMPL->tagdata;
	}
	
	/**
	 * shut_it_down
	 *
	 * Actually creates the new CC Campaign
	 *
	 * @return void
	 */
	public function shut_it_down() {
		$tagdata = $this->EE->TMPL->final_template;
		$apiKey = $this->cc_api_key;
		$username = $this->cc_username;
		$debug = false;
		preg_match("'\[debug\](.*?)\[/debug\]'si", $tagdata, $match);
		if (isset($match[1]) && trim(strtolower($match[1])) == "on") {
			$debug = true;
		}
		if ( ! class_exists('ConstantContact',false))
		{
			require_once(PATH_THIRD . 'lk_cc/wrapper/ConstantContact.php');
		}
		$ConstantContact = new ConstantContact('basic',$this->cc_api_key,$this->cc_username,$this->cc_password);
	
		// Get all verified email addresses
		$VerifiedEmailAddresses = $ConstantContact->getVerifiedAddresses ();
		
		try {
			// Build Campaign Object
			$myCampaign = new Campaign ();
			$match = array();
			preg_match("'\[name\](.*?)\[/name\]'si", $tagdata, $match);
			if (!isset($match[1]) || $match[1] == "") {
				echo "Error: Name is missing";
			}
			$myCampaign->name = $match[1];
			preg_match("'\[subject\](.*?)\[/subject\]'si", $tagdata, $match);
			if (!isset($match[1]) || $match[1] == "") {
				echo "Error: Subject is missing";
			}
			$myCampaign->subject = $match[1];
			preg_match("'\[fromname\](.*?)\[/fromname\]'si", $tagdata, $match);
			if (!isset($match[1]) || $match[1] == "") {
				echo "Error: FromName is missing";
			}
			$myCampaign->fromName = $match[1];
		
			preg_match("'\[fromemail\](.*?)\[emailaddress\](.*?)\[/emailaddress\](.*?)\[/fromemail\]'si", $tagdata, $match);
			if (!isset($match[2]) || $match[2] == "") {
				echo "Error: FromEmail is missing";
			}
			$email = trim($match[2]);
			$allemails = $VerifiedEmailAddresses;
			do {
				// Gets the next link if there are more than 50 results to
				// be returned
				$moreVerifiedEmailAddresses = $ConstantContact->getVerifiedAddresses($VerifiedEmailAddresses['nextLink']);
				
				foreach ( $VerifiedEmailAddresses['addresses'] as $VerifiedEmailAddress ) {
					if ($VerifiedEmailAddress->email == $email) {
						$myCampaign->fromAddress = $VerifiedEmailAddress;
						$fromAddress = $VerifiedEmailAddress;
						break 2; // We found our email address so we can exit our
						       // foreach and while
					}
				}
				// Sets original Verified emaillAddresses to the next 50 so
				// it will replace its values
				$VerifiedEmailAddresses = $moreVerifiedEmailAddresses;
			} while ( $VerifiedEmailAddresses ['nextLink'] != false );
			if (!isset($fromAddress)) { echo "Error: FromEmail is invalid"; }
			preg_match("'\[replytoemail\](.*?)\[emailaddress\](.*?)\[/emailaddress\](.*?)\[/replytoemail\]'si", $tagdata, $match);
			if (!isset($match[2]) || $match[2] == "") {
				echo "Error: ReplyToEmail is missing";
			}
			$email = trim($match[2]);
			$VerifiedEmailAddresses = $allemails;
			do {
				// Gets the next link if there are more than 50 results to
				// be returned
				$moreVerifiedEmailAddresses = $ConstantContact->getVerifiedAddresses ( $VerifiedEmailAddresses ['nextLink'] );
				foreach ( $VerifiedEmailAddresses ['addresses'] as $VerifiedEmailAddress ) {
					if ($VerifiedEmailAddress->email == $email) {
						$myCampaign->replyAddress = $VerifiedEmailAddress;
						$replyAddress = $VerifiedEmailAddress;
						break 2; // We found our email address so we can exit our
						       // foreach and while
					}
				}
				// Sets original Verified emaillAddresses to the next 50 so
				// it will replace its values
				$VerifiedEmailAddresses = $moreVerifiedEmailAddresses;
			} while ( $VerifiedEmailAddresses ['nextLink'] != false );
			if (!isset($replyAddress)) { echo "Error: ReplyToEmail is invalid"; }
			preg_match("'\[permissionreminder\](.*?)\[/permissionreminder\]'si", $tagdata, $match);
			if (!isset($match[1]) || $match[1] == "") {
				echo "Error: PermissionReminder is missing";
			}
			$myCampaign->permissionReminder = $match[1];
			preg_match("'\[permissionremindertext\](.*?)\[/permissionremindertext\]'si", $tagdata, $match);
			if (!isset($match[1])) {
				echo "Error: PermissionReminderText is missing";
			}
			$myCampaign->permissionReminderText = $match[1];
			preg_match("'\[greetingname\](.*?)\[/greetingname\]'si", $tagdata, $match);
			if (!isset($match[1])) {
				echo "Error: GreetingName is missing";
			}
			$myCampaign->greetingName = $match[1];
			preg_match("'\[greetingsalutation\](.*?)\[/greetingsalutation\]'si", $tagdata, $match);
			if (!isset($match[1])) {
				echo "Error: GreetingSalutation is missing";
			}
			$myCampaign->greetingSalutation = $match[1];
			preg_match("'\[greetingstring\](.*?)\[/greetingstring\]'si", $tagdata, $match);
			if (!isset($match[1])) {
				echo "Error: GreetingString is missing";
			}
			$myCampaign->greetingString = $match[1];
			preg_match("'\[organizationname\](.*?)\[/organizationname\]'si", $tagdata, $match);
			if (!isset($match[1]) || $match[1] == "") {
				echo "Error: OrganizationName is missing";
			}
			$myCampaign->orgName = $match[1];
			preg_match("'\[organizationaddress1\](.*?)\[/organizationaddress1\]'si", $tagdata, $match);
			if (!isset($match[1]) || $match[1] == "") {
				echo "Error: OrganizationAddress1 is missing";
			}
			$myCampaign->orgAddr1 = $match[1];
			preg_match("'\[organizationaddress2\](.*?)\[/organizationaddress2\]'si", $tagdata, $match);
			if (!isset($match[1])) {
				echo "Error: OrganizationAddress2 is missing";
			}
			$myCampaign->orgAddr2 = $match[1];
			preg_match("'\[organizationaddress3\](.*?)\[/organizationaddress3\]'si", $tagdata, $match);
			if (!isset($match[1])) {
				echo "Error: OrganizationAddress3 is missing";
			}
			$myCampaign->orgAddr3 = $match[1];
			preg_match("'\[organizationcity\](.*?)\[/organizationcity\]'si", $tagdata, $match);
			if (!isset($match[1]) || $match[1] == "") {
				echo "Error: OrganizationCity is missing";
			}
			$myCampaign->orgCity = $match[1];
			preg_match("'\[organizationstate\](.*?)\[/organizationstate\]'si", $tagdata, $match);
			if (!isset($match[1])) {
				echo "Error: OrganizationState is missing";
			}
			$myCampaign->orgState = $match[1];

			preg_match("'\[organizationpostalcode\](.*?)\[/organizationpostalcode\]'si", $tagdata, $match);
			if (!isset($match[1]) || $match[1] == "") {
				echo "Error: OrganizationPostalCode is missing";
			}
			$myCampaign->orgPostalCode = $match[1];
			preg_match("'\[organizationcountry\](.*?)\[/organizationcountry\]'si", $tagdata, $match);
			if (!isset($match[1]) || $match[1] == "") {
				echo "Error: OrganizationCountry is missing";
			}
			$myCampaign->orgCountry = $match[1];
			// Validates that if the user selects United states, the stateOther
			// is empty
			if ($match[1] == "United States" || $match[1] == "US") {
				$myCampaign->orgInternationalState == NULL;
			} else {
				preg_match("'\[organizationinternationalstate\](.*?)\[/organizationinternationalstate\]'si", $tagdata, $match);
				if (!isset($match[1]) || $match[1] == "") {
					echo "Error: OrganizationInternationalState is missing";
				}
				$myCampaign->orgInternationalState = $match[1];
			}
			preg_match("'\[includeforwardemail\](.*?)\[/IncludeForwardEmail\]'si", $tagdata, $match);
			if (!isset($match[1]) || $match[1] == "") {
				echo "Error: IncludeForwardEmail is missing";
			}
			$myCampaign->incForwardEmail = $match[1];
			preg_match("'\[IncludeSubscribeLink\](.*?)\[/IncludeSubscribeLink\]'si", $tagdata, $match);
			if (!isset($match[1]) || $match[1] == "") {
				echo "Error: IncludeSubscribeLink is missing";
			}
			$myCampaign->incSubscribeLink = $match[1];
			preg_match("'\[ForwardEmailLinkText\](.*?)\[/ForwardEmailLinkText\]'si", $tagdata, $match);
			if (!isset($match[1])) {
				echo "Error: ForwardEmailLinkText is missing";
			}
			$myCampaign->forwardEmailLinkText = $match[1];
			preg_match("'\[SubscribeLinkText\](.*?)\[/SubscribeLinkText\]'si", $tagdata, $match);
			if (!isset($match[1])) {
				echo "Error: SubscribeLinkText is missing";
			}
			$myCampaign->subscribeLinkText = $match[1];
			preg_match("'\[ViewAsWebpage\](.*?)\[/ViewAsWebpage\]'si", $tagdata, $match);
			if (!isset($match[1])) {
				echo "Error: ViewAsWebpage is missing";
			}
			$myCampaign->vawp = $match[1];
			preg_match("'\[ViewAsWebpageText\](.*?)\[/ViewAsWebpageText\]'si", $tagdata, $match);
			if (!isset($match[1])) {
				echo "Error: ViewAsWebpageText is missing";
			}
			$myCampaign->vawpText = $match[1];
			preg_match("'\[ViewAsWebpageLinkText\](.*?)\[/ViewAsWebpageLinkText\]'si", $tagdata, $match);
			if (!isset($match[1])) {
				echo "Error: ViewAsWebpageLinkText is missing";
			}
			$myCampaign->vawpLinkText = $match[1];
			preg_match("'\[EmailContentFormat\](.*?)\[/EmailContentFormat\]'si", $tagdata, $match);
			if (!isset($match[1]) || $match[1] == "") {
				echo "Error: EmailContentFormat is missing";
			}
			$myCampaign->emailContentFormat = $match[1];
			preg_match("'\[StyleSheet\](.*?)\[/StyleSheet\]'si", $tagdata, $match);
			if (!isset($match[1])) {
				echo "Error: StyleSheet is missing";
			}
			$myCampaign->styleSheet = $match[1];
			preg_match("'\[emailcontent\](.*?)\[/emailcontent\]'si", $tagdata, $match);
			if (!isset($match[1]) || $match[1] == "") {
				echo "Error: EmailContent is missing";
			}
			$myCampaign->emailContent = htmlspecialchars($match[1],null,null,false);
			preg_match("'\[emailtextcontent\](.*?)\[/emailtextcontent\]'si", $tagdata, $match);
			if (!isset($match[1]) || $match[1] == "") {
				echo "Error: EmailTextContent is missing";
			}
			$myCampaign->textVersionContent = htmlspecialchars($match[1],null,null,false);
		    preg_match("'\[lists\](.*?)\[/lists\]'si", $tagdata, $match);
		    if (!isset($match[1]) || $match[1] == "") {
				echo "Error: Lists is missing";
			}
			$lists = explode("|",$match[1]);
			$ccListOBJ = new CC_List($this->cc_username,$this->cc_password, $this->cc_api_key); 
			$allLists = $ccListOBJ->getLists();
			$lists2 = array();
			foreach ($allLists as $list) {
				$key = explode('/',$list['id']);
				$key = array_pop($key); // Get the id off the end of the string
				if (in_array($key, $lists)) { // Is it in the list to be checked by default?
					$lists2[] = $list['id'];
				}
			}
			if (count($lists2) == 0) {
				echo "ERROR: No valid lists selected.";
			}
			$myCampaign->lists = $lists2;
			
			// Adds your new campaign to your Constant Contact Account
			if ($debug) { 
				echo "\n\n".$myCampaign->createXml()."\n\n"; 
			}
			$CampaignResult = $ConstantContact->addCampaign ( $myCampaign, $fromAddress );
			// $CampaignResult will now hold the referenced campaign object
		// Catch all errors
		} catch ( Exception $e ) {
			echo $e .' '.$e->getMessage()." needs to be corrected before submission";
		}
		echo "If no error message above, your campaign has been created.  Go to Constant Contact to Schedule and Send.";
	}
		
	/**
	 * Display usage notes in EE control panel
	 *
	 * @return string Usage notes
	 */	
	public static function usage()
	{
		return <<<EOT
lk_cc
=====

Constant Contact Plugin for ExpressionEngine

This addon interfaces with the constant contact API to allow website visitors to sign up for your newsletters by entering their email address and selecting the lists that they wish to sign up for.  It does not interface with current users in your EE memberbase.

If your multiple sites all use the same Constant Contact account this will work with multi-site manager.

If you would like to add features to this addon, feel free to fork the git-repository and/or work with me on it.  I unfortunately have a full time job and won't be able to add features unless they are required for one of my projects, but I will do my best to provide support to those using the addon and help anyone who wants to add more features to it.

Extension Settings
=====================================================
* Constant Contact Username: Your username
* Constant Contact Password: Your password
* Constant Contact API Key:
	* Get your API Key Here: http://community.constantcontact.com/t5/Documentation/API-Keys/ba-p/25015
	* Login, Click Request a New API Key
	* Fill in the form:
		* Multiple Accounts?  Select Yes if you will use this key on multiple websites otherwise select no.
		* Application Description - Website email signup form
		* You may leave the rest of the form blank.
	* Once you recieve a key, copy the key into the API Key setting.
* Save your settings.
* Click settings again.  If everything is working, your lists should now be listed in the List IDs multiselect.
* The number before the list name is the ID which will be used in the tags.

Plugin Basic Usage
=====================================================
Tag Usage
----------------------
* Default_list is the lists that should be checked by default, if omitted no lists will be checked by default
* Show_list is the lists to be shown, if omitted all lists will be shown.  The lists will be shown in the order the ids are listed.
* (You can find your list IDs in the extension settings.)
* Default_list and Show_list both support list ids separated by pipes.  They do not support the use of "not".
* Email and at least one list selection will be required, the name fields are optional.  Error message strings are defined in the language file.
* First and last name fields can be omitted if desired.  This script does not currently support any other Constant Contact fields.

```html
	{exp:lk_cc:subscribe default_list="3" show_list="2|5|6|3|4"}
		{if !success}
			<form action="{path="site/subscribe"}" method="post">
				<div class="clearboth">Please confirm your subscription.</div>
				<div class="clearboth error">{error_message}</div>
				<div class="float-left">
					<p><sup>*</sup>Your Email:<br /><input type="text" name="email" value="{email}" /></p>
					<p>First Name:<br /> <input type="text" name="first_name" value="{first_name}" /></p>
					<p>Last Name:<br /> <input type="text" name="last_name" value="{last_name}" /></span></p>
				</div>
				<div class="float-left"><ul class="none">
					<li>Subscribe to:</li>
					{lists}
						<li id="chk_{count}">
							<input id="chk_{count}" type="checkbox" value="{value}" name="lists[]" {checked} /> 
							<label for="chk_{count}">{name}</label>
						</li>
					{/lists}
				</ul></div>
				<div class="clearboth">
					<p><input type="submit" name="submit" value="Submit" class="contact" /></p></div>
			</form>
		{/if}
		{if success}
			<p>Thanks for subscribing!  Please check your email to confirm your subscription.</p>
		{/if}
	{/exp:lk_cc:subscribe}
```
		
Small form with just email:
--------------------------------------------
This form should submit to the page with the plugin tag code and can be used to embed an email subscribe form into all your website pages without calling the plugin until it is needed.
* Set the "confirm" hidden field so that "success" will not be set allowing the user to confirm, subscribe to additional lists etc.
* If you set default lists, when the user clicks submit they will be added to your default email lists so even if they don't submit the confirmation form, they have still be subscribed.
* Submitting the confirmation will allow them edit their information.

```html
	<form action="/asia/site/subscribe" target="_blank" method="post">
		<fieldset>
			<input type="hidden" name="confirm" value="confirm" />
			<label for="subscribe_email" class="screen-reader-text">Email Address</label> 
			<input type="text" value="" id="subscribe_email" name="email" />
			<input type="image" alt="Subscribe" src="/design/submit-button.png" value="Go" name="go" id="subscribe_image" />
		</fieldset>
	</form>
```

Creating a Campaign:
--------------------------------------------
See the readme file.

EOT;

	}
}

$plugin_info = array(
	'pi_name'			=> LK_CC_NAME,
	'pi_version'		=> LK_CC_VER,
	'pi_author'			=> LK_CC_AUTHOR,
	'pi_author_url'		=> LK_CC_DOCS,
	'pi_description'	=> LK_CC_DESC,
	'pi_usage'			=> Lk_cc::usage()
);