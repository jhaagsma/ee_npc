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


$username = $config['username'];	//<======== PUT IN YOUR USERNAME IN config.php
$ai_key = $config['ai_key'];		//<======== PUT IN YOUR AI API KEY IN config.php
$base_url = $config['base_url'];	//<======== PUT IN THE BASE URL IN config.php
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
	$market_info = get_market_info();	//get the Public Market info
	
	$owned_on_market_info = get_owned_on_market_info();	//find out what we have on the market
	//out_data($market_info);	//output the Public Market info
	//var_export($owned_on_market_info);
	
	while($c->turns > 0){
		//$result = buy_public($c,array('m_bu'=>100),array('m_bu'=>400));
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
			out("Less than 3 turns worth of food! (" . $c->foodnet .  "/turn) Buy food off Public if we can!");	//Text for screen
			$result = buy_public($c,array('m_bu' => -3*$c->foodnet),array('m_bu' => $pm_info->buy_price->m_bu));	//Buy 3 turns of food off the public at or below the PM price
			//out_data($result);
		}
		
		if($c->foodnet < 0 && $c->food < $c->foodnet*-3 && $c->money > 20*$c->foodnet*-3*$pm_info->buy_price->m_bu){ //losing food, less than 3 turns left, AND have the money to buy it
			out("Less than 3 turns worth of food! (" . $c->foodnet .  "/turn) We're rich, so buy food at any price!~");	//Text for screen
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
		
		if($c->foodnet > 0 && $c->foodnet > 3*$c->foodcon && $c->food > 30*$c->foodnet){
			out("Lots of food, let's sell some!");
			
		}
		//$main->turns = 0;				//use this to do one turn at a time
	}
	out("Done Playing Rainbow Turns for #$cnum!");	//Text for screen
}

function update_c(&$c,$result){
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
		$str = "Cashed " . actual_count($result->turns) . "turns";	//Text for screen
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
	$str = str_pad($str,26) . str_pad($explain,12) .  str_pad('$' . $c->money,16) . str_pad('($' . $netmoney . ')',12) . str_pad($c->food . ' Bu',10) . str_pad('(' . $netfood . ')',8); //Text for screen

	
	out(str_pad($c->turns,3) . ' Turns - ' . $str . $event);
}

function play_rainbow_turn(&$c){ //c as in country!
	usleep(500000);
	//out($main->turns . ' turns left');
	if($c->empty > $c->bpt && $c->money > $c->bpt*$c->build_cost){	//build a full BPT if we can afford it
		return build_something($c);
	}elseif($c->turns >= 4 && $c->empty >= 4 && $c->bpt < 80 && $c->money > 4*$c->build_cost && ($c->foodnet > 0 || $c->food > $c->foodnet*-5)) //otherwise... build 4CS if we can afford it and are below our target BPT (80)
		return build_cs(4); //build 4 CS
	elseif($c->tpt > $c->land*0.17 && rand(0,10) > 5) //tech per turn is greater than land*0.17 -- just kindof a rough "don't tech below this" rule...
		return tech_something($c);
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
		if(rand(0,100) > 50 && $c->income > $c->build_cost*$c->bpt/$c->explore_rate){
			return build(array('lab' => $c->bpt));
		}
		else{
			$res = round($c->bpt/2.12);
			$ent = $c->bpt - $res;
			return build(array('ent' => $ent,'res' => $res));
		}
	}
	else{ //build indies or labs
		if(($c->tpt < $c->land && rand(0,100) > 10) || rand(0,100) > 40)
			return build(array('lab' => $c->bpt));
		else
			return build(array('indy' => $c->bpt));
	}
}

function tech_something(&$c){
	//lets do random weighting... to some degree
	$mil	= rand(0,25);
	$med	= rand(0,5);
	$bus	= rand(10,100);
	$res	= rand(10,100);
	$agri	= rand(10,100);
	$war	= rand(0,10);
	$ms		= rand(0,20);
	$weap	= rand(0,20);
	$indy	= rand(5,40);
	$spy	= rand(0,10);
	$sdi	= rand(2,15);	
	$tot	= $mil + $med + $bus + $res + $agri + $war + $ms + $weap + $indy + $spy + $sdi;
	
	$left = $c->tpt;
	$left -= $mil = min($left,floor($c->tpt*($mil/$tot)));
	$left -= $med = min($left,floor($c->tpt*($med/$tot)));
	$left -= $bus = min($left,floor($c->tpt*($bus/$tot)));
	$left -= $res = min($left,floor($c->tpt*($res/$tot)));
	$left -= $agri = min($left,floor($c->tpt*($agri/$tot)));
	$left -= $war = min($left,floor($c->tpt*($war/$tot)));
	$left -= $ms = min($left,floor($c->tpt*($ms/$tot)));
	$left -= $weap = min($left,floor($c->tpt*($weap/$tot)));
	$left -= $indy = min($left,floor($c->tpt*($indy/$tot)));
	$left -= $spy = min($left,floor($c->tpt*($spy/$tot)));
	$left -= $sdi = max($left,min($left,floor($c->tpt*($spy/$tot))));
	if($left != 0)
		die("What the hell?");
	
	return tech(array('mil'=>$mil,'med'=>$med,'bus'=>$bus,'res'=>$res,'agri'=>$agri,'war'=>$war,'ms'=>$ms,'weap'=>$weap,'indy'=>$indy,'spy'=>$spy,'sdi'=>$sdi));
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
	return ee('onmarket');	//get and return the GOODS OWNED ON PUBLIC MARKET information
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



function buy_public(&$c,$quantity=array(),$price=array()){
	$result = ee('buy',array('quantity' => $quantity, 'price' => $price));
	
	$str = 'Bought ';
	$tcost = 0;
	foreach($result->bought as $type => $details){
		if($type == 'm_bu')
			$type = 'food';
		elseif($type == 'm_oil')
			$type = 'oil';
		
		$c->$type += $details->quantity;
		$c->money -= $details->cost;
		$tcost += $details->cost;
		$str .= $details->quantity . ' ' . $type . ', ';
	}
	if($str == 'Bought ')
		$str .= 'nothing ';
	
	$str .= 'for $' .$tcost;
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



