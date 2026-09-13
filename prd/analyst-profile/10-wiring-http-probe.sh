#!/bin/bash
#
# Phase 9 over HTTP: the three verdict endpoints answer from the engine.
#
# What this asserts that a harness cannot. The thing phase 9 ships is a
# **tab that stops making things up**, and three of its properties only
# exist once a request has been served:
#
#   1. The ledger printed on the page sums to the score printed on the
#      page. `01-profile.md` §5.1 is asserted in the engine's own tests
#      against arrays; this reads the two numbers out of the markup,
#      which is the only place a reader ever meets them
#      (`10-wiring.md` §5 item 2).
#   2. The tab and the Overview card agree. They are separate lazy
#      requests in separate processes and nothing is shared between
#      them, so agreement is a claim about determinism and has to be
#      measured rather than arranged (§5 item 3).
#   3. A value with nothing to assess names no profile. §3.1's
#      conditional has been right since the skeleton pass and had
#      nothing to be right about until now (§5 item 4).
#
# It also asserts the absences, because the thirteen unproduced keys
# (§2.2) are defaulted rather than guarded and a default that stopped
# being applied would show up as a notice rather than as a blank card.
#
#   bash prd/analyst-profile/10-wiring-http-probe.sh \
#        https://localhost admin@admin.test admin 8.8.8.8
#
set -u

