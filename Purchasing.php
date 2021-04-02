<?php

namespace EENPC;

// TODO: move other purchasing functions here
// TODO: need landgoal function somewhere... call server code for explore acres?

// buy military or tech (future should include stocking bushels?)
function spend_extra_money (&$c, $priority_list, $strat, $cpref, $money_to_reserve, $delay_military_purchases, $cost_for_military_point_guess, $dpnw_guess, $optimal_tech_buying_array = []) {


    $target_dpa = $c->defPerAcreTarget();
    $target_dpnw = 0; // TODO
    // get $goal_nwpa, $goal_dpa from functions

    // TODO: in AI testing, set different schedules based on magic number to compete
    /*
    // example structure of $priority_list:
    $priority_list = [
        ['DPA',10],
        ['INCOME_TECHS',20],
        ['DPA',40],
        ['INCOME_TECHS',40],
        ['DPA',70],
        ['INCOME_TECHS',60],
        ['DPA',100],
        ['INCOME_TECHS',80],
        ['NWPA',100],
        ['INCOME_TECHS',100]
    ];
    */

    // for each item in $priority_list, call buy_tech OR buy_defense_from_markets() OR buy_military_networth_from_markets()
    // buy_tech(&$c, $tech = 't_bus', $max_spend = null, $maxprice = 9999, $tech_limit = 999999999)
    // stop when budget is up

    log_country_message($c->cnum, "Attempting to spend money with $c->money money, $money_to_reserve money to reserve, and schedule 0");

    $delayed_money = 0;
    $total_spent = 0;
    $max_spend = $c->money - $money_to_reserve;
    foreach($priority_list as $priority_item) {
        if($c->money - $delayed_money <= 10000 + $money_to_reserve) {
            log_country_message($c->cnum, "Ran out of money to spend with $c->money money, $money_to_reserve money to reserve, and $delayed_money in delay cost");
            break;
        }

        $priority_type = $priority_item['type'];
        $priority_goal = $priority_item['goal'];
        $total_spent_by_step = 0;
        $step_purchase_was_delayed = false;

        log_country_message($c->cnum, "Next priority is $priority_type with goal $priority_goal");

        if($priority_type == 'DPA') {
            $step_purchase_was_delayed = $delay_military_purchases;
            $total_defense_points_goal = $target_dpa * $priority_goal * $c->land;
            $current_defense_points = $c->defPerAcre() * $c->land;
            $defense_unit_points_needed = max(0, $total_defense_points_goal - $current_defense_points);
            log_country_message($c->cnum, "Def points needed is $defense_unit_points_needed with goal of $total_defense_points_goal and current value of $current_defense_points");
            if($defense_unit_points_needed > 0) {
                $total_spent_by_step = buy_defense_from_markets($c, $cpref, $defense_unit_points_needed, $max_spend, $delay_military_purchases, $cost_for_military_point_guess);
            }
        }
        elseif($priority_type == 'INCOME_TECHS')
            continue; // FUTURE
        elseif($priority_type == 'NWPA')
            continue; // FUTURE
        else
            log_error_message(119, $c->cnum, "Invalid priority type: $priority_type. Allowed values are 'DPA', 'INCOME_TECHS', and 'NWPA'");  
            
        $max_spend -= $total_spent_by_step;
        if($step_purchase_was_delayed)
            $delayed_money += $total_spent_by_step;
        else
            $total_spent += $total_spent_by_step;
    }

    log_country_message($c->cnum, "Completed spending money with $total_spent in total purchases, with $c->money money remaining, $money_to_reserve money to reserve, $delayed_money in delay cost, and schedule 0");
    return true;
}


function get_cost_per_military_points_for_caching() { // parameters?
    return 80; // TODO: implement
}



function buy_defense_from_markets(&$c, $cpref, $defense_unit_points_needed, $max_spend, $delay_military_purchases, $cost_for_military_point_guess = null) {
    if($delay_military_purchases) {
        if($cost_for_military_point_guess == null)
            $cost_for_military_point_guess = 80; // TODO: call get_cost_per_military_points_for_caching
        return $cost_for_military_point_guess * $defense_unit_points_needed;
    }

    // FUTURE: use country preferences
    $unit_weights = ['m_tr'=>0.7, 'm_tu' => 1.0, 'm_ta' => 2.9];
    $unit_points = ['m_tr'=>1, 'm_tu' => 2, 'm_ta' => 4];
    // these weights prioritize jet and turret public buying over private market buying
    // but will hopefully avoid countries with all of one type of unit (depending on public market conditions)
    return spend_money_on_markets($c, $cpref, $defense_unit_points_needed, $max_spend, $unit_weights, $unit_points, "dpdef"); 
}

