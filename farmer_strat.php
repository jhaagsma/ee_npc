<?php
/**
 * Farmer strategy
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
function play_farmer_strat($server)
{
    global $cnum, $pm_info;
    out("Playing ".FARMER." turns for #$cnum");
    //$main = get_main();     //get the basic stats
    //out_data($main);          //output the main data
    $c = get_advisor();     //c as in country! (get the advisor)
    $c->setIndy('pro_spy');
    //$c = get_advisor();     //c as in country! (get the advisor)

    out("Agri: {$c->pt_agri}%; Bus: {$c->pt_bus}%; Res: {$c->pt_res}%");
    //out_data($c) && exit;             //ouput the advisor data
    if ($c->govt == 'M') {
        $rand = rand(0, 100);
        switch ($rand) {
            case $rand < 12:
                change_govt($c, 'D');
                break;
            case $rand < 20:
                change_govt($c, 'I');
                break;
            case $rand < 50:
                change_govt($c, 'R');
                break;
            default:
                change_govt($c, 'F');
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
        $result = play_farmer_turn($c);
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


        if ($c->income < 0 && $c->money < -5 * $c->income) { //sell 1/4 of all military on PM
            out("Almost out of money! Sell 10 turns of income in food!");   //Text for screen
            sell_on_pm($c, array('m_bu' => min($c->food, floor(-10 * $c->income / $pm_info->sell_price->m_bu))));     //sell 1/4 of our military
        }

        if (turns_of_food($c) > 50 && turns_of_money($c) > 50 && $c->money > 3500 * 500 && ($c->built() > 80 || $c->money > $c->fullBuildCost())) { // 40 turns of food
            buy_farmer_goals($c, $c->money - $c->fullBuildCost()); //keep enough money to build out everything
        }
    }

    $c->countryStats(FARMER, farmerGoals($c));
}//end play_farmer_strat()

function play_farmer_turn(&$c)
{
 //c as in country!
    $target_bpt = 65;
    global $turnsleep;
    usleep($turnsleep);
    //out($main->turns . ' turns left');
    if (($c->empty && $c->bpt < 30 && $c->built() <= 50 && $c->money > $c->build_cost) || ($c->empty && $c->bpt < $target_bpt && $c->b_cs % 4 != 0 && $c->money > $c->build_cost)) { //otherwise... build one CS if we can afford it and are below our target BPT (80)
        return build_cs(); //build 1 CS
    } elseif ($c->protection == 0 && $c->foodnet > 0 && $c->foodnet > 3 * $c->foodcon && $c->food > 30 * $c->foodnet && $c->food > 7000 || $c->turns == 1 && $c->food > 7000) { //Don't sell less than 30 turns of food
        return sellextrafood_farmer($c);
    } elseif ($c->turns_played > 150 && $c->b_indy < $c->bpt && $c->empty > $c->bpt && $c->money > $c->bpt * $c->build_cost) {  //build a full BPT of indies if we have less than that, and we're out of protection
        return build_indy($c);
    } elseif ($c->empty > $c->bpt && $c->money > $c->bpt * $c->build_cost) {  //build a full BPT if we can afford it
        return build_farmer($c);
    } elseif ($c->turns >= 4 && $c->empty >= 4 && $c->bpt < $target_bpt && $c->money > 4 * $c->build_cost && ($c->foodnet > 0 || $c->food > $c->foodnet * -5)) { //otherwise... build 4CS if we can afford it and are below our target BPT (80)
        return build_cs(4); //build 4 CS
    } elseif ($c->built() > 50) {  //otherwise... explore if we can
        return explore($c, min(max(1, $c->turns - 1), max(1, turns_of_money($c) - 3)));
    } elseif ($c->empty && $c->bpt < $target_bpt && $c->money > $c->build_cost) { //otherwise... build one CS if we can afford it and are below our target BPT (80)
        return build_cs(); //build 1 CS
    } else { //otherwise...  cash
        return cash($c);
    }
}//end play_farmer_turn()


function sellextrafood_farmer(&$c)
{
    //out("Lots of food, let's sell some!");
    //$pm_info = get_pm_info();
    //$market_info = get_market_info(); //get the Public Market info
    global $market,$pm_info;

    $c = get_advisor();     //UPDATE EVERYTHING

    $quantity = array('m_bu' => $c->food); //sell it all! :)

    $rmax    = 1.10; //percent
    $rmin    = 0.95; //percent
    $rstep   = 0.01;
    $rstddev = 0.10;
    $max     = $c->goodsStuck('m_bu') ? 0.99 : $rmax;
    $price   = round(max($pm_info->sell_price->m_bu + 1, $market->price('m_bu') * purebell($rmin, $max, $rstddev, $rstep)));
    $price   = array('m_bu' => $price);

    if ($price <= max(29, $pm_info->sell_price->m_bu / $c->tax())) {
        return sell_on_pm($c, array('m_bu' => $quantity)); ///      sell_on_pm($c,array('m_bu' => $c->food));   //Sell 'em
    }
    return sell_public($c, $quantity, $price);    //Sell food!
}//end sellextrafood_farmer()


function build_farmer(&$c)
{
    //build farms
    return build(array('farm' => $c->bpt));
}//end build_farmer()



function buy_farmer_goals(&$c, $spend = null)
{
    $goals = farmerGoals($c);

    $c->countryGoals($goals, $spend);
}//end buy_farmer_goals()


function farmerGoals(&$c)
{
    return [
        //what, goal, priority
        ['t_agri',215,8],
        ['t_bus',178,4],
        ['t_res',178,4],
        ['t_mil',94,1],
        ['nlg',$c->nlgTarget(),2],
        ['dpa',$c->defPerAcreTarget(),2],
    ];
}//end farmerGoals()
