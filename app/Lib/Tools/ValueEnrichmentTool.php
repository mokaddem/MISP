<?php

App::uses('ModuleLocality', 'Tools');

/**
 * The profile's enrichment declaration, met with what the instance
 * actually offers.
 *
 * Phase 7 of prd/analyst-profile/, implementing **Q10 → D15**: the
 * profile *declares* which modules matter for a type and **nothing
 * auto-runs**. The badge the original ask named needs a per-value,
 * per-module last-run store, which does not exist anywhere in MISP
 * (`Module` is `useTable = false`), and the interactive enrichment
 * path is synchronous whatever `MISP.background_jobs` says —
 * `Event::enrichmentRouter()` returns at `Event.php:7997` and strands
 * its own queued branch at `7998`. Both are named in
 * `08-enrichment.md` §1 and both belong to other documents. So what
 * ships is the declaration and its resolution, and the tab it feeds
 * arrives with the analyst's modules **ticked, not run**.
 *
 * ## The one sentence the whole mechanism reduces to
 *
 * **A profile can only ever narrow what the instance already offers,
 * and every place the two disagree is stated rather than dropped.**
 *
 * `Module::getEnabledModules()` filters on three things — the
 * instance setting `Plugin.Enrichment_<name>_enabled`, the requested
 * type, and `canUse()`, which is site-admin-always and otherwise
 * requires `Plugin.Enrichment_<name>_restrict` to be empty or to equal
 * the reader's `org_id`. A profile picks from the survivors. It cannot
 * enable a module, cannot widen a restriction, and cannot invent a
 * type. What it *can* do is name something that has since been turned
 * off, reserved for another organisation, removed from the modules
 * build, or that never accepted the type it was filed under — four
 * states, all reachable in normal use, and a profile whose stated
 * policy is not the one in effect is the class of quiet lie
 * `01-profile.md` §1.3 forbids. So each one is a condition with an id
 * and a sentence.
 *
 * ## Keyed by attribute type, resolved as a union
 *
 * `auto_run` is keyed by attribute type because module validity is
 * type-scoped: `getEnabledModules($user, $type)` filters on
 * `meta.module-type` and the tab's own header names the type for
 * exactly this reason. A value is several types — `8.8.8.8` is four on
 * the dev instance — so the declaration resolves to the **union over
 * the types the reader actually holds an occurrence of**,
 * deduplicated, and a module declared under two of them is one
 * selection carrying both.
 *
 * The type a selection would run under is the **declared** one where
 * the module accepts it, not the value's most common: the analyst
 * filing `virustotal` under `ip-dst` said which question they wanted
 * asked. It falls back to the row's own default type when the
 * declaration cannot be honoured.
 *
 * ## The posture: locality, not cost
 *
 * `locality_posture` is `local_only` (the shipped default) or
 * `allow_external`. `local_only` withholds any module that would tell
 * somebody outside the instance that this value is being looked at —
 * `ModuleLocality` decides which, and treats *unknown* as *outside*, so
 * an incomplete map fails towards not sending.
 *
 * **It was called `cost_posture` and it never gated cost.** Nothing in
 * module introspection says anything about money or rate limits, in
 * MISP or in misp-modules, so there was no cost to gate; the one thing
 * the setting does is decide whether a non-local module is withheld.
 * The old key is still read, so a fork that carries it keeps its
 * setting.
 *
 * **`ask` is gone.** It was byte-identical to `allow_external` — under
 * D15 nothing runs without a press, so a page where every run needs a
 * press is already asking — and a three-option select where two options
 * behave the same is its own defect. A stored `ask` reads as
 * `allow_external`, which is what it did.
 *
 * ## `max_age_hours` is carried and inert
 *
 * It is the reuse window — how stale a cached result may be before the
 * module is asked again — and there is no cache, so it governs
 * nothing. Carried through `planFor()` with `reuse_inert` beside it so
 * that the editor can say so, rather than dropped and silently
 * reappearing with a different meaning when the store lands.
 *
 * No `$user`, no model, no view: the arithmetic and the vocabulary,
 * nothing else, so the tab, phase 8's editor and anything later read
 * the same answer.
 */
class ValueEnrichmentTool
{
    /** Auto-run nothing that leaves the instance. The default. */
    const POSTURE_LOCAL = 'local_only';

    /** Offer whatever is declared, wherever it answers from. */
    const POSTURE_EXTERNAL = 'allow_external';

