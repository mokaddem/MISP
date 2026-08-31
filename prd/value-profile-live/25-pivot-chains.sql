SELECT '--- object templates by count ---' AS x;
SELECT name, COUNT(*) AS n, COUNT(DISTINCT event_id) AS events
FROM objects GROUP BY name ORDER BY n DESC LIMIT 25;

SELECT '--- object reference relationship types ---' AS x;
SELECT relationship_type, COUNT(*) AS n
FROM object_references WHERE deleted = 0
GROUP BY relationship_type ORDER BY n DESC LIMIT 25;

SELECT '--- analyst relationship types ---' AS x;
SELECT relationship_type, COUNT(*) AS n
FROM relationships GROUP BY relationship_type ORDER BY n DESC LIMIT 15;
SELECT '--- passive-dns object_relation shape ---' AS x;
SELECT a.object_relation, a.type, COUNT(*) n
FROM attributes a JOIN objects o ON o.id = a.object_id
WHERE o.name = 'passive-dns' AND a.deleted = 0
GROUP BY a.object_relation, a.type ORDER BY n DESC LIMIT 15;

SELECT '--- domain-ip object_relation shape ---' AS x;
SELECT a.object_relation, a.type, COUNT(*) n
FROM attributes a JOIN objects o ON o.id = a.object_id
WHERE o.name = 'domain-ip' AND a.deleted = 0
GROUP BY a.object_relation, a.type ORDER BY n DESC LIMIT 15;

SELECT '--- url object_relation shape ---' AS x;
SELECT a.object_relation, a.type, COUNT(*) n
FROM attributes a JOIN objects o ON o.id = a.object_id
WHERE o.name = 'url' AND a.deleted = 0
GROUP BY a.object_relation, a.type ORDER BY n DESC LIMIT 15;
SELECT '--- IPs that domain-ip objects tie to 2+ domains ---' AS x;
SELECT ip.value1 AS ip,
       COUNT(DISTINCT d.value1) AS domains,
       COUNT(DISTINCT o.event_id) AS events
FROM objects o
JOIN attributes ip ON ip.object_id = o.id
     AND ip.object_relation = 'ip' AND ip.deleted = 0
JOIN attributes d ON d.object_id = o.id
     AND d.object_relation = 'domain' AND d.deleted = 0
WHERE o.name = 'domain-ip' AND o.deleted = 0
GROUP BY ip.value1
HAVING domains >= 2
ORDER BY domains DESC, events DESC LIMIT 12;

SELECT '--- passive-dns: names with a dated resolution ---' AS x;
SELECT n.value1 AS rrname, r.value1 AS rdata,
       tf.value1 AS first_seen, tl.value1 AS last_seen,
       o.event_id
FROM objects o
JOIN attributes n ON n.object_id = o.id
     AND n.object_relation = 'rrname' AND n.deleted = 0
JOIN attributes r ON r.object_id = o.id
     AND r.object_relation = 'rdata' AND r.deleted = 0
LEFT JOIN attributes tf ON tf.object_id = o.id
     AND tf.object_relation = 'time_first' AND tf.deleted = 0
LEFT JOIN attributes tl ON tl.object_id = o.id
     AND tl.object_relation = 'time_last' AND tl.deleted = 0
WHERE o.name = 'passive-dns' AND o.deleted = 0
ORDER BY o.event_id LIMIT 15;

SELECT '--- passive-dns rdata reused by 2+ rrnames ---' AS x;
SELECT r.value1 AS rdata, COUNT(DISTINCT n.value1) AS names,
       COUNT(DISTINCT o.event_id) AS events
FROM objects o
JOIN attributes n ON n.object_id = o.id
     AND n.object_relation = 'rrname' AND n.deleted = 0
JOIN attributes r ON r.object_id = o.id
     AND r.object_relation = 'rdata' AND r.deleted = 0
WHERE o.name = 'passive-dns' AND o.deleted = 0
GROUP BY r.value1 HAVING names >= 2
ORDER BY names DESC LIMIT 12;
SELECT '--- A: draculax.myq-see.com resolution history ---' AS x;
SELECT n.value1 AS rrname, r.value1 AS rdata,
       tf.value1 AS first_seen, tl.value1 AS last_seen, o.event_id
FROM objects o
JOIN attributes n ON n.object_id = o.id
     AND n.object_relation = 'rrname' AND n.deleted = 0
JOIN attributes r ON r.object_id = o.id
     AND r.object_relation = 'rdata' AND r.deleted = 0
LEFT JOIN attributes tf ON tf.object_id = o.id
     AND tf.object_relation = 'time_first' AND tf.deleted = 0
LEFT JOIN attributes tl ON tl.object_id = o.id
     AND tl.object_relation = 'time_last' AND tl.deleted = 0
WHERE o.name = 'passive-dns' AND o.deleted = 0
  AND n.value1 LIKE 'dracula%'
ORDER BY tf.value1;

SELECT '--- A: event 1416 identity and tags ---' AS x;
SELECT e.id, e.info, e.date, o.name AS org
FROM events e JOIN organisations o ON o.id = e.orgc_id
WHERE e.id = 1416;
SELECT t.name FROM event_tags et JOIN tags t ON t.id = et.tag_id
WHERE et.event_id = 1416 LIMIT 20;

