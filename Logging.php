<?php

namespace EENPC;

// TODO: add communication functions
// TODO: add ee_npc code to get and validate config parameters


function generate_compact_country_status($c, $strat, $number_of_errors, $is_destocking = false, $begin) {
    // TODO: have delta functionality: start, deltas, end

    // turns and resources
    $errs = str_pad(($number_of_errors ?? 'N/A'), 8, ' ', STR_PAD_LEFT);
    $t_st = str_pad("$c->turns($c->turns_stored)", 8, ' ', STR_PAD_LEFT);
    $t_pl = str_pad($c->turns_played, 8, ' ', STR_PAD_LEFT);
    $netw = str_pad(engnot($c->networth), 8, ' ', STR_PAD_LEFT);
    $land = str_pad(engnot($c->land), 8, ' ', STR_PAD_LEFT);
    $cash = str_pad(engnot($c->money), 8, ' ', STR_PAD_LEFT);
    $food = str_pad(engnot($c->food), 8, ' ', STR_PAD_LEFT);
    $tech = str_pad(engnot(totaltech($c)), 8, ' ', STR_PAD_LEFT);
    
    // buildings
    if($strat == 'F')
        $prod_buildings = $c->b_farm;
    elseif($strat == 'C')
        $prod_buildings = $c->b_ent + $c->b_res;
    elseif($strat == 'T')
        $prod_buildings = $c->b_lab;
    else // include indy and rainbow for now
        $prod_buildings = 0;
    $non_prod_buildings = $c->land - $c->empty - $c->b_cs - $c->b_indy - $prod_buildings;
    $pbds = str_pad($prod_buildings, 8, ' ', STR_PAD_LEFT);   
    $ibds = str_pad($c->b_indy, 8, ' ', STR_PAD_LEFT);   
    $nbds = str_pad($non_prod_buildings, 8, ' ', STR_PAD_LEFT);   
    $cs = str_pad($c->b_cs, 8, ' ', STR_PAD_LEFT);   
    $emty = str_pad($c->empty, 8, ' ', STR_PAD_LEFT);   

    // military
    $spy  = str_pad(engnot($c->m_spy), 8, ' ', STR_PAD_LEFT);
    $tr  = str_pad(engnot($c->m_tr), 8, ' ', STR_PAD_LEFT);
    $jets  = str_pad(engnot($c->m_j), 8, ' ', STR_PAD_LEFT);
    $tu  = str_pad(engnot($c->m_tu), 8, ' ', STR_PAD_LEFT);
    $ta  = str_pad(engnot($c->m_ta), 8, ' ', STR_PAD_LEFT);
    $read  = str_pad($c->readiness, 8, ' ', STR_PAD_LEFT);
    $ps = str_pad('5 (5)', 8, ' ', STR_PAD_LEFT); // FUTURE: when attacking is available
    $oil = str_pad(engnot($c->oil), 8, ' ', STR_PAD_LEFT);   
    
    // tech percentages
    $pmil = str_pad($c->pt_mil.'%', 8, ' ', STR_PAD_LEFT);
    $pbus = str_pad($c->pt_bus.'%', 8, ' ', STR_PAD_LEFT);
    $pres = str_pad($c->pt_res.'%', 8, ' ', STR_PAD_LEFT);
    $pwep = str_pad($c->pt_weap.'%', 8, ' ', STR_PAD_LEFT);
    if($strat == 'I')
        $prod_tech = $c->pt_indy;
    elseif($strat == 'F')
        $prod_tech = $c->pt_agri;
    else
        $prod_tech = 'N/A';
    $ppro = str_pad($prod_tech.'%', 8, ' ', STR_PAD_LEFT);

    // production
    $dstk = str_pad(log_translate_boolean_to_YN($is_destocking), 8, ' ', STR_PAD_LEFT);
    $tax = str_pad($c->taxes, 8, ' ', STR_PAD_LEFT);
    $exp = str_pad($c->expenses, 8, ' ', STR_PAD_LEFT);
    $netf = str_pad($c->foodnet, 8, ' ', STR_PAD_LEFT);
    $neto = str_pad($c->oilpro, 8, ' ', STR_PAD_LEFT);
    $tpt  = str_pad($c->tpt, 8, ' ', STR_PAD_LEFT);
    $neti = str_pad(round($c->b_indy * 1.86 * 0.01 * $c->pt_indy * ($c->govt == 'C' ? 1.35 : 1.0)), 8, ' ', STR_PAD_LEFT); // FUTURE: from game code
    $bpt  = str_pad($c->bpt, 8, ' ', STR_PAD_LEFT);

    // value of goods on market
    $om_v = str_pad(engnot(get_total_value_of_on_market_goods($c)), 8, ' ', STR_PAD_LEFT); // FUTURE: won't work with stocking
    
    $on_market_military = 0;
    $military_list = ['m_tr','m_j','m_tu','m_ta'];
    foreach($military_list as $m_unit)
        $on_market_military += $c->onMarket($m_unit);
    $om_u = str_pad(engnot($on_market_military), 8, ' ', STR_PAD_LEFT);

    $on_market_bushels = $c->onMarket('food');
    $om_b = str_pad(engnot($on_market_bushels), 8, ' ', STR_PAD_LEFT);   

    $on_market_tech = 0;
    $tech_list = ['t_mil','t_med','t_bus','t_res','t_agri','t_war','t_ms','t_weap','t_indy','t_spy','t_sdi'];
    foreach($tech_list as $tech_type)
        $on_market_tech += $c->onMarket($tech_type);
    $om_t = str_pad(engnot($on_market_tech), 8, ' ', STR_PAD_LEFT);

    // formatting stuff
    $spcs = str_repeat(' ', 8);
    $s = "\n|  ";
    $e = "  |";

    // TODO: add timestamp? will logging functions automatically do this?
    $header = ($begin ? 'Start' : 'End').' for ['.log_translate_simple_strat_name($strat)."] $c->cname (#$c->cnum) (".$c->govt.($c->gdi ? "G" : "").")";
    $str = "\n".str_pad($header, 78, '-', STR_PAD_BOTH);
    $str .= $s.'New errors:    '.$errs.'   Spies:       '.$spy .'   Destocking:   '.$dstk.$e;
    $str .= $s.'Turns:         '.$t_st.'   Troops:      '.$tr  .'   Tax Revenues: '.$tax .$e;
    $str .= $s.'Turns Played:  '.$t_pl.'   Jets:        '.$jets.'   Expenses:     '.$exp .$e;
    $str .= $s.'Networth:      '.$netw.'   Turrets:     '.$tu  .'   Net Food:     '.$netf.$e;
    $str .= $s.'Land:          '.$land.'   Tanks:       '.$ta  .'   Net Oil:      '.$neto.$e;
    $str .= $s.'Money:         '.$cash.'   Readiness:   '.$read.'   TPT:          '.$tpt .$e;
    $str .= $s.'Food:          '.$food.'   PS Brigades: '.$ps  .'   Indy prod:    '.$neti.$e;
    $str .= $s.'Tech Points:   '.$tech.'   Oil:         '.$oil .'   BPT:          '.$bpt .$e;
	$str .= $s.'               '.$spcs.'                '.$spcs.'                 '.$spcs.$e;
    $str .= $s.'Prod Blds:     '.$pbds.'   Mil tech:    '.$pmil.'   OM Value:     '.$om_v.$e; 
    $str .= $s.'Indy Blds:     '.$ibds.'   Bus tech:    '.$pbus.'   OM Units:     '.$om_u.$e;
    $str .= $s.'Non-prod Blds: '.$nbds.'   Res tech:    '.$pres.'   OM Bushels:   '.$om_b.$e;
    $str .= $s.'CS:            '.$cs  .'   Wep tech:    '.$pwep.'   OM Tech:      '.$om_t.$e;       
    $str .= $s.'Unused:        '.$emty.'   Prd tech:    '.$ppro.'                 '.$spcs.$e;
    $str .= "\n".str_repeat('-', 78);

    // TODO: add built percentage? $c->built()

    return $str;
}




