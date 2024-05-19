<?php

namespace EENPC;




function sell_initial_troops_on_turn_0(&$c) {
    if($c->turns_played == 0 && $c->m_tr > 0)
        PrivateMarket::sell_single_good($c, 'm_tr', $c->m_tr);
}


function get_max_demo_bushel_recycle_price($rules) {
    return round($rules->base_pm_food_sell_price / 0.81667); // FUTURE: get max mil tech from game
}


function predict_destock_bushel_sell_price($c, $rules) {
    $max_demo_recycle_price = get_max_demo_bushel_recycle_price($rules);
    if($c->govt == "H" or $c->govt == "D")
        $sell_price = (2 - $c->tax()) * ($max_demo_recycle_price - 1);
    else
        $sell_price = $max_demo_recycle_price - 3; // if we're stocking, we'll probably have decent mil tech to sell on private at the end

    log_country_message($c->cnum, "The predicted destocking bushel sell price (including commissions) is $sell_price");
    return $sell_price;
}

function emergency_sell_mil_on_pm (&$c, $money_needed) {
    $pm_info = PrivateMarket::getInfo();
    // prefer to sell jets, then tanks, then troops, than turrets
    $mil_sell_order = ['m_j', 'm_ta', 'm_tr', 'm_tu'];

    log_country_message($c->cnum, "Conducting emergency sell of military on PM to get $money_needed money");

    foreach($mil_sell_order as $mil_unit){
        $sell_price = $pm_info->sell_price->$mil_unit;
        $amount_to_sell = min($c->$mil_unit, ceil($money_needed / $sell_price));
        if($amount_to_sell > 0)
            PrivateMarket::sell_single_good($c, $mil_unit, $amount_to_sell);
        $money_needed -= $amount_to_sell * $sell_price;
        if($money_needed < 0)
            return true;
    }
    // couldn't sell enough mil for some reason
    return false;
}



function get_farmer_max_sell_price($c, $cpref, $rules, $server) {
    // this is a bit simple and can lead to a big jump, but 360 turns feels like enough time for prices to stablize
    // if demand for food truly is high then human players can react as needed
    if($c->turns_played <= 360)
        $max_sell_price = $cpref->farmer_max_early_sell_price;
    else
        $max_sell_price = $cpref->farmer_max_late_sell_price;  

    log_country_message($c->cnum, "Bushel maximum sell price calculated as $max_sell_price based on preference and turns played");
    return $max_sell_price;           
}


function get_market_history_all_military_units($cnum, $cpref){
    $market_history = [];

    $military_units = ['m_tr', 'm_j', 'm_tu', 'm_ta'];
    $search_result_fields = ['low_price', 'high_price', 'total_units_sold', 'total_sales', 'avg_price', 'no_results'];
    foreach($military_units as $unit){
        $market_history_for_unit = get_market_history($unit, $cpref->get_market_history_look_back_hours());
        foreach($search_result_fields as $field){
            $market_history[$unit][$field] = $market_history_for_unit->$field;
        }
        log_country_market_history_for_single_unit($cnum, $unit, $market_history[$unit]);
    }

    return $market_history;
}




// leave $tech_name as null to get all tech
function get_market_history_tech($cnum, $cpref, $tech_name = null){
    if(!$tech_name)
        $tech_list = ['t_mil','t_med','t_bus','t_res','t_agri','t_war','t_ms','t_weap','t_indy','t_spy','t_sdi'];
    else
        $tech_list = [$tech_name];

    return get_market_history_tech_internal($cnum, $cpref, $tech_list);
}



function get_market_history_tech_internal($cnum, $cpref, $tech_list){
    $market_history = [];

    $search_result_fields = ['low_price', 'high_price', 'total_units_sold', 'total_sales', 'avg_price', 'no_results'];
    foreach($tech_list as $unit){
        $market_history_for_unit = get_market_history($unit, $cpref->get_market_history_look_back_hours());
        foreach($search_result_fields as $field){
            $market_history[$unit][$field] = $market_history_for_unit->$field;
        }
        log_country_market_history_for_single_unit($cnum, $unit, $market_history[$unit]);
    }

    return $market_history;
}



function get_market_history_food($cnum, $cpref){
    $market_history = [];
    $search_result_fields = ['low_price', 'high_price', 'total_units_sold', 'total_sales', 'avg_price', 'no_results'];
    $market_history_for_unit = get_market_history('food', $cpref->get_market_history_look_back_hours());
    foreach($search_result_fields as $field){
         $market_history[$field] = $market_history_for_unit->$field;
    }
    log_country_market_history_for_single_unit($cnum, 'food', $market_history);

    return $market_history;
}



