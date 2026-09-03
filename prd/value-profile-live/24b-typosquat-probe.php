<?php

/**
 * Scratch shell: what would B10's engine cost, and what would it find?
 *
 * `24b-relationships.md` §12 asks for a permutation engine in the
 * near-match section, and §4.1 leaves a one-line slot for a fourth
 * engine — *Domain / TLD tree* — that nothing in MISP computes. Before
 * either is written the two have to be priced against live data, in
 * the shape `Value::occurrencesForAny` actually builds, because that is
 * the shape whose cost the page pays.
 *
 * One scenario per invocation, so a second reading is not measured
 * against the caches the first one warmed.
 *
 *   cake ValueTyposquatProbe gen <domain>          generation only
 *   cake ValueTyposquatProbe twist <domain>        generate + fetch
 *   cake ValueTyposquatProbe tree <domain>         parents + children
 *   cake ValueTyposquatProbe sweep <n> [seed]      n real domains
 *
 * Not part of the application. Deleted after the run.
 */
class ValueTyposquatProbeShell extends AppShell
{
    public $uses = array('User', 'Value', 'MispAttribute', 'ValueProfile');

    /** Types a domain-shaped candidate could land on. */
    const TYPES = array('domain', 'hostname', 'domain|ip');

    private static $keyboard = array(
        'q' => 'wa', 'w' => 'qeas', 'e' => 'wrsd', 'r' => 'etdf',
        't' => 'ryfg', 'y' => 'tugh', 'u' => 'yihj', 'i' => 'uojk',
        'o' => 'ipkl', 'p' => 'ol', 'a' => 'qwsz', 's' => 'awedxz',
        'd' => 'serfcx', 'f' => 'drtgvc', 'g' => 'ftyhbv', 'h' => 'gyujnb',
        'j' => 'huikmn', 'k' => 'jiolm', 'l' => 'kop', 'z' => 'asx',
        'x' => 'zsdc', 'c' => 'xdfv', 'v' => 'cfgb', 'b' => 'vghn',
        'n' => 'bhjm', 'm' => 'njk', '1' => '2q', '2' => '13w',
        '3' => '24e', '4' => '35r', '5' => '46t', '6' => '57y',
        '7' => '68u', '8' => '79i', '9' => '80o', '0' => '9p',
    );

    private static $homoglyph = array(
        'a' => array('4', '@'), 'b' => array('6', '8', 'lb'),
        'c' => array('e'), 'd' => array('cl', 'b'), 'e' => array('3', 'c'),
        'g' => array('q', '9', '6'), 'i' => array('1', 'l', 'j'),
        'l' => array('1', 'i'), 'm' => array('rn', 'nn'),
        'n' => array('m', 'r'), 'o' => array('0', 'q'), 'q' => array('g', '9'),
        'rn' => array('m'), 's' => array('5'), 'u' => array('v'),
        'v' => array('u'), 'w' => array('vv'), 'z' => array('2'),
        '0' => array('o'), '1' => array('l', 'i'), '5' => array('s'),
    );

    private static $vowels = array('a', 'e', 'i', 'o', 'u');

    private static $tlds = array(
        'com', 'net', 'org', 'info', 'biz', 'co', 'io', 'me', 'cc', 'tv',
        'online', 'site', 'xyz', 'top', 'club', 'shop', 'app', 'dev',
        'live', 'store', 'icu', 'cn', 'ru', 'de', 'uk', 'eu', 'fr', 'nl',
        'it', 'br',
    );

    public function main()
    {
        $mode = $this->args[0] ?? 'gen';
        $user = $this->User->getAuthUser(1);
        if ($mode === 'gen') {
            $this->gen($this->args[1] ?? 'github.com');
        } elseif ($mode === 'twist') {
            $this->twist($user, $this->args[1] ?? 'github.com');
        } elseif ($mode === 'tree') {
            $this->tree($user, $this->args[1] ?? 'github.com');
        } elseif ($mode === 'panel') {
            $this->panel($user, $this->args[1] ?? 'github.com');
        } elseif ($mode === 'sweep') {
            $this->sweep($user, (int)($this->args[1] ?? 20));
        } else {
            $this->out('unknown mode');
        }
    }

    /** What the near-match section costs today, before any new engine. */
    private function panel(array $user, $value)
    {
        $t = microtime(true);
        $out = $this->ValueProfile->forRelationNearMatch($user, $value,
            array('fresh' => true));
        $ms = (microtime(true) - $t) * 1000;
        $near = $out['relationships']['near'];
        $this->out(sprintf('%s: forRelationNearMatch %.1f ms', $value, $ms));
        foreach ($near['engines'] as $e) {
            $this->out(sprintf('  %-8s %-14s %d rows',
                $e['id'], $e['state'], count($e['rows'])));
        }
    }

    private function gen($domain)
    {
        $t = microtime(true);
        $sets = $this->permutations($domain);
        $ms = (microtime(true) - $t) * 1000;
        $all = array();
        foreach ($sets as $name => $set) {
            $this->out(sprintf('  %-14s %5d', $name, count($set)));
            foreach ($set as $c) {
                $all[$c] = $name;
            }
        }
        $this->out(sprintf('  %-14s %5d', 'DEDUPED', count($all)));
        $this->out(sprintf('  generation: %.2f ms', $ms));
    }

