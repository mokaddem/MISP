#!/usr/bin/env python3
"""Scratch seeder: near-match blocks and dated-relation objects.

Two sections of the Relationships tab had almost nothing to render on
this instance, for different reasons:

**Near-match.** 45 CIDR blocks existed but none of them contained a
well-known address, so section two rendered *active, and it found
nothing* on every value anybody would look at. There was also no IPv6
block at all, which left `ValueRelationTool::addressSpace`'s
power-of-two branch — the one that prints `2^64` because no integer holds
2^120 — with no live coverage.

**Dated relations.** `8.8.8.8` sat in 12 objects and not one recorded two
`datetime` attributes, so section five printed its empty state. The fold
needs an object with **two** dates plus at least one correlating
attribute; `passive-dns` (`time_first`/`time_last`) and `domain-ip`
(`first-seen`/`last-seen`) are the two templates that have them, and
using both is what puts two different date labels on the screen.

**Why the REST API and not a console shell.** Unlike
`24-relationships-seed.php`, every write here goes through HTTP so that
`MispAttribute::afterSave` runs — and `afterSave` is the only thing that
calls `Correlation::advancedCorrelationsUpdate`, which is the only thing
that rebuilds the Redis set `misp:cidr_cache_list`. A block written
straight to the database is invisible to the correlation engine *and* to
the near-match panel until that set is refreshed; §6 of
`24-relationships.md` records the debugging session that cost.

**Two MISP behaviours this had to work around.**

1. `ObjectsController::add` pre-validates each attribute with
   `Attribute->set($attr)`, and CakePHP's `set()` *merges* rather than
   replaces. Send no `category` and attribute N inherits N-1's — so a
   `domain-ip` whose first field is a `domain` ("Network activity")
   fails on `first-seen`, because `datetime` exists only in "Other".
   Every attribute below therefore names its category explicitly.

2. `/objects/add` does **not** apply the object template's
   `disable_correlation` flags. Real `passive-dns` data here has 1 on
   `rrtype`, `origin`, `count` and both timestamps and 0 on `rrname` and
   `rdata` — the template's own record of which fields join and which
   describe, which is exactly what `ValueRelationTool::dated` reads to
   pick the far value. Without `stamp_flags()` the panel lists `A` and
   `48213` as related values.

**Events are left unpublished on purpose.** This instance has 8 sync
servers configured; publishing would push the seed out to them.

**Not part of the application** — verification scaffolding, beside the
phase document that explains it, per §14.8 of the contract.

    export MISP_KEY=...            # an admin key; MISP_URL defaults to
    export MISP_URL=https://localhost
    ./24-near-match-dated-seed.py seed
    ./24-near-match-dated-seed.py wipe

`wipe` deletes only the two events `seed` created, found by their info
strings.

The Relationships scan is cached in Redis for
`ValueProfile::RELATION_SCAN_TTL` (300 s) and the dated fold is folded
inside it, so a freshly seeded object does not appear for up to five
minutes. Force the read instead:

    /values/viewRelationCooccurrence/<b64>?fresh=1

which is the only endpoint on the tab that honours `fresh`, and rewrites
the same key section five reads.
"""
import json
import os
import ssl
import sys
import urllib.error
import urllib.request

BASE = os.environ.get('MISP_URL', 'https://localhost').rstrip('/')
KEY = os.environ.get('MISP_KEY', '')

CTX = ssl.create_default_context()
CTX.check_hostname = False
CTX.verify_mode = ssl.CERT_NONE

NEAR_EVENT = ('Near-match bench - CIDR ladders for 8.8.8.8, 1.1.1.1 '
              'and a Vultr /64')
DATED_EVENT = ('Dated relations bench - resolution history for 8.8.8.8 '
               'and 1.1.1.1')

DOMAIN_IP = '43b3b146-77eb-4931-b4cc-b66c60f28734'
PASSIVE_DNS = 'b77b7b1c-66ab-4a41-8da4-83810f6d2d6c'

