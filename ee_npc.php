#!/usr/bin/php
<?php
/**
 * This is the main script for the EE NPC's
 *
 * PHP Version 7
 *
 * @category Main
 * @package  EENPC
 * @author   Julian Haagsma aka qzjul <jhaagsma@gmail.com>
 * @license  All EENPC files are under the MIT License
 * @link     https://github.com/jhaagsma/ee_npc
 */

namespace EENPC;

spl_autoload_register(
    function ($class) {
        if (stristr($class, "EENPC")) {
            $parts = explode('\\', $class);
            include end($parts) . '.class.php';
        }
    }
);

require_once 'Terminal.class.php';
require_once 'communication.php';

// don't allow the process to be kicked off concurrently by accident
// this seemingly cannot be in a function because a return releases the lock?
$fp0 = fopen('zz_lock.txt', 'c');
if (flock($fp0, LOCK_EX | LOCK_NB)) // LOCK_NB makes it fail right away instead of waiting
    out("Lock acquired on zz_lock.txt, script will proceed"); 
else
    die("Lock could not be acquired on zz_lock.txt - is the process already running?");


out(Colors::getColoredString("STARTING UP BOT", "purple"));

date_default_timezone_set('GMT'); //SET THE TIMEZONE FIRST
error_reporting(E_ALL); //SET THE ERROR REPORTING TO REPORT EVERYTHING
out('Error Reporting and Timezone Set');

$config = null;
require_once 'config.php';

if ($config === null) {
    //ADD IN AUTOGENERATE CONFIG HERE?
    die("Config not included successfully! Do you have config.php set up properly?");
}

require_once 'country_functions.php';
require_once 'Country.class.php';
require_once 'PublicMarket.class.php';

require_once 'rainbow_strat.php';
require_once 'farmer_strat.php';
require_once 'techer_strat.php';
require_once 'casher_strat.php';
require_once 'indy_strat.php';
require_once 'oiler_strat.php';
require_once 'Destocking.php';
require_once 'Logging.php';
require_once 'Purchasing.php';
require_once 'Selling.php';
require_once 'Stockpiling.php';

define("RAINBOW", Colors::getColoredString("Rainbow", "purple"));
define("FARMER", Colors::getColoredString("Farmer", "cyan"));
define("TECHER", Colors::getColoredString("Techer", "brown"));
define("CASHER", Colors::getColoredString("Casher", "green"));
define("INDY", Colors::getColoredString("Indy", "yellow"));
define("OILER", Colors::getColoredString("Oiler", "red"));

global $username; // need this for logging
$username     = $config['username'];    //<======== PUT IN YOUR USERNAME IN config.php
$aiKey        = $config['ai_key'];      //<======== PUT IN YOUR AI API KEY IN config.php
$baseURL      = $config['base_url'];    //<======== PUT IN THE BASE URL IN config.php
$serv         = isset($config['server']) ? $config['server'] : 'ai';
$cnum         = null;
$lastFunction = null;
$turnsleep    = isset($config['turnsleep']) ? $config['turnsleep'] : 500000;
$mktinfo      = null; //so we don't have to get it mkt data over and over again
$APICalls     = 0;

$rules  = getRules();
$server = getServer(false); // must be called with false here because directory path depends on initial server object

global $log_country_to_screen, $log_to_local, $local_file_path, $config_local_file_path_root;
$log_country_to_screen = isset($config['log_country_info_to_screen']) ? $config['log_country_info_to_screen'] : true;
$log_to_local = isset($config['log_to_local_files']) ? $config['log_to_local_files'] : false;
if (isset($config['local_path_for_log_files'])) {
    $config_local_file_path_root = $config['local_path_for_log_files'];
}

// function below sets $local_file_path
create_logging_directories($server, 0); // don't purge old files here because we want startup to be as fast as possible

if (file_exists($config['save_settings_file'])) {
    log_main_message("Try to load saved settings");
    global $settings;
    $settings = json_decode(file_get_contents($config['save_settings_file']));
    log_main_message("Successfully loaded settings!");
} else {
    log_error_message(117, null, "No Settings File Found");
}

log_main_message("BOT IS STARTING AND HAS CLEARED INITIAL CHECKS", 'purple');
log_main_message('Current Unix Time: '.time());
log_main_message('Entering Infinite Loop');

$sleepcount = $loopcount = 0;
$played     = true;
$checked_for_non_ai = false;
$set_strategies = true;


//$market            = new PublicMarket();
$server_avg_networth = $server_avg_land = 0;

