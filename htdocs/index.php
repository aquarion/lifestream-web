<?php
/*define("AN_HOUR", 60*60 );
define("A_DAY", 60*60*24 );
define("A_WEEK", 60*60*24*7 );
define("A_MONTH", 60*60*24*30 );
define("A_YEAR", 60*60*24*364 );
*/

require("../lib/lifestream.inc.php");

$split = explode("/", $_SERVER['REQUEST_URI']);
array_shift($split);

$last = end($split);
reset($split);

if($last == "") {
    array_pop($split);
}

$ordered = false;
$title = false;
$date_point = false;

if (count($split) > 0 && $split[0] == 'search') { // One Year
    $today = time();
    $format = "/Y/m/d";
    $display_format = "\Y\e\a\r \o\f Y";
} elseif (count($split) == 1 && is_numeric($split[0])) { // One Year
    $today = mktime(0, 0, 0, 1, 1, $split[0]);
    $format = "/Y/m/d";
    $display_format = "\Y\e\a\r \o\f Y";
} elseif(count($split) == 2 && strpos($split[1], "wk") !== false) {  // One Week
    $week = substr($split[1], 2);
    list($today, $to) = get_start_and_end_date_from_week($week, $split[0]);
    $format = "/Y/\w\kW";
    $display_format = "\W\k W Y";
    $date_point = (($to - $today) / 2) + $today;
} elseif(count($split) == 2 && is_numeric($split[1])) {  // One Month
    $today = mktime(0, 0, 0, intval($split[1]), 1, intval($split[0]));
    $format = "/Y/m";
    $display_format = "F Y";
} elseif(count($split) == 3 && is_numeric($split[1])) {  // One Day
    if($split[0] == '*') {
        $today = mktime(0, 0, 0, intval($split[1]), intval($split[2]), intval($split[0]));
        $format = "/\*/m/d";
        $display_format = "l jS F \A\\n\y \y\e\a\\r";
    } else {
        $today = mktime(0, 0, 0, intval($split[1]), intval($split[2]), intval($split[0]));
        $format = "/Y/m/d";
        $display_format = "l jS F Y";
    }
} elseif(count($split) == 0) {  // Last 200
    $today = time();
    $format = "/Y/m/d";
    $display_format = "l jS F Y";
} else {
    #header('Status: 404 Not Found');
    #eader('HTTP/1.1 404 Something Missing');
    http_response_code(404);
    die();
}

if(!$date_point) {
    $date_point = (int)$today;
}


?><!DOCTYPE>
<html>
<head>
	<script src="//code.jquery.com/jquery-1.10.2.min.js"></script>
	<script src="//cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.2/Chart.bundle.min.js"></script>
	<script src="/assets/js/packery.pkgd.min.js"></script>
	<!-- script type="text/javascript" src="https://www.google.com/jsapi"></script -->
	<script src="//twemoji.maxcdn.com/2/twemoji.min.js?2.7"></script>
	<script src="/assets/libs/md5-min.js"></script>

	<link href='//fonts.googleapis.com/css?family=PT+Mono|Raleway|Comfortaa' rel='stylesheet' type='text/css'>
	<link rel="stylesheet" href="/assets/css/style.css">

	<!-- <Icons> -->
	<link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/assets/icons/favicon-16x16.png">
	<link rel="manifest" href="/assets/icons/site.webmanifest">
	<link rel="mask-icon" href="/assets/icons/safari-pinned-tab.svg" color="#2b5797">
	<link rel="shortcut icon" href="/assets/icons/favicon.ico">
	<meta name="apple-mobile-web-app-title" content="Nicholas Avenell dot Com">
	<meta name="application-name" content="Nicholas Avenell dot Com">
	<meta name="msapplication-TileColor" content="#2b5797">
	<meta name="msapplication-config" content="/assets/icons/browserconfig.xml">
	<meta name="theme-color" content="#559cc8">
	<!-- </Icons> -->

	<!-- Map stuff -->
	<link rel="stylesheet" href="https://unpkg.com/leaflet@1.1.0/dist/leaflet.css"  crossorigin=""/>
	<script src="https://unpkg.com/leaflet@1.1.0/dist/leaflet.js" crossorigin=""></script>
	<script type="text/javascript" src="/assets/js/webgl-heatmap-master/webgl-heatmap.js"></script>
	<script type="text/javascript" src="/assets/js/leaflet-webgl-heatmap-master/dist/leaflet-webgl-heatmap.min.js"></script>
	<script type="text/javascript" src="https://stamen-maps.a.ssl.fastly.net/js/tile.stamen.js"></script>
	<!-- /Map stuff -->

	<script type="text/javascript" src="/assets/js/library.js"></script>
	<script type="text/javascript" src="/assets/js/formatting.js"></script>
	<script type="text/javascript" src="/assets/js/nicave.js"></script>
	<link rel="stylesheet" href="https://dailyphoto.aquarionics.com/background.css.php?from=<?php echo date("Y-m-d", $today) ?>"/>
	<?php include("google_analytics.html"); ?>


	<title>Nicholas Avenell - Web Person</title>

