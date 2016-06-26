<?php namespace EENPC;

function destock($server, $cnum)
{
    $c = get_advisor();     //c as in country! (get the advisor)
    out("Destocking #$cnum!");  //Text for screen
    if ($c->food > 0) {
        sell_on_pm($c, array('m_bu' => $c->food));   //Sell 'em
    }
    $dpnw = 200;
    while ($c->money > 1000 && $dpnw < 500) {
        out("Try to buy goods at $dpnw dpnw or below!");    //Text for screen
        buy_public_below_dpnw($c, $dpnw);
        buy_private_below_dpnw($c, $dpnw);
        $dpnw += 4;
    }
    if ($c->money <= 1000) {
        out("Done Destocking!");    //Text for screen
    } else {
        out("Ran out of goods?");   //Text for screen
    }
}



function buy_public_below_dpnw(&$c, $dpnw, &$money = null, $shuffle = false)
{
    //out("Stage 1");
    //$market_info = get_market_info();
    global $market;
    //out_data($market_info);
    if (!$money || $money < 0) {
        $money = $c->money;
        $reserve = 0;
    } else {
        $reserve = $c->money - $money;
    }

    $tr_price = round($dpnw*0.5/$c->tax());  //THE PRICE TO BUY THEM AT
    $j_price = $tu_price = round($dpnw*0.6/$c->tax());  //THE PRICE TO BUY THEM AT
    $ta_price = round($dpnw*2/$c->tax());  //THE PRICE TO BUY THEM AT

    $tr_cost =  ceil($tr_price*$c->tax());  //THE COST OF BUYING THEM
    $j_cost = $tu_cost = ceil($tu_price*$c->tax());  //THE COST OF BUYING THEM
    $ta_cost = ceil($ta_price*$c->tax());  //THE COST OF BUYING THEM

    $units = array('tu','tr','ta','j');
    if ($shuffle) {
        shuffle($units);
    }

    static $last = 0;
    foreach ($units as $subunit) {
        $unit = 'm_'.$subunit;
        if ($market->price($unit) != null && $market->available($unit) > 0) {
            $price = $subunit.'_price';
            $cost = $subunit.'_cost';
            //out("Stage 1.4");
            while ($market->price($unit) <= $$price && $money > $$cost && $market->available($unit) > 0) {
                //out("Stage 1.4.x");
                //out("Money: $money");
                //out("$subunit Price: $price");
                //out("Buy Price: {$market_info->buy_price->$unit}");
                $quantity = min(floor($money/ceil($market->price($unit)*$c->tax())), $market->available($unit));
                if ($quantity == $last) {
                    $quantity = max(0, $quantity - 1);
                }
                $last = $quantity;
                //out("Quantity: $quantity");
                //out("Available: {$market_info->available->$unit}");
                $result = buy_public($c, [$unit => $quantity], [$unit => $market->price($unit)]);  //Buy troops!
                $market->update();
                $money = $c->money - $reserve;
                if ($result === false || !isset($result->bought->$unit->quantity) || $result->bought->$unit->quantity == 0) {
                    out("Breaking@$unit");
                    break;
                }
            }
        }
    }

}

function buy_private_below_dpnw(&$c, $dpnw, &$money = null, $shuffle = false)
{
    //out("Stage 2");
    $pm_info = get_pm_info();   //get the PM info

    if (!$money || $money < 0) {
        $money = $c->money;
        $reserve = 0;
    } else {
        $reserve = $c->money - $money;
    }

    $tr_price = round($dpnw*0.5);
    $j_price = $tu_price = round($dpnw*0.6);
    $ta_price = round($dpnw*2);

    $order = array(1,2,3,4);
    if ($shuffle) {
        shuffle($order);
    }


    foreach ($order as $o) {
        if ($o == 1 && $pm_info->buy_price->m_tr <= $tr_price && $pm_info->available->m_tr > 0 && $money > $pm_info->buy_price->m_tr) {
            $result = buy_on_pm($c, array('m_tr' => min(floor($money/$pm_info->buy_price->m_tr), $pm_info->available->m_tr)));
            $money = $c->money - $reserve;
        } elseif ($o == 2 && $pm_info->buy_price->m_ta <= $ta_price && $pm_info->available->m_ta > 0 && $money > $pm_info->buy_price->m_ta) {
            $result = buy_on_pm($c, array('m_ta' => min(floor($money/$pm_info->buy_price->m_ta), $pm_info->available->m_ta)));
            $money = $c->money - $reserve;
        } elseif ($o == 3 && $pm_info->buy_price->m_j <= $j_price && $pm_info->available->m_j > 0 && $money > $pm_info->buy_price->m_j) {
            $result = buy_on_pm($c, array('m_j' => min(floor($money/$pm_info->buy_price->m_j), $pm_info->available->m_j)));
            $money = $c->money - $reserve;
        } elseif ($o == 4 && $pm_info->buy_price->m_tu <= $tu_price && $pm_info->available->m_tu > 0 && $money > $pm_info->buy_price->m_tu) {
            $result = buy_on_pm($c, array('m_tu' => min(floor($money/$pm_info->buy_price->m_tu), $pm_info->available->m_tu)));
            $money = $c->money - $reserve;
        }
    }
}




