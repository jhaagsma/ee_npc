<?php

namespace EENPC;

function test(){
    // TODO: remove this when done

    mkdir("./logging/alphaai/11/country", 0700, true);
    mkdir("./logging/alphaai/11/errors", 0700, true);
    mkdir("./logging/alphaai/11/main", 0700, true);

    return;
}


function log_snapshot_message($c, $snapshot_type, $strat, $is_destocking, &$output_c_values, $prev_c_values = []){
    global $log_country_to_screen, $log_to_local, $log_to_server, $local_file_path;

    if($snapshot_type <> 'BEGIN' and $snapshot_type <> 'DELTA' and $snapshot_type <> 'END')
        return; // TODO: throw error

    if($snapshot_type == 'DELTA' and empty($prev_c_values)) 
        return; // TODO: throw error     

    $full_local_file_path_and_name = ($log_to_local ? get_full_country_file_path_and_name($local_file_path, $c->cnum, true) : null);
    if($log_country_to_screen or $log_to_local or $log_to_server)
        $message = generate_compact_country_status($c, $snapshot_type, $strat, $is_destocking, $output_c_values, $prev_c_values);

    return log_to_targets($log_country_to_screen, $log_to_local, $log_to_server, $full_local_file_path_and_name, null, $message, $c->cnum, null);
};



// examples of things to log: what decisions were made (and why), how turns are spent
function log_country_message($cnum_input, $message) {
    global $log_country_to_screen, $log_to_local, $log_to_server, $local_file_path;

    if(!$cnum_input) {
        global $cnum; // some calling functions don't have $cnum easily available
        $cnum_input = $cnum;
        if(!$cnum_input) {
            $message = "ERROR: must call log_country_message() with a valid cnum";
            out($message);
            log_error_message(2, $cnum, $message);
            return;
        }
    }

    $full_local_file_path_and_name = ($log_to_local ? get_full_country_file_path_and_name($local_file_path, $cnum_input, false) : null);
    return log_to_targets($log_country_to_screen, $log_to_local, $log_to_server, $full_local_file_path_and_name, null, $message, $cnum_input, null);
}



function log_main_message($message, $color = null)  {
    global $log_to_local, $log_to_server, $local_file_path;
    $full_local_file_path_and_name = ($log_to_local ? get_full_main_file_path_and_name($local_file_path) : null);
    // always log messages to screen
    return log_to_targets(true, $log_to_local, $log_to_server, $full_local_file_path_and_name, null, $message, null, $color);
}


function log_error_message($error_type, $cnum, $message) {
    global $log_to_local, $log_to_server, $local_file_path;

    if($error_type == null or $error_type < 0) {
        $local_error_message = "ERROR: must call log_error_message() with a valid type (non-negative int)";
        out($local_error_message);
        log_error_message(0, $cnum, $local_error_message);
        return;
    }
   

    /* -- going to allow errors with no cnum for now
    if($error_type <> 1 and !$cnum) {
        $message = "ERROR: must call log_error_message() with a valid cnum";
        out($message);
        log_error_message(1, $cnum, $message);
        return;
    }
    */

    // TODO: log different things on screen/country file/error file?
    // TODO: what about multi-line error messages?
    $error_type_name = log_get_name_of_error_type($error_type);
    if($error_type_name === null) {
        //throw new Exception("Error type $error_type is not mapped"); // TODO: make this work
        return;
    }

    $full_error_message = 'ERROR '.$error_type.' | '.$error_type_name.' | cnum #'.($cnum ?? 'N\A').' | '.$message;
    $full_local_file_path_and_name = ($log_to_local ? get_full_error_file_path_and_name($local_file_path) : null);


    // log to country file always, this is a bit hacky
    if($cnum) {
        $country_local_file_path_and_name = ($log_to_local ? get_full_country_file_path_and_name($local_file_path, $cnum, false) : null);
        log_to_targets(false, $log_to_local, $log_to_server, $country_local_file_path_and_name, null, $full_error_message, $cnum, null);
        // future: return the value of this somehow? or make this less hacky
    }

    // always log errors to screen
    log_to_targets(true, $log_to_local, $log_to_server, $full_local_file_path_and_name, $error_type, $full_error_message, $cnum, null);
    return;
}