    /**
     * Retired. It behaved exactly as `POSTURE_EXTERNAL` and is read as
     * that, so a fork that stored it keeps working. Not offered.
     */
    const POSTURE_ASK_LEGACY = 'ask';

    /** The key this setting had while it claimed to be about cost. */
    const POSTURE_KEY_LEGACY = 'cost_posture';

    /**
     * The only defensible default for a setting one person can apply
     * to a whole organisation.
     */
    const DEFAULT_POSTURE = self::POSTURE_LOCAL;

    /** The reuse window a store would honour. */
    const DEFAULT_MAX_AGE_HOURS = 24;

    /**
     * The stated conditions, one id per way a declaration and an
     * instance can disagree. Ids rather than sentences because the
     * editor (phase 8) states the same conditions in a different place
     * and must not paraphrase them differently.
     */
    const C_SERVICE = 'service.unreachable';
    const C_NOT_OFFERED = 'module.not_offered';
    const C_DISABLED = 'module.disabled';
    const C_RESTRICTED = 'module.restricted';
    const C_TYPE_MISMATCH = 'module.type_mismatch';
    const C_UNRESOLVED = 'module.unresolved';
    const C_TYPE_UNUSED = 'type.unused';
    const C_POSTURE = 'posture.external';

    /**
     * The `enrichment` section, whichever shape the profile arrived
     * in.
     *
     * `ValueTrustTool::section()`'s twin, duplicated for the reason
     * that one is: this class is pure and a caller may hand over the
     * parameters alone.
     *
     * @param array|null $profile An `AnalystProfile` row, unwrapped,
     *                            or its `parameters`
     * @return array
     */
    public static function section($profile)
    {
        if (!is_array($profile)) {
            return array();
        }
        if (isset($profile['AnalystProfile'])) {
            $profile = $profile['AnalystProfile'];
        }
        if (isset($profile['parameters'])
            && is_array($profile['parameters'])
        ) {
            $profile = $profile['parameters'];
        }
        return isset($profile['enrichment'])
            && is_array($profile['enrichment'])
            ? $profile['enrichment']
            : array();
    }

    /**
     * The declaration, normalised once.
     *
     * Everything a hand-edited JSON document can get wrong is absorbed
     * here and nowhere else: a type key holding a bare string rather
     * than a list, a null, a name repeated, whitespace around a name,
     * a posture that is not one of the three. None of them is an
     * error — a profile is data an analyst edits with a text editor,
     * and the failure mode of strictness is a page that will not
     * render.
     *
     * A module name is matched **exactly** downstream, because
     * `Plugin.Enrichment_<name>_enabled` is exact and a helpfully
     * corrected name would be this class quietly enabling something
     * the profile did not name. A near-miss becomes a stated condition
     * instead (`C_NOT_OFFERED`).
     *
     * @param array|null $profile
     * @return array
     */
    public static function planFor($profile)
    {
        $section = self::section($profile);
        $autoRun = array();
        $declared = array();
        $raw = isset($section['auto_run']) && is_array($section['auto_run'])
            ? $section['auto_run']
            : array();
        foreach ($raw as $type => $names) {
            $type = trim((string)$type);
            if ($type === '') {
                continue;
            }
            if (is_string($names)) {
                $names = array($names);
            }
            if (!is_array($names)) {
                continue;
            }
            $clean = array();
            foreach ($names as $name) {
                if (!is_string($name) && !is_numeric($name)) {
                    continue;
                }
                $name = trim((string)$name);
                if ($name === '' || in_array($name, $clean, true)) {
                    continue;
                }
                $clean[] = $name;
                if (!isset($declared[$name])) {
                    $declared[$name] = array();
                }
                $declared[$name][] = $type;
            }
            $autoRun[$type] = $clean;
        }
        $locality = isset($section['locality'])
            && is_array($section['locality'])
            ? $section['locality']
            : array();
        return array(
            'auto_run' => $autoRun,
            'declared' => $declared,
            'posture' => self::posture($section),
            'locality' => $locality,
            'max_age_hours' => self::maxAgeHours($section),
            /*
             * There is no cache, so the window governs nothing. Said
             * here rather than left for a reader to discover.
             */
            'reuse_inert' => true,
            /*
             * The switch, and the same one `ValueTrustTool` uses: an
             * empty declaration takes the mechanism out of the path
             * entirely rather than resolving to an empty answer. A
             * profile that names no module produces no selection and
             * **no conditions** — `01-profile.md` §1.3's "empty means
             * as before", which for this section means a tab
             * byte-identical to the one phase 28 shipped.
             */
            'in_force' => !empty($declared),
        );
    }

