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
        $result = ee('pm', ['buy' => $units]);
        if (!isset($result->cost)) {
            out("--- Failed to BUY Private Market; money={$c->money}");
            //out_data($result);
            // out_data($units);
            // out("UPDATE EVERYTHING");
            // global $pm_info;

            // Debug::on();
            // Debug::msg($pm_info);

            $c = get_advisor();   //Do both??
            $c->updateMain();     //UPDATE EVERYTHING
            //out("refresh money={$c->money}");
            return $result;
        }

        $c->money -= $result->cost;
        $str       = '--- BUY  Private Market: ';
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
                $c->$type += $amount;
                $str      .= str_pad(engnot($amount), 8, ' ', STR_PAD_LEFT)
                            .str_pad($type, 5, ' ', STR_PAD_LEFT);

                if ($first) {
                    $str .= str_pad('$'.engnot($c->money), 28, ' ', STR_PAD_LEFT)
                            .str_pad('($-'.engnot($result->cost).')', 14, ' ', STR_PAD_LEFT);
                }

                $first = false;
            }
        }

        //$str .= 'for $'.$result->cost.' on PM';
        out($str);
        return $result;
    }//end buy()


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

        out($str);
        return $result;
    }//end sell()
}//end class
