<?php namespace EENPC;

$military_list = array('m_tr','m_j','m_tu','m_ta');

function play_indy_strat($server)
{
    global $cnum;
    out("Playing ".INDY." Turns for #$cnum");
    //$main = get_main();     //get the basic stats
    //out_data($main);          //output the main data
    $c = get_advisor();     //c as in country! (get the advisor)
    $c->setIndyFromMarket();
    //$c = get_advisor();     //c as in country! (get the advisor)
    out("Indy: {$c->pt_indy}%; Bus: {$c->pt_bus}%; Res: {$c->pt_res}%");
    //out_data($c) && exit;             //ouput the advisor data
    if ($c->govt == 'M') {
        $rand = rand(0, 100);
        switch ($rand) {
            case $rand < 5:
                change_govt($c, 'I');
                break;
            case $rand < 5:
                change_govt($c, 'D');
                break;
            default:
                change_govt($c, 'C');
                break;
        }
    }
    out($c->turns.' turns left');
    $pm_info = get_pm_info();   //get the PM info
    //out_data($pm_info);       //output the PM info
    //$market_info = get_market_info();   //get the Public Market info
    //out_data($market_info);       //output the PM info

    $owned_on_market_info = get_owned_on_market_info();     //find out what we have on the market
    //out_data($owned_on_market_info);  //output the Owned on Public Market info

    while ($c->turns > 0) {
        //$result = buy_public($c,array('m_bu'=>100),array('m_bu'=>400));
        $result = play_indy_turn($c);
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

        if (turns_of_food($c) > 70 && turns_of_money($c) > 70 && $c->money > 3500 * 500 && ($c->built() > 80 || $c->money > $c->fullBuildCost() - $c->runCash())) { // 40 turns of food
            buy_indy_goals($c, $c->money - $c->fullBuildCost() - $c->runCash()); //keep enough money to build out everything
        }

        global $cpref;
        $tol = $cpref->price_tolerance; //should be between 0.5 and 1.5
        if (turns_of_food($c) > 50 && turns_of_money($c) > 50 && $c->money > 3500 * 500) { // 40 turns of food, and more than 2x nw in cash on hand
            //out("Try to buy tech?");
            $spend = min($c->money, $c->money + max(20, $c->turns) * $c->income) * 0.4; //min what we'll use in max(20,turns-left) turns basically

            if ($c->pt_indy < 150) {
                buy_tech($c, 't_indy', $spend * 2 / 5, 3500 * $tol);
            }
            if ($c->pt_mil > 90) {
                buy_tech($c, 't_mil', $spend * 1 / 5, 3500 * $tol);
            }
            if ($c->pt_bus < 160) {
                buy_tech($c, 't_bus', $spend * 1 / 5, 3500 * $tol);
            }
            if ($c->pt_res < 160) {
                buy_tech($c, 't_res', $spend * 1 / 5, 3500 * $tol);
            }
        }
    }

    $c->countryStats(INDY, indyGoals($c));
}//end play_indy_strat()


function play_indy_turn(&$c)
{
 //c as in country!
    $target_bpt = 65;
    global $turnsleep;
    usleep($turnsleep);
    //out($main->turns . ' turns left');
    if (($c->empty && $c->bpt < 30 && $c->built() <= 50 && $c->money > $c->build_cost) || ($c->empty && $c->bpt < $target_bpt && $c->b_cs % 4 != 0 && $c->money > $c->build_cost)) { //otherwise... build one CS if we can afford it and are below our target BPT (80)
        return build_cs(); //build 1 CS
    } elseif ($c->protection == 0 && total_cansell_military($c) > 7500 && sellmilitarytime($c) || $c->turns == 1 && total_cansell_military($c) > 7500) {
        return sell_max_military($c);
    } elseif ($c->empty > $c->bpt && $c->money > $c->bpt * $c->build_cost + ($c->income > 0 ? 0 : $c->income * -60)) {  //build a full BPT if we can afford it
        return build_indy($c);
    } elseif ($c->turns >= 4 && $c->empty >= 4 && $c->bpt < $target_bpt && $c->money > 4 * $c->build_cost && ($c->foodnet > 0 || $c->food > $c->foodnet * -5)) { //otherwise... build 4CS if we can afford it and are below our target BPT (80)
        return build_cs(4); //build 4 CS
    } elseif ($c->built() > 50) {  //otherwise... explore if we can
        return explore($c, min(max(1, $c->turns - 1), max(1, min(turns_of_money($c) / 1.15, turns_of_food($c)) - 3))); //1.15 is my growth factor for indies
    } elseif ($c->empty && $c->bpt < $target_bpt && $c->money > $c->build_cost) { //otherwise... build one CS if we can afford it and are below our target BPT (80)
        return build_cs(); //build 1 CS
    } else { //otherwise...  cash
        return cash($c);
    }
}//end play_indy_turn()


function build_indy(&$c)
{
    //build farms
    return build(array('indy' => $c->bpt));
}//end build_indy()



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

    $c->countryGoals($goals, $spend);
}//end buy_indy_goals()


function indyGoals(&$c)
{
    return [
        //what, goal, priority
        ['t_indy',150,8],
        ['t_bus',160,3],
        ['t_res',160,3],
        ['t_mil',94,4],
    ];
}//end indyGoals()
