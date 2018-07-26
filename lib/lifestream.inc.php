<?PHP

$settings = parse_ini_file(getcwd().'/etc/config.ini') or die('Config file couldn\'t be read');


include("idiorm.php");


define("AN_HOUR", 60*60);
define("A_DAY", 60*60*24);
define("A_WEEK", 60*60*24*7);
define("A_MONTH", 60*60*24*30);
define("A_YEAR", 60*60*24*364);

define("IMAGE_ROOT", $settings['image_root']);

function get_start_and_end_date_from_week($w, $y)
{

    $date = mktime(0, 0, 0, 1, 4, $y); // 4th Jan is always week 1

    $days = ($w-1)*7;

    $date += $days*(24*60*60);

    $d = date("N", $date);# - 1;

    $from = $date - (($d) * A_DAY);
    $to   = $date + ((7-$d) * A_DAY) -1;

    return array($from, $to);
}    # function datefromweek

function niceTime($from, $to = false, $shortform = false)
{

    if (!$to) {
        $to = time();
    }

    if ($from > $to) {
        $since = $from - $to;
    } else {
        $since = $to - $from;
    }

        // 60 // minute
        // 3600 = hour
        // 86400 = day
        // 604800 = week

    if ($shortform) {
        $units = array ('sec','min','hr','day','wk','yr');
    } else {
        $units = array ('second','minute','hour','day','week','year');
    }

    if ($since < 60) {
        $date = $since;
        $unit = $units[0];
    } elseif ($since < 4000) {
        $date = round($since/60);
        $unit = $units[1];
    } elseif ($since < 82000) {
        $date = round($since/3600);
        $unit = $units[2];
    } elseif ($since < 603800) {
        $date = round($since/86400);
        $unit = $units[3];
        #$plus = " on ".date("jS M");
    } elseif ($since < 31440000) {
        $date = round($since/604800);
        $unit = $units[4];
    } else {
        $date = round($since/(604800 * 52));
        #$date = " over a year";
        $unit = $units[5];
    }

    if ($date == 1 || $unit == "") {
        $date = $date." ".$unit;
    } else {
        $date = $date." ".$unit."s";
    }

    if (!$shortform) {
        #$date .= " ".$plus;
    }

        #$date .= " (".$since.")";

        return $date;
}

