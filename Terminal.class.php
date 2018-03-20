<?php
/**
 * This class is for terminal output for the EE NPC's
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

/*
IDEA FOR USEING out() BUT USING TERMINAL!

https://stackoverflow.com/questions/7141745/alias-class-method-as-global-function
You can either create a wrapper function or use create_function().

function _t() {
    call_user_func_array(array('TranslationClass', '_t'), func_get_args());
}

Or you can create a function on the fly:

$t = create_function('$string', 'return TranslationClass::_t($string);');

// Which would be used as:
print $t('Hello, World');
*/


/**
 * Function alias for Terminal::out($string)
 *
 * @return void
 */
function out()
{
    call_user_func_array(['\EENPC\Terminal', 'out'], func_get_args());
}//end out()


/**
 * Function alias for Terminal::data($data)
 *
 * @return void
 */
function out_data()
{
    call_user_func_array(['\EENPC\Terminal', 'data'], func_get_args());
}//end out_data()


class Terminal
{
    private static $columns  = [];
    private static $maxwidth = 125; //don't know if I need this yet

    /**
     * Ouput strings nicely
     * @param  string  $str              The string to format
     * @param  boolean $newline          If we should make a new line
     * @param  string  $foreground_color Foreground color
     * @param  string  $background_color Background color
     *
     * @return void                      echoes, not returns
     */
    public static function out($str, $newline = true, $foreground_color = null, $background_color = null)
    {
        //This just formats output strings nicely
        if (is_object($str)) {
            return out_data($str);
        }

        if ($foreground_color || $background_color) {
            $str = Colors::getColoredString($str, $foreground_color, $background_color);
        }

        echo ($newline ? "\n" : null)."[".date("H:i:s")."] $str";
    }//end out()




    /**
     * Output and format data
     * @param  array,object $data Data to ouput
     * @return void
     */
    public static function data($data)
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        out(str_replace("\n", " ", var_export($backtrace, true)));
        //This function is to output and format some data nicely
        out("DATA: ({$backtrace[0]['file']}:{$backtrace[0]['line']})\n".json_encode($data));
        //str_replace(",\n", "\n", var_export($data, true)));
    }//end data()


    /**
     * Initialize columsn for
     *
     * @param  integer $num number of columns
     *
     * @return void
     */
    public static function initColumns($num)
    {
        self::$columns = [];
        for ($i = 0; $i < $num; $i++) {
            self::$columns[$i] = null;
        }
    }//end initColumns()
}//end class
