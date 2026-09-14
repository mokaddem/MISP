<?php
/**
 * The labels on an occurrence — the ones on the attribute, and the ones
 * on the event carrying it, marked apart.
 *
 * `Fields/tag_list` draws one list from one path, which on this page
 * meant the column showed `attribute_tags` and nothing else. That is a
 * minority of what anybody has said: an analyst tags the report far
 * more often than the indicator inside it, and on the verification
 * instance `8.8.8.8` carries **7 distinct attribute tags** against
 * **48 distinct event tags** across its twenty events — the second set
 * holding every `tlp:`, the `type:OSINT` marking, and the ATT&CK
 * clusters.
 *
 * **Attribute tags first, then the event's.** The order is the strength
 * of the claim, not the alphabet: a tag on this row is about this
 * occurrence, and a tag on its event is about the report the occurrence
 * arrived in. When the cap bites it is therefore the weaker set that
 * folds behind the `+N`.
 *
 * **A tag on both is drawn once, as the attribute's.** `tlp:white` on
 * the attribute and on its event is one statement made twice, and two
 * chips would read as two sources agreeing.
 *
 * **Galaxy tags are skipped in both scopes**, which is
 * `Fields/tag_list`'s own rule and is kept rather than reasoned about
 * again here: a cluster is not a label and the page draws it as a
 * cluster, in the context card. That card gained the event scope in the
 * same pass.
 *
 * **`max_visible` is the caller's, and the two callers differ.** The
 * tab's table shows four, which is `Fields/tag_list`'s own number and
 * right for a table a reader came to read. The Overview's preview shows
 * **two**: chips wrap one per line in a 200px column, so four of them
 * made a 37px row 100px tall and took that card from 433px to 756 —
 * undoing, in one column, the whole of what shortened it. Two lines and
 * a `+N` is a summary; eight rows of four-line cells is the table
 * again.
 *
 * Expected:
 *   $field['data_path']        attribute tags (AttributeTag)
 *   $field['event_data_path']  event tags (EventTag), optional — with
 *                              it absent this renders the attribute
 *                              scope alone
 *   $field['max_visible']      chips drawn before the `+N` fold; 4
 */
$maxVisible = isset($field['max_visible'])
    ? (int)$field['max_visible']
    : 4;

/**
 * `AttributeTag`/`EventTag` rows, or bare tags, to one shape.
 *
 * @param mixed $raw
 * @param string $scope
 * @return array
 */
$collect = function ($raw, $scope) {
    $out = array();
    if (empty($raw) || !is_array($raw)) {
        return $out;
    }
    if (isset($raw['name'])) {
        $raw = array($raw);
    }
    foreach ($raw as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if (isset($entry['Tag'])) {
            $tag = $entry['Tag'];
            $local = $entry['local'] ?? ($tag['local'] ?? false);
        } elseif (isset($entry['name'])) {
            $tag = $entry;
            $local = $entry['local'] ?? false;
        } else {
            continue;
        }
        // Galaxy tags live in both tables; the context card draws them.
        if (empty($tag['name']) || !empty($tag['is_galaxy'])) {
            continue;
        }
        $out[] = array(
            'tag' => $tag,
            'local' => !empty($local),
            'scope' => $scope,
        );
    }
    return $out;
};

$chips = $collect(
    Hash::extract($row, $field['data_path']),
    'attribute'
);
if (!empty($field['event_data_path'])) {
    $seen = array();
    foreach ($chips as $chip) {
        $seen[$chip['tag']['name']] = true;
    }
    foreach ($collect(
        Hash::extract($row, $field['event_data_path']),
        'event'
    ) as $chip) {
        if (!isset($seen[$chip['tag']['name']])) {
            $seen[$chip['tag']['name']] = true;
            $chips[] = $chip;
        }
    }
}

if (empty($chips)) {
    return;
}

$hiddenCount = max(0, count($chips) - $maxVisible);
?>
<div class="tag-container d-inline-flex flex-wrap align-items-center">
    <?php foreach ($chips as $index => $chip): ?>
        <?= $this->element('genericElementsBS5/Badges/tag', array(
            'tag' => $chip['tag'],
            'local' => $chip['local'],
            'scope' => $chip['scope'],
            'hiddenClass' => $index >= $maxVisible
                ? 'd-none extra-tag'
                : '',
            'showFavourite' => false,
        )) ?>
    <?php endforeach; ?>

    <?php if ($hiddenCount > 0): ?>
        <span class="badge bg-secondary text-white me-1 mb-1 tag-expand"
              style="cursor:pointer;"
              onclick="toggleTags(this)">+<?= h($hiddenCount) ?></span>
    <?php endif; ?>
</div>
