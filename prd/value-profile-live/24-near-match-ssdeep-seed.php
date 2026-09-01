<?php

/**
 * Scratch shell: seed an ssdeep family for the near-match panel.
 *
 * The CIDR engine got its ladder from
 * [`24-near-match-dated-seed.py`](24-near-match-dated-seed.py); the
 * ssdeep engine never had one. §6 of `24-relationships.md` records the
 * state it was verified in: *"the engine ran, compared against the other
 * `ssdeep` attributes the reader may see, and reported that no pair
 * cleared 40"* — which exercises the query, the threshold and the
 * *active, found nothing* wording, and never once renders a row. The
 * 1,387 `ssdeep` attributes on this instance are unrelated samples, so
 * no pair of them was ever going to clear the threshold.
 *
 * This writes a family that does, so `nearRow`'s ssdeep branch, the
 * `Matched hash` table and the `Similarity ≥` control are seen against
 * real data rather than argued about.
 *
 * **How similar hashes are made.** ssdeep is a context-triggered
 * piecewise hash: it survives contiguous divergence and collapses under
 * scattered edits. Replacing 0.2% of the lines of a 24 KB blob at
 * random already drops the score from 99 to 0 — measured, not assumed —
 * so the family here is built by sharing a *prefix*: variant N is the
 * first N% of the subject blob followed by unrelated bytes of the same
 * total length. That gives a monotonic ladder, which is what the
 * closeness bar needs to be legible:
 *
 *   shared  0.98  0.92  0.85  0.65  0.45  0.30  0.25  0.15  0.05
 *   score     99    94    88    75    60    47    44    36     0
 *
 * Total length is held constant on purpose: `ssdeep_fuzzy_compare`
 * returns 0 unless the two block sizes are within one step of each
 * other, so a shorter blob of the *same* content scores 0. One rung
 * below is exactly that case.
 *
 * **What each row is for** — six clear the threshold and seven do not,
 * and the seven are the point:
 *
 *   99, 88, 75, 60, 47, 44  the rows. Two reporters, and 47 is at
 *                           attribute distribution 0 in an ADMIN event,
 *                           so an org-9 reader sees five of the six.
 *   the subject's own hash   an *exact* match in a second event. Section
 *                            one's business; `occurrencesOfType` excludes
 *                            the value itself, so near-match must not
 *                            double-count it.
 *   94, soft-deleted         `deleted = 1`, which the query filters.
 *   36                       a real score, below the threshold of 40.
 *   0 (unrelated)            compared, scored, dropped.
 *   0 (short blob)           same content, different block size — the
 *                            comparison ssdeep declines to make.
 *   `filename|ssdeep`        would score 99, and is not listed. The
 *                            engine tests `type === 'ssdeep'` exactly,
 *                            which is `Correlation::ssdeepCorrelation`'s
 *                            own test — the panel matches MISP here.
 *
 * **A console shell, not the REST API.** The CIDR seeder had to go over
 * HTTP because only `MispAttribute::afterSave` rebuilds the Redis set
 * `misp:cidr_cache_list` that the CIDR engine reads. ssdeep has no such
 * cache: `ValueProfile::ssdeepEngine` compares live against a plain
 * indexed query, and `forRelationNearMatch` is not held in the
 * Relationships scan's five-minute Redis entry either. So a shell that
 * goes through `MispAttribute::save` — same validation, same `afterSave`
 * — is enough, and it needs no API key.
 *
 * `afterSave` still runs, so with `MISP.enable_advanced_correlations`
 * on, MISP's own engine writes these into `fuzzy_correlate_ssdeep`,
 * which §9.4 found empty. That is a side effect worth knowing about and
 * not something the panel reads.
 *
 * **Events are left unpublished on purpose.** This instance has 8 sync
 * servers configured; publishing would push the seed out to them.
 *
 * **Not part of the application.** It lives beside the phase document
 * that explains it rather than under `app/Console/Command/`, because
 * §14.8 of the contract declines to build a seeder into MISP and this is
 * verification scaffolding, not a feature. To run it, copy it into
 * `app/Console/Command/` for the duration:
 *
 *   cp prd/value-profile-live/24-near-match-ssdeep-seed.php \
 *      app/Console/Command/ValueSsdeepSeedShell.php
 *   app/Console/cake ValueSsdeepSeed run     # writes both events
 *   app/Console/cake ValueSsdeepSeed show    # the ladder, no writes
 *   app/Console/cake ValueSsdeepSeed wipe    # undoes `run`
 *   rm app/Console/Command/ValueSsdeepSeedShell.php
 *
 * `run` prints the subject hash and the base64 the page URL wants.
 * `wipe` deletes only the two events `run` created, found by their info
 * strings. Re-running `run` writes a second copy; wipe first.
 */
