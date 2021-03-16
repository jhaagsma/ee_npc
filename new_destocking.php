<?php

namespace EENPC;

const TURNS_TO_PASS_BEFORE_NEXT_DESTOCK_ATTEMPT = 6;

// MAKE EVERYTHING AS TESTABLE AS POSSIBLE!
// NEED TO LOG WAY MORE MESSAGES USING API

// need function to return money for X turns, food for Y turns


/*
NAME: execute_destocking_actions
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$
	
*/
function execute_destocking_actions($cnum, $reset_end_time, $server_seconds_per_turn, $max_market_package_time_in_seconds, &$next_play_time) {

	// TODO: stop converting to minutes?
	$reset_minutes_remaining = ($server->reset_end - time()) / 60;
	$server_minutes_per_turn = $server_seconds_per_turn / 60;
	$max_market_package_time_in_minutes = $max_market_package_time_in_seconds / 60;

	

	
	// FUTURE: cancel all SOs
	
	// change indy production to 100% jets
	$c->setIndy('pro_j');

	// cash_or_tech_out_turns_if_possible - should call code in another file
	
	// TODO: buy mil tech - PM purchases, bushel reselling?, bushel selling
	
	// FUTURE: switch governments if that would help
	
	// make sure everything is correct because destocking is important
	$c = get_advisor();
	
	// resell bushels if profitable
	$pm_info = PrivateMarket::getInfo();
	$private_market_bushel_price = $pm_info->sell_price->m_bu;
	$current_public_market_bushel_price = PublicMarket::price('m_bu');	
	$should_attempt_bushel_reselling = can_resell_bushels_from_public_market ($private_market_bushel_price, $c->tax(), $current_public_market_bushel_price, $max_profitable_public_market_bushel_price);
	
	if ($should_attempt_bushel_reselling)
		do_public_market_bushel_resell_loop ($c, $max_profitable_public_market_bushel_price);

	// TODO: replace 34 with API calls. this won't work after express bushel sell price changes
	$estimated_public_market_bushel_sell_price = 34; // subtract 2 from demo max private market sell price
	dump_bushel_stock($c, $reset_minutes_remaining, $server_minutes_per_turn, $max_market_package_time_in_minutes, $private_market_bushel_price, $estimated_public_market_bushel_sell_price);

	// FUTURE: consider burning oil to generate private market units

	// $pm_info = PrivateMarket::getInfo(); it's ok if prices are a little wrong
	$reserved_money_for_future_private_market_purchases = 0;
	
	$max_spend = $c->money; // TODO: add expenses
	
	// spend money on public market and private market, going in order by dpnw
	// TODO: replenishment rates should come from game API
	$destock_units_to_replenishment_rate = array("m_tr" => 3.0, "m_ta" => 1.0, "m_j" => 2.5, "m_tu" => 2.5);
	foreach($destock_units_to_replenish_rate as $military_unit => $replenishment_rate) {	
		buyout_up_to_private_market_unit_dpnw ($c, $pm_info->buy_price->$military_unit, $military_unit, $max_spend);
		// set aside money for future private market unit generation		
		$reserved_money_for_future_private_market_purchases += estimate_future_private_market_capacity_for_military_unit($pm_info->buy_price->$military_unit, $c->land, $replenishment_rate, $reset_minutes_remaining, $server_minutes_per_turn);
		$max_spend = $c->money - $reserved_money_for_future_private_market_purchases; // TODO: add expenses
	}
	
	// check if this is our last shot at destocking
	if(is_final_destock_attempt($reset_minutes_remaining, $server_minutes_per_turn)) {
	// if last play:
		// TODO: recall stuck bushels if there are enough of them compared to expenses? no API
		
		// note: a human would recall all military goods here, but I don't care if bots lose NW at the end if it allows a human to buy something
		
		final_dump_all_resources($c);
		
		buyout_up_to_public_market_dpnw($c, 5000, $c->money, true); // buy anything ($10000 tech is 5000 dpnw)
	}
	else { // not final attempt
		$max_dpnw = calculate_maximum_dpnw_for_public_market_purchase ($reset_minutes_remaining, $server_minutes_per_turn, $pm_info->buy_price->m_tu, $c->tax());
		buyout_up_to_public_market_dpnw($c, $max_dpnw, $max_spend, false); // don't buy tech, maybe the humans want it

		// TODO: add very simple reselling if we have the PM capacity
		
		// FUTURE: check if should dump tech
		// FUTURE: dump tech
	
	}
	
	// calculate next play time
	$next_login_time = now() + TURNS_TO_PASS_BEFORE_NEXT_DESTOCK_ATTEMPT * $server_seconds_per_turn;
	return $c;
}


