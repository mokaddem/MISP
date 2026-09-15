<?php

App::uses('ComplexTypeTool', 'Tools');

/**
 * What the reader typed, turned into values.
 *
 * `/values/index` has two ways in — a box that takes one value and a
 * box that takes a pasted list — and they must not disagree about what
 * a value is. One of them redirects to a profile and the other links to
 * a hundred of them; a reader who resolves `hxxp://evil[.]com` and gets
 * an answer, then pastes the same string in a list and gets nothing,
 * has been told the page is unreliable rather than that their input was
 * odd. So both go through this file. `value-index.md` §3, V4.
 *
 * Two functions, and the difference between them is the whole design:
 *
 *   normalise()      one string in, one value out. Splits nothing.
 *   normaliseMany()  a paste in, a list of values out. Splits.
 *
 * `normalise()` splitting nothing is what makes a value containing a
 * separator reachable at all — see the comma rule below.
 *
 * **Nothing here is altered silently.** Both functions report every
 * transformation they applied, because the page renders the reader's
 * own strings back to them and a table whose rows are not the strings
 * the reader pasted is a table they cannot check. `normalise()` returns
 * a `changed` list; `normaliseMany()` returns counts under the same
 * token names, so the two cannot spell a transformation differently.
 *
 * **It does not lowercase.** The shipped schema collates `value1` as
 * `utf8mb3_unicode_ci` but the verification instance runs `utf8mb3_bin`,
 * where `Google.com` and `google.com` are different values — so
 * case-folding here would silently merge two values on one instance and
 * not on the other. Deduplication is exact for the same reason. Case is
 * the resolver's decision to make, once, on the miss path
 * (`value-index.md` §1.2, §7.1).
 *
 * Pure and static, and it takes no `$user`:
 * `value-profile-live/00-contract.md` §14.5. It reads no table, so
 * §14.3's `value1`/`value2` rule is satisfied by having nothing to say.
 */
class ValueInputTool
{
    /**
     * Wrapping quotes were stripped.
     *
     * The token is also the `report` key `normaliseMany()` counts
     * under, so the row's note and the summary line above the table
     * cannot name the same transformation two ways.
     */
    const UNQUOTED = 'unquoted';

    /** The same, for a value that arrived defanged. */
    const REFANGED = 'refanged';

    /**
     * How many values a paste may carry.
     *
     * About the size of a report's IOC section. It lives here rather
     * than on the controller because the page mirrors it client-side as
     * a live count, and two copies of a number the reader is measured
     * against drift. `value-index.md` §7.2, G1.
     */
    const CAP = 100;

    /** One value per line; a comma is part of the value. */
    const BY_LINES = 'lines';

    /** One line; commas separate the values on it. */
    const BY_COMMAS = 'commas';

    /**
     * Quote pairs stripped when they wrap the whole value.
     *
     * The straight pair covers a CSV cell, the backtick a value lifted
     * out of markdown, and the typographic pairs a report pasted from a
     * PDF or a word processor — which is where an IOC section usually
     * comes from, and which is the reason the list is not just `"`.
     */
    const QUOTE_PAIRS = array(
        '"' => '"',
        "'" => "'",
        '`' => '`',
        '“' => '”',
        '‘' => '’',
    );

    /**
     * Trimmed from both ends on top of what `trim()` takes.
     *
     * A non-breaking space off a PDF, a zero-width space off a web
     * page, a byte-order mark on the first cell of an exported CSV —
     * all three are what an IOC section is pasted out of, and all three
     * produce a value that looks character-for-character like the one
     * the reader meant and matches nothing. That is the worst failure
     * this file can have, because the page then shows the reader their
     * own string beside an honest *not recorded*.
     */
    const TRIM_WIDE = array("\xC2\xA0", "\xE2\x80\x8B", "\xEF\xBB\xBF");

    /**
     * The defanged forms core's own table does not carry.
     *
     * Applied **before** `ComplexTypeTool::REFANG_REGEX_TABLE`, which
     * is not a detail: core's rule for `[.]` does not consume the
     * spaces around it, so `example [dot] com` would come out of it as
     * `example . com` — still three tokens, and no longer refangable by
     * anything. Running the bracket forms here first, spaces included,
     * is what makes the spaced variants work.
     *
     * The bare-word forms require a non-space on each side so that
     * ` dot ` and ` at ` only ever join two things that were already
     * adjacent to them. They are case-insensitive because analysts
     * write `DOT` as often as `dot`.
     */
    const REFANG_EXTRA = array(
        '/\s*[\[\(\{]\s*(?:\.|dot)\s*[\]\)\}]\s*/i' => '.',
        '/\s*[\[\(\{]\s*(?:@|at)\s*[\]\)\}]\s*/i' => '@',
        '/\s*[\[\(\{]\s*(?::|colon)\s*[\]\)\}]\s*/i' => ':',
        '/(?<=\S)\s+dot\s+(?=\S)/i' => '.',
        '/(?<=\S)\s+at\s+(?=\S)/i' => '@',
    );