class ValueSsdeepSeedShell extends AppShell
{
    public $uses = array('MispAttribute', 'MispObject', 'ObjectTemplate',
        'Event', 'User');

    const SUBJECT_EVENT = 'ssdeep bench - subject sample and its repacks';
    const PARTNER_EVENT = 'ssdeep bench - the same family, another reporter';

    /** Bytes per synthetic sample. Big enough for a stable block size. */
    const BLOB_LEN = 24576;

    /** Seeds for the two independent blobs the ladder is built from. */
    const SUBJECT_SEED = 4242;
    const FOREIGN_SEED = 31337;

    /** `file`, which is the template a sample's hashes belong in. */
    const FILE_TEMPLATE = '688c46fb-5edb-40a3-8273-1af7923e2215';

    /**
     * cake ValueSsdeepSeed run
     */
    public function run()
    {
        if (!function_exists('ssdeep_fuzzy_hash')) {
            $this->err('ssdeep extension not loaded; nothing to seed.');
            return;
        }
        $admin = $this->User->getAuthUser(1);
        Configure::write('CurrentUserId', $admin['id']);

        $subject = $this->hashOf(1.0);
        $subjectEvent = $this->event(self::SUBJECT_EVENT, 1);
        $partnerEvent = $this->event(self::PARTNER_EVENT, 9);
        $this->out(sprintf('event %d  %s', $subjectEvent,
            self::SUBJECT_EVENT));
        $this->out(sprintf('event %d  %s', $partnerEvent,
            self::PARTNER_EVENT));
        $this->hr();

        foreach ($this->plan($subject) as $row) {
            $event = $row['event'] === 'subject'
                ? $subjectEvent
                : $partnerEvent;
            $id = $this->attribute($event, $row);
            if ($id === null) {
                continue;
            }
            if (!empty($row['deleted'])) {
                /*
                 * `updateAll`, not `save`: a save of two fields runs
                 * `AuditLogBehavior` and `DefaultCorrelationBehavior`
                 * over an attribute array that has neither `type` nor
                 * `value1` in it, and both of them read those keys. The
                 * row still soft-deletes, under six pages of notices.
                 */
                $this->MispAttribute->updateAll(
                    array('Attribute.deleted' => 1),
                    array('Attribute.id' => $id)
                );
            }
            $this->out(sprintf('  %-3s %-14s %-5s %s',
                $row['score'] === null ? '-' : $row['score'],
                $row['type'],
                $row['expected'],
                $row['note']
            ));
        }

        $this->fileObject($partnerEvent, $admin, $subject);

        $this->hr();
        $this->out(sprintf('subject   %s', $subject));
        $this->out(sprintf('base64    %s', base64_encode($subject)));
        $this->out(sprintf('page      /values/view/%s',
            base64_encode($subject)));
    }

