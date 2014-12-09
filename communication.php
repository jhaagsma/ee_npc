<?php

include_once('colors.php');
$colors = new Colors();
/*
This file holds the communications with the EE server, so that we can keep
only the real bot logic in the ee_npc file...
*/

///DATA HANDLING AND OUTPUT

function ee($function,$parameter_array = array()){
	global $base_url, $username, $ai_key, $serv, $cnum;
	
	$init = $parameter_array;
	$parameter_array['ai_key'] = $ai_key;
	$parameter_array['username'] = $username;
	$parameter_array['server'] = $serv;
	if($cnum)
		$parameter_array['cnum'] = $cnum;
	
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL,$base_url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS,"api_function=" . $function . "&api_payload=" . json_encode($parameter_array));

	// receive server response ...
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$server_output = curl_exec($ch);

	curl_close($ch);

	$return = handle_output($server_output,$function);
	if($return === false)
		out_data($init);
	return $return;
}

function handle_output($server_output,$function){
	$parts = explode(':',$server_output,2);
	//This will simply kill the script if EE returns with an error
	//This is to avoid foulups, and to simplify the code checking above
	if(expected_result($function) && $parts[0] != expected_result($function)){
		out("\n\nUnexpected Result for '$function': " . $parts[0] . "\n\n");
		return false;
	}
	elseif(!expected_result($function)){
		out($parts[0]);
		if(!isset($parts[1]))
			return;
	}
	
	$output = json_decode($parts[1]);
	return $output;
}

function expected_result($input){
	global $last_function;
	$last_function = $input;
	//This is simply a list of expected return values for each function
	//This allows us to quickly verify if an error occurred
	switch($input){
		case 'server':		return 'SERVER_INFO';
		case 'create':		return 'CNUM';
		case 'advisor':		return 'ADVISOR';
		case 'main':		return 'MAIN';
		case 'build':		return 'BUILD';
		case 'explore':		return 'EXPLORE';
		case 'cash':		return 'CASH';
		case 'pm_info':		return 'PM_INFO';
		case 'pm':			return 'PM';
		case 'tech':		return 'TECH';
		case 'market':		return 'MARKET';
		case 'onmarket':	return 'ONMARKET';
				
		case 'buy':			return 'BUY';
		case 'sell':		return 'SELL';
	}
}

function out($str){
	//This just formats output strings nicely
	echo "\n[" . date("H:i:s") . "] $str";
}

function out_data($data){
	//This function is to output and format some data nicely
	out("DATA:\n" . str_replace(",\n", "\n",var_export($data,true)));
}

function actual_count($data){ //do not ask me why, but count() doesn't work on $result->turns
	$i = 0;
	foreach($data as $stuff)
		$i++;
	
	return $i;
}

function done($str = null){
	if($str)
		out($str);
	out("Exiting\n\n");
	exit;
}