    /**
     * One string, as a value.
     *
     * **Splits nothing.** Not on commas, not on `|`. A reader who means
     * one value means the string they typed, and this is the only path
     * on the page by which a value containing a separator can be
     * reached at all.
     *
     * Whitespace trimming is not reported. Every line of a paste made
     * on Windows carries a `\r`, so a `trimmed` token would appear on
     * every row of every such table and say nothing; the tokens that
     * are reported are the ones that change what the string *means*.
     *
     * @param string|null $raw
     * @return array{value: string|null, changed: array<string>}
     */
    public static function normalise($raw)
    {
        $changed = array();
        $value = self::trimValue((string)$raw);

        $unquoted = self::stripWrappingQuotes($value);
        if ($unquoted !== $value) {
            $changed[] = self::UNQUOTED;
            $value = self::trimValue($unquoted);
        }

        $refanged = self::refang($value);
        if ($refanged !== $value) {
            $changed[] = self::REFANGED;
            $value = self::trimValue($refanged);
        }

        return array(
            'value' => $value === '' ? null : $value,
            'changed' => $changed,
        );
    }

    /**
     * A paste, as a list of values.
     *
     * In order: choose the separator, normalise each field, split
     * composites, deduplicate preserving first-seen order, then cap.
     * The cap is applied last so that a reader who pasted the same
     * hundred values twice is not refused for it.
     *
     * **The separator is the paste's own.** A paste with more than one
     * line has already said what its unit is, and splitting those lines
     * on commas as well could only take values away from it; a paste on
     * one line has no other separator available, so commas govern
     * there. A pasted spreadsheet column and a pasted `a, b, c` list
     * therefore both work, and a value containing a comma survives when
     * it arrives among other lines — which is the documented limit of
     * comma-splitting rather than a solution to it (`value-index.md`
     * §3.2, G5). A value containing a comma and pasted *alone* still
     * splits; `normalise()` is where that reader is served.
     *
     * `report` counts the input, not the returned slice: a value beyond
     * the cap still counted towards `refanged` and `duplicates`,
     * because the caller refuses an overflowing paste outright (§7.2)
     * and the numbers it refuses with should describe what was pasted.
     *
     * @param string|null $raw
     * @param int|null $cap Values returned at most; null or <1 for none
     * @return array{values: array<string>, report: array}
     */
    public static function normaliseMany($raw, $cap = self::CAP)
    {
        $raw = (string)$raw;
        /*
         * `/u` fails outright on a stray byte rather than skipping it,
         * and a paste out of a report carries one often enough that
         * losing the whole list to it is not acceptable. The byte-mode
         * split finds the same line breaks.
         */
        $lines = preg_split('/\R/u', $raw);
        if ($lines === false) {
            $lines = preg_split('/\R/', $raw);
        }
        $lines = self::cleanFields($lines === false ? array() : $lines);

        $separator = count($lines) > 1 ? self::BY_LINES : self::BY_COMMAS;
        if ($separator === self::BY_LINES) {
            $fields = $lines;
        } else {
            $only = isset($lines[0]) ? $lines[0] : '';
            $fields = self::cleanFields(explode(',', $only));
        }

        $report = array(
            'lines' => count($lines),
            'fields' => count($fields),
            'separator' => $separator,
            'empty' => 0,
            'commas_kept' => 0,
            self::UNQUOTED => 0,
            self::REFANGED => 0,
            'composites' => 0,
            'duplicates' => 0,
            'total' => 0,
            'returned' => 0,
            'cap' => $cap,
            'overflow' => 0,
        );

        $produced = array();
        foreach ($fields as $field) {
            if ($separator === self::BY_LINES
                && strpos($field, ',') !== false
            ) {
                $report['commas_kept']++;
            }
            $one = self::normalise($field);
            if ($one['value'] === null) {
                $report['empty']++;
                continue;
            }
            $parts = self::splitComposite($one['value']);
            if (count($parts) > 1) {
                $report['composites']++;
            }
            foreach ($parts as $part) {
                $produced[] = array(
                    'value' => $part['value'],
                    'changed' => array_values(array_unique(
                        array_merge($one['changed'], $part['changed'])
                    )),
                );
            }
        }

        /*
         * `$seen` is keyed by the value, and PHP turns a key like
         * `'123'` into the integer 123 on the way in. That is harmless
         * for a membership test — two distinct strings never land on
         * one key — but it is why the values themselves are collected
         * in a separate list rather than read back off `array_keys()`,
         * which would hand the caller integers.
         */
        $seen = array();
        $values = array();
        foreach ($produced as $item) {
            if (isset($seen[$item['value']])) {
                $report['duplicates']++;
                continue;
            }
            $seen[$item['value']] = true;
            $values[] = $item['value'];
            foreach ($item['changed'] as $token) {
                $report[$token]++;
            }
        }

        $report['total'] = count($values);
        if ($cap !== null && $cap > 0 && count($values) > $cap) {
            $report['overflow'] = count($values) - $cap;
            $values = array_slice($values, 0, $cap);
        }
        $report['returned'] = count($values);

        return array('values' => $values, 'report' => $report);
    }

