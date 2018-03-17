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
        // out("Ally List");
        //out($result);
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
        out("Ally Candidates: $type");
        out($result);
        return $result;
    }//end getCandidates()

    /**
     * Offer an alliance
     *
     * @param int    $target Cuntry number of ally offer
     * @param string $type   String of ally type
     *
     * @return Result The result
     */
    public static function offer($target, $type = 'def')
    {
        out("Ally Offer of $type to $target");
        $result = ee('ally/offer', ['target' => $target, 'type' => $type]);
        //out($result);
        return $result;
    }//end offer()

    /**
     * Accept an alliance
     *
     * @param int    $target Cuntry number of ally offer
     * @param string $type   String of ally type
     *
     * @return Result The result
     */
    public static function accept($target, $type = 'def')
    {
        out("Ally Accept $type from $target");
        $result = ee('ally/accept', ['target' => $target, 'type' => $type]);
        out($result);
        return $result;
    }//end accept()

    /**
     * Automatically fill spots from candidates
     *
     * @param  string $type The alliance type
     *
     * @return null
     */
    public static function fill($type = 'def')
    {
        $list = self::getList();
        $list = $list->list;
        $max  = ['def' => 2, 'off' => 3, 'res' => 3, 'spy' => 2, 'trade' => 2];

        $require = 0;
        for ($i = 1; $i <= $max[$type]; $i++) {
            $name = $type . '_' . $i;
            if (!isset($list->$name)) {
                $require++;
            } elseif ($list->$name->detail == 'reject') {
                self::accept($list->$name->cnum, $type);
            }
        }

        if ($require == 0) {
            out("Allies for $type full!");
            return;
        }

        $candidates = self::getCandidates();
        $candidates = (array)$candidates->list;


        for ($i = 0; $i < $require; $i++) {
            if (empty($candidates)) {
                out("No ally candiates!");
                return;
            }
            $candidate = array_shift($candidates);
            self::offer($candidate->cnum, $type);
        }
    }//end fill()
}//end class
