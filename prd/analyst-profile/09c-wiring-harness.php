<?php
App::uses('View', 'View');
App::uses('ThemeView', 'View');
App::uses('Controller', 'Controller');
App::uses('AnalystProfileFormTool', 'Tools');
App::uses('ValueVerdictTool', 'Tools');
App::uses('ValueVerdictDiffTool', 'Tools');
App::uses('ValueRelevanceTool', 'Tools');
App::uses('ValueEnrichmentTool', 'Tools');
App::uses('ValueUrlTool', 'Tools');
App::uses('ValueDisposition', 'Tools');

/**
 * Phase 8c's templates, rendered and asserted with no HTTP session.
 *
 * 8a proved the view-model and `09a-contract-http-probe.sh` proves the
 * transport. What only a render can see is whether the **markup** says
 * what the arrays mean: whether a signal's points reach a form that
 * posts back the same document, whether an empty diff says *no
 * change* rather than showing a blank table, and whether the relevance
 * axis prints the label the value page prints or the key underneath
 * it.
 *
 * The round-trip is the check this file exists for. §7c item 4: render
 * the form, post it back unchanged, and the stored `points` map must
 * be byte-identical. A form that silently rewrites a document it did
 * not understand is the one failure an editor must not have, and it is
 * invisible to every assertion about an array.
 *
 * **Not part of the application.** Copy it in for the duration:
 *
 *   cp prd/analyst-profile/09c-wiring-harness.php \
 *      app/Console/Command/AnalystWiringShell.php
 *   app/Console/cake AnalystWiring all 1
 *   rm app/Console/Command/AnalystWiringShell.php
 */
class AnalystWiringShell extends AppShell
{
    public $uses = array('User', 'AnalystProfile');

    private $checks = 0;
    private $failures = 0;

    /**
     * cake AnalystWiring all <userId> [value]
     */
    public function all()
    {
        $user = $this->User->getAuthUser((int)$this->args[0]);
        $value = isset($this->args[1]) ? $this->args[1] : '8.8.8.8';
        Configure::write('CurrentUserId', $user['id']);
        Configure::write('debug', 2);

        $this->out(sprintf('reader: %s · value: %s', $user['email'], $value));

        $this->sectionRoundTrip($user);
        $this->sectionLegacyUpgrade($user);
        $this->sectionDiff($user, $value);
        $this->sectionRelevance($user, $value);
        $this->sectionEmptyStates();
        $this->sectionPalette();
        $this->sectionVerdictLinks($user, $value);
        $this->sectionReadOnly($user, $value);
        $this->sectionTheWayIn($user, $value);
        $this->sectionDirectionPair($user, $value);

        $this->out('');
        $this->out(sprintf('%d checks, %d failures',
            $this->checks, $this->failures));
        if ($this->failures > 0) {
            $this->_stop(1);
        }
    }

    /* ============================================================
     * §7c.4 — the form round-trips
     * ============================================================ */