    private function twist(array $user, $domain)
    {
        $t0 = microtime(true);
        $sets = $this->permutations($domain);
        $all = array();
        foreach ($sets as $name => $set) {
            foreach ($set as $c) {
                $all[$c] = $name;
            }
        }
        $cands = array_map('strval', array_keys($all));
        $t1 = microtime(true);
        $rows = $this->Value->occurrencesForAny(
            $user,
            $cands,
            array('types' => self::TYPES)
        );
        $t2 = microtime(true);
        $hits = array();
        foreach ($rows as $row) {
            $v = $row['Attribute']['value'];
            $key = strpos($v, '|') === false ? $v : explode('|', $v)[0];
            if (!isset($all[$key])) {
                continue;
            }
            $hits[$key] = $all[$key];
        }
        $this->out(sprintf('%s: %d candidates', $domain, count($cands)));
        $this->out(sprintf('  generate: %6.1f ms', ($t1 - $t0) * 1000));
        $this->out(sprintf('  fetch:    %6.1f ms  (%d rows)',
            ($t2 - $t1) * 1000, count($rows)));
        $this->out(sprintf('  distinct look-alikes: %d', count($hits)));
        foreach ($hits as $v => $cls) {
            $this->out(sprintf('    %-40s %s', $v, $cls));
        }
    }

    private function tree(array $user, $domain)
    {
        $parts = explode('.', strtolower($domain));
        $parents = array();
        for ($i = 1; $i < count($parts); $i++) {
            $parents[] = implode('.', array_slice($parts, $i));
        }
        $t0 = microtime(true);
        $up = $this->Value->occurrencesForAny(
            $user,
            $parents,
            array('types' => self::TYPES)
        );
        $t1 = microtime(true);
        $down = $this->MispAttribute->fetchAttributesSimple($user, array(
            'conditions' => array(
                'Attribute.deleted' => 0,
                'Attribute.type' => self::TYPES,
                'Attribute.value1 LIKE' => '%.' . strtolower($domain),
            ),
            'fields' => array(
                'Attribute.id', 'Attribute.event_id', 'Attribute.value',
            ),
            'order' => array('Attribute.timestamp DESC'),
            'limit' => 200,
        ));
        $t2 = microtime(true);
        $this->out(sprintf('%s', $domain));
        $this->out(sprintf('  parents (%d: %s): %6.1f ms, %d rows',
            count($parents), implode(' ', $parents),
            ($t1 - $t0) * 1000, count($up)));
        $this->out(sprintf('  children (LIKE %%.%s): %6.1f ms, %d rows',
            $domain, ($t2 - $t1) * 1000, count($down)));
    }

    /**
     * Cost and hit rate over real domain values, not chosen ones.
     */
    private function sweep(array $user, $n)
    {
        $rows = $this->MispAttribute->find('all', array(
            'conditions' => array(
                'Attribute.deleted' => 0,
                'Attribute.type' => array('domain', 'hostname'),
            ),
            'fields' => array('DISTINCT Attribute.value1'),
            'order' => false,
            'limit' => $n * 3,
            'recursive' => -1,
        ));
        $values = array();
        foreach ($rows as $row) {
            $v = $row['Attribute']['value1'];
            if (substr_count($v, '.') >= 1 && strlen($v) < 60) {
                $values[] = $v;
            }
            if (count($values) >= $n) {
                break;
            }
        }
        $totalGen = 0;
        $totalFetch = 0;
        $withHits = 0;
        $cands = 0;
        $this->out(sprintf('%-40s %5s %7s %7s %5s',
            'value', 'cand', 'gen ms', 'fetch ms', 'hits'));
        foreach ($values as $v) {
            $t0 = microtime(true);
            $sets = $this->permutations($v);
            $all = array();
            foreach ($sets as $name => $set) {
                foreach ($set as $c) {
                    $all[$c] = $name;
                }
            }
            $list = array_map('strval', array_keys($all));
            $t1 = microtime(true);
            $found = $this->Value->occurrencesForAny(
                $user,
                $list,
                array('types' => self::TYPES)
            );
            $t2 = microtime(true);
            $hits = array();
            foreach ($found as $row) {
                $val = $row['Attribute']['value'];
                $key = strpos($val, '|') === false
                    ? $val
                    : explode('|', $val)[0];
                if (isset($all[$key])) {
                    $hits[$key] = true;
                }
            }
            $totalGen += ($t1 - $t0) * 1000;
            $totalFetch += ($t2 - $t1) * 1000;
            $cands += count($list);
            if (!empty($hits)) {
                $withHits++;
            }
            $this->out(sprintf('%-40s %5d %7.1f %8.1f %5d',
                substr($v, 0, 40), count($list), ($t1 - $t0) * 1000,
                ($t2 - $t1) * 1000, count($hits)));
        }
        $c = count($values);
        $this->out('');
        $this->out(sprintf('%d values: mean %d candidates, '
            . 'gen %.1f ms, fetch %.1f ms; %d (%.0f%%) found a look-alike',
            $c, $cands / max($c, 1), $totalGen / max($c, 1),
            $totalFetch / max($c, 1), $withHits, 100 * $withHits / max($c, 1)));
    }

