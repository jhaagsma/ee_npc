<?php



// buy military or tech (future should include stocking bushels?)
function spend_extra_money (&$c, $cpref, $money_to_reserve, $delay_military_purchases, $optimal_tech_buying_array = []) {

// get $goal_nwpa, $goal_dpa

/*
-- possible starting point?
    Get to 10% of target military
    Tech that pays itself off within 20% of remaining turns in the reset
    Get to 40% of target military
    Tech that pays itself off within 40% of remaining turns in the reset
    Get to 70% of target military
    Tech that pays itself off within 60% of remaining turns in the reset
    Get to 100% of target military
    Tech that pays itself off within 80% of remaining turns in the reset
    Get to 100% of target nwpa
    Tech that pays itself off within 100% of remaining turns in the reset
*/

// if I call the existing buy military functions, fix them so they don't go over... although I should just make new ones maybe?


}


function get_optimal_tech_buying_array($tech_type_to_ipa, $expected_avg_land, $max_tech_price, $max_possible_spend, $base_tech_value) {
    // FUTURE: support weapons tech


    // get min prices from public market

    // loop through $tech_type_to_ipa and call get_optimal_tech_buying_info
    
    // turn all results into single array with right structure

    return;    
};





// TODO: build the other end of this
// TODO: add validation in communication.php
function get_optimal_tech_buying_info ($tech_type, $expected_avg_land, $min_tech_price, $max_tech_price, $max_possible_spend, $base_income_per_acre, $base_tech_value) {
    // return a fake array here to use for testing other functions
    return $result = ee('get_optimal_tech_buying_info', [
        'tech' => $tech_type,
        'avg_land' => $expected_avg_land,
        'min_price' => $min_tech_price,
        'max_price' => $max_tech_price,
        'budget' => $max_possible_spend,
        'ipa' => $base_income_per_acre,
        'tech_value' => $base_tech_value
    ]);
};