function log_compact_country_status(){
    global $log_to_screen, $log_to_local, $log_to_server, $local_file_path;
    // TODO: implement

        //test log_to_targets with line breaks
    return false;
};



function local_directory_exists($local_file_path) {
    // TODO: implement
    return false;
}

function format_local_file_path($local_file_path) {
    // TODO: implement
    // make work on windows and linux ideally?
    return $local_file_path;
}



function initialize_country_logging_file($server, $cnum) {
    global $log_to_local, $log_to_server, $local_file_path;
    $full_local_file_path_and_name = ($log_to_local ? get_full_country_file_path_and_name($local_file_path, $cnum) : null);
    return initialize_for_targets($log_to_local, $log_to_server, $full_local_file_path_and_name);
}


function initialize_error_logging_file($server) {
    global $log_to_local, $log_to_server, $local_file_path;
    $full_local_file_path_and_name = ($log_to_local ? get_full_error_file_path_and_name($local_file_path) : null);
    return initialize_for_targets($log_to_local, $log_to_server, $full_local_file_path_and_name);
}

function initialize_main_logging_file($server) {
    global $log_to_local, $log_to_server, $local_file_path;
    $full_local_file_path_and_name = ($log_to_local ? get_full_main_file_path_and_name($local_file_path, $cnum) : null);
    return initialize_for_targets($log_to_local, $log_to_server, $full_local_file_path_and_name);
}


