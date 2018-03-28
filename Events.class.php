<?php

/**
 * This file has all the events functions
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

class Events
{
    private static $results = [];
    private static $events  = [];
    private static $market  = [];
    /**
     * Get new events
     *
     * @return $events Event Results
     */
    public static function new()
    {
        $result = ee('events');

        $types = [];

        self::$results = $result['results'];

        if (isset(self::$results['events'])) {
            self::$events = self::$results['events'];

            foreach (self::$events as $event) {
                if (!isset($types[$event['type']])) {
                    $types[$event['type']] = 1;
                } else {
                    $types[$event['type']]++;
                }
            }

            foreach ($types as $type => $count) {
                out("Events: $count x $type");
            }
        }

        return $result;
    }//end new()
}//end class
