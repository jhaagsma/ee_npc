<?php
/**
 * This class is for debugging for the EE NPC's
 *
 * PHP Version 7
 *
 * @category Classes
 * @package  EENPC
 * @author   Julian Haagsma aka qzjul <jhaagsma@gmail.com>
 * @license  All EENPC files are under the MIT License
 * @link     https://github.com/jhaagsma/ee_npc
 */

namespace EENPC;

class Debug
{
    private static $debugging = false;

    /**
     * Set debugging on
     *
     * @return void
     */
    public static function on()
    {
        if (!self::$debugging) {
            out("TURNING ON DEBUGGING!");
            out_data(null);
            sleep(60);
        }

        self::$debugging = true;
    }//end on()


    /**
     * Set debuggin off
     *
     * @return void
     */
    public static function off()
    {
        self::$debugging = false;
    }//end off()

    /**
     * I need a debugging function
     * @param  string  $str     Output
     * @param  boolean $newline Whether to print new lines
     * @return void             Prints text
     */
    public static function msg($str, $newline = true)
    {
        if (self::$debugging == true) {
            out($str, $newline);
        }
    }//end msg()
}//end class
