// Entry point for the /maps and /today heatmap pages.

import { drawLocations, mapboxLayer, stadiaLayer } from './map.js';

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('mapcanvas');
    const data = document.getElementById('locations');
    if (!container || !data) {
        return;
    }

    const metropolis = mapboxLayer('aquarion/cjbj6ztf70vro2sl4y09909bg');

    drawLocations(container, JSON.parse(data.textContent), {
        baseLayer: metropolis,
        baseLayers: {
            Metropolis: metropolis,
            'Satellite-9': mapboxLayer('mapbox/satellite-v9'),
            Watercolor: stadiaLayer('watercolor', '.jpg'),
            Toner: stadiaLayer('toner'),
            'Toner Lite': stadiaLayer('toner_lite'),
        },
        radius: Number(container.dataset.radius),
        weight: 0.6,
        iconWeight: 1,
    });
});