function sell_all_military(&$c, $fraction = 1)
{
    $fraction = max(0, min(1, $fraction));
    $sell_units = array(
        'm_spy'     =>  floor($c->m_spy*$fraction),         //$fraction of spies
        'm_tr'  =>  floor($c->m_tr*$fraction),      //$fraction of troops
        'm_j'   =>  floor($c->m_j*$fraction),       //$fraction of jets
        'm_tu'  =>  floor($c->m_tu*$fraction),      //$fraction of turrets
        'm_ta'  =>  floor($c->m_ta*$fraction)       //$fraction of tanks
    );
    if (array_sum($sell_units) == 0) {
        out("No Military!");
        return;
    }
    return sell_on_pm($c, $sell_units);  //Sell 'em
}

function turns_of_food(&$c)
{
    if ($c->foodnet >= 0) {
        return 1000; //POSITIVE FOOD, CAN LAST FOREVER BASICALLY
    }
    $foodloss = -1*$c->foodnet;
    return floor($c->food/$foodloss);
}

function turns_of_money(&$c)
{
    if ($c->income > 0) {
        return 1000; //POSITIVE INCOME
    }
    $incomeloss = -1*$c->income;
    return floor($c->money/$incomeloss);
}

function money_management(&$c)
{
    while (turns_of_money($c) < 4) {
        $foodloss = -1*$c->foodnet;

        if ($c->turns_stored <= 30 && total_cansell_military($c) > 7500) {
            out("Selling max military, and holding turns.");
            sell_max_military($c);
            return true;
        } elseif ($c->turns_stored > 30 && total_military($c) > 1000) {
            out("We have stored turns or can't sell on public; sell 1/10 of military.");   //Text for screen
            sell_all_military($c, 1/10);
        } else {
            out("Low stored turns ({$c->turns_stored}); can't sell? (".total_cansell_military($c).')');
            return true;
        }
    }

    return false;
}

function food_management(&$c)
{
 //RETURNS WHETHER TO HOLD TURNS OR NOT
    $reserve = max(130, $c->turns);
    if (turns_of_food($c) >= $reserve) {
        return false;
    }

    //out("food management");
    $foodloss = -1*$c->foodnet;
    $turns_buy = max($reserve-turns_of_food($c), 50);

    //$c = get_advisor();     //UPDATE EVERYTHING
    global $market;
    //$market_info = get_market_info();   //get the Public Market info
    //out_data($market_info);
    $pm_info = get_pm_info();
    while ($turns_buy > 1 && $c->food <= $turns_buy*$foodloss && $market->price('m_bu') != null) {
        $turns_of_food = $foodloss*$turns_buy;
        $market_price = $market->price('m_bu');
        //out("Market Price: " . $market_price);
        if ($c->food < $turns_of_food && $c->money > $turns_of_food*$market_price*$c->tax() && $c->money - $turns_of_food*$market_price*$c->tax() + $c->income*$turns_buy > 0) { //losing food, less than turns_buy turns left, AND have the money to buy it
            $quantity = min($foodloss*$turns_buy, $market->available('m_bu'));
            out("Less than $reserve turns worth of food! (".$c->foodnet."/turn) Buy $turns_buy turns of food ($quantity) off Public @\$$market_price if we can!");     //Text for screen
            $result = buy_public($c, array('m_bu' => $quantity), array('m_bu' => $market_price));     //Buy 3 turns of food off the public at or below the PM price
            if ($result === false) {
                $market->update();
            }
            //$market->relaUpdate('m_bu', $quantity, $result->bought->m_bu->quantity);

            $c = get_advisor();     //UPDATE EVERYTHING
        }
        /*else
			out("$turns_buy: " . $c->food . ' < ' . $turns_of_food . '; $' . $c->money . ' > $' . $turns_of_food*$market_price);*/

        $turns_buy--;
    }
    $turns_buy = min(3, max(1, $turns_buy));
    $turns_of_food = $foodloss*$turns_buy;

    if ($c->food > $turns_of_food) {
        return false;
    }

    //WE HAVE MONEY, WAIT FOR FOOD ON MKT
    if ($c->protection == 0 && $c->turns_stored < 30 && $c->income > $pm_info->buy_price->m_bu*$foodloss) {
        out("We make enough to buy food if we want to; hold turns for now, and wait for food on MKT.");   //Text for screen
        return true;
    }

    //WAIT FOR GOODS/TECH TO SELL
    if ($c->protection == 0 && $c->turns_stored < 30 && onmarket_value() > $pm_info->buy_price->m_bu*$foodloss) {
        out("We have goods on market; hold turns for now.");    //Text for screen
        return true;
    }

    //PUT GOODS/TECH ON MKT AS APPROPRIATE


    if ($c->food < $turns_of_food && $c->money > $turns_buy*$foodloss*$pm_info->buy_price->m_bu) { //losing food, less than turns_buy turns left, AND have the money to buy it
        out("Less than $turns_buy turns worth of food! (".$c->foodnet."/turn) We're rich, so buy food on PM (\${$pm_info->buy_price->m_bu})!~");   //Text for screen
        $result = buy_on_pm($c, array('m_bu' => $turns_buy*$foodloss));  //Buy 3 turns of food!
        return false;
    } elseif ($c->food < $turns_of_food && total_military($c) > 50) {
        out("We're too poor to buy food! Sell 1/10 of our military");   //Text for screen
        sell_all_military($c, 1/10);     //sell 1/4 of our military
        $c = get_advisor();     //UPDATE EVERYTHING
        return food_management($c); //RECURSION!
    }

    out('We have exhausted all food options. Valar Morguhlis.');
    return false;
}

