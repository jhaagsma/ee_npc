<?php

namespace EENPC;

// new error types must be added to this function
function log_get_name_of_error_type($error_type) {
    // 40 character limit on name
    // 0 - 99 are logging errors
    if($error_type == 0)
        return 'INVALID $error_type LOGGING CALL';
    if($error_type == 1)
        return 'INVALID $cnum LOGGING CALL'; // for log_country_message()

    // 100 - 999 are communication or programmer errors
    if($error_type == 100)
        return 'COMM NOT ACCEPTABLE RESPONSE';
    if($error_type == 101)
        return 'COMM SELLING MORE THAN OWNED';
    if($error_type == 102)
        return 'COMM NOT ENOUGH MONEY';
    if($error_type == 103)
        return 'COMM NOT ENOUGH TURNS';
    if($error_type == 104)
        return 'COMM ALLIES NOT ALLOWED';
    if($error_type == 105)
        return 'COMM UNEXPECTED MSG OBJ RESULT';
    if($error_type == 106)
        return 'COMM UNEXPECTED MSG NON-OBJ RESULT';
    if($error_type == 107)
        return 'COMM UNEXPECTED FUNCTION RESULT';
    if($error_type == 108)
        return 'COMM POSSIBLE SERVER DOWN';
    if($error_type == 109)
        return 'COMM RULES DID NOT LOAD';
    if($error_type == 110)
        return 'RAN OUT OF COUNTRY CREATE ATTEMPTS';
    if($error_type == 111)
        return 'COUNTRY SNAPSHOT FINAL RESULT WAS NULL';       
    if($error_type == 112)
        return 'UNEXPECTED DIRECTORY FOUND WHILE PURGING';    
    if($error_type == 113)
        return 'UNEXPECTED FILE FOUND WHILE PURGING';           
    if($error_type == 114)
        return 'UNDETECTED COUNTRY OUT OF FOOD';     
    if($error_type == 115)
        return 'UNDETECTED COUNTRY OUT OF MONEY';
    if($error_type == 116)
        return 'MISSING OR INVALID STRATEGY';  
    if($error_type == 117)
        return 'NO SETTINGS FILE FOUND'; 
    if($error_type == 118)
        return 'COUNTRY ATTEMPTED DESTOCK IN PROTECTION';
    if($error_type == 119)
        return 'FORCED ADVISOR UPDATE WITH NO REASON';
    if($error_type == 120)
        return 'DESTOCKING NOT ENOUGH TURNS TO RECALL';
    if($error_type == 121)
        return 'DESTOCKING CANNOT RECALL DUE TO OOF/OOM';
    if($error_type == 122)
        return 'TECHER COMPUTING ZERO SELL';
    if($error_type == 123)
        return 'INVALID CPREF VALUES';       

        
    if($error_type == 999) // use when functions are given stupid input and it isn't worth defining a new error
        return 'GENERIC EE_NPC CODE BAD INPUT'; 

    // 1000-1999 are country playing mistakes 
    if($error_type == 1000)
        return 'COUNTRY PLAYED TURN WITHOUT FOOD';
    if($error_type == 1001)
        return 'COUNTRY PLAYED TURN WITHOUT MONEY';
    if($error_type == 1002)
        return 'COUNTRY BAD SELL OF MILITARY ON PM';     
    if($error_type == 1003)
        return 'COUNTRY BAD FOOD PURCHASE ON PUBLIC';           
    if($error_type == 1004)
        return 'COUNTRY PRIVATE MARKET FOOD PURCHASE';           
    if($error_type == 1005)
        return 'CASHING TOO EARLY';        

    // 2000+ are PHP errors or warnings
    if($error_type == 2000)
        return 'PHP Error';
    if($error_type == 2001)
        return 'PHP Warning';
    if($error_type == 2002)
        return 'PHP Parsing Error';     
    if($error_type == 2003)
        return 'PHP Notice';           
    if($error_type == 2004)
        return 'PHP Core Error';           
    if($error_type == 2005)
        return 'PHP Core Warning';       
    if($error_type == 2006)
        return 'PHP Compile Error';     
    if($error_type == 2007)
        return 'PHP Compile Warning';     
    if($error_type == 2008)
        return 'PHP User Error';
    if($error_type == 2009)
        return 'PHP User Warning';
    if($error_type == 2010)
        return 'PHP User Notice';     
    if($error_type == 2011)
        return 'PHP Strict';           
    if($error_type == 2012)
        return 'PHP Recoverable Error';           
    if($error_type == 2013)
        return 'PHP Deprecated';       
    if($error_type == 2014)
        return 'PHP User Deprecated';     
    if($error_type == 2015)
        return 'PHP E_ALL';
    if($error_type == 2999)
        return 'PHP SHUTDOWN';

       
    // ERROR: log_get_name_of_error_type() called with unmapped $error_type
    return null;
}



