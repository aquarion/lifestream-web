<?php

# Obtain the JSON payload from an OwnTracks app POSTed via HTTP
# and insert into database table.

header("Content-type: application/json");
define('SEND_JSON_ERRORS', true);

require "../../lib/lifestream.inc.php";

$json = file_get_contents('php://input');

getDatabase();
ORM::configure('logging', true);
ORM::configure('driver_options', array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
$query = ORM::for_table('lifestream');
ORM::configure('logger', function ($log_string, $query_time) {
    error_log($log_string . ' in ' . $query_time);
    send_to_slack($message, $slack_botname, "#general", ":robot:");
});

$slack_channel  = lifestream_config('plex', 'slack_channel');
$slack_botname  = lifestream_config('plex', 'slack_botname');

// send_to_slack($json, $slack_botname, "#general", ":robot:");

$payload = file_get_contents("php://input");

$data =  @json_decode($payload, true);

if ($data['_type'] == 'location') {

    // acc Accuracy of the reported location in meters without unit (iOS,Android/integer/meters/optional)
    // alt Altitude measured above sea level (iOS,Android/integer/meters/optional)
    // batt Device battery level (iOS,Android/integer/percent/optional)
    // cog Course over ground (iOS/integer/degree/optional)
    // lat latitude (iOS,Android/float/meters/required)
    // lon longitude (iOS,Android/float/meters/required)
    // rad radius around the region when entering/leaving (iOS/integer/meters/optional)
    // t trigger for the location report (iOS,Android/string/optional)
    // p ping issued randomly by background task (iOS,Android)
    // c circular region enter/leave event (iOS,Android)
    // b beacon region enter/leave event (iOS)
    // r response to a reportLocation cmd message (iOS,Android)
    // u manual publish requested by the user (iOS,Android)
    // t timer based publish in move move (iOS)
    // v updated by Settings/Privacy/Locations Services/System Services/Frequent Locations monitoring (iOS)
    // tid Tracker ID used to display the initials of a user (iOS,Android/string/optional) required for http mode
    // tst UNIX epoch timestamp in seconds of the location fix (iOS,Android/integer/epoch/required)
    // vac vertical accuracy of the alt element (iOS/integer/meters/optional)
    // vel velocity (iOS,Android/integer/kmh/optional)
    // p barometric pressure (iOS/float/kPa/optional/extended data)
    // conn Internet connectivity status (route to host) when the message is created (iOS,Android/string/optional/extended data)
    // w phone is connected to a WiFi connection (iOS,Android)
    // o phone is offline (iOS,Android)
    // m mobile data (iOS,Android)
    // cp copy mode enabled; only if true, missing otherwise (iOS)
    // topic (only in HTTP payloads) contains the original publish topic (e.g. owntracks/jane/phone). (iOS)
    // inregions contains a list of regions the device is currently in (e.g. ["Home","Garage"]). Might be empty. (iOS,Android/list of strings/optional)

    //add_location($timestamp, $source, $lat, $lon, $title, $icon=False, $alt=0, $fulldata_json = null, $device = 'unset', $accuracy = 0)
    add_location(
        $data['tst'],  //timestamp
        'owntracks',   //source
        $data['lat'],
        $data['lon'],  // Lat, Lon
        isset($data['inregions']) ? implode(' / ', $data['inregions']) : '', #Title
        $icon = false, # Icon
        $alt = $data['alt'], # Alt
        $fulldata_json = json_encode($data), # Fulldata
        $device = $data['tid'], # Device
        $accuracy = $data['acc']
    ); #accuracy
}
raw_location_data($data['_type'], $data);

$response = array();
# optionally add objects to return to the app (e.g.
# friends or cards)
print json_encode($response);
