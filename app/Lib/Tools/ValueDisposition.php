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
        return '--vp-dir-with: '
            . ($benign ? 'var(--vp-ben)' : 'var(--vp-mal)')
            . '; --vp-dir-against: '
            . ($benign ? 'var(--vp-mal)' : 'var(--vp-ben)') . ';';
    }
}