    /**
     * cake ValueSsdeepSeed show
     *
     * The ladder and nothing else — the scores are measured, so a
     * changed extension version can be seen before anything is written.
     */
    public function show()
    {
        $subject = $this->hashOf(1.0);
        $this->out(sprintf('subject  %s', $subject));
        foreach (array(0.98, 0.92, 0.85, 0.65, 0.45, 0.30, 0.25, 0.15,
            0.05) as $share
        ) {
            $hash = $this->hashOf($share);
            $this->out(sprintf('  %-5s %-4s %s', $share,
                ssdeep_fuzzy_compare($subject, $hash), $hash));
        }
        $short = ssdeep_fuzzy_hash(
            $this->blob(self::SUBJECT_SEED, 2048)
        );
        $this->out(sprintf('  %-5s %-4s %s', 'short',
            ssdeep_fuzzy_compare($subject, $short), $short));
    }

    /**
     * cake ValueSsdeepSeed wipe
     */
    public function wipe()
    {
        $events = $this->Event->find('all', array(
            'recursive' => -1,
            'fields' => array('Event.id', 'Event.info'),
            'conditions' => array('Event.info' => array(
                self::SUBJECT_EVENT, self::PARTNER_EVENT,
            )),
        ));
        if (empty($events)) {
            $this->out('nothing to wipe');
            return;
        }
        foreach ($events as $event) {
            /*
             * `read` first: MISP's `Event::beforeDelete` builds an
             * `EventBlocklist` row out of `$this->data` — the event's
             * uuid, info and orgc — and `Model::delete` does not load
             * the record. Delete straight from a `find('all')` and the
             * orgc lookup dereferences null.
             */
            $this->Event->read(null, $event['Event']['id']);
            $this->Event->delete($event['Event']['id']);
            $this->out(sprintf('deleted event %d  %s',
                $event['Event']['id'], $event['Event']['info']));
        }
    }

    /**
     * The thirteen rows, and what each of them is there to prove.
     *
     * `expected` is what the near-match panel should do with the row —
     * asserted by eye against the rendered fragment, which is why it is
     * printed rather than only commented.
     *
     * @param string $subject
     * @return array
     */
    private function plan($subject)
    {
        $rows = array(
            array('share' => 1.0, 'event' => 'subject',
                'note' => 'the subject itself',
                'expected' => 'self'),
            array('share' => 0.98, 'event' => 'subject',
                'note' => 'near-identical repack'),
            array('share' => 0.85, 'event' => 'subject',
                'note' => 'same builder, later config'),
            array('share' => 0.30, 'event' => 'subject',
                'distribution' => 0,
                'note' => 'org-only: an org-9 reader loses this row',
                'expected' => 'row/admin'),
            array('share' => 1.0, 'event' => 'partner',
                'note' => 'exact match, section one\'s row not this one',
                'expected' => 'excl:exact'),
            array('share' => 0.92, 'event' => 'partner',
                'deleted' => true,
                'note' => 'soft-deleted, filtered by the query',
                'expected' => 'excl:deleted'),
            array('share' => 0.65, 'event' => 'partner',
                'note' => 'shared loader, different payload'),
            array('share' => 0.45, 'event' => 'partner',
                'note' => 'half a sample in common'),
            array('share' => 0.25, 'event' => 'partner',
                'note' => 'just over the threshold'),
            array('share' => 0.15, 'event' => 'partner',
                'note' => 'scored 36, below the threshold of 40',
                'expected' => 'excl:below'),
            array('share' => 0.05, 'event' => 'partner',
                'note' => 'unrelated sample, scores 0',
                'expected' => 'excl:zero'),
        );
        $out = array();
        foreach ($rows as $row) {
            $row['value'] = $this->hashOf($row['share']);
            $row['type'] = 'ssdeep';
            $row['category'] = 'Payload delivery';
            $out[] = $this->finish($row, $subject);
        }

        // Same content, 2 KB instead of 24 KB: ssdeep refuses to compare
        // across more than one block-size step and returns 0.
        $out[] = $this->finish(array(
            'event' => 'partner',
            'value' => ssdeep_fuzzy_hash(
                $this->blob(self::SUBJECT_SEED, 2048)
            ),
            'type' => 'ssdeep',
            'category' => 'Payload delivery',
            'note' => 'same content, smaller block size',
            'expected' => 'excl:blocksize',
        ), $subject);

        // Composite: the engine's type test is exact, and so is MISP's.
        $out[] = $this->finish(array(
            'event' => 'partner',
            'value' => 'invoice_2026.doc|' . $this->hashOf(0.98),
            'type' => 'filename|ssdeep',
            'category' => 'Payload delivery',
            'note' => 'composite type, never compared',
            'expected' => 'excl:type',
        ), $subject);

        return $out;
    }

