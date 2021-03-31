<?php
/**
 * This file has helper functions for bots
 *
 * PHP Version 7
 *
 * @category Control
 * @package  EENPC
 * @author   Julian Haagsma aka qzjul <jhaagsma@gmail.com>
 * @license  MIT License
 * @link     https://github.com/jhaagsma/ee_npc/
 */

namespace EENPC;

class Bots
{
    /**
     * Get the next playing cnum
     *
     * @param  array   $countries The countries
     * @param  integer $time      The time
     *
     * @return int                The cnum
     */
    public static function getNextPlayCNUM($countries, $time = 0)
    {
        global $settings;
        foreach ($countries as $cnum) {
            if (isset($settings->$cnum->nextplay) && $settings->$cnum->nextplay == $time) {
                return $cnum;
            }
        }
        return null;
    }//end getNextPlayCNUM()

    public static function getLastPlayCNUM($countries, $time = 0)
    {
        global $settings;
        foreach ($countries as $cnum) {
            if (isset($settings->$cnum->lastplay) && $settings->$cnum->lastplay == $time) {
                return $cnum;
            }
        }
        return null;
    }//end getLastPlayCNUM()


    public static function getNextPlays($countries)
    {
        global $settings;
        $nextplays = [];
        foreach ($countries as $cnum) {
            if (isset($settings->$cnum->nextplay)) {
                $nextplays[] = $settings->$cnum->nextplay;
            } else {
                $settings->$cnum->nextplay = 0; //set it?
            }
        }
        return $nextplays;
    }//end getNextPlays()


    public static function getFurthestNext($countries)
    {
        return max(self::getNextPlays($countries));
    }//end getFurthestNext()

    public static function furthest_play($cpref)
    {
        global $server, $rules;
        $max   = $rules->maxturns + $rules->maxstore;
        $held  = $cpref->lastTurns + $cpref->turnsStored;
        $diff  = $max - $held;
        $maxin = floor($diff * $server->turn_rate);
        log_main_message('Country is holding '.$held.'. Turns will max in '.$maxin);
        return $maxin;
    }//end furthest_play()


    public static function server_start_end_notification($server)
    {
        $start  = round((time() - $server->reset_start) / 3600, 1).' hours ago';
        $x      = floor((time() - $server->reset_start) / $server->turn_rate);
        $start .= " ($x turns)";
        $end    = round(($server->reset_end - time()) / 3600, 1).' hours';
        $x      = floor(($server->reset_end - time()) / $server->turn_rate);
        $end   .= " ($x turns)";
        log_main_message("Server started ".$start.' and ends in '.$end);
    }//end server_start_end_notification()


    public static function assign_strat_from_country_loop($country_position, $is_debug_server, $is_ai_server) {
        // FUTURE: make it easy to assign whatever mix of strategies we want with different mixes by server

        // a 20/20/20/20/20 split doesn't work well on the ai server
        // farmers end up not being able to sell food and troops can go to $40
        if($is_ai_server and !$is_debug_server) {
            // per 25 countries: 3 farmer, 3 CI, 3 rainbow, 7 techer, 9 casher
            // this doesn't handle a few country deletions well, but I don't think that it matters for ai
            if (($country_position % 25) <= 8) {
                return 'C';
            } elseif  (($country_position % 25) <= 15) {
                return 'T';
            } elseif  (($country_position % 25) <= 18) {
                return 'I';
            } elseif  (($country_position % 25) <= 21) {
                return 'F';
            } else {
                return 'R';
            }
        }

        // other servers use this logic of 20/20/20/20/20
        // debug servers are included here so we can test this code before it goes live
        if ($country_position % 5 == 0) {
            return 'F';
        } elseif ($country_position % 5 == 1) {
            return 'T';
        } elseif ($country_position % 5 == 2) {
            return 'C';
        } elseif ($country_position % 5 == 3) { 
            return 'I';
        } else {
            return 'R';
        }
    } //end assign_strat_from_country_loop()


