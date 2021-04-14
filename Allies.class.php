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
    public static $allowed = true;

    /**
     * Get the list of allies
     *
     * @return Result The result
     */
    public static function getList()
    {
        $result = ee('ally/list');
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
        log_country_message(null, "Request Ally Candidates: $type", 'cyan');
        $result = ee('ally/candidates', ['type' => $type]);
        return $result;
    }//end getCandidates()

    /**
     * Offer an alliance
     *
     * @param int    $target Country number of ally offer
     * @param string $type   String of ally type
     *
     * @return Result The result
     */
    public static function offer($target, $type = 'def')
    {
        log_country_message(null, "Ally Offer of $type to $target", 'cyan');
        $result = ee('ally/offer', ['target' => $target, 'type' => $type]);
        if ($result == "disallowed_by_server") {
            log_country_message(null, "ALLIES ARE NOT ALLOWED ON THIS SERVER!"); // FUTURE: error
            self::$allowed = false;
            return;
        }

        return $result;
    }//end offer()

    /**
     * Accept an alliance
     *
     * @param int    $target Country number of ally offer
     * @param string $type   String of ally type
     *
     * @return Result The result
     */
    public static function accept($target, $type = 'def')
    {
        log_country_message(null, "Ally Accept $type from $target", 'green');
        $result = ee('ally/accept', ['target' => $target, 'type' => $type]);
        return $result;
    }//end accept()

    /**
     * Cancel an alliance
     *
     * @param int    $target Country number of ally offer
     * @param string $type   String of ally type
     *
     * @return Result The result
     */
    public static function cancel($target, $type = 'def')
    {
        log_country_message(null, "CANCEL ALLIANCE $type from $target", 'yellow');
        $result = ee('ally/cancel', ['target' => $target, 'type' => $type]);
        return $result;
    }//end cancel()

    /**
     * Automatically fill spots from candidates
     *
     * @param  string $type The alliance type
     *
     * @return null
     */
    public static function fill($cpref, $type = 'def')
    {
        if (!self::$allowed || !$cpref->acquire_ingame_allies) {
            return false;
        }

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
            } elseif ($list->$name->detail == 'cancel' && rand(0, 5) == 0) {
                //put this in in case we send to a human by accident who doesn't accept
                log_country_message(null, "Withdraw offer randomly!", 'dark_gray');
                self::cancel($list->$name->cnum, $type);
            }
        }

        if ($require == 0) {
            log_country_message(null, "Allies for $type full!", 'dark_gray');
            return;
        }

        $candidates = self::getCandidates($type);
        $candidates = (array)$candidates->list;


        for ($i = 0; $i < $require; $i++) {
            if (empty($candidates)) {
                log_country_message(null, "No ally candiates!", 'yellow');
                return;
            }
            $candidate = array_shift($candidates);
            self::offer($candidate->cnum, $type);
        }
    }//end fill()

    public static function decline_all_allies_if_needed ($cpref) {
        if ($cpref->acquire_ingame_allies) {
            return false;
        }

        $list = self::getList();
        $list = $list->list;
        // FUTURE: use server info to limit ally types here and elsewhere
        $max  = ['def' => 2, 'off' => 3, 'res' => 3, 'spy' => 2, 'trade' => 2];

        // reject any offers to us and don't send offers
        foreach($max as $type => $max) {
            for ($i = 1; $i <= $max; $i++) {
                $name = $type . '_' . $i;
                if (isset($list->$name)) {
                    log_country_message(null, "Decline offer because we don't want any allies", 'dark_gray');
                    self::cancel($list->$name->cnum, $type);
                }
            }
        }

        return true;
    } // decline_all_allies_if_needed






}//end class
