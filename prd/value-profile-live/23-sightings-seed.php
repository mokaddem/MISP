<?php

/**
 * Scratch shell: seed sightings for Value Profile phase 23 verification.
 *
 * The instance holds 11 sightings, all type 0, no sources, three orgs —
 * far less than the Sightings tab's fixture depicts, so nothing on the
 * tab could be judged against real data. This writes a set shaped to
 * exercise the tab rather than to look plausible: several
 * organisations per value, all three sighting types, sources on a
 * minority of rows, and a time spread that makes the 90-day, 365-day
 * and all-time spans three different charts.
 *
 * Everything goes through Sighting::saveSightings, so the rows are
 * written the way MISP writes them — uuid, validation, afterSave.
 *
 * **Not part of the application.** It lives beside the phase document
 * that explains it rather than under `app/Console/Command/`, because
 * §14.8 of the contract declines to build a seeder into MISP and this is
 * verification scaffolding, not a feature. To run it, copy it into
 * `app/Console/Command/` for the duration:
 *
 *   cp prd/value-profile-live/23-sightings-seed.php \
 *      app/Console/Command/ValueSightingSeedShell.php
 *   app/Console/cake ValueSightingSeed models   # load MISP's own models
 *   app/Console/cake ValueSightingSeed run      # write the reports
 *   app/Console/cake ValueSightingSeed wipe     # undo `run`
 *   rm app/Console/Command/ValueSightingSeedShell.php
 *
 * `wipe` keeps sighting ids 1–11, which is what the instance held before
 * this ran. Re-running `run` writes a second copy; wipe first.
 */
class ValueSightingSeedShell extends AppShell
{
    public $uses = array('Sighting', 'User', 'DecayingModel');

    /**
     * Load MISP's own shipped decaying models and enable them.
     *
     * The instance has none, so nothing on the tab has a curve to draw.
     * This is the ordinary admin action — `POST /decayingModel/update`
     * has no CLI equivalent — followed by enabling them, since
     * `decaying_models.enabled` defaults to 0 and
     * `attachScoresToAttribute` filters on it.
     */
    public function models()
    {
        $user = $this->User->getAuthUser(1, true);
        $this->DecayingModel->update(false, $user);
        $this->DecayingModel->updateAll(
            array('DecayingModel.enabled' => 1),
            array('DecayingModel.default' => 1)
        );
        $models = $this->DecayingModel->find('all', array('recursive' => -1));
        foreach ($models as $model) {
            $this->out(sprintf(
                '%d  %-32s enabled=%d  lifetime=%d speed=%s threshold=%d',
                $model['DecayingModel']['id'],
                $model['DecayingModel']['name'],
                $model['DecayingModel']['enabled'],
                $model['DecayingModel']['parameters']['lifetime'],
                $model['DecayingModel']['parameters']['decay_speed'],
                $model['DecayingModel']['parameters']['threshold']
            ));
        }
    }

