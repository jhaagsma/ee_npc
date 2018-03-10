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
        out("RESULT: $result->govt");
        return $result;
    }//end getList()
}//end class
