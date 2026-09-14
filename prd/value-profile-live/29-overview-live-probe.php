<?php

/**
 * Phase 29's verification that needs the database and a second reader.
 *
 * `29-overview-harness.php` answers *does the fold decide correctly*;
 * this answers *does it decide correctly through CakePHP, against
 * MySQL, under two different readers' permissions* — which is where an
 * ACL applied to the wrong half of a query, an aggregate that ignores
 * the conditions it was handed, or a cap that silently changes a total
 * would show up instead.
 *
 * It exists because §14.9 of the phase document had to admit that every
 * HTTP check ran as a site admin, and *the right method was called* is
 * not *the method is right*. The two readers are the pair
 * `27-history.md` §8 used for the same reason: the site admin, and a
 * CIRCL org admin who owns a fraction of the instance.
 *
 * **It writes nothing.** Every call is a read, and the page it verifies
 * has no write path at all.
 *
 * Run:
 *   cp prd/value-profile-live/29-overview-live-probe.php \
 *      app/Console/Command/ValueOverviewProbeShell.php
 *   app/Console/cake ValueOverviewProbe run
 *   rm app/Console/Command/ValueOverviewProbeShell.php
 */
class ValueOverviewProbeShell extends AppShell
{
    public $uses = array('ValueProfile', 'Value', 'User');

    private $checks = 0;
    private $failures = 0;

    /** The flagship, the heaviest, and one of each awkward shape. */
    const VALUES = array(
        '8.8.8.8',
        '443',
        '0.0.0.0',
        'sage.png',
        '1.162.239.42',
    );

    public function run()
    {
        $this->out('');
        $admin = $this->__reader('admin@admin.test');
        $other = $this->__reader('orgadmin@circl.lu');
        if (empty($admin)) {
            $this->out('<error>no site admin to read as</error>');
            return;
        }

        foreach (self::VALUES as $value) {
            $this->out(sprintf('== %s ==', $value));
            $this->__consistency($admin, $value, 'site admin');
            if (!empty($other)) {
                $this->__consistency($other, $value, 'CIRCL org admin');
                $this->__narrows($admin, $other, $value);
            }
            $this->out('');
        }

        $this->__aggregateColumns($admin);
        $this->__tagCap($admin);

        $this->out('');
        $this->out(sprintf(
            '%d checks, %d failures',
            $this->checks,
            $this->failures
        ));
    }

    /**
     * The invariant the whole page rests on: the frame and the panels
     * are separate requests and separate queries, and they must still
     * be reading one instance.
     */
    private function __consistency(array $user, $value, $who)
    {
        $frame = $this->ValueProfile->forFrame($user, $value);
        $card = $this->ValueProfile->forOccurrences($user, $value);
        $table = $this->ValueProfile->forOccurrenceTable($user, $value);
        $stats = $card['occurrence_stats'];

        $strip = array();
        foreach ($frame['facts'] as $fact) {
            $strip[$fact['label']] = $fact['value'];
        }

        $this->__same(
            number_format($stats['total']),
            $strip['Occurrences'],
            sprintf('%s: strip and card agree on occurrences', $who)
        );
        $this->__same(
            number_format($stats['events']),
            $strip['Events'],
            sprintf('%s: and on events', $who)
        );
        $this->__same(
            number_format($stats['orgs']),
            $strip['Organisations'],
            sprintf('%s: and on organisations', $who)
        );
        $this->__same(
            $stats['total'],
            $table['occurrence_stats']['total'],
            sprintf('%s: the tab reads the same total as the card', $who)
        );
        $this->__same(
            $stats['events'],
            $table['occurrence_stats']['events'],
            sprintf('%s: and the same events', $who)
        );
        $this->__true(
            $stats['shown'] <= $stats['total'],
            sprintf('%s: the card shows no more than it counts', $who)
        );
        $this->__same(
            $frame['counts']['occurrences'],
            $stats['total'],
            sprintf('%s: the tab badge is that total too', $who)
        );

        /*
         * The context card, which is the one with no other surface to
         * check itself against — so what is asserted is internal: every
         * tag it draws is a tag it counted, and no group is empty.
         */
        $context = $this->ValueProfile->forContext($user, $value);
        $drawn = 0;
        foreach ($context['tags'] as $group) {
            $drawn += count($group['tags']);
            $this->__true(
                !empty($group['tags']),
                sprintf('%s: no empty taxonomy group', $who)
            );
            if (isset($group['scale'])) {
                $this->__true(
                    $group['scale']['position'] >= 1
                        && $group['scale']['position']
                            <= $group['scale']['of'],
                    sprintf('%s: a scale position is inside its own scale', $who)
                );
                $this->__same(
                    1,
                    count($group['tags']),
                    sprintf('%s: a scale means exactly one tag', $who)
                );
            }
        }
        $this->__true(
            $drawn <= ValueProfile::CONTEXT_TAG_CAP,
            sprintf('%s: the tag cap holds (%d drawn)', $who, $drawn)
        );

        $life = $this->ValueProfile->forLifecycle($user, $value);
        $this->__true(
            is_bool($life['correlations']['over_correlating']),
            sprintf('%s: the correlation line is a flag', $who)
        );
        $this->__true(
            !array_key_exists('count', $life['correlations']),
            sprintf('%s: and carries no count', $who)
        );
    }