/* // current unused
function buy_military_points_from_markets(&$c, $cpref, $military_unit_points_needed, $max_spend) {
    // FUTURE: use country preferences
    $unit_weights = ['m_tr'=>0.7, 'm_j' => 0.9, 'm_tu' => 1.0, 'm_ta' => 2.9];
    $unit_points = ['m_tr'=>2, 'm_tu' => 2, 'm_ta' => 2, 'm_ta' => 4];
    return spend_money_on_markets($c, $cpref, $military_unit_points_needed, $max_spend, $unit_weights, $unit_points, "dpmil");   
}
*/

function buy_military_networth_from_markets(&$c, $cpref, $nw_needed, $max_spend) {
    // TODO: add delay

    $unit_weights = ['m_tr'=>0.5, 'm_j' => 0.6, 'm_tu' => 0.6, 'm_ta' => 2.0];
    $unit_points = ['m_tr'=>0.5, 'm_tu' => 0.6, 'm_ta' => 0.6, 'm_ta' => 2.0];
    return spend_money_on_markets($c, $cpref, $nw_needed, $max_spend, $unit_weights, $unit_points, "dpnw");    
}


function buyout_up_to_public_market_dpnw_2(&$c, $cpref, $max_dpnw, $max_spend, $military_units_only) {
    $unit_weights = ['m_tr'=>0.5, 'm_j' => 0.6, 'm_tu' => 0.6, 'm_ta' => 2.0];
    $unit_points = ['m_tr'=>0.5, 'm_tu' => 0.6, 'm_ta' => 0.6, 'm_ta' => 2.0];
	if(!$military_units_only) {	// FUTURE: this is kind of stupid
		$unit_weights["mil"] = 2.0;
		$unit_weights["med"] = 2.0;
		$unit_weights["bus"] = 2.0;
		$unit_weights["res"] = 2.0;
		$unit_weights["agri"] = 2.0;
		$unit_weights["war"] = 2.0;
		$unit_weights["ms"] = 2.0;
		$unit_weights["weap"] = 2.0;
		$unit_weights["indy"] = 2.0;
		$unit_weights["spy"] = 2.0;
		$unit_weights["sdi"] = 2.0;
        $unit_points["mil"] = 2.0;
		$unit_points["med"] = 2.0;
		$unit_points["bus"] = 2.0;
		$unit_points["res"] = 2.0;
		$unit_points["agri"] = 2.0;
		$unit_points["war"] = 2.0;
		$unit_points["ms"] = 2.0;
		$unit_points["weap"] = 2.0;
		$unit_points["indy"] = 2.0;
		$unit_points["spy"] = 2.0;
		$unit_points["sdi"] = 2.0;
	}

    return spend_money_on_markets($c, $cpref, 999999999, $max_spend, $unit_weights, $unit_points, "dpnw", $max_dpnw, true);   
}


