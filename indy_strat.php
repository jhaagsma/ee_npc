<?php

namespace EENPC;

$military_list = ['m_tr','m_j','m_tu','m_ta'];

function play_indy_strat($server, $cnum, $rules, $cpref, &$exit_condition, &$turn_action_counts)
{
    $exit_condition = 'NORMAL';
    //global $cnum;
    //$main = get_main();     //get the basic stats
    //out_data($main);          //output the main data
    $c = get_advisor();     //c as in country! (get the advisor)
    log_static_cpref_on_turn_0 ($c, $cpref);
    $starting_turns = $c->turns;

    $is_allowed_to_mass_explore = is_country_allowed_to_mass_explore($c, $cpref);

    sell_initial_troops_on_turn_0($c);


    log_country_message($cnum, "Indy: {$c->pt_indy}%; Bus: {$c->pt_bus}%; Res: {$c->pt_res}%; Mil: {$c->pt_mil}%; Weap: {$c->pt_weap}%");

    log_country_message($cnum, "Getting military prices using market search looking back $cpref->market_search_look_back_hours hours", 'green');
    $military_unit_price_history = get_market_history_all_military_units($cnum, $cpref);
    set_indy_from_production_algorithm($c, $military_unit_price_history, $cpref, false); // changing to not check DPA - Slagpit 20210321

    if ($c->m_spy > 10000) {
        Allies::fill($cpref, 'spy');
    }

    indy_switch_government_if_needed($c);

    $buying_priorities = [
        ['type'=>'INCOME_TECHS','goal'=>100]
    ];
    $tech_inherent_value = get_inherent_value_for_tech($c, $rules, $cpref);
    $eligible_techs = ['t_bus', 't_res', 't_indy', 't_mil']; // don't buy t_weap for now - indies would over-prioritize it
    $optimal_tech_buying_array = get_optimal_tech_buying_array($c, $rules, $eligible_techs, $buying_priorities, $cpref->tech_max_purchase_price, $tech_inherent_value);

    // log useful information about country state
    log_country_message($cnum, $c->turns.' turns left');
    //log_country_message($cnum, 'Explore Rate: '.$c->explore_rate.'; Min Rate: '.$c->explore_min);
    //$market_info = get_market_info();   //get the Public Market info
    //out_data($market_info);       //output the PM info

    $owned_on_market_info = get_owned_on_market_info(); //find out what we have on the market
    //out_data($owned_on_market_info);  //output the Owned on Public Market info

    // FUTURE: get a turn of food before doing anything?

    // indies buy tech instead of building when no limit on goals here- Slagpit 20210321
    // the 80% is here because indies seemed to not be buying tech through 800 turns of play
    if ($c->money > floor(0.8 * $c->fullBuildCost()) - $c->runCash()) {
        spend_extra_money_no_military($c, $buying_priorities, $cpref, floor(0.8 * $c->fullBuildCost()) - $c->runCash(), $optimal_tech_buying_array);
    }

    $turn_action_counts = [];
    $turns_played_for_last_spend_money_attempt = $c->turns_played;
    while ($c->turns > 0) {
        //$result = PublicMarket::buy($c,array('m_bu'=>100),array('m_bu'=>400));
        $result = play_indy_turn($c, $cpref, $rules->max_possible_market_sell, $is_allowed_to_mass_explore);
        if ($result === false) {  //UNEXPECTED RETURN VALUE
            $c = get_advisor();     //UPDATE EVERYTHING
            continue;
        }

        $action_and_turns_used = update_c($c, $result);
        update_turn_action_array($turn_action_counts, $action_and_turns_used);

        if (!$c->turns % 5) {                   //Grab new copy every 5 turns
            $c->updateMain(); //we probably don't need to do this *EVERY* turn
        }

        // money and food management should be here because otherwise indies might not sell goods
        $hold = money_management($c, $rules->max_possible_market_sell, $cpref, $turn_action_counts);
        if ($hold) {
            break; //HOLD TURNS HAS BEEN DECLARED; HOLD!!
        }

        $hold = food_management($c, $cpref);
        if ($hold) {
            $exit_condition = 'WAIT_FOR_PUBLIC_MARKET_FOOD'; 
            break; //HOLD TURNS HAS BEEN DECLARED; HOLD!!
        }

        // TODO: tech buying?
        if (turns_of_food($c) > (10 + $c->turns) && turns_of_money($c) > (10 + $c->turns) && $c->money > 3500 * 500 && ($c->money > floor(0.8 * $c->fullBuildCost()) - $c->runCash())
        ) {
            if ($c->turns_played >= $turns_played_for_last_spend_money_attempt + $cpref->spend_extra_money_cooldown_turns) { // wait some number of turns before trying again
                spend_extra_money_no_military($c, $buying_priorities, $cpref, floor(0.8 * $c->fullBuildCost()) - $c->runCash(), $optimal_tech_buying_array);
                $turns_played_for_last_spend_money_attempt = $c->turns_played;
            }
        }
    }
    // FUTURE: do food and money management first
    // FUTURE: always sell at end if possible - make sell_max_military get food/money to avoid OOF and OOM
    // total_cansell_military > 20000?

    if($exit_condition = 'NORMAL' && $starting_turns > 30 && ($starting_turns - $c->turns) < 0.3 * $starting_turns)
        $exit_condition = 'LOW_TURNS_PLAYED'; 

    //$c->countryStats(INDY);

    return $c;
}//end play_indy_strat()


