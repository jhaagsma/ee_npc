<?php

/**
 * This file has all the attacking functions
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

class Attack
{
    // NOTE: when implemented this must respect cooperation server, $rules->can_attack_players, etc
    public static function doAttack(&$c, $cnum) {

    }

    public static function prepAttack(&$c, $cnum) {

    }

    /**
     * Conduct a Standard Strike
     *
     * @param object $c    The country object
     * @param int    $cnum The country to attack
     *
     * @return $result Game Result
     */
    public static function standardStrike(&$c, $cnum)
    {
        // $result = ee('govt', ['govt' => $govt]);
        // if (isset($result->govt)) {
        //     $c = get_advisor(); //UPDATE EVERYTHING
        // }

        return $result;
    }//end standardStrike()
}//end class
