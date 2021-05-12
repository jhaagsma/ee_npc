<?php
/**
 * Farmer strategy
 *
 * PHP Version 7
 *
 * @category Strat
 *
 * @package EENPC
 *
 * @author Julian Haagsma <jhaagsma@gmail.com>
 *
 * @license All files licensed under the MIT license.
 *
 * @link https://github.com/jhaagsma/ee_npc
 */

namespace EENPC;

/**
 * Play the farmer strat
 *
 * @param  ?? $server Contains the server information
 *
 * @return null
 */
function play_farmer_strat($server, $cnum, $rules, $cpref, &$exit_condition, &$turn_action_counts)
{
    $exit_condition = 'NORMAL';
    //global $cnum;
    //$main = get_main();     //get the basic stats
    //out_data($main);          //output the main data
    $c = get_advisor();     //c as in country! (get the advisor)
    log_static_cpref_on_turn_0 ($c, $cpref);

    $starting_turns = $c->turns;
    $is_allowed_to_mass_explore = is_country_allowed_to_mass_explore($c, $cpref);
    log_country_message($cnum, "Agri: {$c->pt_agri}%; Bus: {$c->pt_bus}%; Res: {$c->pt_res}%; Mil: {$c->pt_mil}%; Weap: {$c->pt_weap}%");
    
    $food_price_history = get_market_history_food($cnum, $cpref);

    $c->setIndy('pro_spy');

    if ($c->m_spy > 10000) {
        Allies::fill($cpref, 'spy');
    }

    farmer_switch_government_if_needed($c);

    $buying_schedule = $cpref->purchase_schedule_number;
    $buying_priorities = farmer_get_buying_priorities ($cnum, $buying_schedule);
    $tech_inherent_value = get_inherent_value_for_tech($c, $rules, $cpref);
    $eligible_techs = ['t_bus', 't_res', 't_agri', 't_mil', 't_weap'];
    $optimal_tech_buying_array = get_optimal_tech_buying_array($c, $rules, $eligible_techs, $buying_priorities, $cpref->tech_max_purchase_price, $tech_inherent_value);
    $cost_for_military_point_guess = get_cost_per_military_points_for_caching($c);
    $dpnw_guess = get_dpnw_for_caching($c);
    $money_to_keep_after_stockpiling = $cpref->target_cash_after_stockpiling;
    get_stockpiling_weights_and_adjustments ($stockpiling_weights, $stockpiling_adjustments, $c, $server, $rules, $cpref, $money_to_keep_after_stockpiling, false, true, true);
    $bushel_min_sell_price = get_farmer_min_sell_price($c, $cpref, $rules, $server);
    $bushel_max_sell_price = get_farmer_max_sell_price($c, $cpref, $rules, $server);

    // log useful information about country state
    log_country_message($cnum, $c->turns.' turns left');
    //log_country_message($cnum, 'Explore Rate: '.$c->explore_rate.'; Min Rate: '.$c->explore_min);
    //$market_info = get_market_info();   //get the Public Market info
    //out_data($market_info);       //output the PM info

    $owned_on_market_info = get_owned_on_market_info();     //find out what we have on the market
    //out_data($owned_on_market_info);  //output the Owned on Public Market info

    attempt_to_recycle_bushels_but_avoid_buyout($c, $cpref, $food_price_history);

    // FUTURE: what if we have a bunch of bushels that we plan to sell on PM?
    if($c->money > 2000000000) { // try to stockpile to avoid corruption and to limit bot abuse
        // first spend extra money normally so we can buy needed military or income techs if they are worthwhile
        spend_extra_money($c, $buying_priorities, $cpref, $money_to_keep_after_stockpiling, false, $cost_for_military_point_guess, $dpnw_guess, $optimal_tech_buying_array, $buying_schedule);
        spend_extra_money_on_stockpiling($c, $cpref, $money_to_keep_after_stockpiling, $stockpiling_weights, $stockpiling_adjustments);
    }

    $turn_action_counts = [];
    if($c->money > 1000000000) {// only called here to keep returned bushels on the market at high prices if we have a lot of cash
        $possible_turn_result = stash_excess_bushels_on_public_if_needed($c, $rules);
        if($possible_turn_result) {
            $action_and_turns_used = update_c($c, $possible_turn_result);
            update_turn_action_array($turn_action_counts, $action_and_turns_used);
        }
    }
    
    $turns_played_for_last_spend_money_attempt = 0;
    while ($c->turns > 0) {
        //$result = PublicMarket::buy($c,array('m_bu'=>100),array('m_bu'=>400));
        $result = play_farmer_turn($c, $cpref, $rules, $is_allowed_to_mass_explore, $bushel_min_sell_price, $bushel_max_sell_price, $food_price_history);
        if ($result === false) {  //UNEXPECTED RETURN VALUE
            $c = get_advisor();     //UPDATE EVERYTHING
            continue;
        }

        $action_and_turns_used = update_c($c, $result);
        update_turn_action_array($turn_action_counts, $action_and_turns_used);

        if (!$c->turns % 5) {                   //Grab new copy every 5 turns
            $c->updateMain(); //we probably don't need to do this *EVERY* turn
        }

        // FUTURE: sell food when needed even if income is positive, such as not enough money to build
        if ($c->income < 0 && $c->money < -5 * $c->income) { //sell 1/4 of all military on PM
            log_country_message($cnum, "Almost out of money! Sell 10 turns of income in food!");

            //sell 1/4 of our military
            $pm_info = PrivateMarket::getRecent();
            PrivateMarket::sell($c, ['m_bu' => min($c->food, floor(-10 * $c->income / $pm_info->sell_price->m_bu))]);
        }
        
        // management is here to make sure that food is sold
        $hold = money_management($c, $rules->max_possible_market_sell, $cpref, $turn_action_counts);
        if ($hold) {
              break; //HOLD TURNS HAS BEEN DECLARED; HOLD!!            
        }

        $hold = food_management($c, $cpref);
        if ($hold) {
            $exit_condition = 'WAIT_FOR_PUBLIC_MARKET_FOOD'; // ???
            break; //HOLD TURNS HAS BEEN DECLARED; HOLD!!            
        }

        if (turns_of_money($c) > 50
            && $c->money > 3500 * 500
            && ($c->money > floor(0.9*$c->fullBuildCost()))
        ) {
            if ($c->turns_played >= $turns_played_for_last_spend_money_attempt + $cpref->spend_extra_money_cooldown_turns) { // wait some number of turns before trying again
                spend_extra_money($c, $buying_priorities, $cpref, floor(0.9*$c->fullBuildCost()), true, $cost_for_military_point_guess, $dpnw_guess, $optimal_tech_buying_array, $buying_schedule);
                $turns_played_for_last_spend_money_attempt = $c->turns_played;
            }
        }
    }

    // buy military at the end
    if (turns_of_money($c) > 30 // try to spend something if we get unlucky
            && $c->money > 3500 * 500
            && ($c->money > floor(0.9*$c->fullBuildCost()))
        ) {
        spend_extra_money($c, $buying_priorities, $cpref, floor(0.9*$c->fullBuildCost()), false, $cost_for_military_point_guess, $dpnw_guess, $optimal_tech_buying_array, $buying_schedule);
    }
    else { // farmers can get caught in a pattern where they have enough cash for military, do a big mass explore, but now their building costs are too high so they can't buy military
        spend_extra_money($c, $buying_priorities, $cpref, floor(0.10 * $c->money), false, $cost_for_military_point_guess, $dpnw_guess, $optimal_tech_buying_array, $buying_schedule);
    }

    if($c->money > 2000000000) { // try to stockpile to avoid corruption and to limit bot abuse
        spend_extra_money_on_stockpiling($c, $cpref, $money_to_keep_after_stockpiling, $stockpiling_weights, $stockpiling_adjustments);
        // don't see a strong reason to sell excess bushels at this step
    }

    if($exit_condition == 'NORMAL' && $starting_turns > 30 && ($starting_turns - $c->turns) < 0.3 * $starting_turns)
        $exit_condition = 'LOW_TURNS_PLAYED'; 

    //$c->countryStats(FARMER);
    return $c;
}//end play_farmer_strat()