# Blocks chosen so each ladder brackets an address the instance already
# holds many times, at prefixes far enough apart that the closeness bar
# and the address count disagree usefully. Nothing wider than /9: a /0
# would contain every address on the instance and say nothing.
BLOCKS = [
    # 8.8.8.8 — joins the pre-existing 8.8.8.0/28 to make a four-rung ladder
    ('ip-dst', '8.8.8.0/24', 'Google Public DNS resolver range'),
    ('ip-dst', '8.8.0.0/16', 'Google LLC allocation'),
    ('ip-src', '8.0.0.0/9', 'Level3/Google legacy space - scan source'),
    # 1.1.1.1
    ('ip-dst', '1.1.1.0/24', 'Cloudflare resolver range'),
    ('ip-dst', '1.1.0.0/20', 'APNIC research allocation'),
    # 2001:19f0:4400:48fd:5400:ff:fe71:3202, a Vultr host with 3 occurrences.
    # /68 leaves 60 free bits, so addressSpace prints the exact
    # 1,152,921,504,606,846,976; /64 and /48 cross the 62-bit line and
    # print 2^64 and 2^80. Both branches, one value.
    ('ip-dst', '2001:19f0:4400:48fd:5000::/68', 'Vultr customer subnet'),
    ('ip-dst', '2001:19f0:4400:48fd::/64', 'Vultr host /64'),
    ('ip-dst', '2001:19f0:4400::/48', 'Vultr Amsterdam /48'),
]

# (relation, type, category, value). Date spans deliberately do not
# overlap and do not sort the same way as the values, so the fold's
# "newest-first for the cut, oldest-first for the eye" is observable.
OBJECTS = [
    (PASSIVE_DNS, 'passive-dns', [
        ('rrname', 'text', 'Other', 'dns.google'),
        ('rdata', 'text', 'Other', '8.8.8.8'),
        ('rrtype', 'text', 'Other', 'A'),
        ('origin', 'text', 'Other', 'CIRCL Passive DNS'),
        ('count', 'counter', 'Other', '48213'),
        ('time_first', 'datetime', 'Other', '2019-06-04T00:00:00+00:00'),
        ('time_last', 'datetime', 'Other', '2026-08-20T00:00:00+00:00'),
    ]),
    (PASSIVE_DNS, 'passive-dns', [
        ('rrname', 'text', 'Other', 'google-public-dns-a.google.com'),
        ('rdata', 'text', 'Other', '8.8.8.8'),
        ('rrtype', 'text', 'Other', 'A'),
        ('origin', 'text', 'Other', 'Farsight DNSDB'),
        ('count', 'counter', 'Other', '1204'),
        ('time_first', 'datetime', 'Other', '2013-01-15T00:00:00+00:00'),
        ('time_last', 'datetime', 'Other', '2018-09-30T00:00:00+00:00'),
    ]),
    # domain-ip has no origin field, so its row prints an em dash — the
    # "most templates do not have one" case the panel's own copy claims.
    (DOMAIN_IP, 'domain-ip', [
        ('domain', 'domain', 'Network activity', 'dns.google'),
        ('ip', 'ip-dst', 'Network activity', '8.8.8.8'),
        ('first-seen', 'datetime', 'Other', '2024-03-11T09:14:00+00:00'),
        ('last-seen', 'datetime', 'Other', '2025-11-02T22:41:00+00:00'),
    ]),
    (DOMAIN_IP, 'domain-ip', [
        ('domain', 'domain', 'Network activity', 'one.one.one.one'),
        ('ip', 'ip-dst', 'Network activity', '1.1.1.1'),
        ('first-seen', 'datetime', 'Other', '2018-04-01T00:00:00+00:00'),
        ('last-seen', 'datetime', 'Other', '2026-07-15T11:02:00+00:00'),
    ]),
]

# What the templates mark disable_correlation = 1 — see behaviour 2 above.
DESCRIPTIVE = {'rrtype', 'origin', 'count', 'time_first', 'time_last',
               'bailiwick', 'first-seen', 'last-seen'}


