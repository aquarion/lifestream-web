// Per-service tile formatting, ported from the pre-Laravel formatting.js.
//
// The feed looks up a formatter by the entry's source, then its type, then
// "source_type", and runs each that exists in turn. A formatter gets the
// entry and its tile element, and returns the element (possibly restyled)
// or null to drop the tile. Entry titles are trusted HTML from our own
// importers (they carry links), so they're set with innerHTML as before.

import { capitalize, decodeEntities, fromHtml, setSize } from './dom.js';

/** Tiles are laid out on a grid of this many pixels. */
export const GRID = 150;

const h = GRID;
const w = GRID;

function describe(object, title) {
    return `${capitalize(`${object.source} ${object.type}`)}: ${title}`;
}

function achievement(object, element) {
    if (element.classList.contains('Raptr') || element.classList.contains('blizzard_wow')) {
        return element;
    }

    const title = decodeEntities(object.title);
    element.classList.add('achievement');

    let delim = ' – ';
    if (title.includes(' --- ')) {
        delim = ' --- ';
    } else if (title.includes(' -- ')) {
        delim = ' -- ';
    }
    const [top, bottom] = title.split(delim);

    const frame = fromHtml(
        '<div class="frame"><div class="achievement_top"></div><div class="achievement_bottom"></div></div>',
    );
    frame.style.backgroundImage = `url('${object.image}')`;
    frame.querySelector('.achievement_top').innerHTML = top ?? '';
    frame.querySelector('.achievement_bottom').innerHTML = bottom ?? '';
    element.replaceChildren(frame);

    element.title = describe(object, title);
    setSize(element, { width: w, height: h });

    return element;
}

function photo(object, element) {
    element.classList.add('photo');
    element.style.backgroundImage = `url('${object.image}')`;
    element.replaceChildren();
    return element;
}

function tumblrQuote(fulldata, extra) {
    const title = fulldata.title ? `<h2>${fulldata.title}</h2>` : '';
    return `<div class="frame"><blockquote>${title}${fulldata.summary}</blockquote>${extra}${fulldata.reblog.comment}</div>`;
}

