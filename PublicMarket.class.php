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

class PublicMarket
{
    public static $updated   = 0;
    public static $available = null;
    public static $buy_price = null;
    public static $so_price  = null;

    /**
     * Get the info on the market, update the object
     * @return void
     */
    public static function update()
    {
        $market_info = get_market_info();   //get the Public Market info
        foreach ($market_info as $k => $var) {
            self::$$k = $var;
        }
        self::$updated = time();
    }//end update()


    public static function relaUpdate($which, $ordered, $got)
    {
        if ($got == $ordered && self::available($which) > $ordered) {
            self::$available->$which -= $ordered;
        } else {
            self::update();
        }
    }//end relaUpdate()


    /**
     * Time since last update
     * @return int seconds
     */
    public static function elapsed()
    {
        return time() - self::$updated;
    }//end elapsed()


    public static function price($item = 'm_bu')
    {
        if (self::elapsed() > 10) {
            self::update();
        }
        return (int)self::$buy_price->$item;
    }//end price()


    public static function available($item = 'm_bu')
    {
        if (self::elapsed() > 10) {
            self::update();
        }
        return self::$available->$item;
    }//end available()

    public static function buy(&$c, $quantity = [], $price = [])
    {
        global $techlist;
        $result = ee('buy', ['quantity' => $quantity, 'price' => $price]);
        $str    = 'Bought ';
        $tcost  = 0;
        foreach ($result->bought as $type => $details) {
            $ttype = 't_'.$type;
            if ($type == 'm_bu') {
                $type = 'food';
            } elseif ($type == 'm_oil') {
                $type = 'oil';
            } elseif (in_array($ttype, $techlist)) {
                $type = $ttype;
                //out_data($result);
            }

            $c->$type += $details->quantity;
            $c->money -= $details->cost;
            $tcost    += $details->cost;
            $str      .= $details->quantity.' '.$type.'@$'.floor($details->cost / $details->quantity);
            $pt        = 'p'.$type;
            if (isset($details->$pt)) {
                $c->$pt = $details->$pt;
                $str   .= '('.$details->$pt.'%)';
            }
            $str .= ', ';

            self::relaUpdate($type, $quantity, $details->quantity);
        }

        $nothing = false;
        if ($str == 'Bought ') {
            $str    .= 'nothing ';
            $nothing = true;
        }

        if ($nothing) {
            $what = null;
            $cost = 0;
            foreach ($quantity as $key => $q) {
                $what .= $key.$q.'@'.$price[$key].', ';
                $cost += round($q * $price[$key] * $c->tax());
            }
            out("Tried: ".$what);
            out("Money: ".$c->money." Cost: ".$cost);
            $c = get_advisor();
            sleep(1);
            return false;
        }

        $str .= 'for $'.$tcost.' on public.';
        out($str);
        return $result;
    }//end buy()


    public static function sell(&$c, $quantity = [], $price = [], $tonm = [])
    {
        //out_data($c);

        //out_data($quantity);
        //out_data($price);
        /*$str = 'Try selling ';
        foreach($quantity as $type => $q){
            if($q == 0)
                continue;
            if($type == 'm_bu')
                $t2 = 'food';
            elseif($type == 'm_oil')
                $t2 = 'oil';
            else
                $t2 = $type;
            $str .= $q . ' ' . $t2 . '@' . $price[$type] . ', ';
        }
        $str .= 'on market.';
        out($str);*/
        if (array_sum($quantity) == 0) {
            out("Trying to sell nothing?");
            $c = get_advisor();
            $c->updateOnMarket();
            global $debug;
            $debug = true;
            return;
        }
        $result = ee('sell', ['quantity' => $quantity, 'price' => $price]); //ignore tonm for now, it's optional
        $c->updateOnMarket();
        if (isset($result->error) && $result->error) {
            out('ERROR: '.$result->error);
            sleep(1);
            return;
        }
        global $techlist;
        $str = 'Put ';
        if (isset($result->sell)) {
            foreach ($result->sell as $type => $details) {
                //$bits = explode('_', $type);
                //$omtype = 'om_' . $bits[1];
                $ttype = 't_'.$type;
                if ($type == 'm_bu') {
                    $type = 'food';
                } elseif ($type == 'm_oil') {
                    $type = 'oil';
                } elseif (in_array($ttype, $techlist)) {
                    $type = $ttype;
                }

                //$c->$omtype += $details->quantity;
                $c->$type -= $details->quantity;
                $str      .= $details->quantity.' '.$type.' @ '.$details->price.', ';
            }
        }
        if ($str == 'Put ') {
            $str .= 'nothing on market.';
        }

        out($str);
        //sleep(1);
        return $result;
    }//end sell()



    public static function buy_tech(&$c, $tech = 't_bus', $spend = 0, $maxprice = 9999)
    {
        $update = false;
        //$market_info = get_market_info();   //get the Public Market info
        $tech = substr($tech, 2);
        $diff = $c->money - $spend;
        //out('Here;P:'.PublicMarket::price($tech).';Q:'.PublicMarket::available($tech).';S:'.$spend.';M:'.$maxprice.';');
        if (self::price($tech) != null && self::available($tech) > 0) {
            while (self::price($tech) != null
                && self::available($tech) > 0
                && self::price($tech) <= $maxprice
                && $spend > 0
            ) {
                $price = self::price($tech);
                $tobuy = min(floor($spend / ($price * $c->tax())), self::available($tech));
                if ($tobuy == 0) {
                    return;
                }
                //out($tech . $tobuy . "@$" . $price);
                $result = PublicMarket::buy($c, [$tech => $tobuy], [$tech => $price]);     //Buy troops!
                if ($result === false) {
                    if ($update == false) {
                        $update = true;
                        self::update(); //force update once more, and let it loop again
                    } else {
                        return;
                    }
                }
                $spend = $c->money - $diff;

                //out_data($result);
            }
        }
    }//end buy_tech()
}//end class
