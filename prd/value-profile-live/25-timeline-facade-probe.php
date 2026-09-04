<?php

/**
 * Scratch shell: what `ValueProfile::forTimeline` actually assembles,
 * per lane, per value, per reader.
 *
 * T1's verification, and the reason it is a shell rather than a page
 * load: the array has to be right before the template is asked to read
 * it, and the failures worth catching here — a lane whose entries do
 * not sort with everyone else's, a count that disagrees with its own
 * rows, an `at` in the wrong shape — are all invisible in rendered
 * HTML, which draws a wrong number as confidently as a right one.
 *
 * It also counts SQL through the datasource's own log, so a query
 * issued deep inside a fetcher is counted the same as one the facade
 * issues itself — §14.9 row 2 is filled by measurement and a blank
 * there is a row §14 will not let the board claim.
 *
 * **Not part of the application.** Copy it in for the duration:
 *
 *   cp prd/value-profile-live/25-timeline-facade-probe.php \
 *      app/Console/Command/ValueTimelineProbeShell.php
 *   app/Console/cake ValueTimelineProbe run 1
 *   app/Console/cake ValueTimelineProbe run 4
 *   rm app/Console/Command/ValueTimelineProbeShell.php
 */
class ValueTimelineProbeShell extends AppShell
{
    public $uses = array('User', 'ValueProfile');

    const VALUES = array(
        '8.8.8.8',
        '143.14.244.37',
        '443',
        '193.161.193.99',
        '2.2.2.2',
        '45.155.205.233',
    );

    /**
     * cake ValueTimelineProbe run <userId> [value]
     */
    public function run()
    {
        $user = $this->User->getAuthUser((int)$this->args[0]);
        if (empty($user)) {
            $this->out('no such user');
            return;
        }
        $this->out(sprintf(
            'reader: %s / org %s / site_admin %s',
            $user['email'],
            $user['Organisation']['name'],
            empty($user['Role']['perm_site_admin']) ? 'no' : 'YES'
        ));
        $this->out(sprintf(
            'MISP.log_new_audit: %s',
            Configure::read('MISP.log_new_audit') ? 'ON' : 'off'
        ));
        /*
         * What `AppController` sets on every request and a shell does
         * not, and the analyst lane needs it: `AnalystData::afterFind`
         * calls `setUser()`, which reads only this, and then hands the
         * result to `rearrangeSharingGroup(array $user)` — typed
         * `array` and unconditionally called. So *any* find on a `Note`
         * or an `Opinion` with `CurrentUserId` unset is a TypeError,
         * not a degraded row, which makes every CLI and worker path
         * that touches analyst data fatal. Found by this probe, on
         * §14.7's report-do-not-fix list, and out of this phase's
         * scope: the page sets it.
         */
        Configure::write('CurrentUserId', (int)$user['id']);
        $values = isset($this->args[1])
            ? array($this->args[1])
            : self::VALUES;

        foreach ($values as $value) {
            $this->out('');
            $this->out('=== ' . $value . ' ===');
            $db = ConnectionManager::getDataSource('default');
            $db->fullDebug = true;
            $before = count($db->getLog(false, false)['log']);
            $t = microtime(true);
            $profile = $this->ValueProfile->forTimeline($user, $value);
            $ms = (microtime(true) - $t) * 1000;
            $queries = count($db->getLog(false, false)['log']) - $before;
            $this->report($profile, $ms, $queries);
        }
    }

    /**
     * @param array $profile From `forTimeline`
     * @param float $ms
     * @param int $queries
     * @return void
     */
    private function report(array $profile, $ms, $queries)
    {
        $tl = $profile['timeline'];
        $counts = $tl['counts'];
        $this->out(sprintf(
            '  %.0f ms, %d queries',
            $ms,
            $queries
        ));
        $this->out(sprintf(
            '  window %s .. %s   range %s .. %s',
            $tl['window']['from'],
            $tl['window']['to'],
            $tl['range']['from'] === null ? '-' : $tl['range']['from'],
            $tl['range']['to'] === null ? '-' : $tl['range']['to']
        ));
        $this->out(sprintf(
            '  audit_recorded: %s',
            $tl['audit_recorded'] ? 'yes' : 'no (hatched lane)'
        ));
        $this->out(sprintf(
            '  entries: %d shown of %d total%s',
            $counts['shown'],
            $counts['total'],
            $counts['capped'] ? '  (CAPPED)' : ''
        ));

        $mix = array();
        foreach ($counts['by_source'] as $source => $n) {
            $mix[] = $source . ' ' . $n;
        }
        $this->out('  by source: ' . (empty($mix) ? '-' : implode(', ', $mix)));
        $this->out(sprintf('  months with entries: %d',
            count($counts['by_month'])));

        $spans = $tl['spans'];
        $this->out(sprintf(
            '  spans: %d of %d occurrences dated, %d drawn (cap %d)',
            $spans['with'],
            $spans['occurrences'],
            $spans['shown'],
            $spans['cap']
        ));

        $undated = array();
        foreach ($tl['undated'] as $row) {
            $undated[] = sprintf(
                '%s=%d(%d chips%s)',
                $row['key'],
                $row['count'],
                count($row['chips']),
                $row['as_of'] === null ? '' : ', as_of ' . $row['as_of']
            );
        }
        $this->out('  undated: '
            . (empty($undated) ? '-' : implode(', ', $undated)));

        $this->checks($tl);

        foreach (array_slice($tl['entries'], -3) as $entry) {
            $this->out(sprintf(
                '    %s  %-14s %s',
                $entry['at'],
                $entry['source'],
                substr((string)$entry['title'], 0, 78)
            ));
        }
    }