// $market_good_name accepts m_tr, food, m_bu, oil, m_oil, bus, t_bus as examples
function get_market_history($market_good_name, $look_back_hours) {
    $result = ee('market_search', [
        'good' => $market_good_name,
        'look_back_hours' => $look_back_hours
    ]);

    return $result;
}





// makes all elements of the array into whole numbers that add up to $target_sum
function normalize_array_for_selling($cnum, &$score_array, $target_sum, $preferred_key_for_remainder = null) {
    if($preferred_key_for_remainder and !isset($score_array[$preferred_key_for_remainder])) {
        log_error_message(999, $cnum, 'normalize_array_for_selling(): $preferred_key_for_remainder is not a key of the array');
        $preferred_key_for_remainder = null;
    }

    $current_sum = array_sum($score_array);
    foreach($score_array as $key => $weight)
        $score_array[$key] = ($current_sum == 0 ? 1 : floor($weight * $target_sum / $current_sum));

    $leftovers = $target_sum - array_sum($score_array);
    if($preferred_key_for_remainder)
        $score_array[$preferred_key_for_remainder] += $leftovers;
    else
        $score_array[key($score_array)] += $leftovers; // take some arbitrary key

    return true;
}


function create_production_scores_based_on_preferences($cnum, $cpref, $unit_price_history, $price_reduction, $production_factor_per_unit,
$high_price_score_array, $no_market_history_score_array, $current_price_score_array, $default_current_prices, $default_historical_prices) {
    // $unit_price_history[$unit] elements can be null if there were no recent sales

    //global $market; // this was in the old code near PublicMarket::price... not sure why

    $price_array = [];
    $production_score = [];    

    if($cpref->production_algorithm == "HIGH_PRICE") {
        log_country_message($cnum, "Unit shares in order of price ascending: ".json_encode($high_price_score_array));
        foreach($production_factor_per_unit as $unit => $units_produced) {
            $unit_price = isset($unit_price_history[$unit]['high_price']) ? $unit_price_history[$unit]['high_price'] : 0;
            $initial_unit_score = round($units_produced * $unit_price);
            $price_array[$unit] = $initial_unit_score;
            log_country_message($cnum, "Initial unit score for $unit is $initial_unit_score with price $unit_price and production $units_produced");
        }

        if(max($price_array) == min($price_array) and max($price_array) == 0) { // no market data
            log_country_message($cnum, "Detected no market data so using default values");
            $price_array = $no_market_history_score_array;
        }

        arsort($price_array);
        reset($price_array);
        foreach(array_keys($price_array) as $unit) {
            $unit_score = array_pop($high_price_score_array);
            $production_score[$unit] = $unit_score;
            //log_country_message($cnum, "New score for $unit is $unit_score");
        }
    } // end HIGH_PRICE
    elseif($cpref->production_algorithm == "CURRENT_PRICE") {   
        log_country_message($cnum, "Unit shares in order of price ascending: ".json_encode($current_price_score_array));
        foreach($production_factor_per_unit as $unit => $units_produced) {
            $market_price = PublicMarket::price($unit);
            $unit_price = $market_price ? $market_price : $default_current_prices[$unit];
            $initial_unit_score = round($units_produced * $unit_price);
            $price_array[$unit] = $initial_unit_score;
            log_country_message($cnum, "Initial unit score for $unit is $initial_unit_score with price $unit_price and production $units_produced");
        }

        arsort($price_array);
        reset($price_array);        
        foreach(array_keys($price_array) as $unit) {
            $unit_score = array_pop($current_price_score_array);
            $production_score[$unit] = $unit_score;
            //log_country_message($cnum, "New score for $unit is $unit_score");
        }
    } // end CURRENT_PRICE
    elseif($cpref->production_algorithm == "SALES") {
        $number_of_units_with_no_data = 0;
        foreach($production_factor_per_unit as $unit => $units_produced) {
            $default_value = $default_historical_prices[$unit];
            $unit_value = max(0,isset($unit_price_history[$unit]['total_sales']) ?
                $unit_price_history[$unit]['total_sales'] - $price_reduction * $unit_price_history[$unit]['total_units_sold'] : $default_value);
            $unit_score = round($units_produced * $unit_value);
            $production_score[$unit] = $unit_score;
            log_country_message($cnum, "Unit score for $unit is $unit_score with value $unit_value, production $units_produced, and default $default_value");

            if($default_value == $unit_value) // false positives are possible, but extremely unlikely
                $number_of_units_with_no_data++;
        }

        if($number_of_units_with_no_data == count($production_factor_per_unit)) { // we will rarely get false positives with this approach, but whatever
            log_country_message($cnum, "Detected no market data so using default values");
            $production_score = $no_market_history_score_array;
        }
    } // end SALES
    elseif($cpref->production_algorithm == "AVG_PRICE") {   
        $number_of_units_with_no_data = 0;
        foreach($production_factor_per_unit as $unit => $units_produced) {
            $default_price = $default_historical_prices[$unit];
            $unit_price = max(0, isset($unit_price_history[$unit]['avg_price']) ?
                $unit_price_history[$unit]['avg_price'] - $price_reduction : $default_price - $price_reduction);
            $unit_score = round($units_produced * $unit_price);
            $production_score[$unit] = $unit_score;
            log_country_message($cnum, "Unit score for $unit is $unit_score with price $unit_price, production $units_produced, and default $default_price");

            if($default_price == $unit_price) // false positives are possible, but extremely unlikely
                $number_of_units_with_no_data++;
        }

        if($number_of_units_with_no_data == count($production_factor_per_unit)) { // we will rarely get false positives with this approach, but whatever
            log_country_message($cnum, "Detected no market data so using default values");
            $production_score = $no_market_history_score_array;
        }
    } // end AVG_PRICE
    else {
        if($cpref->production_algorithm <> "RANDOM")
            log_error_message(999, $cnum, "create_production_scores_based_on_preferences(): invalid value for production algorithm: $cpref->production_algorithm");

        foreach($production_factor_per_unit as $unit => $units_produced) {
            $rand = mt_rand(1, 1000);
            $production_score[$unit] = $rand;
            log_country_message($cnum, "Unit score for $unit is random number $rand (possible range was 1 to 1000)");
        }
    } // end RANDOM

    return $production_score;
}




