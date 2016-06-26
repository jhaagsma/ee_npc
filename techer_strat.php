<?php namespace EENPC;

$techlist = array('t_mil','t_med','t_bus','t_res','t_agri','t_war','t_ms','t_weap','t_indy','t_spy','t_sdi');

function play_techer_strat($server)
{
    global $cnum;
    out("Playing ".TECHER." Turns for #$cnum");
    //$main = get_main();     //get the basic stats
    //out_data($main);			//output the main data
    $c = get_advisor();     //c as in country! (get the advisor)
    $c->setIndy('pro_spy');

    out($c->turns.' turns left');

    if ($c->govt == 'M') {
        $rand = rand(0, 100);
        switch ($rand) {
            case $rand < 40:
                change_govt($c, 'H');
                break;
            case $rand < 80:
                change_govt($c, 'D');
                break;
            default:
                change_govt($c, 'T');
                break;
        }
    }

    //out_data($c);				//ouput the advisor data
    $pm_info = get_pm_info();   //get the PM info
    //out_data($pm_info);		//output the PM info
    //$market_info = get_market_info();   //get the Public Market info
    //out_data($market_info);		//output the PM info

    $owned_on_market_info = get_owned_on_market_info();     //find out what we have on the market
    //out_data($owned_on_market_info); //output the owned on market info


    while ($c->turns > 0) {
        //$result = buy_public($c,array('m_bu'=>100),array('m_bu'=>400));
        $result = play_techer_turn($c);
        if ($result === false) {  //UNEXPECTED RETURN VALUE
            $c = get_advisor();     //UPDATE EVERYTHING
            continue;
        }
        update_c($c, $result);
        if (!$c->turns%5) {                   //Grab new copy every 5 turns
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

        if (turns_of_food($c) > 50 && turns_of_money($c) > 50 && $c->money > 3500*500 &&  ($c->built() > 80 || $c->money > $c->fullBuildCost() - $c->runCash()) && $c->tpt > 200) { // 40 turns of food
            buy_techer_goals($c, $c->money - $c->fullBuildCost() - $c->runCash()); //keep enough money to build out everything
        }
    }
    out("Done Playing ".TECHER." Turns for #$cnum!");   //Text for screen
}

function play_techer_turn(&$c)
{
 //c as in country!
    $target_bpt = 65;
    global $turnsleep, $mktinfo;
    $mktinfo = null;
    usleep($turnsleep);
    //out($main->turns . ' turns left');
    if ($c->protection == 0 && total_cansell_tech($c) > 20*$c->tpt && selltechtime($c) || $c->turns == 1 && total_cansell_tech($c) > 20) { //never sell less than 20 turns worth of tech
        return sell_max_tech($c);
    } elseif ($c->empty > $c->bpt && $c->money > $c->bpt*$c->build_cost) {  //build a full BPT if we can afford it
        return build_techer($c);
    } elseif ($c->turns >= 4 && $c->empty >= 4 && $c->bpt < $target_bpt && $c->money > 4*$c->build_cost && ($c->foodnet > 0 || $c->food > $c->foodnet*-5)) { //otherwise... build 4CS if we can afford it and are below our target BPT (80)
        return build_cs(4); //build 4 CS
    } elseif ($c->tpt > $c->land*0.17*1.3 && rand(0, 10) > 4 && $c->tpt > 100) { //tech per turn is greater than land*0.17 -- just kindof a rough "don't tech below this" rule...
        return tech_techer($c);
    } elseif ($c->built() > 50 && ($c->land < 5000 || rand(0, 10) > 7)) {   //otherwise... explore if we can, for the early bits of the set
        return explore($c, min(max(1, $c->turns - 1), max(1, min(turns_of_money($c), turns_of_food($c))-3)));
    } elseif ($c->empty && $c->bpt < $target_bpt && $c->money > $c->build_cost) { //otherwise... build one CS if we can afford it and are below our target BPT (80)
        return build_cs(); //build 1 CS
    } else { //otherwise, tech, obviously
        return tech_techer($c);
    }
}

function build_techer(&$c)
{
    return build(array('lab' => $c->bpt));
}

function selltechtime($c)
{
    global $techlist;
    $sum = $om = 0;
    foreach ($techlist as $tech) {
        $sum += $c->$tech;
        $om += onmarket($tech);
    }
    if ($om < $sum/6) {
        return true;
    }

    return false;
}

function sell_max_tech($c)
{
    $c = get_advisor();     //UPDATE EVERYTHING
    //$market_info = get_market_info();   //get the Public Market info
    global $market;

    $quantity = array(
        'mil'=>can_sell_tech($c, 't_mil'),
        'med'=>can_sell_tech($c, 't_med'),
        'bus'=>can_sell_tech($c, 't_bus'),
        'res'=>can_sell_tech($c, 't_res'),
        'agri'=>can_sell_tech($c, 't_agri'),
        'war'=>can_sell_tech($c, 't_war'),
        'ms'=>can_sell_tech($c, 't_ms'),
        'weap'=>can_sell_tech($c, 't_weap'),
        'indy'=>can_sell_tech($c, 't_indy'),
        'spy'=>can_sell_tech($c, 't_spy'),
        'sdi'=>can_sell_tech($c, 't_sdi')
    );


    $nogoods_high = 9000;
    $nogoods_low = 2000;
    $nogoods_stddev = 1500;
    $nogoods_step = 1;
    $rmax = 1.30; //percent
    $rmin = 0.80; //percent
    $rstep = 0.01;
    $rstddev = 0.10;
    $price = array();
    foreach ($quantity as $key => $q) {
        if ($q == 0) {
            $price[$key] = 0;
        } elseif ($market->price($key) != null) {
            $price[$key] = floor($market->price($key) * purebell($rmin, $rmax, $rstddev, $rstep));
        } else {
            $price[$key] = floor(purebell($nogoods_low, $nogoods_high, $nogoods_stddev, $nogoods_step));
        }
    }

    $result = sell_public($c, $quantity, $price);
    if ($result == 'QUANTITY_MORE_THAN_CAN_SELL') {
        out("TRIED TO SELL MORE THAN WE CAN!?!");
        $c = get_advisor();     //UPDATE EVERYTHING
    }
    global $mktinfo;
    $mktinfo = null;
    return $result;
}

function tech_techer(&$c)
{
    //lets do random weighting... to some degree
    //$market_info = get_market_info();   //get the Public Market info
    global $market;

    $mil    = max((int)$market->price('mil') - 2000, rand(0, 300));
    $med    = max((int)$market->price('med') - 2000, rand(0, 5));
    $bus    = max((int)$market->price('bus') - 2000, rand(10, 400));
    $res    = max((int)$market->price('res') - 2000, rand(10, 400));
    $agri   = max((int)$market->price('agri') - 2000, rand(10, 300));
    $war    = max((int)$market->price('war') - 2000, rand(0, 10));
    $ms     = max((int)$market->price('ms') - 2000, rand(0, 20));
    $weap   = max((int)$market->price('weap') - 2000, rand(0, 20));
    $indy   = max((int)$market->price('indy') - 2000, rand(5, 300));
    $spy    = max((int)$market->price('spy') - 2000, rand(0, 10));
    $sdi    = max((int)$market->price('sdi') - 2000, rand(2, 150));
    $tot    = $mil + $med + $bus + $res + $agri + $war + $ms + $weap + $indy + $spy + $sdi;

    $left = $c->tpt;
    $left -= $mil = min($left, floor($c->tpt*($mil/$tot)));
    $left -= $med = min($left, floor($c->tpt*($med/$tot)));
    $left -= $bus = min($left, floor($c->tpt*($bus/$tot)));
    $left -= $res = min($left, floor($c->tpt*($res/$tot)));
    $left -= $agri = min($left, floor($c->tpt*($agri/$tot)));
    $left -= $war = min($left, floor($c->tpt*($war/$tot)));
    $left -= $ms = min($left, floor($c->tpt*($ms/$tot)));
    $left -= $weap = min($left, floor($c->tpt*($weap/$tot)));
    $left -= $indy = min($left, floor($c->tpt*($indy/$tot)));
    $left -= $spy = min($left, floor($c->tpt*($spy/$tot)));
    $left -= $sdi = max($left, min($left, floor($c->tpt*($spy/$tot))));
    if ($left != 0) {
        die("What the hell?");
    }

    return tech(array('mil'=>$mil,'med'=>$med,'bus'=>$bus,'res'=>$res,'agri'=>$agri,'war'=>$war,'ms'=>$ms,'weap'=>$weap,'indy'=>$indy,'spy'=>$spy,'sdi'=>$sdi));
}


function buy_techer_goals(&$c, $spend = null)
{
    $goals = [
        //what, goal, priority
        ['nlg',$c->nlgTarget(),2],
    ];

    $c->countryGoals($goals, $spend);
}
