<?php

/**
 * Scratch shell: is a **stateless** Enrichment tab feasible — a live
 * catalogue of the modules eligible for a value, one run on demand, and
 * the result rendered but never stored?
 *
 * `tabs/04-enrichment.md` §11 listed eight things live data would hit,
 * and six of them were persistence. The 2026-09-06 decision drops
 * persistence from the phase rather than building it, so what is left to
 * establish is the half §11 never doubted: that the catalogue, the run
 * and the result are all readable without a store. This probe answers
 * that against the instance rather than against the module list.
 *
 * It checks six things:
 *
 *   1. `Value::typesFor` — a value is *several* types, not one. The
 *      fixture carries a single `'type' => 'ip-dst'` and the tab is
 *      built on it; `8.8.8.8` is four.
 *   2. `Module::getEnabledModules` — what the instance actually enables,
 *      as against the 157 the service reports. Eligible is a much
 *      smaller set than declared.
 *   3. The eligible set per value, unioned over its types, split by
 *      expansion and hover — the rail's real length.
 *   4. Config-completeness. A module may be enabled, eligible and
 *      certain to fail, because `meta.config` names keys the instance
 *      has not set. `getEnabledModules` does not look, so the rail
 *      would list a guaranteed failure beside a working module.
 *   5. A real run through `queryModuleServer($postData, false)` — the
 *      non-writing call `hoverEnrichment` uses — timed, with the result
 *      shape recorded per format.
 *   6. **That the run wrote nothing.** Attributes, objects and events
 *      are counted either side of every query. This is the page's
 *      prime rule and the one a phase that runs third-party code has to
 *      demonstrate rather than assert.
 *
 * **Not part of the application.** Copy it in for the duration:
 *
 *   cp prd/value-profile-live/28-enrichment-probe.php \
 *      app/Console/Command/ValueEnrichProbeShell.php
 *   app/Console/cake ValueEnrichProbe run 1
 *   app/Console/cake ValueEnrichProbe run 1 8.8.8.8
 *   rm app/Console/Command/ValueEnrichProbeShell.php
 *
 * Nothing here runs a module that leaves the building unless RUNNABLE
 * names it: the point is to establish the shape of a result, and doing
 * that against an adversary's infrastructure is exactly what §9 of the
 * tab document says a page must not do casually.
 */
class ValueEnrichProbeShell extends AppShell
{
    public $uses = array('User', 'Value', 'Module', 'MispAttribute');

    /**
     * Instance values chosen to span the shapes: an IP held under four
     * types, a domain, an md5, and a value the reader holds nothing of.
     */
    const VALUES = array(
        '8.8.8.8',
        'github.com',
        'f1d3ff8443297732862df21dc4e57262',
        '45.155.205.233',
    );

    /**
     * Modules this probe is willing to actually execute, and why each
     * is safe to: all four answer from a local or CIRCL service and
     * none of them queries the value's own infrastructure.
     */
    const RUNNABLE = array(
        'mmdb_lookup',
        'hashlookup',
        'circl_passivedns',
        'onion_lookup',
        'whois',
    );

    /**
     * cake ValueEnrichProbe run <userId> [value]
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

        $this->catalogue($user);

        $values = isset($this->args[1])
            ? array($this->args[1])
            : self::VALUES;
        foreach ($values as $value) {
            $this->forValue($user, $value);
        }
    }

    /**
     * The ACL band, without writing a user to the instance.
     *
     * `perm_add` is MISP's own bar for querying a module at all —
     * both `attributes/hoverEnrichment` and `events/queryEnrichment`
     * carry it — and this page does not undercut it. The dev instance
     * has no role without the permission, and adding one is a write to
     * somebody's database for the sake of a boolean, so this strips it
     * from the loaded user in memory and reads the flag the rail draws
     * the control from.
     *
     * The endpoint is gated independently in `ACLComponent`; this
     * checks the half that decides whether the button renders enabled.
     *
     * cake ValueEnrichProbe perms <userId> [value]
     */
    public function perms()
    {
        $user = $this->User->getAuthUser((int)$this->args[0]);
        if (empty($user)) {
            $this->out('no such user');
            return;
        }
        $value = isset($this->args[1]) ? $this->args[1] : '8.8.8.8';
        $profile = ClassRegistry::init('ValueProfile');

        foreach (array(1, 0) as $permAdd) {
            $reader = $user;
            $reader['Role']['perm_add'] = $permAdd;
            // A site admin passes `canUse` whatever else is true, and
            // would mask the flag being read at all.
            $reader['Role']['perm_site_admin'] = 0;
            $out = $profile->forEnrichment($reader, $value);
            $this->out(sprintf(
                'perm_add=%d -> can_run=%-5s modules=%d',
                $permAdd,
                empty($out['enrichment']['can_run']) ? 'false' : 'true',
                count($out['enrichment']['modules'])
            ));
        }
    }