function log_to_targets($log_to_screen, $log_to_local, $log_to_server, $full_local_file_path_and_name, $error_type, $message, $cnum, $color = null) {
    // TODO: error type
    $is_line_breaks_only = str_replace(array("\r", "\n"), '', $message) == "" ? true : false;
    $line_break_count = substr_count($message,"\n" );
    if($log_to_screen) {
        if($is_line_breaks_only)
            echo $message;
        else {
            $screen_message = ($line_break_count > 0 ? "\n" : "").$message;
            if($color)
                out(Colors::getColoredString($screen_message, $color));
            else
                out($screen_message); // timestamp is free
        }
        //out("Country #$cnum: $message");
    }

    if($log_to_local) {
        // write to $full_local_file_path_and_name, catch and print any errors
        //out($full_local_file_path_and_name);
        if($is_line_breaks_only)
            $file_message = $message;
        else {
            $timestamp_for_file = '['.date('Y-m-d H:i:s').'] ';     
            // the regex removes color coding   
            $file_message = $timestamp_for_file.($line_break_count > 0 ? "\n" : "").preg_replace('/\e[[][A-Za-z0-9];?[0-9]*m?/', '', $message)."\n";
        }
        file_put_contents ($full_local_file_path_and_name, $file_message, FILE_APPEND);
    }

    if($log_to_server) {
        // TODO: call function in communication, catch and print any errors
    }

    return;
}


// TODO: windows support?
function get_full_country_file_path_and_name($local_file_path, $cnum, $is_snapshot) {
    $file_name = ($is_snapshot ? 'SNAPSHOT_' : 'COUNTRY_') . $cnum . '.txt';
    return "$local_file_path/country/$file_name";
}

function get_full_error_file_path_and_name($local_file_path) {
    $iso_date = date('Ymd');
    return "$local_file_path/errors/ERRORS_$iso_date.txt";
}

function get_full_main_file_path_and_name($local_file_path) {
    $iso_date = date('Ymd');
    return "$local_file_path/main/LOOP_$iso_date.txt";
}



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
    $full_local_file_path_and_name = ($log_to_local ? get_full_country_file_path_and_name($local_file_path, $cnum, false) : null);
    return initialize_for_targets($log_to_local, $log_to_server, $full_local_file_path_and_name);
}

function initialize_country_snapshot_logging_file($server, $cnum) {
    global $log_to_local, $log_to_server, $local_file_path;
    $full_local_file_path_and_name = ($log_to_local ? get_full_country_file_path_and_name($local_file_path, $cnum, true) : null);
    return initialize_for_targets($log_to_local, $log_to_server, $full_local_file_path_and_name);
}



function initialize_error_logging_file($server) {
    global $log_to_local, $log_to_server, $local_file_path;
    $full_local_file_path_and_name = ($log_to_local ? get_full_error_file_path_and_name($local_file_path) : null);
    return initialize_for_targets($log_to_local, $log_to_server, $full_local_file_path_and_name);
}

function initialize_main_logging_file($server) {
    global $log_to_local, $log_to_server, $local_file_path;
    $full_local_file_path_and_name = ($log_to_local ? get_full_main_file_path_and_name($local_file_path) : null);
    return initialize_for_targets($log_to_local, $log_to_server, $full_local_file_path_and_name);
}


function initialize_for_targets($log_to_local, $log_to_server, $full_local_file_path_and_name) {
    // TODO: implement

    if($log_to_local) {
        // check if dir exists, create if not
        // catch and print any errors
        // "testdir\\subdir\\test" ???

        // https://www.php.net/manual/en/function.is-dir.php
        // https://www.php.net/manual/en/function.mkdir.php

    }

    if($log_to_server) {
        // call functions in communication, catch and print any errors
    }  

    return;
}






function purge_old_logging_files($server)  {
    global $log_to_local, $log_to_server, $local_file_path;

    // TODO: implement

    // https://www.php.net/manual/en/function.unlink.php
    // https://www.php.net/manual/en/function.rmdir.php
    return;
}



