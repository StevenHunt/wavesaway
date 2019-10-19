<?php
  include_once('html_dom.php');

  // Obtaining user's IP address (using regex to search the data)
  $ip = preg_replace('#[^0-9.]#', '', getenv('REMOTE_ADDR'));

  // Using IP-API:
  $api_url= "http://ip-api.com/json/".$ip."?fields=query,zip,lat,lon,city";

  // I had an issue accessing ip-api, due to the SSL certificate, so I had to manually provide the browser user-agent as a workdaround. 
  $arrContextOptions=array(
      'ssl' => array(
          'verify_peer'=> true,
          'verify_peer_name'=> true,
      ),
      'https' => array(
          'header' => 'User-Agent: Mozilla compatible',
      ),
  );

  // Open / Read entire URL into string, passing the additional headers: (path, include_path, context)
  $json = file_get_contents($api_url, false, stream_context_create($arrContextOptions));

  // Decode JSON string (convert JSON to PHP):
  $data = json_decode($json);
  $zip = $data->zip;
  $city = $data->city;

  // Webpage to scrape:
  $url = 'https://www.radiolineup.com/locate/'.$zip;

  // Create stream context with specified User-Agent in HTTP header:
  $context = stream_context_create(array('http' => array('header' => 'User-Agent: Mozilla compatible')));

  // Create DOM object of URL with specified stream context:
  $html = file_get_html($url, false, $context);

  // Loop through html and find all specified data (in <td> tag) and trimming white spaces: 
  $data = array();
  foreach($html->find('table tr td') as $e) {
      $data[] = trim($e->innertext);
  }

  // Function to chunk / split array into specified parts:
  function array_split($array, $parts){
    return array_chunk($array, ceil(count($array) / $parts));
  }

  // Individualizing array by columns: 
  $freq = array_column(array_split($data, sizeof($data)/5), 0); # Column 0 is the frequency
  $format = array_column(array_split($data, sizeof($data)/5), 2); # Column 2 is the format
  $distance = array_column(array_split($data, sizeof($data)/5), 3); # Column 3 is the distance from zip

 // REMOVE DUPLICATE FREQUENCIES, BASED ON BROADCAST DISTANCE FROM USER ==============================
 // Loop through array that contains count of all frequencies, put frequences with
 // value > 1 into new array, essentially returning all requencies with duplicate values:
 $duplicates = array();
# Loop through an array that contains a count of all the frequencies ------ 
 foreach (array_count_values($freq) as $key => $var) {
   # If a frequency has multiple entries (>1), put that key into an array 'duplicates'
   if ($var > 1) {
     $duplicates[] = $key;
   }
 };

 // Find key values of duplicate frequencies
 $dup_keys = array();
 # Loop through all of the duplicate values: 
 for ($i = 0; $i < count($duplicates); $i++) {
   # Put all of the duplicate key values into a new array called 'dup_keys'
   $dup_keys[] = array_keys($freq, $duplicates[$i]);
 }

 // Get first key location and count of each duplicate frequency
 $first = array();
 $count = array();
 foreach ($dup_keys as $f){
   $first[] = $f[0]; // First key location of duplicate freq 
   $count[] = count($f); // How many times does the duplicate freq appear
 }

 // Using array_map (passes each val of an array to a function), convert distances array from string to float via 'floatval', and
 // remove 'miles' from each element via str_replace, essentially returning an array that just has the distances of each frequency
 $distance_fl = array_map('floatval', str_replace(' miles', "", $distance));

 // $loc_count_dups: Contains the location and count of all the duplicate values by combining $first and $count, 
 // to make multidimensional array.
 $loc_count_dups = array_combine($first, $count);

 
 $dup_dist = array();
 // Loop through the array containing the locations (first and count) of all duplicates: 
 foreach($loc_count_dups as $first => $count){
     // and return an new array that now only contains the distances of each of the duplicate value. 
     $dup_dist[] = array_slice($distance_fl, $first, $count, true); // Set 'true' to keep original key.
 }
 
// Return array containing the smallest 
 $small_indx = array();
 // Loop through array (containing freq dist of each duplicate value), and return the key that has the smallest value of each duplicate. 
 foreach($dup_dist as $key => $val) {
     $small_indx[] = array_keys($val, min($val));
 }

 // Smallest: Contains the indexes of the smallest values of each frequency that has duplicates.
 $smallest =  array_column($small_indx,0);

 // Comparing the new array ($smallest) containing the keys of the smallest values of the duplicate values, against the 
 // complete list of duplicate keys and returning the difference (which will result in the ones that need deleting). 
 $delete_indexes = array_diff(call_user_func_array('array_merge', $dup_keys), $smallest);

 // Removing (unset) furthest distance duplicates from $freq and $format arrays:
 foreach($delete_indexes as $k => $v){
   unset($freq[$v]);
   unset($format[$v]);
 }

 // Re-Formatting Call-signs: ===================================================================

 // Replace all blank formates with 'Other':
 $format = array_map(function($value) {
   return $value === "" ? "Other" : $value;
 }, $format);

 // Change format 'Hot AC' to 'Top 40':
 $format = array_map(function($value) {
   return $value === "Hot AC" ? "Top-40" : $value;
 }, $format);

 // Change format 'Rhythmic CHR' to '80's to Today Hits':
 $format = array_map(function($value) {
   return $value === "Rhythmic CHR" ? "80's to Today's Hits" : $value;
 }, $format);

 // Change format 'Soft Adult Contemporary' to 'Soft Hits':
 $format = array_map(function($value) {
   return $value === "Soft Adult Contemporary" ? "Soft Hits" : $value;
 }, $format);

 // Change format 'CHR' to 'Contemporary Hits':
 $format = array_map(function($value) {
   return $value === "CHR" ? "Contemporary Hits" : $value;
 }, $format);

