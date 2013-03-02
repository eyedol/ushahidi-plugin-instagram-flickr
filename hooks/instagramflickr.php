<?php defined('SYSPATH') or die('No direct script access.');

class instagramflickr {

	/**
	 * Registers the main event add method
	 */
	public function __construct()
	{
		// Hook into routing
		Event::add('system.pre_controller', array($this, 'add'));
	}

	/**
	 * Adds all the events to the main Ushahidi application
	 */
	public function add()
	{
		// Add a Sub-Nav Link to messages links
		Event::add('ushahidi_action.nav_admin_messages', array($this, '_flickr_link'));
	}

	public function _flickr_link() 
	{
		$service_id = Event::$data;
		
		echo ($service_id == 4) ? Kohana::lang('instagramflickr.flickr_link') : 
			"<a href=\"".url::site()."admin/messages/index/4\">"
			.Kohana::lang('instagramflickr.flickr_link')."</a>";	
	}

	public function _get_flickr_images() {
		include Kohana::find_file('libraries/phpflickr','phpFlickr');
		
		$f = new phpFlickr(Kohana::config('flickrwijit.flick_api_key'));
		//enable caching
		return $f;
	}
}