function play_indy_turn(&$c, $cpref, $server_max_possible_market_sell, $is_allowed_to_mass_explore)
{
    $target_bpt = $cpref->initial_bpt_target;
    global $turnsleep;
    usleep($turnsleep);

    if ($c->turns_played <= 99) {
        return play_indy_turn_first_99_turns($c, $cpref, $target_bpt);
    } elseif ($c->land < 1800) {
        return play_indy_turn_first_1800_acres($c, $cpref, $target_bpt, $server_max_possible_market_sell);
    } elseif ($c->shouldBuildSingleCS($target_bpt)) {
        //LOW BPT & CAN AFFORD TO BUILD
        //build one CS if we can afford it and are below our target BPT
        return Build::cs(); //build 1 CS
    } elseif (
           $c->money < $cpref->target_cash_after_stockpiling // weak stocking
        && $c->protection == 0
        && total_cansell_military($c, $server_max_possible_market_sell) > 7500
        && ($c->turns == 1 || sellmilitarytime($c))
    ) {
        return sell_max_military($c, $server_max_possible_market_sell, $cpref);
    } elseif ($c->shouldBuildFullBPT($target_bpt)) {
        //build a full BPT if we can afford it
        return Build::indy($c);
    } elseif ($c->shouldBuildFourCS($target_bpt)) {
        //build 4CS if we can afford it and are below our target BPT (80)
        return Build::cs(4); //build 4 CS
    } elseif ($c->built() > 50) {  //otherwise... explore if we can
        //1.15 is my growth factor for indies
        $explore_turn_limit = $is_allowed_to_mass_explore ? 999 : $cpref->spend_extra_money_cooldown_turns;
        return explore($c, max(1, min($explore_turn_limit, $c->turns - 1, turns_of_money($c) / 1.15 - 4, turns_of_food($c) - 4)));

    } elseif(($c->m_tr + $c->m_j + $c->m_tu + $c->m_ta) > 0 && $c->empty >= $c->bpt && $c->money < $c->bpt * $c->build_cost) {
        // sell if we don't have enough cash to build a bpt of indies
        // shouldn't need to check income because money management takes care of it?
        log_country_message($c->cnum, "Selling military on PM in an attempt to avoid cashing");
        return emergency_sell_mil_on_pm ($c, $c->bpt * $c->build_cost - $c->money); // TODO: is it okay to return false/true?
    } else { //otherwise...  cash
        return cash($c);
    }
}//end play_indy_turn()


function play_indy_turn_first_1800_acres (&$c, $cpref, $target_bpt, $server_max_possible_market_sell) {
    if($c->turns_played <= 170 && $c->m_tu) {
        return PrivateMarket::sell_single_good($c, 'm_tu', $c->m_tu);
    } elseif(($c->m_tr + $c->m_j + $c->m_tu + $c->m_ta) > 0 && $c->b_indy > 0 && $c->empty >= $c->bpt && $c->money < $c->bpt * $c->build_cost) {
        // sell if we don't have enough cash to build a bpt of indies
        log_country_message($c->cnum, "Selling military on PM in an attempt to avoid parking lot");
        return emergency_sell_mil_on_pm ($c, $c->bpt * $c->build_cost - $c->money); // TODO: is it okay to return false/true?
    } elseif ( // TODO: takes forever remotely?
        $c->m_tu
        && $c->protection == 0
        && total_cansell_military($c, $server_max_possible_market_sell) > 7500
        && ($c->turns == 1 || sellmilitarytime($c))
    ) {
        return sell_max_military($c, $server_max_possible_market_sell, $cpref);
    } elseif ($c->shouldBuildFullBPT($target_bpt)) { // TODO: why doesn't this work after emergency sell?
        //build a full BPT if we can afford it
        return Build::indy($c);
    } elseif ($c->shouldBuildFourCS($target_bpt)) {
        //build 4CS if we can afford it and are below our target BPT (80)
        return Build::cs(4); //build 4 CS
    } elseif ($c->built() > 50) {  //otherwise... explore if we can
        //1.15 is my growth factor for indies
        $explore_turn_limit = 2;
        return explore($c, max(1, min($explore_turn_limit, $c->turns - 1, turns_of_money($c) / 1.15 - 4, turns_of_food($c) - 4)));
    } else { //otherwise...  cash - TODO: money management needs to be good enough so this doesn't ever happen
        return cash($c);
    }
} // play_indy_turn_first_1800_acres

function play_indy_turn_first_99_turns (&$c, $cpref, $target_bpt) {
    if($c->m_tu) {
        return PrivateMarket::sell_single_good($c, 'm_tu', $c->m_tu);
    } elseif($c->land < 600 && $c->built() > 50) {
        // always explore when possible early on to get more income
        return explore($c, 1);
    } elseif ($c->shouldBuildSingleCS($target_bpt, 20)) {
        return Build::cs(); //build 1 CS
    } elseif($c->b_farm < 20) {
        return Build::farmer($c);
    } elseif ($c->shouldBuildFullBPT($target_bpt)) {
        //build a full BPT if we can afford it
        return Build::indy($c);
    } elseif ($c->shouldBuildFourCS($target_bpt)) {
        //build 4CS if we can afford it and are below our target BPT (65)
        return Build::cs(4); //build 4 CS
    } else { //otherwise...  cash (shouldn't ever happen)
        return cash($c);
    }
} // play_indy_turn_first_99_turns


function sellmilitarytime(&$c)
{
    global $military_list;
    $sum = $om = 0;
    foreach ($military_list as $mil) {
        $sum += $c->$mil;
        $om  += onmarket($mil, $c);
    }
    if ($om < $sum / 6) {
        return true;
    }

    return false;
}//end sellmilitarytime()


function indy_switch_government_if_needed($c) {
    if ($c->govt == 'M') {
        $rand = rand(0, 100);
        switch ($rand) {
            case $rand < 5:
                Government::change($c, 'D');
                break;
            default:
                Government::change($c, 'C');
                break;
        }
    }
} // indy_switch_government_if_needed()