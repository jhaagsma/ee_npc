<?php

/**
 * This file has all the ally functions
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

class Allies
{
    /**
     * Get the list of allies
     *
     * @return Result The result
     */
    public static function getList()
    {
        $result = ee('ally/list');
        out("Ally List");
        out($result);
        return $result;
    }//end getList()

    /**
     * Get the list of candidates
     *
     * @param string $type String of ally type
     *
     * @return Result The result
     */
    public static function getCandidates($type = 'def')
    {
        $result = ee('ally/candidates', ['type' => $type]);
        out("Ally Candidates");
        out($result);
        return $result;
    }//end getCandidates()
}//end class
