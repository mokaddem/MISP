<?php

/**
 * What each lean looks like.
 *
 * A lean is drawn in four places that cannot afford to disagree: the
 * Assessment tab's hero, the tab bar's state pill, the Overview rail
 * card's pill, and the card border. Each needed the mapping in a
 * slightly different form — a raw colour for a CSS variable, a glyph
 * for the hero, a slug for a modifier class — so each grew its own copy
 * of it.
 *
 * They live here instead. Adding a lean is one entry, and a lean the
 * table does not know about degrades to the neutral treatment rather
 * than to no treatment at all.
 *
 * **This was `ValueDisposition` until D11's rename landed in phase 9.**
 * The four states are the same four states and the colours are the same
 * colours; what changed is that the key is the axis the engine actually
 * computes. `MALICIOUS` was a claim about the value and `threat` is a
 * claim about the record — which is the whole of D11 in one column
 * heading — and keeping a translation table between them is how a shim
 * becomes permanent (`10-wiring.md` §7.7).
 *
 * `definite` is the one non-obvious column: it says whether the lean
 * names a state or refuses to. `threat` and `benign` are answers and
 * are drawn as solid chips; `contested` and `none` are the absence of
 * one and are drawn quietly, because a loud chip reading *Contested*
 * claims a certainty the record does not have.
 *
 * `ink` is `colour`'s reading tone, and the two are not interchangeable
 * (`value-palette.css`): `colour` is a fill, held to a fill's 3:1, and
 * every one of these four misses small text's 4.5:1 when a rule reaches
 * for it as a text colour — amber worst at 2.14:1 on the hero. A rule
 * painting a surface takes `colour`; a rule painting words takes `ink`.
 */
class ValueLean
{
    const TREATMENTS = array(
        'threat' => array(
            'label' => 'Asserted threat',
            'colour' => 'var(--vp-mal)',
            'ink' => 'var(--vp-mal-ink)',
            'icon' => 'fas fa-triangle-exclamation',
            'slug' => 'threat',
            'definite' => true,
        ),
        'benign' => array(
            'label' => 'Asserted benign',
            'colour' => 'var(--vp-ben)',
            'ink' => 'var(--vp-ben-ink)',
            'icon' => 'fas fa-circle-check',
            'slug' => 'benign',
            'definite' => true,
        ),
        'contested' => array(
            'label' => 'Contested',
            'colour' => 'var(--vp-conflict)',
            'ink' => 'var(--vp-conflict-ink)',
            'icon' => 'fas fa-circle-exclamation',
            'slug' => 'contested',
            'definite' => false,
        ),
        'none' => array(
            'label' => 'Nothing asserted',
            'colour' => 'var(--vp-unknown)',
            'ink' => 'var(--vp-unknown-ink)',
            'icon' => 'fas fa-circle-question',
            'slug' => 'none',
            'definite' => false,
        ),
    );

    const NEUTRAL = array(
        'label' => 'Nothing asserted',
        'colour' => 'var(--vp-unknown)',
        'ink' => 'var(--vp-unknown-ink)',
        'icon' => 'fas fa-circle-question',
        'slug' => 'none',
        'definite' => false,
    );

    /**
     * @param string|null $lean
     * @return array
     */
    public static function treatment($lean)
    {
        return self::TREATMENTS[$lean] ?? self::NEUTRAL;
    }

    /**
     * The words the page says for a lean.
     *
     * *Asserted* is doing the work in two of the four, and it is the
     * word D11 chose the axis for: the page is reporting what the
     * record claims, not agreeing with it. A reader who disagrees with
     * *Asserted threat* is disagreeing with the organisations that
     * reported it, which is a conversation they can have; a reader who
     * disagreed with `MALICIOUS` was disagreeing with MISP.
     *
     * @param string|null $lean
     * @return string Translated, and safe to print
     */
    public static function label($lean)
    {
        return __(self::treatment($lean)['label']);
    }