    /**
     * Fill in the score and the default expectation.
     *
     * @param array $row
     * @param string $subject
     * @return array
     */
    private function finish(array $row, $subject)
    {
        $hash = $row['type'] === 'filename|ssdeep'
            ? explode('|', $row['value'], 2)[1]
            : $row['value'];
        $score = ssdeep_fuzzy_compare($subject, $hash);
        $row['score'] = $score === false ? null : (int)$score;
        if (!isset($row['expected'])) {
            $row['expected'] = 'row';
        }
        if (!isset($row['distribution'])) {
            $row['distribution'] = 5;
        }
        return $row;
    }

    /**
     * A `file` object whose ssdeep is a near match at 68.
     *
     * The near-match row names the record the matched value sits in, and
     * an attribute in an object is the branch that differs: it links to
     * `/objects/view` and shows the template's name, where a lone
     * attribute can only open its event's Attributes tab. Nothing else
     * in the seed sits in an object, so without this the object branch
     * has no live coverage.
     *
     * A file object is also where an ssdeep hash belongs — beside the
     * filename, the size and a sha256 that is the **real** digest of the
     * same synthetic bytes the ssdeep hash was taken from.
     *
     * @param int $event
     * @param array $user
     * @param string $subject
     * @return void
     */
    private function fileObject($event, array $user, $subject)
    {
        $template = $this->ObjectTemplate->find('first', array(
            'recursive' => -1,
            'conditions' => array(
                'ObjectTemplate.uuid' => self::FILE_TEMPLATE,
            ),
            'order' => array('ObjectTemplate.version DESC'),
        ));
        if (empty($template)) {
            $this->err('no `file` object template on this instance');
            return;
        }
        $bytes = $this->variant(0.55);
        $hash = ssdeep_fuzzy_hash($bytes);
        $fields = array(
            array('filename', 'filename', 'Payload delivery',
                'stage2.bin'),
            array('ssdeep', 'ssdeep', 'Payload delivery', $hash),
            array('sha256', 'sha256', 'Payload delivery',
                hash('sha256', $bytes)),
            array('size-in-bytes', 'size-in-bytes', 'Other',
                (string)strlen($bytes)),
        );
        /*
         * `Attribute` sits beside `Object`, not inside it.
         * `MispObject::saveObject` reads `$object['Attribute']` at the
         * top level; nest it the way `/objects/add` accepts and the
         * object saves with no attributes at all, under a page of
         * notices from the foreach that expected them.
         */
        $object = array(
            'Object' => array(
                'distribution' => 5,
                'comment' => 'ssdeep inside an object, not beside one',
            ),
            'Attribute' => array(),
        );
        foreach ($fields as $field) {
            list($relation, $type, $category, $value) = $field;
            $object['Attribute'][] = array(
                'event_id' => $event,
                'object_relation' => $relation,
                'type' => $type,
                /*
                 * Every attribute names its own category. `ObjectsController
                 * ::add` pre-validates with `Attribute->set()`, which
                 * *merges*, so an omitted category inherits the previous
                 * attribute's — the behaviour `24-near-match-dated-seed.py`
                 * documents. `saveObject` is a different path and does not
                 * merge, but a category per attribute costs nothing and
                 * survives either.
                 */
                'category' => $category,
                'value' => $value,
                'to_ids' => 0,
                'distribution' => 5,
            );
        }
        $saved = $this->MispObject->saveObject(
            $object, $event, $template, $user
        );
        if (!is_numeric($saved)) {
            $this->err(sprintf('  file object: %s', json_encode($saved)));
            return;
        }
        $this->out(sprintf('  %-3s %-14s %-5s %s',
            (int)ssdeep_fuzzy_compare($subject, $hash),
            'ssdeep/object',
            'row',
            sprintf('in file object %d — links to the object', $saved)
        ));
    }