    /**
     * A composite's halves, each a value in its own right.
     *
     * `example.com|1.2.3.4` is one attribute and two identities: the
     * halves are what `value1` and `value2` hold, and `Value`'s own
     * docblock is explicit that a composite contributes two. Split on
     * the **first** pipe only, because every composite type MISP
     * defines has exactly two parts, so a later pipe belongs to the
     * second half.
     *
     * A value whose halves both normalise to nothing is handed back
     * whole rather than dropped — the reader gets their string and the
     * *not recorded* answer, which is better than a row that vanished.
     *
     * @param string $value A value, already normalised
     * @return array<array{value: string, changed: array<string>}>
     */
    private static function splitComposite($value)
    {
        if (strpos($value, '|') === false) {
            return array(array('value' => $value, 'changed' => array()));
        }
        $parts = array();
        foreach (explode('|', $value, 2) as $half) {
            $one = self::normalise($half);
            if ($one['value'] !== null) {
                $parts[] = $one;
            }
        }
        return $parts ? $parts
            : array(array('value' => $value, 'changed' => array()));
    }

    /**
     * A separator's units, trimmed, with the empty ones gone.
     *
     * @param array<string> $fields
     * @return array<string>
     */
    private static function cleanFields(array $fields)
    {
        $trimmed = array_map(
            function ($field) {
                return self::trimValue($field);
            },
            $fields
        );
        return array_values(array_filter($trimmed, 'strlen'));
    }

    /**
     * Whitespace off both ends, including the invisible kinds.
     *
     * Looped because the wide characters and the ASCII ones interleave
     * — a cell pasted out of a spreadsheet can arrive as BOM, space,
     * value — and one pass over each would leave the outer layer.
     *
     * @param string $value
     * @return string
     */
    private static function trimValue($value)
    {
        do {
            $before = $value;
            $value = trim($value);
            foreach (self::TRIM_WIDE as $wide) {
                $width = strlen($wide);
                if (strncmp($value, $wide, $width) === 0) {
                    $value = substr($value, $width);
                }
                if (strlen($value) >= $width
                    && substr($value, -$width) === $wide
                ) {
                    $value = substr($value, 0, strlen($value) - $width);
                }
            }
        } while ($value !== $before);
        return $value;
    }

    /**
     * Quotes wrapping the whole value, removed.
     *
     * One pass: `"'8.8.8.8'"` keeps its inner pair, which is the
     * conservative answer when the alternative is peeling a value that
     * legitimately begins and ends with an apostrophe.
     *
     * @param string $value
     * @return string
     */
    private static function stripWrappingQuotes($value)
    {
        foreach (self::QUOTE_PAIRS as $open => $close) {
            $head = strlen($open);
            $tail = strlen($close);
            if (strlen($value) < $head + $tail) {
                continue;
            }
            if (strncmp($value, $open, $head) === 0
                && substr($value, -$tail) === $close
            ) {
                return substr($value, $head, strlen($value) - $head - $tail);
            }
        }
        return $value;
    }

    /**
     * A defanged value, refanged.
     *
     * Values are **stored** refanged — `ComplexTypeTool` does it on the
     * way in, which is how the freetext importer turns a report's
     * `hxxp://evil[.]com` into an attribute — so a defanged paste that
     * is not refanged here resolves to nothing and the page reports,
     * truthfully and uselessly, that nobody has recorded it.
     *
     * Core's table is therefore read rather than copied: it is the same
     * vocabulary, it already knows forms this file would never have
     * listed (`hxtp`, `meow://`, `h[tt]p`), and a form core learns
     * later is one this page learns with it. Its `types` key is
     * deliberately ignored — every rule is applied to every value,
     * which is exactly what core's own `__refangInput()` does when it
     * has a string and no type, and the position this page is always
     * in.
     *
     * @param string $value
     * @return string
     */
    private static function refang($value)
    {
        foreach (self::REFANG_EXTRA as $from => $to) {
            $value = preg_replace($from, $to, $value);
        }
        foreach (ComplexTypeTool::REFANG_REGEX_TABLE as $regex) {
            $value = preg_replace($regex['from'], $regex['to'], $value);
        }
        return $value;
    }
}
