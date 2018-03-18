<?php
/**
 * This class is to colour strings for the EE NPC's
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

class Colors
{
    // Set up shell colors
    public static $foreground_colors = [
        'black' => '0;30',
        'dark_gray' => '1;30',
        'blue' => '0;34',
        'light_blue' => '1;34',
        'green' => '0;32',
        'light_green' => '1;32',
        'cyan' => '0;36',
        'light_cyan' => '1;36',
        'red' => '0;31',
        'light_red' => '1;31',
        'purple' => '0;35',
        'light_purple' => '1;35',
        'brown' => '0;33',
        'yellow' => '1;33',
        'light_gray' => '0;37',
        'white' => '1;37',
    ];

    public static $background_colors = [
        'black' => '40',
        'red' => '41',
        'green' => '42',
        'yellow' => '43',
        'blue' => '44',
        'magenta' => '45',
        'cyan' => '46',
        'light_gray' => '47',
    ];


    // Returns colored string
    /**
     * Colour a string
     *
     * @param  string $string           The string to clour
     * @param  string $foreground_color The foreground colour
     * @param  string $background_color The background colour
     *
     * @return string                   The coloured string
     */
    public static function getColoredString($string, $foreground_color = null, $background_color = null)
    {
        $colored_string = "";

        // Check if given foreground color found
        if (isset(self::$foreground_colors[$foreground_color])) {
            $colored_string .= "\033[".self::$foreground_colors[$foreground_color]."m";
        }
        // Check if given background color found
        if (isset(self::$background_colors[$background_color])) {
            $colored_string .= "\033[".self::$background_colors[$background_color]."m";
        }

        // Add string and end coloring
        $colored_string .= $string."\033[0m";

        return $colored_string;
    }//end getColoredString()
}//end class
