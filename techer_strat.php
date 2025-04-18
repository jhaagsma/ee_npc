<?php

namespace EENPC;

$techlist = ['t_mil','t_med','t_bus','t_res','t_agri','t_war','t_ms','t_weap','t_indy','t_spy','t_sdi'];

function play_techer_strat($server, $cnum, $rules, $cpref, &$exit_condition, &$turn_action_counts)
{
    $exit_condition = 'NORMAL';
    //global $cnum;
    //$main = get_main();     //get the basic stats
    //out_data($main);          //output the main data
    $c = get_advisor();     //c as in country! (get the advisor)
    log_static_cpref_on_turn_0 ($c, $cpref);
    $starting_turns = $c->turns;
    $is_allowed_to_mass_explore = is_country_allowed_to_mass_explore($c, $cpref);

    if($c->land >= 5000 && $cpref->techer_allowed_to_explore)
        log_country_message($cnum, "Not allowed to explore due to preference explore cutoff value of $cpref->techer_round_explore_cutoff_percentage");

    sell_initial_troops_on_turn_0($c);

    log_country_message($cnum, "Getting tech prices using market search looking back $cpref->market_search_look_back_hours hours", 'green');
    $tech_price_history = get_market_history_tech($cnum, $cpref);
    $tpt_split = get_tpt_split_from_production_algorithm($c, $tech_price_history, $cpref, $server);

    $c->setIndy('pro_spy');

    if ($c->m_spy > 10000) {
        Allies::fill($cpref, 'spy', $rules);
    }

    if ($c->b_lab > 2000) {
        Allies::fill($cpref, 'res', $rules);
    }

    techer_switch_government_if_needed($c);

    $buying_priorities = [
        ['type'=>'DPA','goal'=>100],
        ['type'=>'NWPA','goal'=>100]
    ];
    $money_to_keep_after_stockpiling = $cpref->target_cash_after_stockpiling;
    get_stockpiling_weights_and_adjustments ($stockpiling_weights, $stockpiling_adjustments, $c, $server, $rules, $cpref, $money_to_keep_after_stockpiling, true, true, true);
    $tech_price_min_sell_price = get_techer_min_sell_price($c, $cpref, $rules);

    // log useful information about country state
    log_country_message($cnum, $c->turns.' turns left');
    //log_country_message($cnum, 'Explore Rate: '.$c->explore_rate.'; Min Rate: '.$c->explore_min);

    //out_data($c);             //ouput the advisor data
    //$market_info = get_market_info();   //get the Public Market info
    //out_data($market_info);       //output the PM info

    $owned_on_market_info = get_owned_on_market_info();     //find out what we have on the market
    //out_data($owned_on_market_info); //output the owned on market info


    if($c->money > 1700000000) { // try to stockpile to avoid corruption and to limit bot abuse
        // first spend extra money normally so we can buy needed military
        spend_extra_money($c, $buying_priorities, $cpref, $money_to_keep_after_stockpiling, false);
        spend_extra_money_on_stockpiling($c, $cpref, $money_to_keep_after_stockpiling, $stockpiling_weights, $stockpiling_adjustments);
    }

    $turn_action_counts = [];
    $possible_turn_result = stash_excess_bushels_on_public_if_needed($c, $rules);
    if($possible_turn_result) {
        $action_and_turns_used = update_c($c, $possible_turn_result);
        update_turn_action_array($turn_action_counts, $action_and_turns_used);
    }

    attempt_to_recycle_bushels_but_avoid_buyout($c, $cpref, $food_price_history);

    $teching_turns_remaining_before_explore = floor(0.01 * $cpref->min_perc_teching_turns * $c->turns);
    log_country_message($cnum, "Min teching turns before exploring is $teching_turns_remaining_before_explore based on preference rate of $cpref->min_perc_teching_turns%");

    while ($c->turns > 0) {
        //$result = PublicMarket::buy($c,array('m_bu'=>100),array('m_bu'=>400));
                
        $result = play_techer_turn($c, $cpref, $rules, $tech_price_min_sell_price, $is_allowed_to_mass_explore, $tech_price_history, $tpt_split, $teching_turns_remaining_before_explore);
        if (!$result) {  //UNEXPECTED RETURN VALUE
            $c = get_advisor();     //UPDATE EVERYTHING
            continue;
        }

        $action_and_turns_used = update_c($c, $result);
        update_turn_action_array($turn_action_counts, $action_and_turns_used);

        if (!$c->turns % 5) {                   //Grab new copy every 5 turns
            $c->updateMain(); //we probably don't need to do this *EVERY* turn
        }

        // management is here to make sure that tech is sold
        $hold = money_management($c, $rules->max_possible_market_sell, $cpref, $turn_action_counts);
        if ($hold) {
            break; //HOLD TURNS HAS BEEN DECLARED; HOLD!!
        }

        $hold = food_management($c, $cpref);
        if ($hold) {
            $exit_condition = 'WAIT_FOR_PUBLIC_MARKET_FOOD'; 
            break; //HOLD TURNS HAS BEEN DECLARED; HOLD!!
        }
    }

    // mil tech might have gone up
    attempt_to_recycle_bushels_but_avoid_buyout($c, $cpref, $food_price_history);

    if (turns_of_food($c) > 50 && turns_of_money($c) > 50 && $c->money > 3500 * 500 && ($c->money > $c->fullBuildCost() - $c->runCash()) && $c->tpt > 200) { // 40 turns of food
        spend_extra_money($c, $buying_priorities, $cpref, $c->fullBuildCost() - $c->runCash(), false);//keep enough money to build out everything
    }

    if($c->money > 1700000000) { // try to stockpile to avoid corruption and to limit bot abuse
        spend_extra_money_on_stockpiling($c, $cpref, $money_to_keep_after_stockpiling, $stockpiling_weights, $stockpiling_adjustments);
        // don't see a strong reason to sell excess bushels at this step
    }

    if($exit_condition == 'NORMAL' && $starting_turns > 30 && ($starting_turns - $c->turns) < 0.3 * $starting_turns)
        $exit_condition = 'LOW_TURNS_PLAYED'; 

    //$c->countryStats(TECHER);

    return $c;
}//end play_techer_strat()