function initialize_for_targets($log_to_local, $log_to_server, $full_local_file_path_and_name) {
    // TODO: implement

    if($log_to_local) {
        // check if dir exists, create if not, check if file exists, create if not
        // catch and print any errors
        // "testdir\\subdir\\test"

        // https://www.php.net/manual/en/function.is-dir.php
        // https://www.php.net/manual/en/function.mkdir.php

    }

    if($log_to_server) {
        // call functions in communication, catch and print any errors
    }  

    return;
}


function get_full_country_file_path_and_name($local_file_path, $cnum) {
    // TODO: implement
    // should these three functions call another function?
    return;
}

function get_full_error_file_path_and_name($local_file_path) {
    // TODO: implement
    return;
}

function get_full_main_file_path_and_name($local_file_path) {
    // TODO: implement
    return;
}


function log_to_targets($log_to_screen, $log_to_local, $log_to_server, $full_local_file_path_and_name, $error_type, $message, $cnum) {
    // TODO: implement

    if($log_to_screen)
        out("Country #$cnum: $message");

    if($log_to_local) {
        // write to $local_file_path, catch and print any errors

    }

    if($log_to_server) {
        // call function in communication, catch and print any errors
    }

    return;
}



// examples of things to log: what decisions were made (and why), how turns are spent
function log_country_message($cnum, $message) {
    global $log_to_screen, $log_to_local, $log_to_server, $local_file_path;

    if(!$cnum) {
        $message = "ERROR: must call log_country_message() with a valid cnum";
        out($message);
        log_error_message(2, $cnum, $message);
        return;
    }

    $full_local_file_path_and_name = ($log_to_local ? get_full_country_file_path_and_name($local_file_path, $cnum) : null);
    return log_to_targets($log_to_screen, $log_to_local, $log_to_server, $full_local_file_path_and_name, null, $message, $cnum);
}



function log_main_message($message)  {
    global $log_to_screen, $log_to_local, $log_to_server, $local_file_path;
    $full_local_file_path_and_name = ($log_to_local ? get_full_main_file_path_and_name($local_file_path, $cnum) : null);
    return log_to_targets($log_to_screen, $log_to_local, $log_to_server, $full_local_file_path_and_name, $error_type, $message, $cnum);
}

function log_error_message($error_type, $cnum, $message) {
    global $log_to_screen, $log_to_local, $log_to_server, $local_file_path;

    if(!$cnum) {
        $message = "ERROR: must call log_error_message() with a valid cnum";
        out($message);
        log_error_message(1, $cnum, $message);
        return;
    }

    if($error_type == null or $error_type < 0) {
        $message = "ERROR: must call log_error_message() with a valid type (non-negative int)";
        out($message);
        log_error_message(0, $cnum, $message);
        return;
    }

    $full_local_file_path_and_name = ($log_to_local ? get_full_error_file_path_and_name($local_file_path) : null);
    return log_to_targets($log_to_screen, $log_to_local, $log_to_server, $full_local_file_path_and_name, $error_type, $message, $cnum);
}

function log_get_name_of_error_type($error_type) {
    if($error_type == 0)
        return 'Called log_error_message() with invalid $error_type';
    if($error_type == 1)
        return 'Called log_error_message() with invalid $cnum';
    if($error_type == 2)
        return 'Called log_country_message() with invalid $cnum';
    else
        return 'ERROR: log_get_name_of_error_type() called with unmapped $error_type';
}



function purge_old_logging_files($server)  {
    global $log_to_local, $log_to_server, $local_file_path;

    // TODO: implement

    // https://www.php.net/manual/en/function.unlink.php
    // https://www.php.net/manual/en/function.rmdir.php
    return;
}


function log_translate_simple_strat_name($strat) {
    switch ($strat) {
        case 'C':
            return "CASHER";
        case 'F':
            return "FARMER";
        case 'I':
            return "INDY";
        case 'T':
            return "TECHER";
        case 'R':
            return "RAINBOW";
        case 'O':
            return "OILER";
        default:
            return "UNKNOWN";  
    }   
}

function log_translate_forced_debug($boolean_value) {
    return ($boolean_value ? ' forced by debug variable' : '');
}

function log_translate_boolean_to_YN($boolean_value) {
    return ($boolean_value ? 'yes' : 'no');
}

function log_translate_instant_to_human_readable($instant) {
    return date('m/d/Y H:i:s', $instant);
}

