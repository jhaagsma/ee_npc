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
	$cpref - country preference object
	$server - server object
	$rules - rules object
	$next_play_time_in_seconds - output for the number of seconds to the next play
	$exit_condition - output for country condition when we finished destocking
*/
function execute_destocking_actions($cnum, $cpref, $server, $rules, &$next_play_time_in_seconds, &$exit_condition) {
	$strategy = $cpref->strat;
	$exit_condition = 'NORMAL';
	$reset_end_time = $server->reset_end;
	$server_seconds_per_turn = $server->turn_rate;
	$max_market_package_time_in_seconds = $rules->max_time_to_market;
	$is_oil_on_pm = $rules->is_oil_on_pm;
	$reset_seconds_remaining = $reset_end_time - time();
	$market_autobuy_tech_price = $rules->market_autobuy_tech_price;
	$turns_left_in_set = floor($reset_seconds_remaining / $server_seconds_per_turn);

	$c = get_advisor();	// create object

	if($c->protection == 1) { // somehow we are in protection still
		log_error_message(118, $cnum, "Turns played is $c->turns_played");
		$exit_condition = 'ERROR';
		$next_play_time_in_seconds = TURNS_TO_PASS_BEFORE_NEXT_DESTOCK_ATTEMPT * $server_seconds_per_turn;
		return $c;
	}

	// FUTURE: cancel all SOs
	
	// for indy, set production to 50% jets and 50% turrets because it gives the most NW
	// ideally we'd set to 100% jets (which is common for humans), but doing so makes bots too vulnerable to player abuse
	if($strategy == 'I')
		$c->setIndy(['pro_j' => 50, 'pro_tu' => 50]);

	$debug_force_final_attempt = false; // DEBUG change to true to force final attempt
	$calc_final_attempt = is_final_destock_attempt($reset_seconds_remaining, $server_seconds_per_turn);
	$is_final_destocking_attempt = (($debug_force_final_attempt or $calc_final_attempt) ? true : false);

	
	$bushel_market_history_info = get_market_history('food', $cpref->market_search_look_back_hours);
	log_country_message($cnum, "The average public bushel sell price is $bushel_market_history_info->avg_price over the past $cpref->market_search_look_back_hours hours");
	// reasonable to assume that a demo country will resell bushels for $1 less than max PM sell price on all servers
	// undercut by $1 because we're dumping
	$estimated_public_market_bushel_sell_price = max(get_max_demo_bushel_recycle_price($rules), floor($bushel_market_history_info->avg_price)) - 1;
	log_country_message($cnum, "Estimated public bushel sell price is $estimated_public_market_bushel_sell_price");

	// get what's on the market so we can recall goods or tech as needed depending on time left in reset
	$bushels_on_market = false;
	$expensive_tech_on_market = false;
	$expensive_bushels_on_market = false;	
	$market_owned = get_owned_on_market_info();
	//out_data($market_owned);
	foreach($market_owned as $market_package_piece) {
		// $market_package_piece structure: [{"type":"t_mil","price":6277,"quantity":103068,"time":1617827768,"on_market":true,"seconds_until_on_market":-2362}]
		if($market_package_piece->type == 'food') {
			$bushels_on_market = true;
			if ($market_package_piece->price > 2 + $estimated_public_market_bushel_sell_price) {
				$expensive_bushels_on_market = true;				
			}
		}
		elseif(substr($market_package_piece->type, 0, 2) == 't_') {
			if ($market_package_piece->price > $market_autobuy_tech_price) {
				$expensive_tech_on_market = true;				
			}
		}
	}
	if($expensive_bushels_on_market)
		log_country_message($cnum, "Country has expensive bushels on market");
	//log_country_message($cnum, "Country has expensive tech on market");


	$bushel_recall_needed = false;
	if($expensive_bushels_on_market or ($is_final_destocking_attempt and $bushels_on_market)) {
		$bushel_recall_needed = true;
	}

	// note that we stay logged out for at least 6 turns
	$turns_to_keep = 6; // 1 to sell bushels and 1 to sell military, along with 3 to possibly recall bushels and sell again in later logins
	$tech_recall_needed = false;
	if($strategy == 'T') // one turn to sell tech
		$turns_to_keep += 1;
	if($strategy == 'T' and $market_autobuy_tech_price > $cpref->base_inherent_value_for_tech and $expensive_tech_on_market and !is_there_time_for_selling_tech_at_market_prices($reset_seconds_remaining, $max_market_package_time_in_seconds, $market_autobuy_tech_price, $is_final_destocking_attempt, $cpref)) {
		$turns_to_keep += 3; // probably need to recall tech, so save 3 more turns for that
		log_country_message($cnum, "Country plans to recall tech because there's expensive tech on the market and not enough time to sell at market prices");
		$tech_recall_needed = true;
	}
		
	// TODO: what if selling bushels on private is better and we wouldn't eat corruption? need to figure out how to organize this better...
	$money_to_reserve_temp = $turns_to_keep * max(-1 * $c->income, 10000000); // TODO: this whole thing is gross with the true argument...
	// stash bushels away on public market if needed
	$expect_to_play_turns_for_income = temporary_cash_or_tech_at_end_of_set ($c, $strategy, $turns_to_keep, $money_to_reserve_temp, true);
	if($expect_to_play_turns_for_income and $c->turns >= 20 + $turns_to_keep) {
		if(stash_excess_bushels_on_public_if_needed($c, $rules, $estimated_public_market_bushel_sell_price)) { // sold bushels FUTURE: WATCH RETURN VALUE
			if(!$bushel_recall_needed and !is_there_time_to_sell_on_public($reset_seconds_remaining, $max_market_package_time_in_seconds, $is_final_destocking_attempt, 1800)) {
				$turns_to_keep += 3; // we are going to recall bushels, so save 3 more turns for that
				$bushel_recall_needed = true;
			} 
		}
	}

	$money_to_reserve = $turns_to_keep * max(-1 * $c->income, 10000000); // mil expenses can rise rapidly during destocking, so use 10 M per turn as a guess
	// FUTURE: indies (and maybe other strats) should reserve money to play the rest of the turns they'll get in the set. see $turns_to_keep_for_bushel_calculation
	log_country_message($cnum, "Money is $c->money and calculated money to reserve is $money_to_reserve");
	log_country_message($cnum, "Turns left: $c->turns and turns to keep is $turns_to_keep");
	log_country_message($cnum, "Starting cashing or teching...");	
	$was_playing_turns_profitable = temporary_cash_or_tech_at_end_of_set ($c, $strategy, $turns_to_keep, $money_to_reserve);
	log_country_message($cnum, "Finished cashing or teching");

	// FUTURE: switch governments if that would help???
	
	// recall bushels if needed - TODO: function
	if($bushel_recall_needed) {
		if($c->turns < 3 and $is_final_destocking_attempt)
			log_error_message(120, $cnum, "Could not recall bushels on final destock attempt because turn count is $c->turns");
		else { // have enough turns		
			if(food_and_money_for_turns($c, 3, $money_to_reserve, false)) { // now has get money and food for 3 turns
				log_country_message($cnum, "Recalling bushels because we think the price is too high");
				$turn_result = recall_goods();
				update_c($c, $turn_result);		
			}
			elseif($is_final_destocking_attempt) {
				$money_needed_for_recall = $money_to_reserve + 3 * min(0, $c->income) + 80 * max(0, get_food_needs_for_turns(3, $c->foodpro, $c->foodcon, true) - $c->food);
				if(emergency_sell_mil_on_pm ($c, $money_needed_for_recall)) { // try to force recall if final destocking attempt
					if(food_and_money_for_turns($c, 3, $money_to_reserve, false)) { // now has get money and food for 3 turns
						log_country_message($cnum, "Recalling bushels because this is the final destock attempt");
						$turn_result = recall_goods();
						update_c($c, $turn_result);	
					}	
				}
				else
					log_error_message(120, $cnum, "Could not recall bushels on final destock attempt. Money: $c->money, food: $c->food");
			}
		}
	}
	else // make sure everything is correct because destocking is important (goods recall forces advisor update)
		$c = get_advisor();


	$pm_info = PrivateMarket::getInfo();
	$private_market_bushel_price = $pm_info->sell_price->m_bu;	

	log_country_message($cnum, "Attempting to dump bushel stock...");
	// keep bushels to keep running future turns if it's profitable to do so
	$turns_to_keep_for_bushel_calculation = ($was_playing_turns_profitable ? min($rules->maxturns, $turns_left_in_set) : $turns_to_keep);
	log_country_message($cnum, "Turns of bushels to keep is: $turns_to_keep_for_bushel_calculation");
	$current_public_market_bushel_price = PublicMarket::price('m_bu');
	log_country_message($cnum, "Current private market food price is $private_market_bushel_price");
	log_country_message($cnum, "Current public market food price is $current_public_market_bushel_price");
	dump_bushel_stock($c, $turns_to_keep_for_bushel_calculation, $reset_seconds_remaining, $server_seconds_per_turn, $max_market_package_time_in_seconds, $private_market_bushel_price, $estimated_public_market_bushel_sell_price, $sold_bushels_on_public, $is_final_destocking_attempt);
	log_country_message($cnum, "Done dumping bushel stock");

	// resell bushels if profitable
	$should_attempt_bushel_reselling = can_resell_bushels_from_public_market ($private_market_bushel_price, $c->tax(), $current_public_market_bushel_price, $max_profitable_public_market_bushel_price);
	log_country_message($cnum, "Decision on attempting public market bushel reselling: ".log_translate_boolean_to_YN($should_attempt_bushel_reselling));
	if ($should_attempt_bushel_reselling) {
		do_public_market_bushel_resell_loop ($c, $max_profitable_public_market_bushel_price);
		log_country_message($cnum, "Done with bushel reselling");
	}

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
			buyout_up_to_private_market_unit_dpnw ($c, $cpref, $pm_info->buy_price->$military_unit, $military_unit, $max_spend);
		}

		// estimate future private market unit generation	
		// if this is the final attempt then there's no reason to save money for future PM generation
		$future_pm_capacity_for_unit = ($is_final_destocking_attempt ? 0: estimate_future_private_market_capacity_for_military_unit($pm_info->buy_price->$military_unit, $c->land, $replenishment_rate, $reset_seconds_remaining, $server_seconds_per_turn));
		log_country_message($cnum, "Adjusting budget down by $future_pm_capacity_for_unit which is future PM capacity for $military_unit");
		$total_cost_to_buyout_future_private_market += $future_pm_capacity_for_unit;
		// TODO: should max spend be adjusted if there's lots of food on the market?
		$max_spend = max($c->money - $total_cost_to_buyout_future_private_market - $money_to_reserve, 0);
	}

	log_country_message($cnum, "Completed all PM purchasing. Money is $c->money and budget is $max_spend");
	log_country_message($cnum, "Estimated future PM gen capacity is $total_cost_to_buyout_future_private_market dollars");
	
	$did_resell_military = false;
	// check if this is our last shot at destocking
	if($is_final_destocking_attempt) {		
		// note: a human would recall all military goods here, but I don't care if bots lose NW at the end if it allows a human to buy something
		
		log_country_message($cnum, "This is the FINAL destock attempt for this country". log_translate_forced_debug($debug_force_final_attempt));
		log_country_message($cnum, "Selling all food and oil (if possible) on private market for any price...");
		final_dump_all_resources($c, $is_oil_on_pm);

		if($c->money > 10000000) {
			log_country_message($cnum, "Buying anything available off public market...");
			buyout_up_to_market_dpnw($c, $cpref, 5500, $c->money, false, false); // buy anything ($10000 tech is 5500 dpnw with 10% tax)
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
			buyout_up_to_market_dpnw($c, $cpref, $max_dpnw, $max_spend, true, true); // don't buy tech, maybe the humans want it
			$money_spent = $money_before_purchase - $c->money;
			log_country_message($c->cnum, "Completed public market purchasing. Budget was $max_spend and spent $money_spent money.");
		}

		// consider putting up military for sale if we have money to play a turn and we expect to have at least 100 M in unspent PM capacity
		// note: no need to reserve money in previous has_money_for_turns call - we reserved money earlier so we could spend turns like this
		$value_of_public_market_goods = floor(get_total_value_of_on_market_goods($c));
		log_country_message($cnum, "Considering military reselling...");
		$did_resell_military = consider_and_do_military_reselling($c, $value_of_public_market_goods, $total_cost_to_buyout_future_private_market, $rules->max_possible_market_sell, $reset_seconds_remaining, $max_market_package_time_in_seconds, $is_final_destocking_attempt);

		log_country_message($cnum, "Considering tech sale...");
		dump_tech($c, $strategy, $market_autobuy_tech_price, $rules->max_possible_market_sell, $reset_seconds_remaining, $max_market_package_time_in_seconds, $tech_recall_needed, $is_final_destocking_attempt, $cpref);
	}
	
	// calculate next play time
	if($did_resell_military or $sold_bushels_on_public) {
		$exit_condition = 'WAIT FOR MARKET SALE';
		$next_play_time_in_seconds = $max_market_package_time_in_seconds;
	}
	else
		$next_play_time_in_seconds = TURNS_TO_PASS_BEFORE_NEXT_DESTOCK_ATTEMPT * $server_seconds_per_turn;
	
	//log_country_message($cnum, "Will next login in $next_play_time_in_seconds seconds");
	return $c;
}


