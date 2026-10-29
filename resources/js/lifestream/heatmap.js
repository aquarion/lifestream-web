// Entry point for the /maps and /today heatmap pages.

import { drawLocations, mapboxLayer } from './map.js';

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
            Light: mapboxLayer('mapbox/light-v9'),
            'Satellite-9': mapboxLayer('mapbox/satellite-v9'),
        },
        radius: Number(container.dataset.radius),
        weight: 0.6,
        iconWeight: 1,
    });
});