function play_techer_turn(&$c, $cpref, $rules, $tech_price_min_sell_price, $is_allowed_to_mass_explore, $tech_price_history, $tpt_split, &$teching_turns_remaining_before_explore)
{    
    $target_bpt = $cpref->initial_bpt_target;
    $server_max_possible_market_sell = $rules->max_possible_market_sell;
    global $turnsleep, $mktinfo; //, $server_avg_land;
    $mktinfo = null;
    usleep($turnsleep);
    //log_country_message($cnum, $main->turns . ' turns left');
    $mil_tech_to_keep = min($c->t_mil, ($c->govt == 'D' ? 42 : 0) * $c->land); // for demo recycling

    // FUTURE: why does building 4 cs become so slow? can_sell_tech? after protection? selltechtime ?
    // FUTURE: maybe split logic for < target BPT, < 1800 A, and otherwise

    if($c->govt <> 'I' && $c->turns_played <= 180) {
        return play_techer_turn_first_180_turns_for_most_gov($c, $cpref, $tpt_split, $tech_price_min_sell_price, $server_max_possible_market_sell, $mil_tech_to_keep);
    } elseif($c->b_farm > 0 && $c->money > 1.2 * $c->build_cost * $c->b_farm) { 
        // destroy any farms as soon as we have enough cash to replace with labs
        return Build::destroy_all_of_one_type($c, 'farm');
    } elseif($c->land < 500 && $c->built() > 50) {
        // always explore when possible early on to get more income
        return explore($c, 1);
    } elseif ($c->shouldBuildSingleCS($target_bpt, 30)) {
        //LOW BPT & CAN AFFORD TO BUILD
        //build one CS if we can afford it and are below our target BPT
        return Build::cs(); //build 1 CS
    } elseif ($c->protection == 0 && total_cansell_tech($c, $server_max_possible_market_sell, $mil_tech_to_keep) > 20 * $c->tpt && selltechtime($c)
        || $c->turns == 1 && total_cansell_tech($c, $server_max_possible_market_sell, $mil_tech_to_keep) > 20
    ) {
        //never sell less than 20 turns worth of tech
        //always sell if we can????
        return sell_max_tech($c, $cpref, $tech_price_min_sell_price, $server_max_possible_market_sell, $mil_tech_to_keep, false, true, $tech_price_history);
    } elseif ($c->land >= 1800 && $c->shouldBuildSpyIndies($target_bpt)) { // techer can have early income problems so don't build indies right away
        //build a full BPT of indies if we have less than that, and we're out of protection
        return Build::indy($c);
    } elseif ($c->shouldBuildFullBPT($target_bpt)) {
        //build a full BPT if we can afford it
        return Build::techer($c);
    } elseif ($c->shouldBuildFourCS($target_bpt)) {
        //build 4CS if we can afford it and are below our target BPT (60)
        return Build::cs(4); //build 4 CS
    } elseif ($c->tpt > $c->land * 0.17 * 1.3 && $c->tpt >= 333 && $teching_turns_remaining_before_explore > 0) {
        //tech per turn is greater than land*0.17 -- just kindof a rough "don't tech below this" rule...
        //so, 10 if they can... cap at turns - 1
        $turns_to_tech = min($teching_turns_remaining_before_explore, min(turns_of_money($c), turns_of_food($c), 13, $c->turns + 2) - 3);
        $teching_turns_remaining_before_explore -= $turns_to_tech;
        return tech_techer($c, $turns_to_tech, $tpt_split);
    } elseif (
        // allow exploring if < 5000 acres to improve late start techer
        ($c->land < 5000 || $cpref->techer_allowed_to_explore) && $c->built() > 50 && $c->land < $cpref->techer_land_goal &&
        (
            ($c->empty < 4 && $c->land < 1800) // always allow for early exploring (cs)
            ||
            ($c->land < 1800 && $c->money > 2 * $c->explore_rate * (1500 + 3 * ($c->land + 2 * $c->explore_rate))) // at low acreage, enough money to build labs on 2 explore turns
            ||
            ($c->money + $c->turns * $c->income) > ( // turns is an over-estimate here, but probably ok
                min($c->turns, (0.01 * $c->built() * $c->land - $c->empty) / $c->explore_rate) * $c->explore_rate) * // explore turns
                (1500 + 3 * ($c->land + min($c->turns, (0.01 * $c->built() * $c->land - $c->empty) / $c->explore_rate) * $c->explore_rate) // building cost for new acres
            )
        ) 
        // explore if land is less than 10k, we can explore, and we have less than 4 empty acres
        // or we expect to have enough money to build labs on the new land
    ) {        
        $explore_turn_limit = $is_allowed_to_mass_explore ? ($c->land < 1800 ? 2 : 999) : 5;
        return explore($c, max(1, min($explore_turn_limit, $c->turns - 1, turns_of_money($c) - 4, turns_of_food($c) - 4)));
    } else { //otherwise, tech, obviously
        //so, 10 if they can... cap at turns - 1
        if($c->bpt < $target_bpt || $c->tpt < 333) // only tech one turn at a time when tpt is low so we can get tpt up faster
            $turns_to_tech = 1;   
        else
            $turns_to_tech = max(1, min(turns_of_money($c), turns_of_food($c), 13, $c->turns + 2) - 3);
        $teching_turns_remaining_before_explore -= $turns_to_tech;
        return tech_techer($c, $turns_to_tech, $tpt_split);
    }
}//end play_techer_turn()



