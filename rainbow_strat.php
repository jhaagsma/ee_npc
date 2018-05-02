<?php

namespace EENPC;

function play_rainbow_strat($server)
{
    global $cnum;
    out("Playing ".RAINBOW." turns for #$cnum ".siteURL($cnum));
    //$main = get_main();     //get the basic stats
    //out_data($main);          //output the main data
    $c = get_advisor();     //c as in country! (get the advisor)
    out($c->turns.' turns left');
    out('Explore Rate: '.$c->explore_rate.'; Min Rate: '.$c->explore_min);
    //out_data($c) && exit;             //ouput the advisor data
    out("Agri: {$c->pt_agri}%; Bus: {$c->pt_bus}%; Res: {$c->pt_res}%");
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
        Allies::fill('spy');
    }

    if ($c->b_lab > 2000) {
        Allies::fill('res');
    }

    if ($c->m_j > 1000000) {
        Allies::fill('off');
    }

    //because why n
    if ($c->govt == 'M') {
        Allies::fill('trade');
    }

    //get the PM info
    //$pm_info = get_pm_info();
    //out_data($pm_info);       //output the PM info
    //$market_info = get_market_info();   //get the Public Market info
    //out_data($market_info);       //output the PM info

    //find out what we have on the market
    $owned_on_market_info = get_owned_on_market_info();
    //out_data($market_info);   //output the Public Market info
    //var_export($owned_on_market_info);

    while ($c->turns > 0) {
        //$result = PublicMarket::buy($c,array('m_bu'=>100),array('m_bu'=>400));
        $result = play_rainbow_turn($c);
        if ($result === false) {  //UNEXPECTED RETURN VALUE
            $c = get_advisor();     //UPDATE EVERYTHING
            continue;
        }
        update_c($c, $result);
        if (!$c->turns % 5) {                   //Grab new copy every 5 turns
            $c->updateMain(); //we probably don't need to do this *EVERY* turn
        }

        $hold = money_management($c);
        if ($hold) {
            break; //HOLD TURNS HAS BEEN DECLARED; HOLD!!
        }

        $hold = food_management($c);
        if ($hold) {
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
            out("Losing money! Sell 1/4 of our military!");     //Text for screen
            sell_all_military($c, 1 / 4);  //sell 1/4 of our military
        }



        //$main->turns = 0;             //use this to do one turn at a time
    }

    $c->countryStats(RAINBOW, rainbowGoals($c));
    return $c;
}//end play_rainbow_strat()


function play_rainbow_turn(&$c)
{
 //c as in country!
    $target_bpt = 65;
    global $turnsleep;
    usleep($turnsleep);
    //out($main->turns . ' turns left');
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
        return tech_techer($c, max(1, min(turns_of_money($c), turns_of_food($c), 13, $c->turns + 2) - 3));
        //return tech_rainbow($c);
    } elseif ($c->built() > 50) {  //otherwise... explore if we can
        if ($c->explore_rate == $c->explore_min) {
            return explore($c, min(5, max(1, $c->turns - 1), max(1, min(turns_of_money($c), turns_of_food($c)) - 3)));
        } else {
            return explore($c, min(max(1, $c->turns - 1), max(1, min(turns_of_money($c), turns_of_food($c)) - 3)));
        }
    } elseif ($c->foodnet > 0 && $c->foodnet > 3 * $c->foodcon && $c->food > 30 * $c->foodnet && $c->food > 7000) {
        return sellextrafood_rainbow($c);
    } elseif ($c->protection == 0 && total_cansell_tech($c) > 20 * $c->tpt && selltechtime($c)
        || $c->turns == 1 && total_cansell_tech($c) > 20
    ) {
        //never sell less than 20 turns worth of tech
        return sell_max_tech($c);
    } else { //otherwise...  cash
        return cash($c);
    }
}//end play_rainbow_turn()


function sellextrafood_rainbow(&$c)
{
    global $market_info;

    //out("Lots of food, let's sell some!");
    $pm_info = PrivateMarket::getRecent();

    $c = get_advisor();     //UPDATE EVERYTHING
    if (!is_object($pm_info) || !is_object($pm_info->sell_price)) {
        out("Update PM");
        $pm_info = PrivateMarket::getInfo();
        //out_data($pm_info);       //output the PM info
        //out('here?');
    }

    if (!is_object($market_info) || !is_object($market_info->buy_price)) {
        out("Update market");
        $market_info = get_market_info();   //get the Public Market info
    }

    $quantity = ['m_bu' => $c->food]; //sell it all! :)
    $price    = round(max($pm_info->sell_price->m_bu, $market_info->buy_price->m_bu) * rand(80, 120) / 100);
    $price    = ['m_bu' => $price];

    if ($quantity > 5000 || !is_object($c)) {
        out("Sell Public ".$quantity->m_bu);
        return PublicMarket::sell($c, $quantity, $price);    //Sell food!
    } else {
        out("Can't Sell!");
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


function tech_rainbow(&$c, $turns=1)
{
    //lets do random weighting... to some degree
    $mil  = rand(0, 25);
    $med  = rand(0, 5);
    $bus  = rand(10, 100);
    $res  = rand(10, 100);
    $agri = rand(10, 100);
    $war  = rand(0, 10);
    $ms   = rand(0, 20);
    $weap = rand(0, 20);
    $indy = rand(5, 40);
    $spy  = rand(0, 10);
    $sdi  = rand(2, 15);
    $tot  = $mil + $med + $bus + $res + $agri + $war + $ms + $weap + $indy + $spy + $sdi;

    $left  = $c->tpt;
    $left -= $mil  = min($left, floor($c->tpt * ($mil / $tot)));
    $left -= $med  = min($left, floor($c->tpt * ($med / $tot)));
    $left -= $bus  = min($left, floor($c->tpt * ($bus / $tot)));
    $left -= $res  = min($left, floor($c->tpt * ($res / $tot)));
    $left -= $agri = min($left, floor($c->tpt * ($agri / $tot)));
    $left -= $war  = min($left, floor($c->tpt * ($war / $tot)));
    $left -= $ms   = min($left, floor($c->tpt * ($ms / $tot)));
    $left -= $weap = min($left, floor($c->tpt * ($weap / $tot)));
    $left -= $indy = min($left, floor($c->tpt * ($indy / $tot)));
    $left -= $spy  = min($left, floor($c->tpt * ($spy / $tot)));
    $left -= $sdi = max($left, min($left, floor($c->tpt * ($spy / $tot))));
    if ($left != 0) {
        out("What the hell?");
        return;
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
        ],
        $turns
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
        ['t_agri',225,5],
        ['t_indy',160,5],
        ['t_bus',178,7],
        ['t_res',178,7],
        ['t_mil',94,5],
        ['nlg',$c->nlgTarget(),5],
        ['dpa',$c->defPerAcreTarget(),10],
    ];
}//end rainbowGoals()
