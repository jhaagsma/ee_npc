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
                $score['t_mil'] = ($this->pt_bus-$goal[1])/(100-$goal[1])*$goal[2];
            } elseif ($goal[0] == 'nlg') {
                $score['nlg'] = $this->nlg()/$this->nlgTarget()*$goal[2];
            }
            $psum += $goal[2];
        }
        //out_data($score);

        arsort($score);

        //out_data($score);
        for ($i = 0; $i < $skip; $i++) {
            array_shift($score);
        }

        $what = key($score);
        //out("Highest Goal: ".$what.' Buy $'.$spend_partial);
        $diff = 0;
        $techprice = 5000*$tol;
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
        }

        if ($diff == 0) {
            $skip++;
        }

        $spend -= $spend_partial;
        //10000 because that's how much one tech point *could* cost, and i don't want it to get too ridiculous
        if ($spend > 10000 && $skip < count($score) - 1) {
            $this->countryGoals($goals, $spend, $spend_partial, $skip);
        }
    }
}
