<?php
/**
 * Country Class
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

class Country
{
    public $fresh   = false;
    public $fetched = false;

    /**
     * Takes in an advisor
     * @param {array} $advisor The advisor variables
     */
    public function __construct($advisor)
    {
        $this->fetched = time();
        $this->fresh   = true;

        $this->market_info    = null;
        $this->market_fetched = null;

        foreach ($advisor as $k => $var) {
            $this->$k = $var;
        }

        global $cpref_file;
        $cpref_file->networth = $this->networth;
        $cpref_file->land     = $this->land;
    }//end __construct()


    public function updateMain() // WARNING: this does not update bushel consumption!
    {
        $main           = get_main();                 //Grab a fresh copy of the main stats
        $this->money    = $main->money;       //might as well use the newest numbers?
        $this->food     = $main->food;         //might as well use the newest numbers?
        $this->networth = $main->networth; //might as well use the newest numbers?
        $this->land     = $main->land; //might as well use the newest numbers?
        $this->oil      = $main->oil;           //might as well use the newest numbers?
        $this->pop      = $main->pop;           //might as well use the newest numbers?
        $this->turns    = $main->turns;       //This is the only one we really *HAVE* to check for
    }//end updateMain()


    public function updateOnMarket()
    {
        $this->market_info    = get_owned_on_market_info();  //find out what we have on the market
        $this->market_fetched = time();

        $this->om_total = 0;
        foreach ($this->market_info as $key => $goods) {
            $omgood = 'om_'.$goods->type;
            if (!isset($this->$omgood)) {
                $this->$omgood = 0;
            }

            $this->$omgood  += $goods->quantity;
            $this->om_total += $goods->quantity;

            //Debug::msg("OnMarket: $key: QIn: {$goods->quantity} / QSave: {$this->$omgood}");
            $this->stuckOnMarket($goods);
        }

        //out("Goods on Market: {$this->om_total}");
    }//end updateOnMarket()


    public function get_foodcon_no_decay() {
        return floor($this->foodcon + 0.001 * $this->food); // FUTURE: set this in the object?
    } 


    public function get_foodnet_no_decay() {
        return floor($this->foodnet + 0.001 * $this->food); // FUTURE: set this in the object?
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
    }//end onMarket()


    public function stuckOnMarket($goods)
    {
        //out_data($goods);
        $expl       = explode('_', $goods->type);
        $good       = $expl[0] == 't' ? $expl[1] : $goods->type;
        $good       = $good == 'm_bu' ? 'food' : $good;
        $atm        = 'at'.$good;
        $this->$atm = $goods->time < time() ? true : false;
        //out("Setting $atm: {$this->$atm};");
    }//end stuckOnMarket()


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
        $omgood = 'om_'.$good;
        $om     = $this->$omgood ?? 0;
        if (isset($this->$atm) && $this->$atm) {
            log_country_message($this->cnum, "Goods Stuck: $good: $om");
            return true;
        }

        return false;
    }//end goodsStuck()


    /**
     * Set the indy production
     * @param array|string $what either the unit to set to 100%, or an array of percentages
     *
     * @return void
     */
    public function setIndy($what)
    {
        $init = [
            'pro_spy'   => $this->pro_spy,
            'pro_tr'    => $this->pro_tr,
            'pro_j'     => $this->pro_j,
            'pro_tu'    => $this->pro_tu,
            'pro_ta'    => $this->pro_ta,
        ];
        $new  = [];
        if (is_array($what)) {
            $sum = 0;
            foreach ($init as $item => $percentage) {
                $new[$item] = isset($what[$item]) ? $what[$item] : 0;
                $sum       += $percentage;
            }
        } elseif (array_key_exists($what, $init)) {
            $new        = array_fill_keys(array_keys($init), 0);
            $new[$what] = 100;
        }

        if ($new != $init) {
            foreach ($new as $item => $percentage) {
                $this->$item = $percentage;
            }

            $protext = null;
            if (is_array($what)) {
                foreach ($new as $k => $p) {
                    $protext .= $p.'% '.$k.' ';
                }
            } else {
                $protext .= '100% '.substr($what, 4);
            }

            log_country_message($this->cnum, "--- Set indy production: ".$protext);
            set_indy($this);
        } else {
            $protext = null;
            if (is_array($what)) {
                foreach ($new as $k => $p) {
                    $protext .= $p.'% '.$k.' ';
                }
            } else {
                $protext .= '100% '.substr($what, 4);
            }

            log_country_message($this->cnum, "--- Indy production: ".$protext);
        }
    }//end setIndy()


    /**
     * How much money it will cost to run turns
     * @param  int $turns turns we want to run (or all)
     * @return cost        money
     */
    public function runCash($turns = null)
    {
        if ($turns == null) {
            $turns = $this->turns;
        }

        return min(0, $this->income) * $turns;
    }//end runCash()


    //GOAL functions
    /**
     * [nlg_target description]
     * @param float $powfactor Power Factor
     *
     * @return int nlgTarget
     */
    public function nlgTarget($powfactor = 1.00)
    {
        if($this->turns_played + $this->turns + $this->turns_stored < 360)
            return 0;
        else {
            //lets lower it from 80+turns_playwed/7, to compete
            return floor(80 + pow($this->turns_played + $this->turns + $this->turns_stored, $powfactor) / 15);
        }
    }//end nlgTarget()


    /**
     * A crude Defence Per Acre number
     * $cpref - country preference object
     * @param float $mult      multiplication factor
     *
     *  @param float $powfactor power factor
     *
     * @return int DPATarget
     */
    public function defPerAcreTarget($cpref = null, $mult = 1.5, $powfactor = 1.0)
    {
        // fine if rainbows don't pass this in, screw them
        if(isset($cpref) && $this->land < $cpref->min_land_to_buy_defense)
            $dpat = 0;
        else
            $dpat = floor(75 + pow($this->turns_played + $this->turns + $this->turns_stored, $powfactor) / 10) * $mult;
        //log_country_message($this->cnum, "DPAT: $dpat"); // too much log spam - Slagpit
        return $dpat;
    }//end defPerAcreTarget()


    /**
     * The amount of defence per Acre of Land
     * @return float
     */
    public function defPerAcre()
    {
        return round(0.01 * $this->pt_weap * (1 * $this->m_tr + 2 * $this->m_tu + 4 * $this->m_ta) / $this->land); // FUTURE: govt modifiers?
    }//end defPerAcre()


    public function totalDefense()
    {
        return floor(0.01 * $this->pt_weap * (1 * $this->m_tr + 2 * $this->m_tu + 4 * $this->m_ta)); // FUTURE: govt modifiers?
    }//end defPerAcre()   



    /**
     * Built Percentage
     * @return int Like, 81(%)
     */
    public function built()
    {
        return floor(100 * ($this->land - $this->empty) / $this->land);
    }//end built()


    /**
     * Networth/(Land*Govt)
     * @return int The NLG of the country
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
        // FUTURE: this is wrong - reps have a higher calculated NW/acre as a result

        return floor($this->networth / ($this->land * $govt));
    }//end nlg()


    /**
     * The float taxrate
     * @return {float} Like, 1.06, or 1.12, etc
     */
    public function tax()
    {
        return (100 + $this->g_tax) / 100;
    }//end tax()


    public function fullBuildCost()
    {
        return $this->empty * $this->build_cost;
    }//end fullBuildCost()



    /**
     * Find Highest Goal
     * @param  array $goals an array of goals to persue
     * @param  int   $skip  whtether or not to skip??
     *
     * @return string highest goal!
     */
    public function highestGoal($goals = [], $skip = 0)
    {
        global $market;
        $psum  = 0;
        $score = [];
        foreach ($goals as $goal) {
            if ($goal[0] == 't_agri') {
                $price           = PublicMarket::price('agri');
                $price           = $price > 500 ? $price : 10000;
                $score['t_agri'] = ($goal[1] - $this->pt_agri) / ($goal[1] - 100) * $goal[2] * (2500 / $price);
            } elseif ($goal[0] == 't_indy') {
                $price           = PublicMarket::price('indy');
                $price           = $price > 500 ? $price : 10000;
                $score['t_indy'] = ($goal[1] - $this->pt_indy) / ($goal[1] - 100) * $goal[2] * (2500 / $price);
            } elseif ($goal[0] == 't_bus') {
                $price          = PublicMarket::price('bus');
                $price          = $price > 500 ? $price : 10000;
                $score['t_bus'] = ($goal[1] - $this->pt_bus) / ($goal[1] - 100) * $goal[2] * (2500 / $price);
            } elseif ($goal[0] == 't_res') {
                $price          = PublicMarket::price('res');
                $price          = $price > 500 ? $price : 10000;
                $score['t_res'] = ($goal[1] - $this->pt_res) / ($goal[1] - 100) * $goal[2] * (2500 / $price);
            } elseif ($goal[0] == 't_mil') {
                $price          = PublicMarket::price('mil');
                $price          = $price > 500 ? $price : 10000;
                $score['t_mil'] = ($this->pt_mil - $goal[1]) / (100 - $goal[1]) * $goal[2] * (2500 / $price);
            } elseif ($goal[0] == 'nlg') {
                $target       = $this->nlgt ?? 1 + $this->nlgTarget(); // temp hack to avoid division by 0
                $score['nlg'] = ($target - 1 + $this->nlg()) / $target * $goal[2];
            } elseif ($goal[0] == 'dpa') {
                $target       = $this->dpat ?? 1 + $this->defPerAcreTarget();
                $actual       = 1 + $this->defPerAcre();
                $score['dpa'] = ($target - $actual) / $target * $goal[2];
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
    }//end highestGoal()

    /**
     * Convoluted ladder logic to buy whichever goal is least fulfilled
     * @param  object  $c             the country object
     * @param  array   $goals         an array of goals to persue
     * @param  int     $spend         money to spend
     * @param  int     $spend_partial intermediate money, for recursion
     * @param  integer $skip          goal to skip due to failure
     * @return void
     */
    public static function countryGoals(&$c, $goals = [], $spend = null, $spend_partial = null, $skip = 0)
    {
        if (empty($goals)) {
            return;
        }

        $c->goals = $goals;

        if (isset($goals['dpa'])) {
            $c->dpat = $goals['dpa'];
        }

        if (isset($goals['nlg'])) {
            $c->nlgt = $goals['nlg'];
        }

        if ($spend == null) {
            $spend = $c->money;
        }

        if ($spend_partial == null) {
            $spend_partial = $spend;
            //$spend_partial = $spend / 3;
        }

        if ($spend_partial < 1000000) {
            $spend_partial = $spend;
        }

        if ($spend_partial < 1000000) {
            return;
        }

        global $cpref_file;
        $tol = $cpref_file->price_tolerance; //should be between 0.5 and 1.5

        $what = $c->highestGoal($goals, $skip);
        //out("Highest Goal: ".$what.' Buy $'.$spend_partial);
        $diff      = 0;
        $techprice = 8000 * $tol;
        if ($what == 't_agri') {
            $o = $c->money;
            PublicMarket::buy_tech($c, 't_agri', $spend_partial, $techprice);
            $diff = $c->money - $o;
        } elseif ($what == 't_indy') {
            $o = $c->money;
            PublicMarket::buy_tech($c, 't_indy', $spend_partial, $techprice);
            $diff = $c->money - $o;
        } elseif ($what == 't_bus') {
            $o = $c->money;
            PublicMarket::buy_tech($c, 't_bus', $spend_partial, $techprice);
            $diff = $c->money - $o;
        } elseif ($what == 't_res') {
            $o = $c->money;
            PublicMarket::buy_tech($c, 't_res', $spend_partial, $techprice);
            $diff = $c->money - $o;
        } elseif ($what == 't_mil') {
            $o = $c->money;
            PublicMarket::buy_tech($c, 't_mil', $spend_partial, $techprice);
            $diff = $c->money - $o;
        } elseif ($what == 'nlg') {
            $o = $c->money;
            defend_self($c, floor($c->money - $spend_partial)); //second param is *RESERVE* cash
            $diff = $c->money - $o;
        } elseif ($what == 'dpa') {
            $o = $c->money;
            defend_self($c, floor($c->money - $spend_partial)); //second param is *RESERVE* cash
            $diff = $c->money - $o;
        }/* elseif ($what == 'food') {
            $o = $c->money;
            PublicMarket::buy($c, ['m_bu' => $quantity], ['m_bu' => $market_price]);

            PublicMarket::buy($c, 't_bus', $spend_partial, $techprice);
            $diff = $c->money - $o;
        }*/

        if ($diff == 0) {
            $skip++;
        }

        $spend -= $spend_partial;
        //10000 because that's how much one tech point *could* cost, and i don't want it to get too ridiculous
        if ($spend > 10000 && $skip < count($goals) - 1) {
            self::countryGoals($c, $goals, $spend, $spend_partial, $skip);
        }
    }//end countryGoals()


    /**
     * Output country stats
     *
     * @param  string $strat The strategy
     * @param  array  $goals The goals
     *
     * @return null
     */
    public function countryStats($strat, $goals = [])
    {
        $land = str_pad(engnot($this->land), 8, ' ', STR_PAD_LEFT);
        $netw = str_pad(engnot($this->networth), 8, ' ', STR_PAD_LEFT);
        $govt = str_pad($this->govt, 8, ' ', STR_PAD_LEFT);
        $t_pl = str_pad($this->turns_played, 8, ' ', STR_PAD_LEFT);
        $goal = str_pad($this->highestGoal($goals), 8, ' ', STR_PAD_LEFT);
        $pmil = str_pad($this->pt_mil.'%', 8, ' ', STR_PAD_LEFT);
        $pbus = str_pad($this->pt_bus.'%', 8, ' ', STR_PAD_LEFT);
        $pres = str_pad($this->pt_res.'%', 8, ' ', STR_PAD_LEFT);
        $pagr = str_pad($this->pt_agri.'%', 8, ' ', STR_PAD_LEFT);
        $pind = str_pad($this->pt_indy.'%', 8, ' ', STR_PAD_LEFT);
        $dpa  = str_pad($this->defPerAcre(), 8, ' ', STR_PAD_LEFT);
        $dpat = str_pad($this->dpat ?? $this->defPerAcreTarget(), 8, ' ', STR_PAD_LEFT);
        $nlg  = str_pad($this->nlg(), 8, ' ', STR_PAD_LEFT);
        $nlgt = str_pad($this->nlgt ?? $this->nlgTarget(), 8, ' ', STR_PAD_LEFT);
        $cnum = $this->cnum;
        $url  = str_pad(siteURL($this->cnum), 8, ' ', STR_PAD_LEFT);
        $blt  = str_pad($this->built().'%', 8, ' ', STR_PAD_LEFT);
        $bpt  = str_pad($this->bpt, 8, ' ', STR_PAD_LEFT);
        $tpt  = str_pad($this->tpt, 8, ' ', STR_PAD_LEFT);
        $cash = str_pad(engnot($this->money), 8, ' ', STR_PAD_LEFT);

        $s = "\n|  ";
        $e = "  |";

        $str = str_pad(' '.$strat." #".$cnum.' ', 78, '-', STR_PAD_BOTH).'|';


        $str .= $s.'Government:   '.$govt.'         NLG:        '.$nlg .'         Mil: '.$pmil.$e;
        $str .= $s.'Networth:     '.$netw.'         NLG Target: '.$nlgt.'         Bus: '.$pbus.$e;
        $str .= $s.'Land:         '.$land.'         DPA:        '.$dpa .'         Res: '.$pres.$e;
        $str .= $s.'Turns Played: '.$t_pl.'         DPA Target: '.$dpat.'         Agr: '.$pagr.$e;
        $str .= $s.'Built:        '.$blt. '         Goal:       '.$goal.'         Ind: '.$pind.$e;
        $str .= $s.'Cash:         '.$cash.'         BPT:        '.$bpt .'         TPT: '.$tpt .$e;
        $str .= "\n|".str_pad(' '.$url.' ', 77, '-', STR_PAD_BOTH).'|';

        log_country_message($this->cnum, $str);
    }//end countryStats()


    /**
     * Can we afford to build a full BPT?
     *
     * @return bool Afford T/F
     */
    public function affordBuildBPT()
    {
        if ($this->money < $this->bpt * $this->build_cost) {
            //not enough build money
            return false;
        }

        if ($this->income < 0 && $this->money < $this->bpt * $this->build_cost + $this->income) {
            //going to run out of money
            return false;
        }

        return true;
    }//end affordBuildBPT()



    /**
     * Check to see if we should build a single CS
     *
     * @param  int $target_bpt The target bpt
     * @param  int $low_bpt The bpt considered to be "low" in the code
     * 
     * @return bool            Build or not
     */
    public function shouldBuildSingleCS($target_bpt = 80, $low_bpt = 30)
    {
        if (!$this->empty) {
            //no empty land
            return false;
        }

        if ($this->money < $this->build_cost) {
            //not enough money to build
            return false;
        }

        if ($this->income < 0 && $this->money < $this->build_cost + $this->income) {
            //going to run out of money
            return false;
        }

        if ($this->bpt < $low_bpt && $this->built() <= 50) {
            //you have low BPT and low Builtings
            return true;
        } elseif ($this->bpt < $target_bpt && $this->b_cs % 4 != 0) {
            //you have a BPT below target, but aren't CS % 4
            //IF NOT YOU SHOULD BUILD 4!!
            return true;
        }//end countryStats()


            return false;
    }//end shouldBuildSingleCS()

    /**
     * Should we build indies to make spies?
     *
     * @return bool Yep or Nope
     */
    public function shouldBuildSpyIndies($target_bpt)
    {
        if ($this->empty < $this->bpt) {
            //not enough land
            return false;
        }

        if($this->bpt < $target_bpt) {
            // don't build indies until we have full cs
            return false;
        }

        if (!$this->affordBuildBPT()) {
            //can't afford to build a full BPT
            return false;
        }

        if ($this->turns_played > 300 && $this->b_indy < 2 * $this->bpt) {
            //We're out of protection and don't have two full BPT of indies
            return true;
        }

        return false;
    }//end shouldBuildSpyIndies()

    /**
     * Should we built 4 CS?
     *
     * @param  integer $target_bpt Target BPT
     *
     * @return bool                Yep or Nope
     */
    public function shouldBuildFourCS($target_bpt = 80)
    {
        if ($this->bpt >= $target_bpt) {
            //we're at the target!
            return false;
        }

        if ($this->turns < 4) {
            //not enough turns...
            return false;
        }

        if ($this->empty < 4) {
            //not enough land...
            return false;
        }

        if ($this->money < 4 * $this->build_cost) {
            //not enough money...
            return false;
        }

        if ($this->income < 0 && $this->money < 4 * $this->build_cost + 5 * $this->income) {
            //going to run out of money
            //use 5 because growth of military typically
            return false;
        }

        if ($this->foodnet < 0 && $this->food < $this->foodnet * -5) {
            //going to run out of food
            //use 5 because growth of pop & military typically
            return false;
        }

        return true;
    }//end shouldBuildFourCS()

    /**
     * Should we build a full BPT?
     *
     * @param int $target_bpt Target BPT
     *
     * @return bool Yep or Nope
     */
    public function shouldBuildFullBPT($target_bpt = null)
    {
        if ($this->empty < $this->bpt) {
            //not enough land
            return false;
        }

        if ($this->money < $this->bpt * $this->build_cost + ($this->income > 0 ? 0 : $this->income * -60)) {
            //do we have enough money? This accounts for 60 turns of burn if income < 0
            return false;
        }

        if ($target_bpt == null) {
            //we don't care about BPT for some reason
            return true;
        }

        if ($this->bpt / $target_bpt < 0.80 && rand(0, 1)) {
            //basically, if we're below 80% of our target bpt
            //we have a 50% chance of skipping building buildings
            //so that we can actually get our BPT up
            return false;
        }

        return true;
    }//end shouldBuildFullBPT()


    /**
     * Add a retal to the list
     *
     * @param int    $cnum The country number
     * @param string $type The attack type
     * @param int    $land The amount of land lost
     *
     * @return void
     */
    public static function addRetalDue($cnum, $type, $land)
    {
        global $cpref_file;

        if (!isset($cpref_file->retal[$cnum])) {
            $cpref_file->retal[$cnum] = ['cnum' => $cnum, 'num' => 1, 'land' => $land];
        } else {
             $cpref_file->retal[$cnum]['num']++;
             $cpref_file->retal[$cnum]['land'] += $land;
        }
    }//end addRetalDue()

    public static function listRetalsDue()
    {
        global $cpref_file, $cnum;

        if (!$cpref_file->retal) {
            log_country_message($cnum, "Retals Due: None!");
            return;
        }

        log_country_message($cnum, "Retals Due:");

        $retals = (array)$cpref_file->retal;

        usort(
            $retals,
            function ($a, $b) {
                return $a['land'] <=> $b['land'];
            }
        );

        foreach ($retals as $list) {
            $country = Search::country($list['cnum']);
            if ($country == null) {
                continue;
            }

            log_country_message($cnum, 
                "Country: ".str_pad($country->cname, 32).str_pad(" (#".$list['cnum'].')', 9, ' ', STR_PAD_LEFT).
                ' x '.str_pad($list['num'], 4, ' ', STR_PAD_LEFT).
                ' or '.str_pad($list['land'], 6, ' ', STR_PAD_LEFT).' Acres'
            );
        }
    }//end listRetalsDue()
}//end class
