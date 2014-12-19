#!/usr/bin/php
<?php

include_once('communication.php');
out('STARTING UP BOT');// out() is defined below
date_default_timezone_set('GMT'); //SET THE TIMEZONE FIRST
error_reporting(E_ALL); //SET THE ERROR REPORTING TO REPORT EVERYTHING
out('Error Reporting and Timezone Set');
include_once('config.php');
if(!isset($config))
	die("Config not included successfully! Do you have config.php set up properly?");

include_once('country_functions.php');

include_once('rainbow_strat.php');
include_once('farmer_strat.php');
include_once('techer_strat.php');
include_once('casher_strat.php');
include_once('indy_strat.php');
include_once('oiler_strat.php');

define("RAINBOW",$colors->getColoredString("Rainbow","purple"));
define("FARMER",$colors->getColoredString("Farmer","cyan"));
define("TECHER",$colors->getColoredString("Techer","brown"));
define("CASHER",$colors->getColoredString("Casher","green"));
define("INDY",$colors->getColoredString("Indy","yellow"));
define("OILER",$colors->getColoredString("Oiler","red"));

$username = $config['username'];	//<======== PUT IN YOUR USERNAME IN config.php
$ai_key = $config['ai_key'];		//<======== PUT IN YOUR AI API KEY IN config.php
$base_url = $config['base_url'];	//<======== PUT IN THE BASE URL IN config.php
$serv = isset($config['server']) ? $config['server'] : 'ai';
$cnum = null;
$last_function = null;
$turnsleep = isset($config['turnsleep']) ? $config['turnsleep'] : 500000;
$mktinfo = null; //so we don't have to get it mkt data over and over again
$api_calls = 0;

out('Current Unix Time: ' . time());
out('Entering Infinite Loop');
$loopcount = 0;
while(1){
	$server = ee('server');
	while($server->alive_count < $server->countries_allowed){
		out("Less countries than allowed! (" . $server->alive_count . '/' . $server->countries_allowed . ')');
		include_once('name_generator.php');
		$send_data = array('cname' => rand_name());
		out("Making new country named '" . $send_data['cname'] . "'");
		$cnum = ee('create',$send_data);
		out($send_data['cname'] . ' (#' . $cnum .') created!');
		$server = ee('server');
		if($server->reset_start > time()){
			$timeleft = $server->reset_start - time();
			$countriesleft = $server->countries_allowed - $server->alive_count;
			$sleeptime = $timeleft/$countriesleft;
			out("Sleep for $sleeptime to spread countries out");
			sleep($sleeptime);
		}
	}
	
	
	if($server->reset_start > time()){
		out("Reset has not started!");			//done() is defined below
		sleep(max(300,time()-$server->reset_start));		//sleep until the reset starts
		continue;								//return to the beginning of the loop
	}
	elseif($server->reset_end < time()){
		out("Reset is over!");
		sleep(300);								//wait 5 mins, see if new one is created
		continue;								//restart the loop
	}
	
	server_start_end_notification($server);
	
	$countries = $server->cnum_list->alive;

	foreach($countries as $cnum){
		$mktinfo = null;
		if($cnum%5 == 1)
			play_farmer_strat($server,$cnum);
		elseif($cnum%5 == 2)
			play_techer_strat($server,$cnum);
		elseif($cnum%5 == 3)
			play_casher_strat($server,$cnum);
		elseif($cnum%5 == 4)
			play_indy_strat($server,$cnum);
		else
			play_rainbow_strat($server,$cnum);
	}
	
	$until_end = 50;
	if($server->reset_end - $server->turn_rate * $until_end - time() < 0){
		foreach($countries as $cnum){
			$mktinfo = null;
			destock($server,$cnum);
		}
		out("Sleep until end");
		sleep(($until_end + 1)*$server->turn_rate);	//don't let them fluff things up, sleep through end of reset
	}
	$cnum = null;
	$loopcount++;
	$sleepturns = 25;
	$sleep = min($sleepturns*$server->turn_rate,max(0,$server->reset_end - 60 - time()));
	$sleepturns = ($sleep != $sleepturns*$server->turn_rate ? floor($sleep/$server->turn_rate) : $sleepturns);
	out("Played 'Day' $loopcount; Sleeping for " . $sleep . " seconds ($sleepturns Turns)");
	server_start_end_notification($server);
	sleep($sleep); //sleep for 4 turns
}
done(); //done() is defined below

