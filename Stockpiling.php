<?php

namespace EENPC;


function stash_excess_bushels_on_public_if_needed(&$c, $rules) {
    $excess_bushels = $c->food - 500000 + $rules->maxturns * min(0, $c->foodnet);

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
    if($c->money + $c->turns * max(0, $c->income)  < $min_cash_to_calc)
        return 700; // use 700 if we don't need to stockpile yet

    $sell_price = predict_destock_bushel_sell_price($c, $rules);

    $current_market_price  = PublicMarket::price('m_bu');
    $value_kept_decimal = $sell_price / ((2 + $current_market_price) * $c->tax());
    // no taxes in below code because buying functions handle that
    $tech_value = round(700 / max($value_kept_decimal, 0.4)); // don't stock tech if we expect to lose more than 60%

    log_country_message($c->cnum, "The inherent value for tech is calculated as $tech_value");

    return $tech_value;
}


function get_stockpiling_weights ($c, $server, $rules, $cpref, $min_cash_to_calc, $allow_bushels, $allow_tech, $allow_military) {
    // the stockpiling weight should give the max price we're willing to buy a score of 1000 (score is price / weight)
    // right now our max loss is 60% of value, so use that
    $stockpiling_weights = [];

    // don't see a reason to log country messages at this time: spend_extra_money() has pretty verbose logging already

    if($c->money + $c->turns * max(0, $c->income)  < $min_cash_to_calc)
        return $stockpiling_weights;

    log_country_message($c->cnum, "Calculating stockpiling weights. Price / weight = 1000 for expected 60$ loss of value");    

    if($allow_bushels) {
        $bushel_sell_price = predict_destock_bushel_sell_price($c, $rules);
        $bushel_weight = round($bushel_sell_price / 400, 2); // this is the weight that makes a score of 1000 too high for 60% loss
        log_country_message($c->cnum, "The stockpiling price weight for bushels is $bushel_weight");
        $stockpiling_weights['m_bu'] = $bushel_weight;
    }

	if($allow_tech) {
        $tech_weight = round(1750 / 1000, 2); // 1750 is 700 base value of tech with 60% loss
        log_country_message($c->cnum, "The stockpiling price weight for tech is $tech_weight");
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
	}

    if($allow_military){
        $server_new_turns_remaining = floor(($server->reset_end - time()) / $server->turn_rate); // FUTURE: function?

        $unit_exp = ['m_tr' => 0.11,'m_j' => 0.14,'m_tu' => 0.18,'m_ta' => 0.57]; // TODO: food seems like a hassle, same with NW modifier
        $unit_nw = ['m_tr'=>0.5, 'm_j' => 0.6, 'm_tu' => 0.6, 'm_ta' => 2.0];
        foreach($unit_nw as $unit => $unit_nw) {
            $mil_weight = round(0.001 * 0.01 * $c->pt_mil * 
                (
                    -1 * $unit_exp[$unit] * ($c->turns_stored + $server_new_turns_remaining) + // ignoring turns on hand feels reasonable, we might not play all turns anyway
                    $unit_nw * 2025 * ($c->govt == "H" ? 0.8 : 1.0) / (6.5 * 0.4)
                )
            , 2);
            if($mil_weight < 0) {
                $mil_weight = 0.0001;    
                log_country_message($c->cnum, "The stockpiling price weight for $unit is $mil_weight due to high expenses");
            }
            else
                log_country_message($c->cnum, "The stockpiling price weight for $unit is $mil_weight");
            $stockpiling_weights[$unit] = $mil_weight;
        }
        
        // TODO: techer should use fewer turns because it destocks earlier? although it can keep teching...

        // the weight is based on the max price that we can buy that still retains 40% of value
        // here we calc dpnw and compare it to 40% of the dpnw that we expect to get at the end from our private market
        // if we're stocking military it's probably close to the end, so current mil tech is fine        
    }
    
    // FUTURE: $allow_oil - hard to assign a base value outside of express

    if(empty($stockpiling_weights)) {
        log_error_message(999, $c->cnum, "get_stockpiling_weights(): must allow at least one type of good");        
    }

    return $stockpiling_weights;
}


function spend_extra_money_on_stockpiling(&$c, $cpref, $money_to_reserve, $stockpiling_weights) {
    if(empty($stockpiling_weights)) {
        log_error_message(999, $c->cnum, "spend_extra_money_on_stockpiling(): passed in empty array for stockpiling weights");
        return 0;
    }
    // this is useless, but spend_money_on_markets expects it
    $unit_points = array_combine(array_keys($stockpiling_weights), array_fill(0, count($stockpiling_weights), 1));
    // don't change 1000 without changing get_stockpiling_weights()
    $spent = spend_money_on_markets($c, $cpref, 999999999999, $c->money - $money_to_reserve, $stockpiling_weights, $unit_points, "stock", 1000, false);
    log_country_message($c->cnum, "Finished stockpiling purchases and spent $spent");
    return $spent;
}