    /**
     * A reader who owns less of the instance sees no more of it.
     *
     * The direction is the whole point. Equality is allowed — on a
     * value whose every event is public the two readers legitimately
     * agree — and the failure this catches is the other one.
     */
    private function __narrows(array $admin, array $other, $value)
    {
        $a = $this->ValueProfile->forOccurrences($admin, $value);
        $b = $this->ValueProfile->forOccurrences($other, $value);
        foreach (array('total', 'events', 'orgs') as $key) {
            $this->__true(
                $b['occurrence_stats'][$key] <= $a['occurrence_stats'][$key],
                sprintf(
                    'the org admin sees no more %s (%d <= %d)',
                    $key,
                    $b['occurrence_stats'][$key],
                    $a['occurrence_stats'][$key]
                )
            );
        }
        $ctxA = $this->ValueProfile->forContext($admin, $value);
        $ctxB = $this->ValueProfile->forContext($other, $value);
        $this->__true(
            $this->__tagCount($ctxB) <= $this->__tagCount($ctxA),
            'and no more labels'
        );
        $this->__true(
            count($ctxB['galaxies']) <= count($ctxA['galaxies']),
            'and no more galaxy clusters'
        );
    }

    private function __tagCount(array $context)
    {
        $n = 0;
        foreach ($context['tags'] as $group) {
            $n += count($group['tags']);
        }
        return $n;
    }

    /**
     * The three columns phase 29 added to `occurrenceSummaryFor`, and
     * the `value2` predicate, against SQL that names the same rows.
     */
    private function __aggregateColumns(array $user)
    {
        $this->out('== the added columns, against their own SQL ==');
        $db = ConnectionManager::getDataSource('default');
        foreach (array('8.8.8.8', '443') as $value) {
            $summary = $this->Value->occurrenceSummaryFor($user, $value);
            $quoted = $db->value($value);
            $rows = $db->fetchAll(
                'SELECT COUNT(DISTINCT a.id) AS occurrences,'
                . ' COUNT(DISTINCT e.id) AS events,'
                . ' SUM(CASE WHEN a.first_seen IS NOT NULL THEN 1 ELSE 0 END)'
                . ' AS dated_from,'
                . ' SUM(CASE WHEN a.last_seen IS NOT NULL THEN 1 ELSE 0 END)'
                . ' AS dated_at,'
                . ' COUNT(DISTINCT CASE WHEN e.published = 1 THEN e.id END)'
                . ' AS published'
                . ' FROM attributes a JOIN events e ON e.id = a.event_id'
                . ' WHERE a.value1 = ' . $quoted
                . ' OR a.value2 = ' . $quoted
            );
            $raw = $rows[0][0];
            /*
             * A site admin sees every row, so the ACL'd aggregate and
             * the bare one must agree exactly. Under any other reader
             * they would not, which is why this check names its reader.
             */
            foreach (array('occurrences', 'events', 'dated_from',
                'dated_at', 'published') as $key) {
                $this->__same(
                    (int)$raw[$key],
                    $summary[$key],
                    sprintf('%s: %s matches the raw aggregate', $value, $key)
                );
            }

            $second = $this->Value->value2CountFor($user, $value);
            $rows = $db->fetchAll(
                'SELECT COUNT(DISTINCT a.id) AS n FROM attributes a'
                . ' JOIN events e ON e.id = a.event_id'
                . ' WHERE a.value2 = ' . $quoted
                . ' AND a.value1 <> ' . $quoted
            );
            $total = 0;
            foreach ($second as $row) {
                $total += $row['count'];
            }
            $this->__same(
                (int)$rows[0][0]['n'],
                $total,
                sprintf('%s: the value2 count matches', $value)
            );
        }
    }

    /**
     * The cap, on the value that made it necessary.
     */
    private function __tagCap(array $user)
    {
        $this->out('== the tag cap ==');
        $cap = ValueProfile::CONTEXT_TAG_CAP;
        $wide = $this->Value->topTagsFor($user, '443', $cap + 1);
        $this->__true(
            count($wide) === $cap + 1,
            sprintf('443 has more labels than the cap (%d read)', count($wide))
        );
        $narrow = $this->Value->topTagsFor($user, '8.8.8.8', $cap + 1);
        $this->__true(
            count($narrow) <= $cap,
            sprintf('8.8.8.8 does not (%d read)', count($narrow))
        );
        $context = $this->ValueProfile->forContext($user, '443');
        $this->__same($cap, $context['tag_cap'], '443 reports the cap');
        $this->__same(
            null,
            $this->ValueProfile->forContext($user, '8.8.8.8')['tag_cap'],
            '8.8.8.8 reports none'
        );
        $drawnScales = 0;
        foreach ($context['tags'] as $group) {
            if (isset($group['scale'])) {
                $drawnScales++;
            }
        }
        $this->__same(
            0,
            $drawnScales,
            'and a capped read draws no scale at all'
        );

        /*
         * Ordered by occurrence count, which is what makes "the
         * most-carried 60" a true description rather than "60 of them".
         */
        $last = null;
        $ordered = true;
        foreach ($wide as $row) {
            if ($last !== null && $row['count'] > $last) {
                $ordered = false;
            }
            $last = $row['count'];
        }
        $this->__true($ordered, 'and they are the most-carried ones');
    }

    private function __reader($email)
    {
        $user = $this->User->find('first', array(
            'conditions' => array('User.email' => $email),
            'recursive' => -1,
            'fields' => array('User.id'),
        ));
        if (empty($user)) {
            $this->out(sprintf('  (no %s on this instance)', $email));
            return array();
        }
        return $this->User->getAuthUser($user['User']['id']);
    }

    private function __same($expected, $actual, $label)
    {
        $this->checks++;
        if ($expected === $actual) {
            $this->out(sprintf('  ok    %s', $label));
            return;
        }
        $this->failures++;
        $this->out(sprintf(
            '  FAIL  %s (expected %s, got %s)',
            $label,
            json_encode($expected),
            json_encode($actual)
        ));
    }

    private function __true($actual, $label)
    {
        $this->__same(true, (bool)$actual, $label);
    }
}
