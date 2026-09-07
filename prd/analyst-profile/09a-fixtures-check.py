"""Does each of 09b-prototypes.md §5's nine items appear in the fixtures?

A brief that promises nine states and ships eight is a brief that gets a
candidate redrawn in 8c. Run from the repository root:

    python3 prd/analyst-profile/09a-fixtures-check.py

Its first run found three gaps, and one of them was a finding rather
than an oversight: the candidate edit chosen to produce a *changed*
ledger row lowered a per-unit weight on a signal that was already
saturated at its cap, so the row did not move at all. Phase 6 §7.1
recorded the same thing from the other direction -- a weighting is
invisible past a cap -- and a fixture built without checking would have
handed 8b a diff with no changed row in it.
"""
import json
import os

OUT = 'prd/analyst-profile/09a-fixtures/'
# Only the fixtures. The directory also holds README.md, and the first
# version of this loaded every file in it and died on the first line of
# prose.
F = {n[:-5]: json.load(open(OUT + n))
     for n in sorted(os.listdir(OUT)) if n.endswith('.json')}

checks = []


def check(label, ok, detail=''):
    checks.append((label, ok, detail))


# 1. points columns differ per row
items = F['palette']['items']
keysets = set()
for it in items:
    keys = tuple(sorted(f['key'] for f in it['fields']
                        if f.get('map') == 'points'))
    if keys:
        keysets.add(keys)
check('1  points columns differ per row', len(keysets) >= 4,
      '%d distinct points shapes' % len(keysets))

# 2. a missing signal, badged
missing = [i for i in items if i['state'] == 'missing']
check('2  a signal not implemented here', len(missing) == 1,
      missing[0]['id'] if missing else 'none')
badged = missing and missing[0]['badges'][0]['id'] == 'missing'
check('2b badged as such', bool(badged))

# 3. loader errors
errs = F['palette']['loader_errors']
check('3  the loader error list', len(errs) == 3,
      '%d entries, reasons: %s' % (len(errs),
                                   ', '.join(e['file'] for e in errs)))

# 4. band strip in a wrong state
b = F['bands']
check('4  medium above high', not b['inverted']['ok'],
      b['inverted_errors'][0][:60] if b['inverted_errors'] else '')
check('4b a band beyond the bound', not b['beyond_bound']['ok'],
      b['beyond_bound_errors'][0][:60] if b['beyond_bound_errors'] else '')
check('4c bound falls when signals are disabled',
      b['narrow_catalogue']['bound'] < b['ok']['bound'],
      '%d -> %d' % (b['ok']['bound'], b['narrow_catalogue']['bound']))

# 5. the diff: changed, appeared, vanished, both totals, empty diff
d = F['simulate']['detail']
states = {r['state'] for r in d['rows']}
check('5  a changed row', 'changed' in states)
check('5b a vanished row', 'vanished' in states)
check('5c an appeared row', 'appeared' in states,
      '' if 'appeared' in states else 'MISSING - no row appears')
check('5d both columns sum exactly', d['sums']['ok'],
      json.dumps(d['sums']))
deltas = sum(r['delta'] for r in d['rows'])
check('5e deltas sum to the total delta',
      deltas == d['totals']['delta'],
      '%d vs %d' % (deltas, d['totals']['delta']))
u = F['simulate']['detail_unchanged']
check('5f the empty diff', u['changed'] is False
      and all(r['state'] == 'same' for r in u['rows']))

# 6. comparison set with rows, and empty
comp = F['simulate']['comparison']
check('6  the comparison set', len(comp) >= 3, '%d rows' % len(comp))
check('6b with directions', len({c['direction'] for c in comp}) >= 1,
      ', '.join('%s=%s' % (c['value'], c['direction']) for c in comp))
check('6c and its empty state',
      F['simulate']['comparison_empty'] == [])

# 7. index standings
profiles = F['index']['profiles']
standings = [p['standing']['state'] for p in profiles]
check('7  in_force', 'in_force' in standings, ', '.join(standings))
check('7b disabled', 'disabled' in standings)
check('7c overridden naming the winner',
      any(p['standing']['state'] == 'overridden'
          and p['standing'].get('winner') for p in profiles))
check('7d nothing in force at all',
      F['index']['scoring_off_variant']['scoring_off'] is True)

# 8. two map sections with entries
secs = F['profile']['sections']
ttl = [b for b in secs['relevance']['blocks'] if b.get('id') == 'ttl_days']
trust = [b for b in secs['reference']['blocks']
         if b.get('id') == 'org_trust']
check('8  relevance TTL map', ttl and len(ttl[0]['entries']) > 1,
      '%d rows' % len(ttl[0]['entries']) if ttl else 'none')
check('8b org trust map', trust and len(trust[0]['entries']) > 1,
      '%d rows' % len(trust[0]['entries']) if trust else 'none')
check('8c a graded org not on this instance',
      trust and any(e.get('missing') for e in trust[0]['entries']))
loc = [b for b in secs['enrichment']['blocks']
       if b.get('id') == 'locality']
check('8d enrichment locality map', loc and len(loc[0]['entries']) > 0,
      '%d rows' % len(loc[0]['entries']) if loc else 'none')
auto = [b for b in secs['enrichment']['blocks']
        if b.get('id') == 'auto_run']
check('8e modules per type', auto and len(auto[0]['entries']) > 0,
      '%d rows' % len(auto[0]['entries']) if auto else 'none')

# extra: contributions present so a design can show them
withc = [i for i in secs['signals']['blocks'][0]['items']
         if i['contribution'] is not None]
check('*  contributions on the value', len(withc) >= 8,
      '%d of %d signals contributed' %
      (len(withc), len(secs['signals']['blocks'][0]['items'])))
custom = [i['id'] for i in items
          for bd in i['badges'] if bd['id'] == 'custom']
check('*  a custom-signal badge exists somewhere', bool(custom),
      ', '.join(custom) if custom
      else 'none - the instance has no drop-in signals')

for label, ok, detail in checks:
    print('%s %-42s %s' % ('ok  ' if ok else 'FAIL', label, detail))
bad = [c for c in checks if not c[1]]
print()
print('%d checks, %d failures' % (len(checks), len(bad)))
