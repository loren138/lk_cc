<?php
	date_default_timezone_set('America/New_York');
    /**
    * Class that is using REST to communicate with ConstantContact server
 	* This class currently supports actions performed using the contacts, lists, and campaigns APIs
    * @author ConstantContact Dev Team
    * @version 2.0.0
    * @since 30.03.2010 
    */
	
    class CC_Utility {
    	           
        // FROM HERE YOU MAY MODIFY YOUR CREDENTIALS        
        var $login = ''; //Username for your account
        var $password = ''; //Password for your account
        var $apikey = ''; // API Key for your account.
        
        // CONTACT LIST OPTIONS
        var $contact_lists = array(); // Define which lists will be available for sign-up.
        var $force_lists = false; // Set this to true to take away the ability for users to select and de-select lists
        var $show_contact_lists = true; // Set this to false to hide the list name(s) on the sign-up form.
        // NOTE - Contact Lists will only be hidden if force_lists is set to true. This is to prevent available checkboxes form being hidden.
                
        // FORM OPT IN SOURCE - (Who is performing these actions?)
        var $actionBy = 'ACTION_BY_CONTACT'; // Values: ACTION_BY_CUSTOMER or ACTION_BY_CONTACT
        // ACTION_BY_CUSTOMER - Constant Contact Account holder. Used in internal applications.
        // ACTION_BY_CONTACT - Action by Site visitor. Used in web site sign-up forms.
        
        // DEBUGGING
        var $curl_debug = true; // Set this to true to see the response code returned by cURL
        
        // YOUR BASIC CHANGES SHOULD END HERE
        var $requestLogin; //this contains full authentication string.
        var $lastError = ''; // this variable will contain last error message (if any)
        var $apiPath = 'https://api.constantcontact.com/ws/customers/'; //is used for server calls.
        var $doNotIncludeLists = array('Removed', 'Do Not Mail', 'Active'); //define which lists shouldn't be returned.

        
        public function __construct($login,$password,$apikey) {
        	$this->login = $login;
        	$this->password = $password;
        	$this->apikey = $apikey;
            //when the object is getting initialized, the login string must be created as API_KEY%LOGIN:PASSWORD
            $this->requestLogin = $this->apikey."%".$this->login.":".$this->password;
            $this->apiPath = $this->apiPath.$this->login;
        }
                        
        /**
        * Validate an email address
        * @return  TRUE if address is valid and FALSE if not.
        */    
        protected function isValidEmail($email){
             return preg_match('/^[_a-z0-9-][^()<>@,;:"[] ]*@([a-z0-9-]+.)+[a-z]{2,4}$/i', $email);
        } 
        
        /**
        * Private function used to send requests to ConstantContact server
        * @param string $request - is the URL where the request will be made
        * @param string $parameter - if it is not empty then this parameter will be sent using POST method
        * @param string $type - GET/POST/PUT/DELETE
        * @return a string containing server output/response
        */    
        protected function doServerCall($request, $parameter = '', $type = "GET") {
            $ch = curl_init();
            $request = str_replace('http://', 'https://', $request);
            // Convert id URI to BASIC compliant
            curl_setopt($ch, CURLOPT_URL, $request);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $this->requestLogin);
            # curl_setopt ($ch, CURLOPT_FOLLOWLOCATION  ,1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Content-Type:application/atom+xml", 'Content-Length: ' . strlen($parameter)));
            curl_setopt($ch, CURLOPT_FAILONERROR, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            switch ($type) {
                case 'POST':                  
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $parameter);
                    break;
                case 'PUT':
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $parameter);
                    break;
                case 'DELETE':
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                    break;
                default:
                    curl_setopt($ch, CURLOPT_HTTPGET, 1);
                    break;
            }

           $emessage = curl_exec($ch);          
           if ($this->curl_debug) {   echo $error = curl_error($ch);   }
           curl_close($ch);
           
           return $emessage;
        }
         
    }

    /**
    * Class that is used for retrieving 
    * all the Email Lists from Constant Contact and 
    * all Registered Email Addresses 
    */
    class CC_List extends CC_Utility {
        
        /**
        * Recursive Method that retrieves all the Email Lists from ConstantContact.
        * @param string $path [default is empty]
        * @return array of lists
        */    
        public function getLists($path = '') {
            $mailLists = array();
            
            if ( empty($path)) {
                $call = $this->apiPath.'/lists';
            } else {
                $call = $path;
            }

            $return = $this->doServerCall($call);
            if ($return == "") return array(array('id'=>'','title'=>'API Error: Please check username, password, and API key.'));
            $parsedReturn = simplexml_load_string($return);
            $call2 = '';
            
            foreach ($parsedReturn->link as $item) {
                $tmp = $item->Attributes();
                $nextUrl = '';      
                if ((string) $tmp->rel == 'next') {
                    $nextUrl = (string) $tmp->href;
                    $arrTmp = explode($this->login, $nextUrl);
                    $nextUrl = $arrTmp[1];
                    $call2 = $this->apiPath.$nextUrl;
                    break;
                  }
            }

            foreach ($parsedReturn->entry as $item) {
                if ($this->contact_lists ){
                if (in_array((string) $item->title, $this->contact_lists)) {
                    $tmp = array();
                    $tmp['id'] = (string) $item->id;
                    $tmp['title'] = (string) $item->title;
                    $mailLists[] = $tmp;
                   }
                } else if (!in_array((string) $item->title, $this->doNotIncludeLists)) {
                    $tmp = array();
                    $tmp['id'] = (string) $item->id;
                    $tmp['title'] = (string) $item->title;
                    $mailLists[] = $tmp;
                }
            }
          
            if ( empty($call2)) {
                return $mailLists;
            } else {
                return array_merge($mailLists, $this->getLists($call2));
            }

        }
        
        /**
        * Method that retrieves  all Registered Email Addresses.
        * @param string $email_id [default is empty]
        * @return array of lists
        */           
        public function getAccountLists($email_id = '') {        
            $mailAccountList = array();
            
            if ( empty($email_id)) {
                $call = $this->apiPath.'/settings/emailaddresses';
            } else {
                $call = $this->apiPath.'/settings/emailaddresses/'.$email_id; 
            }

            $return = $this->doServerCall($call);
            $parsedReturn = simplexml_load_string($return);
              
            foreach ($parsedReturn->entry as $item) {               
                $nextStatus = $item->content->Email->Status;
                $nextEmail = (string) $item->title;
                $nextId = $item->id; 
                $nextAccountList = array('Email'=>$nextEmail, 'Id'=>$nextId);
                if($nextStatus == 'Verified'){  
                    $mailAccountList[] = $nextAccountList; 
                }         
            }
            return $mailAccountList;
        }   
           
    }
    
    
    /**
    * Class that is used for ConstantConact CRUD management
    */
	class CC_Contact extends CC_Utility {		

    /**
     * Method that checks if a subscriber already exist
     * @param string $email
     * @return subscriber`s id if it exists or false if it doesn't
     */ 
	 public	function subscriberExists($email = '') {
		 $call = $this->apiPath.'/contacts?email='.$email;
		 $return = $this->doServerCall($call);
		 $xml = simplexml_load_string($return);
		 $id = $xml->entry->id;
		 if($id){ return $id; }
		 else { return false; }
	 }
     
	 /**
     * Method that retrieves from Constant Contact a collection with all the Subscribers
     * If email parameter is mentioned then only mentioned contact is retrieved.
     * @param string $email
     * @return Bi-Dimenstional array with information about contacts.
     */    
	 public	function getSubscribers($email = '', $page = '') {
			$contacts = array();
			$contacts['items'] = array();
			
			if (! empty($email)) {
				$call = $this->apiPath.'/contacts?email='.$email;
			} else {			
				if (! empty($page)) {
					$call = $this->apiPath.$page;
				} else {
					$call = $this->apiPath.'/contacts';
				}
			}

			$return = $this->doServerCall($call);
			$parsedReturn = simplexml_load_string($return);
			// We parse here the link array to establish which are the next page and previous page
			foreach ($parsedReturn->link as $item) {
				$attributes = $item->Attributes();
				
				if (! empty($attributes['rel']) && $attributes['rel'] == 'next') {
					$tmp = explode($this->login, $attributes['href']);
					$contacts['next'] = $tmp[1];
				}			
				if (! empty($attributes['rel']) && $attributes['rel'] == 'first') {
					$tmp = explode($this->login, $attributes['href']);
					$contacts['first'] = $tmp[1];
				}		
				if (! empty($attributes['rel']) && $attributes['rel'] == 'current') {
					$tmp = explode($this->login, $attributes['href']);
					$contacts['current'] = $tmp[1];
				}
			}

			foreach ($parsedReturn->entry as $item) {
				$tmp = array();
				$tmp['id'] = (string) $item->id;
				$tmp['title'] = (string) $item->title;
				$tmp['status'] = (string) $item->content->Contact->Status;
				$tmp['EmailAddress'] = (string) $item->content->Contact->EmailAddress;
				$tmp['EmailType'] = (string) $item->content->Contact->EmailType;
				$tmp['Name'] = (string) $item->content->Contact->Name;
				$contacts['items'][] = $tmp;
			}
			
			return $contacts;
		}

	 /**
     * Retrieves all the details for a specific contact identified by $email.
     * @param string $email
     * @return array with all information about the contact.
     */    
	 public	function getSubscriberDetails($email) {
			$contact = $this->getSubscribers($email);
			$fullContact = array();
			$call = str_replace('http://', 'https://', $contact['items'][0]['id']);
			// Convert id URI to BASIC compliant
			$return = $this->doServerCall($call);
			$parsedReturn = simplexml_load_string($return);
			$fullContact['id'] = $parsedReturn->id;
			$fullContact['email_address'] = $parsedReturn->content->Contact->EmailAddress;
			$fullContact['first_name'] = $parsedReturn->content->Contact->FirstName;
			$fullContact['last_name'] = $parsedReturn->content->Contact->LastName;
			$fullContact['middle_name'] = $parsedReturn->content->Contact->MiddleName;
			$fullContact['company_name'] = $parsedReturn->content->Contact->CompanyName;
			$fullContact['job_title'] = $parsedReturn->content->Contact->JobTitle;
			$fullContact['home_number'] = $parsedReturn->content->Contact->HomePhone;
			$fullContact['work_number'] = $parsedReturn->content->Contact->WorkPhone;
			$fullContact['address_line_1'] = $parsedReturn->content->Contact->Addr1;
			$fullContact['address_line_2'] = $parsedReturn->content->Contact->Addr2;
			$fullContact['address_line_3'] = $parsedReturn->content->Contact->Addr3;
			$fullContact['city_name'] = (string) $parsedReturn->content->Contact->City;
			$fullContact['state_code'] = (string) $parsedReturn->content->Contact->StateCode;
			$fullContact['state_name'] = (string) $parsedReturn->content->Contact->StateName;
			$fullContact['country_code'] = $parsedReturn->content->Contact->CountryCode;
			$fullContact['zip_code'] = $parsedReturn->content->Contact->PostalCode;
			$fullContact['sub_zip_code'] = $parsedReturn->content->Contact->SubPostalCode;
			$fullContact['custom_field_1'] = $parsedReturn->content->Contact->CustomField1;
			$fullContact['custom_field_2'] = $parsedReturn->content->Contact->CustomField2;
			$fullContact['custom_field_3'] = $parsedReturn->content->Contact->CustomField3;
			$fullContact['custom_field_4'] = $parsedReturn->content->Contact->CustomField4;
			$fullContact['custom_field_5'] = $parsedReturn->content->Contact->CustomField5;
			$fullContact['custom_field_6'] = $parsedReturn->content->Contact->CustomField6;
			$fullContact['custom_field_7'] = $parsedReturn->content->Contact->CustomField7;
			$fullContact['custom_field_8'] = $parsedReturn->content->Contact->CustomField8;
			$fullContact['custom_field_9'] = $parsedReturn->content->Contact->CustomField9;
			$fullContact['custom_field_10'] = $parsedReturn->content->Contact->CustomField10;
			$fullContact['custom_field_11'] = $parsedReturn->content->Contact->CustomField11;
			$fullContact['custom_field_12'] = $parsedReturn->content->Contact->CustomField12;
			$fullContact['custom_field_13'] = $parsedReturn->content->Contact->CustomField13;
			$fullContact['custom_field_14'] = $parsedReturn->content->Contact->CustomField14;
			$fullContact['custom_field_15'] = $parsedReturn->content->Contact->CustomField15;
			$fullContact['notes'] = $parsedReturn->content->Contact->Note;
			$fullContact['mail_type'] = $parsedReturn->content->Contact->EmailType;
			$fullContact['status'] = $parsedReturn->content->Contact->Status;
			$fullContact['lists'] = array();
			
			if ($parsedReturn->content->Contact->ContactLists->ContactList) {
				foreach ($parsedReturn->content->Contact->ContactLists->ContactList as $item) {
					$fullContact['lists'][] = trim((string) $item->Attributes());
				}
			}

			return $fullContact;
		}

	 /**
     * Upload a new contact to Constant Contact server
     * @param strong $contactXML - formatted XML with contact information
     * @return TRUE in case of success or FALSE otherwise
     */    
	 public	function addSubscriber($contactXML) {
			$call = $this->apiPath.'/contacts';
			$return = $this->doServerCall($call, $contactXML, 'POST');
			$parsedReturn = simplexml_load_string($return);	

			if ($return) {
				return true;
			} else {
				$xml = simplexml_load_string($contactXML);
				$emailAddress = $xml->content->Contact->EmailAddress;
				if ($this->subscriberExists($emailAddress)){ 
				$this->lastError = 'This contact already exists. <a href="edit_contact.php?email='.$emailAddress.'">Click here</a> to edit the contact details.'; 
				} else { $this->lastError = 'An Error Occurred'; }
				return false;
			}
		}

	 /**
     * Modifies a contact
     * @param string $contactUrl - identifies the url for the modified contact
     * @param string $contactXML - formed XML with contact information
     * @return TRUE in case of success or FALSE otherwise
     */    
	 public	function editSubscriber($contactUrl, $contactXML) {
			$return = $this->doServerCall($contactUrl, $contactXML, 'PUT');
			
			if (! empty($return)) {		      		
				if (strpos($return, '<') !== false) {                
					$parsedReturn = simplexml_load_string($return);	           				
					if (! empty($parsedReturn->message)) {
						$this->lastError = $parsedReturn->message;
					}  
				} else {
					$this->lastError = $parsedReturn->message;
				}     
				return false;
			}      
			return true;
		}

	 /**
     * Method that compose the needed XML format for a contact
     * @param string $id
     * @param array $params
     * @return Formed XML
     */    
	 public	function createContactXML($id, $params = array()) {			
			if ( empty($id)) {
				$id = "urn:uuid:E8553C09F4xcvxCCC53F481214230867087";
			}

			$update_date = date("Y-m-d").'T'.date("H:i:s").'+01:00';
			$xml_string = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><entry xmlns='http://www.w3.org/2005/Atom'></entry>";
			$xml_object = simplexml_load_string($xml_string);
			$title_node = $xml_object->addChild("title", htmlspecialchars(("TitleNode"), ENT_QUOTES, 'UTF-8'));
			$updated_node = $xml_object->addChild("updated", htmlspecialchars(($update_date), ENT_QUOTES, 'UTF-8'));
			$author_node = $xml_object->addChild("author");
			$author_name = $author_node->addChild("name", ("CTCT Samples"));
			$id_node = $xml_object->addChild("id", htmlspecialchars(($id),ENT_QUOTES, 'UTF-8'));
			$summary_node = $xml_object->addChild("summary", htmlspecialchars(("Customer document"),ENT_QUOTES, 'UTF-8'));
			$summary_node->addAttribute("type", "text");
			$content_node = $xml_object->addChild("content");
			$content_node->addAttribute("type", "application/vnd.ctct+xml");
			$contact_node = $content_node->addChild("Contact", htmlspecialchars(("Customer document"), ENT_QUOTES, 'UTF-8'));
			$contact_node->addAttribute("xmlns", "http://ws.constantcontact.com/ns/1.0/");
			$email_node = $contact_node->addChild("EmailAddress", htmlspecialchars(($params['email_address']), ENT_QUOTES, 'UTF-8'));
			$fname_node = $contact_node->addChild("FirstName", urldecode(htmlspecialchars(($params['first_name']), ENT_QUOTES, 'UTF-8')));
			$lname_node = $contact_node->addChild("LastName", urldecode(htmlspecialchars(($params['last_name']), ENT_QUOTES, 'UTF-8')));
			$optin_node = $contact_node->addChild("OptInSource", htmlspecialchars($this->actionBy));
			/*$lname_node = $contact_node->addChild("MiddleName", urldecode(htmlspecialchars(($params['middle_name']), ENT_QUOTES, 'UTF-8')));
			if(isset($params['company_name']))
			{
				$lname_node = $contact_node->addChild("CompanyName", urldecode(htmlspecialchars(($params['company_name']), ENT_QUOTES, 'UTF-8')));	
			}
	 		if(isset($params['JobTitle']))
			{
				$lname_node = $contact_node->addChild("job_title", urldecode(htmlspecialchars(($params['job_title']), ENT_QUOTES, 'UTF-8')));
			}
			if(isset($params['status']))
			{
				if ($params['status'] == 'Do Not Mail') {
					$this->actionBy = 'ACTION_BY_CONTACT';
				}
			}
			$hn_node = $contact_node->addChild("HomePhone", htmlspecialchars($params['home_number'], ENT_QUOTES, 'UTF-8'));
	 		if(isset($params['work_number']))
			{
				$wn_node = $contact_node->addChild("WorkPhone", htmlspecialchars($params['work_number'], ENT_QUOTES, 'UTF-8'));
			}
			$ad1_node = $contact_node->addChild("Addr1", htmlspecialchars($params['address_line_1'], ENT_QUOTES, 'UTF-8'));
			$ad2_node = $contact_node->addChild("Addr2", htmlspecialchars($params['address_line_2'], ENT_QUOTES, 'UTF-8'));
			$ad3_node = $contact_node->addChild("Addr3", htmlspecialchars($params['address_line_3'], ENT_QUOTES, 'UTF-8'));
			$city_node = $contact_node->addChild("City", htmlspecialchars($params['city_name'], ENT_QUOTES, 'UTF-8'));
			$state_node = $contact_node->addChild("StateCode", htmlspecialchars($params['state_code'], ENT_QUOTES, 'UTF-8'));
			$state_name = $contact_node->addChild("StateName", htmlspecialchars($params['state_name'], ENT_QUOTES, 'UTF-8'));
			$ctry_node = $contact_node->addChild("CountryCode", htmlspecialchars($params['country_code'], ENT_QUOTES, 'UTF-8'));
			$zip_node = $contact_node->addChild("PostalCode", htmlspecialchars($params['zip_code'], ENT_QUOTES, 'UTF-8'));
			$subzip_node = $contact_node->addChild("SubPostalCode", htmlspecialchars($params['sub_zip_code'], ENT_QUOTES, 'UTF-8'));
	 		if(isset($params['notes']))
			{
				$note_node = $contact_node->addChild("Note", htmlspecialchars($params['notes'], ENT_QUOTES, 'UTF-8'));
			}*/
			$emailtype_node = $contact_node->addChild("EmailType", htmlspecialchars($params['mail_type'], ENT_QUOTES, 'UTF-8'));
			/*
			if (! empty($params['custom_fields'])) {
				foreach ($params['custom_fields'] as $k=>$v) {
					$contact_node->addChild("CustomField".$k, htmlspecialchars(($v), ENT_QUOTES, 'UTF-8'));
				}
			}*/

			$contactlists_node = $contact_node->addChild("ContactLists");			
			if ($params['lists']) {
				foreach ($params['lists'] as $tmp) {
					$contactlist_node = $contactlists_node->addChild("ContactList");
					$contactlist_node->addAttribute("id", $tmp);
				}
			}

			$entry = $xml_object->asXML();
			return $entry;
		}

    }

?>
