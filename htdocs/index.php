<?PHP
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

if($last == ""){
	array_pop($split);
}

$ordered = false;
$title = false;
$date_point = false;

if (count($split) > 0 && $split[0] == 'search'){ // One Year
	$today = time();
	$format = "/Y/m/d";
	$display_format = "\Y\e\a\r \o\f Y";
} elseif (count($split) == 1 && is_numeric($split[0])){ // One Year
	$today = mktime(0,0,0, 1, 1, $split[0]);
	$format = "/Y/m/d";
	$display_format = "\Y\e\a\r \o\f Y";
} elseif(count($split) == 2 && strpos($split[1], "wk") !== false){  // One Week
	$week = substr($split[1], 2);
	list($today, $to) = get_start_and_end_date_from_week($week, $split[0]);
	$format = "/Y/\w\kW";
	$display_format = "\W\k W Y";
	$date_point = (($to-$today)/2) + $today;
} elseif(count($split) == 2 && is_numeric($split[1])){  // One Month
	$today = mktime (0, 0, 0, intval($split[1]), 1, intval($split[0]));
	$format = "/Y/m";
	$display_format = "F Y";
} elseif(count($split) == 3 && is_numeric($split[1])){  // One Day
	if($split[0] == '*'){
		$today = mktime (0, 0, 0, intval($split[1]), intval($split[2]), intval($split[0]));
		$format = "/\*/m/d";
		$display_format = "l jS F \A\\n\y \y\e\a\\r";
	} else {
		$today = mktime (0, 0, 0, intval($split[1]), intval($split[2]), intval($split[0]));
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

if(!$date_point){
	$date_point = $today;
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
	<link rel="stylesheet" href="https://dailyphoto.aquarionics.com/background.css.php?from=<?PHP echo date("Y-m-d", $today) ?>"/>
	<?PHP include("google_analytics.html"); ?>


	<title>Nicholas Avenell - Web Person</title>

<style type="text/css">

span#glasses {
	background: url('https://nicholasavenell.com/assets/icons/mstile-70x70.png');
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
		<a href="http://www.twitter.com/aquarion"><img src="//art.istic.net/iconography/elegantmediaicons/PNG/twitter.png" title="" alt="" /></a>
		<a href="http://www.linkedin.com/in/webperson"><img src="//art.istic.net/iconography/elegantmediaicons/PNG/linkedin.png" title="" alt="" /></a>
		<a href="http://www.facebook.com/aquarion"><img src="//art.istic.net/iconography/elegantmediaicons/PNG/facebook.png" title="" alt="" /></a>
	<!--	<a href="http://www.last.fm/user/Aquarion"><img src="//art.istic.net/iconography/elegantmediaicons/PNG/lastfm.png" title="" alt="" /></a>
		<a href="http://www.flickr.com/people/aquarion"><img src="//art.istic.net/iconography/elegantmediaicons/PNG/flickr.png" title="" alt="" /></a>
		<a href="http://aquarion.tumblr.com/"><img src="//art.istic.net/iconography/elegantmediaicons/PNG/tumblr.png" title="" alt="" /></a>
		<a href="http://www.reddit.com/user/Aquarion/"><img src="//art.istic.net/iconography/elegantmediaicons/PNG/reddit.png" title="" alt="" /></a>
	</p>a -->
</p>

<p id="message" />
<p id="navigation">
	<a href="#" id="navleft">&#8592;</a>
	<a href="#" id="navup">&#8593;</a>
	<a href="#" id="navright">&#8594;</a>
</p>
<p id="years">
	<?PHP 
	$template = '<li><a href="%s" title="%s">%s</a></li>';
	$this_year = date("Y", $today);
	$years = array();
	

	for($i=2001;$i <= date("Y"); $i++){
		$this_date = new DateTime(date("Y-m-d", $date_point));
		$years_ago = $this_year - $i;
		$interval = new DateInterval(sprintf('P%dY', abs($years_ago)));	
		if ($years_ago > 0){
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
