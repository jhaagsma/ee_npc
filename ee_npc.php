#!/usr/bin/php
<?php namespace EENPC;

include_once('communication.php');
out('STARTING UP BOT');// out() is defined below
date_default_timezone_set('GMT'); //SET THE TIMEZONE FIRST
error_reporting(E_ALL); //SET THE ERROR REPORTING TO REPORT EVERYTHING
out('Error Reporting and Timezone Set');
include_once('config.php');
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

include_once('country_functions.php');
include_once('Country.class.php');
include_once('PublicMarket.class.php');

include_once('rainbow_strat.php');
include_once('farmer_strat.php');
include_once('techer_strat.php');
include_once('casher_strat.php');
include_once('indy_strat.php');
include_once('oiler_strat.php');

define("RAINBOW", $colors->getColoredString("Rainbow", "purple"));
define("FARMER", $colors->getColoredString("Farmer", "cyan"));
define("TECHER", $colors->getColoredString("Techer", "brown"));
define("CASHER", $colors->getColoredString("Casher", "green"));
define("INDY", $colors->getColoredString("Indy", "yellow"));
define("OILER", $colors->getColoredString("Oiler", "red"));

$username = $config['username'];    //<======== PUT IN YOUR USERNAME IN config.php
$aiKey = $config['ai_key'];        //<======== PUT IN YOUR AI API KEY IN config.php
$baseURL = $config['base_url'];    //<======== PUT IN THE BASE URL IN config.php
$serv = isset($config['server']) ? $config['server'] : 'ai';
$cnum = null;
$lastFunction = null;
$turnsleep = isset($config['turnsleep']) ? $config['turnsleep'] : 500000;
$mktinfo = null; //so we don't have to get it mkt data over and over again
$APICalls = 0;

out('Current Unix Time: '.time());
out('Entering Infinite Loop');
$sleepcount = $loopcount = 0;
$played = true;

$rules = ee('rules');
$market = new PublicMarket();

