<?php

namespace EENPC;

$military_list = ['m_tr','m_j','m_tu','m_ta'];

function play_indy_strat($server, $cnum, $rules)
{
    //global $cnum;
    //$main = get_main();     //get the basic stats
    //out_data($main);          //output the main data
    $c = get_advisor();     //c as in country! (get the advisor)
    $c->setIndyFromMarket(false); // changing to not check DPA - Slagpit 20210321
    log_country_message($cnum, "Indy: {$c->pt_indy}%; Bus: {$c->pt_bus}%; Res: {$c->pt_res}%");
    //out_data($c) && exit;             //ouput the advisor data
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
    log_country_message($cnum, $c->turns.' turns left');
    log_country_message($cnum, 'Explore Rate: '.$c->explore_rate.'; Min Rate: '.$c->explore_min);
    //$pm_info = get_pm_info();   //get the PM info
    //out_data($pm_info);       //output the PM info
    //$market_info = get_market_info();   //get the Public Market info
    //out_data($market_info);       //output the PM info

    if ($c->m_spy > 10000) {
        Allies::fill('spy');
    }

    $owned_on_market_info = get_owned_on_market_info(); //find out what we have on the market
    //out_data($owned_on_market_info);  //output the Owned on Public Market info

    // indies buy tech instead of building when no limit on goals here- Slagpit 20210321
    // the 80% is here because indies seemed to not be buying tech through 800 turns of play
    buy_indy_goals($c, $c->money - floor(0.8 * $c->fullBuildCost()) - $c->runCash());

    while ($c->turns > 0) {
        //$result = PublicMarket::buy($c,array('m_bu'=>100),array('m_bu'=>400));
                
        $result = play_indy_turn($c, $rules->max_possible_market_sell);
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
            break; //HOLD TURNS HAS BEEN DECLARED; HOLD!!
        }

        if (turns_of_food($c) > (30 + $c->turns) && turns_of_money($c) > (30 + $c->turns) && $c->money > 3500 * 500 && ($c->built() > 80
           || $c->money > $c->fullBuildCost() - $c->runCash())
        ) {
            //keep enough money to build out everything
            buy_indy_goals($c, $c->money - $c->fullBuildCost() - $c->runCash());
        }

        // why does this code exist???
        /*
        global $cpref;
        $tol = $cpref->price_tolerance; //should be between 0.5 and 1.5
        if (turns_of_food($c) > 50 && turns_of_money($c) > 50 && $c->money > 3500 * 500) {
        // 40 turns of food, and more than 2x nw in cash on hand
            //log_country_message($cnum, "Try to buy tech?");
            //min what we'll use in max(20,turns-left) turns basically
            $spend = min($c->money, $c->money + max(20, $c->turns) * $c->income) * 0.4;

            if ($c->pt_indy < 158) {
                PublicMarket::buy_tech($c, 't_indy', $spend * 2 / 5, 3500 * $tol);
            }
            if ($c->pt_mil > 90) {
                PublicMarket::buy_tech($c, 't_mil', $spend * 1 / 5, 3500 * $tol);
            }
            if ($c->pt_bus < 160) {
                PublicMarket::buy_tech($c, 't_bus', $spend * 1 / 5, 3500 * $tol);
            }
            if ($c->pt_res < 160) {
                PublicMarket::buy_tech($c, 't_res', $spend * 1 / 5, 3500 * $tol);
            }
            
        }
        */
    }

    $c->countryStats(INDY, indyGoals($c));

    return $c;
}//end play_indy_strat()


function play_indy_turn(&$c, $server_max_possible_market_sell)
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
        if ($c->explore_rate == $c->explore_min) {
            return explore(
                $c,
                min(5, max(1, $c->turns - 1), max(1, min(turns_of_money($c) / 1.15, turns_of_food($c)) - 3))
            );
        } else {
            // don't let indies ME more than 100 turns at a time because they have money management problems
            return explore(
                $c,
                min(100, max(1, $c->turns - 1), max(1, min(turns_of_money($c) / 1.15, turns_of_food($c)) - 3)) 
            );
        }
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


function buy_indy_goals(&$c, $spend = null)
{
    $goals = indyGoals($c);

    Country::countryGoals($c, $goals, $spend);
}//end buy_indy_goals()


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