    /**
     * Check 2 and 4: what the instance enables, and which of those are
     * certain to fail for want of a key.
     */
    private function catalogue(array $user)
    {
        $this->out('');
        $this->out('=== enabled catalogue ===');

        $t = microtime(true);
        $all = $this->Module->getModules('Enrichment');
        $serviceMs = (microtime(true) - $t) * 1000;
        if (!is_array($all)) {
            $this->out('  service: ' . $all);
            return;
        }
        $this->out(sprintf(
            '  service reports %d modules  (%.0f ms)',
            count($all),
            $serviceMs
        ));

        $t = microtime(true);
        $enabled = $this->Module->getEnabledModules($user);
        $enabledMs = (microtime(true) - $t) * 1000;
        if (!is_array($enabled) || empty($enabled['modules'])) {
            $this->out('  getEnabledModules: '
                . (is_array($enabled) ? 'empty' : $enabled));
            return;
        }
        $this->out(sprintf(
            '  enabled for this reader: %d  (%.0f ms)',
            count($enabled['modules']),
            $enabledMs
        ));

        foreach ($enabled['modules'] as $module) {
            $kinds = implode('+', $module['meta']['module-type']);
            $format = isset($module['mispattributes']['format'])
                ? $module['mispattributes']['format']
                : 'simplified';
            $inputs = isset($module['mispattributes']['input'])
                ? count($module['mispattributes']['input'])
                : 0;
            $this->out(sprintf(
                '    %-24s %-16s %-13s %2d inputs   %s',
                $module['name'],
                $kinds,
                $format,
                $inputs,
                $this->configState($module)
            ));
        }
    }

    /**
     * Check 4, per module. `meta.config` names the keys a module needs;
     * the instance sets them as `Plugin.Enrichment_<name>_<key>`.
     */
    private function configState(array $module)
    {
        if (empty($module['meta']['config'])) {
            return 'no config needed';
        }
        $missing = array();
        foreach ($module['meta']['config'] as $key) {
            $set = Configure::read(
                'Plugin.Enrichment_' . $module['name'] . '_' . $key
            );
            if ($set === null || $set === '') {
                $missing[] = $key;
            }
        }
        if (empty($missing)) {
            return 'config complete ('
                . count($module['meta']['config']) . ')';
        }
        return 'MISSING ' . implode(',', $missing);
    }

    /**
     * Checks 1, 3, 5 and 6 for one value.
     */
    private function forValue(array $user, $value)
    {
        $this->out('');
        $this->out('=== ' . $value . ' ===');

        $t = microtime(true);
        $types = $this->Value->typesFor($user, $value);
        $typesMs = (microtime(true) - $t) * 1000;
        if (empty($types)) {
            $this->out(sprintf(
                '  types: none the reader may see  (%.0f ms)',
                $typesMs
            ));
            return;
        }
        $labels = array();
        foreach ($types as $row) {
            $labels[] = $row['type'] . ' ' . $row['count'];
        }
        $this->out(sprintf(
            '  types: %s  (%.0f ms)',
            implode(', ', $labels),
            $typesMs
        ));

        $enabled = $this->Module->getEnabledModules($user);
        if (!is_array($enabled) || empty($enabled['modules'])) {
            $this->out('  no enabled modules');
            return;
        }

        /*
         * Check 3. `types` is the expansion map and `hover_type` the
         * hover one; a module in both is one rail row, not two, so the
         * union is over names and the kind is a property of the row.
         */
        $eligible = array();
        foreach ($types as $row) {
            $type = $row['type'];
            foreach (array('types', 'hover_type') as $map) {
                if (empty($enabled[$map][$type])) {
                    continue;
                }
                foreach ($enabled[$map][$type] as $name) {
                    if (!isset($eligible[$name])) {
                        $eligible[$name] = array(
                            'kinds' => array(),
                            'types' => array(),
                        );
                    }
                    $kind = $map === 'types' ? 'expansion' : 'hover';
                    $eligible[$name]['kinds'][$kind] = true;
                    $eligible[$name]['types'][$type] = true;
                }
            }
        }
        if (empty($eligible)) {
            $this->out('  eligible: none — no enabled module takes '
                . 'any of these types');
            return;
        }
        $this->out(sprintf('  eligible: %d', count($eligible)));
        foreach ($eligible as $name => $meta) {
            $this->out(sprintf(
                '    %-24s %-18s via %s',
                $name,
                implode('+', array_keys($meta['kinds'])),
                implode(',', array_keys($meta['types']))
            ));
        }

        /*
         * Checks 5 and 6. One module, one type, timed, with the write
         * counts read either side of it.
         */
        foreach ($eligible as $name => $meta) {
            if (!in_array($name, self::RUNNABLE, true)) {
                continue;
            }
            $type = key($meta['types']);
            $this->runOne($user, $enabled, $name, $type, $value);
        }
    }