BASE=${1:-https://localhost}
EMAIL=${2:-admin@admin.test}
PASS=${3:-admin}
SUBJECT=${4:-8.8.8.8}
JAR=$(mktemp)
WORK=$(mktemp -d)
trap 'rm -rf "$JAR" "$WORK"' EXIT

PASSED=0
FAILED=0
ok() { PASSED=$((PASSED + 1)); printf 'ok   %s\n' "$1"; }
no() { FAILED=$((FAILED + 1)); printf 'FAIL %s\n' "$1"; }
is() { # name, got, want
    if [ "$2" = "$3" ]; then ok "$1 ($2)"; else no "$1: got '$2' want '$3'"; fi
}
absent() { # name, file, needle
    if grep -qF -- "$3" "$2"; then no "$1 (present: $3)"; else ok "$1"; fi
}

# ---------------------------------------------------------------- login
PAGE=$(curl -sk -c "$JAR" "$BASE/users/login")
grab() {
    printf '%s' "$PAGE" \
        | grep -o "name=\"data\[_Token\]\[$1\]\" value=\"[^\"]*\"" \
        | head -1 | sed 's/.*value="//;s/"$//'
}
CODE=$(curl -sk -b "$JAR" -c "$JAR" -o /dev/null -w '%{http_code}' \
    -X POST "$BASE/users/login" \
    --data-urlencode "data[_Token][key]=$(grab key)" \
    --data-urlencode "data[_Token][fields]=$(grab fields)" \
    --data-urlencode "data[_Token][unlocked]=$(grab unlocked)" \
    --data-urlencode "data[User][email]=$EMAIL" \
    --data-urlencode "data[User][password]=$PASS")
if [ "$CODE" != "302" ]; then
    echo "FAILED to log in as $EMAIL (HTTP $CODE)" >&2
    exit 2
fi

# A value nobody has reported. Randomised per run so a cached anything
# cannot make the sparse case pass by having been warmed by the last.
NOTHING="analyst-profile-phase9-absent-$$-$RANDOM"

fetch() { # endpoint, value, outfile -> status
    local b64
    b64=$(printf '%s' "$2" | base64 | tr '+/' '-_' | tr -d '=')
    curl -sk -b "$JAR" -o "$3" -w '%{http_code}' "$BASE/values/$1/$b64"
}

# -------------------------------------------------------- 1. transport
echo "--- the three endpoints answer, for a scored value and a bare one"
for_each() { # label, value
    local label=$1 value=$2 ep code f
    for ep in viewVerdict viewVerdictAside viewVerdictCard; do
        f="$WORK/$label-$ep.html"
        code=$(fetch "$ep" "$value" "$f")
        is "$label $ep status" "$code" "200"
        [ "$code" = "200" ] || continue
        absent "$label $ep no notice" "$f" "Notice (8)"
        absent "$label $ep no warning" "$f" "Warning (2)"
        absent "$label $ep no undefined key" "$f" "Undefined array key"
        # The fixture's artboard literal. Its presence would mean a
        # panel is still reading `ValueProfileFixture`.
        absent "$label $ep not the fixture" "$f" "default-v3"
    done
}
for_each scored "$SUBJECT"
for_each bare "$NOTHING"

TAB="$WORK/scored-viewVerdict.html"
ASIDE="$WORK/scored-viewVerdictAside.html"
CARD="$WORK/scored-viewVerdictCard.html"

# ------------------------------------------------ 2. the exact-sum rule
echo "--- the ledger on the page sums to the score on the page"
python3 - "$TAB" "$ASIDE" <<'PY'
import re
import sys

tab = open(sys.argv[1], encoding='utf-8', errors='replace').read()
aside = open(sys.argv[2], encoding='utf-8', errors='replace').read()

rows = [int(m) for m in re.findall(
    r'vp-ledger-contribution[^>]*>\s*([+-]?\d+)', tab)]
if not rows:
    rows = [int(m.replace('−', '-')) for m in re.findall(
        r'class="vp-ledger-points[^"]*"[^>]*>\s*([+−-]?\d+)', tab)]

score = re.search(r'vp-vc-score-value"[^>]*>\s*([+-]?\d+)\s*/\s*100', tab)
total = re.search(r'How ([+-]?\d+) was reached', aside)

print('rows: %s' % rows)
print('score on the tab: %s' % (score.group(1) if score else None))
print('total on the rail: %s' % (total.group(1) if total else None))

fails = 0
if not rows:
    print('FAIL could not read any ledger row out of the markup')
    fails += 1
if score is None:
    print('FAIL could not read the score out of the markup')
    fails += 1
if rows and score is not None:
    if sum(rows) == int(score.group(1)):
        print('ok   ledger sums to the score (%d)' % sum(rows))
    else:
        print('FAIL ledger sums to %d, score reads %s'
              % (sum(rows), score.group(1)))
        fails += 1
if total is not None and score is not None:
    if total.group(1) == score.group(1):
        print('ok   the rail and the tab print the same total')
    else:
        print('FAIL rail total %s, tab score %s'
              % (total.group(1), score.group(1)))
        fails += 1
sys.exit(1 if fails else 0)
PY
if [ $? -eq 0 ]; then ok "exact-sum on the rendered page"; \
    else no "exact-sum on the rendered page"; fi

# ----------------------------------------- 3. the card and the tab agree
echo "--- the card and the tab agree, computed twice and never shared"
word() { grep -o 'MALICIOUS\|BENIGN\|CONFLICTED\|UNKNOWN' "$1" | head -1; }
# The name is the link text in both places, and the two layouts wrap it
# differently — the tab in a `vp-meta-strong` span inside the anchor,
# the card in the anchor alone. Pull the anchor's text.
profile() {
    grep -o 'analystProfiles/view/[0-9]*[^>]*>\(<[^>]*>\)*[^<]*' "$1" \
        | sed 's/.*>//' | head -1
}
# Flattened first: the tab prints the number on its own line inside the
# span and grep is line-based, so the pattern would match an empty tail.
score() {
    tr '\n' ' ' < "$1" \
        | grep -o 'vp-vc-score-value"[^>]*>[^<]*\|vp-disposition-score">[^<]*' \
        | sed 's/.*>//;s/\/ *100//' | tr -d ' ' | head -1
}
is "disposition" "$(word "$CARD")" "$(word "$TAB")"
is "score" "$(score "$CARD")" "$(score "$TAB")"
is "profile named" "$(profile "$CARD")" "$(profile "$TAB")"
# ...and neither is blank, because two empty strings compare equal and
# an agreement check that passes by reading nothing is worse than none
# (phase 7 §7.1, phase 5 §7.5).
if [ -n "$(profile "$TAB")" ]; then
    ok "the name read is a name ($(profile "$TAB"))"
else
    no "the profile name extractor read nothing — the check above"\
" compared two empty strings"
fi
if [ -n "$(score "$TAB")" ]; then
    ok "the score read is a number ($(score "$TAB"))"
else
    no "the score extractor read nothing"
fi

# ------------------------------------------- 4. the bare value's silence
echo "--- a value with nothing to assess"
is "bare disposition" "$(word "$WORK/bare-viewVerdict.html")" "UNKNOWN"
absent "bare tab names no profile" "$WORK/bare-viewVerdict.html" \
    "Weighting profile"
absent "bare card names no profile" "$WORK/bare-viewVerdictCard.html" \
    "Weighting profile"
# The rail renders nothing at all rather than a column of empty states.
BARE_ASIDE=$(wc -c < "$WORK/bare-viewVerdictAside.html")
is "bare rail is empty" "$BARE_ASIDE" "0"
# ...and a scored one does not.
if [ "$(wc -c < "$ASIDE")" -gt 1000 ]; then
    ok "scored rail renders ($(wc -c < "$ASIDE") bytes)"
else
    no "scored rail is $(wc -c < "$ASIDE") bytes — the branch picked the"\
"conflicted rail against an agreeing tab, or every card is empty"
fi

# --------------------------------------------- 5. what the swap exposed
echo "--- shapes the fixture never produced"
if grep -qE 'Computed at render, </?[^>]*>?[0-9]{9,}' "$TAB"; then
    no "computed_at is formatted (raw unix seconds on the page)"
else
    ok "computed_at is formatted"
fi
if grep -qE 'vp-vc-score-fill"[^>]*width: *-' "$TAB"; then
    no "score bar width is clamped (negative width emitted)"
else
    ok "score bar width is clamped"
fi

# ------------------------------------------- 6. the derived display keys
echo "--- who says what, counted against the ledger that cites it"
python3 - "$TAB" <<'PY'
import re
import sys

tab = open(sys.argv[1], encoding='utf-8', errors='replace').read()
fails = 0

# One <tr> per organisation in the table body of the *Who says what*
# card. The card is the last table on the page and its first column is
# the organisation, so count the rows that carry the semibold name cell.
rows = re.findall(r'<td class="fw-semibold">([^<]*)</td>', tab)
claim = re.search(r'(\d+) independent organisations? reported it', tab)

print('organisations in the table: %d %s' % (len(rows), rows[:6]))
print('organisations the ledger claims: %s'
      % (claim.group(1) if claim else 'no such row'))

if not rows:
    print('FAIL the Who says what card rendered no organisation')
    fails += 1
elif claim is not None:
    # The table and `reporting.independent_orgs` are folded from one
    # context. A card beside an argument that counts differently from it
    # is the hazard this whole panel exists to avoid.
    if len(rows) == int(claim.group(1)):
        print('ok   the table and the ledger count the same organisations')
    else:
        print('FAIL table has %d, ledger claims %s'
              % (len(rows), claim.group(1)))
        fails += 1
else:
    print('ok   no reporting row to cross-check (table has %d)' % len(rows))

# Every row carries all five fixed columns; `opinion` is the one with no
# producer and it must read as *none stated* rather than as a zero.
if rows and 'none stated' not in tab:
    print('FAIL no opinion cell says "none stated" — a null opinion is'
          ' being drawn as a number')
    fails += 1
elif rows:
    print('ok   an unstated opinion says so rather than scoring 0')

sys.exit(1 if fails else 0)
PY
if [ $? -eq 0 ]; then ok "the organisations table"; \
    else no "the organisations table"; fi

echo "--- the warninglist band"
if grep -qF 'vp-vc-warninglist' "$TAB"; then
    if grep -qE 'v[0-9]{6,}' "$TAB"; then
        ok "the band names the version it matched against"
    else
        no "the band drew 'v' with no version after it"
    fi
    if grep -qE 'Category .(known|false_positive). means' "$TAB"; then
        ok "the band says what the category does not claim"
    else
        no "the band carries no category note"
    fi
    if grep -qE 'matched (exactly|by CIDR|as a substring|by pattern)' \
        "$TAB"; then
        ok "the band says how the value matched"
    else
        no "the band does not say how the value matched"
    fi
else
    ok "no warninglist hit on $SUBJECT, so no band (not a failure)"
fi

echo "--- the shelf-life chart"
python3 - "$ASIDE" <<'PY'
import json
import re
import sys

aside = open(sys.argv[1], encoding='utf-8', errors='replace').read()
fails = 0

if 'Shelf life' not in aside:
    print('ok   no runway to draw on this value (not a failure)')
    sys.exit(0)

# The card retains the old vocabulary nowhere: there is no verdict
# history to plot, so a line labelled for one would be invented data.
for dead in ('Verdict over time', 'NIDS decay', 'decay score'):
    if dead in aside:
        print('FAIL the card still says %r' % dead)
        fails += 1

points = re.findall(r'"data":\s*(\[[^\]]*\])', aside)
if not points:
    print('FAIL no chart series in the payload')
    fails += 1
else:
    series = json.loads(points[0])
    print('series length: %d, last point: %r' % (len(series), series[-1]))
    if len(series) == 90:
        print('ok   ninety days, which is what the labels assume')
    else:
        print('FAIL series has %d points, the labels assume 90'
              % len(series))
        fails += 1
    drawn = [p for p in series if p is not None]
    if not drawn:
        print('FAIL every point is null — the card drew an empty chart')
        fails += 1
    elif all(0 <= p <= 100 for p in drawn):
        print('ok   every drawn point is a percentage of shelf life')
    else:
        print('FAIL a point is outside 0-100: %r'
              % [p for p in drawn if not 0 <= p <= 100][:3])
        fails += 1

sys.exit(1 if fails else 0)
PY
if [ $? -eq 0 ]; then ok "the shelf-life chart"; else no "the shelf-life chart"; fi

# The chart's last point is *today's* shelf life, and the Sightings
# tab's relevance card draws the same quantity as a bar. Two endpoints,
# two requests, one axis: if they disagree the page is telling a reader
# two things about how long this value has. Phase 5 §7.2 shipped exactly
# this bug once — 79% drawn under 46% printed — which is why it is
# asserted across the panels rather than inside one.
echo "--- and the relevance card on the Sightings tab agrees with it"
RELV="$WORK/scored-viewRelevance.html"
if [ "$(fetch viewRelevance "$SUBJECT" "$RELV")" = "200" ]; then
    # Both flattened first. The chart's series is one JSON array and the
    # card's bar is an attribute split across two lines, and grep is
    # line-based — the same trap §8.4 records for the score.
    CHART=$(tr '\n' ' ' < "$ASIDE" \
        | grep -o '"data": *\[[^]]*\]' | head -1 \
        | sed 's/.*,//;s/[^0-9-]//g')
    BAR=$(tr '\n' ' ' < "$RELV" \
        | grep -o 'vp-shelf-fill"[^>]*width: *[0-9]*%' \
        | grep -o '[0-9]*%' | tr -d '%' | head -1)
    if [ -z "$CHART" ] || [ -z "$BAR" ]; then
        ok "no shelf life drawn on both panels for $SUBJECT (skipped)"
    else
        is "today's shelf life, chart vs relevance card" "$CHART" "$BAR"
    fi
else
    no "viewRelevance did not answer"
fi

echo
echo "passed: $PASSED   failed: $FAILED"
[ "$FAILED" -eq 0 ]
