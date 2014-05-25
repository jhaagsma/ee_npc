#!/usr/bin/php
<?php

out('STARTING UP BOT');// out() is defined below
date_default_timezone_set('GMT'); //SET THE TIMEZONE FIRST
error_reporting(E_ALL); //SET THE ERROR REPORTING TO REPORT EVERYTHING
out('Error Reporting and Timezone Set');

$username = 'qzjul';     //<======== PUT IN YOUR USERNAME HERE
$ai_key = '7de188b2a96271fb20acc1cee66cd3f7';   //<======== PUT IN YOUR API KEY HERE
$base_url = 'http://qz.earthempires.com/api';
$serv = 'ai';
$cnum = null;
$last_function = null;

out('Current Unix Time: ' . time());
out('Entering Infinite Loop');
$loopcount = 0;
while(1){
	$server = ee('server');
	if($server->reset_start > time()){
		out("Reset has not started!");			//done() is defined below
		sleep(time()-$server->reset_start);		//sleep until the reset starts
		continue;								//return to the beginning of the loop
	}
	elseif($server->reset_end < time()){
		out("Reset is over!");
		sleep(300);								//wait 5 mins, see if new one is created
		continue;								//restart the loop
	}

	while($server->alive_count < $server->countries_allowed){
		out("Less countries than allowed! (" . $server->alive_count . '/' . $server->countries_allowed . ')');
		$send_data = array('cname' => rand_country_name());
		out("Making new country named '" . $send_data['cname'] . "'");
		$cnum = ee('create',$send_data);
		out($send_data['cname'] . ' (#' . $cnum .') created!');
		$server = ee('server');
	}
	
	$countries = $server->cnum_list->alive;

	foreach($countries as $cnum){
		play_rainbow_strat($server,$cnum);
	}
	$cnum = null;
	$loopcount++;
	$sleepturns = 10;
	out("Played 'Day' $loopcount; Sleeping for " . $sleepturns*$server->turn_rate . " seconds ($sleepturns Turns)");
	sleep($sleepturns*$server->turn_rate); //sleep for 4 turns
}
done(); //done() is defined below


//COUNTRY PLAYING STUFF

function play_rainbow_strat($server){
	global $cnum;
	out("Playing rainbow turns for #$cnum");
	$main = get_main();	//get the basic stats
	//out_data($main);			//output the main data
	$c = get_advisor();	//c as in country! (get the advisor)
	out($c->turns . ' turns left');
	//out_data($c);				//ouput the advisor data
	$pm_info = get_pm_info();	//get the PM info
	//out_data($pm_info);		//output the PM info
	
	while($c->turns > 0){
		$result = play_rainbow_turn($c);
		if($result === false){	//UNEXPECTED RETURN VALUE
			$c = get_advisor();	//UPDATE EVERYTHING
			continue;
		}
		update_c($c,$result);
		if(!$c->turns%5){					//Grab new copy every 5 turns
			$main = get_main();		//Grab a fresh copy of the main stats //we probably don't need to do this *EVERY* turn
			$c->money = $main->money;		//might as well use the newest numbers?
			$c->food = $main->food;			//might as well use the newest numbers?
			$c->networth = $main->networth; //might as well use the newest numbers?
			$c->oil = $main->oil;			//might as well use the newest numbers?
			$c->pop = $main->pop;			//might as well use the newest numbers?
			$c->turns = $main->turns;		//This is the only one we really *HAVE* to check for
		}
		
		if($c->foodnet < 0 && $c->food < $c->foodnet*-3 && $c->money > 20*$c->foodnet*-3*$pm_info->buy_price->m_bu){ //losing food, less than 3 turns left, AND have the money to buy it
			out("Less than 3 turns worth of food! We're rich, so buy food at any price!~");	//Text for screen
			$result = buy_on_pm($c,array('m_bu' => -3*$c->foodnet));	//Buy 3 turns of food!
			//out_data($result);
		}
		elseif($c->foodnet < 0 && $c->food < $c->foodnet*-3){
			out("We're too poor to buy food! Sell 1/4 of our military");	//Text for screen
			sell_all_military($c,1/4);	//sell 1/4 of our military
		}
		elseif($c->income < 0){ //sell 1/4 of all military on PM
			out("Losing money! Sell 1/4 of our military!");	//Text for screen
			sell_all_military($c,1/4);	//sell 1/4 of our military
		}
		//$main->turns = 0;				//use this to do one turn at a time
	}
	out("Done Playing Rainbow Turns for #$cnum!");	//Text for screen
}

