<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<link href='https://fonts.googleapis.com/css?family=PT+Mono|Raleway|Comfortaa' rel='stylesheet' type='text/css'>
	@include('partials.icons')
	<link rel="stylesheet" href="https://dailyphoto.aquarionics.com/background.css.php?from={{ date('Y-m-d', $period->today) }}"/>
	@vite(['resources/js/app.js', 'resources/js/lifestream/home.js'])
	@include('partials.matomo')

	<title>Nicholas Avenell - Web Person</title>

<style type="text/css">

span#glasses {
	background: url('{{ Vite::asset('resources/images/icons/mstile-70x70.png') }}');
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
<p class="buttons">
	<a href="http://www.linkedin.com/in/webperson"><svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-linkedin" viewBox="0 0 16 16">
  <path d="M0 1.146C0 .513.526 0 1.175 0h13.65C15.474 0 16 .513 16 1.146v13.708c0 .633-.526 1.146-1.175 1.146H1.175C.526 16 0 15.487 0 14.854V1.146zm4.943 12.248V6.169H2.542v7.225h2.401zm-1.2-8.212c.837 0 1.358-.554 1.358-1.248-.015-.709-.52-1.248-1.342-1.248-.822 0-1.359.54-1.359 1.248 0 .694.521 1.248 1.327 1.248h.016zm4.908 8.212V9.359c0-.216.016-.432.08-.586.173-.431.568-.878 1.232-.878.869 0 1.216.662 1.216 1.634v3.865h2.401V9.25c0-2.22-1.184-3.252-2.764-3.252-1.274 0-1.845.7-2.165 1.193v.025h-.016a5.54 5.54 0 0 1 .016-.025V6.169h-2.4c.03.678 0 7.225 0 7.225h2.4z"/>
</svg></a>
	<a rel="me" href="https://mendeddrum.org/@aquarion"><svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-mastodon" viewBox="0 0 16 16">
  <path d="M11.19 12.195c2.016-.24 3.77-1.475 3.99-2.603.348-1.778.32-4.339.32-4.339 0-3.47-2.286-4.488-2.286-4.488C12.062.238 10.083.017 8.027 0h-.05C5.92.017 3.942.238 2.79.765c0 0-2.285 1.017-2.285 4.488l-.002.662c-.004.64-.007 1.35.011 2.091.083 3.394.626 6.74 3.78 7.57 1.454.383 2.703.463 3.709.408 1.823-.1 2.847-.647 2.847-.647l-.06-1.317s-1.303.41-2.767.36c-1.45-.05-2.98-.156-3.215-1.928a3.614 3.614 0 0 1-.033-.496s1.424.346 3.228.428c1.103.05 2.137-.064 3.188-.189zm1.613-2.47H11.13v-4.08c0-.859-.364-1.295-1.091-1.295-.804 0-1.207.517-1.207 1.541v2.233H7.168V5.89c0-1.024-.403-1.541-1.207-1.541-.727 0-1.091.436-1.091 1.296v4.079H3.197V5.522c0-.859.22-1.541.66-2.046.456-.505 1.052-.764 1.793-.764.856 0 1.504.328 1.933.983L8 4.39l.417-.695c.429-.655 1.077-.983 1.934-.983.74 0 1.336.259 1.791.764.442.505.661 1.187.661 2.046v4.203z"/>
</svg></a>
	<a rel="me" href="https://bsky.app/profile/aquarionics.com"><svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-bluesky" viewBox="0 0 16 16">
  <path d="M3.468 1.948C5.303 3.325 7.276 6.118 8 7.616c.725-1.498 2.698-4.29 4.532-5.668C13.855.955 16 .186 16 2.632c0 .489-.28 4.105-.444 4.692-.572 2.04-2.653 2.561-4.504 2.246 3.236.551 4.06 2.375 2.281 4.2-3.376 3.464-4.852-.87-5.23-1.98-.07-.204-.103-.3-.103-.218 0-.081-.033.014-.102.218-.379 1.11-1.855 5.444-5.231 1.98-1.778-1.825-.955-3.65 2.28-4.2-1.85.315-3.932-.205-4.503-2.246C.28 6.737 0 3.12 0 2.632 0 .186 2.145.955 3.468 1.948"/>
</svg></a>
<!--	<a href="http://www.last.fm/user/Aquarion"><img src="//art.istic.net/iconography/socialmedia/lastfm.png" title="" alt="" /></a>
	<a href="http://www.flickr.com/people/aquarion"><img src="//art.istic.net/iconography/socialmedia/flickr.png" title="" alt="" /></a>
	<a href="http://aquarion.tumblr.com/"><img src="//art.istic.net/iconography/socialmedia/tumblr.png" title="" alt="" /></a>
	<a href="http://www.reddit.com/user/Aquarion/"><img src="//art.istic.net/iconography/socialmedia/reddit.png" title="" alt="" /></a>
-->
</p>

<p id="message"></p>
<p id="navigation">
	<a href="#" id="navleft">&#8592;</a>
	<a href="#" id="navup">&#8593;</a>
	<a href="#" id="navright">&#8594;</a>
</p>
<div id="years">
	<div class="years"><ul>
@foreach ($period->yearLinks() as $link)
		<li><a href="{{ $link['href'] }}" title="{{ $link['title'] }}">{{ $link['label'] }}</a></li>
@endforeach
	</ul></div>
</div>

</header>

  <div id="tiles" class="container">
  	<!-- Yeah, nothing here. It's all a magic trick. The magic's in nicave.js -->
  </div>

</body>
</html>
