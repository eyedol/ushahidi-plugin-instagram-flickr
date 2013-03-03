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

		// Hook into the messages controller
		if (Router::$controller == 'messages' AND Router::$method == 'index')
		{ 
			// Only when we're on the flickr and instagram pages
			// FIXME:: use a better ID to identify flickr and instragm services
			// HERE 4 is Flickr and 5 is Instagram
			if (Router::$segments[3] == 4 OR Router::$segments[3] == 5 )
			{ 
				Event::add('ushahidi_action.admin_messages_custom_layout', array($this,'_instagramflickr_view'));
			}
		}


		Event::add('ushahidi_action.nav_admin_settings', array($this,
			'_settings_link'));
	}

	/**
	 * Add a links to messages sub navs
	 */
	public function _flickr_link() 
	{
		$service_id = Event::$data;
		
		//FIXME:: This is the not the best. There could be a service with 
		//the IDs below
		echo ($service_id == 4) ? "<li class=\"active\"><a>".Kohana::lang('instagramflickr.flickr_link')."</a></li>" : 
			"<li><a href=\"".url::site()."admin/messages/index/4\">"
			.Kohana::lang('instagramflickr.flickr_link')."</a></li>";

		echo ($service_id == 5) ? "<li class=\"active\"><a>".Kohana::lang('instagramflickr.instagram_link')."</a></li>" : 
			"<li><a href=\"".url::site()."admin/messages/index/5\">"
			.Kohana::lang('instagramflickr.instagram_link')."</a></li>";	

	}

	/**
	 * Settings sub tab link
	 */
	public function _settings_link()  {
		$this_sub_page = Event::$data;
		echo ($this_sub_page == "instagramflickr") ? 
		"<li class=\"active\"><a>".Kohana::lang('instagramflickr.title')."</a></li>" : 
		"<li><a href=\"".url::site()."admin/instagramflickr\">"
		.Kohana::lang('instagramflickr.title')."</a>";
	}

	public function _instagramflickr_view() 
	{
		
		$view = View::factory('admin/messages/instagramflickr_view');
		
		//TODO:: Pass necessary variables to the  view file so it displays 
		//the needed content
		//Query the instagramflickr table for the needed content.

		//fetch flickrwijit settings from db
		$settings = ORM::factory('instagramflickr_settings',1);
		
		$f = $this->_get_flickr_images();
		//enable caching
		
		if( $settings->enable_cache == 1 ) 
		{
			$f->enableCache("fs", "application/cache");	
		}

		$photos = $f->photos_search( array(
			'tags' => $settings->flickr_tag,
			'per_page' => $settings->block_no_photos,
			'user_id' => $settings->flickr_id ) );
		$view->f = $f;
		$view->photos = $photos;
		$view->render(TRUE);	
	}

	public function _get_flickr_images() 
	{
		include Kohana::find_file('libraries/phpflickr','phpFlickr');
		
		$f = new phpFlickr(Kohana::config('instagramflickr.flick_api_key'));
		//enable caching
		return $f;
	}
}
new instagramflickr;