function update_c(&$c,$result){
	global $last_function;
	//out_data($result);				//output data for testing
	if(isset($result->built)){
		$str = 'Built ';				//Text for screen
		$first = true;					//Text formatting
		foreach($result->built as $type  =>  $num){	//for each type of building that we built....
			if(!$first)					//Text formatting
				$str .= ' and ';		//Text formatting
			$first = false;				//Text formatting
			$build = 'b_' . $type;		//have to convert to the advisor output, for now
			$c->$build += $num;			//add buildings to keep track
			$c->empty -= $num;			//subtract buildings from empty, to keep track
			$str .= $num . ' ' . $type;	//Text for screen
		}
		$c->bpt = $result->bpt;			//update BPT - added this to the API so that we don't have to calculate it
		$c->money -= $result->cost;
	}
	elseif(isset($result->new_land)){
		$c->empty += $result->new_land;				//update empty land
		$c->land += $result->new_land;				//update land
		$c->build_cost = $result->build_cost;		//update Build Cost
		$c->explore_rate = $result->explore_rate;	//update explore rate
		$str = "Explored " . $result->new_land . " Acres";	//Text for screen
	}
	elseif($last_function == 'cash'){
		$str = "Cashed " . count($result->turns) . "turns";	//Text for screen
	}
	
	$event = null; //Text for screen	
	$netmoney = $netfood = 0;
	foreach($result->turns as $num  =>  $turn){		//update stuff based on what happened this turn
		$netfood 	+= $c->foodnet	= floor(isset($turn->foodproduced)	? $turn->foodproduced : 0)	- (isset($turn->foodconsumed)	? $turn->foodconsumed : 0);
		$netmoney	+= $c->income	= floor(isset($turn->taxrevenue)	? $turn->taxrevenue : 0)	- (isset($turn->expenses)		? $turn->expenses : 0);
		$c->pop		+= floor(isset($turn->popgrowth)		? $turn->popgrowth : 0);		//the turn doesn't *always* return these things, so have to check if they exist, and add 0 if they don't
		$c->m_tr	+= floor(isset($turn->troopsproduced)	? $turn->troopsproduced : 0);	//the turn doesn't *always* return these things, so have to check if they exist, and add 0 if they don't
		$c->m_j		+= floor(isset($turn->jetsproduced)		? $turn->jetsproduced : 0);		//the turn doesn't *always* return these things, so have to check if they exist, and add 0 if they don't
		$c->m_tu	+= floor(isset($turn->turretsproduced)	? $turn->turretsproduced : 0);	//the turn doesn't *always* return these things, so have to check if they exist, and add 0 if they don't
		$c->m_ta	+= floor(isset($turn->tanksproduced)	? $turn->tanksproduced : 0);	//the turn doesn't *always* return these things, so have to check if they exist, and add 0 if they don't
		$c->m_spy	+= floor(isset($turn->spiesproduced)	? $turn->spiesproduced : 0);	//the turn doesn't *always* return these things, so have to check if they exist, and add 0 if they don't
		$c->turns--;
		
		//out_data($turn);

		if(isset($turn->event)){

			if($turn->event == 'earthquake'){	//if an earthquake happens...
				out("Earthquake destroyed {$turn->earthquake} Buildings! Update Advisor");							//Text for screen
				$c = get_advisor();											//update the advisor, because we no longer no what infromation is valid
			}
			elseif($turn->event == 'pciboom')		//in the event of a pci boom, recalculate income so we don't react based on an event
				$c->income = floor(isset($turn->taxrevenue)	? $turn->taxrevenue/3 : 0)	- (isset($turn->expenses)		? $turn->expenses : 0);
			elseif($turn->event == 'pcibad')		//in the event of a pci bad, recalculate income so we don't react based on an event
				$c->income = floor(isset($turn->taxrevenue)	? $turn->taxrevenue*3 : 0)	- (isset($turn->expenses)		? $turn->expenses : 0);
			elseif($turn->event == 'foodboom')		//in the event of a food boom, recalculate netfood so we don't react based on an event
				$c->foodnet = floor(isset($turn->foodproduced)	? $turn->foodproduced/3 : 0)	- (isset($turn->foodconsumed)	? $turn->foodconsumed : 0);
			elseif($turn->event == 'foodbad')		//in the event of a food boom, recalculate netfood so we don't react based on an event
				$c->foodnet = floor(isset($turn->foodproduced)	? $turn->foodproduced*3 : 0)	- (isset($turn->foodconsumed)	? $turn->foodconsumed : 0);
			$event .= event_text($turn->event) . ' ';	//Text for screen
		}
		if(isset($turn->cmproduced))	//a CM was produced
			$event .= 'CM ';			//Text for screen
		if(isset($turn->nmproduced))	//an NM was produced
			$event .= 'NM ';			//Text for screen
		if(isset($turn->emproduced))	//an EM was produced
			$event .= 'EM ';			//Text for screen
	}
	$c->money += $netmoney;
	$c->food += $netfood;
	
	$netfood = ($netfood > 0 ? '+' . $netfood : $netfood);			//Text formatting (adding a + if it is positive; - will be there if it's negative already)
	$netmoney = ($netmoney > 0 ? '+' . $netmoney : $netmoney);		//Text formatting (adding a + if it is positive; - will be there if it's negative already)
	$str = str_pad($str,26) . str_pad('$' . $c->money,16) . str_pad('($' . $netmoney . ')',12) . str_pad($c->food . ' Bu',10) . str_pad('(' . $netfood . ')',8); //Text for screen

	
	out(str_pad($c->turns,3) . ' Turns - ' . $str . $event);
}

