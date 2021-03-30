<?php namespace EENPC;

/*
You must copy or save this file to config.php
and fill out your AI API KEY and your USERNAME
*/


$config = array(
    'ai_key' => 'your-ai-api-key-here',
    'username' => 'your-username-here',
    'base_url' => 'https://www.earthempires.com/api',    //Don't change this unless qz tells you to =D it needs to end in /api either way
    'server' => 'ai',       //don't change this
    'turnsleep' => 500000,    //don't get too ridiculously fast; 500000 is half a second
    'save_settings_file' => 'settings.json',
	'log_country_info_to_screen' => true,
	'log_to_local_files' => false, // probably only works on linux or WSL
	'local_path_for_log_files' => '.',
	'log_to_server_files' => false //don't change this
);
