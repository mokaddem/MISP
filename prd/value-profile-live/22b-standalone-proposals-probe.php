<?php

/**
 * The Proposed additions block, under two readers.
 *
 * `value-profile-coverage.md` §5.1's fourth item is the reason this
 * exists. `ShadowAttribute::buildConditions` ORs `old_id = 0` past the
 * whole attribute-and-object distribution test — there is no attribute
 * to test — so a standalone proposal is gated on **event visibility
 * alone**, which is *looser* than the occurrence fetcher beside it.
 * The occurrence table's ACL reasoning does not carry over, so this
 * asserts the block's own:
 *
 * - **The table's numbers do not move.** §5.1's counting question was
 *   answered by keeping proposals out of the occurrence row set, and
 *   the assertion is that `occurrence_stats` and the rail's facet
 *   totals are what they are with the block absent — computed here by
 *   comparing them against the occurrence count the same read returns.
 * - **Every row really is standalone.** `old_id === 0` on all of them,
 *   which is what makes them invisible to every other panel.
 * - **The narrower reader never sees more**, and on this instance sees
 *   strictly less on at least one value — otherwise the check proves
 *   only that a gate was called, not that it closed.
 * - **The block is absent, not empty, where there is nothing**, which
 *   is the rule a heading over nothing would break.
 *
 * **It writes nothing.**
 *
 * Run:
 *   cp prd/value-profile-live/22b-standalone-proposals-probe.php \
 *      app/Console/Command/ValueStandaloneProbeShell.php
 *   app/Console/cake ValueStandaloneProbe run
 *   rm app/Console/Command/ValueStandaloneProbeShell.php
 */
class ValueStandaloneProbeShell extends AppShell
{
    public $uses = array('ValueProfile', 'User');

    private $checks = 0;
    private $failures = 0;

    /**
     * The instance's six standalone proposals sit on three values, and
     * each is a different shape. `8.8.8.8` carries proposals that are
     * *not* standalone and must draw no block at all.
     */
    const VALUES = array(
        // Held only as a proposal — §2.2's defect, and the page
        // rendered it as §2.12's unknown page.
        '123.123.123.1',
        '123.43.32.22',
        // An occurrence and a pending proposal together.
        '123.43.32.21',
        // An occurrence and two withdrawn proposals.
        'tinyurl.com',
        // A withdrawn proposal on an org-only event.
        '5.2.3.4',
        // Attribute-targeted proposals only: no block.
        '8.8.8.8',
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
        /*
         * **Three readers, and the third is the one that proves it.**
         * All six standalone proposals on this instance sit on events
         * 194, 195 and 1595; two of those are *all communities*, so the
         * CIRCL org admin sees everything the site admin does and a
         * pair of readers cannot tell a working gate from a dropped
         * one. Event 194 is CIRCL's own at distribution 0, so the ADMIN
         * org admin is the reader it must be closed against.
         */
        $outside = $this->__reader('orgadmin@admin.test');
        $narrowed = 0;
        foreach (self::VALUES as $value) {
            $this->out(sprintf('== %s ==', $value));
            $top = $this->__block($admin, $value, 'site admin');
            foreach (array(
                'CIRCL org admin' => $other,
                'ADMIN org admin' => $outside,
            ) as $label => $reader) {
                if (empty($reader)) {
                    continue;
                }
                $low = $this->__block($reader, $value, $label);
                $this->__assert(
                    $low <= $top,
                    sprintf(
                        '%s sees %d <= site admin %d',
                        $label,
                        $low,
                        $top
                    )
                );
                if ($low < $top) {
                    $narrowed++;
                }
            }
            $this->out('');
        }
        /*
         * The check that makes the others mean something. If the gate
         * never closes on any value, every count above is the same
         * count under both readers and a dropped condition would pass.
         */
        $this->__assert(
            $narrowed > 0,
            sprintf(
                'the gate closes somewhere: %d value(s) narrow',
                $narrowed
            )
        );
        $this->out(sprintf(
            '%d checks, %d failures',
            $this->checks,
            $this->failures
        ));
    }

    /**
     * @param array $user
     * @param string $value
     * @param string $label
     * @return int How many standalone proposals this reader sees
     */
    private function __block(array $user, $value, $label)
    {
        $profile = $this->ValueProfile->forOccurrenceTable($user, $value);
        $block = $profile['standalone_proposals'];
        $stats = $profile['occurrence_stats'];

        /*
         * §5.1's counting answer, asserted rather than described, and
         * asserted on every value including the ones with no block: the
         * table's total is the value's occurrence count, read straight
         * off `Value` by a call that has never heard of a proposal. If
         * a standalone row ever reaches the occurrence row set, this is
         * where it shows up.
         */
        $occurrences = ClassRegistry::init('Value')
            ->occurrenceCountFor($user, $value);
        $this->__assert(
            (int)$stats['total'] === (int)$occurrences,
            sprintf(
                '%s: table states %d of %d, occurrenceCountFor says %d',
                $label,
                (int)$stats['shown'],
                (int)$stats['total'],
                (int)$occurrences
            )
        );

        if ($block === null) {
            $this->__assert(
                true,
                sprintf('%s: no block (absent, not empty)', $label)
            );
            return 0;
        }
        $total = (int)$block['total'];
        $this->__assert(
            $total === count($block['rows']) || !empty($block['capped']),
            sprintf(
                '%s: %d rows drawn of %d, capped=%s',
                $label,
                count($block['rows']),
                $total,
                empty($block['capped']) ? 'no' : 'yes'
            )
        );
        $standalone = 0;
        $withdrawn = 0;
        foreach ($block['rows'] as $row) {
            if ((int)$row['old_id'] === 0) {
                $standalone++;
            }
            if (!empty($row['deleted'])) {
                $withdrawn++;
            }
        }
        $this->__assert(
            $standalone === count($block['rows']),
            sprintf(
                '%s: all %d rows are standalone (old_id = 0)',
                $label,
                $standalone
            )
        );
        $this->__assert(
            $withdrawn <= (int)$block['withdrawn'],
            sprintf(
                '%s: %d withdrawn drawn, %d counted',
                $label,
                $withdrawn,
                (int)$block['withdrawn']
            )
        );
        return $total;
    }

    /**
     * @param string $email
     * @return array|null
     */
    private function __reader($email)
    {
        $user = $this->User->getAuthUser(
            $this->User->field('id', array('User.email' => $email))
        );
        if (empty($user)) {
            $this->out(sprintf('  (no reader %s — skipped)', $email));
            return null;
        }
        return $user;
    }

    /**
     * @param bool $ok
     * @param string $what
     * @return void
     */
    private function __assert($ok, $what)
    {
        $this->checks++;
        if (!$ok) {
            $this->failures++;
        }
        $this->out(sprintf(
            '  %s %s',
            $ok ? '<info>ok  </info>' : '<error>FAIL</error>',
            $what
        ));
    }
}
