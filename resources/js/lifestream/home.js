// Entry point for the lifestream homepage and date pages.

import '../../css/lifestream.css';
import { Feed } from './feed.js';

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('tiles');
    if (container) {
        new Feed(container).start();
    }
});