$server = ee('server');
while (1) {
    while ($server->alive_count < $server->countries_allowed) {
        out("Less countries than allowed! (".$server->alive_count.'/'.$server->countries_allowed.')');
        include_once('name_generator.php');
        $send_data = array('cname' => rand_name());
        out("Making new country named '".$send_data['cname']."'");
        $cnum = ee('create', $send_data);
        out($send_data['cname'].' (#'.$cnum.') created!');
        $server = ee('server');
        if ($server->reset_start > time()) {
            $timeleft = $server->reset_start - time();
            $countriesleft = $server->countries_allowed - $server->alive_count;
            $sleeptime = $timeleft/$countriesleft;
            out("Sleep for $sleeptime to spread countries out");
            sleep($sleeptime);
        }
    }


    if ($server->reset_start > time()) {
        out("Reset has not started!");          //done() is defined below
        sleep(max(300, time()-$server->reset_start));        //sleep until the reset starts
        continue;                               //return to the beginning of the loop
    } elseif ($server->reset_end < time()) {
        out("Reset is over!");
        sleep(300);                             //wait 5 mins, see if new one is created
        continue;                               //restart the loop
    }

    $countries = $server->cnum_list->alive;

    if ($played) {
        server_start_end_notification($server);
        playstats($countries);
        echo "\n";
    }

    $played = false;
    //out("Country Count: ".count($countries));
    foreach ($countries as $cnum) {
        $save = false;
        if (!isset($settings->$cnum)) {
            $settings->$cnum = json_decode(json_encode(array(
                'strat' => null,
                'playfreq' => null,
                'playrand' => null,
                'lastplay' => 0,
                'nextplay' => 0,
                'price_tolerance' => 1.0,
                'def' => 1.0,
                'off' => 1.0,
                'aggro' => 1.0
            )));
            out($colors->getColoredString("Resetting Settings #$cnum", 'red'));
            file_put_contents($config['save_settings_file'], json_encode($settings));
        }
        global $cpref;
        $cpref = $settings->$cnum;

        $mktinfo = null;

        if (!isset($cpref->strat) || $cpref->strat == null) {
            $cpref->strat = pickStrat($cnum);
            out($colors->getColoredString("Resetting Strat #$cnum", 'red'));
            $save = true;
        }
        if (!isset($cpref->playfreq) || $cpref->playfreq == null) {
            $cpref->playfreq = purebell($server->turn_rate, $server->turn_rate*$rules->maxturns, $server->turn_rate*20, $server->turn_rate);
            $cpref->playrand = mt_rand(10, 20)/10.0; //between 1.0 and 2.0
            out($colors->getColoredString("Resetting Play #$cnum", 'red'));
            $save = true;
        }

        if (!isset($cpref->price_tolerance) || $cpref->price_tolerance == 1.00) {
            $cpref->price_tolerance = round(purebell(0.5, 1.5, 0.1, 0.01), 3); //50% to 150%, 10% std dev, steps of 1%
            $save = true;
        } elseif ($cpref->price_tolerance != round($cpref->price_tolerance, 3)) {
            $cpref->price_tolerance = round($cpref->price_tolerance, 3); //round off silly numbers...
            $save = true;
        }

        if (!isset($cpref->nextplay) || !isset($cpref->lastplay) || $cpref->lastplay < time() - $server->turn_rate*$rules->maxturns) { //maxturns
            $cpref->nextplay = 0;
            out($colors->getColoredString("Resetting Next #$cnum", 'red'));
            $save = true;
        }

        if (!isset($cpref->lastTurns)) {
            $cpref->lastTurns = 0;
        }

        if (!isset($cpref->turnStored)) {
            $cpref->turnStored = 0;
        }

        if ($cpref->nextplay < time()) {
            $playfactor = 1;
            switch ($cpref->strat) {
                case 'F':
                    play_farmer_strat($server, $cnum);
                    break;
                case 'T':
                    play_techer_strat($server, $cnum);
                    $playfactor = 0.5;
                    break;
                case 'C':
                    play_casher_strat($server, $cnum);
                    break;
                case 'I':
                    play_indy_strat($server, $cnum);
                    $playfactor = 0.5;
                    break;
                default:
                    play_rainbow_strat($server, $cnum);
            }
            $cpref->lastplay = time();
            $nexttime = $cpref->playfreq*purebell(1/$cpref->playrand, $cpref->playrand, 1, 0.1);
            $maxin = furthest_play($cpref);
            $nexttime = min($maxin, $nexttime);
            $cpref->nextplay = $cpref->lastplay + $nexttime;
            out("This country next plays in: $nexttime     ");
            $played = true;
            $save = true;
        }

        if ($save) {
            $settings->$cnum = $cpref;
            out($colors->getColoredString("Saving Settings", 'purple'));
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
        sleep(($until_end + 1)*$server->turn_rate);     //don't let them fluff things up, sleep through end of reset
        $server = ee('server');
    }
    $cnum = null;
    $loopcount++;
    $sleepturns = 25;
    $sleep = 1;
    //sleep 10s //min($sleepturns*$server->turn_rate,max(0,$server->reset_end - 60 - time())); //sleep for $sleepturns turns
    //$sleepturns = ($sleep != $sleepturns*$server->turn_rate ? floor($sleep/$server->turn_rate) : $sleepturns);
    //out("Played 'Day' $loopcount; Sleeping for " . $sleep . " seconds ($sleepturns Turns)");
    if ($played) {
        $sleepcount = 0;
    } else {
        $sleepcount++;
    }


    if ($sleepcount%300 == 0) {
        $server = ee('server');
        //playstats($countries);
        //echo "\n";
    }


    sleep($sleep); //sleep for $sleep seconds
    outNext($countries, true);
}
done(); //done() is defined below

function furthest_play($cpref)
{
    global $server, $rules;
    $max = $rules->maxturns + $rules->maxstore;
    $held = $cpref->lastTurns + $cpref->turnsStored;
    $diff = $max - $held;
    $maxin = floor($diff*$server->turn_rate);
    out('Country is holding '.$held.'. Turns will max in '.$maxin);
    return $maxin;
}

function server_start_end_notification($server)
{
    $start = round((time()-$server->reset_start)/3600, 1).' hours ago';
    $x = floor((time()-$server->reset_start)/$server->turn_rate);
    $start .= " ($x turns)";
    $end = round(($server->reset_end-time())/3600, 1).' hours';
    $x = floor(($server->reset_end-time())/$server->turn_rate);
    $end .= " ($x turns)";
    out("Server started ".$start.' and ends in '.$end);
}

function pickStrat($cnum)
{
    if ($cnum%5 == 1) {
        return 'F';
    } elseif ($cnum%5 == 2) {
        return 'T';
    } elseif ($cnum%5 == 3) {
        return 'C';
    } elseif ($cnum%5 == 4) {
        return 'I';
    } else {
        return 'R';
    }
}

function playstats($countries)
{
    govtStats($countries);

    global $server;
    $stddev = round(playtimes_stddev($countries));
    out("Standard Deviation of play is: $stddev; (".round($stddev/$server->turn_rate).' turns)');
    if ($stddev < $server->turn_rate*72/4 || $stddev > $server->turn_rate*72) {
        out('Recalculating Nextplays');
        global $settings;
        foreach ($countries as $cnum) {
            $settings->$cnum->nextplay = time() + rand(0, $server->turn_rate*72);
        }

        $stddev = round(playtimes_stddev($countries));
        out("Standard Deviation of play is: $stddev");
    }

    outOldest($countries);
    outFurthest($countries);
    //outNext($countries);
}

function outOldest($countries)
{
    global $server;
    $old = oldestPlay($countries);
    $onum = getLastPlayCNUM($countries, $old);
    $ostrat = txtStrat($onum);
    $old = time() - $old;
    out("Oldest Play: ".$old."s ago by #$onum $ostrat (".round($old/$server->turn_rate)." turns)");
    if ($old > 86400 * 2) {
        out("OLD TOO FAR: RESET NEXTPLAY");
        global $settings;
        $settings->$onum->nextplay = 0;
    }
}

function outFurthest($countries)
{
    global $server;
    $furthest = getFurthestNext($countries);
    $fnum = getNextPlayCNUM($countries, $furthest);
    $fstrat = txtStrat($fnum);
    $furthest = $furthest - time();
    out("Furthest Play in ".$furthest."s for #$fnum $fstrat (".round($furthest/$server->turn_rate)." turns)");
}

function outNext($countries, $rewrite = false)
{
    $next = getNextPlays($countries);
    $xnum = getNextPlayCNUM($countries, min($next));
    $xstrat = txtStrat($xnum);
    $next = max(0, min($next) - time());
    out("Next Play in ".$next.'s: #'.$xnum." ($xstrat)    ".($rewrite ? "\r" : null), !$rewrite);
}

function txtStrat($cnum)
{
    global $settings;
    if (!isset($settings->$cnum->strat)) {
        return;
    }

    switch ($settings->$cnum->strat) {
        case 'C':
            return CASHER;
        case 'F':
            return FARMER;
        case 'I':
            return INDY;
        case 'T':
            return TECHER;
            break;
        case 'R':
            return RAINBOW;
        case 'O':
            return OILER;
    }
}

function govtStats($countries)
{
    $cashers = $indies = $farmers = $techers = $oilers = $rainbows = 0;
    $undef = 0;
    global $settings;
    $cNP = $fNP = $iNP = $tNP = $rNP = $oNP = 9999999;

    $govs = [];
    foreach ($countries as $cnum) {
        if (!isset($settings->$cnum->strat)) {
            out("Picking a new strat for #$cnum");
            $settings->$cnum->strat = pickStrat($cnum);
        }
        $s = $settings->$cnum->strat;
        if (!isset($govs[$s])) {
            $govs[$s] = [null, 0, 999999, 0, 0];
        }
        switch ($s) {
            case 'C':
                $govs[$s][0] = CASHER;
                break;
            case 'F':
                $govs[$s][0] = FARMER;
                break;
            case 'I':
                $indies++;
                $govs[$s][0] = INDY;
                break;
            case 'T':
                $govs[$s][0] = TECHER;
                break;
            case 'R':
                $govs[$s][0] = RAINBOW;
                break;
            case 'O':
                $govs[$s][0] = OILER;
                break;
        }
        $govs[$s][1]++;
        $govs[$s][2] = min($settings->$cnum->nextplay-time(), $govs[$s][2]);
        $govs[$s][3] += isset($settings->$cnum->networth) ? $settings->$cnum->networth : 0;
        $govs[$s][4] += isset($settings->$cnum->land) ? $settings->$cnum->land : 0;
    }

    global $serv;
    out("\033[1mServer:\033[0m ".$serv);
    out("\033[1mTotal Countries:\033[0m ".count($countries));
    foreach ($govs as $s => $gov) {
        if ($gov[1] > 0) {
            $next = ' [Next:'.str_pad($gov[2], 5, ' ', STR_PAD_LEFT).']';
            $anw = ' [ANW:'.str_pad(round($gov[3]/1000000, 2), 4, ' ', STR_PAD_LEFT).'M]';
            $ald = ' [ALnd:'.str_pad(round($gov[4]/1000, 2), 4, ' ', STR_PAD_LEFT).'k]';
            out(str_pad($gov[0], 18).': '.$gov[1].$next.$anw.$ald);
        }
    }
}

function getNextPlayCNUM($countries, $time = 0)
{
    global $settings;
    foreach ($countries as $cnum) {
        if (isset($settings->$cnum->nextplay) && $settings->$cnum->nextplay == $time) {
            return $cnum;
        }
    }
    return null;
}

function getLastPlayCNUM($countries, $time = 0)
{
    global $settings;
    foreach ($countries as $cnum) {
        if (isset($settings->$cnum->lastplay) && $settings->$cnum->lastplay == $time) {
            return $cnum;
        }
    }
    return null;
}

function getNextPlays($countries)
{
    global $settings;
    $nextplays = array();
    foreach ($countries as $cnum) {
        if (isset($settings->$cnum->nextplay)) {
            $nextplays[] = $settings->$cnum->nextplay;
        } else {
            $settings->$cnum->nextplay = 0; //set it?
        }
    }
    return $nextplays;
}

function getFurthestNext($countries)
{
    return max(getNextPlays($countries));
}

function playtimes_stddev($countries)
{
    $nextplays = getNextPlays($countries);
    return sd($nextplays);
}

function lastPlays($countries)
{
    global $settings;
    $lastplays = array();
    foreach ($countries as $cnum) {
        if (isset($settings->$cnum->lastplay)) {
            $lastplays[] = $settings->$cnum->lastplay;
        } else {
            $settings->$cnum->lastplay = 0; //set it?
        }
    }
    return $lastplays;
}

function oldestPlay($countries)
{
    return min(lastPlays($countries));
}

function sd($array)
{
    if (!$array) {
        return 0;
    }
    // square root of sum of squares devided by N-1
    //frikkin namespaces making my life difficult
    return sqrt(
        array_sum(
            array_map(
                // ANONYMOUS Function to calculate square of value - mean
                function ($x, $mean) {
                    return pow($x - $mean, 2);
                },
                $array,
                array_fill(0, count($array), (array_sum($array) / count($array)))
            )
        ) / (count($array)-1)
    );
}



//COUNTRY PLAYING STUFF
function onmarket($good = 'food')
{
    global $mktinfo;
    if (!$mktinfo) {
        $mktinfo = get_owned_on_market_info();  //find out what we have on the market
    }
    //out_data($mktinfo);
    //exit;
    $total = 0;
    foreach ($mktinfo as $key => $goods) {
        //out_data($goods);
        if ($good != null && $goods->type == $good) {
            $total += $goods->quantity;
        } elseif ($good == null) {
            $total += $goods->quantity;
        }
    }
    return $total;
}

function onmarket_value($good = null)
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
            $value += $goods->quantity*$goods->price;
        } elseif ($good == null) {
            $value += $goods->quantity;
        }
    }
    return $value;
}

