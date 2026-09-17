<?php
/**
 * Every placing this value has, with the source of each.
 *
 * The compact form picks the newest answer and says so in one line;
 * this is where the disagreement lives. Three databases placing one
 * address in three cities is a finding about the databases, and the
 * only place it can be read is a table with a source column.
 *
 * **The map is a container and not a fetch.** Where the instance has
 * tiles turned on, the points travel on the element as data and the
 * page's script upgrades it; where they are off, the plot and the
 * table are the whole rendering and carry the same facts. Either way
 * this template asks nobody for anything.
 *
 * @var array $data
 */
$points = $data['points'];
$places = $data['places'];
?>
<div class="vp-rf vp-rf-geo">
    <?php if ($data['map'] && !empty($points)): ?>
        <div class="vp-rf-map" data-vp-rmap="<?= h(json_encode(
            array_map(function ($point) {
                return array(
                    'lat' => $point['lat'],
                    'lon' => $point['lon'],
                    'label' => trim(
                        ($point['city'] === null ? '' : $point['city'])
                        . ' '
                        . ($point['country'] === null
                            ? '' : $point['country'])
                    ),
                    'source' => $point['module'],
                );
            }, $points)
        )) ?>" data-vp-rmap-tiles="<?= h((string)$data['tiles']) ?>">
        </div>
    <?php elseif (!empty($points)): ?>
        <svg class="vp-rf-plot" viewBox="0 0 360 180"
             preserveAspectRatio="xMidYMid meet" aria-hidden="true">
            <rect x="0" y="0" width="360" height="180"
                  class="vp-rw-plot-bg"/>
            <line x1="0" y1="90" x2="360" y2="90"
                  class="vp-rw-plot-grid"/>
            <line x1="180" y1="0" x2="180" y2="180"
                  class="vp-rw-plot-grid"/>
            <?php foreach ($points as $point): ?>
                <circle cx="<?= h(round($point['lon'] + 180, 2)) ?>"
                        cy="<?= h(round(90 - $point['lat'], 2)) ?>"
                        r="4" class="vp-rw-plot-dot"/>
            <?php endforeach; ?>
        </svg>
    <?php endif; ?>

    <?php if (!$data['agreed']): ?>
        <p class="vp-rf-note"><?= h(sprintf(
            __('%1$s sources place this value in %2$s different'
                . ' locations.'),
            count($data['sources']),
            count($places)
        )) ?></p>
    <?php endif; ?>

    <table class="table table-sm vp-rf-table">
        <thead>
            <tr>
                <th scope="col"><?= h(__('Place')) ?></th>
                <th scope="col"><?= h(__('Country')) ?></th>
                <th scope="col"><?= h(__('Coordinates')) ?></th>
                <th scope="col"><?= h(__('Source')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            /*
             * The points where there are any and the named places
             * otherwise: a module that resolved a country and no
             * coordinates has still said something, and a table built
             * from the points alone would drop it.
             */
            $rows = empty($points) ? $places : $points;
            ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= h($row['city'] === null
                        ? '—' : $row['city']) ?><?php
                        if (!empty($row['region'])): ?>
                        <span class="vp-rf-dim"><?=
                            h($row['region']) ?></span>
                    <?php endif; ?></td>
                    <td><?= h($row['country'] === null
                        ? '—' : $row['country']) ?><?php
                        if (!empty($row['code'])
                            && $row['code'] !== $row['country']): ?>
                        <span class="vp-rf-dim"><?=
                            h($row['code']) ?></span>
                    <?php endif; ?></td>
                    <td class="font-monospace"><?= isset($row['lat'])
                        ? h(sprintf('%.4f, %.4f', $row['lat'],
                            $row['lon']))
                        : '—' ?></td>
                    <td class="font-monospace vp-rf-dim"><?=
                        h((string)$row['module']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
