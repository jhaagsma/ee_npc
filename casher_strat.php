<?php

namespace EENPC;

function play_casher_strat($server, $cnum, $rules, $cpref, &$exit_condition, &$turn_action_counts)
{
    $exit_condition = 'NORMAL';
    //global $cnum;
    //$main = get_main();     //get the basic stats
    //out_data($main);          //output the main data
    $c = get_advisor();     //c as in country! (get the advisor)

    // FUTURE: cash when explore is no longer worth it

    //out_data($c) && exit;             //ouput the advisor data
    log_static_cpref_on_turn_0 ($c, $cpref);

    $is_allowed_to_mass_explore = is_country_allowed_to_mass_explore($c, $cpref);

    sell_initial_troops_on_turn_0($c);

    log_country_message($cnum, "Bus: {$c->pt_bus}%; Res: {$c->pt_res}%; Mil: {$c->pt_mil}%; Weap: {$c->pt_weap}%");

    $c->setIndy('pro_spy');

    if ($c->m_spy > 10000) {
        Allies::fill($cpref, 'spy', $rules);
    }

    casher_switch_government_if_needed($c);

    // setup for spending extra money (not on food or building costs)
    $buying_schedule = $cpref->purchase_schedule_number;
    $buying_priorities = casher_get_buying_priorities ($cnum, $buying_schedule);
    $tech_inherent_value = get_inherent_value_for_tech($c, $rules, $cpref);
    $eligible_techs = ['t_bus', 't_res', 't_mil', 't_weap'];
    $optimal_tech_buying_array = get_optimal_tech_buying_array($c, $rules, $eligible_techs, $buying_priorities, $cpref->tech_max_purchase_price, $tech_inherent_value);
    $cost_for_military_point_guess = get_cost_per_military_points_for_caching($c);
    $dpnw_guess = get_dpnw_for_caching($c);
    $money_to_keep_after_stockpiling = $cpref->target_cash_after_stockpiling;
    get_stockpiling_weights_and_adjustments ($stockpiling_weights, $stockpiling_adjustments, $c, $server, $rules, $cpref, $money_to_keep_after_stockpiling, true, true, true);

    // log useful information about country state
    log_country_message($cnum, $c->turns.' turns left');
    //log_country_message($cnum, 'Explore Rate: '.$c->explore_rate.'; Min Rate: '.$c->explore_min);

    //$market_info = get_market_info(); //get the Public Market info
    //out_data($market_info);       //output the PM info

    //$owned_on_market_info = get_owned_on_market_info();     //find out what we have on the market


    if($c->money > 1700000000) { // try to stockpile to avoid corruption and to limit bot abuse
        // first spend extra money normally so we can buy needed military or income techs if they are worthwhile
        spend_extra_money($c, $buying_priorities, $cpref, $money_to_keep_after_stockpiling, false, $cost_for_military_point_guess, $dpnw_guess, $optimal_tech_buying_array, $buying_schedule);
        spend_extra_money_on_stockpiling($c, $cpref, $money_to_keep_after_stockpiling, $stockpiling_weights, $stockpiling_adjustments);
    }

    $turn_action_counts = [];
    $possible_turn_result = stash_excess_bushels_on_public_if_needed($c, $rules);
    if($possible_turn_result) {
        $action_and_turns_used = update_c($c, $possible_turn_result);
        update_turn_action_array($turn_action_counts, $action_and_turns_used);
    }
    
    attempt_to_recycle_bushels_but_avoid_buyout($c, $cpref, $food_price_history);
    
    $turns_played_for_last_spend_money_attempt = 0;
    while ($c->turns > 0) {
        $result = play_casher_turn($c, $cpref, $is_allowed_to_mass_explore);
        if (!$result) {  //UNEXPECTED RETURN VALUE
            $c = get_advisor();     //UPDATE EVERYTHING
            continue;
        }

        $action_and_turns_used = update_c($c, $result);
        update_turn_action_array($turn_action_counts, $action_and_turns_used);

        if (!$c->turns % 5) {                   //Grab new copy every 5 turns
            $c->updateMain(); //we probably don't need to do this *EVERY* turn
        }

        $hold = money_management($c, $rules->max_possible_market_sell, $cpref, $turn_action_counts);
        if ($hold) {
            break; //HOLD TURNS HAS BEEN DECLARED; HOLD!!
        }

        $hold = food_management($c, $cpref);
        if ($hold) {
            $exit_condition = 'WAIT_FOR_PUBLIC_MARKET_FOOD';
            break; //HOLD TURNS HAS BEEN DECLARED; HOLD!!
        }

        if (turns_of_food($c) > 40
            && $c->money > 3500 * 500
            && ($c->money > $c->fullBuildCost())
        ) { // 40 turns of food
            if ($c->turns_played >= $turns_played_for_last_spend_money_attempt + $cpref->spend_extra_money_cooldown_turns) { // wait some number of turns before trying again
                spend_extra_money($c, $buying_priorities, $cpref, $c->fullBuildCost(), true, $cost_for_military_point_guess, $dpnw_guess, $optimal_tech_buying_array, $buying_schedule);
                $turns_played_for_last_spend_money_attempt = $c->turns_played;
            }
        }
    }

    // buy military at the end
    if (turns_of_food($c) > 20 // otherwise if a country buys food on the last turn, we end up not buying any military
            && $c->money > 3500 * 500
            && ($c->money > $c->fullBuildCost())
        ) { // 40 turns of food
        // buy military at the end
        spend_extra_money($c, $buying_priorities, $cpref, $c->fullBuildCost(), false, $cost_for_military_point_guess, $dpnw_guess, $optimal_tech_buying_array, $buying_schedule);
    }
    else { // cashers can get caught in a pattern where they have enough cash for military, do a big mass explore, but now their building costs are too high so they can't buy military
        spend_extra_money($c, $buying_priorities, $cpref, floor(0.9 * $c->money), false, $cost_for_military_point_guess, $dpnw_guess, $optimal_tech_buying_array, $buying_schedule);
    }

    if($c->money > 1700000000) { // try to stockpile to avoid corruption and to limit bot abuse
        spend_extra_money_on_stockpiling($c, $cpref, $money_to_keep_after_stockpiling, $stockpiling_weights, $stockpiling_adjustments);
        // don't see a strong reason to sell excess bushels at this step
    }

    //$c->countryStats(CASHER);
    return $c;
}//end play_casher_strat()