function set_indy_from_production_algorithm(&$c, $military_unit_price_history, $cpref, $checkDPA = false)
{
    // produce 100% turrets if checked in the first 150 turns because there's very little demand for anything else
    // FUTURE: 150 is a made up number that could be changed to something better
    if ($c->turns_played < 150) { 
        log_country_message($c->cnum, "Setting indy production to 100% turrets because the country hasn't played 150 turns yet");
        $new_indy_production['pro_tu'] = 100;
    }
    else { // not the first 150 turns
        
        log_country_message($c->cnum, "Setting indy production using algorithm $cpref->production_algorithm", 'green');  
        
        // for now, 400 indy's worth of production with 1% min and 5% max
        // 200 was too low - maybe because of OOF and OOM events?
        $spy = max(1, min(5, round(400 / ($c->b_indy + 1))));

        // create_production_scores_based_on_preferences requires lots of array parameters...

        // base production per unit
        $production_factor_per_unit = [
            'm_tr'  => 1.86,
            'm_j'   => 1.86,
            'm_tu'  => 1.86,
            'm_ta'  => 0.4
        ];
        // scores used for HIGH_PRICE in order of price ascending
        $high_price_score_array = [0, 1, 3, 9];
        // default values used for HIGH_PRICE, AVG_PRICE, and SALES when there's no market history for ANY unit (beginning of the set)
        $no_market_history_score_array  = [
            'm_tr'  => 2,
            'm_j'   => 13,
            'm_tu'  => 82,
            'm_ta'  => 3
        ];
        // scores used for CURRENT_PRICE in order of price ascending
        $current_price_score_array = [1, 1, 1, 27];
        // default values used for CURRENT_PRICE when market is empty
        $default_current_prices = [
            'm_tr'  => 9997,
            'm_j'   => 9998,
            'm_tu'  => 9999,
            'm_ta'  => 9996
        ];
        // default values used for AVG_PRICE and SALES when there's no market history for a unit
        $default_historical_prices = [
            'm_tr'  => 10,
            'm_j'   => 60,
            'm_tu'  => 200,
            'm_ta'  => 40
        ];

        $production_score = create_production_scores_based_on_preferences($c->cnum, $cpref, $military_unit_price_history, 0, $production_factor_per_unit,
        $high_price_score_array, $no_market_history_score_array, $current_price_score_array, $default_current_prices, $default_historical_prices);
       
        // remove jets if we're checking DPA and it's too low
        if ($checkDPA) {
            $target = $c->defPerAcreTarget($cpref);
            if ($c->defPerAcre() < $target) {
                //below def target, don't make jets
                unset($production_score['m_j']);
                log_country_message($c->cnum, "Setting jet production to 0% because DPAT isn't met");
            }
        }

        // make everything an integer and make it all sum to 100%
        log_country_message($c->cnum, "Normalizing indy production scores so they sum to 100");
        normalize_array_for_selling($c->cnum, $production_score, 100 - $spy, 'm_tu');

        // basically just remapping m_* keys to pro_* keys
        $new_indy_production = [
            'pro_spy' => $spy,
            'pro_tr'  => $production_score['m_tr'],
            'pro_j'   => $production_score['m_j'],
            'pro_tu'  => $production_score['m_tu'],
            'pro_ta'  => $production_score['m_ta']
        ];

        $protext = null;
        foreach ($new_indy_production as $k => $s) {
            $protext .= $s.' '.$k.' ';
        }
        //log_country_message($c->cnum, "--- Indy Scoring: ".$protext);
    }

    $c->setIndy($new_indy_production);
}//end set_indy_from_production_algorithm()