    /**
     * value => list of [date, org_id, attribute_id, type, source]
     */
    private function plan()
    {
        return array(
            // The populated case. Six reporting organisations, eight
            // occurrences, 2024-12 to two days ago — so `All time`,
            // `Last 365 days` and `Last 90 days` are three charts.
            '8.8.8.8' => array(
                array('2024-12-03 08:14', 1, 2, 0, null),
                array('2025-01-17 22:41', 9, 2, 0, null),
                array('2025-03-22 11:05', 63, 415804, 0, 'abuse.ch tracker'),
                array('2025-06-08 04:30', 9, 296465, 0, null),
                array('2025-08-14 19:22', 30, 163, 0, null),
                array('2025-09-02 09:15', 1, 163, 0, null),
                array('2025-10-11 13:47', 57, 165, 0, null),
                array('2025-11-25 07:03', 9, 296465, 0, null),
                array('2025-12-19 16:38', 63, 415804, 0, 'abuse.ch tracker'),
                array('2026-01-08 10:12', 1, 2, 1, null),
                array('2026-02-14 21:55', 30, 1867444, 0, null),
                array('2026-03-05 06:44', 9, 1867444, 0, null),
                array('2026-04-02 12:30', 91, 3858841, 0, null),
                array('2026-04-28 08:08', 1, 165, 0, null),
                array('2026-05-19 17:26', 57, 163, 1, null),
                array('2026-06-02 09:31', 9, 296465, 0, null),
                array('2026-06-02 14:12', 9, 1867444, 0, null),
                array('2026-06-05 11:48', 1, 2, 0, null),
                array('2026-06-11 08:22', 30, 163, 0, null),
                array('2026-06-11 08:59', 30, 165, 0, null),
                array('2026-06-17 22:04', 63, 415804, 0, 'abuse.ch tracker'),
                array('2026-06-24 07:15', 9, 296465, 0, null),
                array('2026-06-24 13:41', 1, 2, 0, null),
                array('2026-06-30 19:02', 57, 163, 0, null),
                array('2026-07-03 06:37', 9, 1867444, 0, 'Suricata IDS'),
                array('2026-07-03 06:39', 9, 296465, 0, 'Suricata IDS'),
                array('2026-07-08 15:20', 91, 3858841, 0, null),
                array('2026-07-08 15:55', 91, 2205011, 0, null),
                array('2026-07-14 10:03', 1, 165, 2, null),
                array('2026-07-16 08:44', 30, 163, 0, null),
                array('2026-07-21 12:19', 9, 296465, 0, null),
                array('2026-07-21 12:33', 9, 1867444, 0, null),
                array('2026-07-26 20:11', 63, 415804, 1, null),
                array('2026-07-29 09:07', 1, 2, 0, null),
                array('2026-08-01 07:52', 57, 163, 0, 'internal honeypot'),
                array('2026-08-04 14:26', 9, 296465, 0, null),
                array('2026-08-06 11:11', 30, 165, 0, null),
                array('2026-08-09 16:48', 1, 2, 0, null),
                array('2026-08-11 08:35', 9, 1867444, 0, null),
                array('2026-08-11 09:02', 9, 296465, 0, null),
                array('2026-08-13 21:27', 63, 415804, 0, null),
                array('2026-08-15 06:19', 91, 3858841, 0, null),
                array('2026-08-17 13:53', 1, 2, 0, null),
                array('2026-08-18 10:41', 30, 163, 0, null),
                array('2026-08-19 07:28', 9, 296465, 0, 'Suricata IDS'),
                array('2026-08-20 18:04', 57, 165, 2, null),
                array('2026-08-21 09:16', 1, 2, 1, null),
                array('2026-08-22 15:39', 9, 1867444, 0, null),
                array('2026-08-23 08:02', 30, 163, 0, null),
                array('2026-08-24 11:47', 63, 415804, 0, null),
                array('2026-08-25 07:33', 9, 296465, 0, null),
                array('2026-08-25 19:58', 1, 2, 0, null),
            ),
            // The viewer-scoping case, which is the value phase 22 used
            // for the same purpose. Four of its seven occurrences sit on
            // ADMIN-owned events and three do not, so under the default
            // sighting policy a non-admin ADMIN user sees a strict
            // subset of what a site admin sees.
            '2.2.2.2' => array(
                array('2026-04-19 08:31', 9, 153, 0, null),
                array('2026-05-02 13:12', 1, 216, 0, null),
                array('2026-05-16 09:47', 61, 326108, 0, null),
                array('2026-05-28 17:22', 27, 6552, 0, null),
                array('2026-06-03 07:15', 9, 153, 0, null),
                array('2026-06-09 11:38', 1, 226, 0, null),
                array('2026-06-14 20:04', 8, 104, 0, null),
                array('2026-06-20 06:51', 9, 216, 0, null),
                array('2026-06-27 14:29', 61, 326108, 1, null),
                array('2026-07-01 09:08', 1, 153, 0, null),
                array('2026-07-05 16:44', 27, 6552, 0, null),
                array('2026-07-09 08:17', 9, 1495259, 0, null),
                array('2026-07-13 12:52', 1, 216, 0, null),
                array('2026-07-18 07:39', 9, 153, 0, 'Zeek sensor'),
                array('2026-07-22 19:11', 61, 326108, 0, null),
                array('2026-07-26 10:26', 8, 104, 0, null),
                array('2026-07-30 13:03', 1, 226, 2, null),
                array('2026-08-03 08:58', 9, 153, 0, null),
                array('2026-08-07 15:34', 27, 6552, 0, null),
                array('2026-08-10 09:21', 1, 216, 0, null),
                array('2026-08-13 18:07', 9, 1495259, 0, 'Zeek sensor'),
                array('2026-08-16 07:44', 61, 326108, 1, null),
                array('2026-08-19 11:29', 9, 153, 0, null),
                array('2026-08-22 14:16', 1, 153, 0, null),
                array('2026-08-25 08:03', 9, 216, 0, null),
                array('2026-08-26 17:41', 1, 226, 0, null),
            ),
            // The sparse case: fourteen reports over 497 days, which is
            // what the range control exists for. Opening this on 90
            // days shows two of the fourteen.
            '1.1.1.1' => array(
                array('2025-04-12 09:22', 9, 135896, 0, null),
                array('2025-05-30 14:08', 1, 129, 0, null),
                array('2025-07-19 07:41', 9, 135896, 0, null),
                array('2025-09-05 18:33', 63, 1553909, 0, null),
                array('2025-10-23 11:17', 9, 1867472, 0, null),
                array('2025-12-11 08:04', 1, 129, 1, null),
                array('2026-01-28 16:52', 9, 135896, 0, null),
                array('2026-03-16 07:29', 63, 1553909, 0, null),
                array('2026-04-30 13:45', 9, 1867472, 0, null),
                array('2026-06-05 09:11', 1, 129, 0, null),
                array('2026-07-02 20:38', 9, 135896, 0, null),
                array('2026-07-24 06:57', 63, 1553909, 0, null),
                array('2026-08-10 15:23', 9, 1867472, 0, null),
                array('2026-08-21 10:06', 1, 129, 0, null),
            ),
            // The soft-delete case. Two of the six sit on occurrence
            // 2827379, which is soft-deleted: the Occurrences tab shows
            // that row, and Sighting::listSightings will not.
            'github.com' => array(
                array('2026-06-18 09:14', 98, 2824987, 0, null),
                array('2026-07-02 11:37', 98, 2827379, 0, null),
                array('2026-07-19 08:52', 1, 2844013, 0, null),
                array('2026-08-04 14:21', 98, 2827379, 0, null),
                array('2026-08-14 07:45', 98, 2824987, 0, null),
                array('2026-08-23 16:09', 1, 2844013, 1, null),
            ),
            // Contradiction only: three false positives and nothing
            // else, so the `Sightings` toggle renders disabled with its
            // reason and the decay curves have nothing to reset them.
            '45.155.205.233' => array(
                array('2026-07-11 10:33', 1, 343127, 1, null),
                array('2026-08-02 15:18', 9, 343133, 1, null),
                array('2026-08-20 08:41', 1, 343127, 1, null),
            ),
        );
    }