function process_lifestream_item($row)
{
  
    $row['title'] = str_replace("â€“", "--", $row['title']);
    $row['title'] = str_replace("â€”", "--", $row['title']);

    $text = $row['title'];
    $row['originaltext'] = $row['title'];

    

    
    $row['content'] = twitterFormat($text);
    
    if (!$row['source']) {
        $row['source'] = $row['type'];
    }

    switch ($row['type']) {
        case "lastfm":
            $icon = IMAGE_ROOT.'silk/music.png';
            break;
        
        case "gaming":
            $icon = IMAGE_ROOT.'silk/joystick.png';
            if ($row['source'] == "Champions Online") {
                $icon = IMAGE_ROOT.'games/ChampionsOnline.png';
                $row['url'] = "http://www.champions-online.com/character_profiles/user_characters/Jascain";
            } elseif ($row['source'] == "HeroStats") {
                $icon = IMAGE_ROOT.'games/city_of_heroes/Hero.png';
                $row['small_icon'] = IMAGE_ROOT.'games/cityofheroes.png';
                $row['url'] = "http://cit.cohtitan.com/profile/13610";
            } elseif ($row['source'] == "Raptr" && preg_match('#Champions Online! #', $text)) {
                $row['content'] .= "#";
            } elseif ($row['source'] == "XLN Live Integration") {
                    $icon = IMAGE_ROOT.'silk/controller.png';
                    $row['url'] = "http://live.xbox.com/en-GB/profile/profile.aspx?pp=0&GamerTag=Jascain";
            } elseif (preg_match('#\#wow#', $text)) {
                $row['source'] = "World of Warcraft";
                $icon = IMAGE_ROOT.'games/world_of_warcraft.png';
            }
            break;

        case "steam":
            $icon = IMAGE_ROOT.'games/steam.png';
            $row['small_image'] = IMAGE_ROOT.'games/steam_small.png';
            #$row['url'] = "http://steamcommunity.com/id/aquarion/";
            $row['title'] = "Achieved: ".$row['title'];
            break;

        case "apps":
        case "location":
            if (preg_match("#^I\S* \w* a YouTube video#", $row['content'])) {
                $icon = IMAGE_ROOT.'silk/film_add.png';
                $row['image'] = $icon;
                $match = preg_match("#I\S* \w* a YouTube video -- (.*?) (http.*)#", $row['originaltext'], $matches);
            
                $row['content'] = sprintf('<a href="%s">%s</a>', $matches[2], $matches[1]);
                $row['source'] = "YouTube";
            } elseif ($row['source'] == "LOVEFiLM.com Updates") {
                $match = preg_match("#(Played|Watched|Has been sent) (.*?): (http://LOVEFiLM.com/r/\S*)#", $row['originaltext'], $matches);

                if ($match) {
                    $row['content'] = sprintf('%s <a href="%s">%s</a>', $matches[1], $matches[3], $matches[2]);
                }

                $icon = IMAGE_ROOT.'other/favicon.png';
                $row['source'] = "LOVEFiLM";
            } elseif (strtolower($row['source']) == "foursquare"
                    or strtolower($row['source']) == "foursquare-mayor"
                  ) {
                if ($row['source'] == "Foursquare-Mayor") {
                      $icon = IMAGE_ROOT.'foursquare%20icons/mayorCrown.png';
                } else {
                    $icon = IMAGE_ROOT.'foursquare%20icons/foursquare%20256x256.png';
                }
          
                $row['content'] = preg_replace("/#\w*/", "", $row['originaltext']);


                #preg_match("#(http://\S*)#", $row['content'], $matches);

                #echo $row['originaltext']."<br/>";
            
                $imat = preg_match("#I'm at (.*?) \((.*?)\)\. (http://\S*)#", $row['originaltext'], $matches);


                if ($imat) {
                    $row['content'] = sprintf('I\'m at <a href="%s">%s</a> (%s)', $matches[3], $matches[1], $matches[2]);
                } else {
                    $row['content'] = twitterFormat($row['content']);
                }
            
                if (isset($matches[1])) {
                    $row['url'] = $matches[1];
                }
                #$row['content'] = preg_replace("#http://\S*#", "", $row['content']);
            
                #$row['content'] = twitterFormat($row['content']);
            
                #$row['url'] = "http://www.champions-online.com/character_profiles/user_characters/Jascain";
            } elseif ($row['source'] == "Kindle") {
                $icon = IMAGE_ROOT.'silk/book_open.png';
            } elseif ($row['source'] == "Miso") {
                $icon = IMAGE_ROOT.'silk/television.png';
                preg_match("#(http://\S*)#", $row['originaltext'], $matches);
                $row['url'] = $matches[1];
                $row['content'] = preg_replace("# http://\S*#", "", $row['originaltext']);
            } elseif ($row['source'] == "Untappd") {
                $icon = IMAGE_ROOT.'other/beer.png';
                $row['small_icon'] = IMAGE_ROOT.'silk/drink.png';

                preg_match("#(http://\S*)#", $row['originaltext'], $matches);
                $row['url'] = $matches[1];
                $row['content'] = preg_replace("# http://\S*#", "", $row['originaltext']);
            }
            break;


      


        case "twitter":
            $icon = IMAGE_ROOT.'twitter/Twitter-64.png';
            $row['small_icon'] = IMAGE_ROOT.'twitter/rounded-plain-16x16/twitter-02.png';

            switch ($row['source']) {
                case "Steepster":
                    $icon = IMAGE_ROOT.'silk/cup.png';
                    $row['content'] = preg_replace("/#\w*/", "", $row['originaltext']);
                    preg_match("#(http://\S*)#", $row['content'], $matches);
                    $row['url'] = $matches[1];
                    $row['content'] = preg_replace("#: http://\S*#", "", $row['content']);
                    break;

                case "Goodreads":
                    $icon = IMAGE_ROOT.'silk/book_open.png';
                    preg_match("#(http://\S*)#", $row['originaltext'], $matches);
                    $row['url'] = $matches[1];
                    $row['content'] = preg_replace("# http://\S*#", "", $row['originaltext']);
                    break;

                default:
                    $row['source'] = "Twitter";
            }

            break;

        case "flickr":
            $icon = IMAGE_ROOT.'silk/picture.png';
            $row['content'] = sprintf('<a href="%s">%s</a>', $row['url'], $row['content']);
            break;

        case "code":
                $icon = IMAGE_ROOT.'silk/application_osx_terminal.png';
                $row['content'] = $row['content'];
            break;
  
        case "oyster":
                $icon = IMAGE_ROOT.'tfl.png';
            break;

        case "tumblr":
                $icon = IMAGE_ROOT.'tumblr/tumblr_16.png';
                $row['small_image'] = IMAGE_ROOT.'tumblr/tumblr_16.png';
            break;

        default:
              $icon = IMAGE_ROOT.'silk/asterisk_orange.png';
    }

    if ($row['image']) {
        $icon = $row['image'];
            
        if (!isset($row['small_icon'])) {
            $row['small_icon'] = $icon;
        }
    }


    $row['icon'] = $icon;
    $row['nicetime'] = nicetime($row['epoch']);

    #$row['content'] = $row['type'].$row['content'];

    $row['id'] = md5($row['systemid']);


    return $row;
}



