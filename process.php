<?php

require 'config.php';

echo "Starting...<hr />";

// Import users array
require('users.php');

	foreach ($users as &$user)
	{
		$user['lat'] = NULL;
		$user['long'] = NULL;
		$user['updated_at'] = 0;
		$user['city'] = NULL;
		$user['spot'] = NULL;
		$user['service'] = NULL;
		$user['service_url'] = NULL;

		//TODO Check to see if foursquare is not null before cURLing
		if($user['foursquare'] !== NULL)
		{


			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, 'http://api.foursquare.com/v1/history.json');
			curl_setopt($ch, CURLOPT_USERPWD, $user['foursquare']['username'].':'.$user['foursquare']['password']);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

			$result = curl_exec($ch);

			if ($result !== FALSE)
			{
				$history = json_decode($result, TRUE);

				if (isset($history['checkins']) === TRUE && count($history['checkins']) > 0)
				{
					$user['service'] = 'foursquare';
					$user['service_url'] = 'http://foursquare.com/venue/'.$history['checkins'][0]['venue']['id'];
					$user['spot'] = $history['checkins'][0]['venue']['name'];
					$user['lat'] = $history['checkins'][0]['venue']['geolat'];
					$user['long'] = $history['checkins'][0]['venue']['geolong'];
					$user['updated_at'] = strtotime($history['checkins'][0]['created']);
				}
			}

			curl_close($ch);

			unset($user['foursquare']['password']);
		}

		//Gowalla
		if($user['gowalla'] !== NULL)
		{
			$base_url = 'http://api.gowalla.com';

			$ch = curl_init();

			$headers = array('X-Gowalla-API-Key: ' . GOWALLA_API_KEY, 'Accept: application/json');

			curl_setopt($ch, CURLOPT_URL, $base_url.'/users/'.$user['gowalla']['username']);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

			$result = curl_exec($ch);

			if ($result !== FALSE)
			{
				$result = json_decode($result, true);

				$last_checkin_spot = $result['last_checkins'][0]['spot']['url'];

				if (strtotime($result['last_checkins'][0]['created_at']) > $user['updated_at'])
				{
					$user['service'] = 'gowalla';
					$user['service_url'] = 'http://www.gowalla.com'.$result['last_checkins'][0]['url'];

					$user['updated_at'] = strtotime($result['last_checkins'][0]['created_at']);

					curl_setopt($ch, CURLOPT_URL, $base_url.$last_checkin_spot);

					$result = curl_exec($ch);

					if ($result !== FALSE)
					{
						$result = json_decode($result, true);
						$user['spot'] = $result['name'];
						$user['lat'] = $result['lat'];
						$user['long'] = $result['lng'];

					}
				}

			}
		}

		// Twitter
		if ($user['twitter'] !== NULL)
		{
			$base_url = 'http://api.twitter.com';

			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $base_url.'/status/user_timeline/'.$user['twitter']['username'].'.json?count=20');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

			$result = curl_exec($ch);

			if ($result !== FALSE)
			{
				$result = json_decode($result, true);

				if (isset($result['error']) !== TRUE)
				{

					foreach ($result as $item)
					{
						if (isset($item['place']))
						{
							$coordinates = $item['place']['bounding_box']['coordinates'][0];

							if (strtotime($item['created_at']) > $user['updated_at'])
							{
								$user['service'] = 'twitter';
								//TODO Get URL to exact Tweet, couldn't do it when building was out of API calls :)
								$user['service_url'] = 'http://twitter.com/';
								$user['spot'] = NULL;
								$user['updated_at'] = strtotime($item['created_at']);
								$user['lat'] = ($coordinates[1][1] + $coordinates[2][1]) / 2;
								$user['long'] = ($coordinates[0][0] + $coordinates[1][0]) / 2;
							}

							if (empty($item['user']['profile_image_url']) === FALSE)
							{
								$user['avatar_url'] = $item['user']['profile_image_url'];
							}


						}
					}
				}
			}
		}


		// Get nearby place name
		if ($user['lat'] !== NULL && $user['long'] !== NULL)
		{
			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, 'http://ws.geonames.org/findNearbyPlaceNameJSON?lat='.$user['lat'].'&lng='.$user['long']);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

			$result = curl_exec($ch);

			if ($result !== FALSE)
			{
				$result = json_decode($result, true);

				$user['city'] = $result['geonames'][0]['name'].", ".$result['geonames'][0]['adminCode1'];

			}
		}

		// Replace NULL avatars
		if ($user['avatar_url'] === NULL)
		{
			$user['avatar_url'] = "http://ext.youversion.com/img/avatars/default.png";
		}

	} // end loop

	// write the cache
	$h = fopen('cache.js', 'w+');
	$data = array('created_at' => time());
	$data['users'] = $users;
	fwrite($h, json_encode($data));
	fclose($h);

	echo "Done.";

?>