    /**
     * The invariants the array has to hold whatever it holds.
     *
     * Every one of these is something a rendered panel would draw
     * without complaint: an unsorted array still charts, an `at` in the
     * wrong shape still bins (into the wrong bin), and a count that
     * disagrees with its rows is exactly the disagreement §7 of
     * `06-timeline.md` forbids and cannot see.
     *
     * @param array $tl The `timeline` array
     * @return void
     */
    private function checks(array $tl)
    {
        $fail = array();
        $entries = $tl['entries'];

        $last = null;
        $sources = array();
        foreach ($entries as $entry) {
            if ($last !== null && $entry['at'] < $last) {
                $fail[] = 'entries are not ascending by at';
                break;
            }
            $last = $entry['at'];
        }
        foreach ($entries as $entry) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
                (string)$entry['at'])
            ) {
                $fail[] = 'at is not `Y-m-d H:i:s`: '
                    . var_export($entry['at'], true);
                break;
            }
            foreach (array('source', 'precision', 'title', 'note', 'org',
                'ref', 'span_to') as $key) {
                if (!array_key_exists($key, $entry)) {
                    $fail[] = 'entry is missing ' . $key;
                    break 2;
                }
            }
            if (!array_key_exists('attribute', $entry['ref'])
                || !array_key_exists('event', $entry['ref'])
            ) {
                $fail[] = 'ref is missing a side';
                break;
            }
            if (!in_array($entry['precision'],
                array('exact', 'first_last', 'latest'), true)
            ) {
                $fail[] = 'unknown precision ' . $entry['precision'];
                break;
            }
            $sources[$entry['source']] = true;
        }

        /*
         * The one the template would not survive: a source the
         * vocabulary has no colour, glyph or label for indexes
         * `$sourceMeta` on a missing key.
         */
        $known = array('sighting', 'false_positive', 'expiration',
            'publication', 'note', 'opinion', 'edit', 'seen');
        foreach (array_keys($sources) as $source) {
            if (!in_array($source, $known, true)) {
                $fail[] = 'source outside the template vocabulary: '
                    . $source;
            }
        }

        // D2's invariant, in the direction that matters.
        if ($tl['counts']['shown'] > $tl['counts']['total']) {
            $fail[] = sprintf(
                'shown %d exceeds total %d',
                $tl['counts']['shown'],
                $tl['counts']['total']
            );
        }
        if ($tl['counts']['shown'] !== count($entries)) {
            $fail[] = 'shown does not count the entries';
        }
        if (count($entries) > $tl['counts']['cap']) {
            $fail[] = sprintf(
                'shipped %d entries against a cap of %d',
                count($entries),
                $tl['counts']['cap']
            );
        }
        /*
         * **Against the total, not against the entries**, which is §6
         * in one assertion. The spine's bars and the lanes' counts
         * describe every dated thing the viewer may see; the chronology
         * describes what the fragment can carry. An earlier draft of
         * this probe checked these against `count($entries)` and would
         * have passed the shape §6 rejects — a spine binned from the
         * capped rows — and failed the shape it requires.
         */
        $total = $tl['counts']['total'];
        $summed = 0;
        foreach ($tl['counts']['by_source'] as $n) {
            $summed += $n;
        }
        if ($summed !== $total) {
            $fail[] = sprintf(
                'by_source sums to %d against a total of %d',
                $summed,
                $total
            );
        }
        $summed = 0;
        foreach ($tl['counts']['by_month'] as $bySource) {
            foreach ($bySource as $n) {
                $summed += $n;
            }
        }
        if ($summed !== $total) {
            $fail[] = sprintf(
                'by_month sums to %d against a total of %d',
                $summed,
                $total
            );
        }

        // The window has to be inside the range it was derived from.
        if ($tl['range']['to'] !== null
            && $tl['window']['to'] > substr($tl['range']['to'], 0, 10)
        ) {
            $fail[] = 'window ends after the range';
        }

        foreach ($tl['undated'] as $row) {
            foreach (array('key', 'kind', 'count', 'reason', 'chips',
                'as_of') as $key) {
                if (!array_key_exists($key, $row)) {
                    $fail[] = 'undated row is missing ' . $key;
                    break 2;
                }
            }
            if (count($row['chips']) > $row['count']) {
                $fail[] = 'undated row draws more chips than it counted';
            }
        }

        if (empty($fail)) {
            $this->out('  ok: every invariant holds');
            return;
        }
        foreach ($fail as $line) {
            $this->out('  ** FAIL: ' . $line);
        }
    }
}
