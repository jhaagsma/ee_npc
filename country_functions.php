<?php

namespace EENPC;



function is_country_allowed_to_mass_explore($c, $cpref, $server) {    
    if($c->explore_rate == $c->explore_min) {
        log_country_message($c->cnum, "Country is not allowed to mass explore because explore rate is the minimum $c->explore_min");
        return false;
    }
    elseif(!$server->is_clan_server and $c->govt == "R" and $c->land > 10000) {
        log_country_message($c->cnum, "Country is not allowed to mass explore because it is a rep with more than 10000 acres on a non-clan server");
        return false;
    }
    elseif(!$server->is_clan_server and $c->govt <> "R" and $c->land > 8200) {
        log_country_message($c->cnum, "Country is not allowed to mass explore because it is a non-rep with more than 8200 acres on a non-clan server");
        return false;
    }
    else {
        log_country_message($c->cnum, "Country is allowed to mass explore");
        return true;
    }
}


/*
NAME: food_and_money_for_turns
PURPOSE: as needed, try to acquire food and money to run the specified number of turns
RETURNS: true if we have enough food and money to run turns, false otherwise
PARAMETERS:
	$c - the country object
	$turns_to_play - number of turns we want to play
	$money_to_reserve - money that we cannot spend and have to keep in reserve
	$is_cashing - 1 if cashing, 0 if not	
*/
function food_and_money_for_turns(&$c, $turns_to_play, $money_to_reserve, $is_cashing) {
	$incoming_money_per_turn = ($is_cashing ? 1.0 : 1.2) * $c->taxes;
	$additional_turns_for_expenses_growth = floor($turns_to_play / 5);
	// check money
	if(!has_money_for_turns($turns_to_play + $additional_turns_for_expenses_growth, $c->money, $incoming_money_per_turn, $c->expenses, $money_to_reserve)) {
		// not enough money to play a turn - can we make up the difference by selling a turn's worth of food production?
		if($c->food > 0 and $c->foodnet > 0) {
			// log_country_message($c->cnum, "TEMP DEBUG: Food is ".$c->food); // FUTURE: figure out why this errors sometimes? could be fixed now - Slagpit 20210329
			PrivateMarket::sell_single_good($c, 'm_bu', min($c->food, ($turns_to_play + $additional_turns_for_expenses_growth) * $c->foodnet));
		}
		
		if (!has_money_for_turns($turns_to_play + $additional_turns_for_expenses_growth, $c->money, $incoming_money_per_turn, $c->expenses, $money_to_reserve)) {
			// playing turns is no longer productive
			log_country_message($c->cnum, "Not enough money to play $turns_to_play turns. Money is $c->money and money to reserve is $money_to_reserve");
			return false;
		}
	}

	// try to buy food if needed up to $60, quit if we can't find cheap enough food
	// FUTURE - be smarter about picking $60

	$food_needed = max(0, get_food_needs_for_turns($turns_to_play + $additional_turns_for_expenses_growth, $c->foodpro, $c->foodcon) - $c->food);
	//log_country_message($c->cnum, "Food is $c->food, food consumption is $c->foodcon, and calculated food needs are $food_needed");	
			
	if(!buy_full_food_quantity_if_possible($c, $food_needed, 60, $money_to_reserve)) {
		log_country_message($c->cnum, "Not enough food to play $turns_to_play turns. Food is $c->food, money is $c->money, and money to reserve is $money_to_reserve");				
		return false;
	}

	return true;
}



function get_total_value_of_on_market_goods($c, $max_price_bushels_to_include = 999) {
    $owned_on_market_info = get_owned_on_market_info();

    $total_value = 0;
    foreach($owned_on_market_info as $market_package) {        
        $type = $market_package->type;
        $price = $market_package->price;
        $quantity = $market_package->quantity;

        if($type <> 'm_bu' or $price <= $max_price_bushels_to_include)
            $total_value += $price * $quantity * (2 - $c->tax());
    }

    return $total_value;
}


