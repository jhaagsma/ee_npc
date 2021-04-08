<?php

namespace EENPC;

function play_casher_strat($server, $cnum, $rules, $cpref, &$exit_condition)
{
    $exit_condition = 'NORMAL';
    //global $cnum;
    //$main = get_main();     //get the basic stats
    //out_data($main);          //output the main data
    $c = get_advisor();     //c as in country! (get the advisor)
    //out_data($c) && exit;             //ouput the advisor data
    $is_allowed_to_mass_explore = is_country_allowed_to_mass_explore($c, $cpref, $server);
    log_country_message($cnum, "Bus: {$c->pt_bus}%; Res: {$c->pt_res}%; Mil: {$c->pt_mil}%; Weap: {$c->pt_weap}%");

    $c->setIndy('pro_spy');

    if ($c->m_spy > 10000) {
        Allies::fill('spy');
    }

    casher_switch_government_if_needed($c);

    // setup for spending extra money (not on food or building costs)
    $buying_schedule = casher_get_buying_schedule($cnum, $cpref);
    $buying_priorities = casher_get_buying_priorities ($cnum, $buying_schedule);
    $tech_inherent_value = get_inherent_value_for_tech($c, $rules);
    $eligible_techs = ['t_bus', 't_res', 't_mil', 't_weap'];
    $optimal_tech_buying_array = get_optimal_tech_buying_array($c, $eligible_techs, $buying_priorities, 9999, $tech_inherent_value);
    $cost_for_military_point_guess = get_cost_per_military_points_for_caching($c);
    $dpnw_guess = get_dpnw_for_caching($c);
    $money_to_keep_after_stockpiling = 1500000000;
    get_stockpiling_weights_and_adjustments ($stockpiling_weights, $stockpiling_adjustments, $c, $server, $rules, $cpref, $money_to_keep_after_stockpiling, true, true, true);

    // log useful information about country state
    log_country_message($cnum, $c->turns.' turns left');
    //log_country_message($cnum, 'Explore Rate: '.$c->explore_rate.'; Min Rate: '.$c->explore_min);
    //$pm_info = get_pm_info(); //get the PM info
    //out_data($pm_info);       //output the PM info
    //$market_info = get_market_info(); //get the Public Market info
    //out_data($market_info);       //output the PM info

    //$owned_on_market_info = get_owned_on_market_info();     //find out what we have on the market


    if($c->money > 2000000000) { // try to stockpile to avoid corruption and to limit bot abuse
        // first spend extra money normally so we can buy needed military or income techs if they are worthwhile
        spend_extra_money($c, $buying_priorities, $cpref, $money_to_keep_after_stockpiling, false, $cost_for_military_point_guess, $dpnw_guess, $optimal_tech_buying_array, $buying_schedule);
        spend_extra_money_on_stockpiling($c, $cpref, $money_to_keep_after_stockpiling, $stockpiling_weights, $stockpiling_adjustments);
    }

    stash_excess_bushels_on_public_if_needed($c, $rules);

    $turns_played_for_last_spend_money_attempt = 0;
    while ($c->turns > 0) {
        $result = play_casher_turn($c, $is_allowed_to_mass_explore);
        if ($result === false) {  //UNEXPECTED RETURN VALUE
            $c = get_advisor();     //UPDATE EVERYTHING
            continue;
        }
        update_c($c, $result);
        if (!$c->turns % 5) {                   //Grab new copy every 5 turns
            $c->updateMain(); //we probably don't need to do this *EVERY* turn
        }

        $hold = money_management($c, $rules->max_possible_market_sell);
        if ($hold) {
            break; //HOLD TURNS HAS BEEN DECLARED; HOLD!!
        }

        $hold = food_management($c);
        if ($hold) {
            $exit_condition = 'WAIT_FOR_PUBLIC_MARKET_FOOD';
            break; //HOLD TURNS HAS BEEN DECLARED; HOLD!!
        }

        if (turns_of_food($c) > 40
            && $c->money > 3500 * 500
            && ($c->money > $c->fullBuildCost())
        ) { // 40 turns of food
            if ($c->turns_played >= $turns_played_for_last_spend_money_attempt + 7) { // wait at least 7 turns before trying again
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

    if($c->money > 2000000000) { // try to stockpile to avoid corruption and to limit bot abuse
        spend_extra_money_on_stockpiling($c, $cpref, $money_to_keep_after_stockpiling, $stockpiling_weights, $stockpiling_adjustments);
        // don't see a strong reason to sell excess bushels at this step
    }

    $c->countryStats(CASHER); // FUTURE: implement? , casherGoals($c));
    return $c;
}//end play_casher_strat()


function play_casher_turn(&$c, $is_allowed_to_mass_explore)
{
 //c as in country!
    $target_bpt = 65;
    global $turnsleep;
    usleep($turnsleep);
    //log_country_message($c->cnum, $main->turns . ' turns left');
    if ($c->shouldBuildSingleCS($target_bpt)) {
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
        //build 4CS if we can afford it and are below our target BPT (80)
        return Build::cs(4); //build 4 CS
    } elseif ($c->built() > 50) {
        //otherwise... explore if we can
        $explore_turn_limit = $is_allowed_to_mass_explore ? 999 : 7; // match spend_money call
        return explore($c, max(1, min($explore_turn_limit, $c->turns, turns_of_food($c) - 4)));
    } else {
        //otherwise...  cash
        return cash($c);
    }
}//end play_casher_turn()


function casher_get_buying_schedule($cnum, $cpref) {
    $country_fixed_random_number = decode_bot_secret($cpref->bot_secret, 2);
    $buying_schedule = $country_fixed_random_number % 4;
    log_country_message($cnum, "Buying schedule number is: $buying_schedule");
    return $buying_schedule;
} // casher_get_buying_schedule


function casher_get_buying_priorities ($cnum, $buying_schedule) {
    $buying_priorities = [];
    
    if($buying_schedule == 0) { // heavy military
        $buying_priorities = [
            ['type'=>'DPA','goal'=>100],
            ['type'=>'INCOME_TECHS','goal'=>100],
            ['type'=>'NWPA','goal'=>100]
        ];
    }
    elseif($buying_schedule == 1) { // heavy teach
        $buying_priorities = [
            ['type'=>'DPA','goal'=>1],
            ['type'=>'INCOME_TECHS','goal'=>100],
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
            ['type'=>'INCOME_TECHS','goal'=>100],
            ['type'=>'NWPA','goal'=>100]
        ];
    }
    elseif($buying_schedule == 3) { // favor tech
        $buying_priorities = [
            ['type'=>'DPA','goal'=>2],
            ['type'=>'INCOME_TECHS','goal'=>50],
            ['type'=>'DPA','goal'=>35], 
            ['type'=>'INCOME_TECHS','goal'=>100],          
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

function casher_switch_government_if_needed($c) {
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


/*
function casherGoals(&$c)
{
    $bus_res_goal = ($c->govt == "H" ? 148 : 178);
    return [
        //what, goal, priority
        ['t_bus',$bus_res_goal,800],
        ['t_res',$bus_res_goal,800],
        ['t_mil',94,100],
        ['nlg',$c->nlgTarget(),200],
        ['dpa',$c->defPerAcreTarget(1.0),400],
        ['food', 1000000000, 1],
    ];
}//end casherGoals()
*/