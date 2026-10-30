@if (config('services.matomo.url') && config('services.matomo.site_id'))
<script>
	var _paq = window._paq = window._paq || [];
	_paq.push(['trackPageView']);
	_paq.push(['enableLinkTracking']);
	(function() {
		var u = @js(rtrim(config('services.matomo.url'), '/') . '/');
		_paq.push(['setTrackerUrl', u + 'matomo.php']);
		_paq.push(['setSiteId', @js((string) config('services.matomo.site_id'))]);
		var d = document, g = d.createElement('script'), s = d.getElementsByTagName('script')[0];
		g.async = true; g.src = u + 'matomo.js'; s.parentNode.insertBefore(g, s);
	})();
</script>
@endif
