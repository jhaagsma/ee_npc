<?php
/**
 * This file handles communication with the EE server
 * It should be torn apart a little bit
 *
 * PHP Version 7
 *
 * @category Comms
 * @package  EENPC
 * @author   Julian Haagsma aka qzjul <jhaagsma@gmail.com>
 * @license  MIT License
 * @link     https://github.com/jhaagsma/ee_npc/
 */
namespace EENPC;

/*
This file holds the communications with the EE server, so that we can keep
only the real bot logic in the ee_npc file...
*/

///DATA HANDLING AND OUTPUT

/**
 * Main Communication
 * @param  string $function       which string to call
 * @param  array  $parameterArray parameters to send
 * @return object                 a JSON object converted to class
 */
function ee($function, $parameterArray = [])
{
    global $baseURL, $username, $aiKey, $serv, $cnum, $APICalls;

    $init                       = $parameterArray;
    $parameterArray['ai_key']   = $aiKey;
    $parameterArray['username'] = $username;
    $parameterArray['server']   = $serv;
    if ($cnum) {
        $parameterArray['cnum'] = $cnum;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseURL);
    curl_setopt($ch, CURLOPT_POST, 1);
    $send = "api_function=".$function."&api_payload=".json_encode($parameterArray);
    //log_main_message($send);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $send);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    // receive server response ...
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $serverOutput = curl_exec($ch);

    curl_close($ch);

    $APICalls++;

    $return = handle_output($serverOutput, $function, $cnum);
    if ($return === false) {
        out_data($init);
    }

    //log_main_message($function);
    return $return;
}//end ee()

/**
 * Get the server; handle EE being down
 *
 * @return object The server info
 */
function getServer($refresh_logging_directory = false)
{
    $server_loaded = false;
    $server        = null;
    while (!$server_loaded) {
        if ($server_loaded === false) {
            $server = ee('server');
            if (is_object($server)) {
                $server_loaded = true;
            }
        }

        if (!$server_loaded) {
            log_error_message(108, null, "Server didn't load, try again in 2...");
            sleep(2); //try again in 2 seconds.
        }
    }

    if($refresh_logging_directory) // create new folders for a new round if needed
        create_logging_directories($server, 0);

    return $server;
}//end getServer()

/**
 * Get the rules; handle EE being down
 *
 * @return object The rules
 */
function getRules()
{
    $rules_loaded = false;
    $rules        = null;
    while (!$rules_loaded) {
        if ($rules_loaded === false) {
            $rules = ee('rules');
            if (is_object($rules)) {
                $rules_loaded = true;
            }
        }

        if (!$rules_loaded) {
            log_error_message(109, null, "Rules didn't load, try again in 2...");
            sleep(2); //try again in 2 seconds.
        }
    }

    return $rules;
}//end getRules()


/**
 * Handle the server output
 * @param  JSON   $serverOutput JSON return
 * @param  string $function     function to call
 * @return object               json object -> class
 */