function play_farmer_turn(&$c, $cpref, $rules, $is_allowed_to_mass_explore, $bushel_min_sell_price, $bushel_max_sell_price, $food_price_history)
{
    $target_bpt = $cpref->initial_bpt_target;
    global $turnsleep;
    usleep($turnsleep);

    if ($c->turns_played <= 99) {
        return play_farmer_turn_first_99_turns($c, $cpref, $target_bpt);
    } elseif ($c->land < 1800) {
        return play_farmer_turn_first_1800_acres($c, $cpref, $rules, $target_bpt, $bushel_min_sell_price, $bushel_max_sell_price, $food_price_history);
    } elseif ($c->shouldBuildSingleCS($target_bpt)) {
        //LOW BPT & CAN AFFORD TO BUILD
        //build one CS if we can afford it and are below our target BPT
        return Build::cs(); //build 1 CS
    } elseif (
            $c->protection == 0 && $c->food > 7000
            && (
                $c->foodnet > 0 && $c->get_foodnet_no_decay() > 3 * $c->get_foodcon_no_decay() && $c->food > 30 * $c->get_foodnet_no_decay() //Don't sell less than 30 turns of food unless you're on your last turn (and desperate?)
                || $c->turns == 1
        )
    ) { 
        // sell some food on PM if we have a lot of empty acres, still have turns, and can't afford to build at least 1k acres
        if($c->turns > (1000 / $c->bpt) && $c->empty >= 1000 && $c->money < 1000 * $c->build_cost) {        
            log_country_message($c->cnum, "Selling food on PM because we have over 999 empty acres and not enough money to build them");    
            $pm_info = PrivateMarket::getInfo();
            $food_to_sell = ceil(min($c->food, 1000 * $c->build_cost / $pm_info->sell_price->m_bu));
            return PrivateMarket::sell_single_good($c, 'm_bu', $food_to_sell);
        }
        else
            return sellextrafood_farmer($c, $rules, $bushel_min_sell_price, $bushel_max_sell_price, $cpref, $food_price_history);
    } elseif ($c->shouldBuildSpyIndies($target_bpt)) {
        //build a full BPT of indies if we have less than that, and we're out of protection
        return Build::indy($c);
    } elseif ($c->shouldBuildFullBPT($target_bpt)) {
        //build a full BPT if we can afford it
        return Build::farmer($c);
    } elseif ($c->shouldBuildFourCS($target_bpt)) {
        //build 4CS if we can afford it and are below our target BPT (65)
        return Build::cs(4); //build 4 CS
    } elseif ($c->built() > 50) {  //otherwise... explore if we can
        $explore_turn_limit = $is_allowed_to_mass_explore ? 999 : $cpref->spend_extra_money_cooldown_turns;
        return explore($c, max(1,min($explore_turn_limit, $c->turns - 1, turns_of_money($c) - 4)));
    } elseif($c->food > 0) { // better to sell on PM than cash
        log_country_message($c->cnum, "Selling food on PM in an attempt to avoid cashing turns");
        return PrivateMarket::sell_single_good($c, 'm_bu', $c->food);
    } else { //otherwise...  cash :(
        return cash($c); // FUTURE - hold turns instead if food is on market?
    }
}//end play_farmer_turn()