/*
NAME: buyout_up_to_private_market_unit_dpnw
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$
	
*/
function buyout_up_to_private_market_unit_dpnw(&$c, $pm_buy_price, $unit_type, $max_spend) {
	// TODO: error checking on $unit_type
	
	$unit_to_nw_map = array("m_tr" => 0.5, "m_j" => 0.6, "m_tu" => 0.6, "m_ta" => 2.0); // TODO: this is stupid
	$pm_unit_nw = $unit_to_nw_map($unit_type);
	$max_dpnw = $pm_buy_price / $pm_unit_nw;

	$c = get_advisor();	// money must be correct because we get an error if we try to buy too much

	buyout_up_to_public_market_dpnw($c, $max_dpnw, $max_spend, true);
		
	$c = get_advisor(); // money must be correct because we get an error if we try to buy too much
	
	// fresh update because maybe prices changed or units decayed
	$new_pm_info = PrivateMarket::getInfo();
	$pm_purchase_amount = min($pm_info->available->$unit_type, floor(min($c->money, $max_spend) / $pm_info->buy_price->$unit_type));
	if ($pm_purchase_amount > 0)
		PrivateMarket::buy($c, [$unit_type => $pm_purchase_amount]);
	return;	
}


/*
NAME: buyout_up_to_public_market_dpnw
PURPOSE: 
RETURNS: total spent
PARAMETERS:
	$
	$
	
*/
function buyout_up_to_public_market_dpnw(&$c, $max_dpnw, $max_spend, $military_units_only, $total_spent = 0, $recursion_level = 0) {	
	// do some setup to limit API calls for public market info
	$unit_to_nw_map = array("m_tr" => 0.5, "m_j" => 0.6, "m_tu" => 0.6, "m_ta" => 2.0); // TODO: this is stupid
	if(!$military_units_only) {	// TODO: this is also stupid
		$unit_to_nw_map["mil"] = 2.0;
		$unit_to_nw_map["med"] = 2.0;
		$unit_to_nw_map["bus"] = 2.0;
		$unit_to_nw_map["res"] = 2.0;
		$unit_to_nw_map["agri"] = 2.0;
		$unit_to_nw_map["war"] = 2.0;
		$unit_to_nw_map["ms"] = 2.0;
		$unit_to_nw_map["weap"] = 2.0;
		$unit_to_nw_map["indy"] = 2.0;
		$unit_to_nw_map["spy"] = 2.0;
		$unit_to_nw_map["sdi"] = 2.0;
	}
	
	$candidate_purchase_prices_by_unit = [];
	$candidate_dpnw_by_unit = [];
	foreach($unit_to_nw_map as $unit_name => $nw_for_unit) {
		$public_market_price = $public_market_tax_rate * PublicMarket::price($unit_name);
		if ($public_market_price != null) {
			$candidate_purchase_prices_by_unit[$unit_name] = $public_market_price;
			$candidate_dpnw_by_unit[$unit_name] = $public_market_price / $unit_to_nw_map[$unit_name];
		}
	}
	
	$missed_purchases = 0;
	$total_purchase_loops = 0;
	// spend as much money as possible on public market at or below $max_dpnw
	while ($total_spent + 10000 < $max_spend and $missed_purchases < 100 and $total_purchase_loops < 500) {
		if (empty($candidate_dpnw_by_unit)) // out of stuff to buy?
			break;
			
		sort($candidate_dpnw_by_unit); // get next good to buy, undefined order in case of ties is fine
		$best_unit = array_key_first($candidate_dpnw_by_unit);
		$best_unit_quantity = floor($max_spend / $candidate_purchase_prices_by_unit[$best_unit]); // TODO: I suspect ignoring market quantity like this is fine, but test it
		$best_unit_price = $candidate_purchase_prices_by_unit[$best_unit];
		
		$money_before_purchase = $c->money;
		PublicMarket::buy($c, [$best_unit => $best_unit_quantity], [$best_unit => $best_unit_price]);
		$diff = $c->money - $money_before_purchase; // I don't like this but the return structure of PublicMarket::buy is tough to deal with
		$total_spent += $diff;
		if ($diff == 0) {
			$missed_purchases++;
			if ($missed_purchases % 5 == 0) // maybe cash was stolen or an SO was filled, so do an expensive refresh
				$c = get_advisor();	
		}

		// refresh price
		$new_public_market_price = $public_market_tax_rate * PublicMarket::price($best_unit);
		if ($new_public_market_price == null)
			unset($candidate_dpnw_by_unit[$best_unit]);
		else {			
			$candidate_purchase_prices_by_unit[$unit_name] = $new_public_market_price;
			$candidate_dpnw_by_unit[$unit_name] = $new_public_market_price / $unit_to_nw_map[$unit_name];			
		}

		$total_purchase_loops++;
	}

	// some units might have shown up after we last refreshed prices, so call up to 2 more times recursively
	if ($recursion_level < 2)
		buyout_up_to_public_market_dpnw($c, $max_dpnw, $max_spend - $total_spent, $military_units_only, $total_spent, $recursion_level + 1);

	return $total_spent;
}