export const formatters = {
    achievement,
    badge: achievement,
    gaming: achievement,

    destiny2_gaming(object, element) {
        if (element.classList.contains('Raptr')) {
            return element;
        }

        const title = decodeEntities(object.title);
        const fulldata = object.fulldata_json ?? {};

        element.style.backgroundImage = `url('https://www.bungie.net/${fulldata.activity_info?.pgcrImage}')`;
        element.style.backgroundSize = 'cover';
        element.classList.add('achievement');

        const frame = fromHtml(
            '<div class="frame"><div class="achievement_top">Destiny 2</div><div class="achievement_bottom"></div></div>',
        );
        frame.querySelector('.achievement_bottom').innerHTML = title.split(' --- ')[0];
        element.replaceChildren(frame);

        element.title = describe(object, title);
        setSize(element, { width: w * 2 - 12, height: h * 2 });

        return element;
    },

    blizzard_wow(object, element) {
        if (element.classList.contains('Raptr')) {
            return element;
        }

        const title = decodeEntities(object.title);
        element.classList.add('achievement');

        const frame = fromHtml('<div class="frame"><div class="achievement_bottom"></div></div>');
        frame.style.backgroundImage = `url('${object.image}')`;
        frame.querySelector('.achievement_bottom').innerHTML = title.split(' --- ')[0];
        element.replaceChildren(frame);

        element.title = describe(object, title);
        setSize(element, { width: w * 2 - 12, height: h * 2 });

        return element;
    },

    kickstarter(object, element) {
        element.style.backgroundImage = `url('${object.image}')`;
        element.style.backgroundSize = 'contain';
        element.classList.add('achievement');
        element.replaceChildren();

        element.title = describe(object, decodeEntities(object.title));
        setSize(element, { width: w * 2 - 12, height: h * 2 });

        return element;
    },

    facebook(object, element) {
        const fulldata = object.fulldata_json ?? {};
        const message = (fulldata.message ?? '').replace(/\n/g, '<br />');

        if (object.type === 'status' && fulldata.message) {
            element.innerHTML = message;
        }
        setSize(element, { width: w * 3 - 12 });

        if (object.image && object.image !== '0') {
            element.style.backgroundImage = `url('${object.image}')`;
            element.style.backgroundSize = 'cover';
            element.style.backgroundPosition = 'center center';
            element.classList.remove('photo');
            setSize(element, { width: w * 2 - 12, height: h * 2 });
            element.innerHTML = fulldata.message ? `<p>${message}</p>` : '';
        }

        return element;
    },

    oyster(_object, element) {
        element.classList.add('centered');
        element.innerHTML = element.innerHTML.replace(/\[.*?\]/, '');
        return element;
    },

    lastfm(object, _element, context) {
        // Plays are summarised in the music chart rather than shown as tiles.
        const artist = object.title.split(' – ')[0];
        context.lastfm[artist] = (context.lastfm[artist] ?? 0) + 1;
        return null;
    },

    facebook_link(object, element) {
        if (/^https?:\/\//.test(object.url ?? '')) {
            return null;
        }

        element.innerHTML = `<p>${object.title}</p>`;
        element.classList.remove('photo');
        setSize(element, { width: w * 2, height: h * 3 });
        return element;
    },

    tumblr_photo(object, element) {
        photo(object, element);
        setSize(element, { width: w * 3, height: h * 4 });
        return element;
    },

    tumblr(_object, element) {
        setSize(element, { width: w * 4 });
        return element;
    },

    tumblr_text(object, element) {
        setSize(element, { width: w * 4 });
        const fulldata = object.fulldata_json ?? {};
        if (fulldata.reblog) {
            element.innerHTML = tumblrQuote(fulldata, '');
        }
        return element;
    },

    tumblr_video(object, element) {
        setSize(element, { width: w * 4 });
        const fulldata = object.fulldata_json ?? {};
        if (fulldata.reblog) {
            element.innerHTML = tumblrQuote({ ...fulldata, title: '' }, fulldata.player?.[1]?.embed_code ?? '');
        }
        return element;
    },

    flickr: photo,

    instagram(object, element) {
        photo(object, element);
        element.style.backgroundPosition = 'top center';
        setSize(element, { height: 2 * h });
        element.innerHTML = `<div class="caption">${object.title}</div>`;
        return element;
    },

    Raptr(_object, element) {
        element.classList.add('gaming');
        element.classList.remove('twitter');
        return element;
    },

    'Foursquare-Badge'(object, element) {
        element.classList.add('achievement');
        element.style.backgroundImage = `url('${object.image}')`;
        element.style.backgroundSize = 'cover';
        setSize(element, { width: w });
        element.replaceChildren();
        return element;
    },

    Foursquare(_object, element) {
        setSize(element, { width: w * 0.5, height: h });
        return element;
    },

    fitbit(_object, element) {
        element.innerHTML = element.innerHTML.replace('activeScore', 'Active Score');
        const icon = document.createElement('img');
        icon.className = 'icon';
        icon.src = 'https://art.istic.net/iconography/fitbit.png';
        element.prepend(icon);
        return element;
    },

    twitter(object, element) {
        if (object.source === 'WordPress.com') {
            return null;
        }
        setSize(element, { height: h });
        return element;
    },

    aquarionics(object, element) {
        setSize(element, { width: w * 4, height: h * 4 });

        if (object.image) {
            element.style.backgroundImage = `url('${object.image}')`;
        } else {
            element.style.background = 'rgba(0,0,0,.8)';
        }
        element.style.backgroundSize = 'cover';
        element.style.backgroundPosition = 'center';
        element.innerHTML = `<div class="bloginner">${object.title}</div><img src="https://art.istic.net/iconography/aqcom/logo_64.png" height="64" width="64" class="aqcomicon">`;

        element.title = describe(object, decodeEntities(object.title));

        return element;
    },
};

/** Run every formatter that applies to an entry; null means "don't show it". */
export function format(object, element, context) {
    const keys = [object.source, object.type, `${object.source}_${object.type}`];
    let result = element;

    for (const key of keys) {
        const formatter = Object.hasOwn(formatters, key) ? formatters[key] : null;
        if (result && formatter) {
            result = formatter(object, result, context);
        }
    }

    return result;
}
