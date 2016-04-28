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
    $market_info = get_market_info();
    //out_data($market_info);
    if (!$money || $money < 0) {
        $money = $c->money;
        $reserve = 0;
    } else {
        $reserve = $c->money - $money;
    }
    
    $tr_price = round($dpnw*0.5/((100+$c->g_tax)/100));  //THE PRICE TO BUY THEM AT
    $j_price = $tu_price = round($dpnw*0.6/((100+$c->g_tax)/100));  //THE PRICE TO BUY THEM AT
    $ta_price = round($dpnw*2/((100+$c->g_tax)/100));  //THE PRICE TO BUY THEM AT

    $tr_cost = round($tr_price*((100+$c->g_tax)/100));  //THE COST OF BUYING THEM
    $j_cost = $tu_cost = round($tu_price*((100+$c->g_tax)/100));  //THE COST OF BUYING THEM
    $ta_cost = round($ta_price*((100+$c->g_tax)/100));  //THE COST OF BUYING THEM

    $units = array('tu','tr','ta','j');
    if ($shuffle) {
        shuffle($untis);
    }

    foreach ($units as $subunit) {
        $unit = 'm_' . $subunit;
        if ($market_info->buy_price->$unit != null && $market_info->available->$unit > 0) {
            $price = $subunit . '_price';
            $cost = $subunit . '_cost';
            //out("Stage 1.4");
            while ($market_info->buy_price->$unit <= $$price && $money > $$cost && $market_info->available->m_ta > 0) {
                //out("Stage 1.4.x");
                //out("Money: $money");
                //out("$subunit Price: $price");
                //out("Buy Price: {$market_info->buy_price->$unit}");
                $quantity = min(floor($money/ceil($market_info->buy_price->$unit*((100+$c->g_tax)/100))), $market_info->available->$unit);
                //out("Quantity: $quantity");
                //out("Available: {$market_info->available->$unit}");
                $result = buy_public($c, array($unit => $quantity), array($unit => $market_info->buy_price->$unit));  //Buy troops!
                $market_info = get_market_info();
                $money = $c->money - $reserve;
                if ($result->bought->$unit->quantity == 0) {
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
    if ($c->foodnet > 0) {
        return 1000; //POSITIVE FOOD, CAN LAST FOREVER BASICALLY
    }
    $foodloss = -1*$c->foodnet;
    return floor($c->food/$foodloss);
}

function food_management(&$c)
{
 //RETURNS WHETHER TO HOLD TURNS OR NOT
    if (turns_of_food($c) >= 50) {
        return false;
    }
    
    //out("food management");
    $foodloss = -1*$c->foodnet;
    $turns_buy = 50;
    
    $c = get_advisor();     //UPDATE EVERYTHING
    $market_info = get_market_info();   //get the Public Market info
    //out_data($market_info);
    $pm_info = get_pm_info();
    while ($turns_buy > 1 && $c->food <= $turns_buy*$foodloss && $market_info->buy_price->m_bu != null) {
        $turns_of_food = $foodloss*$turns_buy;
        $market_price = $market_info->buy_price->m_bu;
        //out("Market Price: " . $market_price);
        if ($c->food < $turns_of_food && $c->money > $turns_of_food*$market_price*((100+$c->g_tax)/100)) { //losing food, less than turns_buy turns left, AND have the money to buy it
            $quantity = min($foodloss*$turns_buy, $market_info->available->m_bu);
            out("Less than $turns_buy turns worth of food! (" . $c->foodnet .  "/turn) Buy $quantity food off Public @\$$market_price if we can!");     //Text for screen
            $result = buy_public($c, array('m_bu' => $quantity), array('m_bu' => $market_price));     //Buy 3 turns of food off the public at or below the PM price
            $market_info = get_market_info();   //get the Public Market info
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
        out("We make enough to buy food if we want to; hold turns for now.");   //Text for screen
        return true;
    }
    
    //WAIT FOR GOODS/TECH TO SELL
    if ($c->protection == 0 && $c->turns_stored < 30 && onmarket_value() > $pm_info->buy_price->m_bu*$foodloss) {
        out("We have goods on market; hold turns for now.");    //Text for screen
        return true;
    }
    
    //PUT GOODS/TECH ON MKT AS APPROPRIATE
    
    
    if ($c->food < $turns_of_food && $c->money > $turns_buy*$foodloss*$pm_info->buy_price->m_bu) { //losing food, less than turns_buy turns left, AND have the money to buy it
        out("Less than $turns_buy turns worth of food! (" . $c->foodnet .  "/turn) We're rich, so buy food on PM (\${$pm_info->buy_price->m_bu})!~");   //Text for screen
        $result = buy_on_pm($c, array('m_bu' => $turns_buy*$foodloss));  //Buy 3 turns of food!
        return false;
    } elseif ($c->food < $turns_of_food && total_military($c) > 50) {
        out("We're too poor to buy food! Sell 1/10 of our military");   //Text for screen
        sell_all_military($c, 1/10);     //sell 1/4 of our military
        $c = get_advisor();     //UPDATE EVERYTHING
        return food_management($c); //RECURSION!
    }
    
    return false;
}

function defend_self(&$c, $reserve_cash)
{
    if ($c->protection) {
        return;
    }
    //BUY MILITARY?
    $spend = $c->money - $reserve_cash;
    $nlg_target = floor(80 + $c->turns_played/7);
    $dpnw = 300;
    $nlg = nlg($c);
    while ($nlg < $nlg_target && $spend >= 1000 && $dpnw < 380) {
        out("Try to buy goods at $dpnw dpnw or below to reach NLG of $nlg_target from $nlg!");  //Text for screen
        buy_public_below_dpnw($c, $dpnw, $spend, true);
        $spend = $c->money - $reserve_cash;
        
        buy_private_below_dpnw($c, $dpnw, $spend, true);
        $dpnw += 20;
        $c = get_advisor();     //UPDATE EVERYTHING
        $spend = $c->money - $reserve_cash;
        $nlg = nlg($c);
    }
}

function nlg(&$c)
{
    switch ($c->govt) {
        case 'R':
            $govt = 0.9;
            break;
        case 'I':
            $govt = 1.25;
            break;
        default:
            $govt = 1.0;
    }
    return floor($c->networth/($c->land*$govt));
}

/*
function food_pm_price($c){
	$pm_info = get_pm_info();
	return $pm_info->sell_price->m_bu;
}*/