    /**
     * Render the editor for the shipped default, read every field name
     * and value back out of the markup, parse them the way PHP parses
     * a POST, and merge them onto the stored document.
     *
     * The document that comes out must equal the one that went in.
     * Anything else means the form dropped a key it could not label,
     * cast a number to a string, or rewrote a list as a map — and the
     * analyst would only find out when their assessment moved.
     */
    private function sectionRoundTrip(array $user)
    {
        $this->out('');
        $this->out('== the generated form round-trips ==');
        $profile = $this->AnalystProfile->resolveFor($user);
        if ($profile === null) {
            $this->fail('no profile in force to round-trip');
            return;
        }
        $stored = $profile['parameters'];
        $form = new AnalystProfileFormTool();
        /*
         * This section asks whether a *current-shape* document survives
         * the form, so it starts from one. An instance still holding a
         * shape an older version wrote is not a failure here — the
         * editor announces that and rewrites it, which
         * `sectionLegacyUpgrade()` is the check for — but reading it as
         * one would make this section fail for a reason that has
         * nothing to do with the round trip.
         */
        if (!empty($form->legacyShapes($stored))) {
            $stored = $form->merge($stored, $this->postedFrom(
                $this->renderWorkbench($user, $profile, null, true)));
            $profile['parameters'] = $stored;
            $this->ok(empty($form->legacyShapes($stored)),
                'the in-force profile carried an older shape, upgraded'
                    . ' once before the round trip is asked about');
        }
        $html = $this->renderWorkbench($user, $profile, null, true);
        if (strpos($html, 'EXCEPTION') === 0) {
            $this->fail('the editor did not render: '
                . substr($html, 0, 400));
            return;
        }
        $posted = $this->postedFrom($html);
        $this->ok(!empty($posted['signals']),
            sprintf('the form posts %d sections',
                count($posted)));

        $merged = $form->merge($stored, $posted);

        foreach (array('signals', 'escalations', 'exclusions') as $name) {
            $this->compareEntries($name, $stored, $merged);
        }
        foreach (array('thresholds', 'relevance', 'reference',
            'enrichment') as $name
        ) {
            $this->compareSection($name, $stored, $merged);
        }

        $this->ok(
            $this->canonical($merged) === $this->canonical($stored),
            'the whole document comes back identical, so revision would'
                . ' not move'
        );
        if ($this->canonical($merged) !== $this->canonical($stored)) {
            $this->diffDocuments($stored, $merged);
        }

        /*
         * `group` is the case that makes the round-trip load-bearing
         * rather than decorative. The editor draws no control for it —
         * it decides a heading and no arithmetic, so it was removed —
         * while the engine still reads `$entry['group'] ?: $signal->group`
         * for documents that arrive by import or by the Raw JSON pane.
         * That only holds while a section save *merges*: the moment it
         * starts replacing, a key with no field silently leaves the
         * document, and the assertion above is what notices.
         */
        $this->ok(strpos($html, 'signals][group]') === false
            && strpos($html, '[group]') === false,
            'the editor draws no ledger-group control');
        $grouped = 0;
        foreach ($merged['signals'] as $entry) {
            if (!empty($entry['group'])) {
                $grouped++;
            }
        }
        $this->ok($grouped > 0, sprintf(
            'and a save still keeps the %d group keys the document holds,'
                . ' which is what the import path depends on', $grouped));

        /*
         * The same property for the two things the editor stopped
         * drawing when it was made production-ready: `auto`, which is
         * storable and not offered because nothing runs on its own,
         * and the reuse window, which governs nothing until a store
         * exists. Neither may be rewritten by a save, and a row whose
         * document declares `auto` has to keep offering it or the
         * select would fall to its first option and drop it.
         */
        $carrying = $stored;
        $type = key($carrying['enrichment']['auto_run']);
        $module = key($carrying['enrichment']['auto_run'][$type]);
        $carrying['enrichment']['auto_run'][$type][$module] = 'auto';
        $carrying['enrichment']['max_age_hours'] = 36;
        $autoHtml = $this->renderWorkbench($user,
            array('parameters' => $carrying) + $profile, null, true);
        $this->ok(
            strpos($autoHtml, 'declared here, not offered') !== false,
            sprintf('a row declaring `auto` still offers it, named as'
                . ' not offered (%s on %s)', $module, $type)
        );
        $this->ok(
            strpos($autoHtml, 'enrichment][max_age_hours]') === false,
            'while the reuse window has no control — the key is still in'
                . ' the document, and the Raw JSON pane still shows it'
        );
        $autoMerged = $form->merge($carrying,
            $this->postedFrom($autoHtml));
        $this->ok(
            isset($autoMerged['enrichment']['auto_run'][$type][$module])
                && $autoMerged['enrichment']['auto_run'][$type][$module]
                    === 'auto',
            'and a save keeps the state the editor does not offer'
        );
        $this->ok(
            isset($autoMerged['enrichment']['max_age_hours'])
                && (int)$autoMerged['enrichment']['max_age_hours'] === 36,
            'and keeps the window no field posted, because a section'
                . ' save merges rather than replaces'
        );
    }

    /* ============================================================
     * And the same form over a document an older version wrote
     * ============================================================ */

