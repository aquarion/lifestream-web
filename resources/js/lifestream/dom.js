// Small DOM helpers standing in for the jQuery calls the original site used.

/** Decode HTML entities in a string (e.g. "&amp;" -> "&"). */
export function decodeEntities(html) {
    const element = document.createElement('p');
    element.innerHTML = html ?? '';
    return element.textContent ?? '';
}

/** Capitalise the first letter of each word. */
export function capitalize(text) {
    return text.replace(/(?:^|\s)\S/g, (letter) => letter.toUpperCase());
}

/** Turn a source or type name into a CSS class name. */
export function className(name) {
    return String(name ?? '').replace(/\s/g, '_');
}

/** Create an element from an HTML string. */
export function fromHtml(html) {
    const template = document.createElement('template');
    template.innerHTML = html.trim();
    return template.content.firstElementChild;
}

/** Set an element's content-box size in pixels, like jQuery's .width()/.height(). */
export function setSize(element, { width, height }) {
    if (width !== undefined) {
        element.style.width = `${width}px`;
    }
    if (height !== undefined) {
        element.style.height = `${height}px`;
    }
}

/** An element's content-box size (excluding padding and border), like jQuery's .width()/.height(). */
export function innerSize(element) {
    const style = getComputedStyle(element);
    return {
        width: element.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight),
        height: element.clientHeight - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom),
    };
}

/** An element's size including padding and border, like jQuery's .outerWidth()/.outerHeight(). */
export function outerSize(element) {
    return { width: element.offsetWidth, height: element.offsetHeight };
}