function update_turn_action_array(&$turn_action_counts, $action_and_turns_used) {
    if(!empty($action_and_turns_used)) {
        foreach($action_and_turns_used as $action => $turns_used) {
            if(isset($turn_action_counts[$action]))
                $turn_action_counts[$action] += $turns_used;
            else
                $turn_action_counts[$action] = $turns_used;
        }
    }
    return;
}


function log_turn_action_counts($c, $server, $cpref, $turn_action_counts) {
    if(empty($turn_action_counts))
        return;

    log_country_message($c->cnum, "Approx turn action summary for login: ".json_encode($turn_action_counts), 'green');
    // log_main_message("Approx turn action summary for login: ".json_encode($turn_action_counts), 'green');

    if(isset($turn_action_counts['cash'])) {
        $reset_seconds = $server->reset_end - $server->reset_start;
        if (time() < floor($server->reset_start + 0.5 * $reset_seconds))
            log_error_message(1005, $c->cnum, "Cashed ".$turn_action_counts['cash']." turns too early in the set");
    }

    /* FUTURE: log these
	logged out with money < -income
	logged out with no food
	hit 25% of max stored turns
	hit 100% of max stored turns
	farmer didn't sell
	indy didn't sell
    */

    return;                      
}


function log_static_cpref_on_turn_0 ($c, $cpref) {
    if($c->turns_played <> 0 || $c->govt <> 'M')
        return false;

    $static_prefs = $cpref->get_static_prefs_to_print();

    $log_message = "Printing static country preferences:";
    foreach($static_prefs as $pref_name)
        $log_message .= "\n    $pref_name: ".$cpref->$pref_name;

    return log_country_message($c->cnum, $log_message);
}


function log_snapshot_message($c, $rules, $snapshot_type, $strat, $is_destocking, &$output_c_values, $prev_c_values = []){
    global $log_country_to_screen, $log_to_local, $local_file_path;

    if($snapshot_type <> 'BEGIN' and $snapshot_type <> 'DELTA' and $snapshot_type <> 'END') {
        die('Called log_snapshot_message() with invalid parameter value '.$snapshot_type.' for $snapshot_type');
        return false;
    }

    if($snapshot_type == 'DELTA' and empty($prev_c_values)) {
        die('Called log_snapshot_message() in DELTA mode with empty previous country values array');
        return false;
    }   

    $full_local_file_path_and_name = ($log_to_local ? get_full_country_file_path_and_name($local_file_path, $c->cnum, true) : null);
    if($log_country_to_screen or $log_to_local)
        $message = generate_compact_country_status($c, $rules, $snapshot_type, $strat, $is_destocking, $output_c_values, $prev_c_values);

    return log_to_targets($log_country_to_screen, $log_to_local, $full_local_file_path_and_name, $message, $c->cnum, null);
};


function log_country_data($cnum_input, $data, $intro_message) {    
    $message = "$intro_message\n".json_encode($data);
    log_country_message($cnum_input, $message);
}


function log_country_market_history_for_single_unit($cnum_input, $unit_name, $unit_market_history) {    
    $message = "Market history for $unit_name: ".($unit_market_history['no_results'] ? "no data" : json_encode($unit_market_history));
    log_country_message($cnum_input, $message);
}


// examples of things to log: what decisions were made (and why), how turns are spent
function log_country_message($cnum_input, $message, $color = null) {
    global $log_country_to_screen, $log_to_local, $local_file_path;

    if(!$cnum_input) {
        global $cnum; // some calling functions don't have $cnum easily available
        $cnum_input = $cnum;
        if(!$cnum_input) {
            $message = "must call log_country_message() with a valid cnum";
            out($message);
            log_error_message(2, $cnum, $message);
            return;
        }
    }

    $full_local_file_path_and_name = ($log_to_local ? get_full_country_file_path_and_name($local_file_path, $cnum_input, false) : null);
    return log_to_targets($log_country_to_screen, $log_to_local, $full_local_file_path_and_name, $message, $cnum_input, $color);
}


