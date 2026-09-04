-- Value Profile — phase 25, the survey behind §3, §5.2, §10 and §11.
--
-- Every number in `25-timeline.md` that describes the instance rather
-- than the code comes from one of the blocks below, and each block names
-- the section it feeds. Re-run this before trusting any of them: the
-- instance is a working dev box, and phase 23 alone added 101 sightings
-- to it.
--
-- Not part of the application, and not a seeder — it only reads. To run:
--
--   docker exec -i misp-docker-25-db-1 mysql -umisp -pexample misp -t \
--     < prd/value-profile-live/25-timeline-probe.sql
--
-- The password is the dev container's; read it out of
-- `/var/www/MISP/app/Config/database.php` if it has moved.
--
-- One warning that is the phase's central finding: **every per-value
-- block here matches `value1` OR `value2`.** Matching only `value1` is
-- what makes `443` look like a 395-occurrence value with 403 audit rows
-- instead of a 48,255-occurrence value with 162,539 (§3.3). The seam
-- matches both sides, so a probe that does not is measuring a different
-- value than the page will.

SELECT '=== §3.1  the audit log is on ===' AS x;
SELECT COUNT(*) rows_, MIN(created) first_c, MAX(created) last_c,
       COUNT(DISTINCT model) models
FROM audit_logs;
SELECT model, COUNT(*) n FROM audit_logs
GROUP BY model ORDER BY n DESC LIMIT 5;
-- `MISP.log_new_audit` itself is not in `admin_settings`; it is in the
-- container's app/Config/config.php, `true` on this instance.

SELECT '=== §3.2  spans are common, and not where the sightings are ===' AS x;
SELECT COUNT(*) live_attributes,
       SUM(first_seen IS NOT NULL) with_first_seen
FROM attributes WHERE deleted = 0;
SELECT SUM(first_seen <> last_seen) real_spans,
       SUM(first_seen = last_seen) instants,
       SUM(last_seen IS NULL) open_ended
FROM attributes WHERE deleted = 0 AND first_seen IS NOT NULL;

SELECT '--- values that carry spans (the seen lane needs one of these) ---' AS x;
SELECT value1, COUNT(*) occ,
       SUM(first_seen <> last_seen) real_spans,
       SUM(first_seen = last_seen) instants,
       COUNT(DISTINCT event_id) evts
FROM attributes
WHERE deleted = 0 AND first_seen IS NOT NULL
GROUP BY value1 HAVING real_spans > 0 ORDER BY real_spans DESC LIMIT 8;

SELECT '--- sightings by value (the chart needs one of these) ---' AS x;
SELECT a.value1, COUNT(*) n, COUNT(DISTINCT s.org_id) orgs,
       COUNT(DISTINCT s.type) types,
       MIN(FROM_UNIXTIME(s.date_sighting)) first_s,
       MAX(FROM_UNIXTIME(s.date_sighting)) last_s
FROM sightings s JOIN attributes a ON a.id = s.attribute_id
GROUP BY a.value1 ORDER BY n DESC LIMIT 8;
-- The two lists do not intersect. That is §3.2, and it is why §14 names
-- six verification values rather than one.

SELECT '=== §3.3  the composite side sets the ceiling ===' AS x;
SELECT SUM(value1 = '443') as_value1,
       SUM(value2 = '443') as_value2,
       SUM(deleted = 0 AND (value1 = '443' OR value2 = '443')) live_total
FROM attributes;
SELECT 'value1 side' side, COUNT(*) audit_rows FROM audit_logs
WHERE model = 'Attribute'
  AND model_id IN (SELECT id FROM attributes WHERE value1 = '443')
UNION ALL
SELECT 'value2 side', COUNT(*) FROM audit_logs
WHERE model = 'Attribute'
  AND model_id IN (SELECT id FROM attributes WHERE value2 = '443');

SELECT '=== §5.2  what each audit ACL model costs ===' AS x;
SELECT '--- id-scoped: attribute rows, by value ---' AS x;
SELECT a.value1, COUNT(DISTINCT a.id) occ, COUNT(al.id) audit_rows
FROM attributes a
LEFT JOIN audit_logs al ON al.model = 'Attribute' AND al.model_id = a.id
WHERE a.value1 IN ('8.8.8.8','1.1.1.1','2.2.2.2','github.com',
                   '193.161.193.99','143.14.244.37','45.155.205.233',
                   '213.226.123.172')
GROUP BY a.value1 ORDER BY audit_rows DESC;

