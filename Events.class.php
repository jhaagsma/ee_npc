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
    private static $results     = [];
    private static $events      = [];
    private static $market      = [];
    private static $news        = [];
    private static $spy         = [];
    private static $news_losses = [];

    /**
     * Get new events
     *
     * @return $events Event Results
     */
    public static function new()
    {
        $result = ee('events');

        $types = [];

        self::$results = $result->results;

        $copyarray = (array)$result->results;

        foreach ($copyarray as $mainkey => $subitems) {
            $count_sub = is_array($subitems) ? count($subitems) : 0;
            log_country_message(null, "Events: $count_sub x $mainkey");

            $subitems = (array)$subitems;

            if (is_array($subitems)) {
                $key = key($subitems);
                log_country_message(null, "Key ex: $key");

                foreach (array_shift($subitems) as $subkey => $item) {
                    log_country_message(null, "Subkeys: $subkey; ex: $item");
                }
            }
        }

        //sleep(1);

        if (isset(self::$results->events)) {
            self::$events = self::$results->events;

            foreach (self::$events as $event) {
                if (!isset($types[$event->type])) {
                    $types[$event->type] = 1;
                } else {
                    $types[$event->type]++;
                }
            }

            foreach ($types as $type => $count) {
                log_country_message(null, "Events: $count x $type");
            }
        }

        $types = [];

        if (isset(self::$results->news)) {
            self::$news = self::$results->news;

            foreach (self::$news as $item) {
                if (!isset($types[$item->type])) {
                    $types[$item->type] = 1;
                } else {
                    $types[$item->type]++;
                }

                Country::addRetalDue($item->attacker, $item->type, $item->land);

                //out($item);
            }

            foreach ($types as $type => $count) {
                log_country_message(null, "News: $count x $type");
            }
        }

        // foreach ((array)self::$results as $key => $values) {
        //     $count = count($values);
        //     out("Key: $key; $count");
        // }

        return $result;
    }//end new()
}//end class