function log_get_name_of_error_type($error_type) {
    // 0 - 99 are logging errors
    if($error_type == 0)
        return 'Called log_error_message() with invalid $error_type';
    if($error_type == 1)
        return 'Called log_error_message() with invalid $cnum';
    if($error_type == 2)
        return 'Called log_country_message() with invalid $cnum';

    // 100 - 999 are communication or PHP errors
    if($error_type == 100)
        return 'Not acceptable response';
    if($error_type == 101)
        return 'Country selling more than owned';
    if($error_type == 102)
        return 'Not enough money';
    if($error_type == 103)
        return 'Not enough turns';
    if($error_type == 104)
        return 'Allies not allowed';
    if($error_type == 105)
        return 'Unexpected message object result';
    if($error_type == 106)
        return 'Unexpected message non-object result';
    if($error_type == 107)
        return 'Unexpected function result';
    if($error_type == 108)
        return 'Possible server down';
    if($error_type == 109)
        return 'Rules did not load';
    if($error_type == 110)
        return 'Ran out of create country attempts';



    // 1000+ are playing errors 
    if($error_type == 1000)
        return 'Ran out of food';
    if($error_type == 1001)
        return 'Ran out of money';



       
    // ERROR: log_get_name_of_error_type() called with unmapped $error_type
    return null;
}


function generate_compact_country_status($c, $snapshot_type, $strat, $is_destocking, &$output_c_values, $prev_c_values) {
    // this code is a complete mess because I changed its purpose half way through and tried fixing with copy and replace
    $output_c_values = [];
    // turns and resources
    $output_c_values['c_t_st'] = "$c->turns($c->turns_stored)";
    $output_c_values['c_t_pl'] = $c->turns_played;
    $output_c_values['c_netw'] = $c->networth;
    $output_c_values['c_land'] = $c->land;
    $output_c_values['c_cash'] = $c->money;
    $output_c_values['c_food'] = $c->food;
    $output_c_values['c_tech'] = totaltech($c);

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
    $output_c_values['c_pbds'] = $prod_buildings;
    $output_c_values['c_ibds'] = $c->b_indy;
    $output_c_values['c_nbds'] = $non_prod_buildings;
    $output_c_values['c_cs'] = $c->b_cs;
    $output_c_values['c_emty'] = $c->empty;

    // military
    $output_c_values['c_spy'] = $c->m_spy;
    $output_c_values['c_tr'] = $c->m_tr;
    $output_c_values['c_jets'] = $c->m_j;
    $output_c_values['c_tu'] = $c->m_tu;
    $output_c_values['c_ta'] = $c->m_ta;
    $output_c_values['c_read'] = $c->readiness;
    $output_c_values['c_ps'] = '5 (5)'; // FUTURE: when attacking is available
    $output_c_values['c_oil'] = $c->oil;   

    // tech percentages
    $output_c_values['c_pmil'] = $c->pt_mil; 
    $output_c_values['c_pbus'] = $c->pt_bus;
    $output_c_values['c_pres'] = $c->pt_res;
    $output_c_values['c_pwep'] = $c->pt_weap;
    if($strat == 'I')
        $prod_tech = $c->pt_indy;
    elseif($strat == 'F')
        $prod_tech = $c->pt_agri;
    else
        $prod_tech = "N/A";
    $output_c_values['c_ppro'] = $prod_tech;

    // production
    $output_c_values['c_dstk'] = $is_destocking;
    $output_c_values['c_tax']  = $c->taxes;
    $output_c_values['c_exp']  = $c->expenses;
    $output_c_values['c_netf'] = $c->foodnet;
    $output_c_values['c_neto'] = $c->oilpro;
    $output_c_values['c_tpt']  = $c->tpt;
    $output_c_values['c_neti'] = round($c->b_indy * 1.86 * 0.01 * $c->pt_indy * ($c->govt == 'C' ? 1.35 : 1.0)); // FUTURE: from game code
    $output_c_values['c_bpt']  = $c->bpt;

    // value of goods on market
    $output_c_values['c_om_v'] = get_total_value_of_on_market_goods($c); // FUTURE: won't work with stocking

    $on_market_military = 0;
    $military_list = ['m_tr','m_j','m_tu','m_ta'];
    foreach($military_list as $m_unit)
        $on_market_military += $c->onMarket($m_unit);
    $output_c_values['c_om_u'] = $on_market_military;

    $on_market_bushels = $c->onMarket('food');
    $output_c_values['c_om_b'] = $on_market_bushels;   

    $on_market_tech = 0;
    $tech_list = ['t_mil','t_med','t_bus','t_res','t_agri','t_war','t_ms','t_weap','t_indy','t_spy','t_sdi'];
    foreach($tech_list as $tech_type)
        $on_market_tech += $c->onMarket($tech_type);
    $output_c_values['c_om_t'] = $on_market_tech;

    $header_base = "$snapshot_type for [".log_translate_simple_strat_name($strat)."] $c->cname (#$c->cnum) (".$c->govt.($c->gdi ? "G" : "").")";
    $header_padded = str_pad($header_base, 78, '-', STR_PAD_BOTH);

    return generate_compact_country_status_string($header_padded, $snapshot_type, $output_c_values, $prev_c_values);
}



