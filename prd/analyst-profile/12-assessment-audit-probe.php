<?php

App::uses('ValueVerdictTool', 'Tools');
App::uses('ValueLeanTool', 'Tools');
App::uses('ValueContestedTool', 'Tools');

/**
 * D11's own question, counted: how the Assessment tab's lean is reached
 * on real values, and what the contested layout's two cases are made
 * of.
 *
 * Written for the 2026-09-13 read-back of the live tab, because the
 * defect it found is invisible one value at a time. `8.8.8.8` is
 * genuinely contested — a false-positive listing against eight
 * asserting organisations — so every probe that used it as the
 * contested case passed. The question this answers is how the *other*
 * contested values got there.
 *
 * What it reports:
 *
 *   1. Every `decided_by` exit against the lean finally rendered. Rule
 *      7 (`ValueVerdictTool`, the anchored sum below zero) rewrites the
 *      lean after `decided_by` is set and does not rewrite the exit, so
 *      these two columns disagreeing is the measurement.
 *   2. The flips themselves — a value whose lean band reads *N of M
 *      organisations assert this is a threat* under a **Contested**
 *      badge.
 *   3. Contested values carrying a quality-only signal — corroboration
 *      breadth, published ratio, feed presence, recency, continuity —
 *      inside a case headed *Reads as a threat* or *Reads as benign*.
 *      Those signals grade the record; they do not read it.
 *
 * On the verification instance, 120 values with 2 to 30 events each:
 * 60 contested, 55 of them by rule 7, and all 60 with quality-only
 * signals seated in a case that claims they are a reading.
 *
 * Run:
 *   cp prd/analyst-profile/12-assessment-audit-probe.php \
 *      app/Console/Command/AssessAuditShell.php
 *   app/Console/cake AssessAudit run 120
 *   rm app/Console/Command/AssessAuditShell.php
 *
 * It writes nothing.
 */
class AssessAuditShell extends AppShell
{
    public $uses = array('AnalystProfile', 'ValueProfile', 'Value',
        'User');

    /** Signals whose negative pole grades the record, not the lean. */
    const QUALITY_ONLY = array(
        'record.temporal_precision',
        'reporting.published_ratio',
        'reporting.independent_orgs',
        'lifecycle.feeds',
        'lifecycle.recency',
        'lifecycle.continuity',
        'sightings.volume_recency',
    );

    public function run()
    {
        $limit = isset($this->args[0]) ? (int)$this->args[0] : 200;
        $user = $this->__user();
        $profile = $this->AnalystProfile->resolveFor($user);
        $tool = new ValueVerdictTool($this->ValueProfile);

        $values = $this->__sample($limit);
        $this->out(sprintf('sampled %d values', count($values)));

        $byExit = array();
        $flips = array();
        $caseMix = array('threat' => 0, 'benign' => 0);
        $mislabelled = array();
        $contested = 0;

        foreach ($values as $value) {
            try {
                $v = $tool->verdictFor($user, $value,
                    array('profile' => $profile));
            } catch (Exception $e) {
                continue;
            }
            $exit = isset($v['decided_by']) ? $v['decided_by'] : '?';
            $lean = $v['lean'];
            $key = $exit . ' -> ' . $lean;
            $byExit[$key] = isset($byExit[$key]) ? $byExit[$key] + 1 : 1;

            $preContested = in_array($exit,
                array('escalation', 'no_supermajority'), true);
            if ($lean === 'contested' && !$preContested) {
                $flips[] = sprintf('%s (%s, quality %d)',
                    $value, $exit, $v['quality']);
            }
            if ($lean !== 'contested') {
                continue;
            }
            $contested++;
            $cases = ValueContestedTool::casesFor($v);
            if (empty($cases)) {
                continue;
            }
            foreach ($cases as $case) {
                foreach ($case['rows'] as $row) {
                    $caseMix[$case['side']]++;
                }
            }
            // Rows on the threat side that are quality-only signals.
            $bad = array();
            foreach ($v['ledger'] as $group) {
                foreach ($group['signals'] as $row) {
                    $id = isset($row['id']) ? $row['id'] : '';
                    if ((int)$row['contribution'] > 0
                        && in_array($id, self::QUALITY_ONLY, true)
                    ) {
                        $bad[] = $id;
                    }
                }
            }
            if (!empty($bad)) {
                $mislabelled[] = $value . ': ' . implode(', ', $bad);
            }
        }

        $this->out('');
        $this->out('--- lean exits (decided_by -> final lean)');
        arsort($byExit);
        foreach ($byExit as $k => $n) {
            $this->out(sprintf('  %-46s %d', $k, $n));
        }

        $this->out('');
        $this->out(sprintf(
            '--- rule 7 flips (a non-contested exit rendered contested): %d',
            count($flips)
        ));
        foreach (array_slice($flips, 0, 15) as $f) {
            $this->out('  ' . $f);
        }

        $this->out('');
        $this->out(sprintf('--- contested values: %d', $contested));
        $this->out(sprintf(
            '--- quality-only signals sitting in a "Reads as a threat"'
                . ' case: %d values',
            count($mislabelled)
        ));
        foreach (array_slice($mislabelled, 0, 12) as $m) {
            $this->out('  ' . $m);
        }
    }

    private function __sample($limit)
    {
        $db = ConnectionManager::getDataSource('default');
        $rows = $db->fetchAll(
            'SELECT a.value1 FROM attributes a'
            . ' WHERE a.deleted = 0 AND a.value1 <> \'\''
            . ' AND a.type IN (\'ip-src\', \'ip-dst\', \'domain\','
            . ' \'hostname\', \'url\', \'md5\', \'sha256\')'
            . ' GROUP BY a.value1'
            . ' HAVING COUNT(DISTINCT a.event_id) BETWEEN 2 AND 30'
            . ' ORDER BY COUNT(DISTINCT a.event_id) DESC'
            . ' LIMIT ' . (int)$limit
        );
        $out = array();
        foreach ($rows as $row) {
            $out[] = (string)$row['a']['value1'];
        }
        return $out;
    }

    private function __user()
    {
        $row = $this->User->find('first', array(
            'recursive' => -1,
            'contain' => array('Role', 'Organisation'),
            'conditions' => array('User.disabled' => 0),
            'order' => array('Role.perm_site_admin DESC', 'User.id ASC'),
        ));
        return $this->User->getAuthUser($row['User']['id']);
    }
}