while (1) {
    if (!is_object($server)) {
        $server = getServer(true);
    }

    if($server->alive_count < $server->countries_allowed) {
        create_logging_directories($server, 30);
        $max_create_attempts = $create_attempts_remaining = 2 * ($server->countries_allowed - $server->alive_count);
        while ($server->alive_count < $server->countries_allowed and $create_attempts_remaining > 0) {
            log_main_message("Less countries than allowed! (".$server->alive_count.'/'.$server->countries_allowed.')');
            $set_strategies = true;
            $send_data = ['cname' => NameGenerator::rand_name()];
            log_main_message("Making new country named '".$send_data['cname']."'");
            $cnum = ee('create', $send_data);
            if(isset($settings->$cnum)) { // clear strategy from previous rounds
                // this is here so we can change bot strategy percentages from round to round
                // without trying to figure out when is safe to delete the settings file
                // can't be at end of loop because the eebot script can get killed before all countries are created
                $settings->$cnum = null;
                log_main_message("Clearing out settings from file for new country #$cnum", 'purple');
                file_put_contents($config['save_settings_file'], json_encode($settings));
            }
            log_main_message($send_data['cname'].' (#'.$cnum.') created!');
            $server = getServer(true);
            if ($server->reset_start > time()) {
                $timeleft      = $server->reset_start - time();
                $countriesleft = $server->countries_allowed - $server->alive_count;
                $sleeptime     = $timeleft / $countriesleft;
                log_main_message("Sleep for $sleeptime to spread countries out");
                sleep($sleeptime);
            }
            $create_attempts_remaining--; // avoid infinite loop when something goes wrong
        }
        if($create_attempts_remaining == 0)
            log_error_message(110, null, "Exhausted $max_create_attempts create attempts but did not create all countries");
    }

    if ($server->reset_start > time()) {
        log_main_message("Reset has not started!");          //done() is defined below
        sleep(max(300, time() - $server->reset_start));        //sleep until the reset starts
        continue;                               //return to the beginning of the loop
    } elseif ($server->reset_end < time()) {
        log_main_message("Reset is over!");        
        sleep(300);                             //wait 5 mins, see if new one is created
        $server = getServer(true);
        continue;                               //restart the loop
    }

    if($server->is_debug) { // remove non-AI countries from $countries - useful on debug servers with human countries 
        $countries = [];
        if (!$checked_for_non_ai) {
            log_main_message("Checking for non-AI countries on debug server, this may take tens of seconds remotely...");
            foreach($server->cnum_list->alive as $cnum) {
                // need a cheap call to auth - using pm_info for now
                $result = ee('pm_info');
                if($result <> 'NOT_AN_AI_COUNTRY')
                    $countries[] = $cnum;
                else {
                    log_main_message("Removing non-AI country with cnum #$cnum from the list of countries to play", 'red');
                    $non_ai_countries[$cnum] = 1;
                    if(isset($settings->$cnum)) {
                        $settings->$cnum = null;
                        log_main_message("Clearing out strategy from file for country #$cnum", 'purple');
                        file_put_contents($config['save_settings_file'], json_encode($settings));
                    }
                }
            }
            $checked_for_non_ai = true;
            log_main_message("Finished checking for non-AI countries");
        }
        else { // $checked_for_non_ai is true
            foreach($server->cnum_list->alive as $cnum) {
                if(!isset($non_ai_countries[$cnum]))
                    $countries[] = $cnum;
            }
        }
    }
    else { // not debug
        $countries = $server->cnum_list->alive;
    }

    if(count($countries) == 0) {
        log_main_message("No AI countries exist yet, sleeping for 60 seconds and starting loop over", 'red');
        sleep(60);
        continue;
    }

    if($set_strategies){
        log_main_message("Checking if country strategies need to be assigned...");
        $dead_country_count = count($server->cnum_list->dead);
        log_main_message("Dead country count is $dead_country_count");
        $cnums_new = array_values($countries);
        sort($cnums_new);
        foreach($cnums_new as $key => $cnum) {
            if (!isset($settings->$cnum)) {  
                $strat = Bots::assign_strat_from_country_loop($dead_country_count + $key, $server->is_debug, $server->is_ai_server);    
                log_main_message("No settings line for #$cnum found. Newly assigned strategy is: $strat");
                $settings->$cnum = json_decode(
                    json_encode(
                        [
                            'strat' => $strat,
                            'bot_secret' => null,
                            'playrand' => null,
                            'lastplay' => 0,
                            'nextplay' => 0,
                            'price_tolerance' => 1.0,
                            'allyup' => null,
                            'gdi' => null,
                            'retal' => [],
                        ]
                    )
                );
            }
            
            if (!isset($settings->$cnum->strat) || $settings->$cnum->strat == null) {
                $strat = Bots::assign_strat_from_country_loop($dead_country_count + $key, $server->is_debug, $server->is_ai_server);
                $settings->$cnum->strat = $strat;
                log_main_message("No strategy found for #$cnum in settings line. Newly assigned strategy is: $strat");
            }
        }
        // don't bother to check if save is truly needed because this code will likely only run once per loop (unless bot countries get killed)
        log_main_message("Saving settings file", 'purple');
        file_put_contents($config['save_settings_file'], json_encode($settings));

        log_main_message("Done assigning country strategies");
        $set_strategies = false;
    }

    if ($played) {
        Bots::server_start_end_notification($server);
        Bots::playstats($countries);
        echo "\n";
    }

    $played = false;
    //log_main_message("Country Count: ".count($countries));
    foreach ($countries as $cnum) {
        Debug::off(); //reset for new country
        $save = false;

        global $cpref_file;
        $cpref_file = $settings->$cnum;

        // check for missing strategy just in case
        if (!isset($cpref_file->strat)) {
            log_error_message(116, $cnum, "Strategy is not set in settings.json file");
            continue;
        }

        // check for invalid strategy just in case
        if (log_translate_simple_strat_name($cpref_file->strat) == "UNKNOWN") {
            log_error_message(116, $cnum, "Invalid strategy is set in settings.json file");
            continue;
        }

        if (!isset($cpref_file->retal)) {
            $cpref_file->retal = [];
        }

        $cpref_file->retal = json_decode(json_encode($cpref_file->retal), true);

        $mktinfo = null;

        if (!isset($cpref_file->bot_secret) || $cpref_file->bot_secret == null) {
            $cpref_file->bot_secret = 1000000000 + mt_rand(0,999999999); // 9 digits ought to be enough for anybody
            log_main_message("Resetting bot secret number #$cnum", 'red');
            $save = true;
        }

        if (!isset($cpref_file->playrand) || $cpref_file->playrand == null) {
            $cpref_file->playrand = mt_rand(10, 20) / 10.0; //between 1.0 and 2.0
            log_main_message("Resetting Play #$cnum", 'red');
            $save = true;
        }

        if (!isset($cpref_file->price_tolerance) || $cpref_file->price_tolerance == 1.00) {
            $cpref_file->price_tolerance = round(Math::purebell(0.5, 1.5, 0.1, 0.01), 3); //50% to 150%, 10% std dev, steps of 1%
            $save                   = true;
        } elseif ($cpref_file->price_tolerance != round($cpref_file->price_tolerance, 3)) {
            $cpref_file->price_tolerance = round($cpref_file->price_tolerance, 3); //round off silly numbers...
            $save                   = true;
        }

        if (!isset($cpref_file->nextplay) || !isset($cpref_file->lastplay) || $cpref_file->lastplay < time() - $server->turn_rate * $rules->maxturns) { //maxturns
            $cpref_file->nextplay = 0;
            log_main_message("Resetting Next Play for #$cnum", 'red');
            $save = true;
        }

        if (!isset($cpref_file->lastTurns)) {
            $cpref_file->lastTurns = 0;
        }

        if (!isset($cpref_file->turnStored)) {
            $cpref_file->turnStored = 0;
        }

        if (!isset($cpref_file->allyup) || $cpref_file->allyup == null) {
            $cpref_file->allyup = (bool)(rand(0, 9) > 0);
        }

        if (!isset($cpref_file->gdi)) {
            $cpref_file->gdi = (bool)(rand(0, 2) == 2);
            //log_main_message("Setting GDI to ".($cpref_file->gdi ? "true" : "false"), true, 'brown');
        }

        if ($cpref_file->nextplay < time()) {
            $cpref = new cpref($server, $cpref_file, $cnum);

            log_country_message($cnum, "\n\n");
            log_country_message($cnum, "Beginning country loop");
            log_main_message("Attempting to play turns for #$cnum");

            $earliest_destock_time = $cpref->earliest_destocking_start_time;
            log_country_message($cnum, 'Earliest possible destock time is: ' . log_translate_instant_to_human_readable($earliest_destock_time));
            
            $debug_force_destocking = false; // DEBUG: change to true to force destocking code to run
            $is_destocking = ($debug_force_destocking or time() >= $earliest_destock_time? true : false);

           // $is_destocking = false; // DEBUG

            // log snapshot of country status
            $prev_c_values = [];
            $init_c = get_advisor();
            log_snapshot_message($init_c, "BEGIN", $cpref_file->strat, $is_destocking, $prev_c_values);
            unset($init_c);

            try {
                if ($is_destocking) { // call special destocking code that passes back the next play time in $nexttime
                    log_main_message("Playing destocking ".Bots::txtStrat($cnum)." Turns for #$cnum ".siteURL($cnum));
                    log_country_message($cnum, 'Doing destocking actions' . log_translate_forced_debug($debug_force_destocking));
                    $exit_condition = "NORMAL";
                    $c = execute_destocking_actions($cnum, $cpref, $server, $rules, $nexttime, $exit_condition);
                    
                }
                else { // not destocking
                    log_country_message($cnum, 'Not doing destocking actions');    

                    if ($cpref_file->allyup) {
                        Allies::fill('def');
                    }

                    Events::new();
                    Country::listRetalsDue();

                    $nexttime = null;
                    $exit_condition = null;

                    log_main_message("Playing Standard ".Bots::txtStrat($cnum)." Turns for #$cnum ".siteURL($cnum));
                    log_country_message($cnum, "Begin playing standard ".Bots::txtStrat($cnum)." turns");

                    $turn_action_counts = [];
                    // FUTURE: careful with the $cnum global removal, maybe this breaks things? seems fine so far - 20210323
                    switch ($cpref_file->strat) {
                        case 'F':
                            $c = play_farmer_strat($server, $cnum, $rules, $cpref, $exit_condition, $turn_action_counts); 
                            break;
                        case 'T':
                            $c = play_techer_strat($server, $cnum, $rules, $cpref, $exit_condition, $turn_action_counts);
                            break;
                        case 'C':
                            $c = play_casher_strat($server, $cnum, $rules, $cpref, $exit_condition, $turn_action_counts);
                            break;
                        case 'I':
                            $c = play_indy_strat($server, $cnum, $rules, $cpref, $exit_condition, $turn_action_counts);
                            break;
                        default:
                            $c = play_rainbow_strat($server, $cnum, $rules, $cpref, $exit_condition);
                    }

                    log_turn_action_counts($c, $server, $cpref, $turn_action_counts);                    

                    if ($cpref_file->gdi && !$c->gdi) {
                        GDI::join();
                    } elseif (!$cpref_file->gdi && $c->gdi) {
                        GDI::leave();
                    }

                    if ($c->turns_played < 100 && $cpref_file->retal) {
                        $cpref_file->retal = []; //clear the damned retal thing
                    }

                    if($exit_condition == 'WAIT_FOR_PUBLIC_MARKET_FOOD') {
                        log_country_message($cnum, "Country is holding turns while waiting for cheaper public market food to appear");
                        $nexttime = 4 * $server->turn_rate;
                    }

                    // $maxin           = Bots::furthest_play($cpref_file); // now handled in calculate_next_play_in_seconds
                    // $nexttime        = round(min($maxin, $nexttime));
                }

                log_country_message($cnum, "End playing standard ".Bots::txtStrat($cnum)." turns with exit condition $exit_condition");

                // $seconds_to_next_play = calculate_next_play_in_seconds($cnum, $nexttime, $cpref_file->strat, $rules->is_clan_server, $rules->max_time_to_market, $rules->max_possible_market_sell, $cpref_file->playrand, $server->reset_start, $server->reset_end, $server->turn_rate, $cpref_file->lastTurns, $rules->maxturns, $cpref_file->turnsStored, $rules->maxstore);
                $seconds_to_next_play = $cpref->calculate_next_play_in_seconds($nexttime, $rules, $cpref_file);
                
                $cpref_file->lastplay = time();
                $cpref_file->nextplay = $cpref_file->lastplay + $seconds_to_next_play;
                $nextturns       = floor($seconds_to_next_play / $server->turn_rate);
                log_country_message($cnum, "This country next plays in $seconds_to_next_play seconds ($nextturns Turns) = ".log_translate_instant_to_human_readable(time() + $seconds_to_next_play));
                $played = true;
                $save   = true;

                /*
                // FUTURE: more delta checking, especially for destocking
                $prev_c_values = [];
                log_snapshot_message($c, "BEGIN", $cpref_file->strat, 0, false, $prev_c_values);
 
                $c = get_advisor();
                log_snapshot_message($c, "DELTA", $cpref_file->strat, 2, false, $dummy, $prev_c_values);
                */

                // log snapshots of country status
                // FUTURE: skip snapshot parameter for speed for remote play?
                $c = get_advisor(); // this call probably can't be avoided - market info, events, and tax/expenses could be wrong without it
                log_snapshot_message($c, "DELTA", $cpref_file->strat, $is_destocking, $dummy, $prev_c_values);
                log_snapshot_message($c, "END", $cpref_file->strat, $is_destocking, $dummy); // FUTURE: why does this take six seconds remotely?
                

            } catch (Exception $e) {
                log_main_message("Caught Exception: ".$e);
            }

            log_country_message($cnum, "Ending country loop");
            log_main_message("Finished playing turns for #$cnum with exit condition $exit_condition");
        }

        if ($save) {
            $settings->$cnum = $cpref_file;
            log_main_message("Saving Settings", 'purple');
            file_put_contents($config['save_settings_file'], json_encode($settings));
            log_main_message("\n");
        }

        // don't let bots play during the final minute to reduce server load and avoid market surprises for players
        if (time() + 60 >= $server->reset_end)
            break;
    }

    // don't let bots play during the final minute to reduce server load and avoid market surprises for players
    if (time() + 60 >= $server->reset_end) {
        log_main_message("Sleeping until end of reset!");
        log_main_message("\n");
        while($server->reset_end + 1 >= time()) {
            $end = $server->reset_end - time();
            // FUTURE: this doesn't print correctly like outNext() - why not?
            out("Sleep until end: ".$end, false); // keep as out() - the single line updates
            //log_main_message("\n");
            sleep(1);
        }
        log_main_message("Done Sleeping!");
        log_main_message("\n");
        $server = getServer(true);
    }    

    $cnum = null;
    $loopcount++;
    $sleepturns = 25;
    $sleep      = 1;
    //sleep 10s //min($sleepturns*$server->turn_rate,max(0,$server->reset_end - 60 - time())); //sleep for $sleepturns turns
    //$sleepturns = ($sleep != $sleepturns*$server->turn_rate ? floor($sleep/$server->turn_rate) : $sleepturns);
    //log_main_message("Played 'Day' $loopcount; Sleeping for " . $sleep . " seconds ($sleepturns Turns)");
    if ($played) {
        $sleepcount = 0;
    } else {
        $sleepcount++;
    }

    if ($sleepcount % 300 == 0) { // this code intentionally includes $sleepcount = 0, $sleepcount = 300, etc
        $server = getServer(true);
        //Bots::playstats($countries);
        //echo "\n";
    }

    sleep($sleep); //sleep for $sleep seconds
    Bots::outNext($countries, true, $played);
}

