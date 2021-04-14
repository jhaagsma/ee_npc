<?php

namespace EENPC;


function stash_excess_bushels_on_public_if_needed(&$c, $rules, $max_sell_price = null) {
    $excess_bushels = $c->food - 50000 + ($c->turns + 20) * min(0, $c->foodnet);

    // TODO: check that income is positive so we don't OOM

    if($excess_bushels > 3500000) {
        $quantity = ['m_bu' => $excess_bushels];
        if($max_sell_price) {
            $price   = ['m_bu' => $max_sell_price]; 
            log_country_message($c->cnum, "Selling excess $excess_bushels bushels at $max_sell_price price");
        }
        else {
            $current_bushel_market_price  = PublicMarket::price('m_bu');
            $min_sell_price = ($current_bushel_market_price ? $current_bushel_market_price : $rules->base_pm_food_sell_price + 10) + mt_rand(20, 40); // FUTURE: pref?
            $max_sell_price = 200;
            $std_dev = ($max_sell_price - $min_sell_price) / 2;   
            $sell_price = floor(Math::half_bell_truncate_left_side($min_sell_price, $max_sell_price, $std_dev));
            $price   = ['m_bu' => $sell_price]; 
            log_country_message($c->cnum, "Selling excess $excess_bushels bushels at high prices to stash them away");
        }
        $res = PublicMarket::sell($c, $quantity, $price); 
        update_c($c, $res); // TODO: return the result and don't do update_c? can't log to turn actions with this method
        return true; // FUTURE: catch fails?
    }
    return false;
}

function get_inherent_value_for_tech ($c, $rules, $cpref, $min_cash_to_calc = 2000000000) {
    if(!is_country_expected_to_exceed_target_cash_during_turns($c, $min_cash_to_calc)) {
        $tech_value = $cpref->base_inherent_value_for_tech; // use 700 if we don't need to stockpile yet
    }
    else {
        $bushel_sell_price = predict_destock_bushel_sell_price($c, $rules);

        $current_bushel_market_price  = PublicMarket::price('m_bu');
        log_country_message($c->cnum, "The expected bushel buy price for stocking is 2 above current price of $current_bushel_market_price");
        $value_kept_decimal = $bushel_sell_price / ((2 + $current_bushel_market_price) * $c->tax());
        // no taxes in below code because buying functions handle that
        $tech_value = max(
            $cpref->base_inherent_value_for_tech
            , round($cpref->base_inherent_value_for_tech / max($value_kept_decimal, ((100 - $cpref->max_stockpiling_loss_percent) / 100)))
        );
    }

    log_country_message($c->cnum, "The inherent value for tech is calculated as $tech_value");
    return $tech_value;
}


function get_techer_min_sell_price($c, $cpref, $rules, $min_cash_to_calc = 2000000000) {
    if(!is_country_expected_to_exceed_target_cash_during_turns($c, $min_cash_to_calc)) {
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
        $tech_min_price = max(
            $rules->market_autobuy_tech_price
        , round((2 - $c->tax()) * $cpref->base_inherent_value_for_tech / max($value_kept_decimal, ((100 - $cpref->max_stockpiling_loss_percent) / 100)))
        );
        log_country_message($c->cnum, "Tech minimum sell price calculated as $tech_min_price based on bushel prices");
    
        
    
    }
    return $tech_min_price;
}


