<?php

/**
 * The audit log's action vocabulary: what colour and glyph each
 * `AuditLog::ACTION_*` constant reads as, and what to call it.
 *
 * `Themed/Overmind/Elements/Logs/timeline.ctp` carries the same map
 * inline and keeps it for now — it has two live callers, and rewriting a
 * shared element with live callers is not fixture-first work. Anything
 * new reads the vocabulary from here, because one action drawn two ways
 * is one action meaning two things on two pages of the same product.
 *
 * The glyphs are that element's, unchanged. The colours are not: it
 * carries literal pastel fills that Bootstrap only defines for the
 * light theme, so this class names a Bootstrap *theme colour* instead
 * and the caller resolves `--bs-<name>-bg-subtle` and
 * `--bs-<name>-text-emphasis`, which both themes define. MISP's own
 * palette entries — `--bs-tag`, `--bs-galaxy` — are not candidates:
 * they are referenced by the component variants but never given a
 * subtle or emphasis pair in either theme.
 *
 * Eight usable theme colours against sixteen actions means the colour
 * names the *kind* of change and the glyph names the action: green
 * creates or restores, blue modifies or distributes, cyan annotates,
 * grey removes an annotation, amber removes reversibly, red removes for
 * good.
 */
class AuditActionMeta
{
    /**
     * Keyed by `AuditLog::ACTION_*` values, in the order a reader
     * should meet them: what made the record, what changed it, what
     * annotated it, what took the annotation off, what removed it.
     */
    const MAP = array(
        'add' => array(
            'colour' => 'success',
            'icon' => 'fas fa-plus-circle',
        ),
        'edit' => array(
            'colour' => 'primary',
            'icon' => 'fas fa-pencil-alt',
        ),
        'tag' => array(
            'colour' => 'info',
            'icon' => 'fas fa-tag',
        ),
        'tag_local' => array(
            'colour' => 'info',
            'icon' => 'fas fa-tag',
        ),
        'galaxy' => array(
            'colour' => 'info',
            'icon' => 'fas fa-atom',
        ),
        'galaxy_local' => array(
            'colour' => 'info',
            'icon' => 'fas fa-atom',
        ),
        'remove_tag' => array(
            'colour' => 'secondary',
            'icon' => 'fas fa-tag',
        ),
        'remove_local_tag' => array(
            'colour' => 'secondary',
            'icon' => 'fas fa-tag',
        ),
        'remove_galaxy' => array(
            'colour' => 'secondary',
            'icon' => 'fas fa-atom',
        ),
        'remove_local_galaxy' => array(
            'colour' => 'secondary',
            'icon' => 'fas fa-atom',
        ),
        'publish' => array(
            'colour' => 'primary',
            'icon' => 'fas fa-paper-plane',
        ),
        'publish_sightings' => array(
            'colour' => 'primary',
            'icon' => 'fas fa-eye',
        ),
        'soft_delete' => array(
            'colour' => 'warning',
            'icon' => 'fas fa-trash',
        ),
        'undelete' => array(
            'colour' => 'success',
            'icon' => 'fas fa-trash-restore',
        ),
        'delete' => array(
            'colour' => 'danger',
            'icon' => 'fas fa-trash-alt',
        ),
        'instantiate' => array(
            'colour' => 'secondary',
            'icon' => 'fas fa-clone',
        ),
    );

    /**
     * An action this class does not know still has to render, and has
     * to render as unknown rather than as one of the sixteen.
     */
    const FALLBACK = array(
        'colour' => 'secondary',
        'icon' => 'fas fa-circle',
    );

    /**
     * @param string $action An `AuditLog::ACTION_*` value
     * @return array `colour`, `icon`, `label`
     */
    public static function forAction($action)
    {
        $meta = isset(self::MAP[$action])
            ? self::MAP[$action]
            : self::FALLBACK;
        $meta['label'] = self::label($action);
        return $meta;
    }

    /**
     * Past tense, because every one of these is something that already
     * happened — an audit log has no pending state.
     *
     * @param string $action
     * @return string
     */
    public static function label($action)
    {
        $labels = array(
            'add' => __('Added'),
            'edit' => __('Edited'),
            'tag' => __('Tagged'),
            'tag_local' => __('Tagged locally'),
            'galaxy' => __('Galaxy attached'),
            'galaxy_local' => __('Galaxy attached locally'),
            'remove_tag' => __('Tag removed'),
            'remove_local_tag' => __('Local tag removed'),
            'remove_galaxy' => __('Galaxy removed'),
            'remove_local_galaxy' => __('Local galaxy removed'),
            'publish' => __('Published'),
            'publish_sightings' => __('Sightings published'),
            'soft_delete' => __('Soft-deleted'),
            'undelete' => __('Restored'),
            'delete' => __('Deleted'),
            'instantiate' => __('Instantiated'),
        );
        // The raw constant, not "Unknown": a reader who meets an action
        // this class has not caught up with is better served by its
        // name than by being told there isn't one.
        return isset($labels[$action]) ? $labels[$action] : $action;
    }

    /**
     * The CSS custom properties a caller hangs on the element that
     * carries the action's colour, so no stylesheet on the far end
     * needs a per-action rule and no literal hex reaches one.
     *
     * @param string $action
     * @return string A `style` attribute body, unescaped
     */
    public static function style($action)
    {
        $meta = self::forAction($action);
        return sprintf(
            '--vp-audit-bg: var(--bs-%1$s-bg-subtle);'
            . ' --vp-audit-fg: var(--bs-%1$s-text-emphasis);'
            . ' --vp-audit-edge: var(--bs-%1$s-border-subtle);'
            // The solid colour, for a mark too small for a subtle
            // fill to read as anything at all — a 6px bar segment.
            . ' --vp-audit-hue: var(--bs-%1$s);',
            $meta['colour']
        );
    }
}
