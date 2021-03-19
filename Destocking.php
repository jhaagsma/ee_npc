<?php

namespace EENPC;

const TURNS_TO_PASS_BEFORE_NEXT_DESTOCK_ATTEMPT = 6;

// TODO: finish headers
// Debug::msg("BUY_PM: Money: $money; Price: {$pm_info->buy_price->m_tu}; Q: ".$q);

/*
NAME: execute_destocking_actions
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$
	
*/
function execute_destocking_actions($cnum, $strategy, $reset_end_time, $server_seconds_per_turn, $max_market_package_time_in_seconds, $pm_oil_sell_price, $pm_food_sell_price, &$next_play_time_in_seconds) {
	$reset_seconds_remaining = $reset_end_time - time();

	$c = get_advisor();	// create object

	// FUTURE: cancel all SOs
	
	// change indy production to 100% jets
	$c->setIndy('pro_j');

	// keep 8 turns for possible recall tech, recall goods, double sale of goods, double sale of tech
	// this is likely too conservative but it doesn't matter if bots lose a few turns of income at the end
	$turns_to_keep = 8;
	$money_to_reserve = max(-11 * $c->income, 55000000); // mil expenses can rise rapidly during destocking, so use 10 M per turn as a guess
	log_country_message($cnum, "Money is $c->money and calculated money to reserve is $money_to_reserve");

	log_country_message($c->cnum, "Turns left: $c->turns");
	log_country_message($cnum, "Starting cashing or teching...");
	temporary_cash_or_tech_at_end_of_set ($c, $strategy, $turns_to_keep, $money_to_reserve);
	log_country_message($cnum, "Finished cashing or teching");

	// FUTURE: replace 0.81667 (max demo mil tech) with game API call
	// reasonable to assume that a greedy demo country will resell bushels for $2 less than max PM sell price on all servers
	// FUTURE: use 1 dollar less than max on clan servers?
	$estimated_public_market_bushel_sell_price = round($pm_food_sell_price / 0.81667) - 2;
	log_country_message($cnum, "Estimated public bushel sell price is $estimated_public_market_bushel_sell_price");

	// FUTURE: buy mil tech - PM purchases, bushel reselling?, bushel selling - I expect this to be an annoying calculation
	
	// FUTURE: switch governments if that would help
	
	// FUTURE: recall bushels if there are enough of them compared to expenses? no API

	// make sure everything is correct because destocking is important
	$c = get_advisor();

	if($c->protection == 1) { // somehow we are in protection still
		log_country_message($cnum, "DESTOCK ERROR: country still in protection"); // TODO: should be an error
		$next_play_time_in_seconds = TURNS_TO_PASS_BEFORE_NEXT_DESTOCK_ATTEMPT * $server_seconds_per_turn;
		return $c;
	}

	// resell bushels if profitable
	$pm_info = PrivateMarket::getInfo();
	$private_market_bushel_price = $pm_info->sell_price->m_bu;
	$current_public_market_bushel_price = PublicMarket::price('m_bu');
	log_country_message($cnum, "Current private market food price is $private_market_bushel_price");
	log_country_message($cnum, "Current public market food price is $current_public_market_bushel_price");
	$should_attempt_bushel_reselling = can_resell_bushels_from_public_market ($private_market_bushel_price, $c->tax(), $current_public_market_bushel_price, $max_profitable_public_market_bushel_price);
	log_country_message($cnum, "Decision on attempting public market bushel reselling: ".log_translate_boolean_to_YN($should_attempt_bushel_reselling));
	if ($should_attempt_bushel_reselling) {
		do_public_market_bushel_resell_loop ($c, $max_profitable_public_market_bushel_price);
		log_country_message($cnum, "Done with bushel reselling");
	}


	// FUTURE: right now we force a private market sale if money is short, we could be smarter about this
	// no need to worry about food because dump_bushel_stock handles it
	$force_private_sale = ($c->turns == 0 or !has_money_for_turns(1, $c->money, $c->taxes, $c->expenses, 0) ? true : false);// note the NOT here
	log_country_message($cnum, "Decision on forcing a private market bushel sale: ".log_translate_boolean_to_YN($force_private_sale));
	log_country_message($cnum, "Attempting to dump bushel stock...");
	dump_bushel_stock($c, $turns_to_keep, $reset_seconds_remaining, $server_seconds_per_turn, $max_market_package_time_in_seconds, $private_market_bushel_price, $estimated_public_market_bushel_sell_price, $force_private_sale);
	log_country_message($cnum, "Done dumping bushel stock");

	// FUTURE: consider burning oil to generate private market units

	// $pm_info = PrivateMarket::getInfo(); leave commented out because it's ok if prices are a little wrong here
	$reserved_money_for_future_private_market_purchases = 0;
	$max_spend = max(0, $c->money - $money_to_reserve);

	// spend money on public market and private market, going in order by dpnw
	// FUTURE: replenishment rates should come from game API
	$destock_units_to_replenishment_rate = array("m_tr" => 3.0, "m_ta" => 1.0, "m_j" => 2.5, "m_tu" => 2.5);
	foreach($destock_units_to_replenishment_rate as $military_unit => $replenishment_rate) {
		log_country_message($cnum, "For $military_unit PM buying loop, total money is $c->money and budget is $max_spend");

		if ($max_spend > 0) {
			log_country_message($cnum, "Attempting to spend money on private and public markets on units better or equal to PM $military_unit...");
			log_country_message($cnum, "Temp debug: PM $military_unit buy price is $pm_info->buy_price->$military_unit"); // TODO: not sure if $pm_info->buy_price->$military_unit is valid syntax
			buyout_up_to_private_market_unit_dpnw ($c, $pm_info->buy_price->$military_unit, $military_unit, $max_spend);
		}

		// estimate future private market unit generation		
		$reserved_money_for_future_private_market_purchases += estimate_future_private_market_capacity_for_military_unit($pm_info->buy_price->$military_unit, $c->land, $replenishment_rate, $reset_seconds_remaining, $server_seconds_per_turn);
		$max_spend = max($c->money - $reserved_money_for_future_private_market_purchases - $money_to_reserve, 0);
	}

	log_country_message($cnum, "Completed all PM purchasing. Money is $c->money and budget is $max_spend.");
	log_country_message($cnum, "Estimated future PM gen capacity is $reserved_money_for_future_private_market_purchases dollars");
	
	$debug_force_final_attempt = false; // change to force final attempt

	// check if this is our last shot at destocking
	if($debug_force_final_attempt or is_final_destock_attempt($reset_seconds_remaining, $server_seconds_per_turn)) {

		// FUTURE: recall stuck bushels if there are enough of them compared to expenses? no API
		
		// note: a human would recall all military goods here, but I don't care if bots lose NW at the end if it allows a human to buy something
		
		log_country_message($cnum, "This is the FINAL destock attempt for this country". log_translate_forced_debug($debug_force_final_attempt));
		log_country_message($cnum, "Selling all food and oil (if possible) on private market for any price...");
		final_dump_all_resources($c, $pm_oil_sell_price);

		if($c->money > 10000000) {
			log_country_message($cnum, "Buying anything available off public market...");
			buyout_up_to_public_market_dpnw($c, 5000, $c->money, false); // buy anything ($10000 tech is 5000 dpnw)
		}
		else
			log_country_message($cnum, "Less than 10 million in cash so not attempting to spend on public market");

	}
	else { // not final attempt
		log_country_message($cnum, "This is NOT the final destock attempt for this country");

		// FUTURE: use SOs and recent market data to avoid getting ripped off
		$max_dpnw = calculate_maximum_dpnw_for_public_market_purchase ($reset_seconds_remaining, $server_seconds_per_turn, $pm_info->buy_price->m_tu, $c->tax());
		
		if($max_spend <= 0)
			log_country_message($cnum, "Budget is $max_spend which means not enough money for additional public market purchases up to $max_dpnw dpnw.");
		else {
			log_country_message($c->cnum, "Attempting to spend budget of $max_spend on public market military goods at or below $max_dpnw dpnw...");
			$money_before_purchase = $c->money;
			buyout_up_to_public_market_dpnw($c, $max_dpnw, $max_spend, true); // don't buy tech, maybe the humans want it
			$money_spent = $money_before_purchase - $c->money;
			log_country_message($c->cnum, "Completed public market purchasing. Budget was $max_spend and spent $money_spent money.");
		}

		// TODO: create function to check if there's time for public market sale (include parameter for additional seconds and check it here)
		// consider putting up military for sale if we have money to play a turn and we expect to have at least 100 M in unspent PM capacity
		// note: no need to reserve money in previous has_money_for_turns call - we reserved money earlier so we could spend turns like this
		$target_sell_amount = $reserved_money_for_future_private_market_purchases - floor(get_total_value_of_on_market_goods($c));
		log_country_message($cnum, "Considering military reselling. Extra PM military capacity is estimated at $target_sell_amount dollars");
		$reason_for_not_reselling_military = null;
		if($target_sell_amount < 100000000)
			$reason_for_not_reselling_military = "Target sell amount of $target_sell_amount is below minimum of 100 million";
		if(!has_money_for_turns(1, $c->money, $c->taxes, $c->expenses, 0, true)) // note the NOT
			$reason_for_not_reselling_military = "Not enough money to play a turn";			

		if($reason_for_not_reselling_military == null) {
			$c = get_advisor(); // need to make sure we have a turn

			if($c->turns == 0)
				$reason_for_not_reselling_military = "No turns";	
			else {
				// buy food to play a turn if needed up to $80 in price
				// it's food for one turn so I don't care if players know about this behavior
				if(!buy_full_food_quantity_if_possible($c, get_food_needs_for_turns(1, $c->foodpro, $c->foodcon, true), 80, 0)) // note the NOT
				$reason_for_not_reselling_military = "Not enough food";
			}	
		}

		if($reason_for_not_reselling_military == null) {
			log_country_message($cnum, "Attempting to build a public market package for military reselling...");
			$did_resell = resell_military_on_public($c, $target_sell_amount);
			if(!$did_resell)
				$reason_for_not_reselling_military = "Couldn't put at least 50 million of goods for sale";

		}

		if($reason_for_not_reselling_military == null)
			log_country_message($cnum, "Finished reselling military");
		else
			log_country_message($cnum, "Did not resell military for reason: $reason_for_not_reselling_military");
		
		// FUTURE: techers should probably sell mil tech to help other countries
		// FUTURE: check if should dump tech
		// FUTURE: dump tech	
	}
	
	// calculate next play time
	// TODO: make further out if money is low, change constant name
	$next_play_time_in_seconds = TURNS_TO_PASS_BEFORE_NEXT_DESTOCK_ATTEMPT * $server_seconds_per_turn;
	// $next_play_time_in_seconds = 30; // TODO: TEMP DEBUG TO MAKE THEM ALWAYS KEEP PLAYING
	log_country_message($cnum, "Will next login in $next_play_time_in_seconds seconds");
	return $c;
}


