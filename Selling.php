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
