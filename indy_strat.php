<?php

namespace EENPC;

$military_list = ['m_tr','m_j','m_tu','m_ta'];

function play_indy_strat($server, $cnum, $rules, $cpref, &$exit_condition)
{
    $exit_condition = 'NORMAL';
    //global $cnum;
    //$main = get_main();     //get the basic stats
    //out_data($main);          //output the main data
    $c = get_advisor();     //c as in country! (get the advisor)
    $is_allowed_to_mass_explore = is_country_allowed_to_mass_explore($c, $cpref);
    log_country_message($cnum, "Indy: {$c->pt_indy}%; Bus: {$c->pt_bus}%; Res: {$c->pt_res}%; Mil: {$c->pt_mil}%; Weap: {$c->pt_weap}%");

    $c->setIndyFromMarket(false); // changing to not check DPA - Slagpit 20210321

    if ($c->m_spy > 10000) {
        Allies::fill('spy');
    }

    indy_switch_government_if_needed($c);

    $buying_priorities = [
        ['type'=>'INCOME_TECHS','goal'=>100]
    ];
    $tech_inherent_value = get_inherent_value_for_tech($c, $rules, $cpref);
    $eligible_techs = ['t_bus', 't_res', 't_indy', 't_mil']; // don't buy t_weap for now - indies would over-prioritize it
    $optimal_tech_buying_array = get_optimal_tech_buying_array($c, $eligible_techs, $buying_priorities, $cpref->tech_max_purchase_price, $tech_inherent_value);

    // log useful information about country state
    log_country_message($cnum, $c->turns.' turns left');
    //log_country_message($cnum, 'Explore Rate: '.$c->explore_rate.'; Min Rate: '.$c->explore_min);
    //$pm_info = get_pm_info();   //get the PM info
    //out_data($pm_info);       //output the PM info
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

    $turns_played_for_last_spend_money_attempt = $c->turns_played;
    while ($c->turns > 0) {
        //$result = PublicMarket::buy($c,array('m_bu'=>100),array('m_bu'=>400));
                
        $result = play_indy_turn($c, $rules->max_possible_market_sell, $is_allowed_to_mass_explore);
        if ($result === false) {  //UNEXPECTED RETURN VALUE
            $c = get_advisor();     //UPDATE EVERYTHING
            continue;
        }
        update_c($c, $result);
        if (!$c->turns % 5) {                   //Grab new copy every 5 turns
            $c->updateMain(); //we probably don't need to do this *EVERY* turn
        }

        // money and food management should be here because otherwise indies might not sell goods
        $hold = money_management($c, $rules->max_possible_market_sell);
        if ($hold) {
            break; //HOLD TURNS HAS BEEN DECLARED; HOLD!!
        }

        $hold = food_management($c);
        if ($hold) {
            $exit_condition = 'WAIT_FOR_PUBLIC_MARKET_FOOD'; 
            break; //HOLD TURNS HAS BEEN DECLARED; HOLD!!
        }

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


    $c->countryStats(INDY); // indyGoals($c) // FUTURE: implement?

    return $c;
}//end play_indy_strat()


function play_indy_turn(&$c, $server_max_possible_market_sell, $is_allowed_to_mass_explore)
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
    } elseif ($c->protection == 0 && total_cansell_military($c, $server_max_possible_market_sell) > 7500 && sellmilitarytime($c)
        || $c->turns == 1 && total_cansell_military($c, $server_max_possible_market_sell) > 7500
    ) {
        return sell_max_military($c, $server_max_possible_market_sell);
    } elseif ($c->shouldBuildFullBPT($target_bpt)) {
        //build a full BPT if we can afford it
        return Build::indy($c);
    } elseif ($c->shouldBuildFourCS($target_bpt)) {
        //build 4CS if we can afford it and are below our target BPT (80)
        return Build::cs(4); //build 4 CS
    } elseif ($c->built() > 50) {  //otherwise... explore if we can
        //1.15 is my growth factor for indies
        $explore_turn_limit = $is_allowed_to_mass_explore ? 999 : 7; // match spend_money call
        return explore($c, max(1, min($explore_turn_limit, $c->turns - 1, turns_of_money($c) / 1.15 - 4, turns_of_food($c) - 4)));
    } else { //otherwise...  cash
        return cash($c);
    }
}//end play_indy_turn()



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


/*
function indyGoals(&$c)
{
    return [
        //what, goal, priority
        ['t_indy',158,8],
        ['t_bus',160,3],
        ['t_res',160,3],
        ['t_mil',94,4],
    ];
}//end indyGoals()
*/
