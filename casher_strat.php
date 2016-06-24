<?php namespace EENPC;

function play_casher_strat($server)
{
    global $cnum;
    out("Playing ".CASHER." Turns for #$cnum");
    //$main = get_main();     //get the basic stats
    //out_data($main);			//output the main data
    $c = get_advisor();     //c as in country! (get the advisor)
    //out_data($c) && exit;				//ouput the advisor data

    out("Bus: {$c->pt_bus}%; Res: {$c->pt_res}%");
    if ($c->govt == 'M') {
        $rand = rand(0, 100);
        switch ($rand) {
            case $rand < 12:
                change_govt($c, 'I');
                break;
            case $rand < 12:
                change_govt($c, 'D');
                break;
            default:
                change_govt($c, 'R');
                break;
        }
    }

    out($c->turns.' turns left');
    //$pm_info = get_pm_info();	//get the PM info
    //out_data($pm_info);		//output the PM info
    //$market_info = get_market_info();	//get the Public Market info
    //out_data($market_info);		//output the PM info

    $owned_on_market_info = get_owned_on_market_info();     //find out what we have on the market
    //out_data($owned_on_market_info);	//output the Owned on Public Market info

    while ($c->turns > 0) {
        //$result = buy_public($c,array('m_bu'=>100),array('m_bu'=>400));
        $result = play_casher_turn($c);
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

        if (turns_of_food($c) > 40 && $c->money > 3500*500 && $c->built() > 80) { // 40 turns of food
            buy_casher_goals($c);
        }
    }

    out("Bus: {$c->pt_bus}%; Res: {$c->pt_res}%;  Mil: {$c->pt_mil}%; NLG: ".$c->nlg());
    out("Done Playing ".CASHER." Turns for #$cnum!");   //Text for screen
}

function play_casher_turn(&$c)
{
 //c as in country!
    $target_bpt = 65;
    global $turnsleep;
    usleep($turnsleep);
    //out($main->turns . ' turns left');
    if ($c->empty > $c->bpt && $c->money > $c->bpt*$c->build_cost && ($c->bpt/$target_bpt > 0.8 || rand(0, 1))) {  //build a full BPT if we can afford it
        return build_casher($c);
    } elseif ($c->turns >= 4 && $c->empty >= 4 && $c->bpt < $target_bpt && $c->money > 4*$c->build_cost && ($c->foodnet > 0 || $c->food > $c->foodnet*-5)) { //otherwise... build 4CS if we can afford it and are below our target BPT (80)
        return build_cs(4); //build 4 CS
    } elseif ($c->built() > 50) {  //otherwise... explore if we can
        return explore($c);
    } elseif ($c->empty && $c->bpt < $target_bpt && $c->money > $c->build_cost) { //otherwise... build one CS if we can afford it and are below our target BPT (80)
        return build_cs(); //build 1 CS
    } else { //otherwise...  cash
        return cash($c);
    }
}

function build_casher(&$c)
{
    //build ent/res
    $ent = ceil($c->bpt*1.05/2);
    return build(array('ent' => $ent, 'res' => $c->bpt - $ent));
}

function buy_casher_goals(&$c, $spend = null)
{
    if ($spend == null) {
        $spend = $c->money;
    }

    global $cpref;
    $tol = $cpref->price_tolerance; //should be between 0.5 and 1.5

    $goals = [
        //what, goal, priority
        ['t_bus',178,2],
        ['t_res',178,2],
        ['t_mil',90,1],
        ['nlg',$c->nlgTarget(),1],
    ];
    out_data($goals);

    $psum = 0;
    $score = [];
    foreach ($goals as $goal) {
        if ($goal[0] == 't_bus') {
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
    out_data($score);

    arsort($score);

    out_data($score);

    $what = key($score);
    out("Highest Goal: ".$what);
    if ($key = 't_bus') {
        buy_tech($c, 't_bus', $spend/$psum, 3500*$tol);
    } elseif ($key = 't_res') {
        buy_tech($c, 't_res', $spend/$psum, 3500*$tol);
    } elseif ($key = 't_res') {
        buy_tech($c, 't_mil', $spend/$psum, 3500*$tol);
    } elseif ($key = 'nlg') {
        defend_self($c, floor($c->money - $spend/$psum)); //second param is *RESERVE* cash
    }

    $spend -= $spend/$psum;
    if ($spend > 10000) {
        buy_casher_goals($c, $spend);
    }
}