function twitterFormat($text)
{
    

    $text = nl2br(ereg_replace("[[:alpha:]]+://[^<>[:space:]]+[[:alnum:]/]", "<a href=\"\\0\">\\0</a>", $text));
    $text = preg_replace("#@(\w*)?#", "<a href=\"http://www.twitter.com/\\1\">@\\1</a>", $text);
    
    $text = preg_replace("/#(\w*)/", "<a href=\"http://twitter.com/#search?q=%23\\1\">#\\1</a>", $text);
    
    $text = preg_replace("#^Aquarion: #", "", $text);

    $text = preg_replace("#http://raptr.com/\w*#", "", $text);
    $text = preg_replace("#^Xbox Live: #", "", $text);
    $text = preg_replace("# \(Xbox Live Nation\)$#", "", $text);
    
    return $text;
}


function getDatabase()
{
    global $settings;
    $config = parse_ini_file($settings['lifestream_dir']."/config.ini", true);

    ORM::configure(array(
        'connection_string' => sprintf('mysql:host=%s;dbname=%s', $config['database']['hostname'], $config['database']['database']),
        'username' => $config['database']['username'],
        'password' => $config['database']['password']
    ));


    ORM::configure('logging', true);
    ORM::configure('driver_options', array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
}


function lifestream_config($area, $item)
{
    $config = parse_ini_file("../config.ini", true);
    return $config[$area][$item];
}


function send_to_slack($message, $name = "Lifestream", $channel = false, $icon = false)
{


        //Options
        $token    = lifestream_config("slack", "token");
        $domain   = lifestream_config("slack", "domain");
        $channel  = $channel ? $channel : '#general';
        $bot_name = $name ? $name : 'Webhook';
        $icon     = $icon ? $icon : ':alien:';

        $attachments = array([
            'fallback' => 'Lorem ipsum',
            'pretext'  => 'Lorem ipsum',
            'color'    => '#ff6600',
            'fields'   => array(
                [
                    'title' => 'Title',
                    'value' => 'Lorem ipsum',
                    'short' => true
                ],
                [
                    'title' => 'Notes',
                    'value' => 'Lorem ipsum',
                    'short' => true
                ]
            )
        ]);
        $data = array(
            'channel'     => $channel,
            'username'    => $bot_name,
            'text'        => $message,
            'icon_emoji'  => $icon,
            // 'attachments' => $attachments
        );
        $data_string = json_encode($data);
        $url = 'https://hooks.slack.com/services/'.$token;
        $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string)));
        //Execute CURL
        $result = curl_exec($ch);
        
        $info = curl_getinfo($ch);

    if ($result === false || $info['http_code'] != 200) {
        $output = "No cURL data returned for $url [". $info['http_code']. "]";
        if (curl_error($ch)) {
            $output .= curl_error($ch);
        }
        error_log($output);
    }

        return $result;
}

// addEntry($type,$id,$title,$source,$date,$url='',$image='',$fulldata_json=False,$update=False)
function addEntry( // THis is a conversion of the same function in the python imports section
    $type,
    $id,
    $title,
    $source,
    $date,
    $url = '',
    $image = '',
    $fulldata_json = false,
    $update = false
) {

    if (is_array($fulldata_json) || is_object($fulldata_json)) {
        $fulldata_json = json_encode($fulldata_json);
    }

    $dbCxn = getDatabase();

    $record = ORM::for_table('lifestream')->where("type", $type)->where("systemid", $id)->find_one();

    // error_log(ORM::get_last_query());

    if ($record && (!$update)) {
        return false;
    } elseif (!$record) {
        $record = ORM::for_table('lifestream')->create();
        $record->set("type", $type);
        $record->set("systemid", $id);
        $record->set("date_created", $date);
    }

    if ($record) {
        $record->set('title', $title);
        $record->set('url', $url);
        $record->set('source', $source);
        $record->set('image', $image);
        $record->set('fulldata_json', $fulldata_json);
        $record->save();
    }
    // error_log(ORM::get_last_query());
}