// can't call this for many turns for CI
function has_money_for_turns($number_of_turns_to_play, $money, $incoming_money_per_turn, $expenses_per_turn, $money_to_reserve, $force_negative_events = false) {
    // FUTURE: add estimate based on indy production
    // divide by 3 is negative cash event
    // 1.1 is pessimistic expenses increase
    $expected_money_change_from_turn = ($force_negative_events ? $incoming_money_per_turn / 3 : $incoming_money_per_turn) - 1.1 * $expenses_per_turn;

    if ($expected_money_change_from_turn > 0) // should be ok if money is positive
        return true;
    
    if ($money + $number_of_turns_to_play * $expected_money_change_from_turn < $money_to_reserve)
        return false;

    return true;
}



// tries to buy the requested food within the budget
// stops buying food if it detects that prices are too high
function buy_full_food_quantity_if_possible(&$c, $food_needed, $max_food_price_to_buy, $money_to_reserve, $purchase_attempt_number = 1) {
    // FUTURE: consider private market as well (express might have cheaper food than public)
    if ($food_needed <= 0) {// quit because we bought all of the food we needed
        return true;
    }

    if ($purchase_attempt_number >= 99) { // 100 is the default PHP limit, and 99 feels like more than enough
        log_country_message($c->cnum, "Could not purchase food because code hit recursion limit of 99");
        return false;
    }

    $current_public_market_bushel_price = PublicMarket::price('m_bu');
    if ($current_public_market_bushel_price == 0 or $current_public_market_bushel_price == null) {
        log_country_message($c->cnum, "Could not purchase food because public market is empty");
        return false;
    }

    if ($current_public_market_bushel_price > $max_food_price_to_buy) {
        log_country_message($c->cnum, "Could not purchase food because public market price is too high");
        return false;
    }    

    if(($c->money - $money_to_reserve) < ($food_needed * $c->tax() * $current_public_market_bushel_price)) {
        log_country_message($c->cnum, "Could not purchase food because not enough money");
        return false;
    }

    // try to buy food
    $prev_food = $c->food;
    PublicMarket::buy($c, ['m_bu' => $food_needed], ['m_bu' => $current_public_market_bushel_price]);
    $food_diff = $c->food - $prev_food;
    return buy_full_food_quantity_if_possible($c, $food_needed - $food_diff, $max_food_price_to_buy, $money_to_reserve, $purchase_attempt_number + 1);
} 


function get_food_needs_for_turns($number_of_turns_to_play, $food_production, $food_consumption, $force_negative_events = false) {
    // dividing by 3 is for food production negative events
    // multiplying by 2 is to account for military units on the market
    $expected_food_change_from_turn = floor($force_negative_events ? $food_production / 3 : $food_production) - 2 * $food_consumption;

    return max(0, -1 * $number_of_turns_to_play * $expected_food_change_from_turn);
}


function sell_cheap_units(&$c, $unit = 'm_tr', $fraction = 1)
{
    $fraction   = max(0, min(1, $fraction));
    $sell_units = [$unit => floor($c->$unit * $fraction)];
    if (array_sum($sell_units) == 0) {
        log_country_message($c->cnum, "No Military!");
        return;
    }
    return PrivateMarket::sell($c, $sell_units);  //Sell 'em
}//end sell_cheap_units()



function sell_all_military(&$c, $fraction = 1)
{
    $fraction   = max(0, min(1, $fraction));
    $sell_units = [
        'm_spy'     => floor($c->m_spy * $fraction),         //$fraction of spies
        'm_tr'  => floor($c->m_tr * $fraction),      //$fraction of troops
        'm_j'   => floor($c->m_j * $fraction),       //$fraction of jets
        'm_tu'  => floor($c->m_tu * $fraction),      //$fraction of turrets
        'm_ta'  => floor($c->m_ta * $fraction)       //$fraction of tanks
    ];
    if (array_sum($sell_units) == 0) {
        log_country_message($c->cnum, "No Military!");
        return;
    }
    return PrivateMarket::sell($c, $sell_units);  //Sell 'em
}//end sell_all_military()


function turns_of_food(&$c)
{
    if ($c->foodnet >= 0) {
        return 1000; //POSITIVE FOOD, CAN LAST FOREVER BASICALLY
    }
    $foodloss = -1 * $c->foodnet;
    return floor($c->food / $foodloss);
}//end turns_of_food()


