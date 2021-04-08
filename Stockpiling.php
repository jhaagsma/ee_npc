<?php

namespace EENPC;


function stash_excess_bushels_on_public_if_needed(&$c, $rules) {
    $excess_bushels = $c->food + $rules->maxturns * min(0, $c->foodnet);

    // TODO: check that income is positive so we don't OOM

    if($excess_bushels > 3500000) {
        $quantity = ['m_bu' => $excess_bushels];
        $min_sell_price = PublicMarket::price('m_bu') + mt_rand(20, 40); // FUTURE: pref?
        $max_sell_price = 200; // TODO: is this safe for all countries?
        $std_dev = ($max_sell_price - $min_sell_price) / 2;   
        $sell_price = floor(Math::half_bell_truncate_left_side($min_sell_price, $max_sell_price, $std_dev));
        $price   = ['m_bu' => $sell_price]; 
        log_country_message($c->cnum, "Selling $excess_bushels bushels at high prices to stash them away");
        $res = PublicMarket::sell($c, $quantity, $price); 
        update_c($c, $res);
        return true; // FUTURE: catch fails?
    }
    return false;
}

function get_inherent_value_for_tech ($c, $rules, $min_cash_to_calc = 2000000000) {
    if($c->money + $c->turns * max(0, $c->income)  < $min_cash_to_calc) {
        $tech_value = 700; // use 700 if we don't need to stockpile yet
    }
    else {
        $bushel_sell_price = predict_destock_bushel_sell_price($c, $rules);

        $current_bushel_market_price  = PublicMarket::price('m_bu');
        log_country_message($c->cnum, "The expected bushel buy price for stocking is 2 above current price of $current_bushel_market_price");
        $value_kept_decimal = $bushel_sell_price / ((2 + $current_bushel_market_price) * $c->tax());
        // no taxes in below code because buying functions handle that
        $tech_value = max(700, round(700 / max($value_kept_decimal, 0.4))); // don't sell tech at prices where we expect to lose more than 60% / TODO: cpref
    }

    log_country_message($c->cnum, "The inherent value for tech is calculated as $tech_value");
    return $tech_value;
}


function get_techer_min_sell_price($c, $cpref, $rules, $min_cash_to_calc = 2000000000) {
    if($c->money + $c->turns * max(0, $c->income)  < $min_cash_to_calc) {
        $tech_min_price = $rules->market_autobuy_tech_price; // use server min if we don't have tons of cash
        log_country_message($c->cnum, "Tech minimum sell price calculated as $tech_min_price based on server rules");
    }
    else {
        // if we're stockpiling then our min tech sell price should be based on the bushel price
        // it doesn't make sense to sell tech for $800 to buy bushels for $50 to sell those bushels later for $36
        $bushel_sell_price = predict_destock_bushel_sell_price($c, $rules);
        $current_bushel_market_price  = PublicMarket::price('m_bu');
        log_country_message($c->cnum, "The expected bushel buy price for stocking is 2 above current price of $current_bushel_market_price");
        $value_kept_decimal = $bushel_sell_price / ((2 + $current_bushel_market_price) * $c->tax());
        $tech_min_price = max($rules->market_autobuy_tech_price, round((2 - $c->tax()) * 700 / max($value_kept_decimal, 0.4))); // TODO: cpref
        log_country_message($c->cnum, "Tech minimum sell price calculated as $tech_min_price based on bushel prices");
    }
    return $tech_min_price;
}


