<?php

namespace EENPC;

function get_max_demo_bushel_recycle_price($rules) {
    return round($rules->base_pm_food_sell_price / 0.81667); // FUTURE: get max mil tech from game
}


function predict_destock_bushel_sell_price($c, $rules) {
    $max_demo_recycle_price = get_max_demo_bushel_recycle_price($rules);
    if($c->govt == "H" or $c->govt == "D")
        $sell_price = (2 - $c->tax()) * ($max_demo_recycle_price - 2);
    else
        $sell_price = $max_demo_recycle_price - 3; // if we're stocking, we'll probably have decent mil tech to sell on private at the end

    log_country_message($c->cnum, "The predicted destocking bushel sell price (including commissions) is $sell_price");
    return $sell_price;
}

function emergency_sell_mil_on_pm (&$c, $money_needed) {

    // TODO: implement emergency_sell_mil_on_pm()



    return false;
}



function get_market_history_all_military_units($cnum, $cpref){
    $market_history = [];

    $military_units = ['m_tr', 'm_j', 'm_tu', 'm_ta'];
    $search_result_fields = ['low_price', 'high_price', 'total_units_sold', 'total_sales', 'avg_price', 'no_results'];
    foreach($military_units as $unit){
        $market_history_for_unit = get_market_history($unit, $cpref->market_search_look_back_hours);
        foreach($search_result_fields as $field){
            $market_history[$unit][$field] = $market_history_for_unit->$field;
        }
        log_country_data($cnum, $market_history[$unit], "Market history for $unit:");
    }

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


// TODO: add get_market_history_all_tech
// TODO: add get_tech_from_production_algorithm

function set_indy_from_production_algorithm(&$c, $military_unit_price_history, $cpref, $checkDPA = false)
{
    // produce 100% turrets if checked in the first 150 turns because there's very little demand for anything else
    // FUTURE: 150 is a made up number that could be changed to something better
    if ($c->turns_played < 150) { 
        log_country_message($c->cnum, "Setting indy production to 100% turrets because the country hasn't played 150 turns yet");
        $new_indy_production['pro_tu'] = 100;
    }
    else { // not the first 150 turns
        // for now, 400 indy's worth of production with 1% min and 5% max
        // 200 was too low - maybe because of OOF and OOM events?
        $spy = max(1, min(5, round(400 / ($c->b_indy + 1))));

        $production_score = [];
        $production_factor_per_unit = [
            'm_tr'  => 1.86,
            'm_j'   => 1.86,
            'm_tu'  => 1.86,
            'm_ta'  => 0.4
        ];

        // for CURRENT_PRICE
        $default_current_prices = [
            'm_tr'  => 9997,
            'm_j'   => 9998,
            'm_tu'  => 9999,
            'm_ta'  => 9996
        ];

        // for AVG_PRICE and SALES
        $default_historical_prices = [
            'm_tr'  => 10,
            'm_j'   => 60,
            'm_tu'  => 200,
            'm_ta'  => 40
        ];
       
        //global $market; // why?

        log_country_message($c->cnum, "Setting indy production using algorithm $cpref->production_algorithm", 'green');       

        //out_data($military_unit_price_history);

        // $military_unit_price_history[$unit] elements can be null if there were no recent sales


        $price_array = [];
        $production_score = [];

        if($cpref->production_algorithm == "HIGH_PRICE") {   
            $score_array = [0, 1, 3, 9]; // most expensive unit gets weight 9, second most gets weight 3, and so on...
            log_country_message($c->cnum, "In order of price descending, unit shares are 9:3:1:0");
            foreach($production_factor_per_unit as $unit => $units_produced) {
                $unit_price = isset($military_unit_price_history[$unit]['high_price']) ? $military_unit_price_history[$unit]['high_price'] : 0;
                $initial_unit_score = round($units_produced * $unit_price);
                $price_array[$unit] = $initial_unit_score;
                log_country_message($c->cnum, "Initial unit score for $unit is $initial_unit_score with price $unit_price and production $units_produced");
            }

            if(max($price_array) == min($price_array) and max($price_array) == 0) { // no market data
                log_country_message($c->cnum, "Detected no market data so using default values that favor turret production");
                $price_array['m_tr'] = 1;
                $price_array['m_j'] = 3;           
                $price_array['m_tu'] = 4;
                $price_array['m_ta'] = 2;
            }

            arsort($price_array);
            reset($price_array);
            foreach(array_keys($price_array) as $unit) {
                $unit_score = array_pop($score_array);
                $production_score[$unit] = $unit_score;
                log_country_message($c->cnum, "New score for $unit is $unit_score");
            }
        } // end HIGH_PRICE
        elseif($cpref->production_algorithm == "CURRENT_PRICE") {   
            $score_array = [1, 1, 1, 27]; // most expensive unit gets weight 27, second most gets weight 1, and so on...
            log_country_message($c->cnum, "In order of price descending, unit shares are 27:1:1:1");
            foreach($production_factor_per_unit as $unit => $units_produced) {
                $market_price = PublicMarket::price($unit);
                $unit_price = $market_price ? $market_price : $default_current_prices[$unit];
                $initial_unit_score = round($units_produced * $unit_price);
                $price_array[$unit] = $initial_unit_score;
                log_country_message($c->cnum, "Initial unit score for $unit is $initial_unit_score with price $unit_price and production $units_produced");
            }

            arsort($price_array);
            reset($price_array);
            
            foreach(array_keys($price_array) as $unit) {
                $unit_score = array_pop($score_array);
                $production_score[$unit] = $unit_score;
                log_country_message($c->cnum, "New score for $unit is $unit_score");
            }
        } // end CURRENT_PRICE
        elseif($cpref->production_algorithm == "SALES") {
            $number_of_units_with_no_data = 0;
            foreach($production_factor_per_unit as $unit => $units_produced) {
                $default_value = $default_historical_prices[$unit];
                $unit_value = isset($military_unit_price_history[$unit]['total_sales']) ? $military_unit_price_history[$unit]['total_sales'] : $default_value;
                $unit_score = round($units_produced * $unit_value);
                $production_score[$unit] = $unit_score;
                log_country_message($c->cnum, "Unit score for $unit is $unit_score with value $unit_value, production $units_produced, and default $default_value");

                if($default_value == $unit_value) // false positives are possible, but extremely unlikely
                    $number_of_units_with_no_data++;
            }

            if($number_of_units_with_no_data == 4) { // we will rarely get false positives with this approach, but whatever
                log_country_message($c->cnum, "Detected no market data so using default values that favor turret production");
                $production_score['m_tr'] = 2;
                $production_score['m_j'] = 13;           
                $production_score['m_tu'] = 83;
                $production_score['m_ta'] = 2;
            }
        } // end SALES
        elseif($cpref->production_algorithm == "AVG_PRICE") {   
            $number_of_units_with_no_data = 0;
            foreach($production_factor_per_unit as $unit => $units_produced) {
                $default_price = $default_historical_prices[$unit];
                $unit_price = isset($military_unit_price_history[$unit]['total_sales']) ? $military_unit_price_history[$unit]['avg_price'] : $default_price;
                $unit_score = round($units_produced * $unit_price);
                $production_score[$unit] = $unit_score;
                log_country_message($c->cnum, "Unit score for $unit is $unit_score with price $unit_price, production $units_produced, and default $default_price");

                if($default_price == $unit_price) // false positives are possible, but extremely unlikely
                    $number_of_units_with_no_data++;
            }

            if($number_of_units_with_no_data == 4) { // we will rarely get false positives with this approach, but whatever
                log_country_message($c->cnum, "Detected no market data so using default values that favor turret production");
                $production_score['m_tr'] = 2;
                $production_score['m_j'] = 13;           
                $production_score['m_tu'] = 83;
                $production_score['m_ta'] = 2;
            }
        } // end AVG_PRICE
        else {
            if($cpref->production_algorithm <> "RANDOM")
                log_error_message(999, $c->cnum, "set_indy_from_production_algorithm(): invalid value for production algorithm: $cpref->production_algorithm");

            foreach($production_factor_per_unit as $unit => $units_produced) {
                $rand = mt_rand(1, 1000);
                $production_score[$unit] = $rand;
                log_country_message($c->cnum, "Unit score for $unit is random number $rand (possible range was 1 to 1000)");
            }
        } // end RANDOM
       
        // remove jets if we're checking DPA and it's too low
        if ($checkDPA) {
            $target = $c->defPerAcreTarget();
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