function play_rainbow_turn(&$c){ //c as in country!
	usleep(500000);
	//out($main->turns . ' turns left');
	if($c->empty > $c->bpt && $c->money > $c->bpt*$c->build_cost){	//build a full BPT if we can afford it
		return build_something($c);
	}elseif($c->turns >= 4 && $c->empty >= 4 && $c->bpt < 80 && $c->money > 4*$c->build_cost) //otherwise... build 4CS if we can afford it and are below our target BPT (80)
		return build_cs(4); //build 4 CS
	elseif($c->empty < $c->land/2)	//otherwise... explore if we can
		return explore($c);
	elseif($c->empty && $c->bpt < 80 && $c->money > $c->build_cost) //otherwise... build one CS if we can afford it and are below our target BPT (80)
		return build_cs(); //build 1 CS
	else  //otherwise...  cash
		return cash($c);
}

function build_something(&$c){
	if($c->foodnet < 0 && -1*$c->food/$c->foodnet < 50){ //build farms if we have less than 50 turns of food on hand
		return build(array('farm' => $c->bpt));
	}
	elseif($c->income < max(100000,2*$c->build_cost*$c->bpt/$c->explore_rate)){ //build ent/res if we're not making more than enough to keep building continually at least $100k
		$res = round($c->bpt/2.12);
		$ent = $c->bpt - $res;
		return build(array('ent' => $ent,'res' => $res));
	}
	else{ //build indies
		return build(array('indy' => $c->bpt));
	}
}

function build_cs($turns=1){
	return build(array('cs' => $turns));
}