function get_tpt_split_from_production_algorithm(&$c, $tech_price_history, $cpref, $server)
{
    // produce bus/res in first 150 turns because there's very little demand for anything else and we're probably poor
    // FUTURE: 150 is a made up number that could be changed to something better
    if ($c->turns_played < 150) { 
        $production_score = [
            't_mil' => 0,
            't_med' => 0,
            't_bus' => 40,
            't_res' => 40,
            't_agri' => 15,
            't_war' => 0,
            't_ms' => 0,
            't_weap' => 0,
            't_indy' => 5,
            't_spy' => 0,
            't_sdi' => 0
        ];
        log_country_message($c->cnum, "Setting tech production to mostly bus/res because we haven't played 150 turns yet");
    }
    else { // not the first 150 turns        
        log_country_message($c->cnum, "Setting tech production using algorithm $cpref->production_algorithm", 'green');  
        
        // create_production_scores_based_on_preferences requires lots of array parameters...

        // base production per unit (not useful for tech)
        $production_factor_per_unit = [
            't_mil' => 1,
            't_med' => 1,
            't_bus' => 1,
            't_res' => 1,
            't_agri' => 1,
            't_war' => 1,
            't_ms' => 1,
            't_weap' => 1,
            't_indy' => 1,
            't_spy' => 1,
            't_sdi' => 1
        ];
        // scores used for HIGH_PRICE in order of price ascending
        $high_price_score_array = [2, 2, 2, 2, 4, 4, 4, 15, 15, 25, 25];
        // default values used for HIGH_PRICE, AVG_PRICE, and SALES when there's no market history for ANY unit (beginning of the set)
        $no_market_history_score_array  = [
            't_mil' => 0,
            't_med' => 0,
            't_bus' => 9000,
            't_res' => 9000,
            't_agri' => 4000,
            't_war' => 0,
            't_ms' => 0,
            't_weap' => 0,
            't_indy' => 4000,
            't_spy' => 0,
            't_sdi' => 0
        ];
        // scores used for CURRENT_PRICE in order of price ascending
        $current_price_score_array = [0, 0, 0, 0, 0, 0, 10, 10, 10, 35, 35];
        // default values used for CURRENT_PRICE when market is empty
        $default_current_prices = [
            't_mil' => 4000,
            't_med' => 1000,
            't_bus' => 7000,
            't_res' => 7000,
            't_agri' => 6000,
            't_war' => 1500,
            't_ms' => 3000,
            't_weap' => 3000,
            't_indy' => 6000,
            't_spy' => 1000,
            't_sdi' => 8000
        ];
        // default values used for AVG_PRICE and SALES when there's no market history for a single unit
        $default_historical_prices = [
            't_mil' => 1500,
            't_med' => 1000,
            't_bus' => 2000,
            't_res' => 2000,
            't_agri' => 1500,
            't_war' => 1000,
            't_ms' => 1000,
            't_weap' => 1000,
            't_indy' => 1500,
            't_spy' => 1000,
            't_sdi' => 1000
        ];

        $production_score = create_production_scores_based_on_preferences($c->cnum, $cpref, $tech_price_history, $cpref->base_inherent_value_for_tech, $production_factor_per_unit,
        $high_price_score_array, $no_market_history_score_array, $current_price_score_array, $default_current_prices, $default_historical_prices);
    }

    if($server->is_cooperation_server) {
        $production_score['t_war'] = 0;
        $production_score['t_sdi'] = 0;
    }

    log_country_message($c->cnum, "Normalizing production scores to sum to 10000 for easier reading");
    normalize_array_for_selling($c->cnum, $production_score, 10000, 't_bus');
    log_country_data($c->cnum, $production_score, "Tech production scores: ");    
    // no need for proper normalization here. it will have to keep getting normalized because tpt can change as the country plays turns
    return $production_score;
}//end get_tpt_split_from_production_algorithm()



function recall_goods()
{
    return ee('market_recall', ['type' => 'GOODS']);
}//end recall_goods()


function recall_tech()
{
    return ee('market_recall', ['type' => 'TECH']);
}//end recall_tech()