    /**
     * One real query, and the proof it stored nothing.
     */
    private function runOne(
        array $user,
        array $enabled,
        $name,
        $type,
        $value
    ) {
        $module = null;
        foreach ($enabled['modules'] as $candidate) {
            if ($candidate['name'] === $name) {
                $module = $candidate;
                break;
            }
        }
        if ($module === null) {
            $this->out(sprintf(
                '    run %-20s SKIPPED — not in the enabled list',
                $name
            ));
            return;
        }
        $format = isset($module['mispattributes']['format'])
            ? $module['mispattributes']['format']
            : 'simplified';

        $before = $this->writeCounts();

        $postData = array('module' => $name);
        if (!empty($module['meta']['config'])) {
            $config = array();
            foreach ($module['meta']['config'] as $key) {
                $config[$key] = Configure::read(
                    'Plugin.Enrichment_' . $name . '_' . $key
                );
            }
            $postData['config'] = $config;
        }
        if ($format === 'misp_standard') {
            /*
             * The seam a value page hits and an attribute page does
             * not: this format wants an attribute, and a value is not
             * one. The probe sends the reader's own first occurrence,
             * which is the only choice that cannot leak.
             */
            $occurrence = $this->firstOccurrence($user, $value, $type);
            if (empty($occurrence)) {
                $this->out(sprintf(
                    '    run %-20s SKIPPED — misp_standard and the '
                        . 'reader holds no %s occurrence',
                    $name,
                    $type
                ));
                return;
            }
            $postData['attribute'] = $occurrence;
        } else {
            $postData[$type] = $value;
        }

        $t = microtime(true);
        $result = $this->Module->queryModuleServer($postData, false);
        $ms = (microtime(true) - $t) * 1000;

        $after = $this->writeCounts();
        $delta = array();
        foreach ($after as $table => $n) {
            if ($n !== $before[$table]) {
                $delta[] = $table . ' ' . ($n - $before[$table]);
            }
        }

        $this->out(sprintf(
            '    run %-20s %-13s %7.0f ms  %s',
            $name,
            $format,
            $ms,
            $this->describe($result)
        ));
        $this->out(sprintf(
            '        wrote: %s',
            empty($delta) ? 'NOTHING' : 'CHANGED ' . implode(', ', $delta)
        ));
    }

    /**
     * The three tables an enrichment could plausibly grow.
     */
    private function writeCounts()
    {
        $counts = array();
        foreach (array('attributes', 'objects', 'events') as $table) {
            $model = ClassRegistry::init('MispAttribute');
            $db = $model->getDataSource();
            $row = $db->fetchAll(
                'SELECT COUNT(*) AS n FROM ' . $table
            );
            $counts[$table] = (int)$row[0][0]['n'];
        }
        return $counts;
    }

    /**
     * The reader's own first occurrence of this value under this type,
     * in the shape `misp_standard` expects.
     */
    private function firstOccurrence(array $user, $value, $type)
    {
        $conditions = $this->MispAttribute->buildConditions($user);
        $conditions['AND'][] = $this->Value->conditionsFor($value);
        $conditions['AND'][]['Attribute.type'] = $type;
        $row = $this->MispAttribute->find('first', array(
            'conditions' => $conditions,
            'recursive' => -1,
            'contain' => array('Event', 'Object'),
            'order' => array('Attribute.id ASC'),
        ));
        return empty($row['Attribute']) ? array() : $row['Attribute'];
    }

    /**
     * What came back, as a shape rather than as content.
     */
    private function describe($result)
    {
        if ($result === false) {
            return 'FAILED (false — unreachable or exception)';
        }
        if (!is_array($result)) {
            return 'ERROR string: ' . $result;
        }
        if (isset($result['error'])) {
            return 'module error: ' . $result['error'];
        }
        if (!isset($result['results'])) {
            return 'no results key: ' . implode(',', array_keys($result));
        }
        $results = $result['results'];
        if (isset($results['Attribute']) || isset($results['Object'])) {
            return sprintf(
                'misp_standard: %d attributes, %d objects',
                isset($results['Attribute'])
                    ? count($results['Attribute']) : 0,
                isset($results['Object'])
                    ? count($results['Object']) : 0
            );
        }
        if (empty($results)) {
            return 'SILENT — answered with nothing';
        }
        $elements = 0;
        $types = array();
        foreach ($results as $entry) {
            if (!isset($entry['values'])) {
                continue;
            }
            $elements += is_array($entry['values'])
                ? count($entry['values']) : 1;
            if (isset($entry['types'])) {
                foreach ((array)$entry['types'] as $t) {
                    $types[$t] = true;
                }
            }
        }
        return sprintf(
            'simplified: %d entries, %d elements, types %s',
            count($results),
            $elements,
            implode(',', array_keys($types))
        );
    }
}
