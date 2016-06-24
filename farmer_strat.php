<?php namespace EENPC;

function play_farmer_strat($server)
{
    global $cnum,$pm_info;
    out("Playing ".FARMER." turns for #$cnum");
    //$main = get_main();     //get the basic stats
    //out_data($main);			//output the main data
    $c = get_advisor();     //c as in country! (get the advisor)
    out("Agri: {$c->pt_agri}%; Bus: {$c->pt_bus}%; Res: {$c->pt_res}%");
    //out_data($c) && exit;				//ouput the advisor data
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
    //out_data($pm_info);		//output the PM info
    //$market_info = get_market_info();   //get the Public Market info
    //out_data($market_info);		//output the PM info

    $owned_on_market_info = get_owned_on_market_info();     //find out what we have on the market
    //out_data($owned_on_market_info);	//output the Owned on Public Market info

    while ($c->turns > 0) {
        //$result = buy_public($c,array('m_bu'=>100),array('m_bu'=>400));
        $result = play_farmer_turn($c);
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


        if ($c->income < 0 && $c->money < -5*$c->income) { //sell 1/4 of all military on PM
            out("Almost out of money! Sell 10 turns of income in food!");   //Text for screen
            sell_on_pm($c, array('m_bu' => min($c->food, floor(-10*$c->income/$pm_info->sell_price->m_bu))));     //sell 1/4 of our military
        }

        if (turns_of_food($c) > 50 && turns_of_money($c) > 50 && $c->money > 3500*500 && $c->built() > 80) { // 40 turns of food
            buy_farmer_goals($c);
        }
    }

    out("Agri: {$c->pt_agri}%; Bus: {$c->pt_bus}%; Res: {$c->pt_res}%; Mil: {$c->pt_mil}%; NLG: ".$c->nlg());
    out("Done Playing ".FARMER." Turns for #$cnum!");   //Text for screen
}

function play_farmer_turn(&$c)
{
 //c as in country!
    $target_bpt = 65;
    global $turnsleep;
    usleep($turnsleep);
    //out($main->turns . ' turns left');
    if ($c->protection == 0 && $c->foodnet > 0 && $c->foodnet > 3*$c->foodcon && $c->food > 30*$c->foodnet && $c->food > 7000 || $c->turns == 1 && $c->food > 7000) { //Don't sell less than 30 turns of food
        return sellextrafood_farmer($c);
    } elseif ($c->empty > $c->bpt && $c->money > $c->bpt*$c->build_cost) {  //build a full BPT if we can afford it
        return build_farmer($c);
    } elseif ($c->turns >= 4 && $c->empty >= 4 && $c->bpt < $target_bpt && $c->money > 4*$c->build_cost && ($c->foodnet > 0 || $c->food > $c->foodnet*-5)) { //otherwise... build 4CS if we can afford it and are below our target BPT (80)
        return build_cs(4); //build 4 CS
    } elseif ($c->built() > 50) {  //otherwise... explore if we can
        return explore($c, min($c->turns, max(1, turns_of_money($c)-3)));
    } elseif ($c->empty && $c->bpt < $target_bpt && $c->money > $c->build_cost) { //otherwise... build one CS if we can afford it and are below our target BPT (80)
        return build_cs(); //build 1 CS
    } else { //otherwise...  cash
        return cash($c);
    }
}

function sellextrafood_farmer(&$c)
{
    //out("Lots of food, let's sell some!");
    //$pm_info = get_pm_info();
    //$market_info = get_market_info();	//get the Public Market info
    global $market,$pm_info;

    $c = get_advisor();     //UPDATE EVERYTHING

    $quantity = array('m_bu' => $c->food); //sell it all! :)

    $rmax = 1.10; //percent
    $rmin = 0.95; //percent
    $rstep = 0.01;
    $rstddev = 0.10;
    $price = round(max($pm_info->sell_price->m_bu+1, $market->price('m_bu')*purebell($rmin, $rmax, $rstddev, $rstep)));
    $price = array('m_bu' => $price);

    if ($price <= max(29, $pm_info->sell_price->m_bu/$c->tax())) {
        return sell_on_pm($c, array('m_bu' => $quantity)); ///		sell_on_pm($c,array('m_bu' => $c->food));	//Sell 'em
    }
    return sell_public($c, $quantity, $price);    //Sell food!
}

function build_farmer(&$c)
{
    //build farms
    return build(array('farm' => $c->bpt));
}



function buy_farmer_goals(&$c, $spend = null, $spend_partial = null)
{
    if ($spend == null) {
        $c = get_advisor();
        $spend = $c->money;
    }

    if ($spend_partial == null) {
        $spend_partial = $spend / 3;
    }

    global $cpref;
    $tol = $cpref->price_tolerance; //should be between 0.5 and 1.5

    $goals = [
        //what, goal, priority
        ['t_agri',215,8],
        ['t_bus',178,4],
        ['t_res',178,4],
        ['t_mil',90,1],
        ['nlg',$c->nlgTarget(),2],
    ];
    //out_data($goals);

    $psum = 0;
    $score = [];
    foreach ($goals as $goal) {
        if ($goal[0] == 't_agri') {
            $score['t_agri'] = ($goal[1]-$c->pt_agri)/($goal[1]-100)*$goal[2];
        } elseif ($goal[0] == 't_bus') {
            $score['t_bus'] = ($goal[1]-$c->pt_bus)/($goal[1]-100)*$goal[2];
        } elseif ($goal[0] == 't_res') {
            $score['t_res'] = ($goal[1]-$c->pt_res)/($goal[1]-100)*$goal[2];
        } elseif ($goal[0] == 't_mil') {
            $score['t_mil'] = ($c->pt_bus-$goal[1])/(100-$goal[1])*$goal[2];
        } elseif ($goal[0] == 'nlg') {
            $score['nlg'] = $c->nlg()/$c->nlgTarget()*$goal[2];
        }
        $psum += $goal[2];
    }
    //out_data($score);

    arsort($score);
   // out_data($score);

    $what = key($score);
    //out("Highest Score:".$what);



    if ($what == 't_agri') {
        buy_tech($c, 't_agri', $spend_partial, 3500*$tol);
    } elseif ($what == 't_bus') {
        buy_tech($c, 't_bus', $spend_partial, 3500*$tol);
    } elseif ($what == 't_res') {
        buy_tech($c, 't_res', $spend_partial, 3500*$tol);
    } elseif ($what == 't_mil') {
        buy_tech($c, 't_mil', $spend_partial, 3500*$tol);
    } elseif ($what == 'nlg') {
        defend_self($c, floor($c->money - $spend/$psum)); //second param is *RESERVE* cash
    }

    $spend -= $spend_partial;
    if ($spend > 10000) {
        buy_farmer_goals($c, $spend, $spend_partial);
    }
}
