<?php
/**
 * This file has math functions
 *
 * PHP Version 7
 *
 * @category Library
 * @package  EENPC
 * @author   Julian Haagsma aka qzjul <jhaagsma@gmail.com>
 * @license  MIT License
 * @link     https://github.com/jhaagsma/ee_npc/
 */

namespace EENPC;

class Math
{
    /**
     * Return a random on a bell curve using the box-muller-method
     *
     * @param float   $min           The minimum
     * @param float   $max           The maximum
     * @param float   $std_deviation The standard deviation
     * @param integer $step          The step size/resolution of the random number
     *
     * @return float                 A random number
     */
    public static function purebell($min, $max, $std_deviation, $step = 1)
    {
     //box-muller-method
        $rand1           = (float)mt_rand() / (float)mt_getrandmax();
        $rand2           = (float)mt_rand() / (float)mt_getrandmax();
        $gaussian_number = sqrt(-2 * log($rand1)) * cos(2 * pi() * $rand2);
        $mean            = ($max + $min) / 2;
        $random_number   = ($gaussian_number * $std_deviation) + $mean;
        //out($random_number);
        $random_number = round($random_number / $step) * $step;
        //out($random_number);
        if ($random_number < $min || $random_number > $max) {
            $random_number = purebell($min, $max, $std_deviation, $step);
        }
        return $random_number;
    }//end purebell()
}//end class
