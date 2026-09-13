<?php

/**
 * What each verdict disposition looks like.
 *
 * A disposition is drawn in four places that cannot afford to disagree:
 * the Verdict tab's hero, the tab bar's state pill, the Overview rail
 * card's pill, and the card border. Each needed the mapping in a
 * slightly different form — a raw colour for a CSS variable, a glyph
 * for the hero, a slug for a modifier class — so each grew its own copy
 * of it.
 *
 * They live here instead. Adding a disposition is one entry, and a
 * disposition the table does not know about degrades to the neutral
 * treatment rather than to no treatment at all.
 *
 * `definite` is the one non-obvious column: it says whether the verdict
 * names a state or refuses to. MALICIOUS and BENIGN are answers and are
 * drawn as solid chips; CONFLICTED and UNKNOWN are the absence of one
 * and are drawn quietly, because a loud chip reading CONFLICTED claims
 * a certainty the value does not have.
 */
class ValueDisposition
{
    const TREATMENTS = array(
        'MALICIOUS' => array(
            'colour' => 'var(--vp-mal)',
            'icon' => 'fas fa-triangle-exclamation',
            'slug' => 'malicious',
            'definite' => true,
        ),
        'BENIGN' => array(
            'colour' => 'var(--vp-ben)',
            'icon' => 'fas fa-circle-check',
            'slug' => 'benign',
            'definite' => true,
        ),
        'CONFLICTED' => array(
            'colour' => 'var(--vp-conflict)',
            'icon' => 'fas fa-circle-exclamation',
            'slug' => 'conflicted',
            'definite' => false,
        ),
        'UNKNOWN' => array(
            'colour' => 'var(--vp-unknown)',
            'icon' => 'fas fa-circle-question',
            'slug' => 'unknown',
            'definite' => false,
        ),
    );

    const NEUTRAL = array(
        'colour' => 'var(--vp-unknown)',
        'icon' => 'fas fa-circle-question',
        'slug' => 'unknown',
        'definite' => false,
    );

    /**
     * @param string|null $disposition
     * @return array
     */
    public static function treatment($disposition)
    {
        return self::TREATMENTS[$disposition] ?? self::NEUTRAL;
    }

    /**
     * @param string|null $disposition
     * @return string A CSS variable reference, never a raw hex
     */
    public static function colour($disposition)
    {
        return self::treatment($disposition)['colour'];
    }

    /**
     * @param string|null $disposition
     * @return string Font Awesome classes
     */
    public static function icon($disposition)
    {
        return self::treatment($disposition)['icon'];
    }

    /**
     * @param string|null $disposition
     * @return string Lowercase, for a modifier class
     */
    public static function slug($disposition)
    {
        return self::treatment($disposition)['slug'];
    }

    /**
     * @param string|null $disposition
     * @return bool Whether the verdict names a state rather than
     *              refusing to
     */
    public static function isDefinite($disposition)
    {
        return self::treatment($disposition)['definite'];
    }

    /**
     * Whether a verdict gets the conflicted layout — the two opposed
     * cases — or the agreeing one.
     *
     * **One definition, because two readers of it disagreeing is a page
     * that contradicts itself.** The tab and its rail are separate
     * requests rendering separate templates, and both have to pick the
     * same branch or a reader gets an agreeing argument beside a
     * conflicted rail. Phase 9's first build did exactly that for one
     * commit: the controller had learned the second half of the
     * condition and `value_verdict_aside.ctp` had not, so `8.8.8.8`
     * drew its ledger next to an empty column.
     *
     * The second half is that the layout is built around **two** cases
     * and reads them positionally, so the disposition alone does not
     * qualify a value for it. Nothing in phases 1 to 8 produces `cases`
     * (`prd/analyst-profile/10-wiring.md` §2.2), which is why a
     * contested value renders the agreeing layout today — the engine
     * re-anchors a contested ledger to threat-signed for precisely this
     * reading, so the rows and the word CONFLICTED are still the
     * assessment's own. **The condition retires itself** the day `cases`
     * has a producer.
     *
     * @param array $verdict
     * @return bool
     */
    public static function hasConflictedLayout(array $verdict)
    {
        $disposition = isset($verdict['disposition'])
            ? $verdict['disposition']
            : null;
        if ($disposition !== 'CONFLICTED') {
            return false;
        }
        return !empty($verdict['cases']);
    }

    /**
     * The two colours a signal is drawn in: the one that supports the
     * stated disposition and the one that argues with it.
     *
     * A ▲ row is not "more malicious", it is "supports the verdict" —
     * the same thing on a MALICIOUS value and the opposite on a BENIGN
     * one. Painting ▲ red either way would show the benign case's
     * strongest evidence in the colour of a threat.
     *
     * Red stays the colour of the malicious reading in both directions,
     * so a reader who has learnt the palette on one value does not have
     * to unlearn it on the next.
     *
     * Emitted as an inline style rather than resolved in the stylesheet
     * because each verdict card is its own lazily-loaded fragment, and
     * a fragment cannot count on an ancestor having been told which
     * value it belongs to.
     *
     * @param string|null $disposition
     * @return string A `style` attribute body
     */
    public static function directionStyle($disposition)
    {
        $benign = $disposition === 'BENIGN';
        $with = $benign ? 'ben' : 'mal';
        $against = $benign ? 'mal' : 'ben';
        /*
         * The ink pair travels with the hue pair. A surface that paints
         * direction on a dark ground needs the lighter tone, and a rule
         * that reaches for `--vp-mal-ink` directly cannot be swapped —
         * so dark mode would go on showing the malicious reading of a
         * benign verdict no matter what the two lines above say.
         */
        return '--vp-dir-with: var(--vp-' . $with . ')'
            . '; --vp-dir-against: var(--vp-' . $against . ')'
            . '; --vp-dir-with-ink: var(--vp-' . $with . '-ink)'
            . '; --vp-dir-against-ink: var(--vp-' . $against . '-ink);';
    }
}