function server_start_end_notification($server){
	out("Server started " . round((time()-$server->reset_start)/3600,1) . ' hours ago and ends in ' . round(($server->reset_end-time())/3600,1) . ' hours');
}


//COUNTRY PLAYING STUFF
function onmarket($good = 'food'){
	global $mktinfo;
	if(!$mktinfo)
		$mktinfo = get_owned_on_market_info();	//find out what we have on the market
		
	//out_data($mktinfo);
	//exit;
	$total = 0;
	foreach($mktinfo as $key => $goods){
		//out_data($goods);
		if($good != null && $goods->type == $good){
			$total += $goods->quantity;		
		}
		elseif($good == null)
			$total += $goods->quantity;
	}
	return $total;
}

function onmarket_value($good = null){
	global $mktinfo;
	if(!$mktinfo)
		$mktinfo = get_owned_on_market_info();	//find out what we have on the market
		
	//out_data($mktinfo);
	//exit;
	$value = 0;
	foreach($mktinfo as $key => $goods){
		//out_data($goods);
		if($good != null && $goods->type == $good){
			$value += $goods->quantity*$goods->price;		
		}
		elseif($good == null)
			$value += $goods->quantity;
	}
	return $value;
}

function totaltech($c){
	return $c->t_mil + $c->t_med + $c->t_bus + $c->t_res + $c->t_agri + $c->t_war + $c->t_ms + $c->t_weap + $c->t_indy + $c->t_spy + $c->t_sdi;
}

function total_military($c){
	return $c->m_spy+$c->m_tr+$c->m_j+$c->m_tu+$c->m_ta;	//total_military
}

function total_cansell_tech($c){
	$cansell = 0;
	global $techlist;
	foreach($techlist as $tech)
		$cansell += can_sell_tech($c,$tech);
	
	//out("CANSELL TECH: $cansell");
	return $cansell;
}

function total_cansell_military($c){
	$cansell = 0;
	global $military_list;
	foreach($military_list as $mil)
		$cansell += can_sell_mil($c,$mil);
	
	//out("CANSELL TECH: $cansell");
	return $cansell;
}


function can_sell_tech(&$c, $tech = 't_bus'){
	$onmarket = onmarket($tech);
	$tot = $c->$tech + $onmarket;
	$sell = floor($tot/4) - $onmarket;

	return $sell > 10 ? $sell : 0;
}

function can_sell_mil(&$c, $mil = 'm_tr'){
	$onmarket = onmarket($mil);
	$tot = $c->$mil + $onmarket;
	$sell = floor($tot/4) - $onmarket;
	
	return $sell > 5000 ? $sell : 0;
}