function get_stockpiling_weights_and_adjustments (&$stockpiling_weights, &$stockpiling_adjustments, $c, $server, $rules, $cpref, $min_cash_to_calc, $allow_bushels, $allow_tech, $allow_military) {
    // the stockpiling weight should give the max price we're willing to buy a score of 1000 (score is price / weight)
    
    $max_loss = 60; // right now our max loss is 60% of value, so use that TODO: cpref
    $stockpiling_weights = [];
    $stockpiling_adjustments = [];

    if($c->money + $c->turns * max(0, $c->income)  < $min_cash_to_calc)
        return false;

    log_country_message($c->cnum, "Calculating stockpiling weights. (Price + adjustment) / weight = 1000 for expected 60% loss of value");    

    /*
    weight = $max_loss * sell_value / (1000 * (100 - $max_loss))

    sell_value is what we expect it to be worth end of set    

    the point of all of this is to end up with a score of 0 for break even and a score of 1000 for the max_loss, with it linearly increasing as price goes up
      
    the adjustment for non-mil units is the sell_value
    for military units, the adjustment is end of set buying plice + expenses- they make the paid price higher, but don't change the weight calculation
    */


    if($allow_bushels) {
        $bushel_sell_price = predict_destock_bushel_sell_price($c, $rules);
        $bushel_weight = round($max_loss * $bushel_sell_price / (1000 * (100 - $max_loss)), 4);
        $bushel_adjustment = -1 * $bushel_sell_price;
        log_country_message($c->cnum, "The stockpiling price weight for bushels is $bushel_weight and the adjustment is $bushel_adjustment");
        $stockpiling_weights['m_bu'] = $bushel_weight;
        $stockpiling_adjustments['m_bu'] = $bushel_adjustment;
    }

	if($allow_tech) {
        $tech_sell_price = 700; // inherent value of tech
        $tech_weight = round($max_loss * $tech_sell_price / (1000 * (100 - $max_loss)), 4);
        $tech_adjustment = -1 * $tech_sell_price;
        log_country_message($c->cnum, "The stockpiling price weight for tech is $tech_weight and the adjustment is $tech_adjustment");
		$stockpiling_weights["mil"] = $tech_weight; // FUTURE: :(
		$stockpiling_weights["med"] = $tech_weight;
		$stockpiling_weights["bus"] = $tech_weight;
		$stockpiling_weights["res"] = $tech_weight;
		$stockpiling_weights["agri"] = $tech_weight;
		$stockpiling_weights["war"] = $tech_weight;
		$stockpiling_weights["ms"] = $tech_weight;
		$stockpiling_weights["weap"] = $tech_weight;
		$stockpiling_weights["indy"] = $tech_weight;
		$stockpiling_weights["spy"] = $tech_weight;
		$stockpiling_weights["sdi"] = $tech_weight;
        $stockpiling_adjustments["mil"] = $tech_adjustment;
		$stockpiling_adjustments["med"] = $tech_adjustment;
		$stockpiling_adjustments["bus"] = $tech_adjustment;
		$stockpiling_adjustments["res"] = $tech_adjustment;
		$stockpiling_adjustments["agri"] = $tech_adjustment;
		$stockpiling_adjustments["war"] = $tech_adjustment;
		$stockpiling_adjustments["ms"] = $tech_adjustment;
		$stockpiling_adjustments["weap"] = $tech_adjustment;
		$stockpiling_adjustments["indy"] = $tech_adjustment;
		$stockpiling_adjustments["spy"] = $tech_adjustment;
		$stockpiling_adjustments["sdi"] = $tech_adjustment;
	}

    if($allow_military){
        $server_new_turns_remaining = floor(($server->reset_end - time()) / $server->turn_rate); // FUTURE: function?
        $military_end_dpnw = 2025 * 0.01 * ($c->govt == "H" ? 1.0 : 0.8) * $c->pt_mil / 6.5; // use what we can get on PM
        $unit_exp = ['m_tr' => 0.11,'m_j' => 0.14,'m_tu' => 0.18,'m_ta' => 0.57]; // TODO: food seems like a hassle, same with NW modifier
        $unit_nw = ['m_tr'=>0.5, 'm_j' => 0.6, 'm_tu' => 0.6, 'm_ta' => 2.0];
        foreach($unit_nw as $unit => $unit_nw) {
            $military_unit_sell_price = $unit_nw * $military_end_dpnw;
            $military_unit_weight = round($max_loss * $military_unit_sell_price / (1000 * (100 - $max_loss)), 4);
            // expenses make the unit "cost" more
            $military_unit_adjustment = round(0.01 * $c->pt_mil * $unit_exp[$unit] * ($c->turns_stored + $server_new_turns_remaining) - $military_unit_sell_price, 0);

            $stockpiling_weights[$unit] = $military_unit_weight;
            $stockpiling_adjustments[$unit] = $military_unit_adjustment;
            log_country_message($c->cnum, "The stockpiling price weight for $unit is $military_unit_weight and the adjustment is $military_unit_adjustment");
        }
        
        // TODO: techer should use fewer turns because it destocks earlier? although it can keep teching... 
    }
    
    // FUTURE: $allow_oil - hard to assign a base value outside of express

    if(empty($stockpiling_weights)) {
        log_error_message(999, $c->cnum, "get_stockpiling_weights(): must allow at least one type of good");        
    }

    return true;
}


function spend_extra_money_on_stockpiling(&$c, $cpref, $money_to_reserve, $stockpiling_weights, $stockpiling_adjustments) {
    if(empty($stockpiling_weights) or empty($stockpiling_adjustments)) {
        log_error_message(999, $c->cnum, "spend_extra_money_on_stockpiling(): passed in empty array");
        return 0;
    }
    // this is useless, but spend_money_on_markets expects it
    $unit_points = array_combine(array_keys($stockpiling_weights), array_fill(0, count($stockpiling_weights), 1));
    // don't change 1000 without changing get_stockpiling_weights()
    $spent = spend_money_on_markets($c, $cpref, 999999999999, $c->money - $money_to_reserve, $stockpiling_weights, $unit_points, "stock", 1000, false, $stockpiling_adjustments);
    log_country_message($c->cnum, "Spent $spent and finished stockpiling purchases");
    return $spent;
}