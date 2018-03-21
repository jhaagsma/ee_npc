#!/usr/bin/php
<?php

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

out(Colors::getColoredString("Rainbow", "purple"));

out('STARTING UP BOT');// out() is defined below
date_default_timezone_set('GMT'); //SET THE TIMEZONE FIRST
error_reporting(E_ALL); //SET THE ERROR REPORTING TO REPORT EVERYTHING
out('Error Reporting and Timezone Set');
require_once 'config.php';
if (!isset($config)) {
    //ADD IN AUTOGENERATE CONFIG HERE?
    die("Config not included successfully! Do you have config.php set up properly?");
}

if (file_exists($config['save_settings_file'])) {
    out("Try to load saved settings");
    global $settings;
    $settings = json_decode(file_get_contents($config['save_settings_file']));
    out("Successfully loaded settings!");
} else {
    out("No Settings File Found");
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

define("RAINBOW", Colors::getColoredString("Rainbow", "purple"));
define("FARMER", Colors::getColoredString("Farmer", "cyan"));
define("TECHER", Colors::getColoredString("Techer", "brown"));
define("CASHER", Colors::getColoredString("Casher", "green"));
define("INDY", Colors::getColoredString("Indy", "yellow"));
define("OILER", Colors::getColoredString("Oiler", "red"));

$username     = $config['username'];    //<======== PUT IN YOUR USERNAME IN config.php
$aiKey        = $config['ai_key'];      //<======== PUT IN YOUR AI API KEY IN config.php
$baseURL      = $config['base_url'];    //<======== PUT IN THE BASE URL IN config.php
$serv         = isset($config['server']) ? $config['server'] : 'ai';
$cnum         = null;
$lastFunction = null;
$turnsleep    = isset($config['turnsleep']) ? $config['turnsleep'] : 500000;
$mktinfo      = null; //so we don't have to get it mkt data over and over again
$APICalls     = 0;

out('Current Unix Time: '.time());
out('Entering Infinite Loop');

$sleepcount = $loopcount = 0;
$played     = true;

$rules  = getRules();
$server = getServer();
//$market            = new PublicMarket();
$server_avg_networth = $server_avg_land = 0;

while (1) {
    if (!is_object($server)) {
        $server = getServer();
    }

    while ($server->alive_count < $server->countries_allowed) {
        out("Less countries than allowed! (".$server->alive_count.'/'.$server->countries_allowed.')');
        $send_data = ['cname' => NameGenerator::rand_name()];
        out("Making new country named '".$send_data['cname']."'");
        $cnum = ee('create', $send_data);
        out($send_data['cname'].' (#'.$cnum.') created!');
        $server = getServer();
        if ($server->reset_start > time()) {
            $timeleft      = $server->reset_start - time();
            $countriesleft = $server->countries_allowed - $server->alive_count;
            $sleeptime     = $timeleft / $countriesleft;
            out("Sleep for $sleeptime to spread countries out");
            sleep($sleeptime);
        }
    }



    if ($server->reset_start > time()) {
        out("Reset has not started!");          //done() is defined below
        sleep(max(300, time() - $server->reset_start));        //sleep until the reset starts
        continue;                               //return to the beginning of the loop
    } elseif ($server->reset_end < time()) {
        out("Reset is over!");
        sleep(300);                             //wait 5 mins, see if new one is created
        continue;                               //restart the loop
    }

    $countries = $server->cnum_list->alive;

    if ($played) {
        Bots::server_start_end_notification($server);
        Bots::playstats($countries);
        echo "\n";
    }

    $played = false;
    //out("Country Count: ".count($countries));
    foreach ($countries as $cnum) {
        Debug::off(); //reset for new country
        $save = false;
        if (!isset($settings->$cnum)) {
            $settings->$cnum = json_decode(
                json_encode(
                    [
                        'strat' => null,
                        'playfreq' => null,
                        'playrand' => null,
                        'lastplay' => 0,
                        'nextplay' => 0,
                        'price_tolerance' => 1.0,
                        'def' => 1.0,
                        'off' => 1.0,
                        'aggro' => 1.0,
                        'allyup' => null,
                        'gdi' => null,
                    ]
                )
            );
            out("Resetting Settings #$cnum", true, 'red');
            file_put_contents($config['save_settings_file'], json_encode($settings));
        }

        global $cpref;
        $cpref = $settings->$cnum;

        $mktinfo = null;

        if (!isset($cpref->strat) || $cpref->strat == null) {
            $cpref->strat = Bots::pickStrat($cnum);
            out("Resetting Strat #$cnum", true, 'red');
            $save = true;
        }

        if (!isset($cpref->playfreq) || $cpref->playfreq == null) {
            $cpref->playfreq = Math::purebell($server->turn_rate, $server->turn_rate * $rules->maxturns, $server->turn_rate * 20, $server->turn_rate);
            $cpref->playrand = mt_rand(10, 20) / 10.0; //between 1.0 and 2.0
            out("Resetting Play #$cnum", true, 'red');
            $save = true;
        }

        if (!isset($cpref->price_tolerance) || $cpref->price_tolerance == 1.00) {
            $cpref->price_tolerance = round(Math::purebell(0.5, 1.5, 0.1, 0.01), 3); //50% to 150%, 10% std dev, steps of 1%
            $save                   = true;
        } elseif ($cpref->price_tolerance != round($cpref->price_tolerance, 3)) {
            $cpref->price_tolerance = round($cpref->price_tolerance, 3); //round off silly numbers...
            $save                   = true;
        }

        if (!isset($cpref->nextplay) || !isset($cpref->lastplay) || $cpref->lastplay < time() - $server->turn_rate * $rules->maxturns) { //maxturns
            $cpref->nextplay = 0;
            out("Resetting Next #$cnum", true, 'red');
            $save = true;
        }

        if (!isset($cpref->lastTurns)) {
            $cpref->lastTurns = 0;
        }

        if (!isset($cpref->turnStored)) {
            $cpref->turnStored = 0;
        }

        if (!isset($cpref->allyup) || $cpref->allyup == null) {
            $cpref->allyup = (bool)(rand(0, 9) > 0);
        }

        if (!isset($cpref->gdi)) {
            $cpref->gdi = (bool)(rand(0, 2) == 2);
            //out("Setting GDI to ".($cpref->gdi ? "true" : "false"), true, 'brown');
        }

        if ($cpref->nextplay < time()) {
            if ($cpref->allyup) {
                Allies::fill('def');
            }

            $playfactor = 1;
            try {
                switch ($cpref->strat) {
                    case 'F':
                        $c = play_farmer_strat($server, $cnum);

                        $playfactor = 0.8;
                        break;
                    case 'T':
                        $c = play_techer_strat($server, $cnum);

                        $playfactor = 0.5;
                        break;
                    case 'C':
                        $c = play_casher_strat($server, $cnum);
                        break;
                    case 'I':
                        $c = play_indy_strat($server, $cnum);

                        $playfactor = 0.33;
                        break;
                    default:
                        $c = play_rainbow_strat($server, $cnum);
                }

                if ($cpref->gdi && !$c->gdi) {
                    GDI::join();
                } elseif (!$cpref->gdi && $c->gdi) {
                    GDI::leave();
                }

                $cpref->lastplay = time();
                $nexttime        = round($playfactor * $cpref->playfreq * Math::purebell(1 / $cpref->playrand, $cpref->playrand, 1, 0.1));
                $maxin           = Bots::furthest_play($cpref);
                $nexttime        = round(min($maxin, $nexttime));
                $cpref->nextplay = $cpref->lastplay + $nexttime;
                $nextturns       = floor($nexttime / $server->turn_rate);
                out("This country next plays in: $nexttime ($nextturns Turns)    ");
                $played = true;
                $save   = true;
            } catch (Exception $e) {
                out("Caught Exception: ".$e);
            }
        }

        if ($save) {
            $settings->$cnum = $cpref;
            out(Colors::getColoredString("Saving Settings", 'purple'));
            file_put_contents($config['save_settings_file'], json_encode($settings));
            echo "\n\n";
        }
    }

    $until_end = 50;
    if ($server->reset_end - $server->turn_rate * $until_end - time() < 0) {
        for ($i = 0; $i < 5; $i++) {
            foreach ($countries as $cnum) {
                $mktinfo = null;
                destock($server, $cnum);
            }
        }

        out("Sleep until end");
        sleep(($until_end + 1) * $server->turn_rate);     //don't let them fluff things up, sleep through end of reset
        $server = ee('server');
    }

    $cnum = null;
    $loopcount++;
    $sleepturns = 25;
    $sleep      = 1;
    //sleep 10s //min($sleepturns*$server->turn_rate,max(0,$server->reset_end - 60 - time())); //sleep for $sleepturns turns
    //$sleepturns = ($sleep != $sleepturns*$server->turn_rate ? floor($sleep/$server->turn_rate) : $sleepturns);
    //out("Played 'Day' $loopcount; Sleeping for " . $sleep . " seconds ($sleepturns Turns)");
    if ($played) {
        $sleepcount = 0;
    } else {
        $sleepcount++;
    }


    if ($sleepcount % 300 == 0) {
        $server = ee('server');
        //Bots::playstats($countries);
        //echo "\n";
    }


    sleep($sleep); //sleep for $sleep seconds
    Bots::outNext($countries, true);
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
        if (!isset($settings->$cnum->strat)) {
            out("Picking a new strat for #$cnum");
            $settings->$cnum->strat = Bots::pickStrat($cnum);
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

    global $serv, $server_avg_land, $server_avg_networth;
    //out("TNW:$tnw; TLD: $tld");
    $server_avg_networth = $tnw / count($countries);
    $server_avg_land     = $tld / count($countries);

    $anw = ' [ANW:'.str_pad(round($server_avg_networth / 1000000, 2), 6, ' ', STR_PAD_LEFT).'M]';
    $ald = ' [ALnd:'.str_pad(round($server_avg_land / 1000, 2), 6, ' ', STR_PAD_LEFT).'k]';


    out("\033[1mServer:\033[0m ".$serv);
    out("\033[1mTotal Countries:\033[0m ".str_pad(count($countries), 9, ' ', STR_PAD_LEFT).$anw.$ald);
    foreach ($govs as $s => $gov) {
        if ($gov[1] > 0) {
            $next = ' [Next:'.str_pad($gov[2], 5, ' ', STR_PAD_LEFT).']';
            $anw  = ' [ANW:'.str_pad(round($gov[3] / $gov[1] / 1000000, 2), 6, ' ', STR_PAD_LEFT).'M]';
            $ald  = ' [ALnd:'.str_pad(round($gov[4] / $gov[1] / 1000, 2), 6, ' ', STR_PAD_LEFT).'k]';
            out(str_pad($gov[0], 18).': '.str_pad($gov[1], 4, ' ', STR_PAD_LEFT).$next.$anw.$ald);
        }
    }
}//end govtStats()







//COUNTRY PLAYING STUFF
function onmarket($good = 'food', &$c = null)
{
    if ($c == null) {
        return out_data(debug_backtrace());
    }

    return $c->onMarket($good);
}//end onmarket()


function onmarket_value($good = null, &$c = null)
{
    global $mktinfo;
    if (!$mktinfo) {
        $mktinfo = get_owned_on_market_info();  //find out what we have on the market
    }

    //out_data($mktinfo);
    //exit;
    $value = 0;
    foreach ($mktinfo as $key => $goods) {
        //out_data($goods);
        if ($good != null && $goods->type == $good) {
            $value += $goods->quantity * $goods->price;
        } elseif ($good == null) {
            $value += $goods->quantity;
        }

        if ($c != null) {
            $c->onMarket($goods);
        }
    }

    return $value;
}//end onmarket_value()


function totaltech($c)
{
    return $c->t_mil + $c->t_med + $c->t_bus + $c->t_res + $c->t_agri + $c->t_war + $c->t_ms + $c->t_weap + $c->t_indy + $c->t_spy + $c->t_sdi;
}//end totaltech()


function total_military($c)
{
    return $c->m_spy + $c->m_tr + $c->m_j + $c->m_tu + $c->m_ta;    //total_military
}//end total_military()


function total_cansell_tech($c)
{
    if ($c->turns_played < 100) {
        return 0;
    }

    $cansell = 0;
    global $techlist;
    foreach ($techlist as $tech) {
        $cansell += can_sell_tech($c, $tech);
    }

    Debug::msg("CANSELL TECH: $cansell");
    return $cansell;
}//end total_cansell_tech()


function total_cansell_military($c)
{
    $cansell = 0;
    global $military_list;
    foreach ($military_list as $mil) {
        $cansell += can_sell_mil($c, $mil);
    }

    //out("CANSELL TECH: $cansell");
    return $cansell;
}//end total_cansell_military()



function can_sell_tech(&$c, $tech = 't_bus')
{
    $onmarket = $c->onMarket($tech);
    $tot      = $c->$tech + $onmarket;
    $sell     = floor($tot * 0.25) - $onmarket;
    //Debug::msg("Can Sell $tech: $sell; (At Home: {$c->$tech}; OnMarket: $onmarket)");

    return $sell > 10 ? $sell : 0;
}//end can_sell_tech()


function can_sell_mil(&$c, $mil = 'm_tr')
{
    $onmarket = $c->onMarket($mil);
    $tot      = $c->$mil + $onmarket;
    $sell     = floor($tot * ($c->govt == 'C' ? 0.25 * 1.35 : 0.25)) - $onmarket;

    return $sell > 5000 ? $sell : 0;
}//end can_sell_mil()



//Interaction with API
function update_c(&$c, $result)
{
    if (!isset($result->turns) || !$result->turns) {
        return;
    }
    $extrapad = 0;
    $numT = 0;
    foreach ($result->turns as $z) {
        $numT++; //this is dumb, but count wasn't working????
    }

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
            } elseif ($type == 'lab' && $num > 0) {
                $tpt = true;
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
        $c->bpt    = $result->bpt;
        //update TPT - added this to the API so that we don't have to calculate it
        $c->tpt    = $result->tpt;
        $c->money -= $result->cost;
    } elseif (isset($result->new_land)) {
        $c->empty       += $result->new_land;             //update empty land
        $c->land        += $result->new_land;              //update land
        $c->build_cost   = $result->build_cost;       //update Build Cost
        $c->explore_rate = $result->explore_rate;   //update explore rate
        $c->tpt          = $result->tpt;
        $str             = "Explored ".$result->new_land." Acres \033[1m(".$numT."T)\033[0m";
        $explain         = '('.$c->land.' A)';          //Text for screen
        $extrapad        = 8;
    } elseif (isset($result->teched)) {
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
        $str = "Cashed ".actual_count($result->turns)." turns";     //Text for screen
    } elseif (isset($result->sell)) {
        $str = "Put goods on Public Market";
    }

    $event    = null; //Text for screen
    $netmoney = $netfood = 0;
    foreach ($result->turns as $num => $turn) {
        //update stuff based on what happened this turn
        $netfood  += $c->foodnet  = floor($turn->foodproduced ?? 0) - ($turn->foodconsumed ?? 0);
        $netmoney += $c->income = floor($turn->taxrevenue ?? 0) - ($turn->expenses ?? 0);

        //the turn doesn't *always* return these things, so have to check if they exist, and add 0 if they don't
        $c->pop   += floor($turn->popgrowth ?? 0);
        $c->m_tr  += floor($turn->troopsproduced ?? 0);
        $c->m_j   += floor($turn->jetsproduced ?? 0);
        $c->m_tu  += floor($turn->turretsproduced ?? 0);
        $c->m_ta  += floor($turn->tanksproduced ?? 0);
        $c->m_spy += floor($turn->spiesproduced ?? 0);
        $c->turns--;

        //out_data($turn);

        $advisor_update = false;
        if (isset($turn->event)) {
            if ($turn->event == 'earthquake') {   //if an earthquake happens...
                out("Earthquake destroyed {$turn->earthquake} Buildings! Update Advisor"); //Text for screen

                //update the advisor, because we no longer know what infromation is valid
                $advisor_update = true;
            } elseif ($turn->event == 'pciboom') {
                //in the event of a pci boom, recalculate income so we don't react based on an event
                $c->income = floor(($turn->taxrevenue ?? 0) / 3) - ($turn->expenses ?? 0);
            } elseif ($turn->event == 'pcibad') {
                //in the event of a pci bad, recalculate income so we don't react based on an event
                $c->income = floor(($turn->taxrevenue ?? 0) / 3) - ($turn->expenses ?? 0);
            } elseif ($turn->event == 'foodboom') {
                //in the event of a food boom, recalculate netfood so we don't react based on an event
                $c->foodnet = floor(($turn->foodproduced ?? 0) / 3) - ($turn->foodconsumed ?? 0);
            } elseif ($turn->event == 'foodbad') {
                //in the event of a food boom, recalculate netfood so we don't react based on an event
                $c->foodnet = floor($turn->foodproduced * 3 ?? 0) - ($turn->foodconsumed ?? 0);
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
    }

    $c->money += $netmoney;
    $c->food  += $netfood;

    if ($advisor_update == true) {
        $c = get_advisor();
    }

    //Text formatting (adding a + if it is positive; - will be there if it's negative already)
    $netfood  = str_pad('('.($netfood > 0 ? '+' : null).engnot($netfood).')', 11, ' ', STR_PAD_LEFT);
    $netmoney = str_pad('($'.($netmoney > 0 ? '+' : null).engnot($netmoney).')', 14, ' ', STR_PAD_LEFT);

    $str  = str_pad($str, 26 + $extrapad).str_pad($explain, 12).str_pad('$'.engnot($c->money), 16, ' ', STR_PAD_LEFT);
    $str .= $netmoney.str_pad(engnot($c->food).' Bu', 14, ' ', STR_PAD_LEFT).engnot($netfood); //Text for screen

    global $APICalls;
    $str = str_pad($c->turns, 3).' Turns - '.$str.' '.str_pad($event, 8).' API: '.$APICalls;
    if ($c->money < 0 || $c->food < 0) {
        $str = Colors::getColoredString($str, "red");
    }

    out($str);
    $APICalls = 0;
}//end update_c()


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


function build_cs($turns = 1)
{
                            //default is 1 CS if not provided
    return build(['cs' => $turns]);
}//end build_cs()


function build($buildings = [])
{
                   //default is an empty array
    return ee('build', ['build' => $buildings]);    //build a particular set of buildings
}//end build()


function cash(&$c, $turns = 1)
{
                          //this means 1 is the default number of turns if not provided
    return ee('cash', ['turns' => $turns]);             //cash a certain number of turns
}//end cash()


function explore(&$c, $turns = 1)
{
    if ($c->empty > $c->land / 2) {
        $b = $c->built();
        out("We can't explore (Built: {$b}%), what are we doing?");
        return;
    }

    //this means 1 is the default number of turns if not provided
    $result = ee('explore', ['turns' => $turns]);      //cash a certain number of turns
    if ($result === false) {
        out('Explore Fail? Update Advisor');
        $c = get_advisor();
    }

    return $result;
}//end explore()


function tech($tech = [])
{
                     //default is an empty array
    return ee('tech', ['tech' => $tech]);   //research a particular set of techs
}//end tech()


function get_main()
{
    $main = ee('main');      //get and return the MAIN information

    global $cpref;
    $cpref->lastTurns   = $main->turns;
    $cpref->turnsStored = $main->turns_stored;

    return $main;
}//end get_main()


function get_rules()
{
    return ee('rules');      //get and return the RULES information
}//end get_rules()


function set_indy(&$c)
{
    return ee(
        'indy',
        ['pro' => [
                'pro_spy' => $c->pro_spy,
                'pro_tr' => $c->pro_tr,
                'pro_j' => $c->pro_j,
                'pro_tu' => $c->pro_tu,
                'pro_ta' => $c->pro_ta,
            ]
        ]
    );      //set industrial production
}//end set_indy()


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

    global $cpref;
    $cpref->lastTurns   = $advisor->turns;
    $cpref->turnsStored = $advisor->turns_stored;

    //out_data($advisor);
    return new Country($advisor);
}//end get_advisor()


function get_pm_info()
{
    return ee('pm_info');   //get and return the PRIVATE MARKET information
}//end get_pm_info()


function get_market_info()
{
    return ee('market');    //get and return the PUBLIC MARKET information
}//end get_market_info()


function get_owned_on_market_info()
{
    $goods = ee('onmarket');    //get and return the GOODS OWNED ON PUBLIC MARKET information
    return $goods->goods;
}//end get_owned_on_market_info()


function change_govt(&$c, $govt)
{
    $result = ee('govt', ['govt' => $govt]);
    if (isset($result->govt)) {
        out("Govt switched to {$result->govt}!");
        $c = get_advisor();     //UPDATE EVERYTHING
    }

    return $result;
}//end change_govt()





/**
 * Exit
 * @param  string $str Final output String
 * @return exit
 */
function done($str = null)
{
    if ($str) {
        out($str);
    }

    out("Exiting\n\n");
    exit;
}//end done()