    /**
     * @param array $section
     * @return string
     */
    private static function posture(array $section)
    {
        $posture = null;
        if (isset($section['locality_posture'])) {
            $posture = $section['locality_posture'];
        } elseif (isset($section[self::POSTURE_KEY_LEGACY])) {
            // A fork carries the old key; `updateDefaults()` never
            // touches a fork, so reading it is the whole migration.
            $posture = $section[self::POSTURE_KEY_LEGACY];
        }
        if ($posture === self::POSTURE_ASK_LEGACY) {
            $posture = self::POSTURE_EXTERNAL;
        }
        return in_array($posture, self::postures(), true)
            ? $posture
            : self::DEFAULT_POSTURE;
    }

    /**
     * The two that differ. `ask` is not here: see the class note.
     *
     * @return array
     */
    public static function postures()
    {
        return array(
            self::POSTURE_LOCAL,
            self::POSTURE_EXTERNAL,
        );
    }

    /**
     * @param array $section
     * @return int
     */
    private static function maxAgeHours(array $section)
    {
        if (!isset($section['max_age_hours'])
            || !is_numeric($section['max_age_hours'])
        ) {
            return self::DEFAULT_MAX_AGE_HOURS;
        }
        $hours = (int)$section['max_age_hours'];
        return $hours > 0 ? $hours : self::DEFAULT_MAX_AGE_HOURS;
    }

    /**
     * The declared modules that apply to the types this reader holds,
     * each with the declaring types in the value's own order.
     *
     * The union of §2's *"a value with several types resolves the
     * union, deduplicated"*. Ordered by the value's types rather than
     * by the profile's keys, so the type a run would use is the one
     * the reader's own occurrences make most likely to be meaningful
     * when several were declared.
     *
     * @param array $plan From planFor
     * @param array $types The value's types: `typesFor` rows or plain
     *                     type strings
     * @return array name => list of types
     */
    public static function declaredFor(array $plan, array $types)
    {
        $held = self::typeNames($types);
        $out = array();
        foreach ($held as $type) {
            if (empty($plan['auto_run'][$type])) {
                continue;
            }
            foreach ($plan['auto_run'][$type] as $name) {
                if (!isset($out[$name])) {
                    $out[$name] = array();
                }
                if (!in_array($type, $out[$name], true)) {
                    $out[$name][] = $type;
                }
            }
        }
        ksort($out);
        return $out;
    }

    /**
     * Whether the caller has to go and ask the modules service for the
     * full list before `resolve()` can explain itself.
     *
     * The catalogue the tab already builds is the **enabled and usable
     * and type-matching** set, so a declared module missing from it is
     * missing for one of four reasons and the difference is not in
     * hand. Finding out costs one more local `GET /modules` — 9 ms on
     * the dev instance — and this method exists so that it is paid
     * **only when a condition needs explaining**, which on the shipped
     * default (nothing declared) is never.
     *
     * @param array $plan From planFor
     * @param array $types The value's types
     * @param array $eligible The catalogue's module rows
     * @return array The declared names that need explaining
     */
    public static function needsFacts(array $plan, array $types,
        array $eligible
    ) {
        if (empty($plan['in_force'])) {
            return array();
        }
        $have = array();
        foreach ($eligible as $row) {
            if (isset($row['name'])) {
                $have[$row['name']] = true;
            }
        }
        $missing = array();
        foreach (self::declaredFor($plan, $types) as $name => $ignored) {
            if (!isset($have[$name])) {
                $missing[] = $name;
            }
        }
        return $missing;
    }

