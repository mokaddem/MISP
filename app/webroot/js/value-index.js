/*
 * `/values/index` — the prompt's behaviour.
 *
 * **It waits for the DOM.** `assetLoader` puts this script tag above
 * the markup, so a binding made at parse time binds to nothing and the
 * page still draws perfectly — which is how a dead prompt ships
 * looking finished.
 *
 * Three jobs, and the page works without any of them:
 *
 *   1. Enter submits, Shift+Enter makes a line, an empty box cannot
 *      be pressed. Phase 3's.
 *   2. The counter and the verb, live as the reader types.
 *   3. A list is posted to `/values/triage` and its answer swapped in
 *      under the box, rather than reloading the page around a paste
 *      the reader can still see. With no script the same form posts
 *      to `/values/resolve`, which routes a list to the same rows and
 *      renders the whole page.
 */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    /*
     * **A second parser, and deliberately a cruder one.**
     * `ValueInputTool` is the authority and this only has to put a
     * number beside the box before the reader presses: it picks the
     * same separator, strips the same wrapping quotes, splits the same
     * composites and drops the same exact duplicates.
     *
     * What it does not do is refang, because the refang table is
     * `ComplexTypeTool`'s and copying it here would be two tables to
     * keep in step for a number. The only paste where that changes a
     * count is one carrying an indicator in both its fanged and its
     * defanged spelling — two values here, one there. **That is why
     * the button stays pressable over the cap** where the prototype
     * disabled it: a count that can be one high must not be the thing
     * that refuses, so the counter warns and the server, which parsed
     * properly, refuses with the real number.
     */
    var QUOTES = {
        '"': '"',
        "'": "'",
        '`': '`',
        '“': '”',
        '‘': '’'
    };
    /*
     * The same invisible characters `ValueInputTool::TRIM_WIDE`
     * takes — a non-breaking space off a PDF, a zero-width space
     * off a web page, a BOM off an exported CSV — on top of
     * ordinary whitespace. Any of the three makes a field that
     * looks empty count as a value, or one that looks like its
     * neighbour fail to dedupe against it.
     */
    var TRIM = /^[\s\u00a0\u200b\ufeff]+|[\s\u00a0\u200b\ufeff]+$/g;
    /* `preg_split('/\R/')`'s line breaks, as a literal. */
    var BREAK = /\r\n|[\n\r\u000b\f\u0085\u2028\u2029]/;

    function trimValue(text) {
        return text.replace(TRIM, '');
    }

    function unquote(text) {
        if (text.length < 2) {
            return text;
        }
        var close = QUOTES[text.charAt(0)];
        if (close && text.charAt(text.length - 1) === close) {
            return trimValue(text.slice(1, -1));
        }
        return text;
    }

    function fields(raw) {
        var lines = raw.split(BREAK)
            .map(trimValue)
            .filter(function (line) { return line !== ''; });
        if (lines.length > 1) {
            return lines;
        }
        return (lines[0] || '').split(',')
            .map(trimValue)
            .filter(function (field) { return field !== ''; });
    }

    function countValues(raw) {
        var seen = Object.create(null);
        var n = 0;
        fields(raw).forEach(function (field) {
            var value = trimValue(unquote(field));
            if (value === '') {
                return;
            }
            var parts = value.indexOf('|') === -1
                ? [value]
                : [value.slice(0, value.indexOf('|')),
                    value.slice(value.indexOf('|') + 1)];
            parts.forEach(function (part) {
                part = trimValue(unquote(trimValue(part)));
                if (part === '' || seen[part]) {
                    return;
                }
                seen[part] = true;
                n++;
            });
        });
        return n;
    }

    ready(function () {
        var form = document.getElementById('vi-prompt');
        if (!form) {
            return;
        }
        var box = form.querySelector('[data-vi-box]');
        var go = form.querySelector('[data-vi-go]');
        if (!box || !go) {
            return;
        }
        var counter = form.querySelector('[data-vi-count]');
        var verb = form.querySelector('[data-vi-verb]');
        var out = document.querySelector('[data-vi-out]');
        var cap = counter
            ? parseInt(counter.getAttribute('data-vi-cap'), 10) || 100
            : 100;
        var n = 0;
        var busy = false;

        /*
         * The verb says what pressing will do, which is the whole of
         * how a reader learns this box does two things. One value
         * opens a profile; a list is assessed, and the count is in the
         * label because *Assess* over forty values and *Assess* over
         * four read identically at a glance.
         */
        function say() {
            n = countValues(box.value);
            if (counter) {
                counter.innerHTML = '';
                var strong = document.createElement('b');
                strong.textContent = String(n);
                counter.appendChild(strong);
                counter.appendChild(
                    document.createTextNode('/' + cap)
                );
                counter.classList.toggle('is-over', n > cap);
            }
            if (verb) {
                verb.textContent = n > 1
                    ? verb.getAttribute('data-vi-many')
                        .replace('%d', String(n))
                    : verb.getAttribute('data-vi-one');
            }
            go.disabled = busy || box.value.trim() === '';
        }

        /*
         * Enter submits and Shift+Enter makes a line, which is the
         * shape every paste box the reader already uses has. It is a
         * textarea rather than an input because a paste is many
         * lines, and a one-line field that grows would change under
         * the reader's cursor between one press and the next.
         */
        box.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' || event.shiftKey) {
                return;
            }
            if (event.ctrlKey || event.metaKey || event.altKey) {
                return;
            }
            event.preventDefault();
            if (box.value.trim() === '') {
                return;
            }
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });

        /*
         * A list is worked in place. A single value is left to the
         * browser, because its answer is a redirect to the profile
         * and following one in script would fetch that whole page to
         * throw it away.
         *
         * A failed fetch does not swallow the press: the form is
         * submitted normally, which is the same answer by the longer
         * road.
         */
        form.addEventListener('submit', function (event) {
            if (busy || n < 2 || !out || !window.fetch) {
                return;
            }
            event.preventDefault();
            busy = true;
            say();
            out.setAttribute('aria-busy', 'true');
            fetch(form.getAttribute('data-vi-triage'), {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error(String(response.status));
                }
                return response.text();
            }).then(function (html) {
                out.innerHTML = html;
                busy = false;
                out.removeAttribute('aria-busy');
                say();
            }).catch(function () {
                busy = false;
                out.removeAttribute('aria-busy');
                form.submit();
            });
        });

        box.addEventListener('input', say);
        say();

        /*
         * The caret goes to the end rather than to the start: the box
         * comes back filled after an answer, and a reader correcting
         * what they pasted is at the end of it.
         */
        if (box.value !== '') {
            box.setSelectionRange(box.value.length, box.value.length);
        }
    });
}());