function handle_output($serverOutput, $function, $cnum) // $cnum may not be set
{
    $response = json_decode($serverOutput);
    //if($function == 'OPTIMAL_TECH')
    //    log_country_data($cnum, $response, "Response json decode:");
    if (!$response) {
        log_error_message(100, $cnum, 'Not acceptable response: '. $function .' - '. $serverOutput);
        return false;
    }

    // if ($function == 'buy') {
    //     out("DEBUGGING BUY");
    //     out_data($response);
    // }

    $message  = key($response);
    $response = $response->$message ?? null;
    //$parts = explode(':', $serverOutput, 2);
    //This will simply kill the script if EE returns with an error
    //This is to avoid foulups, and to simplify the code checking above
    if ($message == 'COUNTRY_IS_DEAD') {
        log_country_message($cnum, "Country is Dead!");
        return null;
    } elseif ($message == 'OWNED') {
        log_error_message(101, $cnum, "Trying to sell more than owned!"); // FUTURE: message should have something helpful for debugging
        return null;
    } elseif ($message == "ERROR" && $response == "MAXIMUM_COUNTRIES_REACHED") {
        log_main_message("Already have total allowed countries so refreshing server...");
        global $server; //do all this with a class sometime soon
        $server = ee('server'); // I think it's fine for this to not refresh the logging directories
        return null;
    } elseif ($message == "ERROR" && $response == "MONEY") {
        log_error_message(102, $cnum, "Not enough Money for $function"); // FUTURE: message should have something helpful for debugging
        return null;
    } elseif ($message == "ERROR" && $response == "NOT_ENOUGH_TURNS") {
        log_error_message(103, $cnum, "Not enough Turns!"); // FUTURE: message should have something helpful for debugging
        return null;
    } elseif ($function == 'ally/offer' && $message == "ERROR" && $response == "disallowed_by_server") {
        log_error_message(104, $cnum, "Allies are not allowed!");
        Allies::$allowed = false;
        return null;
    } elseif (expected_result($function) && $message != expected_result($function)) {
        if (is_object($message) || is_object($response)) {
            out_data($message);
            out_data($response);
            log_main_message("Server Output: \n".$serverOutput);
            log_error_message(105, $cnum, "Server Output: \n".$serverOutput);

            return $response;
        }

        log_error_message(106, $cnum, "\n\nUnexpected Result for '$function': ".$message.':'.$response."\n\n");

        return $response;
    } elseif (!expected_result($function)) {
        $error_message = "Function: ".($function ?? "")."\nMessage: ".($message ?? "")."\nServer Output: \n".($serverOutput ?? "");
        log_error_message(107, $cnum, $error_message);
        out_data($response);
        //log_country_or_main_message($cnum, $function);
        //log_country_or_main_message($cnum, $message);
        //out_data($response);
        //log_country_or_main_message($cnum, "Server Output: \n".$serverOutput);
        return false;
    }

    return $response;
}//end handle_output()


/**
 * just verifies that these things exist
 * @param  string $input Whatever the game returned
 * @return string        proper result
 */
function expected_result($input)
{
    global $lastFunction;
    $lastFunction = $input;
    //This is simply a list of expected return values for each function
    //This allows us to quickly verify if an error occurred
    $bits = explode('/', $lastFunction);
    if ($bits[0] == 'ranks' && isset($bits[1]) && is_numeric($bits[1])) {
        $lastFunction = 'ranks/{cnum}';
    }

    $expected = [
        'server' => 'SERVER_INFO',
        'create' => 'CNUM',
        'advisor' => 'ADVISOR',
        'main' => 'MAIN',
        'build' => 'BUILD',
        'destroy' => 'DESTROY',
        'explore' => 'EXPLORE',
        'cash' => 'CASH',
        'pm_info' => 'PM_INFO',
        'pm' => 'PM',
        'tech' => 'TECH',
        'market' => 'MARKET',
        'onmarket' => 'ONMARKET',
        'market_search' => 'MARKET_SEARCH',
        'buy' => 'BUY',
        'sell' => 'SELL',
        'market_recall' => 'MARKET_RECALL',
        'govt' => 'GOVT',
        'rules' => 'RULES',
        'indy' => 'INDY',
        'ally/list' => 'ALLYLIST',
        'ally/info' => 'ALLYINFO',
        'ally/candidates' => 'ALLYCANDIDATES',
        'ally/offer' => 'ALLYOFFER',
        'ally/accept' => 'ALLYACCEPT',
        'ally/cancel' => 'ALLYCANCEL',
        'gdi/join' => 'GDIJOIN',
        'gdi/leave' => 'GDILEAVE',
        'events' => 'NEW_EVENTS',
        'ranks/{cnum}' => 'SEARCH',
        'get_optimal_tech_buying_info' => 'OPTIMAL_TECH'
    ];

    return $expected[$lastFunction] ?? null;
}//end expected_result()


/**
 * Does count() in some case where it doesn't work right
 * @param  object $data probably a $result object
 * @return int       count of things in $data
 */
function actual_count($data)
{
    //do not ask me why, but count() doesn't work on $result->turns
    $i = 0;
    foreach ($data as $stuff) {
        $i++;
        $stuff = $stuff; //keep the linter happy
    }

    return $i;
}//end actual_count()
