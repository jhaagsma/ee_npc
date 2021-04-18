<?php

namespace EENPC;

function play_rainbow_strat($server, $cnum, $rules, $cpref, &$exit_condition)
{
    $exit_condition = 'NORMAL';
    //global $cnum;
    //$main = get_main();     //get the basic stats
    //out_data($main);          //output the main data
    $c = get_advisor();     //c as in country! (get the advisor)
    log_country_message($cnum, $c->turns.' turns left');
    log_country_message($cnum, 'Explore Rate: '.$c->explore_rate.'; Min Rate: '.$c->explore_min);
    //out_data($c) && exit;             //ouput the advisor data
    log_country_message($cnum, "Agri: {$c->pt_agri}%; Bus: {$c->pt_bus}%; Res: {$c->pt_res}%");
    if ($c->govt == 'M' && $c->turns_played < 100) {
        $rand = rand(0, 100);
        switch ($rand) {
            case $rand < 4:
                Government::change($c, 'F');
                break;
            case $rand < 8:
                Government::change($c, 'T');
                break;
            case $rand < 12:
                Government::change($c, 'I');
                break;
            case $rand < 16:
                Government::change($c, 'C');
                break;
            case $rand < 20:
                Government::change($c, 'H');
                break;
            case $rand < 24:
                Government::change($c, 'R');
                break;
            case $rand < 28:
                Government::change($c, 'D');
                break;
            default:
                break;
        }
    }

    if ($c->m_spy > 10000) {
        Allies::fill($cpref, 'spy');
    }

    if ($c->b_lab > 2000) {
        Allies::fill($cpref, 'res');
    }

    if ($c->m_j > 1000000) {
        Allies::fill($cpref, 'off');
    }

    //because why not?
    // it's confusing for players, especially new ones - Slagpit
    //if ($c->govt == 'M') {
    //    Allies::fill($cpref, 'trade');
    //}

    //get the PM info
    //$market_info = get_market_info();   //get the Public Market info
    //out_data($market_info);       //output the PM info

    //find out what we have on the market
    $owned_on_market_info = get_owned_on_market_info();
    //out_data($market_info);   //output the Public Market info
    //var_export($owned_on_market_info);

    $turn_action_counts = []; // NOTE: this isn't properly set up for rainbows, but money_management() needs it
    while ($c->turns > 0) {
        //$result = PublicMarket::buy($c,array('m_bu'=>100),array('m_bu'=>400));
                
        $result = play_rainbow_turn($c, $rules->market_autobuy_tech_price, $rules->max_possible_market_sell);
        if ($result === false) {  //UNEXPECTED RETURN VALUE
            $c = get_advisor();     //UPDATE EVERYTHING
            continue;
        }
        update_c($c, $result);
        if (!$c->turns % 5) {                   //Grab new copy every 5 turns
            $c->updateMain(); //we probably don't need to do this *EVERY* turn
        }

        // management is here to make sure that goods can be sold
        $hold = money_management($c, $rules->max_possible_market_sell, $cpref, $turn_action_counts);
        if ($hold) {
            break; //HOLD TURNS HAS BEEN DECLARED; HOLD!!
        }

        $hold = food_management($c, $cpref);
        if ($hold) {
            $exit_condition = 'WAIT_FOR_PUBLIC_MARKET_FOOD'; 
            break; //HOLD TURNS HAS BEEN DECLARED; HOLD!!
        }

        if (turns_of_food($c) > 70
            && turns_of_money($c) > 70
            && $c->money > 3500 * 500
            && ($c->built() > 80 || $c->money > $c->fullBuildCost() - $c->runCash())
        ) {
            // 70 turns of food
            // keep enough money to build out everything
            $spend = $c->money - $c->fullBuildCost() - $c->runCash();

            if ($spend > abs($c->income) * 10) {
                //try to batch a little bit...
                buy_rainbow_goals($c, $spend);
            }
        }

        if ($c->income < 0 && total_military($c) > 30) { //sell 1/4 of all military on PM
            log_error_message(1002, $cnum, "Losing money! Sell 1/4 of our military!");
            sell_all_military($c, 1 / 4);  //sell 1/4 of our military
        }



        //$main->turns = 0;             //use this to do one turn at a time
    }

    $c->countryStats(RAINBOW, rainbowGoals($c));
    return $c;
}//end play_rainbow_strat()


