<?php

define('SEND_JSON_ERRORS', true);
header('content-type: application/json; charset: utf-8');
require("../lib/lifestream.inc.php");

getDatabase();
ORM::configure('logging', true);
ORM::configure('driver_options', array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
$query = ORM::for_table('lifestream');

$blocksize = 100;
$next = 30;
$max = false;
$append = "append";
$ordered = false;

$message = "";

if (isset($_REQUEST['after'])) {
    $query->where_gte("date_updated", $_POST['after']);
    $append = "prepend";
}

$query->where_not_null("title");
$query->where_not_equal("source", "tumblr");
$query->where_not_equal("source", "lastfm");

if (isset($_REQUEST['path'])) {
    $split = explode("/", $_REQUEST['path']);
    array_shift($split);
} else {
    $split = array();
}

if (count($split) && $split[0] == "search") {
    define("DO_LOCATIONS", false);

    $split[1] = urldecode($split[1]);

    $message = $title = sprintf("Search for \"%s\"", $split[1]);
    $query->where_like("title", sprintf("%%%s%%", $split[1]));

    if (!$ordered) {
        $query->order_by_asc("date_created");
    }
    $from = $back = $forward = false;
    // $back = date("/Y/m/d", $from - A_DAY);
    // $forward = date("/Y/m/d", $from + A_DAY);
    $up = "/";
} else {
    define("DO_LOCATIONS", true);

    $last = end($split);
    reset($split);

    if ($last == "") {
        array_pop($split);
    }

    $ordered = false;
    $title = false;

    $location_before_ts = false;
    $location_after_ts = false;

    if (count($split) == 1) {
        // One Year

        $from = mktime(0, 0, 0, 1, 1, $split[0]);
        $to = strtotime("+1 year", $from) - 1;
        $query->where_gt("date_created", date("Y-m-d 00:00", $from));
        $query->where_lt("date_created", date("Y-m-d 00:00", $to));

        //$location_query->where_gt("timestamp", date("Y-m-d 00:00", $from));
        //$location_query->where_lt("timestamp", date("Y-m-d 00:00", $to));
        $location_after_ts = date("Y-m-d 00:00", $from);
        $location_before_ts = date("Y-m-d 00:00", $to);

        $message = sprintf("Year from %s to %s", date("Y-m-d", $from), date("Y-m-d", $to));

        $back = date("/Y", $from - A_YEAR);
        $forward = date("/Y", $from + A_YEAR);
        $up = false;
    } elseif (count($split) == 2 && strpos($split[1], "wk") !== false) {
        // One Week
        $week = substr($split[1], 2);
        list($from, $to) = get_start_and_end_date_from_week($week, $split[0]);
        $query->where_gt("date_created", date("Y-m-d 00:00", $from));
        $query->where_lt("date_created", date("Y-m-d 00:00", $to));

        $location_after_ts = date("Y-m-d 00:00", $from);
        $location_before_ts = date("Y-m-d 00:00", $to);

        $message = sprintf("Week $week from %s to %s", date("Y-m-d", $from), date("Y-m-d", $to));

        $back = date("/Y/\w\kW", $from - A_WEEK);
        $forward = date("/Y/\w\kW", $from + A_WEEK + A_DAY);
        $up = date("/Y/m", $from);
    } elseif (count($split) == 2 && is_numeric($split[1])) {
        // One Month

        $from = mktime(0, 0, 0, intval($split[1]), 1, intval($split[0]));
        $to = mktime(0, 0, 0, intval($split[1] + 1), 1, intval($split[0])) - 1;

        $query->where_gt("date_created", date("Y-m-d 00:00", $from));
        $query->where_lt("date_created", date("Y-m-d 00:00", $to));

        $location_after_ts = date("Y-m-d 00:00", $from);
        $location_before_ts = date("Y-m-d 00:00", $to);

        $message = sprintf("Month from %s to %s", date("Y-m-d", $from), date("Y-m-d", $to));

        $back = date("/Y/m", $from - A_MONTH);
        $forward = date("/Y/m", $from + A_MONTH + A_DAY);
        $up = date("/Y", $from);
    } elseif (count($split) == 3 && is_numeric($split[1]) && $split[0] == "*") {
        // One Day, Any Year

        // mktime ($hour, $minute, $second, $month, $day, $year)
        $from = mktime(0, 0, 0, intval($split[1]), intval($split[2]), intval($split[0]));

        $query->where_raw('(MONTH(`date_created`) = ? AND DAYOFMONTH(`date_created`) = ?)', array(intval($split[2]), intval($split[1])));

        $location_before_ts = false;
        $location_after_ts = false;

        $message = sprintf("Day from %s to %s", date("Y-m-d 03:00", $from), date("Y-m-d 03:00", $from + A_DAY));
        //$message = print_r($split, 1);#sprintf("Month from %s to %s", date("Y-m-d 00:00", $from), date("Y-m-d 00:00", $to));

        $back = date("/Y/m/d", $from - A_DAY);
        $forward = date("/Y/m/d", $from + A_DAY);
        $up = date("/Y/\w\kW", $from);
    } elseif (count($split) == 3 && is_numeric($split[1])) {
        // One Day

        // mktime ($hour, $minute, $second, $month, $day, $year)
        $from = mktime(0, 0, 0, intval($split[1]), intval($split[2]), intval($split[0]));

        $query->where_gt("date_created", date("Y-m-d 03:00", $from));
        $query->where_lt("date_created", date("Y-m-d 03:00", $from + A_DAY));

        $location_after_ts = date("Y-m-d 03:00", $from);
        $location_before_ts = date("Y-m-d 03:00", $from + A_DAY);

        $message = sprintf("Day from %s to %s", date("Y-m-d 03:00", $from), date("Y-m-d 03:00", $from + A_DAY));
        //$message = print_r($split, 1);#sprintf("Month from %s to %s", date("Y-m-d 00:00", $from), date("Y-m-d 00:00", $to));

        $back = date("/Y/m/d", $from - A_DAY);
        $forward = date("/Y/m/d", $from + A_DAY);
        $up = date("/Y/\w\kW", $from);
    } else {
        // Last 200

        $max = 200;
        #$append = "prepend";
        $query->order_by_desc("date_created");

        $from = time() - (A_WEEK * 2);
        #$location_query->where_gt("timestamp", date("Y-m-d 03:00", $from ));
        $location_after_ts = date("Y-m-d 03:00", $from);

        $message = "This is the last $max things various services have seen me do.";
        $title = " Last 200 Items";
        $ordered = true;

        $from = time();
        $back = date("/Y/m/d", $from);
        $forward = false;
        $up = date("/Y/\w\kW", $from);
    }

    if (!$ordered) {
        $query->order_by_asc("date_created");
    }
}

$return = array(
    'status' => 200,
    'next' => $next,
    'today' => $from,
    'offset' => 0,
    'max' => 0,
    "direction" => $append,
    "message" => $message,
    "title" => $title ? $title : $message,
    'items' => array(),
    'nav' => array(
        'back' => $back,
        'forward' => $forward,
        'up' => $up,
    ),
);

if ($from > time()) {
    header("Content-Type: text/json");
    $return['items'][] = array(
        "source" => "xkcd",
        "title" => "<h2>That is the future. We will do things differently there.</h2>
				<a href=\"http://xkcd.com/338/\"><img src='http://imgs.xkcd.com/comics/future.png'></a>",
        "image" => "",
        "date_created" => time(),
    );
    print json_encode($return);
    die();
}

$countQuery = clone $query;
$return['max'] = $countQuery->count();

$query->limit($blocksize);

if (isset($_POST['offset'])) {
    $offset = intval($_POST['offset']);
    $query->offset($offset);
} else {
    $offset = 0;
}
$items = $query->find_array();

if ($return['max'] > ($offset + $blocksize)) {
    $return['offset'] = $offset + $blocksize;
    $return['next'] = 2;

    $percent = ($return['offset'] / ($max ? $max : $return['max'])) * 100;
    $return['message'] .= sprintf(" (%d%% Loaded)", $percent);
} else {
    $return['offset'] = 0;
}

if ($max && $return['offset'] >= $max) {
    $return['offset'] = 0;
}

foreach ($items as $row) {
    if ($row['image']) {
        $row['image'] = "//art.istic.net/lifestreamprox/?url=" . urlencode($row['image']);
    }
    $return['items'][] = $row;
}

$return['log'][] = ORM::get_last_query();

////////////////////////////////////////////////////// Locations

function generate_location_query($location_before_ts, $location_after_ts)
{
    $location_query = ORM::for_table('lifestream_locations');
    $location_query->select_expr("*");
    $location_query->select_expr("round(`long`) as `long`");
    // $location_query->where_raw("round(`long`) > 0");
    $location_query->select_expr("round(`lat`) as `lat`");
    // $location_query->where_raw("round(`lat`) > 0");
    $location_query->select_expr("count(*) as `value`");
    $location_query->group_by_expr('concat(round(`lat`,2),"/",round(`long`,2))');
    $location_query->where_gt("timestamp", $location_after_ts);
    if ($location_before_ts) {
        $location_query->where_lt("timestamp", $location_before_ts);
    } else {
        $location_query->order_by_desc("timestamp");
    }
    return $location_query;
}

if (DO_LOCATIONS && !$offset) {
    $openpath_query = generate_location_query($location_before_ts, $location_after_ts);
    $openpath_query->where_not_equal("source", "foursquare");
    $openpath_rows = $openpath_query->find_array();
    $return['log'][] = ORM::get_last_query();

    $foursquare_query = generate_location_query($location_before_ts, $location_after_ts);
    $foursquare_query->where("source", "foursquare");
    $foursquare_rows = $foursquare_query->find_array();
    $return['log'][] = ORM::get_last_query();
    // echo "<pre>";
    // var_dump($openpath_rows);
    // echo "\n\n";
    // var_dump(ORM::get_last_query());
    // die("HellO");
    $locations = array();
    $location_rows = array_merge($openpath_rows, $foursquare_rows);

    foreach ($openpath_rows as $row) {
        if (!$row['lat'] || !$row['long']) {
            continue;
        }

        if ($row['title']) {
            $title = $row['title'];
        } else {
            $title = $row['timestamp'];
        }

        $newentry = array("lat" => $row['lat_vague'], "long" => $row['long_vague'], 'title' => $title, 'value' => $row['value']);
        if ($row['icon']) {
            $newentry['icon'] = $row['icon'];
        }
        $locations[] = $newentry;
    }

    $return['locations'] = $locations;
}

#$return['items'] = array_reverse($return['items']);

header("Content-Type: application/json");
print json_encode($return);
