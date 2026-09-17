<?php

/**
 * Which widget draws which answer, and which widgets get a slot.
 *
 * The renderers say what they can draw; this says what is actually in
 * front of the reader. It takes the runs a value has in the store,
 * sorts their objects into shapes, asks each renderer whether it can
 * draw what it was handed, and returns the ones that said yes with
 * their computed data — then ranks them, because the Overview has room
 * for five and a value can easily have eight answers.
 *
 * ## Grouping is by template and nothing else
 *
 * An object goes to the renderer that claims its template. The loader
 * guarantees at most one claim per template, so the grouping cannot be
 * ambiguous and no precedence rule is needed. An object whose template
 * nobody claims goes nowhere and stays a chip, which is the honest
 * fallback rather than a defect.
 *
 * ## One widget per shape, and the newest answer takes it
 *
 * Where three modules all returned a geolocation, the strip shows one
 * map and the rest fold into chips. The alternative — one widget per
 * module — is three maps of the same address filling the row, which is
 * the roster this design refused wearing a different hat. The
 * renderers merge the answers they are given and each says in its own
 * output which sources agreed; what *newest* decides is only the
 * headline, so a re-run visibly wins.
 *
 * ## The fallback order is an opinion, and it lives here
 *
 * A profile ranks shapes and most profiles rank nothing, so a stock
 * instance needs a sensible order rather than an arbitrary one. It is
 * per attribute type, because what matters first about an address is
 * not what matters first about a hash. It is **not** in the shipped
 * default profile: that document is neutral by construction and a
 * promotion order is a preference. In code it is a shipped default
 * that any profile overrides by saying something.
 */
class ValueRendererTool
{
    /** Widgets the Overview strip has room for. */
    const STRIP_MAX = 5;

    /**
     * Type prefix => shapes, best first. Matched on the whole type
     * first and then on the part before a `|`, so that `domain|ip` and
     * `ip-src|port` follow their base type without being listed twice.
     */
    const FALLBACK = array(
        'ip-src' => array('geolocation', 'network-ownership',
            'reputation', 'port-service-set', 'resolution-timeline',
            'certificate-set', 'vulnerability', 'prefix-reputation'),
        'ip-dst' => array('geolocation', 'network-ownership',
            'reputation', 'port-service-set', 'resolution-timeline',
            'certificate-set', 'vulnerability', 'prefix-reputation'),
        'domain' => array('resolution-timeline', 'registration',
            'reputation', 'certificate-set', 'geolocation',
            'port-service-set', 'page-capture'),
        'hostname' => array('resolution-timeline', 'registration',
            'reputation', 'certificate-set', 'geolocation',
            'port-service-set', 'page-capture'),
        'url' => array('page-capture', 'reputation',
            'resolution-timeline', 'certificate-set'),
        'link' => array('page-capture', 'reputation'),
        'uri' => array('page-capture', 'reputation'),
        'md5' => array('file-verdict', 'known-good'),
        'sha1' => array('file-verdict', 'known-good'),
        'sha256' => array('file-verdict', 'known-good'),
        'sha512' => array('file-verdict', 'known-good'),
        'ssdeep' => array('known-good', 'file-verdict'),
        'tlsh' => array('known-good', 'file-verdict'),
        'filename' => array('file-verdict', 'known-good'),
        'vulnerability' => array('vulnerability'),
        'cpe' => array('vulnerability'),
        'btc' => array('crypto-flow'),
        'xmr' => array('crypto-flow'),
        'dash' => array('crypto-flow'),
        'iban' => array('bank-account'),
        'bic' => array('bank-account'),
        'cc-number' => array('payment-card'),
        'phone-number' => array('phone'),
        'whois-registrant-email' => array('registration'),
        'email' => array('account-presence'),
        'email-src' => array('account-presence'),
        'email-dst' => array('account-presence'),
        'github-username' => array('account-presence'),
        'twitter-id' => array('account-presence'),
        'x509-fingerprint-sha1' => array('certificate-set'),
        'x509-fingerprint-sha256' => array('certificate-set'),
        'x509-fingerprint-md5' => array('certificate-set'),
        'ssh-fingerprint' => array('certificate-set'),
    );

    /**
     * What a value of a type nobody listed gets, so that a type this
     * list has never heard of still produces a sensible row rather
     * than an arbitrary one.
     */
    const FALLBACK_ANY = array(
        'reputation',
        'file-verdict',
        'known-good',
        'geolocation',
        'resolution-timeline',
        'network-ownership',
    );

    /**
     * Every renderer this instance has, keyed by shape id.
     *
     * @return array id => instance
     */
    public static function renderers()
    {
        return ValueSignalLoader::classes(
            ValueSignalLoader::SUBJECT_RENDERER
        );
    }