    /**
     * `relevance` and `enrichment` are read through a shim — D18 gave
     * TTLs four buckets, D19 renamed the posture — so the editor
     * renders the shimmed reading and posting any section back writes
     * the current shape. That upgrade is the design; what has to be
     * true of it is that it is **lossless**, that it **settles** in
     * one pass, and that the page **says so** before the analyst
     * presses Save on what they believe is a no-op.
     *
     * Built here rather than waited for: whether the instance happens
     * to hold a legacy profile is not something a check should depend
     * on, and once `updateDefaults()` has run it never does.
     */
    private function sectionLegacyUpgrade(array $user)
    {
        $this->out('');
        $this->out('== a document an older version wrote ==');
        $profile = $this->AnalystProfile->resolveFor($user);
        $form = new AnalystProfileFormTool();

        $legacy = $profile['parameters'];
        $shelf = ValueRelevanceTool::section(array('parameters' => $legacy));
        $legacy['relevance']['ttl_days'] = $shelf['ttl_days']
            + array('default' => $shelf['ttl_default']);
        unset($legacy['relevance']['ttl_buckets'],
            $legacy['relevance']['ttl_types'],
            $legacy['relevance']['ttl_overrides'],
            $legacy['relevance']['ttl_default']);
        /*
         * And the posture, in its oldest spelling. Until 3.21 this was
         * a rename of a key the shipped default carried, so it was
         * built by converting one; the withdrawal took both keys out of
         * the default, the conversion stopped firing, and the note went
         * uncovered while the section still passed. Written in flat.
         */
        $legacy['enrichment']['cost_posture'] = 'local_only';
        unset($legacy['enrichment']['locality_posture']);
        /*
         * And a `when` threshold written as the word for the setting it
         * follows. Built here for the same reason as the two above: the
         * shipped profile stopped carrying the word, so waiting for an
         * instance to hold one is waiting on an accident.
         */
        foreach ($legacy['escalations'] as $i => $entry) {
            if ($entry['id'] === 'conflict:listed-vs-asserted') {
                $legacy['escalations'][$i]['when']['threat_share_at_least']
                    = 'supermajority';
            }
        }

        $notes = $form->legacyShapes($legacy);
        $this->ok(count($notes) === 3,
            sprintf('the editor names %d older shapes before a save',
                count($notes)));

        $stale = $profile;
        $stale['parameters'] = $legacy;
        $posted = $this->postedFrom(
            $this->renderWorkbench($user, $stale, null, true));
        $upgraded = $form->merge($legacy, $posted);

        $this->ok($this->canonical($upgraded) !== $this->canonical($legacy),
            'and a save rewrites it into the current shape');
        $this->ok(!isset($upgraded['relevance']['ttl_days']),
            'dropping the flat TTL map rather than leaving both');
        $this->ok(isset($upgraded['relevance']['ttl_buckets']),
            'and writing the buckets');
        $this->ok(!$this->carriesFollowedWord($upgraded),
            'and dropping the word a threshold followed, which an'
                . ' absent key already said');

        $before = ValueRelevanceTool::section(array('parameters' => $legacy));
        $after = ValueRelevanceTool::section(array('parameters' => $upgraded));
        foreach (array('ttl_days', 'ttl_default', 'clock', 'type_rule',
            'decay_speed', 'aging_fraction', 'lag_uncertain_days') as $key
        ) {
            $this->ok(
                $this->canonical($before[$key])
                    === $this->canonical($after[$key]),
                sprintf('the upgrade leaves relevance.%s alone', $key)
            );
        }

        $settled = $form->merge($upgraded, $this->postedFrom(
            $this->renderWorkbench($user,
                array('parameters' => $upgraded) + $profile, null, true)
        ));
        $this->ok(
            $this->canonical($settled) === $this->canonical($upgraded),
            'and the upgraded document round-trips byte-identical'
        );
        if ($this->canonical($settled) !== $this->canonical($upgraded)) {
            $this->diffDocuments($upgraded, $settled);
        }
        $this->ok(empty($form->legacyShapes($upgraded)),
            'with nothing left for the notice to say');
    }