    /**
     * @param string $domain
     * @return array class => candidates
     */
    private function permutations($domain)
    {
        $domain = strtolower(trim($domain));
        $parts = explode('.', $domain);
        $tld = count($parts) > 1 ? array_pop($parts) : '';
        $label = implode('.', $parts);
        $n = strlen($label);
        $out = array();

        $s = array();
        for ($i = 0; $i < $n; $i++) {
            $s[] = substr($label, 0, $i) . substr($label, $i + 1);
        }
        $out['omission'] = $s;

        $s = array();
        for ($i = 0; $i < $n - 1; $i++) {
            if ($label[$i] === $label[$i + 1]) {
                continue;
            }
            $s[] = substr($label, 0, $i) . $label[$i + 1] . $label[$i]
                . substr($label, $i + 2);
        }
        $out['transposition'] = $s;

        $s = array();
        for ($i = 0; $i < $n; $i++) {
            if (ctype_alnum($label[$i])) {
                $s[] = substr($label, 0, $i) . $label[$i] . substr($label, $i);
            }
        }
        $out['repetition'] = $s;

        $s = array();
        for ($i = 0; $i < $n; $i++) {
            $c = $label[$i];
            if (!isset(self::$keyboard[$c])) {
                continue;
            }
            foreach (str_split(self::$keyboard[$c]) as $k) {
                $s[] = substr($label, 0, $i) . $k . substr($label, $i + 1);
            }
        }
        $out['replacement'] = $s;

        $s = array();
        for ($i = 1; $i < $n - 1; $i++) {
            $c = $label[$i];
            if (!isset(self::$keyboard[$c])) {
                continue;
            }
            foreach (str_split(self::$keyboard[$c]) as $k) {
                $s[] = substr($label, 0, $i) . $k . substr($label, $i);
                $s[] = substr($label, 0, $i + 1) . $k . substr($label, $i + 1);
            }
        }
        $out['insertion'] = $s;

        $s = array();
        for ($i = 0; $i < $n; $i++) {
            $o = ord($label[$i]);
            for ($b = 0; $b < 8; $b++) {
                $ch = chr($o ^ (1 << $b));
                /* Bit 5 is ASCII's case bit, and DNS is case-insensitive
                   — so is `attributes.value1` (utf8mb3_unicode_ci). A
                   case-only flip is the value itself, and the database
                   would happily match it. */
                if (strtolower($ch) === strtolower($label[$i])) {
                    continue;
                }
                if (ctype_alnum($ch) || $ch === '-') {
                    $s[] = substr($label, 0, $i) . $ch . substr($label, $i + 1);
                }
            }
        }
        $out['bitsquat'] = $s;

        $s = array();
        foreach (self::$homoglyph as $from => $tos) {
            $len = strlen($from);
            for ($i = 0; $i + $len <= $n; $i++) {
                if (substr($label, $i, $len) !== (string)$from) {
                    continue;
                }
                foreach ($tos as $to) {
                    $s[] = substr($label, 0, $i) . $to
                        . substr($label, $i + $len);
                }
            }
        }
        $out['homoglyph'] = $s;

        $s = array();
        for ($i = 0; $i < $n; $i++) {
            if (!in_array($label[$i], self::$vowels, true)) {
                continue;
            }
            foreach (self::$vowels as $v) {
                if ($v !== $label[$i]) {
                    $s[] = substr($label, 0, $i) . $v . substr($label, $i + 1);
                }
            }
        }
        $out['vowel_swap'] = $s;

        $s = array();
        $t = array();
        for ($i = 1; $i < $n; $i++) {
            if ($label[$i] === '-' || $label[$i - 1] === '-'
                || $label[$i] === '.' || $label[$i - 1] === '.') {
                continue;
            }
            $s[] = substr($label, 0, $i) . '-' . substr($label, $i);
            $t[] = substr($label, 0, $i) . '.' . substr($label, $i);
        }
        $out['hyphenation'] = $s;
        $out['subdomain'] = $t;

        $s = array();
        foreach (array_merge(range('a', 'z'), range('0', '9')) as $c) {
            $s[] = $label . $c;
        }
        $out['addition'] = $s;

        foreach ($out as $k => $set) {
            $out[$k] = array_map(function ($l) use ($tld) {
                return $tld === '' ? $l : $l . '.' . $tld;
            }, $set);
        }

        $s = array();
        foreach (self::$tlds as $t2) {
            if ($t2 !== $tld) {
                $s[] = $label . '.' . $t2;
            }
        }
        $out['tld_swap'] = $s;

        $seen = array($domain => true);
        foreach ($out as $k => $set) {
            $keep = array();
            foreach ($set as $c) {
                if (!isset($seen[$c])) {
                    $seen[$c] = true;
                    $keep[] = $c;
                }
            }
            $out[$k] = $keep;
        }
        return $out;
    }
}
