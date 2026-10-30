@inject('matomoService', 'App\Services\MatomoService')
@php($matomo = $matomoService->getConfig())
@if ($matomo)
<script>
	var _paq = window._paq = window._paq || [];
	_paq.push(['setTrackerUrl', @js($matomo['tracker_url'] . '/matomo.php')]);
	_paq.push(['setSiteId', @js((string) $matomo['site_id'])]);
	_paq.push(['enableLinkTracking']);
	_paq.push(['trackPageView']);
	(function() {
		var script = document.createElement('script');
		script.async = true;
		script.src = @js($matomo['tracker_url'] . '/matomo.js');
		document.head.appendChild(script);
	})();
</script>
@endif