    /**
     * One event, owned by ADMIN and credited to `$orgc`.
     *
     * Two reporters is what makes the `Reported by` column say something
     * — `nearRow` carries the creator of the *other* value, not ours.
     *
     * @param string $info
     * @param int $orgc
     * @return int
     */
    private function event($info, $orgc)
    {
        $this->Event->create();
        $saved = $this->Event->save(array('Event' => array(
            'org_id' => 1,
            'orgc_id' => $orgc,
            'user_id' => 1,
            'date' => date('Y-m-d'),
            'info' => $info,
            'distribution' => 1,
            'analysis' => 2,
            'threat_level_id' => 3,
            'published' => 0,
            'uuid' => CakeText::uuid(),
            'timestamp' => time(),
        )));
        if (!$saved) {
            $this->err(sprintf('event %s: %s', $info,
                json_encode($this->Event->validationErrors)));
            return 0;
        }
        return (int)$this->Event->id;
    }

    /**
     * @param int $event
     * @param array $row
     * @return int|null
     */
    private function attribute($event, array $row)
    {
        $this->MispAttribute->create();
        $saved = $this->MispAttribute->save(array('Attribute' => array(
            'event_id' => $event,
            'type' => $row['type'],
            'category' => $row['category'],
            'value' => $row['value'],
            'comment' => $row['note'],
            'to_ids' => 0,
            'distribution' => $row['distribution'],
        )));
        if (!$saved) {
            $this->err(sprintf('  %-14s %s', $row['type'], json_encode(
                $this->MispAttribute->validationErrors)));
            return null;
        }
        return (int)$this->MispAttribute->id;
    }

    /**
     * The hash of a variant sharing `$share` of the subject's bytes.
     *
     * @param float $share 1.0 for the subject itself
     * @return string
     */
    private function hashOf($share)
    {
        return ssdeep_fuzzy_hash($this->variant($share));
    }

    /**
     * The bytes of that variant: `$share` of the subject's, then
     * unrelated ones, at a constant total length.
     *
     * Separate from `hashOf` because the `file` object wants a sha256 of
     * the same bytes, and a digest of something else would be a lie in a
     * column nobody would check.
     *
     * @param float $share 1.0 for the subject itself
     * @return string
     */
    private function variant($share)
    {
        $len = self::BLOB_LEN;
        $base = $this->blob(self::SUBJECT_SEED, $len);
        if ($share >= 1.0) {
            return $base;
        }
        $cut = (int)($len * $share);
        return substr($base, 0, $cut)
            . substr($this->blob(self::FOREIGN_SEED, $len), 0, $len - $cut);
    }

    /**
     * A deterministic sample: key=value lines from a fixed word list.
     *
     * `mt_srand` rather than a file on disk, so the family is
     * reproducible from this shell alone and the document can quote the
     * scores. Shaped like a dropped configuration blob because the rows
     * are read as malware samples, but nothing depends on the shape.
     *
     * @param int $seed
     * @param int $len
     * @return string
     */
    private function blob($seed, $len)
    {
        mt_srand($seed);
        $words = array('config', 'beacon', 'sleep', 'jitter', 'host',
            'port', 'key', 'uri', 'agent', 'spawn', 'kill', 'inject',
            'stage', 'pipe', 'mutex', 'task', 'exfil', 'proxy',
            'watchdog', 'payload');
        $out = '';
        while (strlen($out) < $len) {
            $out .= $words[mt_rand(0, count($words) - 1)]
                . '=' . mt_rand(1000, 999999) . "\n";
        }
        return substr($out, 0, $len);
    }
}
