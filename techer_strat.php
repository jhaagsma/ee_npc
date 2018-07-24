<?php

namespace EENPC;

$techlist = ['t_mil','t_med','t_bus','t_res','t_agri','t_war','t_ms','t_weap','t_indy','t_spy','t_sdi'];

function play_techer_strat($server)
{
    global $cnum;
    out("Playing ".TECHER." Turns for #$cnum ".siteURL($cnum));
    //$main = get_main();     //get the basic stats
    //out_data($main);          //output the main data
    $c = get_advisor();     //c as in country! (get the advisor)
    $c->setIndy('pro_spy');


    if ($c->m_spy > 10000) {
        Allies::fill('spy');
    }

    if ($c->b_lab > 2000) {
        Allies::fill('res');
    }

    out($c->turns.' turns left');
    out('Explore Rate: '.$c->explore_rate.'; Min Rate: '.$c->explore_min);

    if ($c->govt == 'M') {
        $rand = rand(0, 100);
        switch ($rand) {
            case $rand < 40:
                Government::change($c, 'H');
                break;
            case $rand < 80:
                Government::change($c, 'D');
                break;
            default:
                Government::change($c, 'T');
                break;
        }
    }

    //out_data($c);             //ouput the advisor data
    //$pm_info = get_pm_info();   //get the PM info
    //out_data($pm_info);       //output the PM info
    //$market_info = get_market_info();   //get the Public Market info
    //out_data($market_info);       //output the PM info

    $owned_on_market_info = get_owned_on_market_info();     //find out what we have on the market
    //out_data($owned_on_market_info); //output the owned on market info


    while ($c->turns > 0) {
        //$result = PublicMarket::buy($c,array('m_bu'=>100),array('m_bu'=>400));
        $result = play_techer_turn($c);
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

        if (turns_of_food($c) > 50 && turns_of_money($c) > 50 && $c->money > 3500 * 500 && ($c->built() > 80 || $c->money > $c->fullBuildCost() - $c->runCash()) && $c->tpt > 200) { // 40 turns of food
            buy_techer_goals($c, $c->money - $c->fullBuildCost() - $c->runCash()); //keep enough money to build out everything
        }
    }
    $c->countryStats(TECHER, techerGoals($c));

    return $c;
}//end play_techer_strat()


function play_techer_turn(&$c)
{
 //c as in country!
    $target_bpt = 65;
    global $turnsleep, $mktinfo, $server_avg_land;
    $mktinfo = null;
    usleep($turnsleep);
    //out($main->turns . ' turns left');


    if ($c->shouldBuildSingleCS($target_bpt)) {
        //LOW BPT & CAN AFFORD TO BUILD
        //build one CS if we can afford it and are below our target BPT
        return Build::cs(); //build 1 CS
    } elseif ($c->protection == 0 && total_cansell_tech($c) > 20 * $c->tpt && selltechtime($c)
        || $c->turns == 1 && total_cansell_tech($c) > 20
    ) {
        //never sell less than 20 turns worth of tech
        //always sell if we can????
        return sell_max_tech($c);
    } elseif ($c->shouldBuildSpyIndies()) {
        //build a full BPT of indies if we have less than that, and we're out of protection
        return Build::indy($c);
    } elseif ($c->shouldBuildFullBPT($target_bpt)) {
        //build a full BPT if we can afford it
        return Build::techer($c);
    } elseif ($c->shouldBuildFourCS($target_bpt)) {
        //build 4CS if we can afford it and are below our target BPT (80)
        return Build::cs(4); //build 4 CS
    } elseif ($c->tpt > $c->land * 0.17 * 1.3 && $c->tpt > 100 && rand(0, 100) > 2) {
        //tech per turn is greater than land*0.17 -- just kindof a rough "don't tech below this" rule...
        //so, 10 if they can... cap at turns - 1
        return tech_techer($c, max(1, min(turns_of_money($c), turns_of_food($c), 13, $c->turns + 2) - 3));
    } elseif ($c->built() > 50
        && ($c->land < 5000 || rand(0, 100) > 95 && $c->land < $server_avg_land)
    ) {
        //otherwise... explore if we can, for the early bits of the set
        if ($c->explore_rate == $c->explore_min) {
            return explore($c, min(5, max(1, $c->turns - 1), max(1, min(turns_of_money($c), turns_of_food($c)) - 3)));
        } else {
            return explore($c, min(max(1, $c->turns - 1), max(1, min(turns_of_money($c), turns_of_food($c)) - 3)));
        }
    } else { //otherwise, tech, obviously
        //so, 10 if they can... cap at turns - 1
        return tech_techer($c, max(1, min(turns_of_money($c), turns_of_food($c), 13, $c->turns + 2) - 3));
    }
}//end play_techer_turn()



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


