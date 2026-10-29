// Location maps: the heatmap tile in the feed and the /maps and /today pages.

import L from './leaflet.js';
import 'leaflet.heat';

const MAPBOX_TOKEN = 'pk.eyJ1IjoiYXF1YXJpb24iLCJhIjoiQzRoeUpwZyJ9.gIhABGtR7UMR-LZUJGRW0A';

export function mapboxLayer(style) {
    return L.tileLayer(`https://api.mapbox.com/styles/v1/${style}/tiles/256/{z}/{x}/{y}?access_token=${MAPBOX_TOKEN}`, {
        attribution: '&copy; Mapbox &copy; OpenStreetMap contributors',
    });
}

/**
 * Draw locations on a map in `container`: a heatmap of every point, plus a
 * marker for points with an icon (e.g. Foursquare check-ins).
 *
 * @param {HTMLElement} container
 * @param {Array<{lat: number, long: number, title?: string, icon?: string}>} locations
 * @param {{baseLayer: L.Layer, baseLayers?: object, radius: number, weight: number, iconWeight: number}} options
 * @returns {L.Map|null} null when there is nothing to show
 */
export function drawLocations(container, locations, { baseLayer, baseLayers, radius, weight, iconWeight }) {
    const points = locations.filter((point) => !(Number(point.lat) === 0 && Number(point.long) === 0));
    if (points.length === 0) {
        return null;
    }

    const map = L.map(container, {
        center: [points[0].lat, points[0].long],
        zoom: 12,
    });
    map.addLayer(baseLayer);
    map.fitBounds(L.latLngBounds(points.map((point) => [point.lat, point.long])));

    const heatData = [];
    const markers = [];

    for (const point of points) {
        let pointWeight = weight;

        if (point.icon) {
            markers.push(
                L.marker([point.lat, point.long], {
                    title: point.title ?? '',
                    icon: L.icon({ iconUrl: point.icon.replace('_64.png', '_bg_32.png'), iconSize: [16, 16] }),
                }),
            );
            pointWeight = iconWeight;
        }

        heatData.push([point.lat, point.long, pointWeight]);
    }

    const heatmap = L.heatLayer(heatData, { radius, blur: radius, maxZoom: 14 }).addTo(map);
    const markerLayer = L.featureGroup(markers).addTo(map);

    if (baseLayers) {
        L.control
            .layers(baseLayers, {
                Heatmap: heatmap,
                Foursquare: markerLayer,
            })
            .addTo(map);
    }

    return map;
}