function turns_of_money(&$c)
{
    if ($c->income > 0) {
        return 1000; //POSITIVE INCOME
    }
    $incomeloss = -1 * $c->income;
    return floor($c->money / $incomeloss);
}//end turns_of_money()


function money_management(&$c, $server_max_possible_market_sell)
{
    while (turns_of_money($c) < 4) {
        //$foodloss = -1 * $c->foodnet;

        if ($c->turns_stored <= 30 && total_cansell_military($c, $server_max_possible_market_sell) > 7500) {
            log_country_message($c->cnum, "Selling max military, and holding turns.");
            sell_max_military($c, $server_max_possible_market_sell);
            return true;
        } elseif ($c->turns_stored > 30 && total_military($c) > 1000) {
            log_error_message(1002, $c->cnum, "We have stored turns or can't sell on public; sell 1/10 of military");
            sell_all_military($c, 1 / 10);
        } else {
            log_country_message($c->cnum, "Low stored turns ({$c->turns_stored}); can't sell? (".total_cansell_military($c, $server_max_possible_market_sell).')');
            return true;
        }
    }

    return false;
}//end money_management()


function food_management(&$c)
{
    //RETURNS WHETHER TO HOLD TURNS OR NOT
    $reserve = max(130, $c->turns);
    if (turns_of_food($c) >= $reserve) {
        return false;
    }

    //log_country_message($c->cnum, "food management");
    $foodloss  = -1 * $c->foodnet;
    $turns_buy = max($reserve - turns_of_food($c), 50);

    //$c = get_advisor();     //UPDATE EVERYTHING
    //global $market;
    //$market_info = get_market_info();   //get the Public Market info
    //out_data($market_info);
    $pm_info = PrivateMarket::getRecent($c);   //get the PM info

    while ($turns_buy > 1 && $c->food <= $turns_buy * $foodloss && PublicMarket::price('m_bu') != null) {
        $turns_of_food = $foodloss * $turns_buy;
        $market_price  = PublicMarket::price('m_bu');

        if($market_price >= 100 && $c->turns_stored < 30) { // FUTURE: this isn't really any good, but it's better than nothing
            log_country_message($c->cnum, "Public market food is too expensive at $market_price; hold turns for now, and wait for food on MKT.");
            return true;
        }

        //log_country_message($c->cnum, "Market Price: " . $market_price);
        if ($c->food < $turns_of_food && $c->money > $turns_of_food * $market_price * $c->tax() && $c->money - $turns_of_food * $market_price * $c->tax() + $c->income * $turns_buy > 0) { //losing food, less than turns_buy turns left, AND have the money to buy it
            $quantity = min($foodloss * $turns_buy, PublicMarket::available('m_bu'));
            // log_country_message($c->cnum, 
            //     "--- FOOD:  - Buy Public ".str_pad('('.$turns_buy, 17, ' ', STR_PAD_LEFT).
            //     " turns)".str_pad(" @ \$".$market_price, 18, ' ', STR_PAD_LEFT).
            //     str_pad($quantity. ' Bu', 28, ' ', STR_PAD_LEFT).
            //     " ".str_pad("(".$c->foodnet."/turn)", 15, ' ', STR_PAD_LEFT),
            //     true,
            //     'brown'
            // );     //Text for screen
            log_country_message($c->cnum, "--- LOW FOOD ---", true, 'brown'); //Text for screen


            //Buy 3 turns of food off the public at or below the PM price
            $result = PublicMarket::buy($c, ['m_bu' => $quantity], ['m_bu' => $market_price]);
            if ($result === false) {
                PublicMarket::update();
            }
            //PublicMarket::relaUpdate('m_bu', $quantity, $result->bought->m_bu->quantity);

            $c = get_advisor();     //UPDATE EVERYTHING
        }
        /*else
            log_country_message($c->cnum, "$turns_buy: " . $c->food . ' < ' . $turns_of_food . ';
                 $' . $c->money . ' > $' . $turns_of_food*$market_price);*/

        $turns_buy--;
    }
    $turns_buy     = min(4, max(1, $turns_buy)); // changed from 3 to 4 to cover scenario of building 4 cs????? this doesn't work
    $turns_of_food = $foodloss * $turns_buy;

    if ($c->food > $turns_of_food) {
        return false;
    }

    //WE HAVE MONEY, WAIT FOR FOOD ON MKT
    if ($c->protection == 0 && $c->turns_stored < 30 && $c->income > 64 * $foodloss) { // 64 is a reasonably high price for public bushel price
        //Text for screen
        log_country_message($c->cnum, "We make enough to buy food if we want to; hold turns for now, and wait for food on MKT.");
        return true;
    }

    //WAIT FOR GOODS/TECH TO SELL
    if ($c->protection == 0 && $c->turns_stored < 30 && onmarket_value() > 64 * $foodloss) {
        log_country_message($c->cnum, "We have goods on market; hold turns for now.");    //Text for screen
        return true;
    }

    if ($c->food < $turns_of_food && $c->money > $turns_buy * $foodloss * $pm_info->buy_price->m_bu) {
        //losing food, less than turns_buy turns left, AND have the money to buy it
        //Text for screen
        // FUTURE: need to check quantity of food available on private market
        log_country_message($c->cnum, 
            "Less than $turns_buy turns worth of food! (".$c->foodnet."/turn) ".
            "We're rich, so buy food on PM (\${$pm_info->buy_price->m_bu})!~"
        );
        $result = PrivateMarket::buy($c, ['m_bu' => $turns_buy * $foodloss]);  //Buy 3 turns of food!        
        return false;
    } elseif ($c->food < $turns_of_food && total_military($c) > 50) {
        log_error_message(1002, $c->cnum, "We're too poor to buy food! Sell 1/10 of our military");
        sell_all_military($c, 1 / 10);     //sell 1/4 of our military
        $c = get_advisor();     //UPDATE EVERYTHING
        return food_management($c); //RECURSION!
    }

    log_country_message($c->cnum, 'We have exhausted all food options. Valar Morguhlis.');
    return true; // FUTURE: changed to true. isn't it better to save turns than to commit suicide by running turns with no food?
}//end food_management()