/*
NAME: get_earliest_possible_destocking_start_time_for_country
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$strategy
	
*/
function get_earliest_possible_destocking_start_time_for_country($bot_secret_number, $strategy, $reset_start_time, $reset_end_time) {
	// I just made this up, can't say that the ranges are any good - Slagpit 20210316
	// techer is last 75% to 90% of reset
	// rainbow and indy are last 90% to 95% of reset
	// farmer and casher are last 95% to 98.5% of reset	
	// note: TURNS_TO_PASS_BEFORE_NEXT_DESTOCK_ATTEMPT value should allow for at least two executions for all strategies

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

	$number_of_seconds_in_set = $reset_end_time - $reset_start_time;
	$number_of_seconds_in_window = ($window_end_time_factor - $window_start_time_factor) * $number_of_seconds_in_set;
	$country_specific_interval_wait = 0.001 * decode_bot_secret($bot_secret_number, 3); // random number three digit number between 0.000 and 0.999 that's fixed for each country

	// example of what we're doing here: suppose that start time factor is 90%, end time is 95% and interval wait is 0.25
	// the earliest destock time then is after 25% has passed of the interval starting with 90% of the reset and ending with 95% of the reset
	// so for a 100 day reset, this country should start destocking after 92.5 days have passed
	return $reset_start_time + $window_start_time_factor * $number_of_seconds_in_set + $country_specific_interval_wait * $number_of_seconds_in_window;
}


