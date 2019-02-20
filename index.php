<?php
  include_once('html_dom.php');

  // Obtaining user's IP address
  $ip = preg_replace('#[^0-9.]#', '', getenv('REMOTE_ADDR'));

  // Using IP-API:
  $api_url= "http://ip-api.com/json/?fields=query,zip,lat,lon,city";

  // Function to bypass HTTPS SSL verification:
  $arrContextOptions=array(
      'ssl' => array(
          'verify_peer'=> true,
          'verify_peer_name'=> true,
      ),
      'https' => array(
          'header' => 'User-Agent: Mozilla compatible',
      ),
  );

  // Open / Read entire URL into string:
  $json = file_get_contents($api_url, false, stream_context_create($arrContextOptions));

  // Decode JSON string:
  $data = json_decode($json);
  $zip = $data->zip;
  $city = $data->city;

  // Webpage to scrape:
  $url = 'https://www.radiolineup.com/locate/'.$zip;

  // Create stream context with specified User-Agent in HTTP header:
  $context = stream_context_create(array('http' => array('header' => 'User-Agent: Mozilla compatible')));

  // Create DOM object of URL with specified streatm context:
  $html = file_get_html($url, false, $context);

  // Loop through html and find specified content:
  $data = array();
  foreach($html->find('table tr td') as $e) {
      $data[] = trim($e->innertext);
  }

  // Split / Chunk array into specified parts:
  function array_split($array, $parts){
    return array_chunk($array, ceil(count($array) / $parts));
  }

  // Individualizing array by column:
  $freq = array_column(array_split($data, sizeof($data)/5), 0);
  $format = array_column(array_split($data, sizeof($data)/5), 2);
  $distance = array_column(array_split($data, sizeof($data)/5), 3);

 // Remove duplicate frequencies, based on broadcast distance from user ==============================
 // Loop through array that contains count of all frequencies, put frequences with
 // value > 1 into new array, essentially returning all requencies with duplicate values:
 $duplicates = array();
 foreach (array_count_values($freq) as $key => $var) {
   if ($var > 1) {
     $duplicates[] = $key;
   }
 };

 // Find key values of duplicate frequencies:
 $dup_keys = array();
 for ($i = 0; $i < count($duplicates); $i++) {
   $dup_keys[] = array_keys($freq, $duplicates[$i]);
 }

 // Get first key location and count of each duplicate frequency:
 $first = array();
 $count = array();
 foreach ($dup_keys as $f){
   $first[] = $f[0];
   $count[] = count($f);
 }

 // Convert distances from string to float and remove ' miles' from each element:
 $floats = array_map('floatval', str_replace(' miles', "", $distance));

 // Combining to make multidimensional array of first location(key) and the count,
 $location_dups = array_combine($first, $count);
 // ... then get all distances of the duplicated values:
 $foo = array();
 foreach($location_dups as $first => $count){
     $foo[] = array_slice($floats, $first, $count, true); // Set 'true' to keep original key.
 }

 // Smallest: Contains the indexes of the smallest values of each frequency that has duplicates.
 $small_indx = array();
 foreach($foo as $key => $val) {
     $small_indx[] = array_keys($val, min($val));
 }
 $smallest =  array_column($small_indx,0);

 // List of indexes that need to be deleted from $freq and $format:
 $delete_indexes = array_diff(call_user_func_array('array_merge', $dup_keys), $smallest);

 // Remove (unset) furthest distance duplicates from $freq and $format arrays:
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

  $freq_size = sizeof($freq_arr);
  $form_size = sizeof($form_arr);

  $fm_freq = array_slice($freq_arr, 0, $key);
  $am_freq = array_slice($freq_arr, $key, $freq_size);

  $fm_form = array_slice($form_arr, 0, $key);
  $am_form = array_slice($form_arr, $key, $form_size);

  $fm_combine = array_combine($fm_freq, $fm_form);
  $am_combine = array_combine($am_freq, $am_form);

?>

<html>
<head>

	<title> Test Waves </title>

	<meta name="viewport" content="width=device-width, initial-scale=1">

	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.0/css/bootstrap.min.css" integrity="sha384-PDle/QlgIONtM1aqA2Qemk5gPOE7wFq8+Em+G/hmo5Iq0CCmYZLv3fVRDJ4MMwEA" crossorigin="anonymous">

	<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
	<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.0/js/bootstrap.min.js" integrity="sha384-7aThvCh9TypR7fIc2HV4O/nFMVCBwyIUKL8XCtKE+8xgCgl/PQGuFsvShjr74PBp" crossorigin="anonymous"></script>

	<style>

	html{
			margin-top:0;
			padding-top:0;
	}

	body{
			margin-top:0;
			padding-top:0;

	}

		table.floatThead-table {
				border-top: none;
				border-bottom: none;
				background-color: gray;
		}

		th {
			position: sticky;
			background: lightgray;
			top: 0px;
		}

		.table{
			text-overflow: ellipsis;
			counter-reset: row-num;
			white-space: nowrap !important;
			overflow-x:auto;
		}

		.table th {
			text-align: center;
		}

		.table tbody tr  {
		counter-increment: row-num;
		}

		.table tr td:first-child::before {
		content: counter(row-num) ".";
		}

		.table tr td:first-child {
		text-align: center;
		}

	</style>

</head>
<body>

<!-- Begin page content -->
<div class="container">

	<div class="page-header">
		<h3 style="text-align:center;">
			<?php
				// Using regex to see if last letter of the city ends with 's'.
				if (preg_match("/s$/", $city)) {
					echo  $city . "' Radio Stations";
				} else {
					echo $city . "'s Radio Stations";
				}
			?>
		</h3>
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
							foreach ($fm_combine as $fm_freq =>  $fm_form):
									$fm_html .= "<tr>";
									$fm_html .= "<td></td>";
									$fm_html .= "<td>".$fm_freq."</td>";
									$fm_html .= "<td>".$fm_form."</td>";
									$fm_html .= "</tr>";
							endforeach;
							echo $fm_html;
					?>
				</table>
				</tbody>
		</table>
		</div>

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
							foreach ($am_combine as $am_freq =>  $am_form):
									$am_html .= "<tr>";
									$am_html .= "<td></td>";
									$am_html .= "<td>".$am_freq."</td>";
									$am_html .= "<td>".$am_form."</td>";
									$am_html .= "</tr>";
							endforeach;
							echo $am_html;
					?>
				</table>
				</tbody>
		</table>
		</div>
</div>
</div>
</body>
</html>