SELECT '--- A: do those IPs appear anywhere else? ---' AS x;
SELECT a.value1, COUNT(DISTINCT a.event_id) AS events, COUNT(*) AS rows_
FROM attributes a
WHERE a.deleted = 0 AND a.value1 IN
  ('179.253.227.97','141.255.147.117','141.255.159.82',
   '168.181.48.248','168.181.51.45','200.101.151.150')
GROUP BY a.value1;

SELECT '--- B: the 22 domains on 45.77.250.80 ---' AS x;
SELECT d.value1 AS domain, o.event_id
FROM objects o
JOIN attributes ip ON ip.object_id = o.id
     AND ip.object_relation = 'ip' AND ip.deleted = 0
JOIN attributes d ON d.object_id = o.id
     AND d.object_relation = 'domain' AND d.deleted = 0
WHERE o.name = 'domain-ip' AND o.deleted = 0
  AND ip.value1 = '45.77.250.80'
ORDER BY d.value1 LIMIT 25;
SELECT '--- B: event 1179 identity, tags, clusters ---' AS x;
SELECT e.id, e.info, e.date, o.name AS org
FROM events e JOIN organisations o ON o.id = e.orgc_id WHERE e.id = 1179;
SELECT t.name, t.is_galaxy FROM event_tags et JOIN tags t ON t.id = et.tag_id
WHERE et.event_id = 1179 LIMIT 25;

SELECT '--- B: spread of 45.77.250.80 itself ---' AS x;
SELECT COUNT(*) AS rows_, COUNT(DISTINCT event_id) AS events
FROM attributes WHERE value1 = '45.77.250.80' AND deleted = 0;

SELECT '--- C: what points at the luxtrust / cns names ---' AS x;
SELECT r.value1 AS rdata, n.value1 AS rrname,
       tf.value1 AS first_seen, tl.value1 AS last_seen, o.event_id
FROM objects o
JOIN attributes n ON n.object_id = o.id
     AND n.object_relation = 'rrname' AND n.deleted = 0
JOIN attributes r ON r.object_id = o.id
     AND r.object_relation = 'rdata' AND r.deleted = 0
LEFT JOIN attributes tf ON tf.object_id = o.id
     AND tf.object_relation = 'time_first' AND tf.deleted = 0
LEFT JOIN attributes tl ON tl.object_id = o.id
     AND tl.object_relation = 'time_last' AND tl.deleted = 0
WHERE o.name = 'passive-dns' AND o.deleted = 0
  AND r.value1 IN ('luxtrust-unlock.com','luxtrust.support',
                   'cns-lu.com','www-cns-lu.com','www-cns.com',
                   'ccss-sante-lu.com','ccss-public.com','luxtrust.co')
ORDER BY r.value1, n.value1 LIMIT 40;

SELECT '--- C: which events hold them ---' AS x;
SELECT e.id, e.info, e.date, o.name AS org
FROM events e JOIN organisations o ON o.id = e.orgc_id
WHERE e.id IN (
  SELECT DISTINCT ob.event_id FROM objects ob
  JOIN attributes r ON r.object_id = ob.id
       AND r.object_relation = 'rdata' AND r.deleted = 0
  WHERE ob.name = 'passive-dns' AND ob.deleted = 0
    AND r.value1 LIKE '%luxtrust%'
);
SELECT '--- C: IPs linking 2+ distinct LU phishing domains ---' AS x;
SELECT n.value1 AS ip, COUNT(DISTINCT r.value1) AS domains,
       GROUP_CONCAT(DISTINCT r.value1 ORDER BY r.value1
                    SEPARATOR ' | ') AS which
FROM objects o
JOIN attributes n ON n.object_id = o.id
     AND n.object_relation = 'rrname' AND n.deleted = 0
JOIN attributes r ON r.object_id = o.id
     AND r.object_relation = 'rdata' AND r.deleted = 0
WHERE o.name = 'passive-dns' AND o.deleted = 0
  AND o.event_id = 1507
  AND n.value1 REGEXP '^[0-9]+\\.[0-9]+\\.[0-9]+\\.[0-9]+$'
GROUP BY n.value1 HAVING domains >= 2 ORDER BY domains DESC;

SELECT '--- C: do those IPs reach outside event 1507? ---' AS x;
SELECT a.value1, COUNT(DISTINCT a.event_id) AS events,
       GROUP_CONCAT(DISTINCT a.event_id) AS which_events
FROM attributes a WHERE a.deleted = 0 AND a.value1 IN
  ('18.117.184.102','35.180.136.109','54.93.211.218',
   '35.177.103.239','3.71.1.255','13.48.203.238','54.211.144.11')
GROUP BY a.value1;

SELECT '--- how flat is event-co-occurrence vs object-co-occurrence ---' AS x;
SELECT 'attributes in objects' AS metric, COUNT(*) AS n
FROM attributes WHERE deleted = 0 AND object_id > 0
UNION ALL
SELECT 'attributes not in objects', COUNT(*)
FROM attributes WHERE deleted = 0 AND object_id = 0
UNION ALL
SELECT 'objects with 2+ attributes', COUNT(*) FROM (
  SELECT object_id FROM attributes WHERE deleted = 0 AND object_id > 0
  GROUP BY object_id HAVING COUNT(*) >= 2) t;