// deliberately using wrong values for NW to encourage bots to buy jets/turrets off public - Slagpit 20210325
function minDpnw(&$c, $onlyDef = false)
{
    $pm_info = PrivateMarket::getRecent($c);   //get the PM info

    PublicMarket::update();
    $pub_tr = PublicMarket::price('m_tr') * $c->tax() / 0.45; // wrong on purpose
    $pub_j  = PublicMarket::price('m_j') * $c->tax() / 0.6;
    $pub_tu = PublicMarket::price('m_tu') * $c->tax() / 0.6;
    $pub_ta = PublicMarket::price('m_ta') * $c->tax() / 1.8;

    $dpnws = [
        'pm_tr' => round($pm_info->buy_price->m_tr / 0.45),// wrong on purpose
        'pm_j' => round($pm_info->buy_price->m_j / 0.6),
        'pm_tu' => round($pm_info->buy_price->m_tu / 0.6),
        'pm_ta' => round($pm_info->buy_price->m_ta / 1.8),
        'pub_tr' => $pub_tr == 0 ? 9000 : $pub_tr,
        'pub_j' => $pub_j == 0 ? 9000 : $pub_j,
        'pub_tu' => $pub_tu == 0 ? 9000 : $pub_tu,
        'pub_ta' => $pub_ta == 0 ? 9000 : $pub_ta,
    ];

    if ($onlyDef) {
        unset($dpnws['pm_j']);
        unset($dpnws['pub_j']);
    }

    return min($dpnws);
}//end minDpnw()