function log_main_message($message, $color = null)  {
    global $log_to_local, $local_file_path;
    $full_local_file_path_and_name = ($log_to_local ? get_full_main_file_path_and_name($local_file_path) : null);
    // always log messages to screen
    return log_to_targets(true, $log_to_local, $full_local_file_path_and_name, $message, null, $color);
}


function log_error_message($error_type, $cnum, $message) {
    global $log_to_local, $local_file_path;

    $error_type_name = log_get_name_of_error_type($error_type);
    if($error_type_name === null) {
        $local_error_message = "must call log_error_message() with a valid type (non-negative int)";
        out($local_error_message);
        log_error_message(0, $cnum, $local_error_message);
        return false;
    }
    
    // if a $cnum is available, log to the country file
    $country_local_file_path_and_name = (($cnum and $log_to_local) ? get_full_country_file_path_and_name($local_file_path, $cnum, false) : null);
    $screen_and_country_message = "ERROR $error_type-$error_type_name; CNUM: #".($cnum ?? 'N/A')."; DETAILS: $message";
    // always log errors to screen
    log_to_targets(true, $log_to_local, $country_local_file_path_and_name, $screen_and_country_message, $cnum, 'red'); // FUTURE - return this value somehow??

    // always log to the error file
    // for the error file, use unprintable characters to separate fields and line breaks to pack everything on a single line
    // the error reader on the server will split out things appropriately
    $error_message_for_error_file = $error_type.chr(9).$error_type_name.chr(9)."#$cnum".chr(9).str_replace(array("\r", "\n"), chr(17), $message);
    $is_php_error = ($error_type >= 2000 ? true : false);
    $error_local_file_path_and_name = ($log_to_local ? get_full_error_file_path_and_name($local_file_path, $is_php_error) : null);
    log_to_targets(false, $log_to_local, $error_local_file_path_and_name, $error_message_for_error_file, $cnum, null);  
    return true; 
}