function play_farmer_turn_first_1800_acres (&$c, $cpref, $rules, $target_bpt, $bushel_min_sell_price, $bushel_max_sell_price, $food_price_history) {
    if($c->food > 0 && $c->b_farm > 0 && $c->empty >= $c->bpt && $c->money < $c->bpt * $c->build_cost) {
        // sell if we don't have enough cash to build a bpt of farms
        $pm_info = PrivateMarket::getInfo(); // FUTURE - cache?
        $food_to_sell = ceil(min($c->food, $c->bpt * $c->build_cost / $pm_info->sell_price->m_bu));
        return PrivateMarket::sell_single_good($c, 'm_bu', $c->food);
    } elseif (
           $c->protection == 0
        && $c->food > 7000
        && (
            $c->foodnet > 0 && $c->get_foodnet_no_decay() > 3 * $c->get_foodcon_no_decay() && $c->food > 30 * $c->get_foodnet_no_decay() //Don't sell less than 30 turns of food unless you're on your last turn (and desperate?)
            || $c->turns == 1
        )
    ) { 
        return sellextrafood_farmer($c, $rules, $bushel_min_sell_price, $bushel_max_sell_price, $cpref, $food_price_history);
    } elseif ($c->shouldBuildFullBPT($target_bpt)) {
        //build a full BPT if we can afford it
        return Build::farmer($c);
    } elseif ($c->shouldBuildFourCS($target_bpt)) {
        //build 4CS if we can afford it and are below our target BPT (65)
        return Build::cs(4); //build 4 CS
    } elseif ($c->built() > 50) {  //otherwise... explore if we can
        $explore_turn_limit = 2;
        return explore($c, max(1,min($explore_turn_limit, $c->turns - 1, turns_of_money($c) - 4)));
    } elseif($c->food > 0) { // better to sell on PM than cash
        log_country_message($c->cnum, "Selling all food on PM in an attempt to avoid cashing turns");
        return PrivateMarket::sell_single_good($c, 'm_bu', $c->food);
    } else { //otherwise...  cash :(
        return cash($c); // FUTURE - hold turns instead if food is on market?
    }
} // play_farmer_turn_first_1800_acres