// Can't retire this code yet because the rainbow strat uses it
function defend_self(&$c, $reserve_cash = 50000, $dpnwMax = 380)
{
    if ($c->protection) {
        return;
    }
    //BUY MILITARY?
    $spend      = $c->money - $reserve_cash;
    $nlg_target = $c->nlgTarget();
    $dpnw       = minDpnw($c, true); //ONLY DEF
    $nlg        = $c->nlg();
    $dpat       = $c->dpat ?? $c->defPerAcreTarget();
    $dpa        = $c->defPerAcre();
    $outonce    = false;

    while (($nlg < $nlg_target || $dpa < $dpat) && $spend >= 100000 && $dpnw < $dpnwMax) {
        if (!$outonce) {
            if ($dpa < $dpat) {
                log_country_message($c->cnum, "--- DPA Target: $dpat (Current: $dpa)");  //Text for screen
            } else {
                log_country_message($c->cnum, "--- NLG Target: $nlg_target (Current: $nlg)");  //Text for screen
            }

            $outonce = true;
        }

        // log_country_message($c->cnum, "0.Hash: ".spl_object_hash($c));

        $dpnwOld = $dpnw;
        $dpnw    = minDpnw($c, $dpa < $dpat); //ONLY DEF
        //log_country_message($c->cnum, "Old DPNW: ".round($dpnwOld, 1)."; New DPNW: ".round($dpnw, 1));
        if ($dpnw <= $dpnwOld) {
            $dpnw = $dpnwOld + 10; // fewer loops - hoping this helps with code running for ~60 s under empty market scenarios - Slagpit 20210321
        }

        buy_public_below_dpnw($c, $dpnw, $spend, true, true); //ONLY DEF

        // log_country_message($c->cnum, "7.Hash: ".spl_object_hash($c));

        $spend = max(0, $c->money - $reserve_cash);
        $nlg   = $c->nlg();
        $dpa   = $c->defPerAcre();
        $c     = get_advisor();     //UPDATE EVERYTHING

        // log_country_message($c->cnum, "8.Hash: ".spl_object_hash($c));

        if ($spend < 100000) {
            break;
        }

        buy_private_below_dpnw($c, $dpnw, $spend, true, true); //ONLY DEF
        $dpnwOld = $dpnw;
        $dpnw    = minDpnw($c, $dpa < $dpat); //ONLY DEF if dpa < dpat
        if ($dpnw <= $dpnwOld) {
            $dpnw = $dpnwOld + 10; // fewer loops - hoping this helps with empty market scenarios  - Slagpit 20210321
        }
        $c     = get_advisor();     //UPDATE EVERYTHING
        $spend = max(0, $c->money - $reserve_cash);
        $nlg   = $c->nlg();
        $dpa   = $c->defPerAcre();
    }
}//end defend_self()



function sell_max_military(&$c, $server_max_possible_market_sell)
{
    $c = get_advisor();     //UPDATE EVERYTHING
    //$market_info = get_market_info();   //get the Public Market info

    $pm_info = PrivateMarket::getRecent($c);   //get the PM info

    global $military_list;

    $quantity = [];
    foreach ($military_list as $unit) {
        $quantity[$unit] = can_sell_mil($c, $unit, $server_max_possible_market_sell);
    }

    $rmax    = 1.30; //percent
    $rmin    = 0.70; //percent
    $rstep   = 0.01;
    $rstddev = 0.10;
    $price   = [];
    foreach ($quantity as $key => $q) {
        if ($q == 0) {
            $price[$key] = 0;
        } elseif (PublicMarket::price($key) == null || PublicMarket::price($key) == 0) {
            $price[$key] = floor($pm_info->buy_price->$key * Math::purebell(0.7, 0.93, 0.3, 0.01)); // don't price worse than private market
        } else {
            $max         = $c->goodsStuck($key) ? 0.99 : $rmax; //undercut if we have goods stuck
            $price[$key] = min(
                floor(0.93 * $pm_info->buy_price->$key),
                floor(PublicMarket::price($key) * Math::purebell($rmin, $max, $rstddev, $rstep))
            );
        }

        if ($price[$key] > 0 && $price[$key] * $c->tax() <= $pm_info->sell_price->$key) {
            //log_country_message($c->cnum, "Public is too cheap for $key, sell on PM");
            sell_cheap_units($c, $key, 0.5);
            $price[$key]    = 0;
            $quantity[$key] = 0;
            return;
        }
    }
    /*
    $randomup = 120; //percent
    $randomdown = 80; //percent
    $price = array(
        'm_tr'=>    $quantity['m_tr'] == 0
        ? 0
        : floor(($market_info->buy_price->m_tr != null
            ? $market_info->buy_price->m_tr
            : rand(110,144))*(rand($randomdown,$randomup)/100)),
        'm_j' =>    $quantity['m_j']  == 0
            ? 0
            : floor(($market_info->buy_price->m_j  != null
                ? $market_info->buy_price->m_j
                : rand(110,192))*(rand($randomdown,$randomup)/100)),
        'm_tu'=>    $quantity['m_tu'] == 0
            ? 0
            : floor(($market_info->buy_price->m_tu != null
                ? $market_info->buy_price->m_tu
                : rand(110,200))*(rand($randomdown,$randomup)/100)),
        'm_ta'=>    $quantity['m_ta'] == 0
            ? 0
            : floor(($market_info->buy_price->m_ta != null
                ? $market_info->buy_price->m_ta
                : rand(400,560))*(rand($randomdown,$randomup)/100))
    );*/

    $result = PublicMarket::sell($c, $quantity, $price);
    if ($result == 'QUANTITY_MORE_THAN_CAN_SELL') {
        log_country_message($c->cnum, "TRIED TO SELL MORE THAN WE CAN!?!");
        $c = get_advisor();     //UPDATE EVERYTHING
    }
    global $mktinfo;
    $mktinfo = null;
    return $result;
}//end sell_max_military()


