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

        $this->market_info = null;
        $this->market_fetched = null;

        foreach ($advisor as $k => $var) {
            //out("K:$k V:$var");
            $this->$k = $var;
        }
        global $cpref;
        $cpref->networth = $this->networth;
        $cpref->land = $this->land;
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

    public function updateOnMarket()
    {
        $this->market_info = get_owned_on_market_info();  //find out what we have on the market
        $this->market_fetched = time();

        $this->om_total = 0;
        foreach ($this->market_info as $key => $goods) {
            $omgood = 'om_'.$goods->type;
            if (!isset($this->$omgood)) {
                $this->$omgood = 0;
            }
            $this->$omgood += $goods->quantity;
            $this->om_total += $goods->quantity;

            debug("OnMarket: $key: QIn:{$goods->quantity}/QSave:{$this->$omgood}");
            $this->stuckOnMarket($goods);
        }
        //out("Goods on Market: {$this->om_total}");
    }

    public function onMarket($good = null)
    {
        if (!$this->market_info) {
            $this->updateOnMarket();
        }

        $omgood = 'om_'.($good != null ? $good : 'total');
        if (!isset($this->$omgood)) {
            $this->$omgood = 0;
        }

        return $this->$omgood;
    }

    public function stuckOnMarket($goods)
    {
        //out_data($goods);
        $expl = explode('_', $goods->type);
        $good = $expl[0] == 't' ? $expl[1] : $goods->type;
        $good = $good == 'm_bu' ? 'food' : $good;
        $atm = 'at'.$good;
        $this->$atm = $goods->time < time() ? true : false;
        //out("Setting $atm: {$this->$atm}");
    }

    /**
     * Tell if $good (like m_tr) are actually FOR SALE on market.
     * Requres onMarket to have been called
     * @param  {string} $good like m_tr, t_mil
     * @return {bool}         return true or false!
     */
    public function goodsStuck($good)
    {
        $atm = 'at'.$good;
        //out("Getting $atm");
        //if (isset($this->$atm)) {
            //out("Getting $atm: {$this->$atm}");
        //}
        if (isset($this->$atm) && $this->$atm) {
            out("Goods Stuck: $good: {$this->$atm}");
            return true;
        }

        return false;
    }

    /**
     * Set the indy production
     * @param array|string $what either the unit to set to 100%, or an array of percentages
     */
    public function setIndy($what)
    {
        $init = [
            'pro_spy'   =>$this->pro_spy,
            'pro_tr'    =>$this->pro_tr,
            'pro_j'     =>$this->pro_j,
            'pro_tu'    =>$this->pro_tu,
            'pro_ta'    =>$this->pro_ta,
        ];
        $new = [];
        if (is_array($what)) {
            $sum = 0;
            foreach ($init as $item => $percentage) {
                $new[$item] = isset($what[$item]) ? $what[$item] : 0;
                $sum += $percentage;
            }
        } elseif (array_key_exists($what, $init)) {
            $new = array_fill_keys(array_keys($init), 0);
            $new[$what] = 100;
        }

        if ($new != $init) {
            foreach ($new as $item => $percentage) {
                $this->$item = $percentage;
            }

            out("Set indy production".(is_array($what) ? '!' : ' to '.substr($what, 4).'.'));
            set_indy($this);
        }
    }

    public function setIndyFromMarket()
    {
        $new = ['pro_spy' => 5]; //just set spies to 5% for now
        global $market;

        $score = [
            'pro_tr'  =>1.86*$market->price('m_tr'),
            'pro_j'   =>1.86*$market->price('m_j'),
            'pro_tu'  =>1.86*$market->price('m_tu'),
            'pro_ta'  =>0.4*$market->price('m_ta')
        ];
        arsort($score);
        $which = key($score);
        $new[$which] = 95; //set to do the most expensive of whatever other good

        $this->setIndy($new);
    }

    /**
     * How much money it will cost to run turns
     * @param  int  $turns turns we want to run (or all)
     * @return cost        money
     */
    public function runCash($turns = null)
    {
        if ($turns == null) {
            $turns = $this->turns;
        }

        return max(0, $this->income)*$turns;
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
     * A crude Defence Per Acre number
     * @return {int} DPATarget
     */
    public function defPerAcreTarget()
    {
        return floor(15 + $this->turns_played/20);
    }

    /**
     * The amount of defence per Acre of Land
     * @return float
     */
    public function defPerAcre()
    {
        return round((1*$this->m_tr+2*$this->m_tu+4*$this->m_ta)/$this->land);
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

    public function fullBuildCost()
    {
        return $this->empty*$this->build_cost;
    }


    /**
     * Find Highest Goal
     * @param  array  $goalsan array of goals to persue
     * @return string highest goal!
     */
    public function highestGoal($goals = [], $skip = 0)
    {
        $psum = 0;
        $score = [];
        foreach ($goals as $goal) {
            if ($goal[0] == 't_agri') {
                $score['t_agri'] = ($goal[1]-$this->pt_agri)/($goal[1]-100)*$goal[2];
            } elseif ($goal[0] == 't_indy') {
                $score['t_indy'] = ($goal[1]-$this->pt_indy)/($goal[1]-100)*$goal[2];
            } elseif ($goal[0] == 't_bus') {
                $score['t_bus'] = ($goal[1]-$this->pt_bus)/($goal[1]-100)*$goal[2];
            } elseif ($goal[0] == 't_res') {
                $score['t_res'] = ($goal[1]-$this->pt_res)/($goal[1]-100)*$goal[2];
            } elseif ($goal[0] == 't_mil') {
                $score['t_mil'] = ($this->pt_mil-$goal[1])/(100-$goal[1])*$goal[2];
            } elseif ($goal[0] == 'nlg') {
                $score['nlg'] = $this->nlg()/$this->nlgTarget()*$goal[2];
            } elseif ($goal[0] == 'dpa') {
                $score['dpa'] = $this->defPerAcre()/$this->defPerAcreTarget()*$goal[2];
            }
            $psum += $goal[2];
        }
        //out_data($score);

        arsort($score);

        //out_data($score);
        for ($i = 0; $i < $skip; $i++) {
            array_shift($score);
        }

        return key($score);
    }

    /**
     * Convoluted ladder logic to buy whichever goal is least fulfilled
     * @param  array   $goals         an array of goals to persue
     * @param  int     $spend         money to spend
     * @param  int     $spend_partial intermediate money, for recursion
     * @param  integer $skip          goal to skip due to failure
     * @return void
     */
    public function countryGoals($goals = [], $spend = null, $spend_partial = null, $skip = 0)
    {
        if (empty($goals)) {
            return;
        }

        if ($spend == null) {
            $spend = $this->money;
        }

        if ($spend_partial == null) {
            $spend_partial = $spend / 3;
        }

        global $cpref;
        $tol = $cpref->price_tolerance; //should be between 0.5 and 1.5

        $what = $this->highestGoal($goals, $skip);
        //out("Highest Goal: ".$what.' Buy $'.$spend_partial);
        $diff = 0;
        $techprice = 8000*$tol;
        if ($what == 't_agri') {
            $o = $this->money;
            buy_tech($this, 't_agri', $spend_partial, $techprice);
            $diff = $this->money - $o;
        } elseif ($what == 't_indy') {
            $o = $this->money;
            buy_tech($this, 't_indy', $spend_partial, $techprice);
            $diff = $this->money - $o;
        } elseif ($what == 't_bus') {
            $o = $this->money;
            buy_tech($this, 't_bus', $spend_partial, $techprice);
            $diff = $this->money - $o;
        } elseif ($what == 't_res') {
            $o = $this->money;
            buy_tech($this, 't_res', $spend_partial, $techprice);
            $diff = $this->money - $o;
        } elseif ($what == 't_mil') {
            $o = $this->money;
            buy_tech($this, 't_mil', $spend_partial, $techprice);
            $diff = $this->money - $o;
        } elseif ($what == 'nlg') {
            $o = $this->money;
            defend_self($this, floor($this->money - $spend_partial)); //second param is *RESERVE* cash
            $diff = $this->money - $o;
        } elseif ($what == 'dpa') {
            $o = $this->money;
            defend_self($this, floor($this->money - $spend_partial)); //second param is *RESERVE* cash
            $diff = $this->money - $o;
        }

        if ($diff == 0) {
            $skip++;
        }

        $spend -= $spend_partial;
        //10000 because that's how much one tech point *could* cost, and i don't want it to get too ridiculous
        if ($spend > 10000 && $skip < count($goals) - 1) {
            $this->countryGoals($goals, $spend, $spend_partial, $skip);
        }
    }


    public function countryStats($strat, $goals = [])
    {
        out("NW: {$this->networth}; Land: {$this->land}; Govt: {$this->govt}; Played: {$this->turns_played}; Goal: ".$this->highestGoal($goals));
        out("Bus: {$this->pt_bus}%; Res: {$this->pt_res}%;  Mil: {$this->pt_mil}%; Agri: {$this->pt_agri}%; Indy: {$this->pt_mil}%;");
        out("DPA: ".$this->defPerAcre()." NLG: ".$this->nlg());
        out("Done Playing ".$strat." Turns for #$this->cnum!");   //Text for screen
    }
}