function sell_max_tech(&$c)
{
    $c = get_advisor();     //UPDATE EVERYTHING
    $c->updateOnMarket();

    //$market_info = get_market_info();   //get the Public Market info
    //global $market;

    $quantity = [
        'mil' => can_sell_tech($c, 't_mil'),
        'med' => can_sell_tech($c, 't_med'),
        'bus' => can_sell_tech($c, 't_bus'),
        'res' => can_sell_tech($c, 't_res'),
        'agri' => can_sell_tech($c, 't_agri'),
        'war' => can_sell_tech($c, 't_war'),
        'ms' => can_sell_tech($c, 't_ms'),
        'weap' => can_sell_tech($c, 't_weap'),
        'indy' => can_sell_tech($c, 't_indy'),
        'spy' => can_sell_tech($c, 't_spy'),
        'sdi' => can_sell_tech($c, 't_sdi')
    ];

    if (array_sum($quantity) == 0) {
        out('Techer computing Zero Sell!');
        $c = get_advisor();
        $c->updateOnMarket();

        Debug::on();
        Debug::msg('This Quantity: '.array_sum($quantity).' TotalCanSellTech: '.total_cansell_tech($c));
        return;
    }


    $nogoods_high   = 9000;
    $nogoods_low    = 2000;
    $nogoods_stddev = 1500;
    $nogoods_step   = 1;
    $rmax           = 1.30; //percent
    $rmin           = 0.80; //percent
    $rstep          = 0.01;
    $rstddev        = 0.10;
    $price          = [];
    foreach ($quantity as $key => $q) {
        if ($q == 0) {
            $price[$key] = 0;
        } elseif (PublicMarket::price($key) != null) {
            // additional check to make sure we aren't repeatedly undercutting with minimal goods
            if ($q < 100 && PublicMarket::available($key) < 1000) {
                $price[$key] = PublicMarket::price($key);
            } else {
                Debug::msg("sell_max_tech:A:$key");
                $max = $c->goodsStuck($key) ? 0.98 : $rmax; //undercut if we have goods stuck
                Debug::msg("sell_max_tech:B:$key");

                $price[$key] = min(
                    9999,
                    floor(PublicMarket::price($key) * Math::purebell($rmin, $max, $rstddev, $rstep))
                );

                Debug::msg("sell_max_tech:C:$key");
            }
        } else {
            $price[$key] = floor(Math::purebell($nogoods_low, $nogoods_high, $nogoods_stddev, $nogoods_step));
        }
    }

    $result = PublicMarket::sell($c, $quantity, $price);
    if ($result == 'QUANTITY_MORE_THAN_CAN_SELL') {
        out("TRIED TO SELL MORE THAN WE CAN!?!");
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
function tech_techer(&$c, $turns = 1)
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
        die("What the hell?");
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



function buy_techer_goals(&$c, $spend = null)
{
    $goals = techerGoals($c);
    Country::countryGoals($c, $goals, $spend);
}//end buy_techer_goals()


function techerGoals(&$c)
{
    return [
        //what, goal, priority
        ['dpa', $c->defPerAcreTarget(1.0), 2],
        ['nlg', $c->nlgTarget(),2 ],
    ];
}//end techerGoals()