/**
 * Return a url to the AI Bot spyop for admins
 *
 * @param  int $cnum Country Number
 *
 * @return string    Spyop URL
 */
function siteURL($cnum)
{
    global $config, $server;
    $name  = $config['server'];
    $round = $server->round_num;

    return "https://www.earthempires.com/$name/$round/ranks/$cnum"; // FUTURE: parse url and use qz./slagpit. if needed?
}//end siteURL()


// deliberately using wrong values for NW to encourage bots to buy jets/turrets off public - Slagpit 20210325
function buy_public_below_dpnw(&$c, $dpnw, &$money = null, $shuffle = false, $defOnly = false)
{
    //$market_info = get_market_info();
    //out_data($market_info);
    if (!$money || $money < 0) {
        $money   = $c->money;
        $reserve = 0;
    } else {
        $reserve = $c->money - $money;
    }

    $tr_price = round($dpnw * 0.45 / $c->tax());  //THE PRICE TO BUY THEM AT // wrong on purpose
    $j_price  = $tu_price = round($dpnw * 0.6 / $c->tax());  //THE PRICE TO BUY THEM AT
    $ta_price = round($dpnw * 1.8 / $c->tax());  //THE PRICE TO BUY THEM AT

    $tr_cost = ceil($tr_price * $c->tax());  //THE COST OF BUYING THEM
    $j_cost  = $tu_cost = ceil($tu_price * $c->tax());  //THE COST OF BUYING THEM
    $ta_cost = ceil($ta_price * $c->tax());  //THE COST OF BUYING THEM

    //We should probably just do these a different way so I don't have to do BS like this
    $bah = $j_price; //keep the linter happy; we DO use these vars, just dynamically
    $bah = $tr_cost; //keep the linter happy; we DO use these vars, just dynamically
    $bah = $j_cost; //keep the linter happy; we DO use these vars, just dynamically
    $bah = $tu_cost; //keep the linter happy; we DO use these vars, just dynamically
    $bah = $ta_cost; //keep the linter happy; we DO use these vars, just dynamically
    $bah = $bah;


    $units = ['tu','tr','ta','j'];
    if ($defOnly) {
        $units = ['tu','tr','ta'];
    }

    if ($shuffle) {
        shuffle($units);
    }

    static $last = 0;
    foreach ($units as $subunit) {
        $unit = 'm_'.$subunit;
        if (PublicMarket::price($unit) != null && PublicMarket::available($unit) > 0) {
            $price = $subunit.'_price';
            $cost  = $subunit.'_cost';

            while (PublicMarket::price($unit) <= $$price
                && $money > $$cost
                && PublicMarket::available($unit) > 0
                && $money > 50000
            ) {
  
                //log_country_message($c->cnum, "Money: $money");
                //log_country_message($c->cnum, "$subunit Price: $price");
                //log_country_message($c->cnum, "Buy Price: {$market_info->buy_price->$unit}");
                $quantity = min(
                    floor($money / ceil(PublicMarket::price($unit) * $c->tax())),
                    PublicMarket::available($unit)
                );
                if ($quantity == $last) {
                    $quantity = max(0, $quantity - 1);
                }
                $last = $quantity;
                //log_country_message($c->cnum, "Quantity: $quantity");
                //log_country_message($c->cnum, "Available: {$market_info->available->$unit}");
                //Buy UNITS!
                $result = PublicMarket::buy($c, [$unit => $quantity], [$unit => PublicMarket::price($unit)]);
                PublicMarket::update();
                $money = $c->money - $reserve;
                if ($result === false
                    || !isset($result->bought->$unit->quantity)
                    || $result->bought->$unit->quantity == 0
                ) {
                    log_country_message($c->cnum, "Breaking@$unit");
                    break;
                }
            }
        }
    }
}//end buy_public_below_dpnw()