    public function run()
    {
        $user = $this->User->getAuthUser(1, true);
        if (empty($user) || empty($user['Role']['perm_site_admin'])) {
            $this->out('User 1 is not a site admin. Aborting.');
            return;
        }
        $written = 0;
        $failed = 0;
        foreach ($this->plan() as $value => $rows) {
            $forValue = 0;
            foreach ($rows as $row) {
                list($date, $orgId, $attributeId, $type, $source) = $row;
                $result = $this->Sighting->saveSightings(
                    (string)$attributeId,
                    false,
                    strtotime($date . ' UTC'),
                    $user,
                    $type,
                    $source,
                    false,
                    false,
                    $orgId
                );
                if (is_numeric($result) && $result > 0) {
                    $written += (int)$result;
                    $forValue += (int)$result;
                } else {
                    $failed++;
                    $this->out(sprintf(
                        '  %s attr %s: %s',
                        $value,
                        $attributeId,
                        is_string($result) ? $result : 'no row written'
                    ));
                }
            }
            $this->out(sprintf('%-18s %d rows', $value, $forValue));
        }
        $this->out(sprintf('Written %d, failed %d.', $written, $failed));
    }

    /**
     * Remove what run() wrote, by attribute id and date, leaving the
     * eleven rows the instance already held.
     */
    public function wipe()
    {
        $keep = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11);
        $deleted = $this->Sighting->deleteAll(
            array('NOT' => array('Sighting.id' => $keep)),
            false
        );
        $this->out($deleted ? 'Wiped.' : 'Nothing wiped.');
    }
}