    /**
     * The same set as the editor's rail reads it: identity only.
     *
     * @return array id => config
     */
    public static function catalogue()
    {
        return ValueSignalLoader::catalogue(
            ValueSignalLoader::SUBJECT_RENDERER
        );
    }

    /**
     * Template name => the shape that draws it.
     *
     * @return array
     */
    public static function byTemplate()
    {
        $map = array();
        foreach (self::renderers() as $id => $renderer) {
            foreach ($renderer->templates as $template) {
                $map[$template] = $id;
            }
        }
        return $map;
    }

    /**
     * Every shape these runs can draw, with its data computed.
     *
     * Runs that failed, ran silent or hold nothing are skipped here
     * rather than filtered by the caller, because *a module answered
     * and had nothing to say* produces no widget for the same reason
     * it produces no ledger row: absence of an answer is not an
     * answer.
     *
     * @param array $runs Shaped runs, each with `module` and `objects`
     * @return array shape id => the drawing
     */
    public static function drawFor(array $runs)
    {
        $renderers = self::renderers();
        $byTemplate = self::byTemplate();
        $grouped = array();
        $loose = array();
        foreach ($runs as $run) {
            if (!is_array($run)
                || ($run['state'] ?? null) !== 'ok'
            ) {
                continue;
            }
            $module = $run['module'] ?? null;
            $ranAt = self::ranAt($run);
            foreach (self::listOf($run, 'objects') as $object) {
                $name = $object['name'] ?? null;
                if ($name === null || !isset($byTemplate[$name])) {
                    continue;
                }
                $object['module'] = $module;
                $object['ran_at'] = $ranAt;
                $grouped[$byTemplate[$name]][] = $object;
            }
            /*
             * The run's own top-level attributes reach every shape its
             * objects reached. A renderer keyed on a template has no
             * business with them and every shipped one ignores them;
             * they are passed because the contract says they are, and
             * because a custom renderer for a module that answers in
             * bare attributes is a thing an instance may want.
             */
            $loose[$module] = self::listOf($run, 'attributes');
        }
        $drawn = array();
        foreach ($grouped as $id => $objects) {
            if (!isset($renderers[$id])) {
                continue;
            }
            $renderer = $renderers[$id];
            $attributes = self::attributesFor($objects, $loose);
            if (!$renderer->matches($objects, $attributes)) {
                continue;
            }
            $drawn[$id] = array(
                'shape' => $id,
                'description' => $renderer->description,
                'compact' => $renderer->compact,
                'full' => $renderer->full,
                'data' => $renderer->prepare($objects, $attributes),
                'modules' => self::modulesOf($objects),
                'ran_at' => self::newestOf($objects),
                'objects' => count($objects),
            );
        }
        return $drawn;
    }

    /**
     * The five shapes that get a slot, in the order they are drawn.
     *
     * **A shape the profile ranked keeps its slot whether or not this
     * value has an answer for it**, and that asymmetry with the
     * fallback is the whole of how a ranking differs from a default.
     * A ranking is a request — *these are the answers I want to see* —
     * so a gap in it is information, and the slot says *not asked*.
     * Skipping it and closing the row up would report four answers to
     * a reader who asked for five and got four, which is the one thing
     * a summary must not do.
     *
     * The shipped fallback is not a request but a sensible default, so
     * it contributes only shapes that actually drew. Otherwise a stock
     * instance that has simply never enriched anything would draw five
     * *not asked* boxes on every value — a permanently empty first row
     * on the Overview, which is exactly what this panel refuses to be.
     *
     * **And nothing else gets promoted.** A drawn shape that neither
     * the profile nor the fallback names stays a chip. The tempting
     * extra rule — *and then anything else, newest first, so nothing
     * is hidden* — reads as generosity and breaks the first rule the
     * whole discovery mechanism rests on: dropping a renderer into a
     * directory would then change what every reader on the instance
     * sees, with nobody having chosen it. A custom shape reaches the
     * strip the way every other preference does, by a profile saying
     * so.
     *
     * @param array $drawn From `drawFor`
     * @param array $ranked The profile's ordered shape ids
     * @param array $types The attribute types this value is
     * @return array Shape ids, at most `STRIP_MAX`
     */
    public static function promote(array $drawn, array $ranked,
        array $types
    ) {
        $catalogue = self::catalogue();
        $out = array();
        foreach ($ranked as $id) {
            if (count($out) >= self::STRIP_MAX) {
                break;
            }
            /*
             * A ranked shape this instance has no renderer for is
             * skipped rather than drawn empty. *Not asked* would be
             * untrue of it — nothing here could draw it however many
             * modules answered — and the honest report of that
             * belongs in the editor, at declaration time, rather than
             * as a permanent box on every value page.
             */
            if (!isset($drawn[$id]) && !isset($catalogue[$id])) {
                continue;
            }
            if (!in_array($id, $out, true)) {
                $out[] = $id;
            }
        }
        foreach (self::fallbackFor($types) as $id) {
            if (count($out) >= self::STRIP_MAX) {
                break;
            }
            if (isset($drawn[$id]) && !in_array($id, $out, true)) {
                $out[] = $id;
            }
        }
        return $out;
    }

