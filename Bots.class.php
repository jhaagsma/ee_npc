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
}//end class
