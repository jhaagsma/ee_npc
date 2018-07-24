<?php

namespace EENPC;

function play_casher_strat($server)
{
    global $cnum;
    out("Playing ".CASHER." Turns for #$cnum ".siteURL($cnum));
    //$main = get_main();     //get the basic stats
    //out_data($main);          //output the main data
    $c = get_advisor();     //c as in country! (get the advisor)
    //out_data($c) && exit;             //ouput the advisor data

    $c->setIndy('pro_spy');

    if ($c->m_spy > 10000) {
        Allies::fill('spy');
    }

    out("Bus: {$c->pt_bus}%; Res: {$c->pt_res}%");
    if ($c->govt == 'M') {
        $rand = rand(0, 100);
        switch ($rand) {
            case $rand < 12:
                Government::change($c, 'I');
                break;
            case $rand < 12:
                Government::change($c, 'D');
                break;
            default:
                Government::change($c, 'R');
                break;
        }
    }

    out($c->turns.' turns left');
    out('Explore Rate: '.$c->explore_rate.'; Min Rate: '.$c->explore_min);
    //$pm_info = get_pm_info(); //get the PM info
    //out_data($pm_info);       //output the PM info
    //$market_info = get_market_info(); //get the Public Market info
    //out_data($market_info);       //output the PM info

    $owned_on_market_info = get_owned_on_market_info();     //find out what we have on the market
    //out_data($owned_on_market_info);  //output the Owned on Public Market info

    while ($c->turns > 0) {
        //$result = PublicMarket::buy($c,array('m_bu'=>100),array('m_bu'=>400));
        $result = play_casher_turn($c);
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

        if (turns_of_food($c) > 40
            && $c->money > 3500 * 500
            && ($c->built() > 80 || $c->money > $c->fullBuildCost())
        ) { // 40 turns of food
            $spend = $c->money - $c->fullBuildCost(); //keep enough money to build out everything

            if ($spend > $c->income * 7) {
                //try to batch a little bit...
                buy_casher_goals($c, $spend);
            }
        }
    }

    $c->countryStats(CASHER, casherGoals($c));
    return $c;
}//end play_casher_strat()


function play_casher_turn(&$c)
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
    } elseif ($c->shouldBuildSpyIndies()) {
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
        if ($c->explore_rate == $c->explore_min) {
            return explore($c, min(5, $c->turns, max(1, turns_of_food($c) - 3)));
        } else {
            return explore($c, min($c->turns, max(1, turns_of_food($c) - 3)));
        }
    } else {
        //otherwise...  cash
        return cash($c);
    }
}//end play_casher_turn()


function buy_casher_goals(&$c, $spend = null)
{
    Country::countryGoals($c, casherGoals($c), $spend);
}//end buy_casher_goals()


function casherGoals(&$c)
{
    return [
        //what, goal, priority
        ['t_bus',178,800],
        ['t_res',178,800],
        ['t_mil',94,100],
        ['nlg',$c->nlgTarget(),200],
        ['dpa',$c->defPerAcreTarget(1.0),400],
        ['food', 1000000000, 1],
    ];
}//end casherGoals()