done(); //done() is defined below



function govtStats($countries)
{
    $cashers = $indies = $farmers = $techers = $oilers = $rainbows = 0;
    $undef   = 0;
    global $settings;
    $cNP = $fNP = $iNP = $tNP = $rNP = $oNP = 9999999;

    $govs = [];
    $tnw  = $tld = 0;
    foreach ($countries as $cnum) {
        if (!isset($settings->$cnum)) {
            continue;
        }

        $s = $settings->$cnum->strat;
        if (!isset($govs[$s])) {
            $govs[$s] = [Bots::txtStrat($cnum), 0, 999999, 0, 0];
        }

        if (!isset($settings->$cnum->networth) || !isset($settings->$cnum->land)) {
            update_stats($cnum);
        }

        //out_data($settings->$cnum);

        $govs[$s][1]++;
        $govs[$s][2]  = min($settings->$cnum->nextplay - time(), $govs[$s][2]);
        $govs[$s][3] += $settings->$cnum->networth;
        $govs[$s][4] += $settings->$cnum->land;
        $tnw         += $settings->$cnum->networth;
        $tld         += $settings->$cnum->land;
    }

    if ($tnw == 0) {
        return;
    }

    global $serv, $server_avg_land, $server_avg_networth;
    //log_main_message("TNW:$tnw; TLD: $tld");
    $server_avg_networth = $tnw / count($countries);
    $server_avg_land     = $tld / count($countries);

    $anw = ' [ANW:'.str_pad(round($server_avg_networth / 1000000, 2), 6, ' ', STR_PAD_LEFT).'M]';
    $ald = ' [ALnd:'.str_pad(round($server_avg_land / 1000, 2), 6, ' ', STR_PAD_LEFT).'k]';


    log_main_message("\033[1mServer:\033[0m ".$serv);
    log_main_message("\033[1mTotal Countries:\033[0m ".str_pad(count($countries), 9, ' ', STR_PAD_LEFT).$anw.$ald);
    foreach ($govs as $s => $gov) {
        if ($gov[1] > 0) {
            $next = ' [Next:'.str_pad($gov[2], 5, ' ', STR_PAD_LEFT).']';
            $anw  = ' [ANW:'.str_pad(round($gov[3] / $gov[1] / 1000000, 2), 6, ' ', STR_PAD_LEFT).'M]';
            $ald  = ' [ALnd:'.str_pad(round($gov[4] / $gov[1] / 1000, 2), 6, ' ', STR_PAD_LEFT).'k]';
            log_main_message(str_pad($gov[0], 18).': '.str_pad($gov[1], 4, ' ', STR_PAD_LEFT).$next.$anw.$ald);
        }
    }
}//end govtStats()