function play_farmer_turn_first_99_turns (&$c, $cpref, $target_bpt) {
    if($c->food > 0 && $c->b_farm > 0) {
        return PrivateMarket::sell_single_good($c, 'm_bu', $c->food);
    } elseif($c->land < 600 && $c->built() > 50) {
        // always explore when possible early on to get more income
        return explore($c, 1);
    } elseif ($c->shouldBuildSingleCS($target_bpt, 20)) {
        return Build::cs(); //build 1 CS
    } elseif ($c->shouldBuildFullBPT($target_bpt)) {
        //build a full BPT if we can afford it
        return Build::farmer($c);
    } elseif ($c->shouldBuildFourCS($target_bpt)) {
        //build 4CS if we can afford it and are below our target BPT (65)
        return Build::cs(4); //build 4 CS
    } else { //otherwise...  cash (shouldn't ever happen)
        return cash($c);
    }
} // play_farmer_turn_first_99_turns


function sellextrafood_farmer(&$c, $rules, $bushel_min_sell_price, $bushel_max_sell_price, $cpref, $food_price_history)
{
    //log_country_message($c->cnum, "Lots of food, let's sell some!");
    //$market_info = get_market_info(); //get the Public Market info
    //global $market;

    $server_base_pm_bushel_sell_price = $rules->base_pm_food_sell_price;

    $c = get_advisor();     //UPDATE EVERYTHING

    $pm_info = PrivateMarket::getRecent();

    // FUTURE: cpref
    $rmax    = 1.05; //percent
    $rmin    = 0.95; //percent
    $rstep   = 0.001;
    $rstddev = 0.025;
    $max     = $c->goodsStuck('m_bu') ? 0.99 : $rmax;  
    
    $food_public_price = 0;
    if($c->protection == 0){ // don't log this during protection

        if($cpref->get_sell_price_method(false) == 'AVG') {
            $food_public_price = $food_price_history['avg_price'];
            log_country_message($c->cnum, "Picking sell food price based on avg ".($food_public_price ? $food_public_price : '?'). ", min $bushel_min_sell_price, max $bushel_max_sell_price");
        }
        else {
            $food_public_price = PublicMarket::price('m_bu');
            log_country_message($c->cnum, "Picking sell food price based on current ".($food_public_price ? $food_public_price : '?'). ", min $bushel_min_sell_price, max $bushel_max_sell_price");
        }
    }

    // don't dump food at +1 when the market is empty... can end up in a state where demo recyclers keep clearing it
    $food_public_price = $food_public_price ? $food_public_price : $server_base_pm_bushel_sell_price + 8;
    $price   = min($bushel_max_sell_price, max($bushel_min_sell_price, round($food_public_price * Math::purebell($rmin, $max, $rstddev, $rstep))));

    if ($c->protection == 1 || $price <= $pm_info->sell_price->m_bu / (2 - $c->tax())) {
        if($c->protection == 1 || $c->money < $cpref->target_cash_after_stockpiling)
            return PrivateMarket::sell_single_good($c, 'm_bu', $c->food);
        else { // stockpile bushel instead if we have a lot of money
            $possible_turn_result = stash_excess_bushels_on_public_if_needed($c, $rules);
            if($possible_turn_result) {
                return $possible_turn_result;
            }
            else { // not enough bushels to stockpile yet
                log_country_message($c->cnum, "Selling a single bushel as a code workaround");
                return PrivateMarket::sell_single_good($c, 'm_bu', 1); // FUTURE: this is an ugly hack to always return a turn result
            }
        }   
    }

    $quantity = ['m_bu' => $c->food];
    $price_array   = ['m_bu' => $price];
    return PublicMarket::sell($c, $quantity, $price_array);
}//end sellextrafood_farmer()


function farmer_switch_government_if_needed(&$c) {
    if ($c->govt == 'M') {
        $rand = rand(0, 100);
        switch ($rand) {
            case $rand < 12:
                Government::change($c, 'D');
                break;
            case $rand < 20:
                Government::change($c, 'I');
                break;
            case $rand < 30:
                Government::change($c, 'R');
                break;
            default:
                Government::change($c, 'F');
                break;
        }
    }
} // farmer_switch_government_if_needed()


function farmer_get_buying_priorities ($cnum, $buying_schedule) {
    return casher_get_buying_priorities ($cnum, $buying_schedule); // same as casher for now
} // farmer_get_buying_priorities()