function play_techer_turn_first_180_turns_for_most_gov (&$c, $cpref, $tpt_split, $tech_price_min_sell_price, $server_max_possible_market_sell, $mil_tech_to_keep) {
    // it's kind of weird to farm start as theo, but it seems to work out decently enough for now
    if($c->food > 0 && $c->b_farm > 0 && $c->foodnet > 20 && $c->b_cs <= 120) {
        return PrivateMarket::sell_single_good($c, 'm_bu', $c->food);
    } elseif($c->turns_played == 179 && total_cansell_tech($c, $server_max_possible_market_sell, $mil_tech_to_keep) >= 40) {
        return sell_max_tech($c, $cpref, $tech_price_min_sell_price, $server_max_possible_market_sell);
    } elseif(($c->land < 400 && $c->built() > 50) || $c->empty < $c->bpt) {
        // always explore when possible early on to get more income
        // otherwise, explore if less than bpt of empty acres
        return explore($c, 1);
    } elseif ($c->shouldBuildSingleCS(10, 10)) {
        return Build::cs(); //build 1 CS
    } elseif ($c->b_farm < 50 && $c->shouldBuildFullBPT(null)) {
        //build a full BPT if we can afford it
        return Build::farmer($c);
    } elseif ($c->shouldBuildFourCS(35, 35)) {
        //build 4CS if we can afford it and are below our target BPT (35 for now)
        return Build::cs(4); //build 4 CS
    } elseif ($c->shouldBuildFullBPT(35)) {
        //build a full BPT if we can afford it
        return Build::techer($c);
    } elseif ($c->shouldBuildFourCS(37)) {
        //build 4CS if we can afford it and are below our target BPT (35 for now)
        return Build::cs(4); //build 4 CS
    } else { //otherwise...  tech
        return tech_techer($c, 1, $tpt_split);
    }
} // play_techer_turn_first_180_turns_for_most_gov