// deliberately using wrong values for NW to encourage bots to buy jets/turrets off public - Slagpit 20210325
function buy_private_below_dpnw(&$c, $dpnw, $money = 0, $shuffle = false, $defOnly = false)
{

    $pm_info = PrivateMarket::getRecent($c);   //get the PM info

    if (!$money || $money < 0) {
        $money   = $c->money;
        $reserve = 0;
    } else {
        $reserve = min($c->money, $c->money - $money);
    }

    $tr_price = round($dpnw * 0.45); // wrong on purpose
    $j_price  = $tu_price = round($dpnw * 0.6);
    $ta_price = round($dpnw * 1.8);

    $order = [1,2,3,4];

    if ($defOnly) {
        $order = [1, 2, 4];
    }

    if ($shuffle) {
        shuffle($order);
    }


    // log_country_message($c->cnum, "1.Hash: ".spl_object_hash($c));
    foreach ($order as $o) {
        $money = max(0, $c->money - $reserve);

        if ($o == 1
            && $pm_info->buy_price->m_tr <= $tr_price
            && $pm_info->available->m_tr > 0
            && $money > $pm_info->buy_price->m_tr
        ) {
            $q = min(floor($money / $pm_info->buy_price->m_tr), $pm_info->available->m_tr);
            Debug::msg("BUY_PM: Money: $money; Price: {$pm_info->buy_price->m_tr}; Q: ".$q);
            PrivateMarket::buy($c, ['m_tr' => $q]);
        } elseif ($o == 2
            && $pm_info->buy_price->m_ta <= $ta_price
            && $pm_info->available->m_ta > 0
            && $money > $pm_info->buy_price->m_ta
        ) {
            $q = min(floor($money / $pm_info->buy_price->m_ta), $pm_info->available->m_ta);
            Debug::msg("BUY_PM: Money: $money; Price: {$pm_info->buy_price->m_ta}; Q: ".$q);
            PrivateMarket::buy($c, ['m_ta' => $q]);
        } elseif ($o == 3
            && $pm_info->buy_price->m_j <= $j_price
            && $pm_info->available->m_j > 0
            && $money > $pm_info->buy_price->m_j
        ) {
            $q = min(floor($money / $pm_info->buy_price->m_j), $pm_info->available->m_j);
            Debug::msg("BUY_PM: Money: $money; Price: {$pm_info->buy_price->m_j}; Q: ".$q);
            PrivateMarket::buy($c, ['m_j' => $q]);
        } elseif ($o == 4
            && $pm_info->buy_price->m_tu <= $tu_price
            && $pm_info->available->m_tu > 0
            && $money > $pm_info->buy_price->m_tu
        ) {
            $q = min(floor($money / $pm_info->buy_price->m_tu), $pm_info->available->m_tu);
            Debug::msg("BUY_PM: Money: $money; Price: {$pm_info->buy_price->m_tu}; Q: ".$q);
            PrivateMarket::buy($c, ['m_tu' => $q]);
        }

        // log_country_message($c->cnum, "Country has \${$c->money}");
        // log_country_message($c->cnum, "3.Hash: ".spl_object_hash($c));

    }
}//end buy_private_below_dpnw()