function play_rainbow_turn(&$c, $market_autobuy_tech_price, $server_max_possible_market_sell)
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
    } elseif ($c->shouldBuildFullBPT($target_bpt)) {
        //build a full BPT if we can afford it
        return build_rainbow($c);
    } elseif ($c->shouldBuildFourCS($target_bpt)) {
        //build 4CS if we can afford it and are below our target BPT (80)
        return Build::cs(4); //build 4 CS
    } elseif ($c->tpt > $c->land * 0.10 && rand(0, 10) > 5) {
        //tech per turn is greater than land*0.17 -- just kindof a rough "don't tech below this" rule... lower for rainbow
        return tech_rainbow($c, max(1, min(turns_of_money($c), turns_of_food($c), 13, $c->turns + 2) - 3));
    } elseif ($c->built() > 50) {  //otherwise... explore if we can
        if ($c->explore_rate == $c->explore_min) {
            return explore($c, min(5, max(1, $c->turns - 1), max(1, min(turns_of_money($c), turns_of_food($c)) - 3)));
        } else {
            return explore($c, min(max(1, $c->turns - 1), max(1, min(turns_of_money($c), turns_of_food($c)) - 3)));
        }
    } elseif ($c->foodnet > 0 && $c->foodnet > 3 * $c->foodcon && $c->food > 30 * $c->foodnet && $c->food > 7000) {
        return sellextrafood_rainbow($c);
    } elseif ($c->protection == 0 && total_cansell_tech($c, $server_max_possible_market_sell) > 20 * $c->tpt && selltechtime($c)
        || $c->turns == 1 && total_cansell_tech($c, $server_max_possible_market_sell) > 20
    ) {
        //never sell less than 20 turns worth of tech
        return sell_max_tech($c, $cpref, $market_autobuy_tech_price, $server_max_possible_market_sell);
    } else { //otherwise...  cash
        return cash($c);
    }
}//end play_rainbow_turn()


function sellextrafood_rainbow(&$c)
{
    global $market_info;

    //log_country_message($c->cnum, "Lots of food, let's sell some!");
    $pm_info = PrivateMarket::getRecent();

    $c = get_advisor();     //UPDATE EVERYTHING
    if (!is_object($pm_info) || !is_object($pm_info->sell_price)) {
        log_country_message($c->cnum, "Update PM");
        $pm_info = PrivateMarket::getInfo();
        //out_data($pm_info);       //output the PM info
        //log_country_message($c->cnum, 'here?');
    }

    if (!is_object($market_info) || !is_object($market_info->buy_price)) {
        log_country_message($c->cnum, "Update market");
        $market_info = get_market_info();   //get the Public Market info
    }

    $quantity = ['m_bu' => $c->food]; //sell it all! :)
    $price    = round(max($pm_info->sell_price->m_bu, $market_info->buy_price->m_bu) * rand(80, 120) / 100);
    $price    = ['m_bu' => $price];

    if ($quantity > 5000 || !is_object($c)) {
        log_country_message($c->cnum, "Sell Public ".$quantity->m_bu);
        return PublicMarket::sell($c, $quantity, $price);    //Sell food!
    } else {
        log_country_message($c->cnum, "Can't Sell!");
    }
}//end sellextrafood_rainbow()


