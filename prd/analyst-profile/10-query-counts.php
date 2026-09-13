<?php
App::uses('ValueUrlTool', 'Tools');

/**
 * What the four Assessment endpoints cost, in queries.
 *
 * `../value-profile-live/00-contract.md` §14.12's board carries one `Q`
 * per endpoint, and phase 9 wired four rows and left them blank — the
 * *converted, unmeasured* state that board did not have before. This
 * fills them.
 *
 * It counts the way every other phase counted: the datasource's own
 * log, cleared before the call and read after it, with the model built
 * fresh so nothing is answered from a request-lifetime memo that a real
 * first request would not have. `AnalystProfile::resolveFor()` memoises
 * per request and the assessment resolves it once, which is a saving a
 * real page load gets too — so the model is rebuilt between endpoints
 * rather than between calls to the same one.
 *
 * **Not part of the application.** Copy it in for the duration:
 *
 *   docker cp prd/analyst-profile/10-query-counts.php \
 *     misp-core:/var/www/MISP/app/Console/Command/ValueQueryCountShell.php
 *   app/Console/cake ValueQueryCount 8.8.8.8
 */
class ValueQueryCountShell extends AppShell
{
    public $uses = array('User');

    /** @var array The viewer every measurement runs as. */
    private $authUser;

    public function main()
    {
        $values = empty($this->args) ? array('8.8.8.8') : $this->args;
        /*
         * Fetched once and held. Re-reading it per measurement inside a
         * flushed registry hands `AnalystData` a half-built user and
         * the comparison run dies on `rearrangeSharingGroup` — which is
         * a property of the harness, not of the endpoint.
         */
        $this->authUser = $this->User->getAuthUser(1, true);

        foreach ($values as $value) {
            $this->out('');
            $this->out('=== ' . $value . ' ===');
            $this->measure('viewVerdictCard (no opinions)', $value,
                function ($model, $u, $v) {
                    return $model->forVerdict($u, $v);
                }, true);
            /*
             * `AnalystData::setUser()` reads this and nothing else, and
             * in a console there is no session to fill it — without it
             * the analyst union dies in `rearrangeSharingGroup` on a
             * null user, which is a property of the harness rather than
             * of the endpoint.
             *
             * Written **here** rather than in the first line of main(),
             * because with it set from the start the card's own count
             * comes out one higher: something on that path reads the
             * current user once it exists. A harness that changes the
             * number it is measuring is the measurement's problem, so
             * it is scoped to the run that needs it.
             */
            Configure::write('CurrentUserId', (int)$this->authUser['id']);
            $this->measure('viewVerdict / Aside (+ the union)', $value,
                function ($model, $u, $v) {
                    return $model->forVerdict($u, $v,
                        array('with_opinions' => true));
                }, true);
            Configure::delete('CurrentUserId');
            /*
             * `forAnalystStanding` is deliberately not measured here.
             * It reaches `AnalystData::rearrangeSharingGroup`, which
             * wants the user shape a controller builds rather than the
             * one `getAuthUser` returns, and the board already carries
             * its number — 7 to 28 — from phase 26, which measured it
             * over HTTP where that shape exists.
             */
        }
    }

    /**
     * @param string $label
     * @param string $value
     * @param callable $run
     * @param bool $breakdown Print one line per table touched
     * @return void
     */
    private function measure($label, $value, $run, $breakdown = false)
    {
        /*
         * Only the model under measurement is dropped. A full flush
         * would also throw away `User`, and the request-lifetime memos
         * a real first request keeps — `AnalystProfile::resolveFor()`
         * among them — belong in the number rather than outside it.
         */
        ClassRegistry::removeObject('ValueProfile');
        /*
         * And the profile store with it. `resolveFor()` memoises per
         * request, so leaving it in place would hand every value after
         * the first a resolution a real request pays for — the first
         * value measured 27 and the second 8 until this line existed,
         * and one of the nineteen was the profile lookup.
         */
        ClassRegistry::removeObject('AnalystProfile');
        $user = $this->authUser;
        $model = ClassRegistry::init('ValueProfile');
        $source = $model->getDataSource();
        $source->fullDebug = true;
        /*
         * **The count comes from the counter, not from the log.** The
         * log is a ring buffer of the last 200 statements, so a delta
         * taken across it stops growing once the buffer is full and
         * then starts shrinking — which is how a run of six values
         * reported `3 queries` and then `0` for endpoints that had just
         * taken twenty. `getLog()['count']` is cumulative and is the
         * only figure here that can be trusted after the first couple
         * of hundred.
         *
         * The statements are still read off the log, for the breakdown
         * only, and only the last `$count` of them — which is exact
         * while a single measurement stays under the buffer's size, and
         * every one of these does.
         */
        $before = (int)$source->getLog(false, false)['count'];
        $started = microtime(true);
        $run($model, $user, $value);
        $ms = (microtime(true) - $started) * 1000;
        $log = $source->getLog(false, false);
        $count = (int)$log['count'] - $before;
        $after = array_slice($log['log'], -max($count, 0));
        $before = 0;
        $this->out(sprintf(
            '%-38s %3d queries   %6.0f ms',
            $label,
            $count,
            $ms
        ));
        if (!$breakdown) {
            return;
        }
        /*
         * What the count grows with is the board's `Scales` column, and
         * it is not readable off a total. Grouped by the table the
         * statement reads, which is as fine as that column ever gets.
         */
        $tables = array();
        foreach (array_slice($after, $before) as $entry) {
            $sql = $entry['query'];
            if (preg_match(
                '/\bFROM\s+(?:`[a-z_]+`\.)?`?([a-z_]+)`?/i',
                $sql,
                $m
            )) {
                $table = $m[1];
            } else {
                $table = trim(strtok($sql, ' '));
            }
            if (!isset($tables[$table])) {
                $tables[$table] = 0;
            }
            $tables[$table]++;
        }
        arsort($tables);
        foreach ($tables as $table => $n) {
            $this->out(sprintf('    %-30s %3d', $table, $n));
        }
        if (empty($this->params['verbose'])) {
            return;
        }
        foreach (array_slice($after, $before) as $i => $entry) {
            $this->out(sprintf('    %2d. %s', $i + 1,
                substr(preg_replace('/\s+/', ' ', $entry['query']), 0, 190)));
        }
    }
}