function get_farmer_min_sell_price($c, $cpref, $rules, $server, $min_cash_to_calc = 2000000000) {
    if(!is_country_expected_to_exceed_target_cash_during_turns($c, $min_cash_to_calc)) {
        $bushel_min_price = $rules->base_pm_food_sell_price; // base on server min if we don't have tons of cash
        log_country_message($c->cnum, "Bushel minimum sell price calculated as $bushel_min_price based on server rules");
    }
    else {
        // if we're stockpiling then our min bushel price should be based on the best value out of tech and military
        // it doesn't make sense to sell bushels for $43 to buy garbage tech at $1500 to have that tech point be worth $700 end of set
        log_country_message($c->cnum, "Calculating minimum bushel sell price because cash is expected to exceed $min_cash_to_calc", 'green');
        $server_new_turns_remaining = floor(($server->reset_end - time()) / $server->turn_rate); // FUTURE: function?
        $bushel_end_sell_price = predict_destock_bushel_sell_price($c, $rules);
        $bushel_break_even_sell_prices = [];

        // public market military
        $military_end_dpnw = 2025 * 0.01 * ($c->govt == "H" ? 0.8 : 1.0) * $c->pt_mil / 6.5; // use what we can get on PM
        $unit_nw = ['m_tr'=>0.5, 'm_j' => 0.6, 'm_tu' => 0.6, 'm_ta' => 2.0];
        foreach($unit_nw as $unit => $unit_nw) {     
            $military_unit_price = PublicMarket::price($unit);
            if(!$military_unit_price)
                continue;

            $military_unit_end_value = round($unit_nw * $military_end_dpnw, 2);

            $military_unit_exp_per_turn = get_approx_expense_per_turn_for_mil_unit($unit, $unit_nw, $bushel_end_sell_price, $c->govt, $c->networth, $c->pt_mil, $c->expenses_mil);
            $military_unit_expenses = round($military_unit_exp_per_turn * ($c->turns_stored + $server_new_turns_remaining), 2);
           
            $sell_break_even_price = 1 + ceil($bushel_end_sell_price * ($c->tax() * $military_unit_price + $military_unit_expenses) / ((2 - $c->tax()) * $military_unit_end_value));
            $bushel_break_even_sell_prices[$unit] = $sell_break_even_price;

            log_country_message($c->cnum, "The break even bushel sell price for public $unit is $sell_break_even_price with price as $military_unit_price, expenses as $military_unit_expenses, value as $military_unit_end_value");
        }  


        // troop is very likely to be the best buy out of pm
        $pm_info = PrivateMarket::getInfo();
        $military_unit_end_value = round(0.5 * $military_end_dpnw, 2);
        $military_unit_price = $pm_info->buy_price->m_tr;
        // expenses make the unit "cost" more
        $military_unit_expenses = round(0.01 * $c->pt_mil * $unit_exp['m_tr'] * $server_new_turns_remaining, 2);
       
        $sell_break_even_price = 1 + ceil($bushel_end_sell_price * ($military_unit_price + $military_unit_expenses) / ((2 - $c->tax()) * $military_unit_end_value));
        $bushel_break_even_sell_prices['pm_tr'] = $sell_break_even_price;

        log_country_message($c->cnum, "The break even bushel sell price for private m_tr is $sell_break_even_price with price as $military_unit_price, expenses as $military_unit_expenses, value as $military_unit_end_value");
    

        // public market tech
        $tech_list = ['t_mil','t_med','t_bus','t_res','t_agri','t_war','t_ms','t_weap','t_indy','t_spy','t_sdi'];
        $tech_prices = [];
        foreach($tech_list as $tech_name) {
            $tech_price = PublicMarket::price($tech_name);
            if($tech_price)
                $tech_prices[] = $tech_price;
        }

        if(!empty($tech_prices)) {
            // get the fifth lowest tech price on the market, assuming there is one
            sort($tech_prices);
            reset($tech_prices);
            $tech_price_for_calc = current($tech_prices);
            for($i=0; $i<3; $i++) {
                $next_price = next($tech_prices);
                if($next_price)
                    $tech_price_for_calc = $next_price;
            }

            // cheap tech market probably isn't very deep, so add 150 to the price as a buffer
            $sell_break_even_price = 1 + ceil($bushel_end_sell_price * $c->tax() * (150 + $tech_price_for_calc) / ((2 - $c->tax()) * $cpref->base_inherent_value_for_tech));
            $bushel_break_even_sell_prices['tech'] = $sell_break_even_price;
            log_country_message($c->cnum, "The break even bushel sell price for tech is $sell_break_even_price with price as $tech_price_for_calc, buffer as 150, value as $cpref->base_inherent_value_for_tech");
        }

        // get the cheapest price
        asort($bushel_break_even_sell_prices);
        reset($bushel_break_even_sell_prices);
        $cheapest_purchase_option = key($bushel_break_even_sell_prices);
        $bushel_min_price = current($bushel_break_even_sell_prices);
        log_country_message($c->cnum, "Bushel minimum sell price calculated as $bushel_min_price based on the best value: $cheapest_purchase_option");
        if ($bushel_min_price > 200) {
            $bushel_min_price = 200;
            log_country_message($c->cnum, "Bushel minimum sell price lowered to 200 to avoid possible game errors");
        }
        
    }
    return $bushel_min_price;
}


function is_country_expected_to_exceed_target_cash_during_turns($c, $target_cash){
    return $c->money + $c->turns * max(0, $c->income) + $c->turns * 39 * max(0, $c->foodnet) >= $target_cash ? true : false;
}



