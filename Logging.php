<?php

namespace EENPC;

// TODO: add communication functions
// TODO: add ee_npc code to get and validate config parameters


function local_directory_exists($local_file_path) {
    // TODO: implement
    return false;
}

function format_local_file_path($local_file_path) {
    // TODO: implement
    // make work on windows and linux ideally?
    return $local_file_path;
}



function initialize_country_logging_file($server, $cnum) {
    global $log_to_local, $log_to_server, $local_file_path;
    $full_local_file_path_and_name = ($log_to_local ? get_full_country_file_path_and_name($local_file_path, $cnum) : null);
    return initialize_for_targets($log_to_local, $log_to_server, $full_local_file_path_and_name);
}


function initialize_error_logging_file($server) {
    global $log_to_local, $log_to_server, $local_file_path;
    $full_local_file_path_and_name = ($log_to_local ? get_full_error_file_path_and_name($local_file_path) : null);
    return initialize_for_targets($log_to_local, $log_to_server, $full_local_file_path_and_name);
}

function initialize_main_logging_file($server) {
    global $log_to_local, $log_to_server, $local_file_path;
    $full_local_file_path_and_name = ($log_to_local ? get_full_main_file_path_and_name($local_file_path, $cnum) : null);
    return initialize_for_targets($log_to_local, $log_to_server, $full_local_file_path_and_name);
}


function initialize_for_targets($log_to_local, $log_to_server, $full_local_file_path_and_name) {
    // TODO: implement

    if($log_to_local) {
        // check if dir exists, create if not, check if file exists, create if not
        // catch and print any errors
        // "testdir\\subdir\\test"

        // https://www.php.net/manual/en/function.is-dir.php
        // https://www.php.net/manual/en/function.mkdir.php

    }

    if($log_to_server) {
        // call functions in communication, catch and print any errors
    }  

    return;
}


function get_full_country_file_path_and_name($local_file_path, $cnum) {
    // TODO: implement
    // should these three functions call another function?
    return;
}

function get_full_error_file_path_and_name($local_file_path) {
    // TODO: implement
    return;
}

function get_full_main_file_path_and_name($local_file_path) {
    // TODO: implement
    return;
}


function log_to_targets($log_to_screen, $log_to_local, $log_to_server, $full_local_file_path_and_name, $error_type, $message, $cnum) {
    // TODO: implement

    if($log_to_screen)
        out("Country #$cnum: $message");

    if($log_to_local) {
        // write to $local_file_path, catch and print any errors

    }

    if($log_to_server) {
        // call function in communication, catch and print any errors
    }

    return;
}



// examples of things to log: what decisions were made (and why), how turns are spent
function log_country_message($cnum, $message) {
    global $log_to_screen, $log_to_local, $log_to_server, $local_file_path;

    if(!$cnum) {
        $message = "ERROR: must call log_country_message() with a valid cnum";
        out($message);
        log_error_message(2, $cnum, $message);
        return;
    }

    $full_local_file_path_and_name = ($log_to_local ? get_full_country_file_path_and_name($local_file_path, $cnum) : null);
    return log_to_targets($log_to_screen, $log_to_local, $log_to_server, $full_local_file_path_and_name, null, $message, $cnum);
}



function log_main_message($message)  {
    global $log_to_screen, $log_to_local, $log_to_server, $local_file_path;
    $full_local_file_path_and_name = ($log_to_local ? get_full_main_file_path_and_name($local_file_path, $cnum) : null);
    return log_to_targets($log_to_screen, $log_to_local, $log_to_server, $full_local_file_path_and_name, $error_type, $message, $cnum);
}

function log_error_message($error_type, $cnum, $message) {
    global $log_to_screen, $log_to_local, $log_to_server, $local_file_path;

    if(!$cnum) {
        $message = "ERROR: must call log_error_message() with a valid cnum";
        out($message);
        log_error_message(1, $cnum, $message);
        return;
    }

    if($error_type == null or $error_type < 0) {
        $message = "ERROR: must call log_error_message() with a valid type (non-negative int)";
        out($message);
        log_error_message(0, $cnum, $message);
        return;
    }

    $full_local_file_path_and_name = ($log_to_local ? get_full_error_file_path_and_name($local_file_path) : null);
    return log_to_targets($log_to_screen, $log_to_local, $log_to_server, $full_local_file_path_and_name, $error_type, $message, $cnum);
}

function log_get_name_of_error_type($error_type) {
    if($error_type == 0)
        return 'Called log_error_message() with invalid $error_type';
    if($error_type == 1)
        return 'Called log_error_message() with invalid $cnum';
    if($error_type == 2)
        return 'Called log_country_message() with invalid $cnum';
    else
        return 'ERROR: log_get_name_of_error_type() called with unmapped $error_type';
}



function purge_old_logging_files($server)  {
    global $log_to_local, $log_to_server, $local_file_path;

    // TODO: implement

    // https://www.php.net/manual/en/function.unlink.php
    // https://www.php.net/manual/en/function.rmdir.php
    return;
}


function log_translate_forced_debug($boolean_value) {
    return ($boolean_value ? ' forced by debug variable' : '');
}

function log_translate_boolean_to_YN($boolean_value) {
    return ($boolean_value ? 'yes' : 'no');
}

function log_translate_instant_to_human_readable($instant) {
    return date('m/d/Y H:i:s', $instant);
}