// ===============================================================================================
  
  // Return only the values (duplicates now removed)
  $form_arr = array_values($format);
  $freq_arr = array_values($freq);

  /*
    - Find the first occurance of 'AM' in frequency array.
    - Cannot use array_search since 'AM' isn't located at the beginning of string
    - This function finds first occurnace of value in array (can even be a partial value)
  */

  // The Needle:
  $am = 'AM';

  function array_find($needle, array $haystack) {
    foreach ($haystack as $key => $value) {
      if (false !== stripos($value, $needle)) {
        return $key;
      }
    }
    return false;
  }

  // First index where AM is found:
  $key = array_find($am, $freq_arr);
 
  // Size of freq and form arrays: 
  $freq_size = sizeof($freq_arr);
  $form_size = sizeof($form_arr);

  // Slice arrays where AM frequencies begin: 
  $fm_freq = array_slice($freq_arr, 0, $key);
  $am_freq = array_slice($freq_arr, $key, $freq_size);

  $fm_form = array_slice($form_arr, 0, $key);
  $am_form = array_slice($form_arr, $key, $form_size);

  // Combine the two AM and FM freq w/ their corresponding formats:  
  $fm_combine = array_combine($fm_freq, $fm_form);
  $am_combine = array_combine($am_freq, $am_form);

?>

<html>
<head>

	<title> Waves Away </title>

	<meta name="viewport" content="width=device-width, initial-scale=1">

	<!-- Bootstrap 4 -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>

    <!-- JQuery -->
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
	
	<!-- External CSS --> 
	<link rel='stylesheet' href='css/style.css'>

</head>
<body>

<!-- Begin page content -->
<div class="container-flex">
	<div class="page-header">
		<p id="loc-header"></p> 
	</div>

	<br>

	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-6 col-xs-6">
			<table class="table table-striped sticky-header">
				<thead>
					<tr>
						<th colspan="3" style="text-align:center;">FM Stations </th>
					</tr>
				</thead>
				<tbody>
				<?php
					// Loop through FM stations, printing their freq and formats
					foreach ($fm_combine as $fm_freq =>  $fm_form):
						$fm_html .= "<tr>";
						$fm_html .= "<td></td>";
						$fm_html .= "<td>".$fm_freq."</td>";
						$fm_html .= "<td>".$fm_form."</td>";
						$fm_html .= "</tr>";
					endforeach;
					echo $fm_html;
				?>
				</tbody>
			</table>
		</div> <!-- Close Col --> 

		<br> <br> <br>

		<div class="col-lg-6 col-md-6 col-sm-6 col-xs-6">
			<table class="table table-striped sticky-header">
				<thead>
					<tr>
							<th colspan="3" style="text-align:center;">AM Stations </th>
					</tr>
				</thead>
				<tbody>
				<?php
					// Loop through AM stations, printing their freq and formats
					foreach ($am_combine as $am_freq =>  $am_form):
						$am_html .= "<tr>";
						$am_html .= "<td></td>";
						$am_html .= "<td>".$am_freq."</td>";
						$am_html .= "<td>".$am_form."</td>";
						$am_html .= "</tr>";
					endforeach;
					echo $am_html;
				?>
				</tbody>
			</table>
		</div> <!-- Close Col --> 
	</div> <!-- Close Row --> 
</div> <!-- Close Container --> 

<!-- Script for Google Maps: This provides a much more accurate city / neighborhood location --> 
<script>
	var x = document.getElementById("loc-header");

	if (navigator.geolocation) {
		navigator.geolocation.getCurrentPosition(locateSuccess, locateFail);
	}

	function locateSuccess(loc) {
		var latitude = loc.coords.latitude;
		var longitude = loc.coords.longitude;
		var url = 'https://maps.googleapis.com/maps/api/geocode/json?latlng=' + latitude +',' + longitude + '&key=MyAPIKey';

		$.getJSON(url, function(data) {

			// Processing fetched data: 
			var data = data.plus_code.compound_code; // "ABCD+## Some City, State, USA"
			var city_state = data.substr(data.indexOf(' ')+1); // Some City, State, USA"
			var city = city_state.substr(0,city_state.indexOf(',')); // Some City"
			
			// Apostrophy placement: 
			if (city.endsWith("s")) {
				x.innerHTML = city + "'" + " Radio Stations";  
			} else {
				x.innerHTML = city + "'s" + " Radio Stations";  
			}

			console.log(data); 
		})
	}

	function locateFail(geoPositionError) {
		switch (geoPositionError.code) {
			case 0: // UNKNOWN_ERROR
				alert('An unknown error occurred, sorry');
				break;
			case 1: // PERMISSION_DENIED
				alert('Permission to use Geolocation was denied');
				break;
			case 2: // POSITION_UNAVAILABLE
				alert('Couldn\'t find you...');
				break;
			case 3: // TIMEOUT
				alert('The Geolocation request took too long and timed out');
				break;
		default:
		}
	}
</script> 

</body>
</html>


