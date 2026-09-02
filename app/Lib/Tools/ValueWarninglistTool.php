<?php

/**
 * Which of a set of values MISP already knows to be benign.
 *
 * The page's **one** warninglist read. The frame's *Warninglist hit*
 * chip is fixture-built today (`value-profile-page.md` §1.4) and the
 * verdict card's band with it, so when the Overview's live phase runs
 * it converts onto this rather than inventing a second regime — which
 * is the §14.10 frame-versus-panel hazard, one level up.
 * `24b-relationships.md` §7.
 *
 * **It is the event view's check, not a re-implementation.**
 * `Warninglist::attachWarninglistToAttributes` is the batched,
 * Redis-backed path core already uses on every event page: one `mget`
 * over `misp:wlc:<md5(type:value)>` keys for the whole set, the misses
 * computed and written back. The cache is keyed on the pair alone, so
 * an event view that has already checked `1.1.1.1` warms this lookup
 * and this lookup warms the next event view.
 *
 * **`to_ids` is deliberately not consulted.** Core gates that check on
 * `to_ids || MISP.warning_for_all`, because there it is asking *should
 * this attribute have been exported*. Here the question is *is this
 * value known-benign infrastructure*, which is a property of the value
 * and not of one occurrence's export flag — and a co-occurrence row
 * folds many occurrences that need not agree on it. The probes below
 * are therefore throwaway lookup keys carrying `to_ids`, not records:
 * nothing stored is being described, and nothing is written back.
 *
 * Model-injected and takes no `$user`, the second of the two shapes
 * prd/value-profile-live/00-contract.md §14.5 allows. No `$user` is
 * needed and none would mean anything: which lists are enabled is
 * instance state, identical for every viewer, and the caller has
 * already scoped the values it asks about.
 *
 * Measured on the dev instance, 8 enabled lists over `8.8.8.8`'s
 * neighbourhood: 41.8 ms for the 100 carried rows against 64.5 ms for
 * all 10,040 the fold holds. The fixed cost — `getEnabled` plus
 * building the entry sets, ~30 ms, most of it the CIDR lists — is what
 * dominates, and the marginal cost is ~2.3 µs a value. That is the
 * measurement §7 made a precondition for checking the fold rather than
 * the page, and it is why the facet counts below can be exact.
 */
class ValueWarninglistTool
{
    /**
     * Every list each value is on, keyed by value.
     *
     * A value is reported under **any** type it appeared as, which is
     * the rule the type facet already uses — `ValueRelationTool`'s row
     * builder gives a value one badge, its `dominant()` type, but
     * counts it under each. Classifying from the dominant type alone
     * would let a `sha1` seen once as `md5` escape the empty-file list
     * it is on.
     *
     * **One SQL query, and only where something matched.** The check
     * itself is Redis; `assignComments` is the query, issued by the
     * batch whenever any probe hit. Measured in isolation over
     * `8.8.8.8`'s 10,187 rows, twice in one process: `Q=1` both times.
     *
     * @param Warninglist $warninglist
     * @param array $pairs `value` and `type`, duplicates welcome
     * @return array value => list of id, name, category, matched,
     *               comment
     */
    public static function hitsFor($warninglist, array $pairs)
    {
        $probes = array();
        $seen = array();
        foreach ($pairs as $pair) {
            $value = isset($pair['value']) ? (string)$pair['value'] : '';
            $type = isset($pair['type']) ? (string)$pair['type'] : '';
            if ($value === '' || $type === '') {
                continue;
            }
            $key = $type . ':' . $value;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $probes[] = array(
                'value' => $value,
                'type' => $type,
                'to_ids' => 1,
            );
        }
        if (empty($probes)) {
            return array();
        }

        $warninglist->attachWarninglistToAttributes($probes);

        $hits = array();
        foreach ($probes as $probe) {
            if (empty($probe['warnings'])) {
                continue;
            }
            $value = $probe['value'];
            if (!isset($hits[$value])) {
                $hits[$value] = array();
            }
            foreach ($probe['warnings'] as $warning) {
                $id = (int)$warning['warninglist_id'];
                if (isset($hits[$value][$id])) {
                    continue;
                }
                $hits[$value][$id] = array(
                    'id' => $id,
                    'name' => $warning['warninglist_name'],
                    'category' => $warning['warninglist_category'],
                    /*
                     * The list entry that matched, which is not the
                     * value: `10.0.5.23` matches `10.0.0.0/8`, and a
                     * tooltip naming only the list leaves the reader
                     * to guess why.
                     */
                    'matched' => $warning['match'],
                    /*
                     * The entry's own note, where whoever curated the
                     * list left one. This is the single query the batch
                     * costs — `assignComments` fetches it whenever
                     * anything matched, whether or not the caller reads
                     * it — so carrying it through is what makes that
                     * query earn its place rather than a cost paid and
                     * thrown away.
                     */
                    'comment' => isset($warning['comment'])
                        ? $warning['comment']
                        : null,
                );
            }
        }
        foreach ($hits as $value => $lists) {
            usort($lists, function ($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            });
            $hits[$value] = $lists;
        }
        return $hits;
    }

    /**
     * How many lists a miss was checked against.
     *
     * A miss is only informative beside this number — it is why the
     * fact strip has printed *"84 lists checked"* under *"No
     * warninglist hit"* since phase 7.
     *
     * @param Warninglist $warninglist
     * @return int
     */
    public static function enabledCount($warninglist)
    {
        return count($warninglist->getEnabled());
    }
}