<style type="text/css">

span#glasses {
	background: url('/assets/icons/mstile-70x70.png');
    background-size: cover;
	background-repeat: no-repeat;
	background-position: center;
	height: 1em;
	width: 1em;
	display: inline-block;
}

</style>

</head>
<body>

<header>
<h1>Nicholas <span id="glasses">&nbsp;</span> Avenell</h1>
<p>Is still working on this site</p>
<p>
	I am Nicholas Avenell. I am a professional geek. I've worked for <a href="http://wiki.aquarionics.com/placesIveWorked">various companies</a>, 
	some of which you might have heard of. I have <a href="http://www.aquarionics.com/">a weblog</a>, which I update sometimes; 
	this, which updates more often; and <a href="http://istic.net">a company</a> which does stuff you could hire me for. 
	I've got accounts pretty much everywhere you'd expect, greatest hits are below, the rest you'll find on the page confusingly 
	named <a href="http://wiki.aquarionics.com/walrus">Project Walrus</a>.</p>
<p>
	<p class="buttons">
		<a href="http://www.twitter.com/aquarion"><svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-twitter" viewBox="0 0 16 16">
  <path d="M5.026 15c6.038 0 9.341-5.003 9.341-9.334 0-.14 0-.282-.006-.422A6.685 6.685 0 0 0 16 3.542a6.658 6.658 0 0 1-1.889.518 3.301 3.301 0 0 0 1.447-1.817 6.533 6.533 0 0 1-2.087.793A3.286 3.286 0 0 0 7.875 6.03a9.325 9.325 0 0 1-6.767-3.429 3.289 3.289 0 0 0 1.018 4.382A3.323 3.323 0 0 1 .64 6.575v.045a3.288 3.288 0 0 0 2.632 3.218 3.203 3.203 0 0 1-.865.115 3.23 3.23 0 0 1-.614-.057 3.283 3.283 0 0 0 3.067 2.277A6.588 6.588 0 0 1 .78 13.58a6.32 6.32 0 0 1-.78-.045A9.344 9.344 0 0 0 5.026 15z"/>
</svg></a>
		<a href="http://www.linkedin.com/in/webperson"><svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-linkedin" viewBox="0 0 16 16">
  <path d="M0 1.146C0 .513.526 0 1.175 0h13.65C15.474 0 16 .513 16 1.146v13.708c0 .633-.526 1.146-1.175 1.146H1.175C.526 16 0 15.487 0 14.854V1.146zm4.943 12.248V6.169H2.542v7.225h2.401zm-1.2-8.212c.837 0 1.358-.554 1.358-1.248-.015-.709-.52-1.248-1.342-1.248-.822 0-1.359.54-1.359 1.248 0 .694.521 1.248 1.327 1.248h.016zm4.908 8.212V9.359c0-.216.016-.432.08-.586.173-.431.568-.878 1.232-.878.869 0 1.216.662 1.216 1.634v3.865h2.401V9.25c0-2.22-1.184-3.252-2.764-3.252-1.274 0-1.845.7-2.165 1.193v.025h-.016a5.54 5.54 0 0 1 .016-.025V6.169h-2.4c.03.678 0 7.225 0 7.225h2.4z"/>
