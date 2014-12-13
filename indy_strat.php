<?php
$military_list = array('m_tr','m_j','m_tu','m_ta');

function play_indy_strat($server){
	global $cnum;
	out("Playing " . INDY . " Turns for #$cnum");
	$main = get_main();	//get the basic stats
	//out_data($main);			//output the main data
	$c = get_advisor();	//c as in country! (get the advisor)
	//out_data($c);				//ouput the advisor data
	out($c->turns . ' turns left');
	$pm_info = get_pm_info();	//get the PM info
	//out_data($pm_info);		//output the PM info
	$market_info = get_market_info();	//get the Public Market info
	//out_data($market_info);		//output the PM info
	
	$owned_on_market_info = get_owned_on_market_info();	//find out what we have on the market
	//out_data($owned_on_market_info);	//output the Owned on Public Market info
	
	while($c->turns > 0){
		//$result = buy_public($c,array('m_bu'=>100),array('m_bu'=>400));
		$result = play_indy_turn($c);
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
		
		food_management($c);
		
		if($c->money > max($c->bpt,30)*$c->build_cost*10){ //buy_tech
			//out("Try to buy tech?");
			$spend = $c->money - $c->bpt*$c->build_cost*10;
			if($c->pt_agri < 140)
				buy_tech($c,'t_indy',$spend*1/2);
			if($c->pt_bus < 140)
				buy_tech($c,'t_bus',$spend*1/4);
			if($c->pt_res < 140)
				buy_tech($c,'t_res',$spend*1/4);
			
			$spend = $c->money - $c->bpt*$c->build_cost*10;
			if($c->pt_agri < 150)
				buy_tech($c,'t_agri',$spend*1/2);
			if($c->pt_bus < 160)
				buy_tech($c,'t_bus',$spend*1/4);
			if($c->pt_res < 160)
				buy_tech($c,'t_res',$spend*1/4);	
		}
	}
	out("Done Playing " . INDY . " Turns for #$cnum!");	//Text for screen
}

function play_indy_turn(&$c){ //c as in country!
	global $turnsleep;
	usleep($turnsleep);
	//out($main->turns . ' turns left');
	if(total_cansell_military($c) > 5000 && sellmilitarytime($c))
		return sell_max_military($c);
	elseif($c->empty > $c->bpt && $c->money > $c->bpt*$c->build_cost){	//build a full BPT if we can afford it
		return build_indy($c);
	}elseif($c->turns >= 4 && $c->empty >= 4 && $c->bpt < 80 && $c->money > 4*$c->build_cost && ($c->foodnet > 0 || $c->food > $c->foodnet*-5)) //otherwise... build 4CS if we can afford it and are below our target BPT (80)
		return build_cs(4); //build 4 CS
	elseif($c->empty < $c->land/2)	//otherwise... explore if we can
		return explore($c);
	elseif($c->empty && $c->bpt < 80 && $c->money > $c->build_cost) //otherwise... build one CS if we can afford it and are below our target BPT (80)
		return build_cs(); //build 1 CS
	else  //otherwise...  cash
		return cash($c);
}

function build_indy(&$c){
	//build farms
	return build(array('indy' => $c->bpt));
}


function sellmilitarytime(&$c){
	global $military_list;
	$sum = $om = 0;
	foreach($military_list as $mil){
		$sum += $c->$mil;
		$om += onmarket($mil);
	}
	if($om < $sum/6)
		return true;
	
	return false;
}

function sell_max_military(&$c){
	$c = get_advisor();	//UPDATE EVERYTHING
	$market_info = get_market_info();	//get the Public Market info
	
	global $military_list;
	
	$quantity = array();
	foreach($military_list as $unit){
		$quantity[$unit] = can_sell_mil($c,$unit);
	}
	
	//$nogoods_high = 5000;
	//$nogoods_low = 3000;
	$randomup = 120; //percent
	$randomdown = 80; //percent
	$price = array(
		'm_tr'=>	$quantity['m_tr'] == 0 ? 0 : floor(($market_info->buy_price->m_tr != null ? $market_info->buy_price->m_tr : rand(110,144))*(rand($randomdown,$randomup)/100)),
		'm_j' =>	$quantity['m_j']  == 0 ? 0 : floor(($market_info->buy_price->m_j  != null ? $market_info->buy_price->m_j  : rand(110,192))*(rand($randomdown,$randomup)/100)),
		'm_tu'=>	$quantity['m_tu'] == 0 ? 0 : floor(($market_info->buy_price->m_tu != null ? $market_info->buy_price->m_tu : rand(110,200))*(rand($randomdown,$randomup)/100)),
		'm_ta'=>	$quantity['m_ta'] == 0 ? 0 : floor(($market_info->buy_price->m_ta != null ? $market_info->buy_price->m_ta : rand(400,560))*(rand($randomdown,$randomup)/100))
	);
	
	$result = sell_public($c,$quantity,$price);
	if($result == 'QUANTITY_MORE_THAN_CAN_SELL'){
		out("TRIED TO SELL MORE THAN WE CAN!?!");
		$c = get_advisor();	//UPDATE EVERYTHING
	}
	global $mktinfo;
	$mktinfo = null;
	return $result;
}