/*
NAME: estimate_future_private_market_capacity_for_military_unit
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$
	
*/
function estimate_future_private_market_capacity_for_military_unit($m_price, $land, $replenishment_rate, $reset_minutes_remaining, $server_minutes_per_turn) {
	return ($m_price * $land * $replenishment_rate * floor($reset_minutes_remaining / $server_minutes_per_turn));
}


/*
NAME: get_earliest_possible_destocking_start_time_for_country
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$strategy
	
*/
function get_earliest_possible_destocking_start_time_for_country($cnum, $strategy, $reset_start_time, $reset_end_time) {
	// TODO: get a seed in flat file, use that to spread out destocking start times more
	// TODO: make sure TURNS_TO_PASS_BEFORE_NEXT_DESTOCK_ATTEMPT value allows for at least two executions for all strategies

	// techer is last 75% to 90% of reset
	// rainbow and indy are last 90% to 95% of reset
	// farmer and casher are last 95% to 98.5% of reset	
	switch ($strategy) {
		case 'F':
			$window_start_time_factor = 0.95;
			$window_end_time_factor = 0.985;
			break;
		case 'T':
			$window_start_time_factor = 0.75;
			$window_end_time_factor = 0.9;
			break;
		case 'C':
			$window_start_time_factor = 0.95;
			$window_end_time_factor = 0.985;
			break;
		case 'I':
			$window_start_time_factor = 0.90;
			$window_end_time_factor = 0.95;
			break;
		default:
			$window_start_time_factor = 0.90;
			$window_end_time_factor = 0.95;
	}

	// TODO: LOG MESSAGE

	// $window_end_time_factor is deliberately unused for now
	return $reset_start_time + $window_start_time_factor * ($reset_end_time - $window_start_time_factor);
}



/*
NAME: can_resell_bushels_from_public_market
PURPOSE: checks if current public market bushel price allows for profitable reselling on private market
RETURNS: true if reselling is profitable, false otherwise
PARAMETERS:
	$private_market_bushel_price - private market bushel price in dollars
	$public_market_tax_rate - public market tax rate as a decimal: 6% would be 1.06 for example
	$current_public_market_bushel_price - current public market bushel price in dollars
	$max_profitable_public_market_bushel_price - output parameter that has the maximum public price that is still profitable
*/
function can_resell_bushels_from_public_market ($private_market_bushel_price, $public_market_tax_rate, $current_public_market_bushel_price, &$max_profitable_public_market_bushel_price) {	
	$max_profitable_public_market_bushel_price = -1 + ceil($private_market_bushel_price / $public_market_tax_rate);	
	return ($max_profitable_public_market_bushel_price < $current_public_market_bushel_price ? true : false);
}