function play_casher_turn(&$c, $cpref, $is_allowed_to_mass_explore)
{
    $target_bpt = $cpref->initial_bpt_target;
    global $turnsleep;
    usleep($turnsleep);

    if ($c->land < 1800) {
        return play_casher_turn_first_1800_acres($c, $cpref, $target_bpt);
    } elseif ($c->shouldBuildSingleCS($target_bpt)) {
        //LOW BPT & CAN AFFORD TO BUILD
        //build one CS if we can afford it and are below our target BPT
        return Build::cs(); //build 1 CS
    } elseif ($c->shouldBuildSpyIndies($target_bpt)) {
        //build a full BPT of indies if we have less than that, and we're out of protection
        return Build::indy($c);
    } elseif ($c->shouldBuildFullBPT($target_bpt)) {
        //build a full BPT if we can afford it
        return Build::casher($c);
    } elseif ($c->shouldBuildFourCS($target_bpt)) {
        //build 4CS if we can afford it and are below our target BPT (65)
        return Build::cs(4); //build 4 CS
    } elseif ($c->built() > 50) {
        //otherwise... explore if we can
        $explore_turn_limit = $is_allowed_to_mass_explore ? 999 : $cpref->spend_extra_money_cooldown_turns;
        return explore($c, max(1, min($explore_turn_limit, $c->turns, turns_of_food($c) - 4)));
    } else {
        //otherwise...  cash
        return cash($c);
    }
}//end play_casher_turn()



function play_casher_turn_first_1800_acres (&$c, $cpref, $target_bpt) {
    if($c->land < 600 && $c->built() > 50) {
        // always explore when possible early on to get more income
        return explore($c, 1);
    } elseif ($c->shouldBuildSingleCS($target_bpt, 25)) {
        return Build::cs(); //build 1 CS
    } elseif ($c->shouldBuildFullBPT($target_bpt)) {
        //build a full BPT if we can afford it
        return Build::casher($c);
    } elseif ($c->shouldBuildFourCS($target_bpt)) {
        //build 4CS if we can afford it and are below our target BPT (65)
        return Build::cs(4); //build 4 CS
    } elseif ($c->built() > 50) {  //otherwise... explore if we can
        $explore_turn_limit = 2;
        return explore($c, max(1, min($explore_turn_limit, $c->turns, turns_of_food($c) - 4)));   
    } else { //otherwise...  cash - this shouldn't happen?
        return cash($c);
    }
} // play_casher_turn_first_1800_acres



function casher_get_buying_priorities ($cnum, $buying_schedule) {
    $buying_priorities = [];
    
    if($buying_schedule == 0) { // heavy military
        $buying_priorities = [
            ['type'=>'DPA','goal'=>100],
            ['type'=>'INCOME_TECHS','goal'=>90],
            ['type'=>'NWPA','goal'=>100]
        ];
    }
    elseif($buying_schedule == 1) { // heavy tech
        $buying_priorities = [
            ['type'=>'DPA','goal'=>1],
            ['type'=>'INCOME_TECHS','goal'=>90],
            ['type'=>'DPA','goal'=>100],
            ['type'=>'NWPA','goal'=>100]
        ];
    }
    elseif($buying_schedule == 2) { // favor military
        $buying_priorities = [
            ['type'=>'DPA','goal'=>3],
            ['type'=>'INCOME_TECHS','goal'=>10],
            ['type'=>'DPA','goal'=>60],
            ['type'=>'INCOME_TECHS','goal'=>40],
            ['type'=>'DPA','goal'=>100],
            ['type'=>'INCOME_TECHS','goal'=>90],
            ['type'=>'NWPA','goal'=>100]
        ];
    }
    elseif($buying_schedule == 3) { // favor tech
        $buying_priorities = [
            ['type'=>'DPA','goal'=>2],
            ['type'=>'INCOME_TECHS','goal'=>50],
            ['type'=>'DPA','goal'=>35], 
            ['type'=>'INCOME_TECHS','goal'=>90],
            ['type'=>'DPA','goal'=>100],
            ['type'=>'NWPA','goal'=>100]
        ];
    }
    else {
        log_error_message(999, $cnum, 'casher_get_buying_priorities() invalid parameter $buying_schedule value of:'.$buying_schedule);
    }

    log_country_message($cnum, "Country buying priority order listed below:");
    $priority_number = 0;
    foreach($buying_priorities as $priority) {
        log_country_message($cnum, "    Priority $priority_number: ".$priority['type']." ".$priority['goal']."%");
        $priority_number++;
    }

    return $buying_priorities;
} // casher_get_buying_priorities()

function casher_switch_government_if_needed(&$c) {
    // FUTURE: should obviously call a function with priorities... (same with other strats)
    if ($c->govt == 'M') {
        $rand = rand(0, 100);
        switch ($rand) {
            case $rand < 10:
                Government::change($c, 'I');
                break;
            case $rand < 25:
                Government::change($c, 'D');
                break;
            case $rand < 40:
                Government::change($c, 'H');
                break;
            default:
                Government::change($c, 'R');
                break;
        }
    }
} // casher_switch_government_if_needed()