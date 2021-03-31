<?php
/**
 * This file has interfacing functions for bots
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

class PrivateMarket
{

    public static $info    = [];
    public static $updated = 0;
    public static $cnum    = null;


    /**
     * Get the public market information
     *
     * Give the option to specify a country number, just in case
     *
     * @param Object $c Country object
     *
     * @return result EE Private Market Result
     */
    public static function getInfo($c = null)
    {
        if ($c !== null) {
            self::$cnum = $c->cnum;
        }

        self::$info = ee('pm_info');   //get and return the PRIVATE MARKET information

        self::$updated = time();

        return self::$info;
    }//end getInfo()

    /**
     * Get a recent version of the info, but don't fetch a new one
     *
     * Give the option to specify a country number, just in case
     *
     * @param Object $c Country object
     *
     * @return result EE Private Market Result
     */
    public static function getRecent($c = null)
    {
        if (time() - self::$updated > 20 && ($c === null || $c->cnum == self::$cnum) || self::$info == []) {
            return self::getInfo($c);
        }

        return self::$info;
    }//end getRecent()

    /**
     * Buy on the Private Market
     *
     * @param  Object $c     Country Object
     * @param  array  $units Units to buy
     *
     * @return object        Return value
     */
    public static function buy(&$c, $units = [])
    {
        // log_country_message($c->cnum, "2.Hash: ".spl_object_hash($c));

        $result = ee('pm', ['buy' => $units]);
        if (!isset($result->cost)) {
            log_country_message($c->cnum, "--- Failed to BUY Private Market; money={$c->money}");

            self::getInfo(); //update the PM, because weird

            $c = get_advisor();   //Do both??
            //$c->updateMain();     //UPDATE EVERYTHING

            return $result;
        }

        $c->money -= $result->cost;
        $str       = '--- BUY  Private Market: ';
        $pad       = "\n".str_pad(' ', 34);
        $first     = true;
        foreach ($result->goods as $type => $amount) {
            if ($type == 'm_bu') {
                $c_type = 'food';
            } elseif ($type == 'm_oil') {
                $c_type = 'oil';
            } else {
                $c_type = $type;
            }

            if ($amount > 0) {
                if (!$first) {
                    $str .= $pad;
                }

                self::$info->available->$type -= $amount;
                $c->$c_type                   += $amount;

                $str .= str_pad(engnot($amount), 8, ' ', STR_PAD_LEFT)
                        .str_pad($c_type, 5, ' ', STR_PAD_LEFT);

                if ($first) {
                    $str .= str_pad('$'.engnot($c->money), 28, ' ', STR_PAD_LEFT)
                            .str_pad('($-'.engnot($result->cost).')', 14, ' ', STR_PAD_LEFT);
                }

                $first = false;
            }
        }

        //$str .= 'for $'.$result->cost.' on PM';
        log_country_message($c->cnum, $str);
        return $result;
    }//end buy()

    /*
    Simple wrapper function for sell when you just want to sell one type of unit (like food)
    */
    public static function sell_single_good(&$c, $type, $amount)
    {
        $units = [$type => $amount];
        return self::sell($c, $units);
    } // end sell_single_good()

    /**
     * Sell on the Private Market
     *
     * @param  Object $c     Country Object
     * @param  array  $units Units to sell
     *
     * @return object        Return value
     */
    public static function sell(&$c, $units = [])
    {
        $result    = ee('pm', ['sell' => $units]);
        $c->money += $result->money;
        $str       = '--- SELL Private Market: ';
        $pad       = "\n".str_pad(' ', 34);
        $first     = true;

        foreach ($result->goods as $type => $amount) {
            if ($type == 'm_bu') {
                $type = 'food';
            } elseif ($type == 'm_oil') {
                $type = 'oil';
            }

            if ($amount > 0) {
                if (!$first) {
                    $str .= $pad;
                }
                $c->$type -= $amount;
                $str      .= str_pad(engnot($amount), 8, ' ', STR_PAD_LEFT)
                            .str_pad($type, 5, ' ', STR_PAD_LEFT);

                if ($first) {
                    $str .= str_pad('$'.engnot($c->money), 28, ' ', STR_PAD_LEFT)
                            .str_pad('($+'.engnot($result->money).')', 14, ' ', STR_PAD_LEFT);
                }

                $first = false;
            }
        }

        log_country_message($c->cnum, $str);
        return $result;
    }//end sell()
}//end class