// FUTURE: move the update_c stuff to a new file
//Interaction with API
function update_c(&$c, $result)
{
    if (!isset($result->turns) || !$result->turns) {
        return;
    }
    $extrapad = 0;
    $numT     = 0;
    foreach ($result->turns as $z) {
        $numT++; //this is dumb, but count wasn't working????
    }

    $turn_action = null; // approximate turn action... won't handle weird stuff like building cs and other buildings at the same time

    global $lastFunction;
    //out_data($result);                //output data for testing
    $explain = null;                    //Text formatting
    if (isset($result->built)) {
        $str   = 'Built ';                //Text for screen
        $first = true;                  //Text formatting
        $bpt   = $tpt = false;
        foreach ($result->built as $type => $num) {     //for each type of building that we built....
            if (!$first) {                     //Text formatting
                $str .= ' and ';        //Text formatting
            }

            $first      = false;             //Text formatting
            $build      = 'b_'.$type;        //have to convert to the advisor output, for now
            $c->$build += $num;         //add buildings to keep track
            $c->empty  -= $num;          //subtract buildings from empty, to keep track
            $str       .= $num.' '.$type;     //Text for screen
            if ($type == 'cs' && $num > 0) {
                $bpt = true;
                $turn_action = 'cs';
            } elseif ($type == 'lab' && $num > 0) {
                $tpt = true;
                $turn_action = 'buildings';
            } elseif ($num > 0) {
                $turn_action = 'buildings';
            }

        }

        $explain = '('.$c->built().'%)';

        if ($bpt) {
            $explain = '('.$result->bpt.' bpt)';    //Text for screen
        }

        if ($tpt) {
            $str .= ' ('.$result->tpt.' tpt)';    //Text for screen
        }

        //update BPT - added this to the API so that we don't have to calculate it
        $c->bpt = $result->bpt;
        //update TPT - added this to the API so that we don't have to calculate it
        $c->tpt    = $result->tpt;
        $c->money -= $result->cost;
    } elseif (isset($result->new_land)) {
        $turn_action = 'explore';
        $c->empty       += $result->new_land;             //update empty land
        $c->land        += $result->new_land;              //update land
        $c->build_cost   = $result->build_cost;       //update Build Cost
        $c->explore_rate = $result->explore_rate;   //update explore rate
        $c->tpt          = $result->tpt;
        $str             = "Explored ".$result->new_land." Acres \033[1m(".$numT."T)\033[0m";
        $explain         = '('.$c->land.' A)';          //Text for screen
        $extrapad        = 8;
    } elseif (isset($result->teched)) {
        $turn_action = 'tech';
        $str = 'Tech: ';
        $tot = 0;
        foreach ($result->teched as $type => $num) {    //for each type of tech that we teched....
            $build      = 't_'.$type;      //have to convert to the advisor output, for now
            $c->$build += $num;             //add buildings to keep track
            $tot       += $num;   //Text for screen
        }

        $c->tpt  = $result->tpt;             //update TPT - added this to the API so that we don't have to calculate it
        $str    .= $tot.' '.actual_count($result->turns).' turns';
        $explain = '('.$c->tpt.' tpt)';     //Text for screen
    } elseif ($lastFunction == 'cash') {
        $turn_action = 'cash';
        $str = "Cashed ".actual_count($result->turns)." turns";     //Text for screen
    } elseif (isset($result->sell)) {
        $turn_action = 'sell';
        $str = "Put goods on Public Market";
    } elseif (isset($result->market_recalled_type)) {
        $turn_action = 'recall';
        $str = ($result->market_recalled_type == 'GOODS' ? 'Recalled goods' : 'Recalled tech');        
    }


    $event    = null; //Text for screen
    $netmoney = $netfood = 0;
    $advisor_update = $OOF = $OOM = false;
    $money_before_turns = $c->money; // for debugging
    $food_before_turns = $c->food; // for debugging
    $turns_played_before_turns = $c->turns_played;

    foreach ($result->turns as $num => $turn) {
        //update stuff based on what happened this turn
        $netfood  += floor($turn->foodproduced ?? 0) - ($turn->foodconsumed ?? 0);
        $netmoney += floor($turn->taxrevenue ?? 0) - ($turn->expenses ?? 0);

        //the turn doesn't *always* return these things, so have to check if they exist, and add 0 if they don't
        $c->pop   += floor($turn->popgrowth ?? 0);
        $c->m_tr  += floor($turn->troopsproduced ?? 0);
        $c->m_j   += floor($turn->jetsproduced ?? 0);
        $c->m_tu  += floor($turn->turretsproduced ?? 0);
        $c->m_ta  += floor($turn->tanksproduced ?? 0);
        $c->m_spy += floor($turn->spiesproduced ?? 0);
        $c->turns--;
        $c->turns_played++;

        //out_data($turn);

        // might need to override income if we got a certain type of event - see code below
        $latest_taxrevenue = $turn->taxrevenue;
        $latest_expenses = $turn->expenses;
        $latest_foodproduced = $turn->foodproduced;
        $latest_foodconsumed = $turn->foodconsumed;
        
        if (isset($turn->event)) {
            if ($turn->event == 'earthquake') {   //if an earthquake happens...
                log_country_message($c->cnum, "Earthquake destroyed {$turn->earthquake} Buildings! Update Advisor");
                $advisor_update = true; //update the advisor, because we no longer know what information is valid
            } elseif ($turn->event == 'pciboom') {
                //in the event of a pci boom, recalculate income so we don't react based on an event
                $latest_taxrevenue = floor(($turn->taxrevenue ?? 0) / 3);
                //$c->income = floor(($turn->taxrevenue ?? 0) / 3) - ($turn->expenses ?? 0);
            } elseif ($turn->event == 'pcibad') {
                //in the event of a pci bad, recalculate income so we don't react based on an event
                $latest_taxrevenue = floor($turn->taxrevenue * 3 ?? 0);
                // $c->income = floor(($turn->taxrevenue * 3 ?? 0) - ($turn->expenses ?? 0);
            } elseif ($turn->event == 'foodboom') {
                //in the event of a food boom, recalculate netfood so we don't react based on an event
                $latest_foodproduced = floor(($turn->foodproduced ?? 0) / 3);
                //$c->foodnet = floor(($turn->foodproduced ?? 0) / 3) - ($turn->foodconsumed ?? 0);
            } elseif ($turn->event == 'foodbad') {
                //in the event of a food boom, recalculate netfood so we don't react based on an event
                $latest_foodproduced = floor($turn->foodproduced * 3 ?? 0);
                //$c->foodnet = floor($turn->foodproduced * 3 ?? 0) - ($turn->foodconsumed ?? 0);
            }

            $event .= event_text($turn->event).' ';//Text for screen
        }

        if (isset($turn->cmproduced)) {//a CM was produced
            $event .= 'CM '; //Text for screen
        }

        if (isset($turn->nmproduced)) {//an NM was produced
            $event .= 'NM '; //Text for screen
        }

        if (isset($turn->emproduced)) {//an EM was produced
            $event .= 'EM '; //Text for screen
        }

        if (isset($turn->outoffood)) {
            $OOF = true;
            $event .= 'OOF '; 
            $advisor_update = true;
        }

        if (isset($turn->outofmoney)) {
            $OOM = true;
            $event .= 'OOM ';
            $advisor_update = true;
        }
    }

    // always update these to make country management simpler
    // for example, expenses rise every turn for an indy so it's good to have the latest info
    $c->taxes = $latest_taxrevenue;
    $c->expenses = $latest_expenses;
    $c->income = $latest_taxrevenue - $latest_expenses;
    //$c->cashing = floor(1.2 * $latest_taxrevenue - $latest_expenses);
    $c->foodpro = $latest_foodproduced;
    $c->foodcon = $latest_foodconsumed;
    $c->foodnet = $latest_foodproduced - $latest_foodconsumed;

    $c->money += $netmoney;
    $c->food  += $netfood;


    // check that there aren't other reasons to update the advisor
    // current example is recall goods and recall tech don't tell us what was sent back
    if(isset($result->force_advisor_update)) { 
        if($result->force_advisor_update) {
            if(isset($result->market_recalled_type))            
                log_country_message($c->cnum, "Update Advisor for market recall of goods or tech");
            else
                log_error_message(119, $c->cnum, "Update Advisor for unknown reason");
            $advisor_update = true;
        }
    }

    // need to track this to log the right error message if we ran out of food or money
    // it's worse to run out food or money when the object thinks that we didn't
    if($OOM or $OOF) {
        $did_c_object_think_money_was_non_negative_before_update = $c->money >= 0 ? true: false;
        $did_c_object_think_food_was_non_negative_before_update = $c->food >= 0 ? true: false;
        $OOM_error_message = "Before playing $numT turns, money was $money_before_turns. Before advisor update, money was $c->money, taxes were $c->taxes, and exp were $c->expenses. ";
        $OOF_error_message = "Before playing $numT turns, food was $food_before_turns. Before advisor update, food was $c->food, pro was $c->foodpro, and con was $c->foodnet. ";
    }

    // update advisor if we got an earthquake, ran out of food, or ran out of money
    if ($advisor_update == true) {
        $c = get_advisor();
    }

    //Text formatting (adding a + if it is positive; - will be there if it's negative already)
    $netfood  = str_pad('('.($netfood > 0 ? '+' : null).engnot($netfood).')', 11, ' ', STR_PAD_LEFT);
    $netmoney = str_pad('($'.($netmoney > 0 ? '+' : null).engnot($netmoney).')', 14, ' ', STR_PAD_LEFT);

    $str  = str_pad($str, 26 + $extrapad).str_pad($explain, 12).str_pad('$'.engnot($c->money), 16, ' ', STR_PAD_LEFT);
    $str .= $netmoney.str_pad(engnot($c->food).' Bu', 14, ' ', STR_PAD_LEFT).engnot($netfood);

    global $APICalls;
    $str = str_pad($c->turns, 3).' Turns - '.$str.' '.str_pad($event, 8).' API: '.$APICalls;
    if ($OOF || $OOM) {
        $str = Colors::getColoredString($str, "red");
    }

    log_country_message($c->cnum, $str);

    if($OOM) {
        $OOM_error_message .= "After advisor update, money was $c->money, taxes were $c->taxes, and exp were $c->expenses";
        $error_code = $did_c_object_think_money_was_non_negative_before_update ? 115 : 1001;
        log_error_message($error_code, $c->cnum, $OOM_error_message);
    }

    if($OOF) {
        $OOF_error_message .= "After advisor update, food is $c->food, pro is $c->foodpro, and con is $c->foodnet";
        $error_code = $did_c_object_think_food_was_non_negative_before_update ? 114 : 1000;
        log_error_message($error_code, $c->cnum, $OOF_error_message);
    }

    $APICalls = 0;

    // returning the number of turns played is useful for logging purposes
    return [$turn_action => $c->turns_played - $turns_played_before_turns];
}//end update_c()


