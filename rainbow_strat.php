<?php namespace EENPC;

function play_rainbow_strat($server)
{
    global $cnum,$market_info;
    out("Playing " . RAINBOW . " turns for #$cnum");
    $main = get_main();     //get the basic stats
    //out_data($main);			//output the main data
    $c = get_advisor();     //c as in country! (get the advisor)
    out($c->turns . ' turns left');
    //out_data($c) && exit;				//ouput the advisor data
    if ($c->govt == 'M' && $c->turns_played < 100) {
        $rand = rand(0, 100);
        switch ($rand) {
            case $rand < 4:
                change_govt($c, 'F');
                break;
            case $rand < 8:
                change_govt($c, 'T');
                break;
            case $rand < 12:
                change_govt($c, 'I');
                break;
            case $rand < 16:
                change_govt($c, 'C');
                break;
            case $rand < 20:
                change_govt($c, 'H');
                break;
            case $rand < 24:
                change_govt($c, 'R');
                break;
            case $rand < 28:
                change_govt($c, 'D');
                break;
            default:
                break;
        }
    }
    $pm_info = get_pm_info();   //get the PM info
    //out_data($pm_info);		//output the PM info
    $market_info = get_market_info();   //get the Public Market info
    //out_data($market_info);		//output the PM info
    
    $owned_on_market_info = get_owned_on_market_info();     //find out what we have on the market
    //out_data($market_info);	//output the Public Market info
    //var_export($owned_on_market_info);
    
    while ($c->turns > 0) {
        //$result = buy_public($c,array('m_bu'=>100),array('m_bu'=>400));
        $result = play_rainbow_turn($c);
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
            defend_self($c, floor($c->money * 0.35)); //money is *RESERVE* cash
        }
        if ($c->income < 0 && total_military($c) > 30) { //sell 1/4 of all military on PM
            out("Losing money! Sell 1/4 of our military!");     //Text for screen
            sell_all_military($c, 1/4);  //sell 1/4 of our military
        }

        global $cpref;
        $tol = $cpref->price_tolerance; //should be between 0.5 and 1.5
        if ($c->money > max($c->bpt, 30)*$c->build_cost*10) { //buy_tech
            //out("Try to buy tech?");
            $spend = $c->money - max($c->bpt, 30)*$c->build_cost*10;
            if ($c->pt_agri < 160) {
                buy_tech($c, 't_agri', $spend*1/2, 3500*$tol);
            }
            if ($c->pt_bus < 140) {
                buy_tech($c, 't_bus', $spend*1/4, 3500*$tol);
            }
            if ($c->pt_res < 140) {
                buy_tech($c, 't_res', $spend*1/4, 3500*$tol);
            }
            
            $c = get_advisor();     //UPDATE EVERYTHING
            //out("Try Higher Amount!");
            $spend = $c->money - max($c->bpt, 30)*$c->build_cost*10;
            if ($c->pt_agri < 200) {
                buy_tech($c, 't_agri', $spend*1/2, 3500*$tol);
            }
            if ($c->pt_bus < 160) {
                buy_tech($c, 't_bus', $spend*1/4, 3500*$tol);
            }
            if ($c->pt_res < 160) {
                buy_tech($c, 't_res', $spend*1/4, 3500*$tol);
            }
        }
        //$main->turns = 0;				//use this to do one turn at a time
    }
    out("Done Playing " . RAINBOW . " Turns for #$cnum!");  //Text for screen
}

function play_rainbow_turn(&$c)
{
 //c as in country!
    $targetBPT = 65;
    global $turnsleep;
    usleep($turnsleep);
    //out($main->turns . ' turns left');
    if ($c->empty > $c->bpt && $c->money > $c->bpt*$c->build_cost) {  //build a full BPT if we can afford it
        return build_rainbow($c);
    } elseif ($c->turns >= 4 && $c->empty >= 4 && $c->bpt < $targetBPT && $c->money > 4*$c->build_cost && ($c->foodnet > 0 || $c->food > $c->foodnet*-5)) { //otherwise... build 4CS if we can afford it and are below our target BPT (80)
        return build_cs(4); //build 4 CS
    } elseif ($c->tpt > $c->land*0.17 && rand(0, 10) > 5) { //tech per turn is greater than land*0.17 -- just kindof a rough "don't tech below this" rule...
        return tech_rainbow($c);
    } elseif ($c->empty < $c->land/2) {  //otherwise... explore if we can
        return explore($c);
    } elseif ($c->empty && $c->bpt < $targetBPT && $c->money > $c->build_cost) { //otherwise... build one CS if we can afford it and are below our target BPT (80)
        return build_cs(); //build 1 CS
    } elseif ($c->foodnet > 0 && $c->foodnet > 3*$c->foodcon && $c->food > 30*$c->foodnet && $c->food > 7000) {
        return sellextrafood_rainbow($c);
    } else { //otherwise...  cash
        return cash($c);
    }
}

function sellextrafood_rainbow(&$c)
{
    global $market_info, $pm_info;

    //out("Lots of food, let's sell some!");
    $c = get_advisor();     //UPDATE EVERYTHING
    if (!is_object($pm_info) || !is_object($pm_info->sell_price)) {
        out("Update PM")
        $pm_info = get_pm_info();   //get the PM info
    }

    if (!is_object($market_info) || !is_object($market_info->buy_price)) {
        out("Update market")
        $market_info = get_market_info();   //get the Public Market info
    }

    $quantity = round(max($pm_info->sell_price->m_bu, $market_info->buy_price->m_bu)*rand(80, 120)/100);
    if ($quantity > 5000 || !is_object($c)) {
        return sell_public($c, array('m_bu' => $quantity));  //Sell food!
    }
}

function build_rainbow(&$c)
{
    if ($c->foodnet < 0 && -1*$c->food/$c->foodnet < 50) { //build farms if we have less than 50 turns of food on hand
        return build(array('farm' => $c->bpt));
    } elseif ($c->income < max(100000, 2*$c->build_cost*$c->bpt/$c->explore_rate)) { //build ent/res if we're not making more than enough to keep building continually at least $100k
        if (rand(0, 100) > 50 && $c->income > $c->build_cost*$c->bpt/$c->explore_rate) {
            return build(array('lab' => $c->bpt));
        } else {
            $res = round($c->bpt/2.12);
            $ent = $c->bpt - $res;
            return build(array('ent' => $ent,'res' => $res));
        }
    } else { //build indies or labs
        if (($c->tpt < $c->land && rand(0, 100) > 10) || rand(0, 100) > 40) {
            return build(array('lab' => $c->bpt));
        } else {
            return build(array('indy' => $c->bpt));
        }
    }
}

function tech_rainbow(&$c)
{
    //lets do random weighting... to some degree
    $mil    = rand(0, 25);
    $med    = rand(0, 5);
    $bus    = rand(10, 100);
    $res    = rand(10, 100);
    $agri   = rand(10, 100);
    $war    = rand(0, 10);
    $ms         = rand(0, 20);
    $weap   = rand(0, 20);
    $indy   = rand(5, 40);
    $spy    = rand(0, 10);
    $sdi    = rand(2, 15);
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
