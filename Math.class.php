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
        $random_number = round($random_number / $step) * $step;
        if ($random_number < $min || $random_number > $max) {
            $random_number = self::purebell($min, $max, $std_deviation, $step);
        }
        return $random_number;
    }//end purebell()

    // use if you want values to be the mean 50% of the time with the other 50% being a normal distribution to the right of the mean
    // it's like cutting a bell curve in half and forcing the missing bottom 50% to be equal to the mean
    public static function half_bell_truncate_left_side($mean, $max, $std_deviation, $step = 1) {
        $purebell_min = $mean - ($max - $mean);
        return max($mean, self::purebell($purebell_min, $max, $std_deviation, $step));
    }

    public static function half_bell_truncate_right_side($mean, $min, $std_deviation, $step = 1) {
        $purebell_max = $mean + ($mean - $min);
        return min($mean, self::purebell($min, $purebell_max, $std_deviation, $step));
    }


    /**
     * Calculate the standard deviation
     *
     * @param  array $array An array of numbers
     *
     * @return float        The standard deviation of the numbers
     */
    public static function standardDeviation($array)
    {
        if (!$array or count($array) == 1) { // avoid division by zero error
            return 0;
        }

        // square root of sum of squares devided by N-1
        //frikkin namespaces making my life difficult
        return sqrt(
            array_sum(
                array_map(
                    // ANONYMOUS Function to calculate square of value - mean
                    function ($x, $mean) {
                        return pow($x - $mean, 2);
                    },
                    $array,
                    array_fill(0, count($array), (array_sum($array) / count($array)))
                )
            ) / (count($array) - 1)
        );
    }//end standardDeviation()
}//end class
