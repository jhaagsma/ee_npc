<?php

namespace EENPC;

class PublicMarket
{
    public $updated = 0;

    /**
     * Get the info on the market, update the object
     * @return void
     */
    public function update()
    {
        $market_info = get_market_info();   //get the Public Market info
        foreach ($market_info as $k => $var) {
            $this->$k = $var;
        }
        $this->updated = time();
    }//end update()


    public function relaUpdate($which, $ordered, $got)
    {
        if ($got == $ordered && $this->available($which) > $ordered) {
            $this->available->$which -= $ordered;
        } else {
            $this->update();
        }
    }//end relaUpdate()


    /**
     * Time since last update
     * @return int seconds
     */
    public function elapsed()
    {
        return time() - $this->updated;
    }//end elapsed()


    public function price($item = 'm_bu')
    {
        if ($this->elapsed() > 10) {
            $this->update();
        }
        return $this->buy_price->$item;
    }//end price()


    public function available($item = 'm_bu')
    {
        if ($this->elapsed() > 10) {
            $this->update();
        }
        return $this->available->$item;
    }//end available()
}//end class
