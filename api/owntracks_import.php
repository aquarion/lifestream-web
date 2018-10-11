<?php

if(php_sapi_name() !== 'cli'){
    die("Command line only");
}

define('SEND_TEXT_ERRORS', true);
require "../lib/lifestream.inc.php";

$json = file_get_contents('php://input');

getDatabase();
ORM::configure('logging', true);
ORM::configure('driver_options', array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
$query = ORM::for_table('lifestream');

$slack_channel  = lifestream_config('plex', 'slack_channel');
$slack_botname  = lifestream_config('plex', 'slack_botname');

$records = ORM::for_table('owntracks_unhandled')->where('type', 'location')->find_result_set();

foreach($records as $i => $record){


    $data = json_decode($record->get('fulldata_json'));

    add_location(
        $data->tst, 
        'owntracks', 
        $data->lat, $data->lon, 
        isset($data->inregions) ? implode(' / ', $data->inregions) : '', 
        //$icon=False, 
        $alt=$data->alt, 
        $fulldata_json = json_encode($data),
        $device = $data->tid,
        $accuracy=$data->acc);
    print($i."/".count($records)."\n");
}


// add_location(
//     $data->tst, 
//     'owntracks', 
//     $data->lat, $data->lon, 
//     isset($data->inregions) ? implode(' / ', $data->inregions) : '', 
//     //$icon=False, 
//     $alt=$data->alt, 
//     $fulldata_json = json_encode($data),
//     $device = $data->tid,
//     $accuracy=$data->acc);

?>