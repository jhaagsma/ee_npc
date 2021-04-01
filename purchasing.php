<?php

namespace EENPC;

// TODO: move other purchasing functions here


// buy military or tech (future should include stocking bushels?)
function spend_extra_money (&$c, $cpref, $money_to_reserve, $delay_military_purchases, $optimal_tech_buying_array = []) {

// get $goal_nwpa, $goal_dpa



// call buy_single_tech_up_to_limits
// what to call to buy military? old code calls defend_self() which has problems...

// need to buy off public or private with spend limit, NW/defense points limit - be smart about private to avoid huge purchase loop
// delay feels like a separate function

/*
["DPA" or "NWPA" or "INCOME_TECHS"]

// buy_single_tech_up_to_limits

/*
-- possible starting point (country needs to pass in the priority list)
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



}


function get_optimal_tech_buying_array($cnum, $tech_type_to_ipa, $expected_avg_land, $max_tech_price, $max_possible_spend, $base_tech_value) {
    // FUTURE: support weapons tech
    log_country_message($cnum, "Creating optimal tech buying array");

    $optimal_tech_buying_array = [];
    for($key_num = 10; $key_num <= 100; $key_num+=10){
        $optimal_tech_buying_array["turns$key_num"] = [];
    }

    foreach($tech_type_to_ipa as $tech_type => $ipa) {
        if($tech_type <> 't_agri' and $tech_type <> 't_indy' and $tech_type <> 't_bus' and $tech_type <> 't_res' and $tech_type <> 't_mil') {
            log_error_message(117, $cnum, "Invalid tech type value is: $tech_type");
            continue;
        }

        $current_tech_price = PublicMarket::price($tech_type);
        if(!$current_tech_price) {
            log_country_message($cnum, "No $tech_type tech available on public market so skipping optimal tech calculations");
            continue;
        }

        // dump results into the array, further process the array later
        log_country_message($cnum, "Querying server for optimal tech buying results...");
        $res = get_optimal_tech_buying_info ($tech_type, $expected_avg_land, $current_tech_price, $max_tech_price, $max_possible_spend, $ipa, $base_tech_value);
        // no need for additional error handling because comm should handle that
        if(is_array($res)) {
            foreach($res as $turn_bucket => $pq_results) {
                foreach($pq_results as $pq_result) {
                    $optimal_tech_buying_array[$turn_bucket][] = $pq_result;
                }
            }
        }
    }

    // need to further process $optimal_tech_buying_array
    // if a turn bucket is empty remove it. otherwise sort by price (undefined order in case of ties is fine)
    /*   
    // goal is single array with right structure, with $p1 <= $p2 <= ... $p50 and so on
    $result = [
        'turns10' => [1=> ['t'=>'t_agri', 'p' => $p1, 'q' => $q1], 2=> ['t'=>'bus', 'p' => $p2, 'q' => $q2], ... 50=> ['t'=>'bus', 'p' => $p50, 'q' => $q50]],
        'turns20' => [1=> ['t'=>'t_agri', 'p' => $p51, 'q' => $q51], 2=> ['t'=>'bus', 'p' => $p52, 'q' => $q52], ... 40=> ['t'=>'bus', 'p' => $p90, 'q' => $q90]],
        ...
        'turns100' => 1=> ['t'=>'t_agri', 'p' => $p356, 'q' => $q357], ...
    ];
    */

    log_country_message($cnum, "Final processing for optimal tech buying array");
    foreach($optimal_tech_buying_array as $turn_bucket => $pq_results) {
        if(empty($pq_results))
            unset($optimal_tech_buying_array[$turn_bucket]);
        else
            usort($optimal_tech_buying_array[$turn_bucket],
            function ($a, $b) {
                return $a['p'] <=> $b['p']; // spaceship!
            }); // TODO: is this safe?
    }

    // TODO: some kind of validation or error checking? the array could be empty if prices are too high though...
    log_country_message($cnum, "Array processing complete for optimal tech buying array");
    return $optimal_tech_buying_array;  
    

};






// TODO: build the other end of this
// TODO: add validation in communication.php
function get_optimal_tech_buying_info ($tech_type, $expected_avg_land, $min_tech_price, $max_tech_price, $max_possible_spend, $base_income_per_acre, $base_tech_value) {
    // return a fake array here to use for testing other functions

    $debug_result = [
        'turns10' => [1=> ['t'=>$tech_type, 'p' => 1500, 'q' => 10000], 2=> ['t'=>$tech_type, 'p' => 2500, 'q' => 9000], 3=> ['t'=>$tech_type, 'p' => 3000, 'q' => 8888]],
        'turns20' => [1=> ['t'=>$tech_type, 'p' => 1500, 'q' => 8000], 2=> ['t'=>$tech_type, 'p' => 2500, 'q' => 7777]],
        'turns100' => [1=> ['t'=>$tech_type, 'p' => 1500, 'q' => 6000]]
    ];

    return $debug_result;


    /*
    // result is a multidim array ($p is price, q is quantity)

    $result = [
        'turns10' => [1=> ['t'=>'t_agri', 'p' => $p1, 'q' => $q1], 2=> ['t'=>'t_agri', 'p' => $p2, 'q' => $q2], ... 10=> ['t'=>'t_agri', 'p' => $p10, 'q' => $q10]],
        'turns20' => [1=> ['t'=>'t_agri', 'p' => $p11, 'q' => $q11], 2=> ['t'=>'t_agri', 'p' => $p19, 'q' => $q19], ... 40=> ['t'=>'t_agri', 'p' => $p20, 'q' => $q20]],
        ...
        'turns100' => [1=> ['t'=>'t_agri', 'p' => $57, 'q' => $58]]
    ];


    $result = [
        'turns10' => [$p1 => $q1_1, $p2 => $q2_1, ... $p10 => $q10_1],
        'turns20' => [$p1 => $q1_2, $p2 => $q2_2, ... $p10 => $q10_2],
        ...
        'turns100' => [$p1 => $q1_10, $p2 => $q2_10, ... $p10 => $q10_10],
    ];
    */

    return ee('get_optimal_tech_buying_info', [
        'tech' => $tech_type,
        'avg_land' => $expected_avg_land,
        'min_price' => $min_tech_price,
        'max_price' => $max_tech_price,
        'budget' => $max_possible_spend,
        'ipa' => $base_income_per_acre,
        'tech_value' => $base_tech_value
    ]);
};


// use "t_agri" for tech type, as example
function buy_single_tech_up_to_limits(&$c, $tech_type, $max_price, $country_quantity_limit, &$max_spend) {
    $amount_spent = PublicMarket::buy_tech($c, $tech_type, $max_spend, $max_price, $country_quantity_limit);
    $max_spend -= $amount_spent;
    return;
}