function generate_compact_country_status_string($header, $snapshot_type, $current_c_values, $prev_c_values) {
    if($snapshot_type == 'DELTA') {
        foreach($current_c_values as $k=>$v) {
            if($k == 'c_t_st' OR $k == 'c_ps' OR $k == 'c_dstk')
                continue;            
            if(isset($prev_c_values[$k])) {
                if($prev_c_values[$k] <> "N/A" and $current_c_values[$k] <> "N/A") {
                    //out("doing calc for $k: $prev_c_values[$k]     $current_c_values[$k]");
                    $current_c_values[$k] = $v - $prev_c_values[$k]; 
                }
            }
        }
    }

    extract($current_c_values);

    if($snapshot_type == 'DELTA') {
        // no support for these in delta view
        $c_t_st = 'N/A';
        $c_ps = 'N/A';
        $c_dstk = 'N/A';    
    }

    $t_st = str_pad($c_t_st, 8, ' ', STR_PAD_LEFT);
    $t_pl = str_pad($c_t_pl, 8, ' ', STR_PAD_LEFT);
    $netw = str_pad(engnot($c_netw), 8, ' ', STR_PAD_LEFT);
    $land = str_pad(engnot($c_land), 8, ' ', STR_PAD_LEFT);
    $cash = str_pad(engnot($c_cash), 8, ' ', STR_PAD_LEFT);
    $food = str_pad(engnot($c_food), 8, ' ', STR_PAD_LEFT);
    $tech = str_pad(engnot($c_tech), 8, ' ', STR_PAD_LEFT);
    
    // buildings
    $pbds = str_pad($c_pbds, 8, ' ', STR_PAD_LEFT);
    $ibds = str_pad($c_ibds, 8, ' ', STR_PAD_LEFT);   
    $nbds = str_pad($c_nbds, 8, ' ', STR_PAD_LEFT);
    $cs = str_pad($c_cs, 8, ' ', STR_PAD_LEFT);   
    $emty = str_pad($c_emty, 8, ' ', STR_PAD_LEFT);   
 
    // military
    $spy = str_pad(engnot($c_spy), 8, ' ', STR_PAD_LEFT);
    $tr = str_pad(engnot($c_tr), 8, ' ', STR_PAD_LEFT);
    $jets = str_pad(engnot($c_jets), 8, ' ', STR_PAD_LEFT);
    $tu = str_pad(engnot($c_tu), 8, ' ', STR_PAD_LEFT);
    $ta = str_pad(engnot($c_ta), 8, ' ', STR_PAD_LEFT);
    $read = str_pad($c_read, 8, ' ', STR_PAD_LEFT);
    $ps = str_pad($c_ps, 8, ' ', STR_PAD_LEFT); // FUTURE: when attacking is available
    $oil = str_pad(engnot($c_oil), 8, ' ', STR_PAD_LEFT);   
    
    // tech percentages
    $pmil = str_pad(round($c_pmil, 2).'%', 8, ' ', STR_PAD_LEFT); //-100.12% is 8 characters
    $pbus = str_pad(round($c_pbus, 2).'%', 8, ' ', STR_PAD_LEFT);
    $pres = str_pad(round($c_pres, 2).'%', 8, ' ', STR_PAD_LEFT);
    $pwep = str_pad(round($c_pwep, 2).'%', 8, ' ', STR_PAD_LEFT);
    $ppro = str_pad(($c_ppro <> -1 ? round($c_ppro, 2).'%' : 'N/A'), 8, ' ', STR_PAD_LEFT);
    
    // production
    $dstk = str_pad(($c_dstk === "N/A" ? $c_dstk : log_translate_boolean_to_YN($c_dstk)), 8, ' ', STR_PAD_LEFT);
    $tax = str_pad($c_tax, 8, ' ', STR_PAD_LEFT);
    $exp = str_pad($c_exp, 8, ' ', STR_PAD_LEFT);
    $netf = str_pad($c_netf, 8, ' ', STR_PAD_LEFT);
    $neto = str_pad($c_neto, 8, ' ', STR_PAD_LEFT);
    $tpt = str_pad($c_tpt, 8, ' ', STR_PAD_LEFT);
    $neti = str_pad($c_neti, 8, ' ', STR_PAD_LEFT);
    $bpt = str_pad($c_bpt, 8, ' ', STR_PAD_LEFT);
    
    // value of goods on market
    $om_v = str_pad(engnot($c_om_v), 8, ' ', STR_PAD_LEFT);   
    $om_u = str_pad(engnot($c_om_u), 8, ' ', STR_PAD_LEFT);   
    $om_b = str_pad(engnot($c_om_b), 8, ' ', STR_PAD_LEFT);   
    $om_t = str_pad(engnot($c_om_t), 8, ' ', STR_PAD_LEFT);   

    $spcs = str_repeat(' ', 8);
    $s = "\n|  ";
    $e = "  |";   

    $str = $header;
    $str .= $s.'Turns:         '.$t_st.'   Spies:       '.$spy .'   Destocking:   '.$dstk.$e;
    $str .= $s.'Turns Played:  '.$t_pl.'   Troops:      '.$tr  .'   Tax Revenues: '.$tax .$e;
    $str .= $s.'Networth:      '.$netw.'   Jets:        '.$jets.'   Expenses:     '.$exp .$e;
    $str .= $s.'Land:          '.$land.'   Turrets:     '.$tu  .'   Net Food:     '.$netf.$e;
    $str .= $s.'Money:         '.$cash.'   Tanks:       '.$ta  .'   Net Oil:      '.$neto.$e;
    $str .= $s.'Food:          '.$food.'   Readiness:   '.$read.'   TPT:          '.$tpt .$e;
    $str .= $s.'Oil:           '.$oil .'   PS Brigades: '.$ps  .'   Indy prod:    '.$neti.$e;
    $str .= $s.'Tech Points:   '.$tech.'                '.$spcs.'   BPT:          '.$bpt .$e;
	$str .= $s.'               '.$spcs.'                '.$spcs.'                 '.$spcs.$e;
    $str .= $s.'Prod Blds:     '.$pbds.'   Mil tech:    '.$pmil.'   OM Value:     '.$om_v.$e;
    $str .= $s.'Indy Blds:     '.$ibds.'   Bus tech:    '.$pbus.'   OM Units:     '.$om_u.$e;
    $str .= $s.'Non-prod Blds: '.$nbds.'   Res tech:    '.$pres.'   OM Bushels:   '.$om_b.$e;
    $str .= $s.'CS:            '.$cs  .'   Wep tech:    '.$pwep.'   OM Tech:      '.$om_t.$e;
    $str .= $s.'Unused:        '.$emty.'   Prd tech:    '.$ppro.'                 '.$spcs.$e;
    $str .= "\n".str_repeat('-', 78);

    // TODO: throw exception if null string (make sure this doesn't crash the script)
    return $str;
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