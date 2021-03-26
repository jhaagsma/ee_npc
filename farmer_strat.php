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
function play_farmer_strat($server)
{
    global $cnum;
    out("Playing ".FARMER." turns for #$cnum ".siteURL($cnum));
    //$main = get_main();     //get the basic stats
    //out_data($main);          //output the main data
    $c = get_advisor();     //c as in country! (get the advisor)
    $c->setIndy('pro_spy');
    //$c = get_advisor();     //c as in country! (get the advisor)


    if ($c->m_spy > 10000) {
        Allies::fill('spy');
    }

    out("Agri: {$c->pt_agri}%; Bus: {$c->pt_bus}%; Res: {$c->pt_res}%");
    //out_data($c) && exit;             //ouput the advisor data
    if ($c->govt == 'M') {
        $rand = rand(0, 100);
        switch ($rand) {
            case $rand < 12:
                Government::change($c, 'D');
                break;
            case $rand < 20:
                Government::change($c, 'I');
                break;
            case $rand < 50:
                Government::change($c, 'R');
                break;
            default:
                Government::change($c, 'F');
                break;
        }
    }


    out($c->turns.' turns left');
    out('Explore Rate: '.$c->explore_rate.'; Min Rate: '.$c->explore_min);
    //$pm_info = get_pm_info();   //get the PM info
    //out_data($pm_info);       //output the PM info
    //$market_info = get_market_info();   //get the Public Market info
    //out_data($market_info);       //output the PM info

    $owned_on_market_info = get_owned_on_market_info();     //find out what we have on the market
    //out_data($owned_on_market_info);  //output the Owned on Public Market info

    while ($c->turns > 0) {
        //$result = PublicMarket::buy($c,array('m_bu'=>100),array('m_bu'=>400));
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

            //sell 1/4 of our military
            $pm_info = PrivateMarket::getRecent();
            PrivateMarket::sell($c, ['m_bu' => min($c->food, floor(-10 * $c->income / $pm_info->sell_price->m_bu))]);
        }

        // 40 turns of food
        if (turns_of_food($c) > 50
            && turns_of_money($c) > 50
            && $c->money > 3500 * 500
            && ($c->built() > 80 || $c->money > $c->fullBuildCost())
        ) {
            $spend = $c->money - $c->fullBuildCost(); //keep enough money to build out everything

            if ($spend > abs($c->income) * 5) {
                //try to batch a little bit...
                buy_farmer_goals($c, $spend);
            }
        }
    }

    $c->countryStats(FARMER, farmerGoals($c));
    return $c;
}//end play_farmer_strat()

function play_farmer_turn(&$c)
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
    } elseif ($c->protection == 0 && $c->food > 7000
        && (
            $c->foodnet > 0 && $c->foodnet > 3 * $c->foodcon && $c->food > 30 * $c->foodnet
            || $c->turns == 1
        )
    ) { //Don't sell less than 30 turns of food unless you're on your last turn (and desperate?)
        return sellextrafood_farmer($c);
    } elseif ($c->shouldBuildSpyIndies()) {
        //build a full BPT of indies if we have less than that, and we're out of protection
        return Build::indy($c);
    } elseif ($c->shouldBuildFullBPT($target_bpt)) {
        //build a full BPT if we can afford it
        return Build::farmer($c);
    } elseif ($c->shouldBuildFourCS($target_bpt)) {
        //build 4CS if we can afford it and are below our target BPT (80)
        return Build::cs(4); //build 4 CS
    } elseif ($c->built() > 50) {  //otherwise... explore if we can
        if ($c->explore_rate == $c->explore_min) {
            return explore($c, min(5, max(1, $c->turns - 1), max(1, turns_of_money($c) - 3)));
        } else {
            return explore($c, min(max(1, $c->turns - 1), max(1, turns_of_money($c) - 3)));
        }
    } else { //otherwise...  cash
        return cash($c);
    }
}//end play_farmer_turn()


function sellextrafood_farmer(&$c)
{
    //out("Lots of food, let's sell some!");
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
    $price   = round(
        max(
            $pm_info->sell_price->m_bu + 1,
            PublicMarket::price('m_bu') * Math::purebell($rmin, $max, $rstddev, $rstep)
        )
    );
    $price   = ['m_bu' => $price];

    if ($price <= max(29, $pm_info->sell_price->m_bu / $c->tax())) {
        return PrivateMarket::sell($c, ['m_bu' => $quantity]);
        ///      PrivateMarket::sell($c,array('m_bu' => $c->food));   //Sell 'em
    }
    return PublicMarket::sell($c, $quantity, $price);    //Sell food!
}//end sellextrafood_farmer()


function buy_farmer_goals(&$c, $spend = null)
{
    $goals = farmerGoals($c);

    Country::countryGoals($c, $goals, $spend);
}//end buy_farmer_goals()


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