function totaltech($c)
{
    return $c->t_mil + $c->t_med + $c->t_bus + $c->t_res + $c->t_agri + $c->t_war + $c->t_ms + $c->t_weap + $c->t_indy + $c->t_spy + $c->t_sdi;
}

function total_military($c)
{
    return $c->m_spy+$c->m_tr+$c->m_j+$c->m_tu+$c->m_ta;    //total_military
}

function total_cansell_tech($c)
{
    $cansell = 0;
    global $techlist;
    foreach ($techlist as $tech) {
        $cansell += can_sell_tech($c, $tech);
    }

    //out("CANSELL TECH: $cansell");
    return $cansell;
}

function total_cansell_military($c)
{
    $cansell = 0;
    global $military_list;
    foreach ($military_list as $mil) {
        $cansell += can_sell_mil($c, $mil);
    }

    //out("CANSELL TECH: $cansell");
    return $cansell;
}


function can_sell_tech(&$c, $tech = 't_bus')
{
    $onmarket = onmarket($tech);
    $tot = $c->$tech + $onmarket;
    $sell = floor($tot*0.25) - $onmarket;

    return $sell > 10 ? $sell : 0;
}

function can_sell_mil(&$c, $mil = 'm_tr')
{
    $onmarket = onmarket($mil);
    $tot = $c->$mil + $onmarket;
    $sell = floor($tot*($c->govt == 'C' ? 0.25*1.35 : 0.25)) - $onmarket;

    return $sell > 5000 ? $sell : 0;
}


