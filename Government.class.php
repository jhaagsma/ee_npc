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

class Government
{
    /**
     * Change Government
     *
     * @param object $c    The country object
     * @param char   $govt The government letter to change to
     *
     * @return $result Game Result
     */
    public static function change(&$c, $govt)
    {
        $result = ee('govt', ['govt' => $govt]);
        if (isset($result->govt)) {
            out("Govt switched to {$result->govt}!");
            $c = get_advisor(); //UPDATE EVERYTHING
        }

        return $result;
    }//end change()
}//end class