    /**
     * The declaration met with the instance: what to tick, what is
     * withheld, and every place the two disagree.
     *
     * @param array $plan From planFor
     * @param array $facts `service.reachable`, `types`, `eligible`
     *                     (the catalogue's rows), and — only when
     *                     `needsFacts()` asked for them — `modules`
     *                     (name => `present`, `enabled`, `restricted`,
     *                     `restrict_org`, `accepts`) and `offered`
     *                     (every name the service listed)
     * @return array
     */
    public static function resolve(array $plan, array $facts)
    {
        $out = array(
            'posture' => $plan['posture'],
            'in_force' => !empty($plan['in_force']),
            'max_age_hours' => $plan['max_age_hours'],
            'reuse_inert' => true,
            'declared' => 0,
            'applicable' => 0,
            'selected' => array(),
            'withheld' => array(),
            'conditions' => array(),
        );
        if (empty($plan['in_force'])) {
            return $out;
        }
        $out['declared'] = count($plan['declared']);

        $types = self::typeNames(
            isset($facts['types']) ? $facts['types'] : array()
        );
        $out['conditions'] = self::unusedTypes($plan, $types);

        $declared = self::declaredFor($plan, $types);
        $out['applicable'] = count($declared);
        if (empty($declared)) {
            return $out;
        }

        $reachable = !isset($facts['service']['reachable'])
            || !empty($facts['service']['reachable']);
        if (!$reachable) {
            /*
             * One condition, not one per module. Nothing about the
             * declaration is knowable this visit — which is a fact
             * about the service and not about the modules, the same
             * distinction the tab's own empty state makes.
             */
            $out['conditions'][] = self::condition(
                self::C_SERVICE,
                null,
                array_keys($declared),
                __(
                    'The modules service did not answer, so none of'
                    . ' these could be checked against what this'
                    . ' instance offers. Nothing has been selected and'
                    . ' nothing has been sent anywhere.'
                )
            );
            return $out;
        }

        $eligible = array();
        foreach (self::rows($facts, 'eligible') as $row) {
            if (isset($row['name'])) {
                $eligible[$row['name']] = $row;
            }
        }
        $moduleFacts = isset($facts['modules'])
            && is_array($facts['modules'])
            ? $facts['modules']
            : array();
        $offered = isset($facts['offered']) && is_array($facts['offered'])
            ? $facts['offered']
            : array();

        foreach ($declared as $name => $decTypes) {
            if (!isset($eligible[$name])) {
                $out['conditions'][] = self::explain(
                    $name,
                    $decTypes,
                    isset($moduleFacts[$name])
                        ? $moduleFacts[$name]
                        : null,
                    $offered
                );
                continue;
            }
            $locality = self::localityOf(
                $eligible[$name],
                $name,
                $plan['locality']
            );
            $entry = array(
                'name' => $name,
                'type' => self::runType($eligible[$name], $decTypes),
                'declared_for' => $decTypes,
                'locality' => $locality['locality'],
                'locality_source' => $locality['source'],
            );
            if ($plan['posture'] === self::POSTURE_LOCAL
                && $locality['locality'] !== ModuleLocality::LOCAL
            ) {
                $out['withheld'][] = $entry;
                $out['conditions'][] = self::condition(
                    self::C_POSTURE,
                    $name,
                    $decTypes,
                    self::postureNote($name, $locality)
                );
                continue;
            }
            $out['selected'][] = $entry;
        }
        return $out;
    }

    /**
     * The types a profile declares for that this reader holds no
     * occurrence of.
     *
     * One condition listing all of them rather than one each: a
     * profile written for `ip-src`, `ip-dst`, `domain` and `md5` will
     * have three unused types on most values, and three sentences
     * saying the same thing is noise where one is information. It is
     * stated at all because `01-profile.md` §1.3 names this exact case
     * — *"a TTL for a type the value does not have"* — as a condition
     * rather than a quiet omission.
     *
     * @param array $plan
     * @param array $types The value's type names
     * @return array
     */
    private static function unusedTypes(array $plan, array $types)
    {
        $unused = array();
        foreach ($plan['auto_run'] as $type => $names) {
            if (empty($names) || in_array($type, $types, true)) {
                continue;
            }
            $unused[] = $type;
        }
        if (empty($unused)) {
            return array();
        }
        return array(self::condition(
            self::C_TYPE_UNUSED,
            null,
            $unused,
            sprintf(
                __n(
                    'Your profile also names modules for %s, which'
                    . ' this value is not — nothing was matched from'
                    . ' it.',
                    'Your profile also names modules for %s, which'
                    . ' this value is none of — nothing was matched'
                    . ' from them.',
                    count($unused)
                ),
                implode(', ', $unused)
            )
        ));
    }

