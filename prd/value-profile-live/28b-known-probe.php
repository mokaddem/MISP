<?php

/**
 * Scratch shell: what does `Already in MISP` actually claim, and on
 * what?
 *
 * The chip fires on `count: 1`, `rrtype: A`, `SSDEEP 3::` and
 * `FileSize 4`. `28-enrichment.md` §8.1 argued the untyped probe was
 * "not in practice matching across types" — checked against
 * `mmdb_lookup` only, which is the one module whose values happen to
 * be prose. `circl_passivedns` and `hashlookup` show otherwise.
 *
 * Three things to establish before changing the rule:
 *
 *   1. **What fields a module actually sends per attribute.** If
 *      `disable_correlation` comes through, MISP's own answer to "is
 *      this value an identity" is already on the wire.
 *   2. **Which returned values are matching, and as what.** A value
 *      that matches only under a type nobody would deduplicate is the
 *      whole complaint.
 *   3. **What a type-scoped probe costs.** §8.1 rejected one on the
 *      grounds it would be "one query per distinct type"; that number
 *      is measurable rather than assumed.
 *
 * **Not part of the application.** Copy it in for the duration:
 *
 *   cp prd/value-profile-live/28b-known-probe.php \
 *      app/Console/Command/ValueKnownProbeShell.php
 *   app/Console/cake ValueKnownProbe run
 *   rm app/Console/Command/ValueKnownProbeShell.php
 *
 * Only modules that answer from a local or CIRCL service are run —
 * the same three `28-enrichment-probe.php` was willing to execute.
 */
class ValueKnownProbeShell extends AppShell
{
    public $uses = array('User', 'Value', 'Module', 'MispAttribute');

    const RUNS = array(
        array('mmdb_lookup', 'ip-dst', '8.8.8.8'),
        array('hashlookup', 'md5', 'f1d3ff8443297732862df21dc4e57262'),
        array('circl_passivedns', 'ip-dst', '8.8.8.8'),
    );

    public function run()
    {
        $user = $this->User->getAuthUser(1, true);
        if (empty($user)) {
            $this->out('no user 1');
            return;
        }
        $this->out('non-correlating types: ' . implode(
            ', ',
            MispAttribute::NON_CORRELATING_TYPES
        ));
        $this->hr();

        foreach (self::RUNS as $spec) {
            list($name, $type, $value) = $spec;
            $this->out(sprintf('== %s (%s %s)', $name, $type, $value));
            $result = $this->query($user, $name, $type, $value);
            if (!is_array($result) || empty($result['results'])) {
                $this->out('  no results: ' . (is_array($result)
                    ? json_encode($result)
                    : (string)$result));
                continue;
            }
            $this->shape($user, $result['results'], $value);
            $this->hr();
        }
    }

    /**
     * The non-writing call, exactly as the tab makes it.
     */
    private function query(array $user, $name, $type, $value)
    {
        $occurrence = $this->MispAttribute->fetchAttributes($user, array(
            'conditions' => array(
                'Attribute.value1' => $value,
                'Attribute.type' => $type,
                'Attribute.deleted' => 0,
            ),
            'limit' => 1,
            'flatten' => 1,
        ));
        if (empty($occurrence)) {
            return 'no occurrence of ' . $value . ' as ' . $type;
        }
        $module = null;
        $enabled = $this->Module->getEnabledModules($user);
        foreach ($enabled['modules'] as $one) {
            if ($one['name'] === $name) {
                $module = $one;
                break;
            }
        }
        if ($module === null) {
            return 'not enabled: ' . $name;
        }
        $post = array('module' => $name);
        if (!empty($module['mispattributes']['format'])
            && $module['mispattributes']['format'] === 'misp_standard'
        ) {
            $post['attribute'] = $occurrence[0]['Attribute'];
        } else {
            $post[$type] = $value;
        }
        if (!empty($module['meta']['config'])) {
            foreach ($module['meta']['config'] as $key) {
                $post['config'][$key] = Configure::read(
                    'Plugin.Enrichment_' . $name . '_' . $key
                );
            }
        }
        return $this->Module->queryModuleServer(
            $post,
            false,
            'Enrichment',
            true,
            $occurrence[0]
        );
    }

    /**
     * Every returned attribute, with the fields it carried and whether
     * MISP already holds its value — untyped, and scoped to its type.
     */
    private function shape(array $user, array $results, $subject)
    {
        $rows = array();
        $keys = array();
        foreach (array('Attribute', 'Object') as $kind) {
            foreach (($results[$kind] ?? array()) as $entry) {
                $list = $kind === 'Object'
                    ? ($entry['Attribute'] ?? array())
                    : array($entry);
                foreach ($list as $attribute) {
                    $rows[] = $attribute;
                    foreach (array_keys($attribute) as $key) {
                        $keys[$key] = true;
                    }
                }
            }
        }
        $this->out('  returned attributes: ' . count($rows));
        $this->out('  fields seen: ' . implode(', ', array_keys($keys)));

        // One row per distinct (type, value), which is what a chip is.
        $seen = array();
        foreach ($rows as $attribute) {
            $type = $attribute['type'] ?? '?';
            $value = (string)($attribute['value'] ?? '');
            if ($value === '' || $value === (string)$subject) {
                continue;
            }
            $seen[$type . "\0" . $value] = array(
                'type' => $type,
                'value' => $value,
                'relation' => $attribute['object_relation'] ?? '',
                'disable_correlation' => array_key_exists(
                    'disable_correlation',
                    $attribute
                ) ? var_export(
                    !empty($attribute['disable_correlation']),
                    true
                ) : 'absent',
            );
        }
        $seen = array_values($seen);
        $this->out('  distinct (type, value): ' . count($seen));

        $values = array();
        $byType = array();
        foreach ($seen as $one) {
            $values[] = $one['value'];
            $byType[$one['type']][] = $one['value'];
        }
        $untyped = $this->Value->prevalenceFor($user, $values);
        $typed = array();
        $statements = 0;
        foreach ($byType as $type => $list) {
            $statements++;
            $probe = $this->Value->prevalenceFor($user, $list, array(
                'types' => array($type),
            ));
            foreach ($list as $value) {
                if (isset($probe['counts'][$value])
                    || isset($probe['capped'][$value])
                ) {
                    $typed[$type . "\0" . $value] = true;
                }
            }
        }
        $this->out(sprintf(
            '  distinct types: %d -> %d typed statements (untyped: 1)',
            count($byType),
            $statements
        ));

        $noise = 0;
        foreach ($seen as $one) {
            $u = isset($untyped['counts'][$one['value']])
                || isset($untyped['capped'][$one['value']]);
            $t = isset($typed[$one['type'] . "\0" . $one['value']]);
            $nonCorrelating = in_array(
                $one['type'],
                MispAttribute::NON_CORRELATING_TYPES,
                true
            );
            if ($u && !$t) {
                $noise++;
            }
            if (!$u && !$t) {
                continue;
            }
            $this->out(sprintf(
                '    %-16s %-12s %-28s untyped=%s typed=%s non-corr=%s'
                . ' dis-corr=%s',
                substr($one['relation'], 0, 16),
                substr($one['type'], 0, 12),
                substr($one['value'], 0, 28),
                $u ? 'Y' : 'n',
                $t ? 'Y' : 'n',
                $nonCorrelating ? 'Y' : 'n',
                $one['disable_correlation']
            ));
        }
        $this->out(sprintf(
            '  chips the untyped probe lights that a typed one would'
            . ' not: %d',
            $noise
        ));
    }
}