/*
NAME: resell_military_on_public
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$
	
*/
function resell_military_on_public (&$c, $target_sell_amount, $min_sell_amount = 50000000) {
	// sell in an opposite order from what I expect humans to do: first sell turrets, then jets, then tanks, than troops

	$total_value_in_new_market_package = 0;
	$pm_info = PrivateMarket::getRecent();

	$market_quantities = [];
	$market_prices = [];

	$unit_types = array('m_tu', 'm_j', 'm_ta', 'm_tr');
	foreach($unit_types as $unit_type) {
		$pm_price = $pm_info->buy_price->$unit_type;
		// FUTURE: prices could be smarter, especially for techers with long destock periods
		// we're basically just throwing military up at a somewhat expensive price
		$public_price = Math::half_bell_truncate_left_side(floor(1.2 * $pm_price), 3 * $pm_price, $pm_price);
		$max_sell_amount = can_sell_mil($c, $unit_type); // FUTURE - this function should account for increased express package sizes
		$units_to_sell = min($max_sell_amount, floor(($target_sell_amount - $total_value_in_new_market_package) / ((2 - $c->tax()) * $public_price)));
		if ($units_to_sell < 5000) // FUTURE: game API call
			break;

		$total_value_in_new_market_package += $units_to_sell * $public_price * (2 - $c->tax());

		$market_quantities[$unit_type] = $units_to_sell;
		$market_prices[$unit_type] = $public_price;
	}

	if ($total_value_in_new_market_package > $min_sell_amount) { // don't bother with sales under $50 M
		// FUTURE: if allowed, set market hours to be the longest possible value
		foreach($market_quantities as $unit_type => $units_to_sell) {
			$market_price = $market_prices[$unit_type];
			log_country_message($cnum, "Placed $units_to_sell units of $unit_type at price $market_price in public market package");
		}
		PublicMarket::sell($c, $market_quantities, $market_prices);
		// FUTURE: do something with the return object
		return true;
	}
	else {
		return false;
	}
}


