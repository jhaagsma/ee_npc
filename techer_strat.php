<?php namespace EENPC;

$techlist = array('t_mil','t_med','t_bus','t_res','t_agri','t_war','t_ms','t_weap','t_indy','t_spy','t_sdi');

function play_techer_strat($server)
{
    global $cnum;
    out("Playing " . TECHER . " Turns for #$cnum");
    $main = get_main();     //get the basic stats
    //out_data($main);			//output the main data
    $c = get_advisor();     //c as in country! (get the advisor)
    out($c->turns . ' turns left');

    if ($c->govt == 'M') {
        $rand = rand(0, 100);
        switch ($rand) {
            case $rand < 20:
                change_govt($c, 'H');
                break;
            case $rand < 40:
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
    $market_info = get_market_info();   //get the Public Market info
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
            $main = get_main();         //Grab a fresh copy of the main stats //we probably don't need to do this *EVERY* turn
            $c->money = $main->money;       //might as well use the newest numbers?
            $c->food = $main->food;             //might as well use the newest numbers?
            $c->networth = $main->networth; //might as well use the newest numbers?
            $c->oil = $main->oil;           //might as well use the newest numbers?
            $c->pop = $main->pop;           //might as well use the newest numbers?
            $c->turns = $main->turns;       //This is the only one we really *HAVE* to check for
        }



        $hold = money_management($c);
        if ($hold) {
            break; //HOLD TURNS HAS BEEN DECLARED; HOLD!!
        }

        $hold = food_management($c);
        if ($hold) {
            break; //HOLD TURNS HAS BEEN DECLARED; HOLD!!
        }

        if (turns_of_food($c) > 40 && $c->money > $c->networth *2) { // 40 turns of food, and more than 2x nw in cash on hand
            defend_self($c, floor($c->money * 0.25)); //second param is *RESERVE* cash
        }        //$main->turns = 0;				//use this to do one turn at a time
    }
    out("Done Playing " . TECHER . " Turns for #$cnum!");   //Text for screen
}

function play_techer_turn(&$c)
{
 //c as in country!
    $target_bpt = 65;
    global $turnsleep, $mktinfo;
    $mktinfo = null;
    usleep($turnsleep);
    //out($main->turns . ' turns left');
    if ($c->protection == 0 && total_cansell_tech($c) > 20*$c->tpt && selltechtime($c)) { //never sell less than 20 turns worth of tech
        return sell_max_tech($c);
    } elseif ($c->empty > $c->bpt && $c->money > $c->bpt*$c->build_cost) {  //build a full BPT if we can afford it
        return build_techer($c);
    } elseif ($c->turns >= 4 && $c->empty >= 4 && $c->bpt < $target_bpt && $c->money > 4*$c->build_cost && ($c->foodnet > 0 || $c->food > $c->foodnet*-5)) { //otherwise... build 4CS if we can afford it and are below our target BPT (80)
        return build_cs(4); //build 4 CS
    } elseif ($c->tpt > $c->land*0.17*1.3 && rand(0, 10) > 6) { //tech per turn is greater than land*0.17 -- just kindof a rough "don't tech below this" rule...
        return tech_techer($c);
    } elseif ($c->empty < $c->land/2 && ($c->land < 5000 || rand(0, 10) > 8)) {   //otherwise... explore if we can, for the early bits of the set
        return explore($c);
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
    $market_info = get_market_info();   //get the Public Market info

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
        } elseif ($market_info->buy_price->$key != null) {
            $price[$key] = floor($market_info->buy_price->$key * purebell($rmin, $rmax, $rstddev, $rstep));
        } else {
            $price[$key] = floor(purebell($nogoods_low, $nogoods_high, $nogoods_stddev, $nogoods_step));
        }
    }

    /*$price = array(
		'mil'=>	$quantity['mil'] == 0 ? 0 : floor(($market_info->buy_price->mil != null ? $market_info->buy_price->mil : rand($nogoods_low,$nogoods_high))*(rand($randomdown,$randomup)/100)),
		'med'=>	$quantity['med'] == 0 ? 0 : floor(($market_info->buy_price->med != null ? $market_info->buy_price->med : rand($nogoods_low,$nogoods_high))*(rand($randomdown,$randomup)/100)),
		'bus'=>	$quantity['bus'] == 0 ? 0 : floor(($market_info->buy_price->bus != null ? $market_info->buy_price->bus : rand($nogoods_low,$nogoods_high))*(rand($randomdown,$randomup)/100)),
		'res'=>	$quantity['res'] == 0 ? 0 : floor(($market_info->buy_price->res != null ? $market_info->buy_price->res : rand($nogoods_low,$nogoods_high))*(rand($randomdown,$randomup)/100)),
		'agri'=>$quantity['agri'] == 0 ? 0 : floor(($market_info->buy_price->agri != null ? $market_info->buy_price->agri : rand($nogoods_low,$nogoods_high))*(rand($randomdown,$randomup)/100)),
		'war'=>	$quantity['war'] == 0 ? 0 : floor(($market_info->buy_price->war != null ? $market_info->buy_price->war : rand($nogoods_low,$nogoods_high))*(rand($randomdown,$randomup)/100)),
		'ms'=>	$quantity['ms'] == 0 ? 0 : floor(($market_info->buy_price->ms != null ? $market_info->buy_price->ms : rand($nogoods_low,$nogoods_high))*(rand($randomdown,$randomup)/100)),
		'weap'=>$quantity['weap'] == 0 ? 0 : floor(($market_info->buy_price->weap != null ? $market_info->buy_price->weap : rand($nogoods_low,$nogoods_high))*(rand($randomdown,$randomup)/100)),
		'indy'=>$quantity['indy'] == 0 ? 0 : floor(($market_info->buy_price->indy != null ? $market_info->buy_price->indy : rand($nogoods_low,$nogoods_high))*(rand($randomdown,$randomup)/100)),
		'spy'=>	$quantity['spy'] == 0 ? 0 : floor(($market_info->buy_price->spy != null ? $market_info->buy_price->spy : rand($nogoods_low,$nogoods_high))*(rand($randomdown,$randomup)/100)),
		'sdi'=>	$quantity['sdi'] == 0 ? 0 : floor(($market_info->buy_price->sdi != null ? $market_info->buy_price->sdi : rand($nogoods_low,$nogoods_high))*(rand($randomdown,$randomup)/100))
	);*/

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
    $mil    = rand(0, 300);
    $med    = rand(0, 5);
    $bus    = rand(10, 400);
    $res    = rand(10, 400);
    $agri   = rand(10, 300);
    $war    = rand(0, 10);
    $ms         = rand(0, 20);
    $weap   = rand(0, 20);
    $indy   = rand(5, 300);
    $spy    = rand(0, 10);
    $sdi    = rand(2, 150);
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
