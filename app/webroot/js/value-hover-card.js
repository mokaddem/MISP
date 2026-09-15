/*
 * The value hover card.
 *
 * Hovering a value anywhere in MISP fetches the assessment of that
 * value and floats it beside the cursor. Proposal C of
 * `38-hover-card.md`; the markup is `value_hover_card.ctp` and the
 * rules are `value-hover-card.css`.
 *
 * **Its own listener rather than a Bootstrap popover**, for the reason
 * `value_claim_target_card.ctp` states: MISP initialises
 * `[data-bs-toggle]` once at `DOMContentLoaded`, so anything that
 * arrives later — every lazily-loaded attribute table on an event page
 * — would silently never bind. Delegation on `document.body` has no
 * such moment to miss.
 *
 * It deliberately does not reuse the hover-enrichment IIFE in
 * `mispOvermind.js`, which is the same shape but hardcoded to one URL
 * and one data attribute. Folding the two together is worth doing and
 * is not worth doing in the change that introduces the second one.
 */
(function () {
    'use strict';

    if (window._vpHoverCardBound) {
        return;
    }
    window._vpHoverCardBound = true;

    var TRIGGER = '.vp-hc-trigger[data-vp-hc-value]';

    /*
     * Long enough that sweeping a column does not fire fifty requests,
     * short enough that a deliberate hover feels answered. The
     * enrichment popover next door uses 400/250 and this is a cheaper
     * question, so it may be a little quicker to speak.
     */
    var SHOW_DELAY = 320;
    var HIDE_DELAY = 220;

    var host = null;
    var showTimer = null;
    var hideTimer = null;
    var currentValue = null;
    var token = 0;
    var inFlight = null;
    var cache = {};

    function base() {
        return (typeof baseurl === 'string' ? baseurl : '');
    }

    function element() {
        if (host) {
            return host;
        }
        host = document.createElement('div');
        host.id = 'vpHoverCard';
        host.setAttribute('role', 'tooltip');
        /*
         * Entering the card cancels the hide, so a reader can reach
         * *Open profile* without the thing they are reaching for
         * disappearing on the way.
         */
        host.addEventListener('mouseenter', function () {
            window.clearTimeout(hideTimer);
        });
        host.addEventListener('mouseleave', hide);
        document.body.appendChild(host);
        return host;
    }

    /*
     * Placed against the anchor and clamped to the viewport: below and
     * left-aligned by default, flipped above when the space below is
     * shorter than the card, and pulled back inside when the anchor is
     * near the right edge. Measured after the markup is in, because a
     * card whose height is not known yet cannot be flipped correctly.
     */
    function place(anchor) {
        var node = element();
        var rect = anchor.getBoundingClientRect();
        var box = node.getBoundingClientRect();
        var gap = 8;
        var margin = 12;

        var top = rect.bottom + gap;
        if (top + box.height > window.innerHeight - margin) {
            var above = rect.top - gap - box.height;
            if (above >= margin) {
                top = above;
            } else {
                top = Math.max(
                    margin,
                    window.innerHeight - margin - box.height
                );
            }
        }

        var left = rect.left;
        if (left + box.width > window.innerWidth - margin) {
            left = window.innerWidth - margin - box.width;
        }
        node.style.top = Math.round(Math.max(margin, top)) + 'px';
        node.style.left = Math.round(Math.max(margin, left)) + 'px';
    }

    function render(anchor, html) {
        var node = element();
        node.innerHTML = html;
        node.classList.add('vp-hc-shown');
        place(anchor);
    }

    function hide() {
        window.clearTimeout(showTimer);
        window.clearTimeout(hideTimer);
        currentValue = null;
        if (inFlight) {
            inFlight.abort();
            inFlight = null;
        }
        if (host) {
            host.classList.remove('vp-hc-shown');
        }
    }

    function show(anchor, value) {
        var mine = ++token;
        currentValue = value;

        if (Object.prototype.hasOwnProperty.call(cache, value)) {
            render(anchor, cache[value]);
            return;
        }

        /*
         * No spinner. The card answers in one query budget and a
         * skeleton that flashes for 80ms is noise; a card that simply
         * appears when it is ready reads as faster than one that
         * announces it is coming. If the fetch is slow the reader has
         * lost nothing — nothing was covering their row.
         */
        if (window.AbortController) {
            inFlight = new window.AbortController();
        }
        var options = {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        };
        if (inFlight) {
            options.signal = inFlight.signal;
        }

        fetch(
            base() + '/values/viewHoverCard/' + encodeURIComponent(value),
            options
        ).then(function (res) {
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }
            return res.text();
        }).then(function (html) {
            cache[value] = html;
            // The pointer may have moved on while this was in the air.
            if (mine === token && currentValue === value) {
                render(anchor, html);
            }
        }).catch(function (err) {
            if (err && err.name === 'AbortError') {
                return;
            }
            if (mine !== token) {
                return;
            }
            render(
                anchor,
                '<div class="vp-hc-error">'
                + 'Could not load this value’s assessment.'
                + '</div>'
            );
        });
    }

    function triggerFor(target) {
        if (!target || !target.closest) {
            return null;
        }
        return target.closest(TRIGGER);
    }

    document.body.addEventListener('mouseover', function (event) {
        var trigger = triggerFor(event.target);
        if (!trigger) {
            return;
        }
        var value = trigger.getAttribute('data-vp-hc-value');
        if (!value) {
            return;
        }
        window.clearTimeout(hideTimer);
        window.clearTimeout(showTimer);
        showTimer = window.setTimeout(function () {
            show(trigger, value);
        }, SHOW_DELAY);
    });

    document.body.addEventListener('mouseout', function (event) {
        if (!triggerFor(event.target)) {
            return;
        }
        window.clearTimeout(showTimer);
        hideTimer = window.setTimeout(hide, HIDE_DELAY);
    });

    /*
     * Keyboard parity. The trigger is focusable, so tabbing onto it
     * shows the card without the delay a pointer needs — a reader who
     * tabbed there has already declared intent.
     */
    document.body.addEventListener('focusin', function (event) {
        var trigger = triggerFor(event.target);
        if (!trigger) {
            return;
        }
        var value = trigger.getAttribute('data-vp-hc-value');
        if (value) {
            show(trigger, value);
        }
    });

    document.body.addEventListener('focusout', function (event) {
        if (triggerFor(event.target)) {
            hideTimer = window.setTimeout(hide, HIDE_DELAY);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            hide();
        }
    });

    // Capture, so a scroll inside `.table-responsive` counts too.
    window.addEventListener('scroll', hide, true);
    window.addEventListener('resize', hide);
}());
