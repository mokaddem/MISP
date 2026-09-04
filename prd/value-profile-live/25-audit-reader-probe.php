<?php

/**
 * Scratch shell: does `25-timeline.md` §5's audit reader return what
 * §5.2's table says it should, and does the ACL model actually bite?
 *
 * T2's verification, and it checks three things the probe SQL cannot.
 * The SQL measures the *decided* scoping by hand-writing the same three
 * `IN` sets; this runs the code, so it also catches the reader agreeing
 * with the SQL for the wrong reason. It reads as a chosen user, so
 * §8.2's stated trap — *read as a site admin, all three ACL models look
 * identical* — is something this can fail rather than something a
 * verifier has to remember. And it compares the grouped aggregate
 * against the capped read, which is D2's invariant and the one thing
 * §6 cannot be checked without.
 *
 * **Not part of the application.** Copy it in for the duration:
 *
 *   cp prd/value-profile-live/25-audit-reader-probe.php \
 *      app/Console/Command/ValueAuditProbeShell.php
 *   app/Console/cake ValueAuditProbe run 1
 *   app/Console/cake ValueAuditProbe run 4
 *   rm app/Console/Command/ValueAuditProbeShell.php
 *
 * The reader is private, and deliberately: it is a facade internal, not
 * an API. Reflection is a probe's business and not the application's.
 */
class ValueAuditProbeShell extends AppShell
{
    public $uses = array('User', 'Value', 'ValueProfile');

    /**
     * §14's six values, and the two the 2026-09-04 re-probe turned up
     * for the occurrence-level analyst branch (§8 predicted none on a
     * candidate value and was right; these two are not candidates).
     */
    const VALUES = array(
        '8.8.8.8',
        '143.14.244.37',
        '443',
        '193.161.193.99',
        '2.2.2.2',
        '45.155.205.233',
    );

    /**
     * cake ValueAuditProbe run <userId> [value]
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
        $values = isset($this->args[1])
            ? array($this->args[1])
            : self::VALUES;

        $rowsFor = new ReflectionMethod('ValueProfile', 'auditRowsFor');
        $rowsFor->setAccessible(true);
        $countsFor = new ReflectionMethod('ValueProfile', 'auditCountsFor');
        $countsFor->setAccessible(true);

        foreach ($values as $value) {
            $this->out('');
            $this->out('=== ' . $value . ' ===');
            $t = microtime(true);
            $scope = $this->scopeFor($user, $value);
            $scoped = (microtime(true) - $t) * 1000;
            $this->out(sprintf(
                '  scope: %d attributes, %d objects, %d events  (%.0f ms)',
                count($scope['attributes']),
                count($scope['objects']),
                count($scope['events']),
                $scoped
            ));

            $t = microtime(true);
            $counts = $countsFor->invoke($this->ValueProfile, $scope);
            $aggMs = (microtime(true) - $t) * 1000;
            $this->out(sprintf(
                '  aggregate: %d rows over %d months, %s .. %s  (%.0f ms)',
                $counts['total'],
                count($counts['by_month']),
                $counts['first'] === null ? '-' : $counts['first'],
                $counts['last'] === null ? '-' : $counts['last'],
                $aggMs
            ));
            $actions = array();
            foreach ($counts['by_action'] as $action => $n) {
                $actions[] = $action . ' ' . $n;
            }
            if (!empty($actions)) {
                $this->out('  by action: ' . implode(', ', $actions));
            }

            $t = microtime(true);
            $rows = $rowsFor->invoke(
                $this->ValueProfile,
                $scope,
                array('limit' => ValueProfile::TIMELINE_ROW_CAP)
            );
            $readMs = (microtime(true) - $t) * 1000;
            $this->out(sprintf(
                '  capped read: %d rows  (%.0f ms)',
                count($rows),
                $readMs
            ));

            /*
             * D2's invariant, stated as the panel will state it. The
             * shown number may be lower than the total and must never
             * be higher, and the two must agree exactly whenever the
             * cap did not bite — which is the half a spot-check misses.
             */
            $shown = count($rows);
            $capped = $shown >= ValueProfile::TIMELINE_ROW_CAP;
            if ($shown > $counts['total']) {
                $this->out('  ** FAIL: read more rows than the aggregate counted');
            } elseif (!$capped && $shown !== $counts['total']) {
                $this->out(sprintf(
                    '  ** FAIL: uncapped read %d against aggregate %d',
                    $shown,
                    $counts['total']
                ));
            } else {
                $this->out(sprintf(
                    '  ok: showing %d of %d entries%s',
                    $shown,
                    $counts['total'],
                    $capped ? ' (cap bit)' : ''
                ));
            }

            /*
             * Newest first, and every row inside one of the three
             * scopes. A row from a model the scope never asked for is
             * the failure that would make the subset claim false, so it
             * is checked rather than assumed.
             */
            $models = array();
            $last = null;
            $stray = 0;
            $byModel = array(
                'Attribute' => array_flip($scope['attributes']),
                'Object' => array_flip($scope['objects']),
                'Event' => array_flip($scope['events']),
            );
            foreach ($rows as $row) {
                $model = $row['model'];
                $models[$model] = (isset($models[$model])
                    ? $models[$model]
                    : 0) + 1;
                if ($last !== null && $row['created'] > $last) {
                    $this->out('  ** FAIL: rows are not newest-first');
                    $last = null;
                    break;
                }
                $last = $row['created'];
                if (!isset($byModel[$model])) {
                    $stray++;
                    continue;
                }
                $id = $model === 'Event'
                    ? $row['event_id']
                    : $row['model_id'];
                if (!isset($byModel[$model][$id])) {
                    $stray++;
                }
            }
            $mix = array();
            foreach ($models as $model => $n) {
                $mix[] = $model . ' ' . $n;
            }
            $this->out('  models: ' . (empty($mix) ? '-' : implode(', ', $mix)));
            $this->out($stray === 0
                ? '  ok: every row is inside the scope'
                : sprintf('  ** FAIL: %d rows outside the scope', $stray));

            if (!empty($rows)) {
                $row = $rows[0];
                $this->out(sprintf(
                    '  newest: %s %s %s#%s by %s (%s)',
                    $row['created'],
                    $row['action'],
                    $row['model'],
                    $row['model_id'],
                    $row['actor'] === null ? '-' : $row['actor'],
                    $row['org'] === null ? '-' : $row['org']
                ));
                if ($row['subject'] !== null) {
                    $this->out('  subject: ' . $row['subject']);
                }
            }
        }
    }

    /**
     * The three id sets, from the accessors that have already applied
     * `buildConditions($user)` — which is what makes the reader's
     * safety argument a subset claim rather than a hope.
     *
     * @param array $user
     * @param string $value
     * @return array
     */
    private function scopeFor(array $user, $value)
    {
        $occurrences = $this->Value->occurrenceIdsFor($user, $value);
        $objects = $this->Value->occurrenceObjectIdsFor($user, $value);
        $events = array();
        foreach ($occurrences as $occurrence) {
            $events[$occurrence['event_id']] = true;
        }
        return array(
            'attributes' => array_map('intval', array_keys($occurrences)),
            'objects' => array_map('intval', array_keys($objects)),
            'events' => array_map('intval', array_keys($events)),
        );
    }
}