// $point_name is for message logging only
function spend_money_on_markets(&$c, $cpref, $points_needed, $max_spend, $unit_weights, $unit_points, $point_name, $max_dollars_per_point = 100000, $public_only = false, $total_spent = 0, $total_points_gained = 0, $recursion_level = 1) {
    // TODO: TEST THIS!!!!!!!!!
    log_country_message($c->cnum, "Iteration $recursion_level for public".($public_only ? "" : " and private")." market purchasing based on $point_name");
    log_country_message($c->cnum, "Budget is ".($max_spend - $total_spent).", purchase limit is ".($points_needed - $total_points_gained)." points, and price limit is $max_dollars_per_point $point_name");

    $pm_purchase_prices_by_unit = [];
    $pm_score_by_unit = [];
    // identify best purchase from private market
    if(!$public_only) {
        $pm_info = PrivateMarket::getInfo();
        foreach($unit_weights as $unit_name => $weight_for_unit) {
            $pm_price = $pm_info->buy_price->$unit_name;
            $pm_quantity = $pm_info->available->$unit_name;
            if ($pm_quantity > 0) {
                $pm_purchase_prices_by_unit[$unit_name] = $pm_price;
                // log_country_message($c->cnum, "unit:$unit_name, price:$pm_price");
                $unit_score = floor($pm_price / $unit_weights[$unit_name]); // slightly favor over public that uses round()
                $pm_score_by_unit[$unit_name] = $unit_score;
                log_country_message($c->cnum, "Iteration $recursion_level initial private market conditions for $unit_name are price $pm_price and weight $unit_score ($point_name)");
            }
            else {
                log_country_message($c->cnum, "Iteration $recursion_level initial private market conditions for $unit_name are nothing on market");
            }
        }
    }

	$public_market_tax_rate = $c->tax();
	// this is written like this to try to limit public market API calls
	$public_purchase_prices_by_unit = [];
	$public_score_by_unit = [];
	foreach($unit_weights as $unit_name => $weight_for_unit) {
		$public_market_price = PublicMarket::price($unit_name);
		if ($public_market_price <> null and $public_market_price <> 0) {
			$public_purchase_prices_by_unit[$unit_name] = $public_market_price;
			// log_country_message($c->cnum, "unit:$unit_name, price:$public_market_price");
			$unit_score = round($public_market_tax_rate * $public_market_price / $unit_weights[$unit_name]);
			$public_score_by_unit[$unit_name] = $unit_score;
			log_country_message($c->cnum, "Iteration $recursion_level initial public market conditions for $unit_name are price $public_market_price and weight $unit_score ($point_name)");
		}
		else {
			log_country_message($c->cnum, "Iteration $recursion_level initial public market conditions for $unit_name are nothing on market");
		}
	}	
	
	$missed_purchases = 0;
	$total_purchase_loops = 0;
	// spend as much money as possible on public market at or below $$max_dollars_per_point
	while ($total_spent + 10000 < $max_spend and $total_purchase_loops < 500 and $total_points_gained < $points_needed) {
		if (empty($public_score_by_unit) and empty($pm_score_by_unit)) // out of stuff to buy?
			break;
			
        // find best public unit to buy
        if(empty($public_score_by_unit)) {
            $best_public_score = 999999999;
        }
        else {
            asort($public_score_by_unit); // get next good to buy, undefined order in case of ties is fine
            reset($public_score_by_unit);
            $best_public_unit = key($public_score_by_unit); // no array_key_first until PHP 7.3
            $best_public_score = $public_score_by_unit[$best_public_unit];
        }

        // find best private unit to buy
        if(empty($pm_score_by_unit)) {
            $best_pm_score = 999999999;
        }
        else {
            asort($pm_score_by_unit);
            reset($pm_score_by_unit);
            $best_pm_unit = key($pm_score_by_unit);
            $best_pm_score = $pm_score_by_unit[$best_pm_unit]; 
        }

        // log_country_message($c->cnum, "pm score: $best_pm_score; public score: $best_public_score; max: $max_dollars_per_point");

        // lower score is better
        if($best_pm_score <= $best_public_score) { // buy off private
            if($best_pm_score > $max_dollars_per_point)
                break; // best unit is too expensive

            $point_per_unit = $unit_points[$best_pm_unit];
            $best_unit_price = $pm_purchase_prices_by_unit[$best_pm_unit];            
    
            $pm_info = PrivateMarket::getInfo(); // need price and quantity to be correct: in theory this will only get called four times at most
            // no need to update prices of other units - they shouldn't have changed much (probably didn't change at all)
            if($pm_info->available->$best_pm_unit == 0)
                unset($pm_score_by_unit[$best_pm_unit]);
            else { 
                $pm_purchase_amount = min(
                    $pm_info->available->$best_pm_unit,
                    floor(min($c->money, $max_spend) / $pm_info->buy_price->$best_pm_unit),
                    ceil(($points_needed - $total_points_gained) / $point_per_unit),
                );
                
                if ($pm_purchase_amount > 0) {
                    $money_before_purchase = $c->money;
                    $unit_count_before_purchase = $c->$best_pm_unit; // FUTURE: should use return value

                    PrivateMarket::buy($c, [$best_pm_unit => $pm_purchase_amount]);

                    $money_spent_on_purchase = $money_before_purchase - $c->money;
                    $total_spent += $money_spent_on_purchase;
                    $units_gained = max(0, $c->$best_pm_unit - $unit_count_before_purchase);
                    $total_points_gained += $point_per_unit * $units_gained;

                    if($pm_info->available->$best_pm_unit == 0)
                        unset($pm_score_by_unit[$best_pm_unit]); // we bought everything
                }

                // realistically shouldn't happen often
                if($money_spent_on_purchase == 0)
                    $c = get_advisor();
            }            
        } // end private buying
        else { // buy off public
            if($best_public_score > $max_dollars_per_point)
                break; // best unit is too expensive
		
            $point_per_unit = $unit_points[$best_public_unit];
            $best_unit_price = $public_purchase_prices_by_unit[$best_public_unit];            

            // deliberately ignoring market quantity because it doesn't matter. attempting to purchase more units than available is not an error
            $best_unit_quantity = min(
                ceil(($points_needed - $total_points_gained) / $point_per_unit),
                floor(($max_spend - $total_spent) / ($public_market_tax_rate * $best_unit_price))
            );
        
            // log_country_message($c->cnum, "Best unit:$best_public_unit, quantity:$best_unit_quantity, price:$best_unit_price ");

            $money_before_purchase = $c->money; // this is repeated because we want it as close as possible to the buy call
            $unit_count_before_purchase = $c->$best_public_unit; // FUTURE: should use return value

            PublicMarket::buy($c, [$best_public_unit => $best_unit_quantity], [$best_public_unit => $best_unit_price]);

            $money_spent_on_purchase = $money_before_purchase - $c->money; // I don't like this but the return structure of PublicMarket::buy is tough to deal with
            $total_spent += $money_spent_on_purchase;
            $units_gained = max(0, $c->$best_public_unit - $unit_count_before_purchase);
            $total_points_gained += $point_per_unit * $units_gained;

            if ($money_spent_on_purchase == 0) {
                $missed_purchases++;
                if ($missed_purchases % 10 == 0) // maybe cash was stolen or an SO was filled, so do an expensive refresh
                    $c = get_advisor();	
            }

            // refresh price
            $new_public_market_price = PublicMarket::price($best_public_unit);
            if ($new_public_market_price == null or $new_public_market_price == 0)
                unset($public_score_by_unit[$best_public_unit]);
            else {			
                $public_purchase_prices_by_unit[$best_public_unit] = $new_public_market_price;
                $public_score_by_unit[$best_public_unit] = round($public_market_tax_rate * $new_public_market_price / $unit_weights[$best_public_unit]);			
            }
        } // end public

		$total_purchase_loops++;
	}

	// some units might have shown up after we last refreshed prices, so call up to 2 more times recursively
	if ($recursion_level < 2 and $total_spent + 10000 < $max_spend and $total_points_gained < $points_needed)
        spend_money_on_markets($c, $cpref, $points_needed, $max_spend, $unit_weights, $unit_points, $point_name, $max_dollars_per_point, $public_only, $total_spent, $total_points_gained, $recursion_level + 1);

    return $total_spent;
}