SELECT '--- id-scoped: event rows for one value''s events ---' AS x;
SELECT action, COUNT(*) n FROM audit_logs
WHERE model = 'Event'
  AND event_id IN (SELECT DISTINCT event_id FROM attributes WHERE value1 = '8.8.8.8')
GROUP BY action ORDER BY n DESC;

SELECT '--- per-event model, unscoped by model: what it would read ---' AS x;
SELECT COUNT(*) all_rows_for_those_events FROM audit_logs
WHERE event_id IN (SELECT DISTINCT event_id
                   FROM attributes WHERE value1 = '193.161.193.99');
-- 204 events, 816,041 rows, to reach the 1,007 that concern the value —
-- and 204 fetchEvent() calls before the read, per §8.2.

SELECT '--- per-user model: whose rows are they? ---' AS x;
SELECT o.name, COUNT(*) n
FROM audit_logs al JOIN organisations o ON o.id = al.org_id
WHERE al.model = 'Attribute'
  AND al.model_id IN (SELECT id FROM attributes WHERE value1 = '8.8.8.8')
GROUP BY o.name;
-- One organisation. Under __applyAuditAcl every other reader on the
-- instance sees an empty tab, which is §5.2's first row.

SELECT '=== §8  analyst data is on events, not on occurrences ===' AS x;
SELECT 'notes' k, object_type, COUNT(*) n FROM notes GROUP BY object_type
UNION ALL
SELECT 'opinions', object_type, COUNT(*) FROM opinions GROUP BY object_type;
-- `Event1556` is a real row and not a typo in this file: one note carries
-- an object_type that is not a type. §8's last paragraph.

SELECT '--- notes on the value''s own occurrences ---' AS x;
SELECT a.value1, COUNT(*) n
FROM notes n JOIN attributes a ON a.uuid = n.object_uuid
WHERE n.object_type = 'Attribute' GROUP BY a.value1;

SELECT '--- notes on the value''s events ---' AS x;
SELECT a.value1, COUNT(DISTINCT n.id) notes
FROM notes n
JOIN events e ON e.uuid = n.object_uuid
JOIN attributes a ON a.event_id = e.id
WHERE n.object_type = 'Event'
  AND a.value1 IN ('8.8.8.8','1.1.1.1','2.2.2.2','443','google.com')
GROUP BY a.value1;

SELECT '=== §10  the two lanes the coverage survey owes ===' AS x;
SELECT COUNT(*) proposals, SUM(timestamp = 0) epoch_zero,
       SUM(old_id = 0) standalone, SUM(deleted = 1) deleted_
FROM shadow_attributes;
SELECT COUNT(*) reports, SUM(timestamp = 0) epoch_zero,
       SUM(deleted = 1) deleted_,
       MIN(FROM_UNIXTIME(NULLIF(timestamp,0))) first_t,
       MAX(FROM_UNIXTIME(timestamp)) last_t
FROM event_reports;
SELECT a.value1, COUNT(DISTINCT er.id) reports
FROM event_reports er
JOIN attributes a ON a.event_id = er.event_id
WHERE er.deleted = 0
  AND a.value1 IN ('8.8.8.8','1.1.1.1','2.2.2.2','google.com')
GROUP BY a.value1 ORDER BY reports DESC;

SELECT '=== §11  publications, and the tag bound ===' AS x;
SELECT COUNT(*) events, SUM(published = 1) published,
       SUM(publish_timestamp > 0) with_publish_ts,
       SUM(first_publication > 0) with_first_publication
FROM events;
SELECT '--- an event with a first publication and no current one ---' AS x;
SELECT id, published, publish_timestamp, first_publication
FROM events
WHERE publish_timestamp = 0 AND first_publication > 0 LIMIT 3;

SELECT '--- how many chips the undated strip would draw ---' AS x;
SELECT a.value1, COUNT(*) attribute_tag_rows
FROM attribute_tags at JOIN attributes a ON a.id = at.attribute_id
WHERE a.value1 IN ('8.8.8.8','193.161.193.99','1.1.1.1','45.155.205.233')
GROUP BY a.value1 ORDER BY attribute_tag_rows DESC;

SELECT '=== §15  the deferred passive-dns lane has data ===' AS x;
SELECT COUNT(DISTINCT o.id) passive_dns_objects
FROM objects o WHERE o.name = 'passive-dns' AND o.deleted = 0;
SELECT a.object_relation, COUNT(*) n
FROM attributes a JOIN objects o ON o.id = a.object_id
WHERE o.name = 'passive-dns' AND a.deleted = 0
  AND a.object_relation IN ('time_first','time_last')
GROUP BY a.object_relation;