//Interaction with API
function update_c(&$c,$result){
	if(!isset($result->turns) || !$result->turns)
		return;
	global $last_function;
	//out_data($result);				//output data for testing
	$explain = null;					//Text formatting
	if(isset($result->built)){
		$str = 'Built ';				//Text for screen
		$first = true;					//Text formatting
		$bpt = $tpt = false;
		foreach($result->built as $type  =>  $num){	//for each type of building that we built....
			if(!$first)					//Text formatting
				$str .= ' and ';		//Text formatting
			$first = false;				//Text formatting
			$build = 'b_' . $type;		//have to convert to the advisor output, for now
			$c->$build += $num;			//add buildings to keep track
			$c->empty -= $num;			//subtract buildings from empty, to keep track
			$str .= $num . ' ' . $type;	//Text for screen
			if($type == 'cs' && $num > 0)
				$bpt = true;
			elseif($type == 'lab' && $num > 0)
				$tpt = true;
		}
		if($bpt)
			$explain = '(' . $result->bpt . ' bpt)';	//Text for screen
		if($tpt)
			$explain = '(' . $result->tpt . ' tpt)';	//Text for screen
			
		$c->bpt = $result->bpt;			//update BPT - added this to the API so that we don't have to calculate it
		$c->tpt = $result->tpt;			//update TPT - added this to the API so that we don't have to calculate it
		$c->money -= $result->cost;
	}
	elseif(isset($result->new_land)){
		$c->empty += $result->new_land;				//update empty land
		$c->land += $result->new_land;				//update land
		$c->build_cost = $result->build_cost;		//update Build Cost
		$c->explore_rate = $result->explore_rate;	//update explore rate
		$c->tpt = $result->tpt;			//update TPT - added this to the API so that we don't have to calculate it
		$str = "Explored " . $result->new_land . " Acres";	//Text for screen
		$explain = '(' . $c->land . ' A)';			//Text for screen
	}
	elseif(isset($result->teched)){
		$str = 'Tech: ';
		$tot = 0;
		foreach($result->teched as $type  =>  $num){	//for each type of tech that we teched....
			$build = 't_' . $type;		//have to convert to the advisor output, for now
			$c->$build += $num;			//add buildings to keep track
			$tot += $num;	//Text for screen
		}
		$c->tpt = $result->tpt;			//update TPT - added this to the API so that we don't have to calculate it
		$str .=  $tot . ' ' . actual_count($result->turns) . ' turns';
		$explain = '(' . $c->tpt . ' tpt)';	//Text for screen
	}
	elseif($last_function == 'cash'){
		$str = "Cashed " . actual_count($result->turns) . " turns";	//Text for screen
	}
	elseif(isset($result->sell)){
		$str = "Put goods on market";
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
	$str = str_pad($str,26) 
			. str_pad($explain,12) 
			. str_pad('$' . $c->money,16, ' ', STR_PAD_LEFT) 
			. str_pad('($' . $netmoney . ')',12, ' ', STR_PAD_LEFT) 
			. str_pad($c->food . ' Bu',12, ' ', STR_PAD_LEFT) 
			. str_pad('(' . $netfood . ')',10, ' ', STR_PAD_LEFT); //Text for screen

	global $api_calls;
	out(str_pad($c->turns,3) . ' Turns - ' . $str . str_pad($event,5) . ' API: ' . $api_calls);
	$api_calls = 0;
}

function event_text($event){
	switch($event){
		case 'earthquake':	return '--EQ--';
		case 'oilboom':		return '+OIL';
		case 'oilfire':		return '-oil';
		case 'foodboom':	return '+FOOD';
		case 'foodbad':		return '-food';
		case 'indyboom':	return '+INDY';
		case 'indybad':		return '-indy';
		case 'pciboom':		return '+PCI';
		case 'pcibad':		return '-pci';
		default:			return null;
	}
}

function build_cs($turns=1){							//default is 1 CS if not provided
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

function tech($tech = array()){					//default is an empty array
	return ee('tech',array('tech' => $tech));	//research a particular set of techs
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

function get_market_info(){
	return ee('market');	//get and return the PUBLIC MARKET information
}

function get_owned_on_market_info(){
	$goods = ee('onmarket');	//get and return the GOODS OWNED ON PUBLIC MARKET information
	return $goods->goods;
}

function change_govt(&$c,$govt){
	$result = ee('govt',array('govt' => $govt));
	if(isset($result->govt)){
		out("Govt switched to {$result->govt}!");
		$c = get_advisor();	//UPDATE EVERYTHING
	}
	return $result;
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

function buy_public(&$c,$quantity=array(),$price=array()){
	global $techlist;
	$result = ee('buy',array('quantity' => $quantity, 'price' => $price));
	$str = 'Bought ';
	$tcost = 0;
	foreach($result->bought as $type => $details){
		$ttype = 't_' . $type;
		if($type == 'm_bu')
			$type = 'food';
		elseif($type == 'm_oil')
			$type = 'oil';
		elseif(in_array($ttype,$techlist))
			$type = $ttype;
		
		$c->$type += $details->quantity;
		$c->money -= $details->cost;
		$tcost += $details->cost;
		$str .= $details->quantity . ' ' . $type . ', ';
	}
	$nothing = false;
	if($str == 'Bought '){
		$str .= 'nothing ';
		$nothing = true;
		
	}
	if($nothing){
		$what = null;
		$cost = 0;
		foreach($quantity as $key => $q){
			$what .= $key . $q . '@' . $price[$key] . ', ';
			$cost += round($q*$price[$key]*(100+$c->g_tax)/100);
		}
		out("Tried: " . $what);
		out("Money: " . $c->money . " Cost: " . $cost);
		sleep(1);
	}
	
	$str .= 'for $' .$tcost;
	out($str);
	return $result;
}

function sell_public(&$c,$quantity=array(),$price=array(),$tonm=array()){
	//out_data($c);
	
	/*$str = 'Try selling ';
	foreach($quantity as $type => $q){
		if($q == 0)
			continue;
		if($type == 'm_bu')
			$t2 = 'food';
		elseif($type == 'm_oil')
			$t2 = 'oil';
		else
			$t2 = $type;
		$str .= $q . ' ' . $t2 . '@' . $price[$type] . ', ';
	}
	$str .= 'on market.';
	out($str);*/
	if(array_sum($quantity) == 0){
		out("Trying to sell nothing?");
		return;
	}
	$result = ee('sell',array('quantity' => $quantity, 'price' => $price)); //ignore tonm for now, it's optional
	//out_data($result);
	if(isset($result->error) && $result->error){
		out('ERROR: ' . $result->error);
		sleep(1);
		return;
	}
	global $techlist;
	$str = 'Put ';
	if(isset($result->sell)){
		foreach($result->sell as $type => $details){
			$bits = explode('_',$type);
			//$omtype = 'om_' . $bits[1];
			$ttype = 't_' . $type;
			if($type == 'm_bu')
				$type = 'food';
			elseif($type == 'm_oil')
				$type = 'oil';
			elseif(in_array($ttype,$techlist))
				$type = $ttype;
			
			//$c->$omtype += $details->quantity;
			$c->$type -= $details->quantity;
			$str .= $details->quantity . ' ' . $type . ' @ ' . $details->price . ', ';
		}
	}
	if($str == 'Put ')
		$str .= 'nothing on market.';
	
	out($str);
	//sleep(1);
	return $result;
}


function buy_tech(&$c, $tech = 't_bus', $spend = 0, $maxprice = 9999){
	$market_info = get_market_info();	//get the Public Market info
	$tech = substr($tech,2);
	$diff = $c->money - $spend;
	if($market_info->buy_price->$tech != null && $market_info->available->$tech > 0){
		while($market_info->buy_price->$tech != null && $market_info->available->$tech > 0 && $market_info->buy_price->$tech <= $maxprice && $spend > 0){
			$price = $market_info->buy_price->$tech;
			$tobuy = min(floor($spend / ($price*(100 + $c->g_tax)/100)),$market_info->available->$tech);
			if($tobuy == 0)
				return;
			//out($tech . $tobuy . "@$" . $price);
			$result = buy_public($c,array($tech => $tobuy),array($tech => $price));	//Buy troops!
			$spend = $c->money - $diff;
			$market_info = get_market_info();
		}
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


function purebell($min,$max,$std_deviation,$step=1) { //box-muller-method
  $rand1 = (float)mt_rand()/(float)mt_getrandmax();
  $rand2 = (float)mt_rand()/(float)mt_getrandmax();
  $gaussian_number = sqrt(-2 * log($rand1)) * cos(2 * pi() * $rand2);
  $mean = ($max + $min) / 2;
  $random_number = ($gaussian_number * $std_deviation) + $mean;
  $random_number = round($random_number / $step) * $step;
  if($random_number < $min || $random_number > $max) {
    $random_number = purebell($min, $max,$std_deviation);
  }
  return $random_number;
}
