// The homepage: loads tiles from POST /feed and lays them out with Packery,
// polling for newer items. Ported from the pre-Laravel nicave.js.

import Chart from 'chart.js/auto';
import Packery from 'packery';
import { className, innerSize, outerSize, setSize } from './dom.js';
import { format, GRID } from './formatting.js';
import { drawLocations, mapboxLayer } from './map.js';

const MAP_ID = 'mapcanvas';

export class Feed {
    constructor(container) {
        this.container = container;
        this.packery = new Packery(container, { gutter: 0 });
        this.seen = new Set();
        this.newest = new Date(0);
        this.context = { lastfm: {} };
        this.musicChart = null;
    }

    start() {
        this.fetch({});
    }

    async fetch(params) {
        const body = new URLSearchParams({ path: window.location.pathname, ...params });

        try {
            const response = await fetch('/feed', {
                method: 'POST',
                body,
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) {
                throw new Error(`Feed returned ${response.status}`);
            }
            this.render(await response.json());
        } catch (error) {
            console.error('[lifestream] Could not load the feed:', error);
        }
    }

    render(data) {
        document.getElementById('message').innerHTML = data.message;
        document.title = `Nicholas Avenell - Web Person - ${data.title}`;
        this.updateNav(data.nav);

        const items = [...data.items];
        const showMap = data.locations?.length && !document.getElementById(MAP_ID);
        if (showMap) {
            items.splice(Math.floor(Math.random() * (items.length + 1)), 0, 'MAP');
        }

        for (const object of items) {
            if (object === 'MAP') {
                this.addMap(data.locations);
                continue;
            }

            const tile = this.tile(object);
            if (!tile) {
                continue;
            }

            if (data.direction === 'append') {
                this.container.append(tile);
                this.packery.appended(tile);
            } else {
                this.container.prepend(tile);
                this.packery.prepended(tile);
            }

            const updated = new Date(object.date_updated);
            if (updated > this.newest) {
                this.newest = updated;
            }
        }

        this.buildMusicChart();
        this.snapToGrid();
        this.packery.layout();

        const delay = data.next * 1000;
        if (data.offset) {
            setTimeout(() => this.fetch({ offset: data.offset }), delay);
        } else {
            setTimeout(() => this.fetch({ after: this.newest.toISOString() }), delay);
        }
    }

    updateNav(nav) {
        for (const [key, id] of [
            ['back', 'navleft'],
            ['forward', 'navright'],
            ['up', 'navup'],
        ]) {
            const link = document.getElementById(id);
            link.setAttribute('href', nav[key] || '');
            link.classList.toggle('navDisabled', !nav[key]);
        }
    }

    /** Build a tile for one entry, or null if it shouldn't be shown. */
    tile(object) {
        const key = `${object.type}\u0000${object.systemid}`;
        if (this.seen.has(key) || (!object.title && !object.image)) {
            return null;
        }

        const element = document.createElement('a');
        element.className = 'item';
        element.classList.add(
            className(object.source) || '_',
            className(object.type) || '_',
            className(`${object.source}_${object.type}`),
        );
        setSize(element, { width: 200 });
        element.innerHTML = object.title ?? '';

        if (object.image && object.image !== '0') {
            const icon = document.createElement('img');
            icon.className = 'icon';
            icon.src = object.image;
            element.prepend(icon);
        }
        if (object.url && object.url !== '0') {
            element.href = object.url;
        }

        const tile = format(object, element, this.context);
        if (!tile) {
            return null;
        }

        const cite = document.createElement('cite');
        cite.textContent = `${object.source}@${object.date_created}`;
        tile.append(cite);

        this.seen.add(key);
        return tile;
    }

    addMap(locations) {
        const element = document.createElement('div');
        element.id = MAP_ID;
        element.className = 'item map';
        element.dataset.resized = 'true';
        setSize(element, { width: 500, height: 56 * 6 });

        this.container.append(element);
        this.packery.appended(element);

        const map = drawLocations(element, locations, {
            baseLayer: mapboxLayer('mapbox/light-v9'),
            radius: 12,
            weight: 0.7,
            iconWeight: 1,
        });
        if (!map) {
            this.packery.remove(element);
        }
    }

    buildMusicChart() {
        const plays = this.context.lastfm;
        const artists = Object.keys(plays);
        if (artists.length === 0) {
            return;
        }

        const labels = [];
        const values = [];
        let other = 0;
        let total = 0;

        for (const artist of artists) {
            total += plays[artist];
            if (plays[artist] < 5) {
                other += plays[artist];
            } else {
                labels.push(artist);
                values.push(plays[artist]);
            }
        }
        if (other > 0) {
            labels.push('Others');
            values.push(other);
        }

        if (this.musicChart) {
            this.musicChart.data.labels = labels;
            this.musicChart.data.datasets[0].data = values;
            this.musicChart.update();
            return;
        }

        const maxWidth = this.container.clientWidth;
        let width = Math.min(Math.ceil(total / 100) * 100, maxWidth);
        let height = Math.min(Math.ceil((total * 0.75) / 56) * 56, maxWidth * 0.75);
        if (width < 300) {
            width = 400;
            height = 300;
        }

        const box = document.createElement('div');
        box.className = 'item music';
        box.dataset.resized = 'true';
        setSize(box, { width, height });
        const canvas = document.createElement('canvas');
        box.append(canvas);
        this.container.append(box);
        this.packery.appended(box);

        this.musicChart = new Chart(canvas, {
            type: 'pie',
            data: { labels, datasets: [{ data: values }] },
        });
    }

    /** Round every tile up to whole grid cells, so Packery can pack them neatly. */
    snapToGrid() {
        for (const item of this.container.querySelectorAll('.item')) {
            const outer = outerSize(item);
            const inner = innerSize(item);
            const marginWidth = outer.width - inner.width;
            const marginHeight = outer.height - inner.height;

            setSize(item, {
                width: Math.ceil(inner.width / GRID) * GRID - marginWidth,
                height: Math.ceil(inner.height / GRID) * GRID - marginHeight,
            });

            if (!item.dataset.resized && item.clientWidth / item.clientHeight < 0.7) {
                setSize(item, { height: GRID * 2 });
            }
        }
    }
}
