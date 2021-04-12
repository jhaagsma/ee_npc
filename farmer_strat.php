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
function play_farmer_strat($server, $cnum, $rules, $cpref, &$exit_condition)
{
    $exit_condition = 'NORMAL';
    //global $cnum;
    //$main = get_main();     //get the basic stats
    //out_data($main);          //output the main data
    $c = get_advisor();     //c as in country! (get the advisor)
    $is_allowed_to_mass_explore = is_country_allowed_to_mass_explore($c, $cpref);
    log_country_message($cnum, "Agri: {$c->pt_agri}%; Bus: {$c->pt_bus}%; Res: {$c->pt_res}%; Mil: {$c->pt_mil}%; Weap: {$c->pt_weap}%");
    
    $c->setIndy('pro_spy');

    if ($c->m_spy > 10000) {
        Allies::fill('spy');
    }

    farmer_switch_government_if_needed($c);

    $buying_schedule = $cpref->purchase_schedule_number;
    $buying_priorities = farmer_get_buying_priorities ($cnum, $buying_schedule);
    $tech_inherent_value = get_inherent_value_for_tech($c, $rules, $cpref);
    $eligible_techs = ['t_bus', 't_res', 't_agri', 't_mil', 't_weap'];
    $optimal_tech_buying_array = get_optimal_tech_buying_array($c, $eligible_techs, $buying_priorities, $cpref->tech_max_purchase_price, $tech_inherent_value);
    $cost_for_military_point_guess = get_cost_per_military_points_for_caching($c);
    $dpnw_guess = get_dpnw_for_caching($c);
    $money_to_keep_after_stockpiling = $cpref->target_cash_after_stockpiling;
    get_stockpiling_weights_and_adjustments ($stockpiling_weights, $stockpiling_adjustments, $c, $server, $rules, $cpref, $money_to_keep_after_stockpiling, false, true, true);
    $bushel_min_sell_price = get_farmer_min_sell_price($c, $cpref, $rules, $server);

    // log useful information about country state
    log_country_message($cnum, $c->turns.' turns left');
    //log_country_message($cnum, 'Explore Rate: '.$c->explore_rate.'; Min Rate: '.$c->explore_min);
    //$pm_info = get_pm_info();   //get the PM info
    //out_data($pm_info);       //output the PM info
    //$market_info = get_market_info();   //get the Public Market info
    //out_data($market_info);       //output the PM info

    $owned_on_market_info = get_owned_on_market_info();     //find out what we have on the market
    //out_data($owned_on_market_info);  //output the Owned on Public Market info

    if($c->money > 2000000000) { // try to stockpile to avoid corruption and to limit bot abuse
        // first spend extra money normally so we can buy needed military or income techs if they are worthwhile
        spend_extra_money($c, $buying_priorities, $cpref, $money_to_keep_after_stockpiling, false, $cost_for_military_point_guess, $dpnw_guess, $optimal_tech_buying_array, $buying_schedule);
        spend_extra_money_on_stockpiling($c, $cpref, $money_to_keep_after_stockpiling, $stockpiling_weights, $stockpiling_adjustments);
    }

    if($c->money > 1000000000) // only called here to keep returned bushels on the market at high prices if we have a lot of cash
        stash_excess_bushels_on_public_if_needed($c, $rules);

    $turns_played_for_last_spend_money_attempt = 0;
    while ($c->turns > 0) {
        //$result = PublicMarket::buy($c,array('m_bu'=>100),array('m_bu'=>400));

        $result = play_farmer_turn($c, $rules->base_pm_food_sell_price, $is_allowed_to_mass_explore, $bushel_min_sell_price);
        if ($result === false) {  //UNEXPECTED RETURN VALUE
            $c = get_advisor();     //UPDATE EVERYTHING
            continue;
        }

        update_c($c, $result);
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
        $hold = money_management($c, $rules->max_possible_market_sell);
        if ($hold) {
              break; //HOLD TURNS HAS BEEN DECLARED; HOLD!!            
        }

        $hold = food_management($c);
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

    if($c->money > 2000000000) { // try to stockpile to avoid corruption and to limit bot abuse
        spend_extra_money_on_stockpiling($c, $cpref, $money_to_keep_after_stockpiling, $stockpiling_weights, $stockpiling_adjustments);
        // don't see a strong reason to sell excess bushels at this step
    }

    $c->countryStats(FARMER); // , farmerGoals($c) FUTURE: implement?
    return $c;
}//end play_farmer_strat()

function play_farmer_turn(&$c, $server_base_pm_bushel_sell_price, $is_allowed_to_mass_explore, $bushel_min_sell_price)
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
    } elseif ($c->protection == 0 && $c->food > 7000
        && (
            $c->foodnet > 0 && $c->foodnet > 3 * $c->foodcon && $c->food > 30 * $c->foodnet
            || $c->turns == 1
        )
    ) { //Don't sell less than 30 turns of food unless you're on your last turn (and desperate?)
        return sellextrafood_farmer($c, $server_base_pm_bushel_sell_price, $bushel_min_sell_price);
    } elseif ($c->shouldBuildSpyIndies($target_bpt)) {
        //build a full BPT of indies if we have less than that, and we're out of protection
        return Build::indy($c);
    } elseif ($c->shouldBuildFullBPT($target_bpt)) {
        //build a full BPT if we can afford it
        return Build::farmer($c);
    } elseif ($c->shouldBuildFourCS($target_bpt)) {
        //build 4CS if we can afford it and are below our target BPT (80)
        return Build::cs(4); //build 4 CS
    } elseif ($c->built() > 50) {  //otherwise... explore if we can
        $explore_turn_limit = $is_allowed_to_mass_explore ? 999 : 7; // match spend_money call
        return explore($c, max(1,min($explore_turn_limit, $c->turns - 1, turns_of_money($c) - 4)));
    } else { //otherwise...  cash
        return cash($c);
    }
}//end play_farmer_turn()