    /**
     * The shipped order for these types, deduplicated.
     *
     * A value is several types at once, so the orders are read in the
     * order the types were given and merged — the first type's
     * preferences lead, and the others contribute what they add.
     *
     * @param array $types Type names, or rows carrying `type`
     * @return array
     */
    public static function fallbackFor(array $types)
    {
        $out = array();
        foreach ($types as $row) {
            $type = is_array($row) && isset($row['type'])
                ? $row['type']
                : $row;
            foreach (self::fallbackForType((string)$type) as $id) {
                if (!in_array($id, $out, true)) {
                    $out[] = $id;
                }
            }
        }
        if (empty($out)) {
            return self::FALLBACK_ANY;
        }
        return $out;
    }

    /**
     * Which shapes a profile may rank on this instance, and why not
     * where it may not.
     *
     * Three states rather than two. A shape with a renderer and a
     * producer can be ranked and will draw. A shape with a renderer
     * and no producer can be ranked and will leave an empty slot until
     * somebody upstream moves, which is a thing the reader is owed at
     * declaration time rather than at read time. A shape a profile
     * names that this instance has no renderer for cannot draw at all
     * — a custom renderer was removed, or the document came from an
     * instance that had one.
     *
     * @param array $ranked What a profile ranks
     * @return array shape id => `ok`, `no_producer` or `unknown`
     */
    public static function standingOf(array $ranked)
    {
        $catalogue = self::catalogue();
        $out = array();
        foreach ($ranked as $id) {
            if (!isset($catalogue[$id])) {
                $out[$id] = 'unknown';
                continue;
            }
            $out[$id] = $catalogue[$id]['producer']
                === ValueRendererBase::PRODUCER_TODAY
                ? 'ok'
                : 'no_producer';
        }
        return $out;
    }

    /**
     * @param string $type
     * @return array
     */
    private static function fallbackForType($type)
    {
        if (isset(self::FALLBACK[$type])) {
            return self::FALLBACK[$type];
        }
        /*
         * A composite type follows its left half: `domain|ip` is a
         * question about a domain and `ip-src|port` about an address,
         * and listing every composite separately would be the same
         * opinion written four more times.
         */
        $pipe = strpos($type, '|');
        if ($pipe !== false) {
            $base = substr($type, 0, $pipe);
            if (isset(self::FALLBACK[$base])) {
                return self::FALLBACK[$base];
            }
        }
        return array();
    }

    /**
     * The loose attributes belonging to the modules that produced
     * these objects, flattened.
     *
     * @param array $objects
     * @param array $loose module => attributes
     * @return array
     */
    private static function attributesFor(array $objects, array $loose)
    {
        $out = array();
        foreach (self::modulesOf($objects) as $module) {
            foreach ($loose[$module] ?? array() as $attribute) {
                $out[] = $attribute;
            }
        }
        return $out;
    }

    /**
     * @param array $objects
     * @return array
     */
    private static function modulesOf(array $objects)
    {
        $out = array();
        foreach ($objects as $object) {
            $module = $object['module'] ?? null;
            if ($module !== null && !in_array($module, $out, true)) {
                $out[] = $module;
            }
        }
        return $out;
    }

    /**
     * @param array $objects
     * @return int|null
     */
    private static function newestOf(array $objects)
    {
        $newest = null;
        foreach ($objects as $object) {
            $ranAt = $object['ran_at'] ?? null;
            if ($ranAt !== null
                && ($newest === null || $ranAt > $newest)
            ) {
                $newest = $ranAt;
            }
        }
        return $newest;
    }

    /**
     * When a run answered.
     *
     * A run off the wire has neither field and is answering now; one
     * out of the store carries how old it is rather than when it was,
     * because `age` is what the pane states and a stamp would have to
     * be re-derived on every read anyway.
     *
     * @param array $run
     * @return int
     */
    private static function ranAt(array $run)
    {
        if (isset($run['ran_at'])) {
            return (int)$run['ran_at'];
        }
        if (isset($run['age'])) {
            return time() - (int)$run['age'];
        }
        return time();
    }

    /**
     * @param array $run
     * @param string $key
     * @return array
     */
    private static function listOf(array $run, $key)
    {
        return isset($run[$key]) && is_array($run[$key])
            ? $run[$key]
            : array();
    }
}