    /**
     * Why a declared module is not on the rail.
     *
     * Four states and a fifth that should be unreachable, in the order
     * `Module::getEnabledModules()` itself applies them, so the
     * sentence a reader gets is the first thing that actually stopped
     * the module rather than the most interesting one.
     *
     * The unreachable fifth is `C_UNRESOLVED`, and it is a real branch
     * rather than a defensive one: a caller that skips `needsFacts()`
     * lands here, and a page saying *"not available, and I did not
     * find out why"* is honest where a page picking one of the four
     * reasons at random is not.
     *
     * @param string $name
     * @param array $decTypes
     * @param array|null $facts
     * @param array $offered
     * @return array
     */
    private static function explain($name, array $decTypes, $facts,
        array $offered
    ) {
        if (!is_array($facts)) {
            $near = self::nearMiss($name, $offered);
            if (!empty($offered) && !in_array($name, $offered, true)) {
                return self::notOffered($name, $decTypes, $near);
            }
            return self::condition(
                self::C_UNRESOLVED,
                $name,
                $decTypes,
                __(
                    'Your profile names this module and it is not'
                    . ' available for this value. Why was not'
                    . ' established.'
                )
            );
        }
        if (empty($facts['present'])) {
            return self::notOffered(
                $name,
                $decTypes,
                self::nearMiss($name, $offered)
            );
        }
        if (empty($facts['enabled'])) {
            return self::condition(
                self::C_DISABLED,
                $name,
                $decTypes,
                __(
                    'Your profile names this module and this instance'
                    . ' has it turned off. An administrator enables it'
                    . ' under Plugin settings; until then the'
                    . ' declaration stands and does nothing.'
                )
            );
        }
        if (!empty($facts['restricted'])) {
            return self::condition(
                self::C_RESTRICTED,
                $name,
                $decTypes,
                __(
                    'Your profile names this module and this instance'
                    . ' reserves it for one organisation, which is not'
                    . ' yours. A profile shared to an organisation'
                    . ' reaches readers this applies to and readers it'
                    . ' does not.'
                )
            );
        }
        $accepts = isset($facts['accepts']) && is_array($facts['accepts'])
            ? $facts['accepts']
            : array();
        if (!empty($accepts)) {
            return self::condition(
                self::C_TYPE_MISMATCH,
                $name,
                $decTypes,
                sprintf(
                    __(
                        'Your profile files this module under %1$s and'
                        . ' the module does not accept it. It accepts'
                        . ' %2$s.'
                    ),
                    implode(', ', $decTypes),
                    implode(', ', $accepts)
                )
            );
        }
        return self::condition(
            self::C_UNRESOLVED,
            $name,
            $decTypes,
            __(
                'Your profile names this module and it is not'
                . ' available for this value. Why was not established.'
            )
        );
    }

    /**
     * @param string $name
     * @param array $decTypes
     * @param string|null $near
     * @return array
     */
    private static function notOffered($name, array $decTypes, $near)
    {
        $note = __(
            'Your profile names this module and this instance does not'
            . ' offer one by that name — a module build without it, or'
            . ' a name that has changed.'
        );
        if ($near !== null) {
            $note .= ' ' . sprintf(
                __('It does offer %s.'),
                $near
            );
        }
        return self::condition(
            self::C_NOT_OFFERED,
            $name,
            $decTypes,
            $note
        );
    }

    /**
     * An offered module whose name differs from the declared one only
     * in case.
     *
     * Named, never substituted. `Plugin.Enrichment_<name>_enabled` is
     * an exact key, so treating `VirusTotal` as `virustotal` would be
     * this class enabling a module the profile did not name — and the
     * reader can fix a typo in one edit once they can see it.
     *
     * @param string $name
     * @param array $offered
     * @return string|null
     */
    private static function nearMiss($name, array $offered)
    {
        foreach ($offered as $candidate) {
            if (strcasecmp((string)$candidate, (string)$name) === 0
                && (string)$candidate !== (string)$name
            ) {
                return (string)$candidate;
            }
        }
        return null;
    }