    /*
    // retired in favor of assign_strat_from_country_loop()
    public static function pickStrat($cnum)
    {
        $rand = rand(0, 100);
        if ($rand < 20) { // used to be 25
            return 'F';
        } elseif ($rand < 40) { // used to be 55
            return 'T';
        } elseif ($rand < 60) { // used to be 80
            return 'C';
        } elseif ($rand < 80) { // used to be 95
            return 'I';
        } else {
            return 'R';
        }
    }//end pickStrat()
    */

    public static function playstats($countries)
    {
        govtStats($countries);

        global $server;
        $stddev = round(self::playtimes_stddev($countries));
        log_main_message("Standard Deviation of play is: $stddev; (".round($stddev / $server->turn_rate).' turns)');
        /* 
        // no longer resetting because we want to set nextplay based on country status now
        // for example, if a country stops playing turns because there's no food on the public market then we want it to login again
        // soon to try to buy food and play turns
        if ($stddev < $server->turn_rate * 72 / 4 || $stddev > $server->turn_rate * 72) {
            log_main_message('Recalculating Nextplays');
            global $settings;
            foreach ($countries as $cnum) {
                $settings->$cnum->nextplay = time() + rand(0, $server->turn_rate * 72);
            }

            $stddev = round(self::playtimes_stddev($countries));
            log_main_message("Standard Deviation of play is: $stddev");
        }
        */

        self::outOldest($countries);
        self::outFurthest($countries);
        //self::outNext($countries);
    }//end playstats()


    public static function outOldest($countries)
    {
        global $server;
        $old    = self::oldestPlay($countries);
        $onum   = self::getLastPlayCNUM($countries, $old);
        $ostrat = self::txtStrat($onum);
        $old    = time() - $old;
        log_main_message("Oldest Play: ".$old."s ago by #$onum $ostrat (".round($old / $server->turn_rate)." turns)");
        if ($old > 86400 * 2) {
            log_main_message("OLD TOO FAR: RESET NEXTPLAY");
            global $settings;
            $settings->$onum->nextplay = 0;
        }
    }//end outOldest()


    public static function outFurthest($countries)
    {
        global $server;
        $furthest = self::getFurthestNext($countries);
        $fnum     = self::getNextPlayCNUM($countries, $furthest);
        $fstrat   = self::txtStrat($fnum);
        $furthest = $furthest - time();
        log_main_message("Furthest Play in ".$furthest."s for #$fnum $fstrat (".round($furthest / $server->turn_rate)." turns)");
    }//end outFurthest()


    public static function outNext($countries, $rewrite = false, $log_to_file = false)
    {
        $next   = self::getNextPlays($countries);
        $xnum   = self::getNextPlayCNUM($countries, min($next));
        $xstrat = self::txtStrat($xnum);
        $next   = max(0, min($next) - time());
        if($log_to_file) {
            log_main_message("The next country to play is #$xnum in $next seconds...");
            echo '\n'; // need a line break for the countdown below
        }
        out("Next Play in ".$next.'s: #'.$xnum." $xstrat    ".($rewrite ? "\r" : null), !$rewrite); // leave as out()
    }//end outNext()


    public static function txtStrat($cnum)
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
    }//end txtStrat()

    public static function playtimes_stddev($countries)
    {
        $nextplays = self::getNextPlays($countries);
        return Math::standardDeviation($nextplays);
    }//end playtimes_stddev()


    public static function lastPlays($countries)
    {
        global $settings;
        $lastplays = [];
        foreach ($countries as $cnum) {
            if (isset($settings->$cnum->lastplay)) {
                $lastplays[] = $settings->$cnum->lastplay;
            } else {
                $settings->$cnum->lastplay = 0; //set it?
            }
        }

        return $lastplays;
    }//end lastPlays()


    public static function oldestPlay($countries)
    {
        return min(self::lastPlays($countries));
    }//end oldestPlay()
}//end class