function defend_self(&$c, $reserve_cash)
{
    if ($c->protection) {
        return;
    }
    //BUY MILITARY?
    $spend = $c->money - $reserve_cash;
    $nlg_target = $c->nlgTarget();
    $dpnw = 320;
    $nlg = $c->nlg();
    while ($nlg < $nlg_target && $spend >= 1000 && $dpnw < 380) {
        out("Try to buy goods at $dpnw dpnw or below to reach NLG of $nlg_target from $nlg!");  //Text for screen
        buy_public_below_dpnw($c, $dpnw, $spend, false);
        $spend = $c->money - $reserve_cash;

        buy_private_below_dpnw($c, $dpnw, $spend, true);
        $dpnw += 20;
        $c = get_advisor();     //UPDATE EVERYTHING
        $spend = $c->money - $reserve_cash;
        $nlg = $c->nlg();
    }
}



/*
function food_pm_price($c){
	$pm_info = get_pm_info();
	return $pm_info->sell_price->m_bu;
}*/


function sell_max_military(&$c)
{
    $c = get_advisor();     //UPDATE EVERYTHING
    $market_info = get_market_info();   //get the Public Market info
    $pm_info = get_pm_info();   //get the PM info
    global $military_list;

    $quantity = array();
    foreach ($military_list as $unit) {
        $quantity[$unit] = can_sell_mil($c, $unit);
    }

    $rmax = 1.30; //percent
    $rmin = 0.75; //percent
    $rstep = 0.01;
    $rstddev = 0.10;
    $price = array();
    foreach ($quantity as $key => $q) {
        if ($q == 0) {
            $price[$key] = 0;
        } elseif ($market_info->buy_price->$key == null || $market_info->buy_price->$key == 0) {
            $price[$key] = floor($pm_info->buy_price->$key * purebell(0.5, 1.0, 0.3, 0.01));
        } else {
            $max = $c->goodsStuck($key) ? 0.99 : $rmax; //undercut if we have goods stuck
            $price[$key] = min($pm_info->buy_price->$key, floor($market_info->buy_price->$key * purebell($rmin, $max, $rstddev, $rstep)));
        }
    }
    /*
    $randomup = 120; //percent
    $randomdown = 80; //percent
    $price = array(
        'm_tr'=>    $quantity['m_tr'] == 0 ? 0 : floor(($market_info->buy_price->m_tr != null ? $market_info->buy_price->m_tr : rand(110,144))*(rand($randomdown,$randomup)/100)),
        'm_j' =>    $quantity['m_j']  == 0 ? 0 : floor(($market_info->buy_price->m_j  != null ? $market_info->buy_price->m_j  : rand(110,192))*(rand($randomdown,$randomup)/100)),
        'm_tu'=>    $quantity['m_tu'] == 0 ? 0 : floor(($market_info->buy_price->m_tu != null ? $market_info->buy_price->m_tu : rand(110,200))*(rand($randomdown,$randomup)/100)),
        'm_ta'=>    $quantity['m_ta'] == 0 ? 0 : floor(($market_info->buy_price->m_ta != null ? $market_info->buy_price->m_ta : rand(400,560))*(rand($randomdown,$randomup)/100))
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
