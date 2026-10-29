<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="initial-scale=1.0, user-scalable=no" />

    <title>{{ $title }}</title>
    <meta property="og:title" content="{{ $title }}" />
    <meta property="og:image" content="{{ Vite::asset('resources/images/map-icon.png') }}">
    @include('partials.icons')

    <style type="text/css">
      html { height: 100% }
      body { height: 100%; margin: 0; padding: 0 }
      #mapcanvas { height: 100%; width: 100% }
      .leaflet-container {
          background: rgb(2,50,54) !important;
      }
    </style>

    @vite(['resources/js/app.js', 'resources/js/lifestream/heatmap.js'])
  </head>
  <body>
    <script type="application/json" id="locations">@json($locations)</script>
    <div id="mapcanvas" data-radius="{{ $heatmapRadius }}"></div>
  </body>
</html>