function get_stockpiling_weights_and_adjustments (&$stockpiling_weights, &$stockpiling_adjustments, $c, $server, $rules, $cpref, $min_cash_to_calc, $allow_bushels, $allow_tech, $allow_military) {
    $max_loss = $cpref->max_stockpiling_loss_percent;
    $stockpiling_weights = [];
    $stockpiling_adjustments = [];

    if(!is_country_expected_to_exceed_target_cash_during_turns($c, $min_cash_to_calc))
        return false;

    log_country_message($c->cnum, "Calculating stockpiling weights: (Price + adjustment) / weight = 600 for expected 60% loss of value", 'green');    

    /* in this example 60% is the max loss so the max score is 600
    weight = $max_loss * sell_value / (600 * (100 - $max_loss))

    sell_value is what we expect it to be worth end of set    

    the point of all of this is to end up with a score of 0 for break even and a score of 600 for the max_loss, with it non-linearly increasing as price goes up
      
    the adjustment for non-mil units is the sell_value
    for military units, the adjustment is end of set buying plice + expenses- they make the paid price higher, but don't change the weight calculation
    */


    if($allow_bushels) {
        $bushel_sell_price = predict_destock_bushel_sell_price($c, $rules);
        $bushel_weight = round($max_loss * $bushel_sell_price / (10 * $max_loss * (100 - $max_loss)), 4);
        $bushel_adjustment = -1 * $bushel_sell_price;
        log_country_message($c->cnum, "The stockpiling price weight for bushels is $bushel_weight, value is $bushel_sell_price, and the adjustment is $bushel_adjustment");
        $stockpiling_weights['m_bu'] = $bushel_weight;
        $stockpiling_adjustments['m_bu'] = $bushel_adjustment;
    }

	if($allow_tech) {
        $tech_sell_price = $cpref->base_inherent_value_for_tech; // inherent value of tech
        $tech_weight = round($max_loss * $tech_sell_price / (10 * $max_loss * (100 - $max_loss)), 4);
        $tech_adjustment = -1 * $tech_sell_price;
        log_country_message($c->cnum, "The stockpiling price weight for tech is $tech_weight, value is $tech_sell_price, and the adjustment is $tech_adjustment");
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
        $military_end_dpnw = $cpref->final_dpnw_for_stocking_calcs * 0.01 * $c->pt_mil * ($c->govt == "H" ? 0.8 : 1.0);
        // FUTURE: could a country buy so much mil that it ends up not making money per turn?
        $unit_nw = ['m_tr'=>0.5, 'm_j' => 0.6, 'm_tu' => 0.6, 'm_ta' => 2.0];
        foreach($unit_nw as $unit => $unit_nw) {
            $military_unit_sell_price = round($unit_nw * $military_end_dpnw, 2);
            $military_unit_weight = round($max_loss * $military_unit_sell_price / (10 * $max_loss * (100 - $max_loss)), 4);
            // $c->expenses_mil only gets updated by the advisor, but should be fine because this code is called early on during turns
            // 45 is just a guess... not clear what to do here
            $military_unit_exp_per_turn = get_approx_expense_per_turn_for_mil_unit($unit, $unit_nw, 45, $c->govt, $c->networth, $c->pt_mil, $c->expenses_mil);
            $exp = round($military_unit_exp_per_turn * ($c->turns_stored + $server_new_turns_remaining), 2);
            $military_unit_adjustment = $exp - $military_unit_sell_price;

            $stockpiling_weights[$unit] = $military_unit_weight;
            $stockpiling_adjustments[$unit] = $military_unit_adjustment;
            log_country_message($c->cnum, "The stockpiling price weight for $unit is $military_unit_weight, expenses are $exp, value is $military_unit_sell_price, and the adjustment is $military_unit_adjustment");
        }
        
        // FUTURE: techer should use fewer turns because it destocks earlier? although it can keep teching... 
    }
    
    // FUTURE: $allow_oil - hard to assign a base value outside of express

    if(empty($stockpiling_weights)) {
        log_error_message(999, $c->cnum, "get_stockpiling_weights(): must allow at least one type of good");        
    }

    return true;
}


function get_approx_expense_per_turn_for_mil_unit ($unit, $unit_nw, $food_price, $govt, $networth, $pt_mil, $current_expenses_mil) {
    $unit_base_exp_money = ['m_tr' => 0.11,'m_j' => 0.14,'m_tu' => 0.18,'m_ta' => 0.57];
    $unit_base_exp_food = ['m_tr' => 0.001,'m_j' => 0.001,'m_tu' => 0.001,'m_ta' => 0.003];

    // assumes no military bases
    return 0.01 * $pt_mil * ($govt == "T" ? 0.9 : 1) * (1 + $networth / 200000000) * $unit_base_exp_money[$unit] // money
    + $food_price * $unit_base_exp_food[$unit] // food
    + $current_expenses_mil * (-1 + (1 + (($unit_nw + $networth) / 200000000)) / (1 + $networth / 200000000)); // expenses increase because NW goes up
}


function spend_extra_money_on_stockpiling(&$c, $cpref, $money_to_reserve, $stockpiling_weights, $stockpiling_adjustments) {
    if(empty($stockpiling_weights) or empty($stockpiling_adjustments)) {
        log_error_message(999, $c->cnum, "spend_extra_money_on_stockpiling(): passed in empty array");
        return 0;
    }
    // this is useless, but spend_money_on_markets expects it
    $unit_points = array_combine(array_keys($stockpiling_weights), array_fill(0, count($stockpiling_weights), 1));
    log_country_message($c->cnum, "Attempting to spend money on stockpiling purchases...");
    $spent = spend_money_on_markets($c, $cpref, 999999999999, $c->money - $money_to_reserve, $stockpiling_weights, $unit_points, "stock", 10 * $cpref->max_stockpiling_loss_percent, false, $stockpiling_adjustments);
    log_country_message($c->cnum, "Spent $spent and finished stockpiling purchases");
    return $spent;
}