</svg></a>
		<a href="http://www.facebook.com/aquarion"><svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-facebook" viewBox="0 0 16 16">
  <path d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.951v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98h-1.009c-.993 0-1.303.621-1.303 1.258v1.51h2.218l-.354 2.326H9.25V16c3.824-.604 6.75-3.934 6.75-7.951z"/>
</svg></a>
		<a rel="me" href="https://mendeddrum.org/@aquarion"><svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-mastodon" viewBox="0 0 16 16">
  <path d="M11.19 12.195c2.016-.24 3.77-1.475 3.99-2.603.348-1.778.32-4.339.32-4.339 0-3.47-2.286-4.488-2.286-4.488C12.062.238 10.083.017 8.027 0h-.05C5.92.017 3.942.238 2.79.765c0 0-2.285 1.017-2.285 4.488l-.002.662c-.004.64-.007 1.35.011 2.091.083 3.394.626 6.74 3.78 7.57 1.454.383 2.703.463 3.709.408 1.823-.1 2.847-.647 2.847-.647l-.06-1.317s-1.303.41-2.767.36c-1.45-.05-2.98-.156-3.215-1.928a3.614 3.614 0 0 1-.033-.496s1.424.346 3.228.428c1.103.05 2.137-.064 3.188-.189zm1.613-2.47H11.13v-4.08c0-.859-.364-1.295-1.091-1.295-.804 0-1.207.517-1.207 1.541v2.233H7.168V5.89c0-1.024-.403-1.541-1.207-1.541-.727 0-1.091.436-1.091 1.296v4.079H3.197V5.522c0-.859.22-1.541.66-2.046.456-.505 1.052-.764 1.793-.764.856 0 1.504.328 1.933.983L8 4.39l.417-.695c.429-.655 1.077-.983 1.934-.983.74 0 1.336.259 1.791.764.442.505.661 1.187.661 2.046v4.203z"/>
</svg></a>
	<!--	<a href="http://www.last.fm/user/Aquarion"><img src="//art.istic.net/iconography/socialmedia/lastfm.png" title="" alt="" /></a>
		<a href="http://www.flickr.com/people/aquarion"><img src="//art.istic.net/iconography/socialmedia/flickr.png" title="" alt="" /></a>
		<a href="http://aquarion.tumblr.com/"><img src="//art.istic.net/iconography/socialmedia/tumblr.png" title="" alt="" /></a>
		<a href="http://www.reddit.com/user/Aquarion/"><img src="//art.istic.net/iconography/socialmedia/reddit.png" title="" alt="" /></a>
	</p>a -->
</p>

<p id="message" />
<p id="navigation">
	<a href="#" id="navleft">&#8592;</a>
	<a href="#" id="navup">&#8593;</a>
	<a href="#" id="navright">&#8594;</a>
</p>
<p id="years">
	<?php
    $template = '<li><a href="%s" title="%s">%s</a></li>';
$this_year = date("Y", $today);
$years = array();


for($i = 2001;$i <= date("Y"); $i++) {
    $this_date = new DateTime(date("Y-m-d", (int)$date_point));
    $years_ago = $this_year - $i;
    $interval = new DateInterval(sprintf('P%dY', abs($years_ago)));
    if ($years_ago > 0) {
        $this_date->sub($interval);
    } else {
        $this_date->add($interval);
    }
    $date = $this_date->format($format);
    $years[] = sprintf($template, $date, $this_date->format($display_format), $i);
}
print '<div class="years"><ul>'.implode("\n", $years).'</ul></div>';
?>
</p>

</header>

  <div id="tiles" class="container">
  	<!-- Yeah, nothing here. It's all a magic trick. The magic's in nicave.js -->
  </div>

</body>
</html>
