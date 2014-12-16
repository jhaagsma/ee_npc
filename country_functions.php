<?php


function destock($server,$cnum){
	$c = get_advisor();	//c as in country! (get the advisor)
	out("Destocking #$cnum!");	//Text for screen
	if($c->food > 0)
		sell_on_pm($c,array('m_bu' => $c->food));	//Sell 'em
	
	$dpnw = 200;
	while($c->money > 1000 && $dpnw < 500){
		out("Try to buy goods at $dpnw dpnw or below!");	//Text for screen
		buy_public_below_dpnw($c,$dpnw);
		buy_private_below_dpnw($c,$dpnw);
		$dpnw += 4;
	}
	if($c->money <= 1000)
		out("Done Destocking!");	//Text for screen
	else
		out("Ran out of goods?");	//Text for screen
}

function buy_public_below_dpnw(&$c,$dpnw){
	$market_info = get_market_info();
	//out_data($market_info);
	
	$tr_price = round($dpnw*0.5/((100+$c->g_tax)/100));
	$j_price = $tu_price = round($dpnw*0.6/((100+$c->g_tax)/100));
	$ta_price = round($dpnw*2/((100+$c->g_tax)/100));

	if($market_info->buy_price->m_tr != null && $market_info->available->m_tr > 0){
		while($market_info->buy_price->m_tr <= $tr_price && $c->money > $tr_price){
			$result = buy_public($c,array('m_tr' => floor($c->money/ceil($tr_price*(100+$c->g_tax)/100))),array('m_tr' => $tr_price));	//Buy troops!
			$market_info = get_market_info();
		}
	}
	if($market_info->buy_price->m_j != null && $market_info->available->m_j > 0){
		while($market_info->buy_price->m_j <= $j_price && $c->money > $j_price){
			$result = buy_public($c,array('m_j' => floor($c->money/ceil($j_price*(100+$c->g_tax)/100))),array('m_j' => $j_price));	//Buy troops!
			$market_info = get_market_info();
		}
	}
	if($market_info->buy_price->m_tu != null && $market_info->available->m_tu > 0){
		while($market_info->buy_price->m_tu <= $tr_price && $c->money > $tu_price){
			$result = buy_public($c,array('m_tu' => floor($c->money/ceil($tu_price*(100+$c->g_tax)/100))),array('m_tu' => $tu_price));	//Buy troops!
			$market_info = get_market_info();
		}
	}
	if($market_info->buy_price->m_ta != null && $market_info->available->m_ta > 0){
		while($market_info->buy_price->m_ta <= $tr_price && $c->money > $ta_price){
			$result = buy_public($c,array('m_ta' => floor($c->money/ceil($ta_price*(100+$c->g_tax)/100))),array('m_ta' => $ta_price));	//Buy troops!
			$market_info = get_market_info();
		}
	}
}

function buy_private_below_dpnw(&$c,$dpnw){
	$pm_info = get_pm_info();	//get the PM info
	
	$tr_price = round($dpnw*0.5);
	$j_price = $tu_price = round($dpnw*0.6);
	$ta_price = round($dpnw*2);
	
	if($pm_info->buy_price->m_tr <= $tr_price && $pm_info->available->m_tr > 0){
		$result = buy_on_pm($c,array('m_tr' => min(floor($c->money/$tr_price),$pm_info->available->m_tr)));
	}
	if($pm_info->buy_price->m_ta <= $ta_price && $pm_info->available->m_ta > 0){
		$result = buy_on_pm($c,array('m_ta' => min(floor($c->money/$ta_price),$pm_info->available->m_ta)));
	}
	if($pm_info->buy_price->m_j <= $j_price && $pm_info->available->m_j > 0){
		$result = buy_on_pm($c,array('m_j' => min(floor($c->money/$j_price),$pm_info->available->m_j)));
	}
	if($pm_info->buy_price->m_tu <= $tu_price && $pm_info->available->m_tu > 0){
		$result = buy_on_pm($c,array('m_tu' => min(floor($c->money/$tu_price),$pm_info->available->m_tu)));
	}
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
	if(array_sum($sell_units) == 0){
		out("No Military!");
		return;
	}
	return sell_on_pm($c,$sell_units);	//Sell 'em
}


function food_management(&$c){
	if($c->foodnet > 0)
		return;
	
	//out("food management");
	$foodloss = -1*$c->foodnet;
	$turns_buy = 50;
	if($c->food > $turns_buy*$foodloss)
		return;
	
	$c = get_advisor();	//UPDATE EVERYTHING
	$market_info = get_market_info();	//get the Public Market info
	//out_data($market_info);
	$pm_info = get_pm_info();
	while($turns_buy > 1 && $c->food < $turns_buy*$foodloss){
		$turns_of_food = $foodloss*$turns_buy;
		$market_price = ($market_info->buy_price->m_bu != null ? $market_info->buy_price->m_bu : $pm_info->buy_price->m_bu);
		//out("Market Price: " . $market_price);
		if($c->food < $turns_of_food && $c->money > $turns_of_food*$market_price*((100+$c->g_tax)/100)){ //losing food, less than turns_buy turns left, AND have the money to buy it
			out("Less than $turns_buy turns worth of food! (" . $c->foodnet .  "/turn) Buy food off Public if we can!");	//Text for screen
			$result = buy_public($c,array('m_bu' => min($foodloss*$turns_buy,$market_info->available->m_bu)),array('m_bu' => $market_price));	//Buy 3 turns of food off the public at or below the PM price
			$market_info = get_market_info();	//get the Public Market info
			$c = get_advisor();	//UPDATE EVERYTHING
		}
		/*else
			out("$turns_buy: " . $c->food . ' < ' . $turns_of_food . '; $' . $c->money . ' > $' . $turns_of_food*$market_price);*/
		
		$turns_buy--;
	}

	$turns_buy = 5;
	$turns_of_food = $foodloss*$turns_buy;
	if($c->food < $turns_of_food && $c->money > $turns_buy*$foodloss*$pm_info->buy_price->m_bu){ //losing food, less than turns_buy turns left, AND have the money to buy it
		out("Less than $turns_buy turns worth of food! (" . $c->foodnet .  "/turn) We're rich, so buy food at any price!~");	//Text for screen
		$result = buy_on_pm($c,array('m_bu' => $turns_buy*$foodloss));	//Buy 3 turns of food!
	}
	elseif($c->foodnet < 0 && $c->food < $c->foodnet*-3 && total_military($c) > 30){
		out("We're too poor to buy food! Sell 1/4 of our military");	//Text for screen
		sell_all_military($c,1/4);	//sell 1/4 of our military
	}
}

function defend_self(&$c,$reserve_cash){


}