function selltechtime(&$c)
{
    global $techlist;
    $sum = $om = 0;
    foreach ($techlist as $tech) {
        $sum += $c->$tech;
        $om  += $c->onMarket($tech);
    }
    if ($om < $sum / 6) {
        return true;
    }

    return false;
}//end selltechtime()


// future: $mil_tech_to_keep should be an array of all techs? new function or change the name?
function sell_max_tech(&$c, $cpref, $tech_price_min_sell_price, $server_max_possible_market_sell, $mil_tech_to_keep = 0,
    $dump_at_min_sell_price = false, $allow_average_prices = false, $tech_price_history = [])
{
    if($allow_average_prices && empty($tech_price_history)) {
        log_error_message(999, $c->cnum, 'sell_max_tech() allowed average price selling but $tech_price_history was empty');
        $allow_average_prices = false;
    }
    // it's okay if $tech_price_history is empty if $allow_average_prices == false

    $c = get_advisor();     //UPDATE EVERYTHING
    $c->updateOnMarket();

    //$market_info = get_market_info();   //get the Public Market info
    //global $market;

    $quantity = [
        'mil' => min($c->t_mil - $mil_tech_to_keep, can_sell_tech($c, 't_mil', $server_max_possible_market_sell)),
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

    if (array_sum($quantity) == 0) {
        if(!$mil_tech_to_keep) {
            log_error_message(122, $c->cnum, 'Techer computing Zero Sell!');
            $c = get_advisor();
            $c->updateOnMarket();

            Debug::on();
            Debug::msg('This Quantity: '.array_sum($quantity).' TotalCanSellTech: '.total_cansell_tech($c, $server_max_possible_market_sell));
        }
        return;
    }

    // mil, bus, res, agri, indy, SDI
    $good_tech_nogoods_high   = 8800;
    $good_tech_nogoods_low    = 4000;
    $good_tech_nogoods_stddev = 1200;
    $good_tech_nogoods_step   = 1;

    // med, mil strat, warfare, weapons, spy
    $garbage_tech_nogoods_high   = 4500;
    $garbage_tech_nogoods_low    = 1500;
    $garbage_tech_nogoods_stddev = 750;
    $garbage_tech_nogoods_step   = 1;    

    $rmax    = 1 + 0.01 * $cpref->selling_price_max_distance;
    $rmin    = 1 - 0.01 * $cpref->selling_price_max_distance;
    $rstep   = 0.01;
    $rstddev = 0.01 * $cpref->selling_price_std_dev;
    $price          = [];

    foreach ($quantity as $key => $q) {
        $t__key = "t_$key"; // :(
        $avg_price = ($allow_average_prices and isset($tech_price_history[$t__key]['avg_price'])) ? $tech_price_history[$t__key]['avg_price'] : null;

        if ($q == 0) {
            $price[$key] = 0;
        } else {
            $max = $c->goodsStuck($key) ? 0.98 : $rmax; //undercut if we have goods stuck
            // there's a random chance to sell based on market average prices instead of current prices
            $use_avg_price = ($allow_average_prices && $cpref->get_sell_price_method(false) == 'AVG') ? true : false;

            if($dump_at_min_sell_price)
                $price[$key] = $tech_price_min_sell_price;
            elseif ($use_avg_price and $avg_price) { // use average price and average price exists
                $price[$key] = max($tech_price_min_sell_price, floor($avg_price * Math::purebell($rmin, $max, $rstddev, $rstep)));
            }
            elseif (PublicMarket::price($key) != null) {
                // additional check to make sure we aren't repeatedly undercutting with minimal goods
                if ($q < 100 && PublicMarket::available($key) < 1000) {
                    $price[$key] = PublicMarket::price($key);
                } else {
                    Debug::msg("sell_max_tech:A:$key");                    
                    Debug::msg("sell_max_tech:B:$key");

                    $price[$key] = max($tech_price_min_sell_price, 
                        min(9999,
                            floor(PublicMarket::price($key) * Math::purebell($rmin, $max, $rstddev, $rstep))
                        )
                    );

                    Debug::msg("sell_max_tech:C:$key");
                }
            } else {
                if($key == 'med' || $key == 'war' || $key == 'weap' || $key == 'ms' || $key == 'spy')
                    $price[$key] = floor(Math::purebell($garbage_tech_nogoods_low, $garbage_tech_nogoods_high, $garbage_tech_nogoods_stddev, $garbage_tech_nogoods_step));
                else
                    $price[$key] = floor(Math::purebell($good_tech_nogoods_low, $good_tech_nogoods_high, $good_tech_nogoods_stddev, $good_tech_nogoods_step));
            }
        }
    }

    $result = PublicMarket::sell($c, $quantity, $price);
    if ($result == 'QUANTITY_MORE_THAN_CAN_SELL') {
        log_country_message($c->cnum, "TRIED TO SELL MORE THAN WE CAN!?!");
        $c = get_advisor();     //UPDATE EVERYTHING
    }

    return $result;
}//end sell_max_tech()


/**
 * Make it so we can tech multiple turns...
 *
 * @param  Object  $c     Country Object
 * @param  integer $turns Number of turns to tech
 *
 * @return EEResult       Teching
 */
function tech_techer(&$c, $turns = 1, $tpt_split = [])
{
    //lets do random weighting... to some degree
    //$market_info = get_market_info();   //get the Public Market info
    //global $market;

    // normalize array to current tpt
    normalize_array_for_selling($c->cnum, $tpt_split, $c->tpt, 't_bus');

    $turns = max(1, min($turns, $c->turns));
    $left  = $c->tpt * $turns;
    $left -= $mil = $tpt_split['t_mil'] * $turns;
    $left -= $med = $tpt_split['t_med'] * $turns;
    $left -= $bus = $tpt_split['t_bus'] * $turns;
    $left -= $res = $tpt_split['t_res'] * $turns;
    $left -= $agri = $tpt_split['t_agri'] * $turns;
    $left -= $war = $tpt_split['t_war'] * $turns;
    $left -= $ms = $tpt_split['t_ms'] * $turns;
    $left -= $weap = $tpt_split['t_weap'] * $turns;
    $left -= $indy = $tpt_split['t_indy'] * $turns;
    $left -= $spy = $tpt_split['t_spy'] * $turns;
    $left -= $sdi = $tpt_split['t_sdi'] * $turns;
    if ($left != 0) {
        die("What the hell? tech_techer()");
    }

    return tech(
        [
            'mil' => $mil,
            'med' => $med,
            'bus' => $bus,
            'res' => $res,
            'agri' => $agri,
            'war' => $war,
            'ms' => $ms,
            'weap' => $weap,
            'indy' => $indy,
            'spy' => $spy,
            'sdi' => $sdi
        ]
    );
}//end tech_techer()


function techer_switch_government_if_needed(&$c) {
    if ($c->govt == 'M') {
        $rand = rand(0, 100);
        switch ($rand) {
            case $rand < 35:
                Government::change($c, 'H');
                break;
            case $rand < 85:
                Government::change($c, 'D');
                break;
            default:
                Government::change($c, 'T');
                break;
        }
    }
} // techer_switch_government_if_needed()
