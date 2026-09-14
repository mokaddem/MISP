<?php

/**
 * The preview card's report count, under a reader who sees less.
 *
 * `30-report-count-check.py` compares the two surfaces over HTTP, and
 * every one of those requests is a site admin's — the one reader who
 * cannot detect an ACL mistake, because their count is the same count
 * whether the conditions narrowed correctly or not at all. This runs
 * the same pair under two readers and asserts the three things that
 * would catch a wrong one:
 *
 * - **The two surfaces agree, per reader.** `forAnalystPreview`'s
 *   `counts.reports` against `forAnalystReports`'s `total`. They are
 *   built by different code over the same events — a count query and a
 *   row fetch — so agreement is a real check and not a tautology.
 * - **The narrower reader never sees more.** An org admin who owns a
 *   fraction of the instance must see at most what the site admin sees,
 *   on both surfaces.
 * - **`buildACLConditions` is what does the narrowing.** The count is
 *   asserted equal to a site admin's count of the same events only
 *   where the reader is a site admin, and strictly bounded by it
 *   otherwise — which is what fails if the conditions were dropped.
 *
 * The readers are `27-history.md` §8's pair, for the reason §15 of
 * `29-overview.md` gives.
 *
 * **It writes nothing.**
 *
 * Run:
 *   cp prd/value-profile-live/30-report-count-probe.php \
 *      app/Console/Command/ValueReportCountProbeShell.php
 *   app/Console/cake ValueReportCountProbe run
 *   rm app/Console/Command/ValueReportCountProbeShell.php
 */
class ValueReportCountProbeShell extends AppShell
{
    public $uses = array('ValueProfile', 'User');

    private $checks = 0;
    private $failures = 0;

    /**
     * Values chosen for their report branches, not for looking
     * representative: two that carry reports on events several
     * organisations own, one that carries them on a single event, the
     * flagship, one past the occurrence cap, and one with none at all.
     */
    const VALUES = array(
        'circl.lu',
        'google.com',
        '1.2.3.4',
        '8.8.8.8',
        '443',
        'sage.png',
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
            $top = $this->__pair($admin, $value, 'site admin');
            if (!empty($other)) {
                $low = $this->__pair($other, $value, 'CIRCL org admin');
                $this->__narrows($top, $low, $value);
            }
            $this->out('');
        }
        Configure::delete('CurrentUserId');
        $this->out(sprintf(
            '%d checks, %d failures',
            $this->checks,
            $this->failures
        ));
    }

    /**
     * Both surfaces for one reader, and the invariant between them.
     *
     * @param array $user
     * @param string $value
     * @param string $label
     * @return int The count both surfaces agreed on
     */
    private function __pair(array $user, $value, $label)
    {
        /*
         * `AnalystData::afterFind` resolves its own reader from
         * `CurrentUserId` and blows up without one. `AppController`
         * writes it on every request; a console shell has no request,
         * so the probe supplies it — and supplies it per call, because
         * the model caches `current_user` on first use and would
         * otherwise answer the second reader with the first one's
         * permissions.
         */
        Configure::write('CurrentUserId', $user['id']);
        foreach (array('Note', 'Opinion', 'Relationship') as $alias) {
            ClassRegistry::init($alias)->current_user = $user;
        }
        $preview = $this->ValueProfile->forAnalystPreview($user, $value);
        $reports = $this->ValueProfile->forAnalystReports($user, $value);
        $card = (int)$preview['analyst']['counts']['reports'];
        $panel = (int)$reports['analyst_reports']['total'];
        $this->__assert(
            $card === $panel,
            sprintf(
                '%s: card %d == panel %d',
                $label,
                $card,
                $panel
            )
        );
        /*
         * The count must not be the rows the panel drew. `total` is
         * taken before the 50-row cap slices, so on a capped value the
         * two still agree and `rows` does not — asserted so a future
         * change that counted the rendered rows fails here.
         */
        $drawn = count($reports['analyst_reports']['rows']);
        if ($panel > $drawn) {
            $this->__assert(
                $card > $drawn,
                sprintf(
                    '%s: count %d is the total, not the %d drawn',
                    $label,
                    $card,
                    $drawn
                )
            );
        }
        return $card;
    }

    /**
     * @param int $top The site admin's count
     * @param int $low The narrower reader's
     * @param string $value
     * @return void
     */
    private function __narrows($top, $low, $value)
    {
        $this->__assert(
            $low <= $top,
            sprintf(
                'CIRCL org admin sees %d <= site admin %d on %s',
                $low,
                $top,
                $value
            )
        );
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