function get_optimal_tech_buying_array($cnum, $tech_type_to_ipa, $expected_avg_land, $max_tech_price, $max_possible_spend, $base_tech_value) {
    // FUTURE: support weapons tech
    log_country_message($cnum, "Creating optimal tech buying array");

    $optimal_tech_buying_array = [];
    for($key_num = 10; $key_num <= 100; $key_num+=10){
        $optimal_tech_buying_array["turns$key_num"] = [];
    }

    foreach($tech_type_to_ipa as $tech_type => $ipa) {
        if($tech_type <> 't_agri' and $tech_type <> 't_indy' and $tech_type <> 't_bus' and $tech_type <> 't_res' and $tech_type <> 't_mil') {
            log_error_message(117, $cnum, "Invalid tech type value is: $tech_type");
            continue;
        }

        $current_tech_price = PublicMarket::price($tech_type);
        if(!$current_tech_price) {
            log_country_message($cnum, "No $tech_type tech available on public market so skipping optimal tech calculations");
            continue;
        }

        // dump results into the array, further process the array later
        log_country_message($cnum, "Querying server for optimal tech buying results...");
        $res = get_optimal_tech_buying_info ($tech_type, $expected_avg_land, $current_tech_price, $max_tech_price, $max_possible_spend, $ipa, $base_tech_value);
        // no need for additional error handling because comm should handle that
        if(is_array($res)) {
            foreach($res as $turn_bucket => $pq_results) {
                foreach($pq_results as $pq_result) {
                    $optimal_tech_buying_array[$turn_bucket][] = $pq_result;
                }
            }
        }
    }

    // need to further process $optimal_tech_buying_array
    // if a turn bucket is empty remove it. otherwise sort by price (undefined order in case of ties is fine)
    /*   
    // goal is single array with right structure, with $p1 <= $p2 <= ... $p50 and so on
    $result = [
        'turns10' => [1=> ['t'=>'t_agri', 'p' => $p1, 'q' => $q1], 2=> ['t'=>'bus', 'p' => $p2, 'q' => $q2], ... 50=> ['t'=>'bus', 'p' => $p50, 'q' => $q50]],
        'turns20' => [1=> ['t'=>'t_agri', 'p' => $p51, 'q' => $q51], 2=> ['t'=>'bus', 'p' => $p52, 'q' => $q52], ... 40=> ['t'=>'bus', 'p' => $p90, 'q' => $q90]],
        ...
        'turns100' => 1=> ['t'=>'t_agri', 'p' => $p356, 'q' => $q357], ...
    ];
    */

    log_country_message($cnum, "Final processing for optimal tech buying array");
    foreach($optimal_tech_buying_array as $turn_bucket => $pq_results) {
        if(empty($pq_results))
            unset($optimal_tech_buying_array[$turn_bucket]);
        else
            usort($optimal_tech_buying_array[$turn_bucket],
            function ($a, $b) {
                return $a['p'] <=> $b['p']; // spaceship!
            });
    }

    // TODO: some kind of validation or error checking? unfortunately, the array could be empty if prices are too high though...
    log_country_message($cnum, "Array processing complete for optimal tech buying array");
    return $optimal_tech_buying_array;  
    

};






