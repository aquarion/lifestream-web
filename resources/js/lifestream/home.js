// Entry point for the lifestream homepage and date pages.

import '../../css/lifestream.css';
import { Feed } from './feed.js';
import { SourceFilter } from './filter.js';

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('tiles');
    if (!container) {
        return;
    }

    const menu = document.getElementById('filter');
    const filter = menu ? new SourceFilter(menu, () => feed.reload()) : null;
    const feed = new Feed(container, filter);
    feed.start();
});