    /**
     * The posture's sentence, which has to name **why** the module
     * counts as leaving the building — a reader looking at a withheld
     * `dns` is owed the fact that it resolves through Google's
     * `8.8.8.8` unless somebody configured otherwise, and a reader
     * looking at a withheld module nobody has classified is owed the
     * different fact that the classification is missing.
     *
     * @param string $name
     * @param array $locality From ModuleLocality::resolve
     * @return string
     */
    private static function postureNote($name, array $locality)
    {
        if ($locality['locality'] === ModuleLocality::UNKNOWN) {
            return __(
                'Your profile names this module and your posture is'
                . ' local only. Nothing records whether asking it'
                . ' leaves this instance, and "not known" is treated'
                . ' as "it does". Selecting it by hand still works.'
            );
        }
        return __(
            'Your profile names this module and your posture is local'
            . ' only. Asking it tells somebody outside this instance'
            . ' that this value is being looked at. Selecting it by'
            . ' hand still works.'
        );
    }

    /**
     * The row's locality, and only the map's when the row has none.
     *
     * **One fact, one producer.** The catalogue row already carries
     * this — the rail chips it and the tray counts it — and computing
     * it a second time here would be the page's oldest hazard in a new
     * place: a number in one part of the frame fed by a different code
     * path from the panel it summarises
     * (`../value-profile-page.md` §1.4). They would agree today,
     * because both would call `ModuleLocality` with the same
     * overrides, and *"they agree today"* is what that hazard sounds
     * like every time before it stops being true. So the withholding
     * decision and the chip beside it are literally the same value.
     *
     * The fall-back is for a caller with no catalogue — phase 8's
     * editor resolves a declaration against no value at all.
     *
     * @param array $row A catalogue row
     * @param string $name
     * @param array $overrides
     * @return array
     */
    private static function localityOf(array $row, $name,
        array $overrides
    ) {
        if (!empty($row['locality'])) {
            return array(
                'locality' => $row['locality'],
                'source' => isset($row['locality_source'])
                    ? $row['locality_source']
                    : ModuleLocality::SOURCE_UNKNOWN,
            );
        }
        return ModuleLocality::resolve($name, $overrides);
    }

    /**
     * The type a selection would run under: the declared one where the
     * module accepts it, else the row's own default.
     *
     * The declared type wins because it is a statement — an analyst
     * filing `virustotal` under `ip-dst` asked for that question — and
     * the row's default is `typesFor`'s most-common, which is a fact
     * about the corpus rather than about the analyst.
     *
     * @param array $row A catalogue row
     * @param array $decTypes
     * @return string|null
     */
    private static function runType(array $row, array $decTypes)
    {
        $accepts = isset($row['types']) && is_array($row['types'])
            ? $row['types']
            : array();
        foreach ($decTypes as $type) {
            if (isset($accepts[$type])) {
                return $type;
            }
        }
        return isset($row['type']) ? $row['type'] : null;
    }

    /**
     * @param string $id
     * @param string|null $module
     * @param array $subjects Types, or module names for `C_SERVICE`
     * @param string $note
     * @return array
     */
    private static function condition($id, $module, array $subjects,
        $note
    ) {
        return array(
            'id' => $id,
            'module' => $module,
            'subjects' => $subjects,
            'note' => $note,
        );
    }

    /**
     * `typesFor` rows or plain strings, in the order given.
     *
     * @param array $types
     * @return array
     */
    private static function typeNames(array $types)
    {
        $out = array();
        foreach ($types as $row) {
            if (is_array($row)) {
                $row = isset($row['type']) ? $row['type'] : null;
            }
            if (!is_string($row) && !is_numeric($row)) {
                continue;
            }
            $row = (string)$row;
            if ($row !== '' && !in_array($row, $out, true)) {
                $out[] = $row;
            }
        }
        return $out;
    }

    /**
     * @param array $facts
     * @param string $key
     * @return array
     */
    private static function rows(array $facts, $key)
    {
        return isset($facts[$key]) && is_array($facts[$key])
            ? $facts[$key]
            : array();
    }

    /**
     * Whether the reader's posture leaves anything for the tab to say
     * about spending, given what got selected.
     *
     * Used by the strip, and separate from `resolve()` because it is a
     * statement about the **selection** rather than about the
     * declaration: a `local_only` profile whose every selection is
     * local has nothing to warn about, and one with an
     * `allow_external` posture and an external selection is the case
     * where the tray's own "queries leave this instance" line is the
     * whole warning.
     *
     * @param array $resolved From resolve
     * @return int How many pre-selected modules leave the instance
     */
    public static function leavingCount(array $resolved)
    {
        $n = 0;
        foreach ($resolved['selected'] as $entry) {
            if ($entry['locality'] !== ModuleLocality::LOCAL) {
                $n++;
            }
        }
        return $n;
    }
}