function event_text($event)
{
    switch ($event) {
        case 'earthquake':
            return '--EQ--';
        case 'oilboom':
            return '+OIL';
        case 'oilfire':
            return '-oil';
        case 'foodboom':
            return '+FOOD';
        case 'foodbad':
            return '-food';
        case 'indyboom':
            return '+INDY';
        case 'indybad':
            return '-indy';
        case 'pciboom':
            return '+PCI';
        case 'pcibad':
            return '-pci';
        default:
            return null;
    }
}//end event_text()


function get_main()
{
    $main = ee('main');      //get and return the MAIN information

    global $cpref_file;
    $cpref_file->lastTurns   = $main->turns;
    $cpref_file->turnsStored = $main->turns_stored;

    return $main;
}//end get_main()


function get_rules()
{
    return ee('rules');      //get and return the RULES information
}//end get_rules()


function update_stats($number)
{
    global $settings, $cnum;
    $cnum                      = $number;
    $advisor                   = ee('advisor');   //get and return the ADVISOR information

    $settings->$cnum->networth = $advisor->networth;
    $settings->$cnum->land     = $advisor->land;
    return;
}//end update_stats()


function get_advisor()
{
    $advisor = ee('advisor');   //get and return the ADVISOR information

    global $cpref_file;
    $cpref_file->lastTurns   = $advisor->turns;
    $cpref_file->turnsStored = $advisor->turns_stored;

    //out_data($advisor);
    return new Country($advisor);
}//end get_advisor()



/**
 * Exit
 * @param  string $str Final output String
 * @return exit
 */
function done($str = null)
{
    if ($str) {
        log_main_message($str);
    }

    log_main_message("Exiting\n\n");
    exit;
}//end done()