/*
NAME: do_public_market_bushel_resell_loop 
PURPOSE: in a loop, buys bushels off public to sell on private market
RETURNS: nothing
PARAMETERS:
	$c - country object
	$max_public_market_bushel_purchase_price - maximum public price that is still profitable
*/
function do_public_market_bushel_resell_loop (&$c, $max_public_market_bushel_purchase_price) {	
	$current_public_market_bushel_price = PublicMarket::price('m_bu');
	$price_refreshes = 0;
	
	// limited to 500 because I don't want an insane number of purchases if the buyer has low cash compared to market volume
	for ($number_of_purchases = 0; $number_of_purchases < 500; $number_of_purchases++) {
		if ($current_public_market_bushel_price > $max_public_market_bushel_purchase_price)
			break;
		
		$max_quantity_to_buy_at_once = floor($c->money / ($current_public_market_bushel_price * $c->tax()));

		$result = PublicMarket::buy($c, ['m_bu' => $max_quantity_to_buy_at_once], ['m_bu' => $current_public_market_bushel_price]);
		$bushels_purchased_quantity = $result->purchased[5]["quantity"]; // TODO: just use state of country, don't bother with result
		if ($bushels_purchased_quantity == 0) {
			$current_public_market_bushel_price = PublicMarket::price('m_bu');// most likely explanation is price changed, so update it
			$price_refreshes++;
			if ($price_refreshes % 5 == 0) // maybe cash was stolen or an SO was filled, so do an expensive refresh
				$c = get_advisor();
		}			
		else // sell what we purchased on the private market
			PrivateMarket::sell($c, ['m_bu' => $bushels_purchased_quantity]); 
	}
	
	return;
}


/*
NAME: calculate_maximum_dpnw_for_public_market_purchase
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$
	
*/
function calculate_maximum_dpnw_for_public_market_purchase ($reset_minutes_remaining, $server_minutes_per_turn, $private_market_turret_price, $public_market_tax_rate) {
	// TODO: add random factor, add personality factor, actually use parameters
	// FIX BEFORE RELEASE
	return 499;
}


/*
NAME: is_final_destock_attempt
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$
	
*/
function is_final_destock_attempt ($reset_minutes_remaining, $server_minutes_per_turn) {
	return ($reset_minutes_remaining / $server_minutes_per_turn < TURNS_TO_PASS_BEFORE_NEXT_DESTOCK_ATTEMPT ? true : false);
}


/*
NAME: dump_bushel_stock
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$
	
*/
function dump_bushel_stock(&$c, $reset_minutes_remaining, $server_minutes_per_turn, $max_market_package_time_in_minutes, $private_market_bushel_price, $estimated_public_market_bushel_sell_price) {
	// TODO: recall bushels if profitable (API doesn't exist)
	$bushels_to_sell = $c->food; // TODO: save 10 turns of expenses
	
	if(should_dump_bushels_on_private_market($reset_minutes_remaining, $server_minutes_per_turn, $max_market_package_time_in_minutes, $private_market_bushel_price, $estimated_public_market_bushel_sell_price, $c->tax())) {			
		// TODO: how do I call this?
		PrivateMarket::sell($c, ['m_bu' => $bushels_to_sell]);
		/*
		PrivateMarket::sell($c, ['m_bu' => $c->food]);

		$quantity = ['m_bu' => $c->food];
		return PrivateMarket::sell($c, ['m_bu' => $quantity]);
		*/		
	}
	else { // sell on public
		PublicMarket::sell($c, ['m_bu' => $bushels_to_sell], ['m_bu' => $estimated_public_market_bushel_sell_price]); 
	}
	return;
}


/*
NAME: should_dump_bushels_on_private_market
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$
	
*/
function should_dump_bushels_on_private_market ($reset_minutes_remaining, $server_minutes_per_turn, $max_market_package_time_in_minutes, $private_market_bushel_price, $estimated_public_market_bushel_sell_price, $public_market_tax_rate) {
	// sell on private if not enough time to sell on public
	if ($reset_minutes_remaining < $max_market_package_time_in_minutes + TURNS_TO_PASS_BEFORE_NEXT_DESTOCK_ATTEMPT * $server_minutes_per_turn)
		return true;

	// sell on private if the public price isn't at least $1.5 better
	if ($estimated_public_market_bushel_sell_price * (2 - $public_market_tax_rate) < 1.5 + $private_market_bushel_price)
		return true;
	
	return false;
}


/*
NAME: final_dump_all_resources
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$
	
*/
function final_dump_all_resources(&$c) {
	// no more turns to play for the reset so sell food for any price on private market
	PrivateMarket::sell($c, ['m_bu' => $c->food]); 	

	if(true) // TODO: API call to check if oil can be sold on PM on this server
		PrivateMarket::sell($c, ['m_oil' => $c->oil]); 	

	return;
}