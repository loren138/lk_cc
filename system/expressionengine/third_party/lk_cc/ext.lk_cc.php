<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once PATH_THIRD . 'lk_cc/config.php';

/**
 * LK Popular, pulls popular entries
 */
class Lk_cc_ext {

	public $name			= LK_CC_NAME;
	public $version			= LK_CC_VER;
	public $description		= LK_CC_DESC;
	public $docs_url		= LK_CC_DOCS;
	public $settings_exist	= 'y';

	public $settings		= array();
	public $config_loc		= FALSE;
	
	public $EE;

	/**
	 * Constructor
	 *
	 * @param 	mixed	Settings array or empty string if none exist.
	 * @return void
	 */
	public function __construct($settings = array())
	{
		$this->EE =& get_instance();

		// initialise default settings array
		$this->settings = $this->_default_settings();
		
		/*
		 * Try to determine where Lk_cc is being configured.
		 * This check is only reliable on the front end.
		 * ================================================ */
		// first check config
		if ($this->EE->config->item('lk_cc_cc_username') && $this->EE->config->item('lk_cc_cc_password') && $this->EE->config->item('lk_cc_cc_api_key'))
		{
			$this->config_loc = 'config';
		}
		// check in global varas
		elseif (array_key_exists('lk_cc_cc_username', $this->EE->config->_global_vars) && array_key_exists('lk_cc_cc_password', $this->EE->config->_global_vars) && array_key_exists('lk_cc_cc_api_key', $this->EE->config->_global_vars))
		{
			$this->config_loc = 'global';
		}
		// assume db (default)
		else
		{
			$this->config_loc = 'db';
		}
	}
	// END


	/**
	 * Activate Extension
	 * @return void
	 */
	public function activate_extension()
	{
		$data = array(
			'class'		=> __CLASS__,
			'hook'		=> 'fake_hook_install',
			'method'	=> 'do_nothing',
			'settings'	=> serialize($this->settings),
			'priority'	=> 10,
			'version'	=> $this->version,
			'enabled'	=> 'y'
		);
		
		$this->EE->db->insert('extensions', $data);
	}
	// END


	/**
	 * Disable Extension
	 *
	 * @return void
	 */
	public function disable_extension()
	{
		$this->EE->db->where('class', __CLASS__);
		$this->EE->db->delete('extensions');
	}
	// END


	/**
	 * Used by plugin, retrieves settings from config, global variables OR database (and in that order)
	 *
	 * @return void
	 */
	public function get_settings()
	{
		// if settings are already in session cache, use those
		if (isset($this->EE->session->cache['lk_cc']['settings']))
		{
			$this->settings = $this->EE->session->cache['lk_cc']['settings'];
			return;
		}
		
		// retrieve config settings (location may vary)
		switch ($this->config_loc) :

			case ('config') :
				$this->settings['cc_api_key'] = $this->EE->config->item('lk_cc_cc_api_key');
				$this->settings['cc_username'] = $this->EE->config->item('lk_cc_cc_username');
				$this->settings['cc_password'] = $this->EE->config->item('lk_cc_cc_password');
				if (isset($this->EE->TMPL)) {
					$this->EE->TMPL->log_item('LK CC has retrieved settings from config.');
				}
			break;
			
			case ('global') :
				$this->settings['cc_api_key'] = $this->EE->config->_global_vars['lk_cc_cc_api_key'];
				$this->settings['cc_username'] = $this->EE->config->_global_vars['lk_cc_cc_username'];
				$this->settings['cc_password'] = $this->EE->config->_global_vars['lk_cc_cc_password'];
				if (isset($this->EE->TMPL)) {
					$this->EE->TMPL->log_item('LK CC has retrieved settings from global variables.');
				}
			break;
			
			case ('db') :
			default :
				$this->EE->db
							->select('settings')
							->from('extensions')
							->where(array('enabled' => 'y', 'class' => __CLASS__ ))
							->limit(1);
				$query = $this->EE->db->get();
				
				if ($query->num_rows() > 0)
				{
					$this->settings = unserialize($query->row()->settings);
					if (isset($this->EE->TMPL)) {
						$this->EE->TMPL->log_item('LK CC has retrieved settings from DB.');
					}
				}
				else
				{
					if (isset($this->EE->TMPL)) {
						$this->EE->TMPL->log_item('LK CC has not yet been configured.');
					}
				}
			break;

		endswitch;

		// normalize settings before adding to session
		$this->settings = $this->_normalize_settings($this->settings);
		
		// now set to session for subsequent calls
		$this->EE->session->cache['lk_cc'] = array(
			'settings' => array(),
			'js' => array(),
			'css' => array()
		);
		$this->EE->session->cache['lk_cc']['settings'] = $this->settings;
		
	}
	// END

	/**
	 * Method for fake hook just in case it gets called
	 */
	public function do_nothing($parm1=false, $param2=false, $param3=false)
	{
		return;
	}

	/**
	 * The available settings
	 */
	function settings()
	{
    	$settings = array();
	
    	$settings['cc_username']    = array('i', '', "");
	    $settings['cc_password']    = array('i', '', "");
    	$settings['cc_api_key']     = array('i', '', "");
	    $settings['cc_newsletters'] = array('ms', $this->newsletters(), array());

    	return $settings;
	}
	
	/**
	 * Newsletters
	 *
	 * Returns the available newsletter options and their IDs
	 *
	 * @return	array	Array keys are list ids, Values is a string id - name
	 */
	function newsletters() {
		$this->get_settings();
		if(!$this->settings['cc_username'] || !$this->settings['cc_password'] || !$this->settings['cc_api_key']) {
			return array(0 => 'LK CC has not yet been configured');
		}
		if ( ! class_exists('CC_Utility',false))
		{
			require_once(PATH_THIRD . 'lk_cc/cc_class.php');
		}
		$ccListOBJ = new CC_List($this->settings['cc_username'],$this->settings['cc_password'],
			$this->settings['cc_api_key']); 
		$allLists = $ccListOBJ->getLists();
		$return = array();
		foreach ($allLists as $list) {
			$key = explode('/',$list['id']);
			$key = array_pop($key); // Get the id off the end of the string
			$return[$key] = $key.' - '.$list['title'];
		}
		return $return;
	}

	/**
	 * Update Extension
	 *
	 * @param 	string	String value of current version
	 * @return 	mixed	void on update / false if none
	 */
	public function update_extension($current = '')
	{
		if ($current == '' OR $current == $this->version)
		{
			return FALSE;
		}
		
		// update table row with version
		$this->EE->db->where('class', __CLASS__);
		$this->EE->db->update(
			'extensions', 
			array('version' => $this->version)
		);
	}
	// END

	
	/**
	 * Returns a default array of settings
	 *
	 * @return array default settings & values
	 */
	function _default_settings()
	{
		return array(
			'cc_username'	=> '',
			'cc_password'	=> '',
			'cc_api_key'	=> '',
		);
	}

	
	/**
	 * Standardise settings just to be safe!
	 *
	 * @param array an array of options to be normalised
	 * @return void
	 */
	private function _normalize_settings($settings)
	{
		// this ensures we avoid any PHP errors
		$settings = array_merge($this->_default_settings(), $settings);

		// required
		$settings['cc_username'] = trim($settings['cc_username']);
		$settings['cc_password'] = trim($settings['cc_password']);
		$settings['cc_api_key'] = trim($settings['cc_api_key']);
		
		return $settings;
	}
	// END

}
// END CLASS