function build_rainbow(&$c)
{
    if ($c->foodnet < 0) { //build farms if we are foodnet < 0
        return Build::farmer($c);
    } elseif ($c->income < max(100000, 2 * $c->build_cost * $c->bpt / $c->explore_rate)) {
        //build ent/res if we're not making more than enough to keep building continually at least $100k
        if (rand(0, 100) > 50 && $c->income > $c->build_cost * $c->bpt / $c->explore_rate) {
            if (($c->tpt < $c->land && rand(0, 100) > 10) || rand(0, 100) > 40) {
                return Build::techer($c);
            } else {
                return Build::indy($c);
            }
        } else {
            return Build::casher($c);
        }
    } else { //build indies or labs
        if (($c->tpt < $c->land && rand(0, 100) > 10) || rand(0, 100) > 40) {
            return Build::techer($c);
        } else {
            return Build::indy($c);
        }
    }
}//end build_rainbow()


function tech_rainbow(&$c, $turns = 1)
{
    //lets do random weighting... to some degree
    //$market_info = get_market_info();   //get the Public Market info
    //global $market;

    $techfloor = 600;

    $mil  = max(pow(PublicMarket::price('mil') - $techfloor, 2), rand(0, 30000));
    $med  = max(pow(PublicMarket::price('med') - $techfloor, 2), rand(0, 500));
    $bus  = max(pow(PublicMarket::price('bus') - $techfloor, 2), rand(10, 40000));
    $res  = max(pow(PublicMarket::price('res') - $techfloor, 2), rand(10, 40000));
    $agri = max(pow(PublicMarket::price('agri') - $techfloor, 2), rand(10, 30000));
    $war  = max(pow(PublicMarket::price('war') - $techfloor, 2), rand(0, 1000));
    $ms   = max(pow(PublicMarket::price('ms') - $techfloor, 2), rand(0, 2000));
    $weap = max(pow(PublicMarket::price('weap') - $techfloor, 2), rand(0, 2000));
    $indy = max(pow(PublicMarket::price('indy') - $techfloor, 2), rand(5, 30000));
    $spy  = max(pow(PublicMarket::price('spy') - $techfloor, 2), rand(0, 1000));
    $sdi  = max(pow(PublicMarket::price('sdi') - $techfloor, 2), rand(2, 15000));
    $tot  = $mil + $med + $bus + $res + $agri + $war + $ms + $weap + $indy + $spy + $sdi;

    $turns = max(1, min($turns, $c->turns));
    $left  = $c->tpt * $turns;
    $left -= $mil = min($left, floor($c->tpt * $turns * ($mil / $tot)));
    $left -= $med = min($left, floor($c->tpt * $turns * ($med / $tot)));
    $left -= $bus = min($left, floor($c->tpt * $turns * ($bus / $tot)));
    $left -= $res = min($left, floor($c->tpt * $turns * ($res / $tot)));
    $left -= $agri = min($left, floor($c->tpt * $turns * ($agri / $tot)));
    $left -= $war = min($left, floor($c->tpt * $turns * ($war / $tot)));
    $left -= $ms = min($left, floor($c->tpt * $turns * ($ms / $tot)));
    $left -= $weap = min($left, floor($c->tpt * $turns * ($weap / $tot)));
    $left -= $indy = min($left, floor($c->tpt * $turns * ($indy / $tot)));
    $left -= $spy = min($left, floor($c->tpt * $turns * ($spy / $tot)));
    $left -= $sdi = max($left, min($left, floor($c->tpt * $turns * ($sdi / $tot))));
    if ($left != 0) {
        die("What the hell? rainbow");
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
}//end tech_rainbow()



function buy_rainbow_goals(&$c, $spend = null)
{
    Country::countryGoals($c, rainbowGoals($c), $spend);
}//end buy_rainbow_goals()


function rainbowGoals(&$c)
{
    return [
        //what, goal, priority
        ['t_agri',160,5],
        ['t_indy',130,5],
        ['t_bus',145,7],
        ['t_res',145,7],
        ['t_mil',94,5],
        ['nlg',$c->nlgTarget(),5],
        ['dpa',$c->defPerAcreTarget(),10],
    ];
}//end rainbowGoals()
