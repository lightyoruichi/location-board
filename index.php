<?php

/* Works out the time since the entry post, takes a an argument in unix time (seconds) */
function time_since($original) {
    // array of time period chunks
    $chunks = array(
        array(60 * 60 * 24 * 365 , 'year'),
        array(60 * 60 * 24 * 30 , 'month'),
        array(60 * 60 * 24 * 7, 'week'),
        array(60 * 60 * 24 , 'day'),
        array(60 * 60 , 'hour'),
        array(60 , 'minute'),
    );

    $today = time(); /* Current unix time  */
    $since = $today - $original;

    // $j saves performing the count function each time around the loop
    for ($i = 0, $j = count($chunks); $i < $j; $i++) {

        $seconds = $chunks[$i][0];
        $name = $chunks[$i][1];

        // finding the biggest chunk (if the chunk fits, break)
        if (($count = floor($since / $seconds)) != 0) {
            // DEBUG print "<!-- It's $name -->\n";
            break;
        }
    }

    $print = ($count == 1) ? '1 '.$name : "$count {$name}s";

    if ($i + 1 < $j) {
        // now getting the second item
        $seconds2 = $chunks[$i + 1][0];
        $name2 = $chunks[$i + 1][1];

        // add second item if it's greater than 0
        if (($count2 = floor(($since - ($seconds * $count)) / $seconds2)) != 0) {
            $print .= ($count2 == 1) ? ', 1 '.$name2 : ", $count2 {$name2}s";
        }
    }
    return $print;
}

// check for file
if (file_exists('cache.js')) {
	$h = fopen("cache.js", "r");
	$data = fread($h, filesize("cache.js"));
	fclose($h);
}
else
{
	die("cache does not exist! run process.php to generate.");
}

$data = json_decode($data, TRUE);
$users = $data['users'];

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
<title>Location Board</title>

<style type="text/css">
  html { height: 100% }
  body { height: 100%; margin:0 auto; padding: 0px; font-family: Arial;}

  #map_canvas {
	float:left;
	width:74%;
	height: 100%;
	}
#users {
	float:right;
	width:25%;
	padding: 15px 0px;
	height: 95%;
	overflow: auto;
	}

	.user {
		border-bottom: 1px solid #ccc;
		clear: both;
	}

	.current {
		min-height: 54px;
		margin: 5px;
	}
	.old {
		color: #000;
		padding: 5px;
		opacity: 0.2;
	}
</style>

<script type="text/javascript">
var users = <?php echo json_encode($users); ?>
</script>

<script type="text/javascript">

	var map = null;

  function initialize() {
    var myLatlng = new google.maps.LatLng(35.2596352,-95.58807988);
    var myOptions = {
      zoom: 8,
      center: myLatlng,
      mapTypeId: google.maps.MapTypeId.ROADMAP
    }
    map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);

    setMarkers(map, users);
  }


function recenter (lat, long) {
	var center = new google.maps.LatLng(lat, long);
	//map.setCenter(center);
	map.panTo(center);

	// Check to see if already at current zoom level that way
	// the panTo called above animates instead of "jumping" if
	// the user clicked is already in the viewable map
	var current_zoom = map.getZoom();
	if (current_zoom !== 12) {
		setTimeout(function() {
			map.setZoom(12);
		}, 500);
	}
}


function setMarkers(map, users) {

   var bound = new google.maps.LatLngBounds();

  for (var i = 0; i < users.length; i++) {
  	var user = users[i];

  	if (user.lat != null || user.long != null) {
  			var old = false;

  			var foo = new Date; // Generic JS date object
			var unixtime_ms = foo.getTime(); // Returns milliseconds since the epoch
			var unixtime = parseInt(unixtime_ms / 1000);

  			// green = 3 hours, yellow = 12 hours, red = 24 hours, else don't show
  			if ((unixtime - user.updated_at) < 10800) {
  				var image = 'green.png';
  			} else if ((unixtime - user.updated_at) < 43200) {
  				var image = 'yellow.png';
  			} else if ((unixtime - user.updated_at) <= 86400) {
  				var image = 'red.png';
  			} else if ((unixtime - user.updated_at) > 86400) {
  				var image = null;
  				old = true;
  			}

  			// set shadow

  			if (!old) {

	  			var shadow = new google.maps.MarkerImage(image,
		  		new google.maps.Size(74, 78),
		  		new google.maps.Point(0,0),
			    new google.maps.Point(37, 78));
			      var shape = {
				      coord: [1, 1, 1, 20, 18, 20, 18 , 1],
				      type: 'poly'
				  };

		  		// set avatar
		  	  image = new google.maps.MarkerImage(user.avatar_url,
		      // This marker is 20 pixels wide by 32 pixels tall.
		      new google.maps.Size(48, 48),
		      // The origin for this image is 0,0.
		      new google.maps.Point(0,0),
		      // The anchor for this image is the base of the flagpole at 0,32.
		      new google.maps.Point(24, 68));


		      var myLatLng = new google.maps.LatLng(user.lat, user.long);
		      var marker = new google.maps.Marker({
		        position: myLatLng,
		        map: map,
		        shadow: shadow,
		        icon: image,
		        shape: shape,
		        title: user.username,
		        zIndex: i,
		    });

		    bound.extend(myLatLng);
	    }
    }

  }

  map.fitBounds(bound);
}

  function loadScript() {
    var script = document.createElement("script");
    script.type = "text/javascript";
    script.src = "http://maps.google.com/maps/api/js?sensor=false&callback=initialize";
    document.body.appendChild(script);
  }

  window.onload = loadScript;
</script>

<?php

// Sort the users

function usort_cmp($a, $b) {
	if ($a == $b) return 0;
	return ($a['updated_at'] > $b['updated_at']) ? -1 : 1;
}


usort($users, "usort_cmp");

?>

</head>
<body>
<div id="map_canvas"></div>
  <div id="users">
  	<?php foreach ($users as $user) : ?>
  		<div class="user">
  			<?php if ($user['updated_at'] != NULL && (time() - $user['updated_at']) < 86400) : ?>
				<div class="current">
  				<div style="float: right; padding: 1px; border: 2px solid #ccc;"><a href="javascript:recenter(<?php echo $user['lat']?>, <?php echo $user['long']?>);"><img border="0" width="48" height="48" src="<?php echo $user['avatar_url']?>" /></a></div>
  				<a href="javascript:recenter(<?php echo $user['lat']?>, <?php echo $user['long']?>);"><?php echo $user['username']?></a><br />
    			<small>
    			<?php echo ($user['spot'] !== NULL) ? $user['spot']."<br />" : ''; ?>
    			<?php echo $user['city']?><br />
    			<em><a href="<?php echo $user['service_url'] ?>"><?php echo time_since($user['updated_at']) ?> ago with <?php echo ucfirst($user['service'])?></a></em></small>
    		</div>
    		<?php else : ?>
    			<div class="old"><?php echo $user['username']?></div>
    		<?php endif ?>
  		</div>

  	<?php endforeach ?>
  </div>

</body>
</html>