/*
NAME: temporary_cash_or_tech_at_end_of_set
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$
	
*/
function temporary_cash_or_tech_at_end_of_set (&$c, $strategy, $turns_to_keep, $money_to_reserve) {
	// FUTURE: this code is overly simple and shouldn't exist here - it should call standard code used for teching and cashing
	$current_public_market_bushel_price = PublicMarket::price('m_bu');

	$turns_remaining = $c->turns; // cash and tech don't update $c->turns, so need to do our own management
	while($turns_remaining > $turns_to_keep) {
		// expenses might be high so go turn by turn?

		$incoming_money_per_turn = ($strategy == 'T' ? 1.0 : 1.2) * $c->taxes;
		// check cash
		if(!has_money_for_turns(1, $c->money, $incoming_money_per_turn, $c->expenses, $money_to_reserve)) {
			// not enough money to play a turn - can we make up the difference by selling a turn's worth of food production?
			if($c->foodnet > 0)
				sell_single_good($c, 'm_bu', min($c->food, $c->foodnet));
			
			if (!has_money_for_turns(1, $c->money, $incoming_money_per_turn, $c->expenses, $money_to_reserve)) {
				// playing turns is no longer productive
				log_country_message($c->cnum, "Not enough money to continue running turns. Money is $c->money and money to reserve is $money_to_reserve");
				break;
			}
		}

		// try to buy food if needed up to $50, quit if we can't find cheap enough food
		// FUTURE - be smarter about picking $50
		if(!buy_full_food_quantity_if_possible($c, get_food_needs_for_turns(1, $c->foodpro, $c->foodcon), 50, $money_to_reserve)) {
			log_country_message($c->cnum, "Not enough food to continue running turns. Food is $c->food, money is $c->money, and money to reserve is $money_to_reserve");				
			break;
		}
			

		// TODO: cash more than 1 turn at a time if possible
		// we should have enough food and money to play a turn
		if ($strategy == 'T') {
			tech(['mil' => $c->tpt]);	// FUTURE: adjust for earthquakes
		}
		else {
			cash($c, 1); // TODO: does $c->income get refreshed after cashing? concerned about expenses rising for CIs for example
		}
		$turns_remaining--;	

		if($turns_remaining % 10 == 0)
			log_country_message($c->cnum, "Inside cash or tech loop. Country turns remaining: $turns_remaining");
	}
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
	// FUTURE: error checking on $unit_type
	
	$unit_to_nw_map = array("m_tr" => 0.5, "m_j" => 0.6, "m_tu" => 0.6, "m_ta" => 2.0); // FUTURE: this is stupid
	$pm_unit_nw = $unit_to_nw_map[$unit_type];
	$max_dpnw = $pm_buy_price / $pm_unit_nw;

	$c = get_advisor();	// money must be correct because we get an error if we try to buy too much

	$money_before_purchase = $c->money;
	buyout_up_to_public_market_dpnw($c, $max_dpnw, $max_spend, true);
	$money_spent = $money_before_purchase - $c->money;
	$max_spend -= $money_spent;
	log_country_message($c->cnum, "Spent $money_spent money on public market military cheaper than $max_dpnw dpnw (on $unit_type pm iteration(s))");

	$c = get_advisor(); // money must be correct because we get an error if we try to buy too much
	
	// fresh update because maybe prices changed or units decayed
	$pm_info = PrivateMarket::getInfo();
	$pm_purchase_amount = min($pm_info->available->$unit_type, floor(min($c->money, $max_spend) / $pm_info->buy_price->$unit_type));
	if ($pm_purchase_amount > 0) {
		$money_before_purchase = $c->money;
		PrivateMarket::buy($c, [$unit_type => $pm_purchase_amount]);
		$money_spent = $money_before_purchase - $c->money;
		//log_country_message($c->cnum, "Spent $money_spent money on pm $unit_type"); // logged for free
	}
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
function buyout_up_to_public_market_dpnw(&$c, $max_dpnw, $max_spend, $military_units_only, $total_spent = 0, $recursion_level = 1) {	
	// do some setup to limit API calls for public market info
	$unit_to_nw_map = array("m_tr" => 0.5, "m_j" => 0.6, "m_tu" => 0.6, "m_ta" => 2.0); // FUTURE: this is stupid
	if(!$military_units_only) {	// FUTURE: this is also stupid
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

	$public_market_tax_rate = $c->tax();
	
	log_country_message($c->cnum, "Iteration $recursion_level for public market purchasing with budget $max_spend at or below $max_dpnw dpnw");

	// this is written like this to try to limit public market API calls
	$candidate_purchase_prices_by_unit = [];
	$candidate_dpnw_by_unit = [];
	foreach($unit_to_nw_map as $unit_name => $nw_for_unit) {
		$public_market_price = PublicMarket::price($unit_name);
		if ($public_market_price <> null and $public_market_price <> 0) {
			$candidate_purchase_prices_by_unit[$unit_name] = $public_market_price;
			// log_country_message($c->cnum, "unit:$unit_name, price:$public_market_price");
			$unit_dpnw = round($public_market_tax_rate * $public_market_price / $unit_to_nw_map[$unit_name]);
			$candidate_dpnw_by_unit[$unit_name] = $unit_dpnw;
			log_country_message($c->cnum, "Iteration $recursion_level initial public market conditions for $unit_name are price $public_market_price and dpnw $unit_dpnw");
		}
		else {
			log_country_message($c->cnum, "Iteration $recursion_level initial public market conditions for $unit_name are nothing on market");
		}
	}	
	
	$missed_purchases = 0;
	$total_purchase_loops = 0;
	// spend as much money as possible on public market at or below $max_dpnw
	while ($total_spent + 10000 < $max_spend and $total_purchase_loops < 500) {
		if (empty($candidate_dpnw_by_unit)) // out of stuff to buy?
			break;
			
		asort($candidate_dpnw_by_unit); // get next good to buy, undefined order in case of ties is fine
		$best_unit = array_key_first($candidate_dpnw_by_unit);

		if($candidate_dpnw_by_unit[$best_unit] > $max_dpnw)
			break; // best unit is too expensive
		
		$best_unit_quantity = floor(($max_spend - $total_spent) / ($public_market_tax_rate * $candidate_purchase_prices_by_unit[$best_unit]));
		// deliberately ignoring market quantity because it doesn't matter. attempting to purchase more units than available is not an error
		$best_unit_price = $candidate_purchase_prices_by_unit[$best_unit];
	
		// log_country_message($c->cnum, "Best unit:$best_unit, quantity:$best_unit_quantity, price:$best_unit_price ");

		$money_before_purchase = $c->money;
		PublicMarket::buy($c, [$best_unit => $best_unit_quantity], [$best_unit => $best_unit_price]);
		$diff = $money_before_purchase - $c->money; // I don't like this but the return structure of PublicMarket::buy is tough to deal with
		$total_spent += $diff;
		if ($diff == 0) {
			$missed_purchases++;
			if ($missed_purchases % 10 == 0) // maybe cash was stolen or an SO was filled, so do an expensive refresh
				$c = get_advisor();	
		}

		// refresh price
		$new_public_market_price = PublicMarket::price($best_unit);
		if ($new_public_market_price == null or $new_public_market_price == 0)
			unset($candidate_dpnw_by_unit[$best_unit]);
		else {			
			$candidate_purchase_prices_by_unit[$best_unit] = $new_public_market_price;
			$candidate_dpnw_by_unit[$best_unit] = round($public_market_tax_rate * $new_public_market_price / $unit_to_nw_map[$best_unit]);			
		}

		$total_purchase_loops++;
	}

	// some units might have shown up after we last refreshed prices, so call up to 2 more times recursively
	if ($recursion_level < 3 and $total_spent + 10000 < $max_spend)
		buyout_up_to_public_market_dpnw($c, $max_dpnw, $max_spend, $military_units_only, $total_spent, $recursion_level + 1);

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
function estimate_future_private_market_capacity_for_military_unit($military_unit_price, $land, $replenishment_rate, $reset_seconds_remaining, $server_seconds_per_turn) {
	return ($military_unit_price * $land * $replenishment_rate * floor($reset_seconds_remaining / $server_seconds_per_turn));
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
	$max_profitable_public_market_bushel_price = ceiling($private_market_bushel_price / $public_market_tax_rate - 1);	
	return ($max_profitable_public_market_bushel_price >= $current_public_market_bushel_price ? true : false);
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

		$previous_food = $c->food;
		$result = PublicMarket::buy($c, ['m_bu' => $max_quantity_to_buy_at_once], ['m_bu' => $current_public_market_bushel_price]);
		$bushels_purchased_quantity = $c->food - $previous_food;
		if ($bushels_purchased_quantity == 0) {
			 // most likely explanation is price changed, so update it
			$current_public_market_bushel_price = PublicMarket::price('m_bu');
			$price_refreshes++;
			if ($price_refreshes % 10 == 0) // maybe cash was stolen or an SO was filled, so do an expensive refresh
				$c = get_advisor();
		}			
		else // sell what we purchased on the private market
			PrivateMarket::sell_single_good($c, 'm_bu', $bushels_purchased_quantity);
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
function calculate_maximum_dpnw_for_public_market_purchase ($reset_seconds_remaining, $server_seconds_per_turn, $private_market_turret_price, $public_market_tax_rate) {
	$min_dpnw = ($private_market_turret_price / 0.6) / $public_market_tax_rate; // always buy better than private turret price	
	$max_dpnw = max($min_dpnw + 10, 500 - 2 * floor($reset_seconds_remaining / $server_seconds_per_turn)); // FUTURE: this is stupid and players who read code can take advantage of it
	$std_dev = ($max_dpnw - $min_dpnw) / 2;

	return floor(Math::half_bell_truncate_left_side($min_dpnw, $max_dpnw, $std_dev));
}


/*
NAME: is_final_destock_attempt
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$
	
*/
function is_final_destock_attempt ($reset_seconds_remaining, $server_seconds_per_turn) {
	// TODO: make sure this still works after I change the next play time calculation
	return ($reset_seconds_remaining / $server_seconds_per_turn < TURNS_TO_PASS_BEFORE_NEXT_DESTOCK_ATTEMPT ? true : false);
}


/*
NAME: dump_bushel_stock
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$
	
*/
function dump_bushel_stock(&$c, $turns_to_keep, $reset_seconds_remaining, $server_seconds_per_turn, $max_market_package_time_in_seconds, $private_market_bushel_price, $estimated_public_market_bushel_sell_price, $force_private_sale = false) {
	// FUTURE: recall bushels if profitable (API doesn't exist)

	$bushels_to_sell = $c->food - get_food_needs_for_turns($turns_to_keep, $c->foodpro, $c->foodcon, true);

	if ($bushels_to_sell < 5000) { // don't bother if below min quantity
		log_country_message($c->cnum, "Food is $c->food which is less than 5000 so don't bother selling");
		return;
	}
	
	if($force_private_sale or should_dump_bushels_on_private_market($reset_seconds_remaining, $server_seconds_per_turn, $max_market_package_time_in_seconds, $private_market_bushel_price, $estimated_public_market_bushel_sell_price, $c->tax())) {			
		PrivateMarket::sell_single_good($c, 'm_bu', $bushels_to_sell);
	}
	else { // sell on public
		// TODO: possible to run out of cash while selling
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
function should_dump_bushels_on_private_market ($reset_seconds_remaining, $server_seconds_per_turn, $max_market_package_time_in_seconds, $private_market_bushel_price, $estimated_public_market_bushel_sell_price, $public_market_tax_rate) {
	// sell on private if not enough time to sell on public
	// TODO: use function to check if there's time for public market sale (include parameter for additional seconds and check it here)
	if ($reset_seconds_remaining < $max_market_package_time_in_seconds + TURNS_TO_PASS_BEFORE_NEXT_DESTOCK_ATTEMPT * $server_seconds_per_turn)
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
function final_dump_all_resources(&$c, $pm_oil_sell_price) {
	// no more turns to play for the reset so sell food for any price on private market
	if($c->food > 0)
		PrivateMarket::sell_single_good($c, 'm_bu', $c->food);

	if($pm_oil_sell_price > 0) // sell oil if the server allows it
		PrivateMarket::sell_single_good($c, 'm_oil', $c->oil);

	return;
}