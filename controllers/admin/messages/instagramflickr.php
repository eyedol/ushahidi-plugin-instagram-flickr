<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Instagram Flickr
 */
class Instagramflickr_Controller extends Controller {

	public function __construct()
	{
		parent::__construct();
	}

	public function index() 
	{
		
	}

	/**
	 * Add photos to the database
	 * 
	 * @param  array $photos Details of the photos
	 * 
	 */
	private function _add_flickr($photos)
	{
		if (empty($photos) OR ! is_array($photos))
		{
			return;
		}

		foreach($photos as $photo) 
		{
			$reporter = ORM::factory('reporter')
				->where('service_id', $service->id)
				->where('service_account', $photo['flickr'])
				->find();
		}

		if (!$reporter->loaded == true)
		{

			// get default reporter level (Untrusted)
			$level = ORM::factory('level')
				->where('level_weight', 0)
				->find();

			// Add new reporter
			$reporter->service_id		= $service->id;
			$reporter->level_id			= $level->id;
			$reporter->service_account	= $photo['flickr']; 
			$reporter->reporter_first	= $photo['from'];
			$reporter->reporter_last	= null;
			$reporter->reporter_email	= null;
			$reporter->reporter_phone	= null;
			$reporter->reporter_ip		= null;
			$reporter->reporter_date	= date('Y-m-d');
			$reporter->save();
		}

		if ($reporter->level_id > 1 && 
				count(ORM::factory('instgramflickr')
					->where('service_photoid', $photo['photo_id'])
					->find_all()) == 0 )
			{
				// Save Email as Message
				$instgramflickr = new Instagramflickr_Model();
				$instgramflickr->parent_id = 0;
				$instgramflickr->incident_id = 0;
				$instgramflickr->user_id = 0;
				$instgramflickr->reporter_id = $reporter->id;
				$instgramflickr->photo_from = $photo['from'];
				$instgramflickr->photo_to = null;
				$instgramflickr->photo_title = $photo['title'];
				$instgramflickr->photo_description = $photo['description'];
				$instgramflickr->photo_type = 1; // Inbox
				$instgramflickr->photo_date = $photo['date'];
				$instgramflickr->service_photoid = $photo['photo_id'];
				$instgramflickr->latitude = $photo['latitude'];
				$instgramflickr->longitude = $photo['longitude'];
				$instgramflickr->save();

				//Add media
				$media = new Media_Model();
				$media->location_id = 0;
				$media->incident_id = 0;
				$media->message_id = $email->id;
				$media->media_type = 1; // Images
				$media->media_link = $photo['link'];
				$media->media_medium = $photo['medium'];
				$media->media_thumb = $photo['thumb'];
				$media->media_date = date("Y-m-d H:i:s",time());
				$media->save();

				// Auto-Create A Report if Reporter is Trusted
				$reporter_weight = $reporter->level->level_weight;
				$reporter_location = $reporter->location;
				if ($reporter_weight > 0 AND $reporter_location)
				{
					// Create Incident
					$incident = new Incident_Model();
					$incident->location_id = $reporter_location->id;
					$incident->incident_title = $photo['title'];
					$incident->incident_description = $photo['description'];
					$incident->incident_date = $photo['date'];
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
						->where("message_id", $instgramflickr->id)
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