def call(path, payload=None):
    """Returns (data, error). MISP answers a validation failure with 403
    and the reason in the body, so the body is read on HTTPError."""
    data = json.dumps(payload).encode() if payload is not None else None
    req = urllib.request.Request(
        BASE + path, data=data, method='POST' if data else 'GET',
        headers={'Authorization': KEY, 'Accept': 'application/json',
                 'Content-Type': 'application/json'})
    try:
        with urllib.request.urlopen(req, context=CTX) as r:
            return json.loads(r.read().decode() or '{}'), None
    except urllib.error.HTTPError as e:
        return None, '%d %s' % (e.code, e.read().decode()[:400])
    except urllib.error.URLError as e:
        return None, str(e)


def add_event(info):
    res, err = call('/events/add', {'Event': {
        'info': info,
        'date': '2026-09-01',
        'distribution': 1,
        'analysis': 2,
        'threat_level_id': 3,
        'published': False,
    }})
    if err:
        raise SystemExit('could not create event: %s' % err)
    return int(res['Event']['id'])


def stamp_flags(event):
    """Apply the template's disable_correlation, which /objects/add does
    not. Goes through /attributes/edit so beforeSaveCorrelation re-runs
    and drops the correlation rows the missing flag created."""
    res, err = call('/events/view/%d' % event)
    if err:
        print('  cannot re-read event: %s' % err)
        return
    for obj in res['Event'].get('Object', []):
        for attr in obj.get('Attribute', []):
            if attr.get('object_relation') not in DESCRIPTIVE:
                continue
            if attr.get('disable_correlation'):
                continue
            _, err = call('/attributes/edit/%s' % attr['id'], {'Attribute': {
                'id': attr['id'],
                'disable_correlation': True,
            }})
            print('  flag %-11s %-32s %s' % (
                attr['object_relation'], attr['value'][:32], err or 'ok'))


def seed():
    near = add_event(NEAR_EVENT)
    print('event %d - near-match blocks' % near)
    for atype, value, comment in BLOCKS:
        res, err = call('/attributes/add/%d' % near, {'Attribute': {
            'type': atype,
            'category': 'Network activity',
            'value': value,
            'comment': comment,
            'to_ids': False,
            'distribution': 5,
        }})
        print('  %-32s %s' % (
            value, err or ('attribute %s' % res['Attribute']['id'])))

    dated = add_event(DATED_EVENT)
    print('event %d - dated objects' % dated)
    for uuid, name, fields in OBJECTS:
        res, err = call('/objects/add/%d/%s' % (dated, uuid), {'Object': {
            'name': name,
            'meta-category': 'network',
            'template_uuid': uuid,
            'distribution': 5,
            'Attribute': [{
                'object_relation': rel,
                'type': atype,
                'category': category,
                'value': value,
                'to_ids': False,
                'distribution': 5,
            } for rel, atype, category, value in fields],
        }})
        print('  %-12s %s' % (
            name, err or ('object %s' % res['Object']['id'])))
    stamp_flags(dated)
    print('\nevents %d and %d, unpublished' % (near, dated))


def wipe():
    # `eventinfo`, not `info`: /events/index silently ignores an unknown
    # filter key and hands back all 4,283 events instead of erroring.
    res, err = call('/events/index', {'eventinfo': 'bench -'})
    if err:
        raise SystemExit('could not list events: %s' % err)
    wanted = {NEAR_EVENT, DATED_EVENT}
    for row in res if isinstance(res, list) else res.get('response', []):
        event = row.get('Event', row)
        if event.get('info') not in wanted:
            continue
        _, err = call('/events/delete/%s' % event['id'])
        print('  deleted event %s (%s) %s' % (
            event['id'], event['info'][:40], err or 'ok'))


def main():
    if not KEY:
        raise SystemExit('set MISP_KEY to an admin authkey')
    action = sys.argv[1] if len(sys.argv) > 1 else 'seed'
    if action == 'seed':
        seed()
    elif action == 'wipe':
        wipe()
    else:
        raise SystemExit('usage: %s [seed|wipe]' % sys.argv[0])


if __name__ == '__main__':
    main()