// TODO: build the other end of this
// TODO: add validation in communication.php
function get_optimal_tech_buying_info ($tech_type, $expected_avg_land, $min_tech_price, $max_tech_price, $max_possible_spend, $base_income_per_acre, $base_tech_value) {
    // return a fake array here to use for testing other functions

    $debug_result = [
        'turns10' => [1=> ['t'=>$tech_type, 'p' => 1500, 'q' => 10000], 2=> ['t'=>$tech_type, 'p' => 2500, 'q' => 9000], 3=> ['t'=>$tech_type, 'p' => 3000, 'q' => 8888]],
        'turns20' => [1=> ['t'=>$tech_type, 'p' => 1500, 'q' => 8000], 2=> ['t'=>$tech_type, 'p' => 2500, 'q' => 7777]],
        'turns100' => [1=> ['t'=>$tech_type, 'p' => 1500, 'q' => 6000]]
    ];

    return $debug_result;


    /*
    // result is a multidim array ($p is price, q is quantity)

    $result = [
        'turns10' => [1=> ['t'=>'t_agri', 'p' => $p1, 'q' => $q1], 2=> ['t'=>'t_agri', 'p' => $p2, 'q' => $q2], ... 10=> ['t'=>'t_agri', 'p' => $p10, 'q' => $q10]],
        'turns20' => [1=> ['t'=>'t_agri', 'p' => $p11, 'q' => $q11], 2=> ['t'=>'t_agri', 'p' => $p19, 'q' => $q19], ... 40=> ['t'=>'t_agri', 'p' => $p20, 'q' => $q20]],
        ...
        'turns100' => [1=> ['t'=>'t_agri', 'p' => $57, 'q' => $58]]
    ];


    $result = [
        'turns10' => [$p1 => $q1_1, $p2 => $q2_1, ... $p10 => $q10_1],
        'turns20' => [$p1 => $q1_2, $p2 => $q2_2, ... $p10 => $q10_2],
        ...
        'turns100' => [$p1 => $q1_10, $p2 => $q2_10, ... $p10 => $q10_10],
    ];
    */

    return ee('get_optimal_tech_buying_info', [
        'tech' => $tech_type,
        'avg_land' => $expected_avg_land,
        'min_price' => $min_tech_price,
        'max_price' => $max_tech_price,
        'budget' => $max_possible_spend,
        'ipa' => $base_income_per_acre,
        'tech_value' => $base_tech_value
    ]);
};


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
		if($max_quantity_to_buy_at_once == 0)
			break;

		$previous_food = $c->food;
		$result = PublicMarket::buy($c, ['m_bu' => $max_quantity_to_buy_at_once], ['m_bu' => $current_public_market_bushel_price]);
		// FUTURE: sometimes see an error here with an unexpected price (1 below last) - expected? taxes?
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