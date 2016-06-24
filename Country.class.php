<?php namespace EENPC;

class Country
{
    public $fresh = false;
    public $fetched = false;

    /**
     * Takes in an advisor
     * @param {array} $advisor The advisor variables
     */
    public function __construct($advisor)
    {
        $this->fetched = time();
        $this->fresh = true;

        foreach ($advisor as $k => $var) {
            //out("K:$k V:$var");
            $this->$k = $var;
        }
    }

    public function updateMain()
    {
        $main = get_main();                 //Grab a fresh copy of the main stats
        $this->money = $main->money;       //might as well use the newest numbers?
        $this->food = $main->food;         //might as well use the newest numbers?
        $this->networth = $main->networth; //might as well use the newest numbers?
        $this->oil = $main->oil;           //might as well use the newest numbers?
        $this->pop = $main->pop;           //might as well use the newest numbers?
        $this->turns = $main->turns;       //This is the only one we really *HAVE* to check for
    }

    //GOAL functions
    /**
     * [nlg_target description]
     * @return [type] [description]
     */
    public function nlgTarget()
    {
        //lets lower it from 80+turns_playwed/7, to compete
        return floor(80 + $this->turns_played/15);
    }


    /**
     * Built Percentage
     * @return {int} Like, 81(%)
     */
    public function built()
    {
        return round(100*($this->land - $this->empty)/$this->land);
    }

    /**
     * Networth/(Land*Govt)
     * @return {int} The NLG of the country
     */
    public function nlg()
    {
        switch ($this->govt) {
            case 'R':
                $govt = 0.9;
                break;
            case 'I':
                $govt = 1.25;
                break;
            default:
                $govt = 1.0;
        }
        return floor($this->networth/($this->land*$govt));
    }

    /**
     * The float taxrate
     * @return {float} Like, 1.06, or 1.12, etc
     */
    public function tax()
    {
        return (100+$this->g_tax)/100;
    }
}
