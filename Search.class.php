<?php

/**
 * This file has all the government functions
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

class Search
{
    /**
     * Change Government
     *
     * @param int $cnum The country to search for
     *
     * @return $result Search Result
     */
    public static function country($cnum)
    {
        $result = $cnum;
        $result = ee('ranks/'.(int)$cnum);
        //out("Search: $result");

        return $result['results'];
    }//end country()
}//end class