    /**
     * Whether any escalation still writes a threshold as the word for
     * the setting it follows.
     */
    private function carriesFollowedWord(array $parameters)
    {
        foreach ($parameters['escalations'] as $entry) {
            if (isset($entry['when']['threat_share_at_least'])
                && !is_numeric($entry['when']['threat_share_at_least'])
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * One list section, entry by entry.
     */
    private function compareEntries($name, array $stored, array $merged)
    {
        $before = $this->byId(isset($stored[$name]) ? $stored[$name] : array());
        $after = $this->byId(isset($merged[$name]) ? $merged[$name] : array());
        $this->ok(array_keys($before) === array_keys($after),
            sprintf('%s keeps its %d entries, in order',
                $name, count($before)));
        foreach ($before as $id => $entry) {
            if (!isset($after[$id])) {
                $this->fail(sprintf('%s.%s vanished', $name, $id));
                continue;
            }
            if (json_encode($entry) !== json_encode($after[$id])) {
                $this->fail(sprintf('%s.%s changed: %s -> %s',
                    $name, $id, json_encode($entry),
                    json_encode($after[$id])));
            }
        }
        $this->ok(true, sprintf('%s: every entry byte-identical', $name));
    }

    private function compareSection($name, array $stored, array $merged)
    {
        $before = isset($stored[$name]) ? $stored[$name] : array();
        $after = isset($merged[$name]) ? $merged[$name] : array();
        $same = $this->canonical($before) === $this->canonical($after);
        $this->ok($same, sprintf('%s is byte-identical', $name));
        if (!$same) {
            foreach (array_unique(array_merge(array_keys((array)$before),
                array_keys((array)$after))) as $key
            ) {
                $a = isset($before[$key]) ? $before[$key] : null;
                $b = isset($after[$key]) ? $after[$key] : null;
                if ($this->canonical($a) === $this->canonical($b)) {
                    continue;
                }
                $this->out(sprintf('        %s.%s : %s  ->  %s',
                    $name, $key,
                    substr($this->canonical($a), 0, 160),
                    substr($this->canonical($b), 0, 160)));
            }
        }
    }

    /* ============================================================
     * §7c.2, §7c.3 — the diff, and what it says when nothing moved
     * ============================================================ */

    private function sectionDiff(array $user, $value)
    {
        $this->out('');
        $this->out('== the diff, and the direction pair ==');
        $profile = $this->AnalystProfile->resolveFor($user);
        if ($profile === null) {
            $this->fail('no profile in force');
            return;
        }
        $this->loadModel('ValueProfile');
        $engine = new ValueVerdictTool($this->ValueProfile);
        $context = $this->ValueProfile->verdictContextFor($user, $value,
            $profile);
        $before = $engine->assess($context, $profile);

        $same = ValueVerdictDiffTool::diff($before, $before);
        $html = $this->renderElement($user, 'AnalystProfiles/diff_table',
            array('detail' => $same, 'full' => false));
        $this->ok(stripos($html, 'No change.') !== false,
            'an identity diff renders as "no change", not a blank table');
        $this->ok(strpos($html, '<tbody>') === false,
            'and draws no table at all');

        /*
         * One weight changed, which is the case the whole simulator
         * exists for. `reporting.independent_orgs` is capped on this
         * value, so the cap is what moves the row — a lowered per-unit
         * weight would not, and a check that used one would pass for
         * the wrong reason.
         */
        $candidate = $profile;
        $entries = $candidate['parameters']['signals'];
        $touched = null;
        foreach ($entries as $index => $entry) {
            if ($entry['id'] === 'reporting.independent_orgs') {
                $candidate['parameters']['signals'][$index]['points']['cap']
                    = (int)($entry['points']['cap'] / 2);
                $touched = $entry['id'];
            }
        }
        $after = $engine->assess($context, $candidate);
        $moved = ValueVerdictDiffTool::diff($before, $after);
        $this->ok(!empty($moved['moved']),
            sprintf('halving one cap moves %d row(s): %s',
                count($moved['moved']), implode(', ', $moved['moved'])));
        $this->ok(in_array($touched, $moved['moved'], true),
            'and the row that moved is the one whose weight changed');
        $this->ok(!empty($moved['sums']['ok']),
            'both columns still sum to their own quality exactly');

        $html = $this->renderElement($user, 'AnalystProfiles/diff_table',
            array('detail' => $moved, 'full' => true));
        $this->ok(strpos($html, 'd-dn') !== false
            || strpos($html, 'd-up') !== false,
            'the delta column carries a direction class');
        $this->ok(strpos($html, 'text-danger') === false
            && strpos($html, 'text-success') === false,
            'and not a raw Bootstrap colour');
    }

    /* ============================================================
     * The relevance axis prints a label and a shelf, not a key
     * ============================================================ */

    private function sectionRelevance(array $user, $value)
    {
        $this->out('');
        $this->out('== the relevance axis ==');
        $profile = $this->AnalystProfile->resolveFor($user);
        $this->loadModel('ValueProfile');
        $engine = new ValueVerdictTool($this->ValueProfile);
        $context = $this->ValueProfile->verdictContextFor($user, $value,
            $profile);
        $verdict = $engine->assess($context, $profile);
        $diff = ValueVerdictDiffTool::diff($verdict, $verdict);
        $axis = $diff['axes']['relevance'];

        $this->ok(array_key_exists('label', $axis)
            && array_key_exists('runway', $axis),
            'the diff carries the label and the runway, not only the state');
        $state = $axis['after'];
        if ($state !== null) {
            $this->ok(
                $axis['label'] === ValueRelevanceTool::stateLabel($state),
                sprintf('and the label is the one writer\'s: %s -> %s',
                    $state, $axis['label'])
            );
        }

        $html = $this->renderElement($user, 'AnalystProfiles/assessment_head',
            array('axes' => $diff['axes'], 'moved' => false));
        foreach (array('lean', 'relevance', 'quality') as $name) {
            $this->ok(strpos($html, '>' . $name . '<') !== false,
                sprintf('the assessment head names %s', $name));
        }
        if ($state !== null && $state !== $axis['label']) {
            $this->ok(strpos($html, h($axis['label'])) !== false,
                'and prints the label rather than the key');
        }
        if (!empty($axis['runway']['runway'])) {
            $this->ok(strpos($html, 'ax-fill') !== false,
                'the shelf is drawn, so relevance has a magnitude too');
            $this->ok(strpos($html, '--vp-dir-') === false,
                'and not in the supports/disputes pair, which a clock is not');
        }

        /*
         * And the curve in the relevance pane marks where this value
         * sits on it. Asserted because the first version did not: the
         * loop that samples the polyline used `$runway` as its own
         * counter and overwrote the value the caller passed in, so
         * the curve drew and the point on it silently did not.
         */
        $form = new AnalystProfileFormTool();
        $sections = $form->sections($profile['parameters'], array(
            'attribute_types' => array('ip-src', 'ip-dst'),
        ));
        $relevance = $sections['relevance'];
        $buckets = null;
        foreach ($relevance['blocks'] as $block) {
            if ($block['kind'] === 'fields' && $block['id'] === 'ttl_buckets') {
                $buckets = $block;
            }
        }
        $html = $this->renderElement($user, 'AnalystProfiles/ttl_curve',
            array('block' => $buckets, 'section' => $relevance,
                'runway' => $axis['runway']));
        $this->ok(strpos($html, 'ttl-line') !== false,
            'the shelf-life curve is drawn');
        if (!empty($axis['runway']['elapsed_days'])) {
            $this->ok(strpos($html, 'ttl-here') !== false,
                'and it marks where the value on the bench sits on it');
            $this->ok(strpos($html, 'This value sits at day') !== false,
                'with the same thing said for a reader who cannot see it');
        }
    }

    /* ============================================================
     * The states a page has to have and cannot be shown by luck
     * ============================================================ */

    private function sectionEmptyStates()
    {
        $this->out('');
        $this->out('== empty states ==');
        $user = $this->User->getAuthUser((int)$this->args[0]);
        $profile = $this->AnalystProfile->resolveFor($user);
        $form = new AnalystProfileFormTool();

        $html = $this->renderElement($user, 'AnalystProfiles/comparison',
            array('comparison' => array(), 'pinned' => array()));
        $this->ok(strpos($html, 'Nothing pinned yet') !== false,
            'an empty comparison set renders its instruction');
        $this->ok(strpos($html, '<tbody>') === false,
            'and no empty table');

        /*
         * The three band states, built from the live tool rather than
         * read from `09a-fixtures/`: the fixtures are not mounted into
         * the container, and a strip computed here is a strip this
         * instance's catalogue can actually produce.
         */
        $cases = array(
            'inverted' => array('medium' => 60, 'high' => 30),
            'beyond the bound' => array('medium' => 30, 'high' => 180),
        );
        foreach ($cases as $name => $bands) {
            $candidate = $profile['parameters'];
            $candidate['thresholds']['quality_bands'] = $bands
                + $candidate['thresholds']['quality_bands'];
            $strip = $form->bandStrip($candidate);
            $this->ok(empty($strip['ok']),
                sprintf('%s is refused', $name));
            $html = $this->renderElement($user,
                'AnalystProfiles/block_strip',
                array('strip' => $strip, 'marks' => array()));
            $this->ok(strpos($html, 'wb-note bad') !== false
                || strpos($html, 'wb-note warn') !== false,
                sprintf('and the strip says what is wrong with %s', $name));
        }

        /*
         * And the one nobody caused: disabling signals moves the bound
         * under a band that was legal when it was set.
         */
        $narrow = $profile['parameters'];
        foreach ($narrow['signals'] as $index => $entry) {
            if ($index > 1) {
                $narrow['signals'][$index]['enabled'] = false;
            }
        }
        $strip = $form->bandStrip($narrow);
        $this->ok(empty($strip['ok']),
            'disabling nine signals makes the high band unreachable');
        $html = $this->renderElement($user, 'AnalystProfiles/block_strip',
            array('strip' => $strip, 'marks' => array()));
        $this->ok(strpos($html, 'attainable') !== false,
            'and the strip names the bound it can no longer reach');

        $html = $this->renderElement($user, 'AnalystProfiles/loader_errors',
            array('loader_errors' => array(array(
                'subject' => 'Signal',
                'file' => 'Broken.php',
                'reason' => 'not a ValueSignalBase',
            ))));
        $this->ok(strpos($html, 'Broken.php') !== false,
            "the loader's error list is on screen where an admin will see it");
    }

    /* ============================================================
     * The first trap: a stylesheet that did not apply
     * ============================================================ */

    private function sectionPalette()
    {
        $this->out('');
        $this->out('== the palette resolves ==');
        $palette = WWW_ROOT . 'css' . DS . 'value-palette.css';
        $editor = WWW_ROOT . 'css' . DS . 'analyst-profile.css';
        $this->ok(file_exists($palette) && file_exists($editor),
            'both stylesheets are on disk');
        $css = file_get_contents($palette);
        foreach (array('--vp-mal', '--vp-ben', '--vp-dir-with',
            '--vp-dir-against', '--vp-conflict') as $token
        ) {
            $this->ok(strpos($css, $token . ':') !== false,
                sprintf('%s is declared', $token));
        }
        $this->ok(strpos($css, '[data-bs-theme="dark"]') !== false,
            'and the dark theme redeclares the inks');
        $profileCss = file_get_contents(WWW_ROOT . 'css'
            . DS . 'value-profile.css');
        $this->ok(strpos($profileCss, '--vp-mal:') === false,
            'the value page no longer declares its own copy');
    }

    /* ============================================================
     * §5.1 — the two links back from the verdict
     * ============================================================ */

    /**
     * The value page names the profile that weighted it and lists what
     * a policy set aside. Both become links — and both must stay plain
     * text when the assessment names no profile, which is what the
     * page does today because it is still rendering the fixture.
     */
    private function sectionVerdictLinks(array $user, $value)
    {
        $this->out('');
        $this->out('== the links back from the verdict ==');
        $profile = $this->AnalystProfile->resolveFor($user);
        $this->loadModel('ValueProfile');
        $engine = new ValueVerdictTool($this->ValueProfile);
        $verdict = $engine->verdictFor($user, $value,
            array('profile' => $profile));
        $this->ok(!empty($verdict['profile_id']),
            'a computed assessment names the profile it read');

        $b64 = ValueUrlTool::encode($value);
        $html = $this->renderValueElement($user,
            'Values/View/value_verdict_meta',
            array('verdict' => $verdict, 'valueB64' => $b64));
        $this->ok(strpos($html, '/analystProfiles/view/'
            . $verdict['profile_id']) !== false,
            'and the provenance line links to it');

        $bare = $verdict;
        unset($bare['profile_id']);
        $html = $this->renderValueElement($user,
            'Values/View/value_verdict_meta',
            array('verdict' => $bare, 'valueB64' => $b64));
        $this->ok(strpos($html, '/analystProfiles/view/') === false,
            'an assessment naming none stays plain text');
        $this->ok(strpos($html, h($verdict['profile'])) !== false,
            'and still says which profile it was');

        /*
         * A `policy` entry is the analyst's own decision, so it is the
         * one kind of not-counted row that can say where the decision
         * lives. Synthesised rather than waited for: whether this
         * value happens to trip an exclusion today is not something a
         * check should depend on.
         */
        $withPolicy = $verdict;
        $withPolicy['not_counted'] = array(array(
            'id' => 'sightings.self',
            'title' => 'Self-sightings',
            'note' => 'Two sightings by the reporting organisation.',
            'reason' => 'policy',
        ), array(
            'id' => 'attribution.galaxy',
            'title' => 'No galaxy',
            'note' => 'Nothing to read.',
            'reason' => 'nodata',
        ));
        $html = $this->renderValueElement($user,
            'Values/View/value_verdict_not_counted',
            array(
                'valueProfile' => array('verdict' => $withPolicy),
                'valueB64' => $b64,
            ));
        $this->ok(strpos($html, 'section=exclusions') !== false,
            'a policy entry links to the section that produced it');
        $this->ok(substr_count($html, 'section=exclusions') === 1,
            'and only the policy entry does — a nodata row has nowhere'
                . ' to send anybody');
    }

    /**
     * A value-page element, under the theme those live in.
     *
     * @param array $user
     * @param string $element
     * @param array $data
     * @return string
     */
    private function renderValueElement(array $user, $element, array $data)
    {
        $controller = new Controller(new CakeRequest(null, false),
            new CakeResponse());
        $controller->theme = 'Overmind';
        $controller->viewPath = 'Values';
        $controller->layout = false;
        $controller->set($data + array(
            'baseurl' => '',
            'me' => $user,
            'isSiteAdmin' => !empty($user['Role']['perm_site_admin']),
        ));
        $controller->helpers = array('Html', 'Form');
        $view = new ThemeView($controller);
        $view->theme = 'Overmind';
        ob_start();
        try {
            $html = $view->element($element, $data);
        } catch (Exception $e) {
            $html = 'EXCEPTION: ' . get_class($e) . ': ' . $e->getMessage();
        }
        return ob_get_clean() . $html;
    }

    /* ============================================================
     * `view` is the same two panes with the inputs taken out
     * ============================================================ */

    private function sectionReadOnly(array $user, $value)
    {
        $this->out('');
        $this->out('== the read-only page ==');
        $profile = $this->AnalystProfile->resolveFor($user);
        $html = $this->renderWorkbench($user, $profile, $value, false);
        $this->ok(substr_count($html, 'data-ap-field') === 0,
            'a profile rendered read-only carries no editable field');
        $this->ok(strpos($html, 'wb-rail-item') !== false,
            'and the same rail');
        $this->ok(strpos($html, 'readonly') !== false,
            'with the raw document readable and not writable');

        $editable = $this->renderWorkbench($user, $profile, $value, true);
        $this->ok(substr_count($editable, 'data-ap-field') > 40,
            sprintf('the editable page carries %d fields',
                substr_count($editable, 'data-ap-field')));
    }

    /* ============================================================
     * §7.12 — the bench can be filled, and the editor can run
     * ============================================================ */

    /**
     * Four defects the rendered arrays cannot see, and one the markup
     * cannot: an editor whose script never executes draws perfectly.
     */
    private function sectionTheWayIn(array $user, $value)
    {
        $this->out('');
        $this->out('== the way onto the bench ==');
        $profile = $this->AnalystProfile->resolveFor($user);

        /*
         * The whole file is inert unless it waits: `assetLoader` echoes
         * the script above the markup, so a lookup at load time answers
         * null and the editor silently ships without JavaScript.
         */
        $js = file_get_contents(WWW_ROOT . 'js' . DS . 'analyst-profile.js');
        $this->ok(strpos($js, 'DOMContentLoaded') !== false,
            'the editor waits for the document before reading it');

        $empty = $this->renderWorkbench($user, $profile, null, true);
        $this->ok(strpos($empty, 'data-ap-bench-input') !== false,
            'an empty bench carries the box that fills it');
        $this->ok(strpos($empty, 'Nothing on the bench') !== false,
            'and still says it is empty');
        $this->ok(strpos($empty, 'id="ap-bench-value"') !== false,
            'the benched value rides in a field the recompute posts');
        $this->ok(!preg_match('/data-ap-simulate="[^"]*\?value=/', $empty),
            'and not in the query, which would outrank it');

        $filled = $this->renderWorkbench($user, $profile, $value, true);
        $this->ok(strpos($filled, 'data-ap-pin=') !== false,
            'a benched value carries the press that pins it');
        /*
         * A `<form>` for it would sit inside the editor's form, and a
         * browser discards the inner one — so the press submitted the
         * editor instead of pinning. This is the assertion that says
         * the markup is one form deep.
         */
        $this->ok(substr_count($filled, '<form') === 1,
            'and the editor is one form deep, so the press is not swallowed');
    }

    /* ============================================================
     * §7.16 — the direction pair follows the lean, on both surfaces
     * ============================================================ */

    /**
     * `--vp-dir-with` means *with the lean*, not *malicious*. On a
     * benign value the row agreeing with the verdict is the green one,
     * and the editor used to paint it red because it took the `:root`
     * default while the value page swapped.
     */
    private function sectionDirectionPair(array $user, $value)
    {
        $this->out('');
        $this->out('== the direction pair follows the lean ==');

        $benign = ValueDisposition::directionStyle('BENIGN');
        $malicious = ValueDisposition::directionStyle('MALICIOUS');
        $this->ok(strpos($benign, '--vp-dir-with: var(--vp-ben)') !== false,
            'a benign verdict makes *with* the green');
        $this->ok(strpos($benign, '--vp-dir-against: var(--vp-mal)') !== false,
            'and *against* the red');
        $this->ok(strpos($malicious, '--vp-dir-with: var(--vp-mal)') !== false,
            'a malicious verdict is the other way round');
        /*
         * The ink pair has to swap too. A dark-theme rule naming
         * `--vp-mal-ink` directly cannot be swapped, which is exactly
         * how `analyst-profile.css` kept the malicious reading in dark
         * however carefully the hue was flipped.
         */
        $this->ok(strpos($benign, '--vp-dir-with-ink: var(--vp-ben-ink)')
            !== false, 'and the ink pair swaps with it');
        $css = file_get_contents(WWW_ROOT . 'css' . DS . 'analyst-profile.css');
        $this->ok(strpos($css, '--vp-dir-with-ink') !== false
            && !preg_match('/\.d-up\s*\{[^}]*--vp-mal-ink/', $css),
            'the dark rules read the pair rather than the inks');
        $palette = file_get_contents(WWW_ROOT . 'css' . DS . 'value-palette.css');
        $this->ok(strpos($palette, '--vp-dir-with-ink:') !== false,
            'which the shared palette declares a default for');

        /*
         * And the editor actually emits it. Rendered, because the
         * plumbing runs through three templates and a lean that never
         * arrives is indistinguishable from one that did.
         */
        $profile = $this->AnalystProfile->resolveFor($user);
        $html = $this->renderWorkbench($user, $profile, $value, true);
        $this->ok(strpos($html, '--vp-dir-with:') !== false,
            'the rendered editor carries a direction pair at all');
        $this->ok(strpos($html, 'toward') !== false,
            'and the contribution column says what a + is toward');

        /*
         * The recompute answers the bench alone, so the contribution
         * column can only keep up if the fragment carries the new
         * ledger and the cells are addressable. Both halves asserted:
         * one missing is a column that silently goes stale again.
         */
        $this->ok(strpos($html, 'data-ap-contrib="') !== false,
            'the contribution cells are addressed by signal id');
        $this->ok(strpos($html, 'data-ap-ledger="') !== false,
            'and the bench fragment carries the ledger back to them');
        $this->ok(strpos($html, 'data-ap-anchor') !== false,
            'with the anchor line always present, so it can be rewritten');
        $js = file_get_contents(WWW_ROOT . 'js' . DS . 'analyst-profile.js');
        /*
         * Anything the swap hands over may be repainted beside the
         * ledger — the curve already is — so what this asserts is that
         * the ledger is repainted *from the same handler*, not that it
         * is the only line after it.
         */
        $this->ok(strpos($js, 'repaintLedger') !== false
            && preg_match('/innerHTML = request\.responseText;'
                . '(?:\s*\n\s*\w+\(\);)*\s*\n\s*repaintLedger/', $js),
            'and the editor repaints them on every recompute');

        /*
         * The explanation a reader needs before any of the above means
         * anything: the scale, and why it flips.
         */
        $this->ok(strpos($html, 'looks dangerous') !== false,
            'the signals pane explains the scale in plain words');
        $this->ok(strpos($html, 'decided separately') !== false,
            'and says the verdict is decided before the points are');
    }

    /* ============================================================
     * Rendering
     * ============================================================ */

    /**
     * The whole workbench, as `edit` or `view` would hand it over.
     */
    private function renderWorkbench(array $user, array $profile, $value,
        $editable
    ) {
        $form = new AnalystProfileFormTool();
        $parameters = $profile['parameters'];
        $sources = array(
            'attribute_types' => array('ip-src', 'ip-dst', 'domain', 'url',
                'md5', 'sha1', 'sha256', 'hostname', 'email-src', 'btc',
                'filename'),
            'orgs' => array(),
            'warninglists' => array(),
            'modules' => array(),
        );
        $checked = $form->validate($parameters);
        $bench = array(
            'detail' => null,
            'focus' => $value,
            'values' => $value === null ? array() : array($value),
            'comparison' => array(),
            'comparison_set' => array(),
            'context_builds' => 0,
        );
        if ($value !== null) {
            $this->loadModel('ValueProfile');
            $engine = new ValueVerdictTool($this->ValueProfile);
            $context = $this->ValueProfile->verdictContextFor($user,
                $value, $profile);
            $verdict = $engine->assess($context, $profile);
            $bench['detail'] = ValueVerdictDiffTool::diff($verdict, $verdict);
            $bench['comparison'] = array(
                ValueVerdictDiffTool::headline($value, $verdict, $verdict),
            );
            $bench['context_builds'] = 1;
        }
        return $this->renderElement($user, 'AnalystProfiles/workbench', array(
            'profile' => $this->AnalystProfile->summarise($profile),
            'editable' => $editable,
            'sections' => $form->sections($parameters, $sources),
            'bands' => $form->bandStrip($parameters),
            'errors' => $checked['errors'],
            'warnings' => $checked['warnings'],
            'legacy' => $form->legacyShapes($parameters),
            'loader_errors' => array(),
            'raw' => json_encode($parameters,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'value' => $value,
            'open_section' => null,
            'bench' => $bench,
        ));
    }

    private function renderElement(array $user, $element, array $data)
    {
        $controller = new Controller(new CakeRequest(null, false),
            new CakeResponse());
        $controller->theme = 'Overmind';
        $controller->viewPath = 'AnalystProfiles';
        $controller->layout = false;
        $controller->set($data + array(
            'baseurl' => '',
            'queryVersion' => '203',
            'me' => $user,
            'isSiteAdmin' => !empty($user['Role']['perm_site_admin']),
        ));
        $controller->helpers = array('Html', 'Form');
        $view = new ThemeView($controller);
        $view->theme = 'Overmind';
        ob_start();
        try {
            $html = $view->element($element, $data);
        } catch (Exception $e) {
            $html = 'EXCEPTION: ' . get_class($e) . ': ' . $e->getMessage()
                . "\n" . $e->getTraceAsString();
        }
        $noise = ob_get_clean();
        return $noise . $html;
    }

    /* ============================================================
     * Reading a form back out of its own markup
     * ============================================================ */

    /**
     * Every `data[AnalystProfile][parameters]...` control in the
     * markup, parsed the way PHP parses the POST it would make.
     *
     * Checkboxes count only when checked; a select contributes its
     * selected option; a disabled control contributes nothing, which
     * is how the raw-document textarea stays out of a section save.
     *
     * @param string $html
     * @return array The `parameters` sub-array of the request
     */
    private function postedFrom($html)
    {
        $pairs = array();
        $previous = new DOMDocument();
        libxml_use_internal_errors(true);
        $previous->loadHTML('<?xml encoding="utf-8" ?><div>' . $html
            . '</div>');
        libxml_clear_errors();
        $xpath = new DOMXPath($previous);
        foreach ($xpath->query('//input | //select | //textarea') as $node) {
            $name = $node->getAttribute('name');
            if (strpos($name, 'data[AnalystProfile][parameters]') !== 0) {
                continue;
            }
            if ($node->hasAttribute('disabled')) {
                continue;
            }
            if ($node->nodeName === 'input') {
                $type = strtolower($node->getAttribute('type'));
                if (($type === 'checkbox' || $type === 'radio')
                    && !$node->hasAttribute('checked')
                ) {
                    continue;
                }
                $pairs[] = array($name, $node->getAttribute('value'));
                continue;
            }
            if ($node->nodeName === 'textarea') {
                $pairs[] = array($name, $node->textContent);
                continue;
            }
            $value = '';
            foreach ($node->getElementsByTagName('option') as $option) {
                if ($option->hasAttribute('selected')) {
                    $value = $option->getAttribute('value');
                }
            }
            $pairs[] = array($name, $value);
        }

        $query = array();
        foreach ($pairs as $pair) {
            $query[] = urlencode($pair[0]) . '=' . urlencode($pair[1]);
        }
        $parsed = array();
        parse_str(implode('&', $query), $parsed);
        return isset($parsed['data']['AnalystProfile']['parameters'])
            ? $parsed['data']['AnalystProfile']['parameters']
            : array();
    }

    /* ============================================================
     * Small things
     * ============================================================ */

    private function byId($entries)
    {
        $out = array();
        foreach ((array)$entries as $entry) {
            if (is_array($entry) && isset($entry['id'])) {
                $out[$entry['id']] = $entry;
            }
        }
        return $out;
    }

    private function canonical($value)
    {
        return json_encode($this->sorted($value));
    }

    private function sorted($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->sorted($item);
        }
        return $value;
    }

    private function diffDocuments(array $stored, array $merged)
    {
        foreach (array_unique(array_merge(array_keys($stored),
            array_keys($merged))) as $key
        ) {
            $a = isset($stored[$key]) ? $stored[$key] : null;
            $b = isset($merged[$key]) ? $merged[$key] : null;
            if ($this->canonical($a) !== $this->canonical($b)) {
                $this->out('        section ' . $key . ' differs');
            }
        }
    }

    private function ok($condition, $label)
    {
        $this->checks++;
        if ($condition) {
            $this->out('  ok    ' . $label);
            return;
        }
        $this->failures++;
        $this->out('  FAIL  ' . $label);
    }

    private function fail($label)
    {
        $this->checks++;
        $this->failures++;
        $this->out('  FAIL  ' . $label);
    }
}