function build($buildings = array()){					//default is an empty array
	return ee('build',array('build' => $buildings));	//build a particular set of buildings
}

function cash(&$c,$turns = 1){							//this means 1 is the default number of turns if not provided
	return ee('cash',array('turns' => $turns));			//cash a certain number of turns
}


function explore(&$c,$turns = 1){						//this means 1 is the default number of turns if not provided
	return ee('explore',array('turns' => $turns));		//cash a certain number of turns
}

function get_main(){
	return ee('main');		//get and return the MAIN information
}

function get_advisor(){
	return ee('advisor');	//get and return the ADVISOR information
}

function get_pm_info(){
	return ee('pm_info');	//get and return the PRIVATE MARKET information
}


function sell_all_military(&$c,$fraction = 1){
	$fraction = max(0,min(1,$fraction));
	$sell_units = array(
		'm_spy'	=>	floor($c->m_spy*$fraction),		//$fraction of spies
		'm_tr'	=>	floor($c->m_tr*$fraction),		//$fraction of troops
		'm_j'	=>	floor($c->m_j*$fraction),		//$fraction of jets
		'm_tu'	=>	floor($c->m_tu*$fraction),		//$fraction of turrets
		'm_ta'	=>	floor($c->m_ta*$fraction)		//$fraction of tanks
	);
	return sell_on_pm($c,$sell_units);	//Sell 'em
}

function buy_on_pm(&$c,$units = array()){
	$result = ee('pm',array('buy' => $units));
	$c->money -= $result->cost;
	$str = 'Bought ';
	foreach($result->goods as $type => $amount){
		if($type == 'm_bu')
			$type = 'food';
		elseif($type == 'm_oil')
			$type = 'oil';
					
		$c->$type += $amount;
		$str .= $amount . ' ' . $type . ', ';
	}
	$str .= 'for $' . $result->cost;
	out($str);
	return $result;	
}


function sell_on_pm(&$c,$units = array()){
	$result =  ee('pm',array('sell' => $units));
	$c->money += $result->money;
	$str = 'Sold ';
	foreach($result->goods as $type => $amount){
		if($type == 'm_bu')
			$type = 'food';
		elseif($type == 'm_oil')
			$type = 'oil';
		
		$c->$type -= $amount;
		$str .= $amount . ' ' . $type . ', ';
	}
	$str .= 'for $' . $result->money;
	out($str);
	return $result;	
}

function event_text($event){
	switch($event){
		case 'earthquake':	return 'earthquake';
		case 'oilboom':		return '+OIL';
		case 'oilfire':		return '-oil';
		case 'foodboom':	return '+FOOD';
		case 'foodbad':		return '-food';
		case 'indyboom':	return '+INDY';
		case 'indybad':		return '-indy';
		case 'pciboom':		return '+PCI';
		case 'pcibad':		return '-pci';
	}
}


function rand_country_name(){
	global $username;
	$name = substr($username,0,2) . ' '; //name them by the first 2 chars of a username; should still be fairly unique on this server
	$last = chr(32); //we just added a space
	$length = rand(5,24);
	for($i = 0; $i < $length; $i++){
		$rand = rand(0,10);
		if($rand == 0 && $last != chr(32))
			$name .= $last = chr(32); //space
		elseif($rand%2)
			$name .= $last = chr(rand(65,90)); //A-Z
		else
			$name .= $last =  chr(rand(97,122)); //a-z
	}
	$name = trim($name);
	return $name;
}


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
		case 'server':	return 'SERVER_INFO';
		case 'create':	return 'CNUM';
		case 'advisor':	return 'ADVISOR';
		case 'main':	return 'MAIN';
		case 'build':	return 'BUILD';
		case 'explore':	return 'EXPLORE';
		case 'cash':	return 'CASH';
		case 'pm_info':	return 'PM_INFO';
		case 'pm':		return 'PM';
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

function done($str = null){
	if($str)
		out($str);
	out("Exiting\n\n");
	exit;
}
