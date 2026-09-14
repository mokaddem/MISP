<?php
/**
 * Variables attendues :
 * - $tag (array)
 * - $local (bool)
 * - $hiddenClass (string)
 * - $showStar (bool) (optionnel)
 * - $scope (string|null) (optionnel) `event` marks a tag carried by the
 *   row's event rather than by the row itself. Absent — which is every
 *   caller but the Value Profile's occurrence tables — renders exactly
 *   as before.
 */

$showFavourite = $showFavourite ?? false;
$scope = $scope ?? null;
$name = h($tag['name']);
$isFavourite = !empty($tag['favourite']);

// Not every association fetches the colour (tag collections, for one).
$colour = !empty($tag['colour']) ? $tag['colour'] : '#0088cc';

$bgColor = 'background-color:' . h($colour);
$textColor = $this->TextColour->getTextColour($colour);
$shadow = 'filter: drop-shadow(-1px 3px 2px rgba(50, 50, 0, 0.5))';
$metallicEffect = "background-image: linear-gradient(145deg, rgba(255,255,255,0.25) 0%, rgba(255,255,255,0.05) 40%, rgba(0,0,0,0.05) 100%)";
$text = "text-align:left; white-space:normal; word-wrap:break-word";

$style = sprintf('%s; color: %s; %s; %s; %s; cursor:pointer;', $bgColor, $textColor, $shadow, $metallicEffect, $text);

// if ($local) {
//     $style .= sprintf(' border:2px dashed %s', $textColor);
// }
?>

<div class="d-inline-flex align-items-center">
    <?php if ($showFavourite && !empty($tag['id'])): ?>
        <i
            class="<?= $isFavourite ? 'fas fa-star' : 'far fa-star' ?> text-warning me-1 tag-star"
            data-id="<?= (int)$tag['id'] ?>"
            style="cursor:pointer;"
        ></i>
    <?php endif; ?>

    <?php
    /*
     * A title on every chip, not only the scoped ones: a caller may cap
     * the chip's width — the Value Profile's occurrence preview does —
     * and a truncated tag name has to stay recoverable without opening
     * anything.
     */
    ?>
    <span class="badge me-1 mb-1 <?= h($hiddenClass) ?>"
          style="<?= $style ?>"
          title="<?= h($scope === 'event'
              ? sprintf(
                  __('%s — carried by the event, not by this row'),
                  $tag['name']
              )
              : $tag['name']) ?>">
        <?php if ($scope === 'event'): ?>
            <?php
            /*
             * The glyph MISP draws an event with, inside the chip, in
             * the chip's own text colour — `.misp-icon` masks
             * `currentColor`, so it takes whatever `getTextColour`
             * chose for this tag's background and cannot fight it.
             *
             * Only the event scope is marked. An attribute tag is what
             * this column has always drawn, so leaving it unmarked
             * keeps the addition legible and costs no ink on the rows
             * that had tags before — the same asymmetry `local` uses
             * one line down.
             */
            ?>
            <span class="misp-icon misp-icon-event misp-simple me-1"
                  aria-hidden="true"></span>
        <?php endif; ?>
        <?php if ($local): ?>
            <i class="fas fa-user me-1"></i>
        <?php endif; ?>

        <?= $name ?>
    </span>
</div>