<?php


function play_farmer_strat($server){
	global $cnum;
	out("Playing " . FARMER . " turns for #$cnum");
	$main = get_main();	//get the basic stats
	//out_data($main);			//output the main data
	$c = get_advisor();	//c as in country! (get the advisor)
	//out_data($c) && exit;				//ouput the advisor data
	if($c->govt == 'M'){
		$rand = rand(0,100);
		switch($rand){
			case $rand < 12: change_govt($c,'D'); break;
			case $rand < 20: change_govt($c,'I'); break;
			case $rand < 50: change_govt($c,'R'); break;
			default: change_govt($c,'F'); break;
		}
	}
		
	
	out($c->turns . ' turns left');
	$pm_info = get_pm_info();	//get the PM info
	//out_data($pm_info);		//output the PM info
	$market_info = get_market_info();	//get the Public Market info
	//out_data($market_info);		//output the PM info
	
	$owned_on_market_info = get_owned_on_market_info();	//find out what we have on the market
	//out_data($owned_on_market_info);	//output the Owned on Public Market info
	
	while($c->turns > 0){
		//$result = buy_public($c,array('m_bu'=>100),array('m_bu'=>400));
		$result = play_farmer_turn($c);
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
		

		if($c->income < 0 && $c->money < -5*$c->income){ //sell 1/4 of all military on PM
			out("Almost out of money! Sell 10 turns of income in food!");	//Text for screen
			sell_on_pm($c,array('m_bu' => min($c->food,floor(-10*$c->income/$pm_info->sell_price->m_bu))));	//sell 1/4 of our military
		}
		
		if(turns_of_food($c) > 40 && $c->money > $c->networth *2) // 40 turns of food, and more than 2x nw in cash on hand
			defend_self($c,floor($c->money * 0.25)); //second param is *RESERVE* cash
		
		global $cpref;
		$tol = $cpref->price_tolerance; //should be between 0.5 and 1.5
		if($c->money > max($c->bpt,30)*$c->build_cost*10){ //buy_tech
			//out("Try to buy tech?");
			$spend = $c->money - max($c->bpt,30)*$c->build_cost*10;
			if($c->pt_agri < 160)
				buy_tech($c,'t_agri',$spend*1/2,3500*$tol);
			if($c->pt_bus < 140)
				buy_tech($c,'t_bus',$spend*1/4,3500*$tol);
			if($c->pt_res < 140)
				buy_tech($c,'t_res',$spend*1/4,3500*$tol);
			
			$c = get_advisor();	//UPDATE EVERYTHING
			//out("Try Higher Amount!");
			$spend = $c->money - max($c->bpt,30)*$c->build_cost*10;
			if($c->pt_agri < 200)
				buy_tech($c,'t_agri',$spend*1/2,3500*$tol);
			if($c->pt_bus < 160)
				buy_tech($c,'t_bus',$spend*1/4,3500*$tol);
			if($c->pt_res < 160)
				buy_tech($c,'t_res',$spend*1/4,3500*$tol);	
		}
	}
	out("Done Playing " . FARMER . " Turns for #$cnum!");	//Text for screen
}

function play_farmer_turn(&$c){ //c as in country!
	$target_bpt = 50;
	global $turnsleep;
	usleep($turnsleep);
	//out($main->turns . ' turns left');
	if($c->protection == 0 && $c->foodnet > 0 && $c->foodnet > 3*$c->foodcon && $c->food > 30*$c->foodnet && $c->food > 7000) //Don't sell less than 30 turns of food
		return sellextrafood_farmer($c);
	elseif($c->empty > $c->bpt && $c->money > $c->bpt*$c->build_cost){	//build a full BPT if we can afford it
		return build_farmer($c);
	}elseif($c->turns >= 4 && $c->empty >= 4 && $c->bpt < $target_bpt && $c->money > 4*$c->build_cost && ($c->foodnet > 0 || $c->food > $c->foodnet*-5)) //otherwise... build 4CS if we can afford it and are below our target BPT (80)
		return build_cs(4); //build 4 CS
	elseif($c->empty < $c->land/2)	//otherwise... explore if we can
		return explore($c);
	elseif($c->empty && $c->bpt < $target_bpt && $c->money > $c->build_cost) //otherwise... build one CS if we can afford it and are below our target BPT (80)
		return build_cs(); //build 1 CS
	else  //otherwise...  cash
		return cash($c);
}

function sellextrafood_farmer(&$c){
	//out("Lots of food, let's sell some!");
	$pm_info = get_pm_info();
	$market_info = get_market_info();	//get the Public Market info
	$c = get_advisor();	//UPDATE EVERYTHING
	
	$quantity = array('m_bu' => $c->food); //sell it all! :)
	
	$rmax = 1.30; //percent
	$rmin = 0.80; //percent
	$rstep = 0.01;
	$rstddev = 0.10;
	$price = round(max($pm_info->sell_price->m_bu,$market_info->buy_price->m_bu*purebell($rmin,$rmax,$rstddev,$rstep)));
	$price = array('m_bu' => $price);

	if($price <= 29*(100+$c->g_tax)/100)
		return sell_on_pm($c,array('m_bu' => $quantity)); ///		sell_on_pm($c,array('m_bu' => $c->food));	//Sell 'em
	
	return sell_public($c,$quantity,$price);	//Sell food!
}

function build_farmer(&$c){
	//build farms
	return build(array('farm' => $c->bpt));
}