function log_to_targets($log_to_screen, $log_to_local, $local_file_path_and_name, $message, $cnum, $color = null) {
    $is_line_breaks_only = str_replace(array("\r", "\n"), '', $message) == "" ? true : false;
    $line_break_count = substr_count($message,"\n" );
    if($log_to_screen) {
        if($is_line_breaks_only)
            echo $message;
        else {
            $screen_message = ($line_break_count > 0 ? "\n" : "").$message;
            out($screen_message, true, $color); //timestamp is free with out()
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
        if($local_file_path_and_name) {
            file_put_contents ($local_file_path_and_name, $file_message, FILE_APPEND);
        }  
    }

    return;
}



function get_full_country_file_path_and_name($local_file_path, $cnum, $is_snapshot) {
    $file_name = ($is_snapshot ? 'SNAPSHOT_' : 'COUNTRY_') . $cnum . '.txt';
    return "$local_file_path/country/$file_name";
}

function get_full_error_file_path_and_name($local_file_path, $is_php_error) {
    global $username;
    $iso_date = date('Ymd');
    if($is_php_error)
        return "$local_file_path/errors/PHP_ERRORS_$username"."_$iso_date.txt";
    else
        return "$local_file_path/errors/ERRORS_$username"."_$iso_date.txt";
}

function get_full_main_file_path_and_name($local_file_path) {
    global $username;
    $iso_date = date('Ymd');
    return "$local_file_path/main/LOOP_$username"."_$iso_date.txt";
}



function get_current_local_file_path($config_local_file_path_root, $server) {
    return $config_local_file_path_root."/"."logging/$server->name/$server->round_num";
}


function create_logging_directories ($server, $timeout_for_purging = 0) {
    global $log_to_local, $local_file_path, $config_local_file_path_root;

    if(!$config_local_file_path_root) // in case this isn't set somehow
        return false;

    if(!is_dir($config_local_file_path_root)) {
        die("Logging root directory $config_local_file_path_root does not exist!");
        return false;
    }

    // reset $local_file_path if needed
    $local_file_path = get_current_local_file_path($config_local_file_path_root, $server);
    
    if($log_to_local) {
        create_local_directory_if_needed("$local_file_path/country");
        create_local_directory_if_needed("$local_file_path/errors");
        create_local_directory_if_needed("$local_file_path/main");
    }

    if($log_to_local and ($timeout_for_purging <> 0)) { // do purging if there's time
        log_main_message("Looking for old logging data to purge with timeout $timeout_for_purging seconds...");
        $stop_purging_time = time() + $timeout_for_purging;
    
        $dirs = scandir($config_local_file_path_root."/"."logging/$server->name");
    
        foreach($dirs as $dir_name) {
            if(time() >= $stop_purging_time) {
                log_main_message("Purging code hit time limit", "red");
                return false;
            }

            if($dir_name == '.' or $dir_name == '..')
                continue;
   
            $new_directory_path = $config_local_file_path_root."/"."logging/$server->name/$dir_name";

            if(!is_dir($new_directory_path)) { // shouldn't be any files here, so delete them
                log_error_message(113, null, $new_directory_path);
                log_main_message("Deleting file at $new_directory_path");
                unlink($new_directory_path);
            }
            else { // is a directory, should be a round number
                if(!is_numeric($dir_name))
                    log_error_message(112, null, $new_directory_path);
                elseif((int)$dir_name < $server->round_num - 2) { // keep files for previous two resets
                    delete_folder_containing_files($new_directory_path.'/country', $stop_purging_time);
                    delete_folder_containing_files($new_directory_path.'/errors', $stop_purging_time);
                    delete_folder_containing_files($new_directory_path.'/main', $stop_purging_time);

                    // need to delete any other files in here, but there shouldn't be any
                    $files = scandir($new_directory_path);
                    foreach($files as $file) {                            
                        if(time() >= $stop_purging_time) {
                            log_main_message("Purging code hit time limit", "red");
                            return false;
                        }
                            
                        if($file == '.' or $file == '..')
                            continue;

                        $file_name_with_path = $new_directory_path.'/'.$file;   
                        if(!is_dir($file_name_with_path)) { // shouldn't be any files here, so delete them
                            log_error_message(113, null, $file_name_with_path);
                            log_main_message("Deleting file at $file_name_with_path");
                            unlink($file_name_with_path);
                        }
                        else { // error on directory being here
                            log_error_message(112, null, $file_name_with_path);                            
                        }  
                    }
                    log_main_message("Removing directory at $new_directory_path");
                    rmdir($new_directory_path); // can only remove if all files are gone
                }  
            }
        }
    log_main_message("Completed purging of old log files");     
    } // done purging
    
    return true;
}

// delete a folder that only contains files (no other folders)
function delete_folder_containing_files($path_name, $stop_purging_time) {
    if(!file_exists($path_name)) // nothing to delete
        return true;

    $deleted_file_count = 0;

    $files = scandir($path_name);
    foreach($files as $file) {
        if(time() >= $stop_purging_time) {
            log_main_message("Purging code hit time limit", "red");
            return false;
        }

        if($file == '.' or $file == '..')
            continue;

        $file_name_with_path = $path_name.'/'.$file;
        if(is_dir($file_name_with_path)) { // shouldn't be any directories here
            log_error_message(112, null, $file_name_with_path);
            return false;         
        }
        //log_main_message("Deleting file at $file_name_with_path"); 
        unlink($file_name_with_path);
        $deleted_file_count++;
        if($deleted_file_count % 50 == 0)
            log_main_message("Deleted 50 files from $path_name");
    }

    return rmdir($path_name);
}

function create_local_directory_if_needed($path_name) {
    $success = true;
    if(!is_dir($path_name)) {
        out("Creating local directory $path_name"); // have to use out() here because folders don't exist yet
        $success = mkdir("$path_name", 0770, true);
    }
    if(!$success) {
        sleep(5); // we might have had another process create the same folder, so wait 5 seconds and try again
        $success = true;
        if(!is_dir($path_name)) {
            out("Creating local directory $path_name"); // have to use out() here because folders don't exist yet
            $success = mkdir("$path_name", 0770, true);
        }
    }
    if(!$success)
        die("Failed to create local directory $path_name");

    return $success; // mkdir() returns true on success
}


function generate_compact_country_status($c, $rules, $snapshot_type, $strat, $is_destocking, &$output_c_values, $prev_c_values) {
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
    $output_c_values['c_om_v'] = get_total_value_of_on_market_goods($c, $rules);

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

    return generate_compact_country_status_string($c->cnum, $header_padded, $snapshot_type, $output_c_values, $prev_c_values);
}



function generate_compact_country_status_string($cnum, $header, $snapshot_type, $current_c_values, $prev_c_values) {
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
    //$str = ""; // for testing error message

    if(!$str)
        log_error_message(111, $cnum, ""); // FUTURE: add troubleshooting info to error message
    return $str;
}



/**
 * Return engineering notation
 *
 * @param  number $number The number to round
 *
 * @return string         The rounded number with B/M/k
 */
function engnot($number)
{
    if (abs($number) > 1000000000) {
        return round($number / 1000000000, $number / 1000000000 > 100 ? 0 : 1).'B';
    } elseif (abs($number) > 1000000) {
        return round($number / 1000000, $number / 1000000 > 100 ? 0 : 1).'M';
    } elseif (abs($number) > 10000) {
        return round($number / 1000, $number / 1000 > 100 ? 0 : 1).'k';
    }

    return $number;
}//end engnot()


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
        //case 'O':
        //    return "OILER";
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



// copied from /earthempires/blob/master/www/include/errorlog.php
function userErrorHandler($errno, $errmsg, $filename, $linenum) {
    global $cnum;

    $errmsg = preg_replace("/^(.*)\[<(.*)>\](.*)$/", "\\1\\3", $errmsg);

    $backoutput = "";

    if(false && function_exists('debug_backtrace')){
        $backtrace = debug_backtrace();

        //ignore $backtrace[0] as that is this function, the errorlogger
        
        for($i = 1; $i < 99 && $i < count($backtrace); $i++){ //only show 10 levels deep
            $errfile = (isset($backtrace[$i]['file']) ? $backtrace[$i]['file'] : '');
            
            if(strpos($errfile, $sitebasedir) === 0)
                $errfile = substr($errfile, strlen($sitebasedir));
            
            $line = (isset($backtrace[$i]['line']) ? $backtrace[$i]['line'] : '');
            $function = (isset($backtrace[$i]['function']) ? $backtrace[$i]['function'] : '');
            $args = (isset($backtrace[$i]['args']) ? count($backtrace[$i]['args']) : '');
            
            $backoutput .= "$errfile:$line:$function($args)";
            
            if($i+1 < count($backtrace)) //show if there are more levels that were cut off
                $backoutput .= "<-";
        }
    }

    $error_code = get_log_error_type_from_PHP_errno($errno);
    $error_message = "\"$filename: $linenum\",\"$errmsg\",\"$backoutput\"\r\n";
    log_error_message($error_code, $cnum, $error_message);
    
    //Terminate script if fatal error
    if($errno != 2 && $errno != 8 && $errno != 512 && $errno != 1024 && $errno != 2048){
        die("A fatal error has occured. Script execution has been aborted");
    }
}

set_error_handler("EENPC\userErrorHandler"); 

function get_log_error_type_from_PHP_errno($errno) {
    /*
    static $errortype = array ( 1   => "Error",
                                2   => "Warning",
                                4   => "Parsing Error",
                                8   => "Notice",
                                16  => "Core Error",
                                32  => "Core Warning",
                                64  => "Compile Error",
                                128 => "Compile Warning",
                                256 => "User Error",
                                512 => "User Warning",
                                1024=> "User Notice",
                                2048=> "PHP Strict",
                                4096=> "Recoverable Error",
                                8192=> "Deprecated",
                                16384=> "User Deprecated",
                                32767=> "E_ALL"
                            );
    */
    //out($errno);
    return 2000 + ceil(log($errno, 2));
}


// TODO: does this log die() commands?
function handleShutdown(){
    $error = error_get_last();
    if($error !== NULL){
        global $cnum;
        $error_message = "[SHUTDOWN] Parse Error: ".$error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line'] . PHP_EOL;
        log_error_message(2999, $cnum, $error_message);
    }
}

register_shutdown_function('EENPC\handleShutdown');