// The filter menu in the top left: a checkbox per entry source (tumblr,
// mastodon, Foursquare...), so viewers can choose what the feed shows.
// Unchecked sources are sent to /feed as exclude[] and remembered in
// localStorage.
//
// Sources are listed as the feed comes across them, plus anything currently
// excluded (which the feed will never return, so would otherwise vanish).

const STORAGE_KEY = 'lifestream.excludedSources';

/** Off until the viewer turns them on: all Tumblr posts, and Last.fm (whose plays make the music chart). */
const DEFAULT_EXCLUDED = ['tumblr', 'lastfm'];

function loadExcluded() {
    try {
        const stored = JSON.parse(localStorage.getItem(STORAGE_KEY));
        if (Array.isArray(stored)) {
            return stored.filter((source) => typeof source === 'string');
        }
    } catch {
        // Storage blocked or unreadable: fall back to the defaults.
    }
    return DEFAULT_EXCLUDED;
}

function compareSources(a, b) {
    return a.localeCompare(b, undefined, { sensitivity: 'base' });
}

function saveExcluded(excluded) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify([...excluded]));
    } catch {
        // Not remembered, but the filter still applies to this page view.
    }
}

export class SourceFilter {
    /**
     * @param {HTMLDetailsElement} root  The #filter element.
     * @param {() => void} onChange  Called after the viewer toggles a source.
     */
    constructor(root, onChange) {
        this.root = root;
        this.list = root.querySelector('ul');
        this.onChange = onChange;
        this.excluded = new Set(loadExcluded());
        this.sources = new Set();

        this.list.addEventListener('change', (event) => this.toggle(event.target));

        // Close the menu on a click elsewhere, or Escape.
        document.addEventListener('click', (event) => {
            if (root.open && !root.contains(event.target)) {
                root.open = false;
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && root.open) {
                root.open = false;
                root.querySelector('summary').focus();
            }
        });

        for (const source of this.excluded) {
            this.add(source);
        }
    }

    /** Sources to leave out of the feed. */
    get exclusions() {
        return [...this.excluded];
    }

    /**
     * Note a source the feed has returned, adding it to the menu if it's new.
     * The row is inserted in place rather than the list rebuilt, so a viewer
     * using the open menu keeps keyboard focus on their checkbox.
     */
    add(source) {
        if (!source || this.sources.has(source)) {
            return;
        }
        this.sources.add(source);

        const next = [...this.list.children].find((item) => compareSources(item.dataset.source, source) > 0);
        this.list.insertBefore(this.item(source), next ?? null);
    }

    toggle(checkbox) {
        if (checkbox.checked) {
            this.excluded.delete(checkbox.value);
        } else {
            this.excluded.add(checkbox.value);
        }
        saveExcluded(this.excluded);
        this.onChange();
    }

    item(source) {
        const item = document.createElement('li');
        item.dataset.source = source;
        const label = document.createElement('label');
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.value = source;
        checkbox.checked = !this.excluded.has(source);
        label.append(checkbox, ` ${source}`);
        item.append(label);
        return item;
    }
}
