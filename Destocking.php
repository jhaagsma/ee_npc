<?php

namespace EENPC;

const TURNS_TO_PASS_BEFORE_NEXT_DESTOCK_ATTEMPT = 6;

// FUTURE: update advisor if we unexpectedly run out of food, money, or turns? catch said errors?
// FUTURE: call Debug::msg as needed

/*
NAME: execute_destocking_actions
PURPOSE: implements destocking logic. plays turns, makes purchases, and calculates next play time
RETURNS: the country object
PARAMETERS:
	$cnum - the country number
	$strategy - single letter strategy name abbreviation
	$server - server object
	$rules - rules object
	$next_play_time_in_seconds - output for the number of seconds to the next play
	
*/
function execute_destocking_actions($cnum, $strategy, $server, $rules, &$next_play_time_in_seconds) {
	$reset_end_time = $server->reset_end;
	$server_seconds_per_turn = $server->turn_rate;
	$max_market_package_time_in_seconds = $rules->max_time_to_market;
	$is_oil_on_pm = $rules->is_oil_on_pm;
	$base_pm_food_sell_price = $rules->base_pm_food_sell_price;
	$reset_seconds_remaining = $reset_end_time - time();
	$market_autobuy_tech_price = $rules->market_autobuy_tech_price;

	$c = get_advisor();	// create object

	// FUTURE: cancel all SOs
	
	// change indy production to 100% jets
	$c->setIndy('pro_j');

	$debug_force_final_attempt = false; // DEBUG change to true to force final attempt
	$calc_final_attempt = is_final_destock_attempt($reset_seconds_remaining, $server_seconds_per_turn);
	$is_final_destocking_attempt = ($debug_force_final_attempt or $calc_final_attempt ? true : false);

	// FUTURE: keep 8 turns for possible recall tech, recall goods, double sale of goods, double sale of tech
	// however, we don't recall yet so set this to 2 turns
	// want to be careful with the money to reserve unless we make ... if we have 200 bots all spending $50 million at the end then that's 10 B dollars on public
	$turns_to_keep = 2;
	$money_to_reserve = max(-2 * $c->income, 20000000); // mil expenses can rise rapidly during destocking, so use 10 M per turn as a guess
	log_country_message($cnum, "Money is $c->money and calculated money to reserve is $money_to_reserve");
	log_country_message($c->cnum, "Turns left: $c->turns");
	log_country_message($cnum, "Starting cashing or teching...");
	temporary_cash_or_tech_at_end_of_set ($c, $strategy, $turns_to_keep, $money_to_reserve);
	log_country_message($cnum, "Finished cashing or teching");

	// FUTURE: replace 0.81667 (max demo mil tech) with game API call
	// reasonable to assume that a greedy demo country will resell bushels for $2 less than max PM sell price on all servers
	// FUTURE: use 1 dollar less than max on clan servers?
	$estimated_public_market_bushel_sell_price = round($base_pm_food_sell_price / 0.81667) - 2;
	log_country_message($cnum, "Estimated public bushel sell price is $estimated_public_market_bushel_sell_price");

	// FUTURE: buy mil tech - PM purchases, bushel reselling?, bushel selling - I expect this to be an annoying calculation
	
	// FUTURE: switch governments if that would help
	
	// FUTURE: recall bushels if there are enough of them compared to expenses? no API

	// make sure everything is correct because destocking is important
	$c = get_advisor();

	if($c->protection == 1) { // somehow we are in protection still
		log_country_message($cnum, "DESTOCK ERROR: country still in protection"); // FUTURE: should be an error
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

	log_country_message($cnum, "Attempting to dump bushel stock...");
	dump_bushel_stock($c, $turns_to_keep, $reset_seconds_remaining, $server_seconds_per_turn, $max_market_package_time_in_seconds, $private_market_bushel_price, $estimated_public_market_bushel_sell_price, $sold_bushels_on_public);
	log_country_message($cnum, "Done dumping bushel stock");

	// FUTURE: consider burning oil to generate private market units? maybe better to sell for humans

	// $pm_info = PrivateMarket::getInfo(); leave commented out because it's ok if prices are a little wrong here
	$total_cost_to_buyout_future_private_market = 0;
	$max_spend = max(0, $c->money - $money_to_reserve);

	// spend money on public market and private market, going in order by dpnw
	// FUTURE: replenishment rates should come from game API
	$destock_units_to_replenishment_rate = array("m_tr" => 3.0, "m_ta" => 1.0, "m_j" => 2.5, "m_tu" => 2.5);
	foreach($destock_units_to_replenishment_rate as $military_unit => $replenishment_rate) {
		log_country_message($cnum, "For $military_unit market buying loop, total money is $c->money and budget is $max_spend");

		if ($max_spend > 0) {
			log_country_message($cnum, "Attempting to spend money on private and public markets on units better or equal to PM $military_unit...");
			buyout_up_to_private_market_unit_dpnw ($c, $pm_info->buy_price->$military_unit, $military_unit, $max_spend);
		}

		// estimate future private market unit generation	
		// if this is the final attempt then there's no reason to save money for future PM generation
		$future_pm_capacity_for_unit = ($is_final_destocking_attempt ? 0: estimate_future_private_market_capacity_for_military_unit($pm_info->buy_price->$military_unit, $c->land, $replenishment_rate, $reset_seconds_remaining, $server_seconds_per_turn));
		log_country_message($cnum, "Adjusting budget down by $future_pm_capacity_for_unit which is future PM capacity for $military_unit");
		$total_cost_to_buyout_future_private_market += $future_pm_capacity_for_unit;
		$max_spend = max($c->money - $total_cost_to_buyout_future_private_market - $money_to_reserve, 0);
	}

	log_country_message($cnum, "Completed all PM purchasing. Money is $c->money and budget is $max_spend");
	log_country_message($cnum, "Estimated future PM gen capacity is $total_cost_to_buyout_future_private_market dollars");
	
	$did_resell_military = false;
	// check if this is our last shot at destocking
	if($is_final_destocking_attempt) {

		// FUTURE: recall stuck bushels if there are enough of them compared to expenses? no API
		
		// note: a human would recall all military goods here, but I don't care if bots lose NW at the end if it allows a human to buy something
		
		log_country_message($cnum, "This is the FINAL destock attempt for this country". log_translate_forced_debug($debug_force_final_attempt));
		log_country_message($cnum, "Selling all food and oil (if possible) on private market for any price...");
		final_dump_all_resources($c, $is_oil_on_pm);

		if($c->money > 10000000) {
			log_country_message($cnum, "Buying anything available off public market...");
			// FUTURE: buyout private again too? can end up with 55 M that could be better spent on private
			buyout_up_to_public_market_dpnw($c, 5000, $c->money, false); // buy anything ($10000 tech is 5000 dpnw)
			log_country_message($cnum, "Done with public market purchases. Money is $c->money");
		}
		else
			log_country_message($cnum, "Less than 10 million in cash so not attempting to spend on public market");

	}
	else { // not final attempt
		log_country_message($cnum, "This is NOT the final destock attempt for this country");

		// FUTURE: use SOs and recent market data to avoid getting ripped off
		$max_dpnw = calculate_maximum_dpnw_for_public_market_purchase ($reset_seconds_remaining, $server_seconds_per_turn, $pm_info->buy_price->m_tu, $c->tax());
		
		if($max_spend <= 0)
			log_country_message($cnum, "Budget is $max_spend which means not enough money for additional public market purchases up to $max_dpnw dpnw");
		else {
			log_country_message($c->cnum, "Attempting to spend budget of $max_spend on public market military goods at or below $max_dpnw dpnw...");
			$money_before_purchase = $c->money;
			buyout_up_to_public_market_dpnw($c, $max_dpnw, $max_spend, true); // don't buy tech, maybe the humans want it
			$money_spent = $money_before_purchase - $c->money;
			log_country_message($c->cnum, "Completed public market purchasing. Budget was $max_spend and spent $money_spent money.");
		}

		// consider putting up military for sale if we have money to play a turn and we expect to have at least 100 M in unspent PM capacity
		// note: no need to reserve money in previous has_money_for_turns call - we reserved money earlier so we could spend turns like this
		$value_of_public_market_goods = floor(get_total_value_of_on_market_goods($c));
		log_country_message($cnum, "Considering military reselling...");
		$did_resell_military = consider_and_do_military_reselling($c, $value_of_public_market_goods, $total_cost_to_buyout_future_private_market, $rules->max_possible_market_sell, $reset_seconds_remaining, $max_market_package_time_in_seconds);

		log_country_message($cnum, "Considering tech sale...");
		dump_tech($c, $strategy, $market_autobuy_tech_price, $rules->max_possible_market_sell);
	}
	
	// calculate next play time
	if($did_resell_military or $sold_bushels_on_public)
		$next_play_time_in_seconds = $max_market_package_time_in_seconds;
	else
		$next_play_time_in_seconds = TURNS_TO_PASS_BEFORE_NEXT_DESTOCK_ATTEMPT * $server_seconds_per_turn;
	
	//log_country_message($cnum, "Will next login in $next_play_time_in_seconds seconds");
	return $c;
}


/*
NAME: get_earliest_possible_destocking_start_time_for_country
PURPOSE: calculates the earliest time that a country can start destocking based on strategy, time in reset, and other things
RETURNS: the earliest time
PARAMETERS:
	$bot_secret_number - 9 digit country specific number taken from the settings file
	$strategy - single letter strategy name abbreviation
	$reset_start_time - time the current reset began
	$reset_end_time - time the current reset will end
	
*/
function get_earliest_possible_destocking_start_time_for_country($bot_secret_number, $strategy, $reset_start_time, $reset_end_time) {
	// I just made this up, can't say that the ranges are any good - Slagpit 20210316
	// techer is last 75% to 90% of reset
	// rainbow and indy are last 90% to 95% of reset
	// farmer and casher are last 95% to 98.5% of reset	
	// note: TURNS_TO_PASS_BEFORE_NEXT_DESTOCK_ATTEMPT value should allow for at least two executions for all strategies

	$country_specific_interval_wait = 0.001 * decode_bot_secret($bot_secret_number, 3); // random number three digit number between 0.000 and 0.999 that's fixed for each country

	switch ($strategy) {
		case 'F':
			$window_start_time_factor = 0.95;
			$window_end_time_factor = 0.985;
			$country_specific_interval_wait = 0; // farmer window is too short to use the random factor
			break;
		case 'T':
			$window_start_time_factor = 0.75;
			$window_end_time_factor = 0.9;			
			break;
		case 'C':
			$window_start_time_factor = 0.95;
			$window_end_time_factor = 0.985;
			$country_specific_interval_wait = 0; // casher window is too short to use the random factor
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
	
	// example of what we're doing here: suppose that start time factor is 90%, end time is 95% and interval wait is 0.25
	// the earliest destock time then is after 25% has passed of the interval starting with 90% of the reset and ending with 95% of the reset
	// so for a 100 day reset, this country should start destocking after 92.5 days have passed
	return $reset_start_time + $window_start_time_factor * $number_of_seconds_in_set + $country_specific_interval_wait * $number_of_seconds_in_window;
}


/*
NAME: dump_tech
PURPOSE: sell extra tech during destocking - techers only for now
RETURNS: true if tech was sold, false otherwise
PARAMETERS:
	$c - the country object
	$strategy - single letter strategy name abbreviation
	$market_autobuy_tech_price - price floor for public market, typically between $500 and $1000 based on server rules
	$server_max_possible_market_sell - percentage of goods that can be sold at once as a whole number, usually 25
*/
function dump_tech($c, $strategy, $market_autobuy_tech_price, $server_max_possible_market_sell) {
	$food_needed = max(0, get_food_needs_for_turns(1, $c->foodpro, $c->foodcon, true) - $c->food);

	$reason_for_not_selling_tech = null;
	if ($strategy <> 'T')
		$reason_for_not_selling_tech = "No support for non-techers at this time"; // FUTURE: add support
	elseif($market_autobuy_tech_price < 700)
		$reason_for_not_selling_tech = "Autobuy price of $market_autobuy_tech_price is too low"; // FUTURE: allow for sales above autoprice??
	elseif(!buy_full_food_quantity_if_possible($c, $food_needed, 80, 0))
		$reason_for_not_selling_tech = "Not enough food";
	elseif(!has_money_for_turns(1, $c->money, $c->taxes, $c->expenses, 0, true)) // note the NOT
		$reason_for_not_selling_tech = "Not enough money to play a turn";			
	elseif($c->turns == 0)
		$reason_for_not_selling_tech = "No turns";	

	if($reason_for_not_selling_tech == null) {
		$tech_quantities = [
			'mil' => 0,
			'med' => can_sell_tech($c, 't_med', $server_max_possible_market_sell),
			'bus' => can_sell_tech($c, 't_bus', $server_max_possible_market_sell),
			'res' => can_sell_tech($c, 't_res', $server_max_possible_market_sell),
			'agri' => can_sell_tech($c, 't_agri', $server_max_possible_market_sell),
			'war' => can_sell_tech($c, 't_war', $server_max_possible_market_sell),
			'ms' => can_sell_tech($c, 't_ms', $server_max_possible_market_sell),
			'weap' => can_sell_tech($c, 't_weap', $server_max_possible_market_sell),
			'indy' => can_sell_tech($c, 't_indy', $server_max_possible_market_sell),
			'spy' => can_sell_tech($c, 't_spy', $server_max_possible_market_sell),
			'sdi' => can_sell_tech($c, 't_sdi', $server_max_possible_market_sell)
		];

		$total_tech_to_sell = array_sum($tech_quantities);

		$money_from_tech_sale = (2 - $c->tax()) * $market_autobuy_tech_price * $total_tech_to_sell;
		if($total_tech_to_sell > 0 and $money_from_tech_sale + $c->income > 0) {
			// create matching array of prices
			$prices = array_combine(array_keys($tech_quantities), array_fill(0, count($tech_quantities), $market_autobuy_tech_price));
			$result = PublicMarket::sell($c, $tech_quantities, $prices);
			update_c($c, $result);
			log_country_message($c->cnum, "Sold $total_tech_to_sell tech points at autobuy prices");
			return true;
		}
		else
			$reason_for_not_selling_tech = "Selling $total_tech_to_sell tech points at autobuy prices isn't profitable";	
	}

	if($reason_for_not_selling_tech <> null)
		log_country_message($c->cnum, "Did not sell tech for reason: $reason_for_not_selling_tech");
	return false;
}


/*
NAME: consider_and_do_military_reselling
PURPOSE: determines if military should be sold on public during destocking and sells military if so
RETURNS: true if military was sold on public, false otherwise
PARAMETERS:
	$c - the country object
	$value_of_public_market_goods - the total value of all goods already on the public market
	$total_cost_to_buyout_future_private_market - future PM generation
	$server_max_possible_market_sell - percentage of goods that can be sold at once as a whole number, usually 25
	$reset_start_time - time the current reset began
	$max_market_package_time_in_seconds - server rule for maximum number of seconds it can take for a package to show up on the public market	
*/
function consider_and_do_military_reselling(&$c, $value_of_public_market_goods, $total_cost_to_buyout_future_private_market, $server_max_possible_market_sell, $reset_seconds_remaining, $max_market_package_time_in_seconds) {
	log_country_message($c->cnum, "Money is $c->money, on-market value is $value_of_public_market_goods, and future PM gen is $total_cost_to_buyout_future_private_market");
	$target_sell_amount = max(0, $total_cost_to_buyout_future_private_market - $value_of_public_market_goods - $c->money);
	log_country_message($c->cnum, "Future free PM military capacity is estimated at $target_sell_amount dollars");
	$reason_for_not_reselling_military = null;

	if(!is_there_time_to_sell_on_public($reset_seconds_remaining, $max_market_package_time_in_seconds, 300))
		$reason_for_not_reselling_military = "Not enough time left in set for military resell";	
	elseif($target_sell_amount < 100000000)
		$reason_for_not_reselling_military = "Target sell amount of $target_sell_amount is below minimum of 100 million";
	elseif($c->turns == 0)
		$reason_for_not_reselling_military = "No turns";	

	if($reason_for_not_reselling_military == null) {
		// buy food to play a turn if needed up to $80 in price
		// it's food for one turn so I don't care if players know about this behavior
		$food_needed = max(0, get_food_needs_for_turns(1, $c->foodpro, $c->foodcon, true) - $c->food);
		if(!buy_full_food_quantity_if_possible($c, $food_needed, 80, 0))
			$reason_for_not_reselling_military = "Not enough food";
		elseif(!has_money_for_turns(1, $c->money, $c->taxes, $c->expenses, 0, true)) // note the NOT
			$reason_for_not_reselling_military = "Not enough money to play a turn";		

	}

	if($reason_for_not_reselling_military == null) {
		log_country_message($c->cnum, "Attempting to build a public market package for military reselling...");
		$did_resell = resell_military_on_public($c, $server_max_possible_market_sell, $target_sell_amount);
		if(!$did_resell)
			$reason_for_not_reselling_military = "Couldn't put at least 50 million of goods for sale";
	}

	if($reason_for_not_reselling_military == null) {
		log_country_message($c->cnum, "Resold military successfully");
		return true;
	}
	else {
		log_country_message($c->cnum, "Did not resell military for reason: $reason_for_not_reselling_military");
		return false;
	}
}


/*
NAME: resell_military_on_public
PURPOSE: sells military on public market if possible - not possible if max is already on market, for example
RETURNS: true if military was sold, false otherwise
PARAMETERS:
	$c - the country object
	$server_max_possible_market_sell - percentage of goods that can be sold at once as a whole number, usually 25
	$target_sell_amount - maximum value of goods that should be sold
	$min_sell_amount - minimum value of goods that can be sold	
*/
function resell_military_on_public (&$c, $server_max_possible_market_sell, $target_sell_amount, $min_sell_amount = 50000000) {
	// sell in an opposite order from what I expect humans to do: first sell turrets, then jets, then tanks, than troops
	log_country_message($c->cnum, "Want to sell mil units with min value of $min_sell_amount dollars and max of $target_sell_amount dollars");

	$total_value_in_new_market_package = 0;
	$pm_info = PrivateMarket::getRecent();

	$market_quantities = [];
	$market_prices = [];

	$unit_types = array('m_tu', 'm_j', 'm_ta', 'm_tr');
	foreach($unit_types as $unit_type) {
		$pm_price = $pm_info->buy_price->$unit_type;
		// FUTURE: prices could be smarter, especially for techers with long destock periods
		// we're basically just throwing military up at a somewhat expensive price
		$price_increase = 1 + 0.01 * mt_rand(10, 25);
		$public_price = Math::half_bell_truncate_left_side(floor($price_increase * $pm_price), 3 * $pm_price, $pm_price);
		$max_sell_amount = can_sell_mil($c, $unit_type, $server_max_possible_market_sell);
		log_country_message($c->cnum, "Calc sale of $unit_type: PM price is $pm_price, our sell price is $public_price, and max sell is $max_sell_amount units");

		$units_to_sell = min($max_sell_amount, floor(($target_sell_amount - $total_value_in_new_market_package) / ((2 - $c->tax()) * $public_price)));
		if ($units_to_sell < 5000) // FUTURE: game API call
			continue;			

		$additional_value_from_unit = round($units_to_sell * $public_price * (2 - $c->tax()));
		$total_value_in_new_market_package += $additional_value_from_unit;
		log_country_message($c->cnum, "For $unit_type: sell volume recalculated as $units_to_sell and package value is $additional_value_from_unit");

		$market_quantities[$unit_type] = $units_to_sell;
		$market_prices[$unit_type] = $public_price;
	}

	log_country_message($c->cnum, "After checking all military unit types, the total package value is $total_value_in_new_market_package dollars");	
	if ($total_value_in_new_market_package > $min_sell_amount) { // don't bother with sales under $50 M
		// FUTURE: if allowed, set market hours to be the longest possible value
		foreach($market_quantities as $unit_type => $units_to_sell) {
			$market_price = $market_prices[$unit_type];
			//log_country_message($c->cnum, "Placed $units_to_sell units of $unit_type at price $market_price in public market package"); // logged for free?
		}
		$turn_result = PublicMarket::sell($c, $market_quantities, $market_prices);
		update_c($c, $turn_result);
		// FUTURE: do something with the return object
		return true;
	}
	else {
		return false;
	}
}



/*
NAME: temporary_check_if_cash_or_tech_is_profitable
PURPOSE: very rough calculation to determine if a destocking country should keep cashing or teching turns
RETURNS: true if playing turns is profitable, false otherwise
PARAMETERS:
	$strategy - single letter strategy name abbreviation
	$income - net income for country (non-cashing)
	$cashing - cashing income for country
	$tpt - tech per turn for country
	$foodnet - net food change per turn for country	
*/
function temporary_check_if_cash_or_tech_is_profitable ($strategy, $income, $cashing, $tpt, $foodnet) {
	// very rough calculations - don't care if this is inaccurate
	if ($strategy == 'I')
		return true; // future: calc indy production to check something
	elseif ($strategy == 'T')
		return ($income + 700 * $tpt + 34 * $foodnet > 0 ? true: false);
	else
		return ($cashing + 34 * $foodnet > 0 ? true: false);
}


/*
NAME: temporary_cash_or_tech_at_end_of_set
PURPOSE: cashes or techs turns for a destocking country - code should probably be moved out of here at some point
RETURNS: nothing
PARAMETERS:
	$c - the country object
	$strategy - single letter strategy name abbreviation
	$turns_to_keep - keep at least these many turns
	$money_to_reserve - stop running turns if we think we'll end up with less money than this
*/
function temporary_cash_or_tech_at_end_of_set (&$c, $strategy, $turns_to_keep, $money_to_reserve) {
	// FUTURE: this code is overly simple and shouldn't exist here - it should call standard code used for teching and cashing

	$should_play_turns = temporary_check_if_cash_or_tech_is_profitable($strategy, $c->income, $c->cashing, $c->tpt, $c->foodnet);
	if(!$should_play_turns) {
		log_country_message($c->cnum, "Not cashing or teching turns because playing turns is not expected to be profitable");
		return;
	}

	$current_public_market_bushel_price = PublicMarket::price('m_bu');
	$is_cashing = ($strategy == 'T' ? false : true);
	$turns_to_play_at_once = 10;

	while($c->turns > $turns_to_keep) {
		// first try to play in blocks of 10 turns, if that fails then go to turn by turn
		// FUTURE: a farmer can sell on private and should end up being able to cash 10X turn at a time
		// FUTURE: shouldn't farmers avoid decay?
		if($c->turns - $turns_to_keep < 10)
			$turns_to_play_at_once = 1;

		if(!food_and_money_for_turns($c, $turns_to_play_at_once, $money_to_reserve, $is_cashing)){ // add 1 to reduce chance of running out
			if($turns_to_play_at_once == 10) {
				$turns_to_play_at_once = 1;
				continue;
			}
			else
				break; // couldn't get money or food for playing a single turn, so stop
		}

		// we should have enough food and money to play turns
		if ($strategy == 'T') {
			$turn_result = tech(['mil' => $turns_to_play_at_once * $c->tpt]);
		}
		else {
			$turn_result = cash($c, $turns_to_play_at_once);
		}
		update_c($c, $turn_result);
	}

	return;
}


/*
NAME: food_and_money_for_turns
PURPOSE: as needed, try to acquire food and money to run the specified number of turns
RETURNS: true if we have enough food and money to run turns, false otherwise
PARAMETERS:
	$c - the country object
	$turns_to_play - number of turns we want to play
	$money_to_reserve - money that we cannot spend and have to keep in reserve
	$is_cashing - 1 if cashing, 0 if not	
*/
function food_and_money_for_turns(&$c, $turns_to_play, $money_to_reserve, $is_cashing) {
	$incoming_money_per_turn = ($is_cashing ? 1.0 : 1.2) * $c->taxes;
	$additional_turns_for_expenses_growth = floor($turns_to_play / 7); // FUTURE: this is a wild guess
	// check money
	if(!has_money_for_turns($turns_to_play + $additional_turns_for_expenses_growth, $c->money, $incoming_money_per_turn, $c->expenses, $money_to_reserve)) {
		// not enough money to play a turn - can we make up the difference by selling a turn's worth of food production?
		if($c->food > 0 and $c->foodnet > 0) {
			// log_country_message($c->cnum, "TEMP DEBUG: Food is ".$c->food); // TODO: figure out why this errors sometimes?
			PrivateMarket::sell_single_good($c, 'm_bu', min($c->food, ($turns_to_play + $additional_turns_for_expenses_growth) * $c->foodnet));
		}
		
		if (!has_money_for_turns($turns_to_play + $additional_turns_for_expenses_growth, $c->money, $incoming_money_per_turn, $c->expenses, $money_to_reserve)) {
			// playing turns is no longer productive
			log_country_message($c->cnum, "Not enough money to play $turns_to_play turns. Money is $c->money and money to reserve is $money_to_reserve");
			return false;
		}
	}

	// try to buy food if needed up to $60, quit if we can't find cheap enough food
	// FUTURE - be smarter about picking $60

	$food_needed = max(0, get_food_needs_for_turns($turns_to_play + $additional_turns_for_expenses_growth, $c->foodpro, $c->foodcon) - $c->food);
	//log_country_message($c->cnum, "Food is $c->food and calculated food needs are $food_needed");	
			
	if(!buy_full_food_quantity_if_possible($c, $food_needed, 60, $money_to_reserve)) {
		log_country_message($c->cnum, "Not enough food to play $turns_to_play turns. Food is $c->food, money is $c->money, and money to reserve is $money_to_reserve");				
		return false;
	}

	return true;
}






/*
NAME: buyout_up_to_private_market_unit_dpnw
PURPOSE: for a single military unit, buy public market for goods with better dpnw and buyout private market while staying within the budget
RETURNS: nothing
PARAMETERS:
	$c - the country object
	$pm_buy_price - the private market purchase price of the unit that we're processing
	$unit_type - the unit that we're processing: m_tr, m_j, m_tu, or m_ta
	$max_spend - don't spend more money than this
*/
function buyout_up_to_private_market_unit_dpnw(&$c, $pm_buy_price, $unit_type, $max_spend) {
	// FUTURE: error checking on $unit_type
	
	$unit_to_nw_map = array("m_tr" => 0.5, "m_j" => 0.6, "m_tu" => 0.6, "m_ta" => 2.0); // FUTURE: this is stupid
	$pm_unit_nw = $unit_to_nw_map[$unit_type];
	$max_dpnw = floor($pm_buy_price / $pm_unit_nw);

	$c = get_advisor();	// money must be correct because we get an error if we try to buy too much

	$money_before_purchase = $c->money;
	buyout_up_to_public_market_dpnw($c, $max_dpnw, $max_spend, true);
	$money_spent = $money_before_purchase - $c->money;
	$max_spend -= $money_spent;
	log_country_message($c->cnum, "Spent $money_spent money on public market military cheaper than $max_dpnw dpnw (on $unit_type pm iterations)");

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
PURPOSE: attempts to purchase from the public market below a dpnw while following certain rules, loops up to two times
RETURNS: total money spent so far by all iterations
PARAMETERS:
	$c - the country object
	$max_dpnw - don't buy anything with a higher dpnw than this
	$max_spend - don't spend more money than this
	$military_units_only - true if should only buy military units, false is buying tech is okay too
	$total_spent - internal, don't pass this in when calling externally
	$recursion_level - internal, don't pass this in when calling externally
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
	
	log_country_message($c->cnum, "Iteration $recursion_level for public market purchasing with budget ".($max_spend - $total_spent)." at or below $max_dpnw dpnw");

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
		// NOTE: might log some weird looking purchases here with the quantity slowly decreasing
		// this can happen if the country buys off private instead of public
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
	if ($recursion_level < 2 and $total_spent + 10000 < $max_spend)
		buyout_up_to_public_market_dpnw($c, $max_dpnw, $max_spend, $military_units_only, $total_spent, $recursion_level + 1);

	return $total_spent;
}


/*
NAME: estimate_future_private_market_capacity_for_military_unit
PURPOSE: calculates the amount of money needed to purchase all future generated private market units of a single type
RETURNS: the amount of money needed to purchase all future generated private market units of a single type
PARAMETERS:
	$military_unit_price - private military price of the unit
	$land - land for the country
	$replenishment_rate - number of units generated per acre per turn, typically within 1-3 for military units
	$reset_seconds_remaining - seconds left in the reset
	$server_seconds_per_turn - seconds per turn
*/
function estimate_future_private_market_capacity_for_military_unit($military_unit_price, $land, $replenishment_rate, $reset_seconds_remaining, $server_seconds_per_turn) {
	return round($military_unit_price * $land * $replenishment_rate * floor($reset_seconds_remaining / $server_seconds_per_turn));
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
	$max_profitable_public_market_bushel_price = ceil($private_market_bushel_price / $public_market_tax_rate - 1);	
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
		if ($current_public_market_bushel_price == 0 or $current_public_market_bushel_price == null or $current_public_market_bushel_price > $max_public_market_bushel_purchase_price)
			break;
		
		$max_quantity_to_buy_at_once = floor($c->money / ($current_public_market_bushel_price * $c->tax()));

		$previous_food = $c->food;
		$result = PublicMarket::buy($c, ['m_bu' => $max_quantity_to_buy_at_once], ['m_bu' => $current_public_market_bushel_price]);
		// TODO: sometimes see an error here with an unexpected price (1 below last) - expected? taxes?
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
PURPOSE: calculates a high value for dpnw to use for public market purchases more expensive than the private market
RETURNS: a dpnw value
PARAMETERS:
	$reset_seconds_remaining - seconds left in the reset
	$server_seconds_per_turn - seconds per turn
	$private_market_turret_price - purchase price for private market turrets
	$public_market_tax_rate - public market tax rate as a decimal: 6% would be 1.06 for example
*/
function calculate_maximum_dpnw_for_public_market_purchase ($reset_seconds_remaining, $server_seconds_per_turn, $private_market_turret_price, $public_market_tax_rate) {
	$min_dpnw = ($private_market_turret_price / 0.6) / $public_market_tax_rate; // always buy better than private turret price	
	$max_dpnw = max($min_dpnw + 10, 500 - 2 * floor($reset_seconds_remaining / $server_seconds_per_turn)); // FUTURE: this is stupid and players who read code can take advantage of it
	$std_dev = ($max_dpnw - $min_dpnw) / 2;

	return floor(Math::half_bell_truncate_left_side($min_dpnw, $max_dpnw, $std_dev));
}


/*
NAME: is_final_destock_attempt
PURPOSE: determines if this is likely to be the final destocking attempt (no more logins for this country)
RETURNS: true if final destock attempt, false otherwise
PARAMETERS:
	$reset_seconds_remaining - seconds left in the reset
	$server_seconds_per_turn - seconds per turn	
*/
function is_final_destock_attempt ($reset_seconds_remaining, $server_seconds_per_turn) {
	// don't have to worry about longer logouts due to market selling because market selling is only done if there's enough time left in the set for it
	return ($reset_seconds_remaining / $server_seconds_per_turn < TURNS_TO_PASS_BEFORE_NEXT_DESTOCK_ATTEMPT ? true : false);
}


/*
NAME: dump_bushel_stock
PURPOSE: sell extra bushels on private or public market
RETURNS: nothing
PARAMETERS:
	$c - the country object
	$turns_to_keep - keep at least these many turns
	$reset_seconds_remaining - seconds left in the reset
	$max_market_package_time_in_seconds - server rule for maximum number of seconds it can take for a package to show up on the public market	
	$private_market_bushel_price - private market bushel price in dollars
	$estimated_public_market_bushel_sell_price - the max price that we expect demos to bushel clear at
	$sold_bushels_on_public - output parameter that is true if we sold bushels on public, false otherwise
*/
function dump_bushel_stock(&$c, $turns_to_keep, $reset_seconds_remaining, $server_seconds_per_turn, $max_market_package_time_in_seconds, $private_market_bushel_price, $estimated_public_market_bushel_sell_price, &$sold_bushels_on_public) {
	// FUTURE: recall bushels if profitable (API doesn't exist)

	// decide if should sell on public or private
	$sold_bushels_on_public = true;

	if(!is_there_time_to_sell_on_public($reset_seconds_remaining, $max_market_package_time_in_seconds, 1800)) {
		$sold_bushels_on_public = false;
		$sold_on_private_reason = 'not enough time';
	}
	
	if($estimated_public_market_bushel_sell_price * (2 - $c->tax()) < 1.5 + $private_market_bushel_price) {
		$sold_bushels_on_public = false;
		$sold_on_private_reason = 'can get more money on private';
	}

	if($c->turns == 0) {
		$sold_bushels_on_public = false;
		$sold_on_private_reason = 'no turns';
	}

	// not factoring in expenses, but who cares?

	if(!$sold_bushels_on_public) {
		log_country_message($c->cnum, "Not selling bushels on public for reason: $sold_on_private_reason");
		$bushels_to_sell = max(0, $c->food - get_food_needs_for_turns($turns_to_keep, $c->foodpro, $c->foodcon, true));
		if ($bushels_to_sell > 0)
			PrivateMarket::sell_single_good($c, 'm_bu', $bushels_to_sell);
		return;
	}

	// if low money, sell some bushels on private
	if(!has_money_for_turns(1, $c->money, $c->taxes, $c->expenses, 0)) {
		$bushels_to_sell_to_play_turn = min($c->food, ceil(($c->expenses - $c->taxes - $c->money) / $private_market_bushel_price));
		if($bushels_to_sell_to_play_turn > 0) {
			log_country_message($c->cnum, "Selling bushels on private to get money to play a turn");
			PrivateMarket::sell_single_good($c, 'm_bu', $bushels_to_sell_to_play_turn);
		}
	}

	$bushels_to_sell = $c->food - get_food_needs_for_turns($turns_to_keep, $c->foodpro, $c->foodcon, true);
	if ($bushels_to_sell < 5000) { // don't bother if below min quantity
		log_country_message($c->cnum, "Possible bushels to sell is $bushels_to_sell which is less than 5000 so don't bother selling");
		return;
	}
	
	$turn_result = PublicMarket::sell($c, ['m_bu' => $bushels_to_sell], ['m_bu' => $estimated_public_market_bushel_sell_price]); 
	update_c($c, $turn_result);
	return;
}


/*
NAME: is_there_time_to_sell_on_public
PURPOSE: determines if there's enough time left in the reset to make a public market sale
RETURNS: true if there's enough time, false otherwise
PARAMETERS:
	$reset_seconds_remaining - seconds left in the reset
	$max_market_package_time_in_seconds - server rule for maximum number of seconds it can take for a package to show up on the public market
	$padding_seconds - additional seconds to add after the max time. for example, use 1800 if we are selling bushels and we expect it to take half an hour for bushels to be cleared
*/
function is_there_time_to_sell_on_public($reset_seconds_remaining, $max_market_package_time_in_seconds, $padding_seconds = 3600) {
	return ($reset_seconds_remaining >= $padding_seconds + $max_market_package_time_in_seconds);
}


/*
NAME: final_dump_all_resources
PURPOSE: sell all food and oil on private market because we are done playing turns for the reset
RETURNS: nothing
PARAMETERS:
	$c - the country object
	$is_oil_on_pm - server rule for if oil is on the private market	
*/
function final_dump_all_resources(&$c, $is_oil_on_pm) {
	// no more turns to play for the reset so sell food for any price on private market
	if($c->food > 0)
		PrivateMarket::sell_single_good($c, 'm_bu', $c->food);

	if($is_oil_on_pm and $c->oil > 0) // sell oil if the server allows it
		PrivateMarket::sell_single_good($c, 'm_oil', $c->oil);

	return;
}