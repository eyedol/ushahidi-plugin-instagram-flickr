<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Instagram Flickr
 */
class Instagramflickr_Controller extends Controller {

	private $flicrk;
	public function __construct()
	{
		parent::__construct();
	}

	public function index() 
	{
		$this->flickr = flickr::fetch_flickr_images();

		// Fetch flickr settings from db
		$settings = ORM::factory('instagramflickr_settings',1);
		
		$photos = $this->flickr->photos_search( array(
			'tags' => $settings->flickr_tag,
			'per_page' => $settings->block_no_photos,
			'user_id' => $settings->flickr_id ) );

		$this->_add_flickr($photos);
	}

	/**
	 * Add photos to the database
	 * 
	 * @param  array $photos Details of the photos
	 * 
	 */
	private function _add_flickr($photos)
	{
		$service = ORM::factory('service')
			->where('service_name', 'Flickr')
			->find();
		
		if ( ! $service->loaded)
		{
			return;
		}

		if (empty($photos['photo']) OR ! is_array($photos['photo']))
		{
			return;
		}
		
		foreach($photos['photo'] as $photo) 
		{
			// Get details of the photo	
			$photo_info = $this->flickr->photos_getInfo($photo['id'], 
				$photo['secret']);
			
			$name = $photo_info['owner']['realname'];

			$username = $photo_info['owner']['username']; 

			$title = $photo_info['title'];

			$photo_id = $photo_info['id'];

			$date = $photo_info['dates']['posted'];

			$description = $photo_info['description'];

			$photo_link = $this->flickr->buildPhotoURL($photo, "Large");
			
			$photo_medium = $this->flickr->buildPhotoURL($photo);
			
			$photo_thumb = $this->flickr->buildPhotoURL($photo,'Square');

			// Check if there is location attached to the photo
			if( ( $photo_info['location'] != NULL) AND 
				(is_array($photo_info['location'])))
				{
					$latitude = $photo_info['location']['latitude'];

					$longitude = $photo_info['location']['longitude'];
				}
				else 
				{
					$latitude = "";

					$longitude = "";
				}

			$reporter = ORM::factory('reporter')
				->where('service_id', $service->id)
				->where('service_account', $username)
				->find();
			
			if (!$reporter->loaded == true)
			{
				// Add new reporter
				$names = explode(' ', $name, 2);
				$last_name = '';
				if (count($names) == 2) {
					$last_name = $names[1]; 
				}

				// get default reporter level (Untrusted)
				$level = ORM::factory('level')
					->where('level_weight', 0)
					->find();

				// Add new reporter
				$reporter->service_id		= $service->id;
				$reporter->level_id			= $level->id;
				$reporter->service_account	= $username; 
				$reporter->reporter_first	= $names[0];
				$reporter->reporter_last	= $last_name;
				$reporter->reporter_email	= null;
				$reporter->reporter_phone	= null;
				$reporter->reporter_ip		= null;
				$reporter->reporter_date	= date('Y-m-d');
				$reporter->save();
			}

			if ($reporter->level_id > 1 AND 
					count(ORM::factory('instagramflickr')
						->where('service_photoid', $photo_id)
						->find_all()) == 0 )
			{
				// Save Email as Message
				$instagramflickr = new Instagramflickr_Model();
				$instagramflickr->parent_id = 0;
				$instagramflickr->incident_id = 0;
				$instagramflickr->user_id = 0;
				$instagramflickr->reporter_id = $reporter->id;
				$instagramflickr->photo_from = $name;
				$instagramflickr->photo_to = null;
				$instagramflickr->photo_title = $title;
				$instagramflickr->photo_description = $description;
				$instagramflickr->photo_type = 1; // Inbox
				$instagramflickr->photo_date = $date;
				$instagramflickr->service_photoid = $photo_id;
				$instagramflickr->latitude = $latitude;
				$instagramflickr->longitude = $longitude;
				$instagramflickr->save();

				//Add media
				$media = new Media_Model();
				$media->location_id = 0;
				$media->incident_id = 0;
				$media->message_id = $instagramflickr->id;
				$media->media_type = 1; // Images
				$media->media_link = $photo_link;
				$media->media_medium = $photo_medium;
				$media->media_thumb = $photo_thumb;
				$media->media_date = date("Y-m-d H:i:s",time());
				$media->save();

				// Auto-Create A Report if Reporter is Trusted
				$reporter_weight = $reporter->level->level_weight;
				$reporter_location = $reporter->location;
				//Auto-Create A Report if there is location
				if ($reporter_weight > 0 AND $reporter_location)
				{
					// Create Incident
					$incident = new Incident_Model();
					$incident->location_id = $reporter_location->id;
					$incident->incident_title = $title;
					$incident->incident_description = $description;
					$incident->incident_date = $date;
					$incident->incident_dateadd = date("Y-m-d H:i:s",time());
					$incident->incident_active = 1;
					if ($reporter_weight == 2)
					{
						$incident->incident_verified = 1;
					}
					if ($reporter->user_id > 0)
					{
						$incident->user_id = $reporter->user_id;
					}
					$incident->save();

					// Update Message with Incident ID
					$email->incident_id = $incident->id;
					$email->save();

					// Save Incident Category
					$trusted_categories = ORM::factory("category")
						->where("category_trusted", 1)
						->find();
					if ($trusted_categories->loaded)
					{
						$incident_category = new Incident_Category_Model();
						$incident_category->incident_id = $incident->id;
						$incident_category->category_id = $trusted_categories->id;
						$incident_category->save();
					}

					// Add media
					$media = ORM::factory("media")
						->where("message_id", $instagramflickr->id)
						->find_all();
					foreach ($media AS $m)
					{
						$m->incident_id = $incident->id;
						$m->save();
					}
				}
			}
		}
	}
} 