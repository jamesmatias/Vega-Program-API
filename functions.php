<?php

/*
//	getToken($token, &$tstamp)
//  Input: (String) $token (can be null), (DateTime) $tstamp: timestamp of token (cannot be null)
//			$tstamp is passed by reference and will be changed if a new token is created.
//	Output: If $token is still valid (within 5 minutes of creation), returns existing $token. 
//		Else returns new $token and updates $tstamp to current time
*/
function getToken($token, &$tstamp)
{
	include "apiconstants.php";
	
	// If token is expired, get new token
	if ($tstamp <= (time() - $token_expire_interval) || is_null($token))
	{
		//echo date("Y-m-d H:i:s")." Token expired or null. Requesting new token.\n";
		// Address for token request
		$tokenurl = $apiurl."/1.0/authorization";
		
		$postarr = array("secretKey" => $auth);
		$postBody = json_encode($postarr);
		
		$ch = curl_init($tokenurl);
		curl_setopt_array($ch,array(
				CURLOPT_POST => TRUE,
				CURLOPT_RETURNTRANSFER => TRUE,
				CURLOPT_HTTPHEADER => array(
						'Accept: application/json',						
						'Content-Type: application/json'
				),
				CURLOPT_POSTFIELDS => $postBody
		));

		$response = curl_exec($ch);

		if($response === FALSE){ // check if the curl response is a failure
			echo date("Y-m-d H:i:s")." ".curl_error($ch)."\n";
			return false;
		}

		$tokenData = json_decode($response, true);
		
		if(is_null($tokenData)){ // check if the json data that was returned is null. 
			echo date("Y-m-d H:i:s")." Could not retrieve token from server.\n";
			return false;
		}
		
		
		$token = $tokenData["data"]["token"];
		
		// Update the timestamp with the new expiration
		$tstamp = $tokenData["data"]["expires"];
		
		// Return the new token
		return $token;
		
	}
	else // otherwise do nothing, return original token
		return $token;
	
}

/*
//	getEvents($startDate, $endDate, &$token, &$tstamp)
//  Input: 	(String) $startDate, (String) $endDate - Start and End dates for search in 'd-m-Y' format
			(String) $token (can be null), (DateTime) $tstamp: timestamp of token (cannot be null)
//			$token and $tstamp are passed by reference and will be changed if a new token is created.
//	Output: If $token is not valid, updates $token and $tstamp
//			Returns array of events in the date range specified, up to 999 events. Array format as follows:
			$evData['status'] - http status code
			$evData['data'][] - event data variables, such as count, offset
			$evData['data']['event_list'][] - numbered list of returned events, beginning with 0
			$evData['data']['event_list'][0][] - event information such as event_id, name, description, categories, etc.			
*/
function getEvents($startDate, $endDate, &$token, &$tstamp)
{
	// date format is dd-mm-YYYY
	include "apiconstants.php";

	// Check the life of the token and renew if necessary.
	$token = getToken($token, $tstamp);

	// Get Events from API -- modify string to change limits and other filters
	$getEventsURL = $apiurl."/1.0/event/query?startDate=$startDate&endDate=$endDate&limit=999";

	$ch = curl_init($getEventsURL);
	curl_setopt_array($ch,array(
			CURLOPT_HTTPGET => TRUE,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_HTTPHEADER => array(
					'Authorization: '.$token,
					'Accept: application/json',
					'Content-Type: application/json'
			)
	));

	$response = curl_exec($ch);

	if($response === FALSE){ // check if the curl response is a failure
		echo date("Y-m-d H:i:s")." ".curl_error($ch)."\n";
		return false;
	}

	$evData = json_decode($response, true);
	if(is_null($evData)){ // check if the json data that was returned is null. 
		echo date("Y-m-d H:i:s")." Events response is null.\n";		
		return false;
	}
	
	return $evData;
}

/*
//	getEventsByCode($startDate, $query, &$token, &$tstamp)
//  Input: 	(String) $startDate, (String) $endDate - Start and End dates for search in 'd-m-Y' format
			(String) $query - Keyword search term
			(String) $token (can be null), (DateTime) $tstamp: timestamp of token (cannot be null)
//	Output: If $token is not valid, updates $token and $tstamp
//			Returns array of events in the date range specified, up to 999 events. Array format as follows:
			$evData['status'] - http status code
			$evData['data'][] - event data variables, such as count, offset
			$evData['data']['event_list'][] - numbered list of returned events, beginning with 0
			$evData['data']['event_list'][0][] - event information such as event_id, name, description, categories, etc.			
*/
function getEventsByCode($startDate, $endDate, $query, &$token, &$tstamp)
{
	// date format is dd-mm-YYYY
	include "apiconstants.php";
	
	// Check the life of the token and renew if necessary.
	$token = getToken($token, $tstamp);

	// Get Checkouts from API
	$getEventsURL = $apiurl."/1.0/event/query?startDate=$startDate&endDate=$endDate&includeCancelled=false&includePrivate=false&limit=200&offset=0&sortBy=startTime&sortOrder=asc&keyword=$query";

	$ch = curl_init($getEventsURL);
	curl_setopt_array($ch,array(
			CURLOPT_HTTPGET => TRUE,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_HTTPHEADER => array(
					'Authorization: '.$token,
					'Accept: application/json',
					'Content-Type: application/json'
			)
	));

	$response = curl_exec($ch);

	if($response === FALSE){ // check if the curl response is a failure
		echo date("Y-m-d H:i:s")." ".curl_error($ch)."\n";
		return false;
	}

	$evData = json_decode($response, true);
	if(is_null($evData)){ // check if the json data that was returned is null. 
		echo date("Y-m-d H:i:s")." Events response is null.\n";		
		return false;
	}
	
	if($evData["data"]["count"] == 0)
		return false; // no results -- could also return the array and check results in app
	
	return $evData;
}

/*
//	getEventDetails($eventID, &$token, &$tstamp)
//  Input: 	(Int) $eventID - Vega Program Event ID
			(String) $token (can be null), (DateTime) $tstamp: timestamp of token (cannot be null)
//	Output: If $token is not valid, updates $token and $tstamp
//			Returns array of event details. Array format as follows:
			$evData['status'] - http status code
			$evData['data'][] - event data variables - event_details, ticket_details, waiting_list_details
			$evData['data']['event_details'][] - event information such as event_id, name, description, categories, tickets used, tickets available etc	
			$evData['data']['ticket_details'][] - ticket information, patron name, barcode, email, note
			$evData['data']['waiting_list_details'][] - ticket information, patron name, barcode, email
*/
function getEventDetails($eventID, &$token, &$tstamp)
{
	// date format is dd-mm-YYYY
	include "apiconstants.php";

	// Check the life of the token and renew if necessary.
	$token = getToken($token, $tstamp);

	// Get Checkouts from API
	$getEventsURL = $apiurl."/1.0/event/$eventID";

	$ch = curl_init($getEventsURL);
	curl_setopt_array($ch,array(
			CURLOPT_HTTPGET => TRUE,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_HTTPHEADER => array(
					'Authorization: '.$token,
					'Accept: application/json',
					'Content-Type: application/json'
			)
	));

	$response = curl_exec($ch);

	if($response === FALSE){ // check if the curl response is a failure
		echo date("Y-m-d H:i:s")." ".curl_error($ch)."\n";
		return false;
	}

	$evData = json_decode($response, true);
	if(is_null($evData)){ // check if the json data that was returned is null. 
		echo date("Y-m-d H:i:s")." Events response is null.\n";		
		return false;
	}
	
	return $evData;
}

?>

