<?php

/**
 * Whether MISP already knows this value is not worth acting on.
 *
 * The heaviest single row the fixture carries (+38 on the benign
 * value), and the one whose meaning depends entirely on **which kind of
 * list matched** — which is why the category resolution is a profile
 * section of its own (`07-reference.md` §3).
 *
 * Two readings, and conflating them is the mistake this signal exists
 * to avoid:
 *
 * - **`false_positive`** — the list means *this is not an indicator*.
 *   A resolver address, an RFC1918 range, a top-1000 domain. Heavy
 *   negative points; the list is a judgement MISP ships.
 * - **`known`** — the list means *this is shared infrastructure*. A CDN
 *   front, a hosting range. It is not a false positive and it does not
 *   argue the value is harmless; it argues that the value cannot be
 *   attributed to one tenant. The default weight is therefore **zero**
 *   — the row is on the page saying so, counted for neither side, and
 *   the contradiction with wide reporting is named by an escalation
 *   rather than netted off in arithmetic (`04-dispositions.md` §4).
 *
 * **A category nothing sets, today.** `07-reference.md` §3.1 verified
 * that 0 of 89 upstream lists carry a `category` *and* that
 * `Warninglist::__updateList()` drops the field on import, so the
 * resolution comes from the profile's `reference.warninglist_category`
 * override map, from a hardcoded name map phase 6 ships, and only then
 * from the table's own column. Where nothing resolves, an unresolved
 * hit reads as `false_positive` — which is what MISP's own warning
 * banner has always meant by a hit, and the honest default until the
 * upstream PR lands.
 *
 * Absence fires as `no_hit`: *"no warninglist hit, 84 lists checked"*
 * is the row the fixture's malicious value carries, and the lists
 * checked is half of it — a hit against nothing is only meaningful
 * beside how much was looked at.
 */
class LifecycleWarninglist extends ValueSignalBase
{
    public $id = 'lifecycle.warninglist';
    public $group = 'Lifecycle';
    public $evidence_class = self::EVIDENCE_AGGREGATE;
    public $reads = array('warninglist');
    public $absence_key = 'no_hit';
    /*
     * The other of D11 §2.1's two ledger-borne lean sources. A hit
     * reads the value — *this is not an indicator*, or *this is shared
     * infrastructure* — so a hit anchors.
     *
     * **`no_hit` does not, and that is the whole reason the axis is
     * per-row.** *Nothing matched, 8 lists checked* is the control
     * case: it says the value is on no list MISP ships, which is not
     * the same statement as *the value is a threat*. Anchored, it
     * became one — on a benign lean its `+6` inverted to `−6` and, on
     * its own, tipped rule 7 into calling an uncontested value
     * contested. It is a quality row: what it measures is that the
     * record survived the check.
     */
    public $axis = self::AXIS_LEAN;
    public $tab = 'general';

    public function __construct()
    {
        $this->description = __(
            'Whether the value hits a warninglist, and what kind of'
            . ' list it is.'
        );
        $this->points_schema = array(
            'no_hit' => array(
                'type' => 'int',
                'default' => 6,
                'label' => __('Points when nothing matched'),
            ),
            'false_positive_hit' => array(
                'type' => 'int',
                'default' => -38,
                'label' => __('Points for a false-positive list'),
            ),
            'known_hit' => array(
                'type' => 'int',
                'default' => 0,
                'label' => __('Points for a known-infrastructure list'),
            ),
        );
    }

    public function evaluate(array $context, array $config)
    {
        $warninglist = isset($context['warninglist'])
            ? $context['warninglist']
            : array();
        $hits = isset($warninglist['hits'])
            ? $warninglist['hits']
            : array();
        $checked = (int)($warninglist['lists_checked'] ?? 0);

        if (empty($hits)) {
            if (!$this->absenceFires($config, $context, 'warninglist')) {
                return null;
            }
            return $this->row(
                $this->points($config, 'no_hit'),
                __('No warninglist hit'),
                sprintf(
                    $checked === 1
                        ? __('%d list checked')
                        : __('%d lists checked'),
                    $checked
                ),
                $context,
                null,
                self::AXIS_QUALITY
            );
        }

        $names = array();
        foreach ($hits as $hit) {
            if (!empty($hit['name'])) {
                $names[] = $hit['name'];
            }
        }
        $category = isset($warninglist['category'])
            ? $warninglist['category']
            : null;
        $known = ($category === 'known');
        $points = $known
            ? $this->points($config, 'known_hit')
            : $this->points($config, 'false_positive_hit');
        $lead = empty($names) ? __('a warninglist') : $names[0];
        $signal = $known
            ? sprintf(
                __('Known infrastructure: %s'),
                $lead
            )
            : sprintf(
                __('Hits a known-benign warninglist: %s'),
                $lead
            );
        $evidence = $known
            ? __('Shared infrastructure — not attributable to one'
                . ' tenant, and not a false positive')
            : sprintf(
                $checked === 1
                    ? __('%d list checked')
                    : __('%d lists checked'),
                $checked
            );
        if (count($names) > 1) {
            $evidence = implode(', ', array_slice($names, 0, 4))
                . '; ' . $evidence;
        }

        return $this->row($points, $signal, $evidence, $context);
    }
}