function is_there_time_for_selling_tech_at_market_prices ($reset_seconds_remaining, $max_market_package_time_in_seconds, $market_autobuy_tech_price, $is_final_destocking_attempt, $cpref) {
	if ($is_final_destocking_attempt)
		return false;
	if($market_autobuy_tech_price <= $cpref->base_inherent_value_for_tech)
		return is_there_time_to_sell_on_public($reset_seconds_remaining, $max_market_package_time_in_seconds, $is_final_destocking_attempt, 0);
	else
		return ($reset_seconds_remaining >= ($max_market_package_time_in_seconds * 5) ? true : false);
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
	$reset_seconds_remaining, $max_market_package_time_in_seconds, $tech_recall_needed
*/
function dump_tech(&$c, $strategy, $market_autobuy_tech_price, $server_max_possible_market_sell, $reset_seconds_remaining, $max_market_package_time_in_seconds, $tech_recall_needed, $is_final_destocking_attempt, $cpref) {

	$turns_needed = 1;
	// if it can't sell at market prices, it still might be able to sell at autobuy
	$can_sell_at_market_prices = is_there_time_for_selling_tech_at_market_prices($reset_seconds_remaining, $max_market_package_time_in_seconds, $market_autobuy_tech_price, $is_final_destocking_attempt, $cpref);

	if($strategy == 'T' and $tech_recall_needed) { // not worth the effort to check the value of the stuck tech
		log_country_message($c->cnum, "Getting close to the end of the set with expensive tech on market, so try to recall tech");
		$turns_needed += 3;
	}

	// I don't care if a country could sell a tech but not recall tech - ok to not do anything and try again later
	$food_needed = max(0, get_food_needs_for_turns($turns_needed, $c->foodpro, $c->foodcon, true) - $c->food);

	// demos should keep 42 mil tech per acre for bushel recycle reasons, others keep 10 for buying PM
	$mil_tech_to_keep = min($c->t_mil, ($c->govt == 'D' ? 42 : 10) * $c->land);

	$reason_for_not_selling_tech = null;
	if ($strategy <> 'T')
		$reason_for_not_selling_tech = "No support for non-techers at this time"; // FUTURE: add support
	elseif(!buy_full_food_quantity_if_possible($c, $food_needed, 80, 0))
		$reason_for_not_selling_tech = "Not enough food";
	elseif(!has_money_for_turns($turns_needed, $c->money, $c->taxes, $c->expenses, 0, true)) // note the NOT
		$reason_for_not_selling_tech = "Not enough money to play turns";			
	elseif($c->turns < $turns_needed)
		$reason_for_not_selling_tech = "Not enough turns";	
	elseif(!is_there_time_to_sell_on_public($reset_seconds_remaining, $max_market_package_time_in_seconds, $is_final_destocking_attempt, 0))
		$reason_for_not_selling_tech = "Not enough time for goods to get to market";	
	elseif(total_cansell_tech($c, $server_max_possible_market_sell, $mil_tech_to_keep) < 10000 ) // in place of a price check for normal sales
		$reason_for_not_selling_tech = "Minimum allowed tech sale during destocking is 10000 units";			
			
	// passed all error checks, so try to sell tech
	if($reason_for_not_selling_tech == null) {
		if($tech_recall_needed) {
			// we already checked money, food, and turns, so go ahead and recall tech...
			$turn_result = recall_tech();
			update_c($c, $turn_result);
		}

		if ($mil_tech_to_keep) {
			log_country_message($c->cnum, "With $c->land acres and $c->t_mil mil tech points, a ".($c->govt == 'D' ? 'demo' : 'non-demo')." should keep $mil_tech_to_keep mil tech");
		}

		// if there's time in the set or if auto buy prices are bad, do a normal tech sale
		$dump_at_min_sell_price = ($can_sell_at_market_prices or $market_autobuy_tech_price <= $cpref->base_inherent_value_for_tech) ? false : true;
		$allow_average_prices = false;
		$tech_price_history = [];
		// if not dumping tech at min prices, we have the option to sell by average price
		if(!$dump_at_min_sell_price) {
			$allow_average_prices = true;
			$tech_price_history = get_market_history_all_tech($c->cnum, $cpref);
		}

		$turn_result = sell_max_tech($c, $cpref, $market_autobuy_tech_price, $server_max_possible_market_sell, $mil_tech_to_keep, $dump_at_min_sell_price, $allow_average_prices, $tech_price_history);
		update_c($c, $turn_result);
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
function consider_and_do_military_reselling(&$c, $value_of_public_market_goods, $total_cost_to_buyout_future_private_market, $server_max_possible_market_sell, $reset_seconds_remaining, $max_market_package_time_in_seconds, $is_final_destocking_attempt) {
	log_country_message($c->cnum, "Money is $c->money, on-market value is $value_of_public_market_goods, and future PM gen is $total_cost_to_buyout_future_private_market");
	$target_sell_amount = max(0, $total_cost_to_buyout_future_private_market - $value_of_public_market_goods - $c->money);
	log_country_message($c->cnum, "Future free PM military capacity is estimated at $target_sell_amount dollars");
	$reason_for_not_reselling_military = null;

	if(!is_there_time_to_sell_on_public($reset_seconds_remaining, $max_market_package_time_in_seconds, $is_final_destocking_attempt, 300))
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

	$unit_types = array('m_tu' => 1.0, 'm_j' => 1.0, 'm_ta' => 1.06, 'm_tr' => 1.09); // based on dpnw compared to 2025/6.5
	foreach($unit_types as $unit_type =>$min_price_jump) {
		$pm_price = $pm_info->buy_price->$unit_type;
		// FUTURE: prices could be smarter, especially for techers with long destock periods
		// we're basically just throwing military up at a somewhat expensive price
		$price_increase = $min_price_jump * $c->tax() + 0.01 * mt_rand(5, 25); // still basic, but a bit better than before
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
	$incoming_money_per_turn - net income for country, already factors in cashing or not
	$tpt - tech per turn for country
	$foodnet - net food change per turn for country	
	$govt - the one letter government abbreviation
	$land - acres for the country
	$mil_tech - points of mil tech for the country
*/
function temporary_check_if_cash_or_tech_is_profitable ($cnum, $strategy, $incoming_money_per_turn, $tpt, $foodnet, $govt, $land, $mil_tech) {
	// very rough calculations - don't care if this is inaccurate
	// future - account for tech allies?
	if ($strategy == 'I') {
		log_country_message($cnum, "Cashing turns because indies always play turns (this is a limitation)");
		return true; // future: calc indy production to check something
	}
	elseif ($strategy == 'T' and $govt == 'D' and $mil_tech / $land < 42) {// demo techers should get enough mil tech to clear bushels
		log_country_message($cnum, "Teching turns because country is a demo does not have 42 mil tech per acre");
		return true;
	}
	elseif ($strategy == 'T') {
		//log_country_message($cnum, "Teching turns because income is positive with implied tech value of $1500 per point");
		return ($incoming_money_per_turn + 1500 * $tpt + 34 * $foodnet > 0 ? true: false); // TODO: get a tech sell price somehow
	}
	else
		return ($incoming_money_per_turn + 34 * $foodnet > 0 ? true: false);
}


/*
NAME: temporary_cash_or_tech_at_end_of_set
PURPOSE: cashes or techs turns for a destocking country - code should probably be moved out of here at some point
RETURNS: true if running turns was profitable, false otherwise
PARAMETERS:
	$c - the country object
	$strategy - single letter strategy name abbreviation
	$turns_to_keep - keep at least these many turns
	$money_to_reserve - stop running turns if we think we'll end up with less money than this
	$should_play_turns - output parameter set to true if playing turns are profitable
	$profit_check_only - pass this in if you just want to see if turns would be profitable without playing them
*/
function temporary_cash_or_tech_at_end_of_set (&$c, $strategy, $turns_to_keep, $money_to_reserve, $profit_check_only = false) {
	// FUTURE: this code is overly simple and shouldn't exist here - it should call standard code used for teching and cashing
	// in this code rainbows cash because I expect rainbows to not be techers in the future - Slagpit 20210325
	$is_cashing = ($strategy == 'T' ? false : true);
	$incoming_money_per_turn = ($is_cashing ? 1.0 : 1.2) * $c->taxes - $c->expenses;

	$should_play_turns = temporary_check_if_cash_or_tech_is_profitable($c->cnum, $strategy, $incoming_money_per_turn, $c->tpt, $c->foodnet, $c->govt, $c->land, $c->t_mil);
	if(!$should_play_turns and !$profit_check_only) {
		log_country_message($c->cnum, "Not cashing or teching turns because playing turns is not expected to be profitable");
		return false;
	}

	if($profit_check_only)
		return $should_play_turns;
	
	$turns_to_play_at_once = 10;
	while($c->turns > $turns_to_keep) {
		// first try to play in blocks of 10 turns, if that fails then go to turn by turn
		// FUTURE: a farmer can sell on private and should end up being able to cash 10X turn at a time
		// FUTURE: shouldn't farmers avoid decay?
		// FUTURE: a casher can get stopped from playing turns if it doesn't have enough food for a turn and has less money than the reserve figure
		if($c->turns - $turns_to_keep < 10)
			$turns_to_play_at_once = 1;

		if(!food_and_money_for_turns($c, $turns_to_play_at_once, $money_to_reserve, $is_cashing)){
			if($turns_to_play_at_once == 10) {
				$turns_to_play_at_once = 1;
				continue;
			}
			else
				break; // couldn't get money or food for playing a single turn, so stop
		}

		// we should have enough food and money to play turns
		if ($strategy == 'T') {
			$turn_result = tech(['mil' => $turns_to_play_at_once * $c->tpt]); // TODO: is it really ok to just tech mil tech?
		}
		else {
			$turn_result = cash($c, $turns_to_play_at_once);
		}
		update_c($c, $turn_result);
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
function buyout_up_to_private_market_unit_dpnw(&$c, $cpref, $pm_buy_price, $unit_type, $max_spend) {
	// FUTURE: error checking on $unit_type
	
	$unit_to_nw_map = array("m_tr" => 0.5, "m_j" => 0.6, "m_tu" => 0.6, "m_ta" => 2.0); // FUTURE: this is stupid
	$pm_unit_nw = $unit_to_nw_map[$unit_type];
	$max_dpnw = floor(($pm_buy_price / $pm_unit_nw)); // buyout_up_to_market_dpnw deals with market commissions

	$c = get_advisor();	// money must be correct because we get an error if we try to buy too much

	$money_before_purchase = $c->money;
	buyout_up_to_market_dpnw($c, $cpref, $max_dpnw, $max_spend, true, false); // buy off private too
	$money_spent = $money_before_purchase - $c->money;
	$max_spend -= $money_spent;
	log_country_message($c->cnum, "Spent $money_spent money on public and private market military <= $max_dpnw dpnw (on $unit_type pm iterations)");

	/*
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
	*/
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
	$min_dpnw = 3 + floor(($private_market_turret_price / 0.6)); // always buy better than private turret price	
	$max_dpnw = max($min_dpnw + 10, 500 - 2 * floor($reset_seconds_remaining / $server_seconds_per_turn)); // FUTURE: this is stupid and players who read code can take advantage of it
	$std_dev = ($max_dpnw - $min_dpnw) / 2;

	// FUTURE: half the time we won't buy anything because the dpnw will equal pm turrets - is that ok?

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
	// 120 second buffer because bots don't play in the final minute of a reset
	return (($reset_seconds_remaining - 120) / $server_seconds_per_turn < TURNS_TO_PASS_BEFORE_NEXT_DESTOCK_ATTEMPT ? true : false);
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
function dump_bushel_stock(&$c, $turns_to_keep, $reset_seconds_remaining, $server_seconds_per_turn, $max_market_package_time_in_seconds, $private_market_bushel_price, $estimated_public_market_bushel_sell_price, &$sold_bushels_on_public, $is_final_destocking_attempt) {
	// decide if should sell on public or private
	$sold_bushels_on_public = false;
	$sold_on_private_reason = null;

	if(!is_there_time_to_sell_on_public($reset_seconds_remaining, $max_market_package_time_in_seconds, $is_final_destocking_attempt, 1800)) {
		$sold_on_private_reason = 'not enough time';
	}
	
	if($estimated_public_market_bushel_sell_price * (2 - $c->tax()) < 1.5 + $private_market_bushel_price) {
		$sold_on_private_reason = 'can get more money on private';
	}

	if($c->turns == 0) {
		$sold_on_private_reason = 'no turns';
	}

	// not factoring in expenses, but who cares?

	if($sold_on_private_reason) {
		log_country_message($c->cnum, "Not selling bushels on public for reason: $sold_on_private_reason");
		$bushels_to_sell = max(0, $c->food - get_food_needs_for_turns($turns_to_keep, $c->foodpro, $c->foodcon, false));
		while ($bushels_to_sell > 0) { // while loop because bushel decay is factored into consumption now
			PrivateMarket::sell_single_good($c, 'm_bu', $bushels_to_sell);
			$c = get_advisor(); // can't use UpdateMain() here because it doesn't update $c->foodcon
			$bushels_to_sell = max(0, $c->food - get_food_needs_for_turns($turns_to_keep, $c->foodpro, $c->foodcon, false));
			if($bushels_to_sell < 3000000)
				break;
		}
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

	$bushels_to_sell = $c->food - get_food_needs_for_turns($turns_to_keep, $c->foodpro, $c->foodcon, false);
	if ($bushels_to_sell < 5000) { // don't bother if below min quantity
		log_country_message($c->cnum, "Possible bushels to sell is $bushels_to_sell which is less than 5000 so don't bother selling");
		return;
	}

	$sold_bushels_on_public = true;	
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
	$is_final_destocking_attempt - is this the last destock attempt (can be set by debug)
	$padding_seconds - additional seconds to add after the max time. for example, use 1800 if we are selling bushels and we expect it to take half an hour for bushels to be cleared
*/
function is_there_time_to_sell_on_public($reset_seconds_remaining, $max_market_package_time_in_seconds, $is_final_destocking_attempt, $padding_seconds = 3600) {
	if($is_final_destocking_attempt)
		return false;
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