    /**
     * @param string|null $lean
     * @return string A CSS variable reference, never a raw hex
     */
    public static function colour($lean)
    {
        return self::treatment($lean)['colour'];
    }

    /**
     * @param string|null $lean
     * @return string A CSS variable reference; `colour`'s tone for
     *                words rather than for a surface
     */
    public static function ink($lean)
    {
        return self::treatment($lean)['ink'];
    }

    /**
     * @param string|null $lean
     * @return string Font Awesome classes
     */
    public static function icon($lean)
    {
        return self::treatment($lean)['icon'];
    }

    /**
     * @param string|null $lean
     * @return string Lowercase, for a modifier class
     */
    public static function slug($lean)
    {
        return self::treatment($lean)['slug'];
    }

    /**
     * @param string|null $lean
     * @return bool Whether the lean names a state rather than refusing
     *              to
     */
    public static function isDefinite($lean)
    {
        return self::treatment($lean)['definite'];
    }

    /**
     * Whether an assessment gets the contested layout — the two opposed
     * cases — or the agreeing one.
     *
     * **One definition, because two readers of it disagreeing is a page
     * that contradicts itself.** The tab and its rail are separate
     * requests rendering separate templates, and both have to pick the
     * same branch or a reader gets an agreeing argument beside a
     * contested rail. Phase 9's first build did exactly that for one
     * commit: the controller had learned the second half of the
     * condition and `value_verdict_aside.ctp` had not, so `8.8.8.8`
     * drew its ledger next to an empty column.
     *
     * The second half is that the layout is built around **two** cases
     * and reads them positionally, so the lean alone does not qualify a
     * value for it.
     *
     * @param array $assessment
     * @return bool
     */
    public static function hasConflictedLayout(array $assessment)
    {
        $lean = isset($assessment['lean']) ? $assessment['lean'] : null;
        if ($lean !== 'contested') {
            return false;
        }
        return !empty($assessment['cases']);
    }

    /**
     * The two colours a signal is drawn in: the one that supports the
     * record's own assertion and the one that argues with it.
     *
     * A ▲ row is not "more malicious", it is "supports the lean" — the
     * same thing on a `threat` record and the opposite on a `benign`
     * one. Painting ▲ red either way would show the benign case's
     * strongest evidence in the colour of a threat.
     *
     * **It is the lean band's pair now, and not the ledger's**
     * (`review-2026-09-13.md` §D1). The ledger holds the rows that
     * weigh the record rather than read the value, and those have no
     * side to support: *four organisations reported it* is the same
     * fact under either reading, so it is drawn in ink weight
     * (`--vp-weighs-carries`) and this pair does not reach it. What
     * still uses the swap is the band that explains the reading, the
     * Overview card's badge, and the falsifiability arrows — every
     * surface whose subject really is the lean.
     *
     * Red stays the colour of the malicious reading in both directions,
     * so a reader who has learnt the palette on one value does not have
     * to unlearn it on the next.
     *
     * Emitted as an inline style rather than resolved in the stylesheet
     * because each assessment card is its own lazily-loaded fragment,
     * and a fragment cannot count on an ancestor having been told which
     * value it belongs to.
     *
     * @param string|null $lean
     * @return string A `style` attribute body
     */
    public static function directionStyle($lean)
    {
        $benign = $lean === 'benign';
        $with = $benign ? 'ben' : 'mal';
        $against = $benign ? 'mal' : 'ben';
        /*
         * The ink pair travels with the hue pair. A surface that paints
         * direction on a dark ground needs the lighter tone, and a rule
         * that reaches for `--vp-mal-ink` directly cannot be swapped —
         * so dark mode would go on showing the malicious reading of a
         * benign assessment no matter what the two lines above say.
         */
        return '--vp-dir-with: var(--vp-' . $with . ')'
            . '; --vp-dir-against: var(--vp-' . $against . ')'
            . '; --vp-dir-with-ink: var(--vp-' . $with . '-ink)'
            . '; --vp-dir-against-ink: var(--vp-' . $against . '-ink);';
    }
}