function sellextrafood_farmer(&$c, $server_base_pm_bushel_sell_price = 29, $bushel_min_sell_price)
{
    //log_country_message($c->cnum, "Lots of food, let's sell some!");
    //$pm_info = get_pm_info();
    //$market_info = get_market_info(); //get the Public Market info
    //global $market;

    $c = get_advisor();     //UPDATE EVERYTHING

    $quantity = ['m_bu' => $c->food]; //sell it all! :)

    $pm_info = PrivateMarket::getRecent();

    $rmax    = 1.10; //percent
    $rmin    = 0.95; //percent
    $rstep   = 0.01;
    $rstddev = 0.10;
    $max     = $c->goodsStuck('m_bu') ? 0.99 : $rmax;    
    $food_public_price = PublicMarket::price('m_bu');
    // don't dump food at +1 when the market is empty... can end up in a state where demo recyclers keep clearing it
    $food_public_price = $food_public_price ? $food_public_price : $server_base_pm_bushel_sell_price + 8;
    $price   = max($bushel_min_sell_price, round($food_public_price * Math::purebell($rmin, $max, $rstddev, $rstep)));

    if ($price <= 1 + $pm_info->sell_price->m_bu / (2 - $c->tax())) {
        return PrivateMarket::sell($c, ['m_bu' => $quantity]);
        ///      PrivateMarket::sell($c,array('m_bu' => $c->food));   //Sell 'em
    }

    $price_array   = ['m_bu' => $price];

    return PublicMarket::sell($c, $quantity, $price_array);    //Sell food!
}//end sellextrafood_farmer()


function farmer_switch_government_if_needed($c) {
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


/*
function farmerGoals(&$c)
{
    return [
        //what, goal, priority
        ['t_agri',227,1000],
        ['t_bus',174,500],
        ['t_res',174,500],
        ['t_mil',95,200],
        ['nlg',$c->nlgTarget(),200],
        ['dpa',$c->defPerAcreTarget(1.0),1000],
        ['food', 1000000000, 5],
    ];
}//end farmerGoals()
*/