//Interaction with API
function update_c(&$c, $result)
{
    if (!isset($result->turns) || !$result->turns) {
        return;
    }
    $numT = 0;
    foreach ($result->turns as $t) {
        $numT++; //this is dumb, but count wasn't working????
    }

    global $lastFunction;
    //out_data($result);				//output data for testing
    $explain = null;                    //Text formatting
    if (isset($result->built)) {
        $str = 'Built ';                //Text for screen
        $first = true;                  //Text formatting
        $bpt = $tpt = false;
        foreach ($result->built as $type => $num) {     //for each type of building that we built....
            if (!$first) {                     //Text formatting
                $str .= ' and ';        //Text formatting
            }
            $first = false;             //Text formatting
            $build = 'b_'.$type;        //have to convert to the advisor output, for now
            $c->$build += $num;         //add buildings to keep track
            $c->empty -= $num;          //subtract buildings from empty, to keep track
            $str .= $num.' '.$type;     //Text for screen
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

        $c->bpt = $result->bpt;             //update BPT - added this to the API so that we don't have to calculate it
        $c->tpt = $result->tpt;             //update TPT - added this to the API so that we don't have to calculate it
        $c->money -= $result->cost;
    } elseif (isset($result->new_land)) {
        $c->empty += $result->new_land;             //update empty land
        $c->land += $result->new_land;              //update land
        $c->build_cost = $result->build_cost;       //update Build Cost
        $c->explore_rate = $result->explore_rate;   //update explore rate
        $c->tpt = $result->tpt;                     //update TPT - added this to the API so that we don't have to calculate it
        $str = "Explored ".$result->new_land." Acres (".$numT.'T)';  //Text for screen
        $explain = '('.$c->land.' A)';          //Text for screen
    } elseif (isset($result->teched)) {
        $str = 'Tech: ';
        $tot = 0;
        foreach ($result->teched as $type => $num) {    //for each type of tech that we teched....
            $build = 't_'.$type;      //have to convert to the advisor output, for now
            $c->$build += $num;             //add buildings to keep track
            $tot += $num;   //Text for screen
        }
        $c->tpt = $result->tpt;             //update TPT - added this to the API so that we don't have to calculate it
        $str .=  $tot.' '.actual_count($result->turns).' turns';
        $explain = '('.$c->tpt.' tpt)';     //Text for screen
    } elseif ($lastFunction == 'cash') {
        $str = "Cashed ".actual_count($result->turns)." turns";     //Text for screen
    } elseif (isset($result->sell)) {
        $str = "Put goods on market";
    }

    $event = null; //Text for screen
    $netmoney = $netfood = 0;
    foreach ($result->turns as $num => $turn) {
        //update stuff based on what happened this turn
        $netfood    += $c->foodnet  = floor(isset($turn->foodproduced)  ? $turn->foodproduced : 0)  - (isset($turn->foodconsumed)   ? $turn->foodconsumed : 0);
        $netmoney   += $c->income   = floor(isset($turn->taxrevenue)    ? $turn->taxrevenue : 0)    - (isset($turn->expenses)       ? $turn->expenses : 0);

        //the turn doesn't *always* return these things, so have to check if they exist, and add 0 if they don't
        $c->pop     += floor(isset($turn->popgrowth)        ? $turn->popgrowth : 0);
        $c->m_tr    += floor(isset($turn->troopsproduced)   ? $turn->troopsproduced : 0);
        $c->m_j     += floor(isset($turn->jetsproduced)     ? $turn->jetsproduced : 0);
        $c->m_tu    += floor(isset($turn->turretsproduced)  ? $turn->turretsproduced : 0);
        $c->m_ta    += floor(isset($turn->tanksproduced)    ? $turn->tanksproduced : 0);
        $c->m_spy   += floor(isset($turn->spiesproduced)    ? $turn->spiesproduced : 0);
        $c->turns--;

        //out_data($turn);

        if (isset($turn->event)) {
            if ($turn->event == 'earthquake') {   //if an earthquake happens...
                out("Earthquake destroyed {$turn->earthquake} Buildings! Update Advisor"); //Text for screen

                //update the advisor, because we no longer know what infromation is valid
                $c = get_advisor();
            } elseif ($turn->event == 'pciboom') {       //in the event of a pci boom, recalculate income so we don't react based on an event
                $c->income = floor(isset($turn->taxrevenue)     ? $turn->taxrevenue/3 : 0)      - (isset($turn->expenses)       ? $turn->expenses : 0);
            } elseif ($turn->event == 'pcibad') {        //in the event of a pci bad, recalculate income so we don't react based on an event
                $c->income = floor(isset($turn->taxrevenue)     ? $turn->taxrevenue*3 : 0)      - (isset($turn->expenses)       ? $turn->expenses : 0);
            } elseif ($turn->event == 'foodboom') {      //in the event of a food boom, recalculate netfood so we don't react based on an event
                $c->foodnet = floor(isset($turn->foodproduced)  ? $turn->foodproduced/3 : 0)    - (isset($turn->foodconsumed)   ? $turn->foodconsumed : 0);
            } elseif ($turn->event == 'foodbad') {       //in the event of a food boom, recalculate netfood so we don't react based on an event
                $c->foodnet = floor(isset($turn->foodproduced)  ? $turn->foodproduced*3 : 0)    - (isset($turn->foodconsumed)   ? $turn->foodconsumed : 0);
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
    $c->food += $netfood;

    global $colors;
    //Text formatting (adding a + if it is positive; - will be there if it's negative already)
    $netfood = str_pad('('.($netfood > 0 ? '+' : null).$netfood.')', 10, ' ', STR_PAD_LEFT) ;
    $netmoney = str_pad('($'.($netmoney > 0 ? '+' : null).$netmoney.')', 12, ' ', STR_PAD_LEFT);

    $str = str_pad($str, 26).str_pad($explain, 12).str_pad('$'.$c->money, 16, ' ', STR_PAD_LEFT);
    $str .= $netmoney.str_pad($c->food.' Bu', 12, ' ', STR_PAD_LEFT).$netfood; //Text for screen

    global $APICalls;
    $str = str_pad($c->turns, 3).' Turns - '.$str.' '.str_pad($event, 5).' API: '.$APICalls;
    if ($c->money < 0 || $c->food < 0) {
        $str = $colors->getColoredString($str, "red");
    }
    out($str);
    $APICalls = 0;
}

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
}

function build_cs($turns = 1)
{
                            //default is 1 CS if not provided
    return build(array('cs' => $turns));
}

function build($buildings = array())
{
                   //default is an empty array
    return ee('build', array('build' => $buildings));    //build a particular set of buildings
}

function cash(&$c, $turns = 1)
{
                          //this means 1 is the default number of turns if not provided
    return ee('cash', array('turns' => $turns));             //cash a certain number of turns
}

function explore(&$c, $turns = 1)
{
                       //this means 1 is the default number of turns if not provided
    return ee('explore', array('turns' => $turns));      //cash a certain number of turns
}

function tech($tech = array())
{
                     //default is an empty array
    return ee('tech', array('tech' => $tech));   //research a particular set of techs
}

function get_main()
{
    $main = ee('main');      //get and return the MAIN information

    global $cpref;
    $cpref->lastTurns = $main->turns;
    $cpref->turnsStored = $main->turns_stored;

    return $main;
}

function get_rules()
{
    return ee('rules');      //get and return the RULES information
}

function set_indy(&$c)
{
    return ee(
        'indy',
        ['pro' => [
                'pro_spy'=>$c->pro_spy,
                'pro_tr'=>$c->pro_tr,
                'pro_j'=>$c->pro_j,
                'pro_tu'=>$c->pro_tu,
                'pro_ta'=>$c->pro_ta,
            ]
        ]
    );      //set industrial production
}


function get_advisor()
{
    $advisor = ee('advisor');   //get and return the ADVISOR information

    global $cpref;
    $cpref->lastTurns = $advisor->turns;
    $cpref->turnsStored = $advisor->turns_stored;

    //out_data($advisor);
    return new Country($advisor);
}

function get_pm_info()
{
    return ee('pm_info');   //get and return the PRIVATE MARKET information
}

function get_market_info()
{
    return ee('market');    //get and return the PUBLIC MARKET information
}

function get_owned_on_market_info()
{
    $goods = ee('onmarket');    //get and return the GOODS OWNED ON PUBLIC MARKET information
    return $goods->goods;
}

function change_govt(&$c, $govt)
{
    $result = ee('govt', array('govt' => $govt));
    if (isset($result->govt)) {
        out("Govt switched to {$result->govt}!");
        $c = get_advisor();     //UPDATE EVERYTHING
    }
    return $result;
}


function buy_on_pm(&$c, $units = array())
{
    $result = ee('pm', array('buy' => $units));
    if (!isset($result->cost)) {
        out("Failed to buy units on PM; money={$c->money}");
        out("UPDATE EVERYTHING");
        $c = get_advisor();     //UPDATE EVERYTHING
        out("refresh money={$c->money}");
        return $result;
    }

    $c->money -= $result->cost;
    $str = 'Bought ';
    foreach ($result->goods as $type => $amount) {
        if ($type == 'm_bu') {
            $type = 'food';
        } elseif ($type == 'm_oil') {
            $type = 'oil';
        }

        $c->$type += $amount;
        $str .= $amount.' '.$type.', ';
    }
    $str .= 'for $'.$result->cost.' on PM';
    out($str);
    return $result;
}


function sell_on_pm(&$c, $units = array())
{
    $result =  ee('pm', array('sell' => $units));
    $c->money += $result->money;
    $str = 'Sold ';
    foreach ($result->goods as $type => $amount) {
        if ($type == 'm_bu') {
            $type = 'food';
        } elseif ($type == 'm_oil') {
            $type = 'oil';
        }

        $c->$type -= $amount;
        $str .= $amount.' '.$type.', ';
    }
    $str .= 'for $'.$result->money.' on PM';
    out($str);
    return $result;
}

function buy_public(&$c, $quantity = array(), $price = array())
{
    global $techlist, $market;
    $result = ee('buy', array('quantity' => $quantity, 'price' => $price));
    $str = 'Bought ';
    $tcost = 0;
    foreach ($result->bought as $type => $details) {
        $ttype = 't_'.$type;
        if ($type == 'm_bu') {
            $type = 'food';
        } elseif ($type == 'm_oil') {
            $type = 'oil';
        } elseif (in_array($ttype, $techlist)) {
            $type = $ttype;
            //out_data($result);
        }

        $c->$type += $details->quantity;
        $c->money -= $details->cost;
        $tcost += $details->cost;
        $str .= $details->quantity.' '.$type.'@$'.floor($details->cost/$details->quantity);
        $pt = 'p'.$type;
        if (isset($details->$pt)) {
            $c->$pt = $details->$pt;
            $str.= '('.$details->$pt.'%)';
        }
        $str .= ', ';

        $market->relaUpdate($type, $quantity, $details->quantity);
    }

    $nothing = false;
    if ($str == 'Bought ') {
        $str .= 'nothing ';
        $nothing = true;
    }

    if ($nothing) {
        $what = null;
        $cost = 0;
        foreach ($quantity as $key => $q) {
            $what .= $key.$q.'@'.$price[$key].', ';
            $cost += round($q*$price[$key]*$c->tax());
        }
        out("Tried: ".$what);
        out("Money: ".$c->money." Cost: ".$cost);
        sleep(1);
        return false;
    }

    $str .= 'for $'.$tcost.' on public.';
    out($str);
    return $result;
}

function sell_public(&$c, $quantity = array(), $price = array(), $tonm = array())
{
    //out_data($c);

    //out_data($quantity);
    //out_data($price);
    /*$str = 'Try selling ';
	foreach($quantity as $type => $q){
		if($q == 0)
			continue;
		if($type == 'm_bu')
			$t2 = 'food';
		elseif($type == 'm_oil')
			$t2 = 'oil';
		else
			$t2 = $type;
		$str .= $q . ' ' . $t2 . '@' . $price[$type] . ', ';
	}
	$str .= 'on market.';
	out($str);*/
    if (array_sum($quantity) == 0) {
        out("Trying to sell nothing?");
        $c = get_advisor();
        return;
    }
    $result = ee('sell', array('quantity' => $quantity, 'price' => $price)); //ignore tonm for now, it's optional
    //out_data($result);
    if (isset($result->error) && $result->error) {
        out('ERROR: '.$result->error);
        sleep(1);
        return;
    }
    global $techlist;
    $str = 'Put ';
    if (isset($result->sell)) {
        foreach ($result->sell as $type => $details) {
            $bits = explode('_', $type);
            //$omtype = 'om_' . $bits[1];
            $ttype = 't_'.$type;
            if ($type == 'm_bu') {
                $type = 'food';
            } elseif ($type == 'm_oil') {
                $type = 'oil';
            } elseif (in_array($ttype, $techlist)) {
                $type = $ttype;
            }

            //$c->$omtype += $details->quantity;
            $c->$type -= $details->quantity;
            $str .= $details->quantity.' '.$type.' @ '.$details->price.', ';
        }
    }
    if ($str == 'Put ') {
        $str .= 'nothing on market.';
    }

    out($str);
    //sleep(1);
    return $result;
}


function buy_tech(&$c, $tech = 't_bus', $spend = 0, $maxprice = 9999)
{
    global $market;
    $update = false;
    //$market_info = get_market_info();   //get the Public Market info
    $tech = substr($tech, 2);
    $diff = $c->money - $spend;
    //out('Here;P:'.$market->price($tech).';Q:'.$market->available($tech).';S:'.$spend.';M:'.$maxprice.';');
    if ($market->price($tech) != null && $market->available($tech) > 0) {
        while ($market->price($tech) != null && $market->available($tech) > 0 && $market->price($tech) <= $maxprice && $spend > 0) {
            $price = $market->price($tech);
            $tobuy = min(floor($spend / ($price*$c->tax())), $market->available($tech));
            if ($tobuy == 0) {
                return;
            }
            //out($tech . $tobuy . "@$" . $price);
            $result = buy_public($c, array($tech => $tobuy), array($tech => $price));     //Buy troops!
            if ($result === false) {
                if ($update == false) {
                    $update = true;
                    $market->update(); //force update once more, and let it loop again
                } else {
                    return;
                }
            }
            $spend = $c->money - $diff;

            //out_data($result);
        }
    }
}



function rand_country_name()
{
    global $username;
    $name = substr($username, 0, 2).' '; //name them by the first 2 chars of a username; should still be fairly unique on this server
    $last = chr(32); //we just added a space
    $length = rand(5, 24);
    for ($i = 0; $i < $length; $i++) {
        $rand = rand(0, 10);
        if ($rand == 0 && $last != chr(32)) {
            $name .= $last = chr(32); //space
        } elseif ($rand%2) {
            $name .= $last = chr(rand(65, 90)); //A-Z
        } else {
            $name .= $last =  chr(rand(97, 122)); //a-z
        }
    }
    $name = trim($name);
    return $name;
}


function purebell($min, $max, $std_deviation, $step = 1)
{
 //box-muller-method
    $rand1 = (float) mt_rand()/(float) mt_getrandmax();
    $rand2 = (float) mt_rand()/(float) mt_getrandmax();
    $gaussian_number = sqrt(-2 * log($rand1)) * cos(2 * pi() * $rand2);
    $mean = ($max + $min) / 2;
    $random_number = ($gaussian_number * $std_deviation) + $mean;
    $random_number = round($random_number / $step) * $step;
    if ($random_number < $min || $random_number > $max) {
        $random_number = purebell($min, $max, $std_deviation);
    }
    return $random_number;
}
