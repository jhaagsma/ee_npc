<?php

/**
 * This file has all the build functions
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

class Build
{
    /**
     * Build Things
     *
     * @param array $buildings Build a particular set of buildings
     *
     * @return $result         Game Result
     */
    public static function buildings($buildings = [])
    {
        //default is an empty array
        return ee('build', ['build' => $buildings]);
    }//end buildings()


    public static function destroy_all_of_one_type(&$c, $building_type)
    {
        $buildings = [];
        if($building_type == 'farm')
            $buildings = ['farm' => $c->b_farm];
        else // FUTURE: support all
            log_error_message(999, $c->cnum, 'destroy_all_of_one_type(): invalid value for $building_type:'.$building_type);

        ee('destroy', ['destroy' => $buildings]);

        log_country_message($c->cnum, "Destroyed all buildings of type $building_type and updating advisor");
        
        // FUTURE: do incremental update
        return false; //this will do $c->get_advisor();
    }//end buildings()


    /**
     * Build CS
     *
     * @param  integer $turns Number of turns of CS to build
     *
     * @return $result        Game Result
     */
    public static function cs($turns = 1)
    {
                                //default is 1 CS if not provided
        return self::buildings(['cs' => $turns]);
    }//end cs()


    /**
     * Build one BPT for techer
     *
     * @param  object $c Country Object
     *
     * @return $result   Game Result
     */
    public static function techer(&$c)
    {
        return self::buildings(['lab' => $c->bpt]);
    }//end techer()


    /**
     * Build one BPT for farmer
     *
     * @param  object $c Country Object
     *
     * @return $result   Game Result
     */
    public static function farmer(&$c)
    {
        //build farms
        return self::buildings(['farm' => $c->bpt]);
    }//end farmer()



    /**
     * Build one BPT for casher
     *
     * @param  object $c Country Object
     *
     * @return $result   Game Result
     */
    public static function casher(&$c)
    {
        //build ent/res
        $ent = ceil($c->bpt * 1.05 / 2);
        return self::buildings(['ent' => $ent, 'res' => $c->bpt - $ent]);
    }//end casher()


    /**
     * Build one BPT for indy
     *
     * @param  object $c Country Object
     *
     * @return $result   Game Result
     */
    public static function indy(&$c)
    {
        //build indies
        return self::buildings(['indy' => $c->bpt]);
    }//end indy()
}//end class
