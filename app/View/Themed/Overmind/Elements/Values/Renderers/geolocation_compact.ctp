<?php
/**
 * Where an address is, in 190×150.
 *
 * **The thumbnail is a position plot and not a tile fetch**, and that
 * is the geometry deciding rather than a preference. The strip is one
 * row of five widgets inside 200px; five tile-fetching maps in it
 * would be five map instances, five sets of network requests and five
 * scripts, on a panel whose whole argument is that it paints at the
 * speed of a database read. The real map is the full rendering on the
 * Enrichment pane, where there is one of it and room for it.
 *
 * So this draws a graticule and the points on it. It is a map in the
 * sense that it says *where*, and it says so with no request at all —
 * which is also why it does not change when the instance has tiles
 * turned off. What that setting decides here is only whether the plot
 * is drawn beside the place name or the place name stands alone.
 *
 * Pure: everything drawn comes from `prepare()`.
 *
 * @var array $data
 */
$place = $data['place'];
$points = $data['points'];
$city = $place === null ? null : $place['city'];
$country = $place === null ? null : $place['country'];
$code = $place === null ? null : $place['code'];

/*
 * The headline is the most specific thing known, because a reader who
 * already knows the country learns nothing from it — and a country is
 * the fallback rather than the answer.
 */
$headline = $city !== null ? $city : ($country !== null ? $country : null);
$sub = $city !== null && $country !== null ? $country : null;
?>
<div class="vp-rw-in vp-rw-geo">
    <?php if ($headline !== null): ?>
        <div class="vp-rw-head" title="<?= h(trim(
            ($city === null ? '' : $city . ', ')
            . ($country === null ? '' : $country)
        )) ?>"><?= h($headline) ?></div>
        <?php if ($sub !== null): ?>
            <div class="vp-rw-sub"><?= h($sub) ?><?php
                if ($code !== null && $code !== $sub): ?>
                <span class="vp-rw-code"><?= h($code) ?></span>
            <?php endif; ?></div>
        <?php endif; ?>
    <?php else: ?>
        <div class="vp-rw-head vp-rw-quiet"><?= h(__('located')) ?></div>
    <?php endif; ?>

    <?php if (!empty($points)): ?>
        <?php
        /*
         * Equirectangular, which is the projection a rectangle already
         * is: x is longitude and y is latitude, both linear. Anything
         * truer would need a world outline to be true *against*, and
         * there is none here on purpose.
         */
        ?>
        <svg class="vp-rw-plot" viewBox="0 0 180 90"
             preserveAspectRatio="none" aria-hidden="true">
            <rect x="0" y="0" width="180" height="90"
                  class="vp-rw-plot-bg"/>
            <line x1="0" y1="45" x2="180" y2="45"
                  class="vp-rw-plot-grid"/>
            <line x1="90" y1="0" x2="90" y2="90"
                  class="vp-rw-plot-grid"/>
            <line x1="0" y1="22.5" x2="180" y2="22.5"
                  class="vp-rw-plot-grid vp-rw-plot-faint"/>
            <line x1="0" y1="67.5" x2="180" y2="67.5"
                  class="vp-rw-plot-grid vp-rw-plot-faint"/>
            <?php foreach ($points as $point): ?>
                <circle
                    cx="<?= h(round(($point['lon'] + 180) / 2, 2)) ?>"
                    cy="<?= h(round((90 - $point['lat']) / 2, 2)) ?>"
                    r="3" class="vp-rw-plot-dot"/>
            <?php endforeach; ?>
        </svg>
    <?php endif; ?>

    <?php if (!$data['agreed']): ?>
        <?php
        /*
         * Two services placing one address in two cities is the fact a
         * compact widget must not average away — it is the reason to
         * open the full rendering, and without this line the widget
         * shows one of the two answers as though it were the answer.
         */
        ?>
        <div class="vp-rw-note"><?= h(sprintf(
            __('%s placings'),
            count($data['places'])
        )) ?></div>
    <?php endif; ?>
</div>
