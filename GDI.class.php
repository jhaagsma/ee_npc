<?php

/**
 * This file has all the GDI functions
 *
 * PHP Version 7
 *
 * @category Interface
 * @package  EENPC
 * @author   Julian Haagsma aka qzjul <jhaagsma@gmail.com>
 * @license  MIT License
 * @link     https://github.com/jhaagsma/ee_npc/
 */

namespace EENPC;

class GDI
{
    /**
     * Join GDI
     *
     * @return $result Game Result
     */
    public static function join()
    {
        $result = ee('gdi/join');
        log_country_message(null, "Join GDI");
        return $result;
    }//end join()

    /**
     * Leave GDI
     *
     * @return $result Game Result
     */
    public static function leave()
    {
        $result = ee('gdi/leave');
        log_country_message(null, "Leave GDI");
        return $result;
    }//end leave()
}//end class
