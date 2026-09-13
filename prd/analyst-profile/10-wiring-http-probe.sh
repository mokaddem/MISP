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

# Everything below reads a value that was actually scored. Handed one
# this instance holds nothing about, the sections would report four
# failures that are all the same fact — and that fact already has its
# own section, run against a value randomised per run. Say so once and
# stop, rather than letting a reader conclude the page is broken.
if ! grep -qF 'vp-ledger-table' "$TAB" \
    && ! grep -qF 'vp-vc-cases' "$TAB"; then
    echo
    echo "'$SUBJECT' has nothing this viewer can see, so there is no"\
" assessment to check. The bare-value case is covered above, on a"\
" value randomised per run; pass a scored value as the fourth"\
" argument to exercise the rest."
    echo
    echo "passed: $PASSED   failed: $FAILED"
    [ "$FAILED" -eq 0 ]
    exit
fi

# ------------------------------------------------ 2. the exact-sum rule
# Two layouts, one invariant. The agreeing one prints a ledger and a
# quality and the rows have to sum to it; the contested one prints two
# case totals and no quality at all, and `support − dispute` is the same
# number — which the Overview card, in its own request, still prints.
# So the contested form of the check is stronger than the agreeing one:
# it crosses two templates instead of staying inside one.
echo "--- the ledger on the page sums to the quality on the page"
python3 - "$TAB" "$ASIDE" "$CARD" <<'PY'
import re
import sys

tab = open(sys.argv[1], encoding='utf-8', errors='replace').read()
aside = open(sys.argv[2], encoding='utf-8', errors='replace').read()
card = open(sys.argv[3], encoding='utf-8', errors='replace').read()
fails = 0

if 'vp-vc-cases' in tab:
    # ---------------------------------------------- contested layout
    heads = re.search(r'Threat case\s*([+-]?\d+)', tab)
    tail = re.search(r'([+-]?\d+)\s*benign case', tab)
    # The Overview card prints the quality the tab does not.
    quality = re.search(r'vp-disposition-score">\s*([+-]?\d+)', card)
    print('threat case: %s, benign case: %s, card quality: %s'
          % (heads and heads.group(1), tail and tail.group(1),
             quality and quality.group(1)))
    if heads is None or tail is None:
        print('FAIL could not read the two case totals off the tug')
        fails += 1
    elif quality is None:
        print('FAIL the Overview card printed no quality to check them'
              ' against')
        fails += 1
    else:
        support, dispute = int(heads.group(1)), int(tail.group(1))
        if support - dispute == int(quality.group(1)):
            print('ok   support - dispute is the quality (%d - %d = %s),'
                  ' across two requests'
                  % (support, dispute, quality.group(1)))
        else:
            print('FAIL %d - %d is %d, the card reads %s'
                  % (support, dispute, support - dispute,
                     quality.group(1)))
            fails += 1
        # Each case's own rows sum to its total. The row bars carry the
        # points in their title attribute, which is the only place a
        # per-row number is printed on this layout.
        cases = re.findall(
            r'vp-vc-case vp-vc-case-(\w+)(.*?)(?=vp-vc-case vp-vc-case-|$)',
            tab, re.S)
        sums = []
        for side, blob in cases:
            pts = [int(m) for m in re.findall(
                r'title="(\d+) points?"', blob)]
            sums.append((side, sum(pts), len(pts)))
        print('per-case sums: %s' % sums)
        if len(sums) != 2:
            print('FAIL the layout drew %d cases, and it reads two'
                  ' positionally' % len(sums))
            fails += 1
        elif [sums[0][1], sums[1][1]] == [support, dispute]:
            print('ok   each column sums to the head above it (%d, %d)'
                  % (sums[0][1], sums[1][1]))
        else:
            print('FAIL columns sum to %d and %d, heads read %d and %d'
                  % (sums[0][1], sums[1][1], support, dispute))
            fails += 1
        # And the rail's card names the same two totals.
        rail = re.search(r'How ([+-]?\d+) and ([+-]?\d+) were reached',
                         aside)
        if rail is None:
            print('FAIL the rail drew no case composition')
            fails += 1
        elif [int(rail.group(1)), int(rail.group(2))] == [support, dispute]:
            print('ok   and the rail names the same two totals')
        else:
            print('FAIL rail says %s and %s' % rail.groups())
            fails += 1
else:
    # ----------------------------------------------- agreeing layout
    rows = [int(m) for m in re.findall(
        r'vp-ledger-contribution[^>]*>\s*([+-]?\d+)', tab)]
    if not rows:
        rows = [int(m.replace('−', '-')) for m in re.findall(
            r'class="vp-ledger-points[^"]*"[^>]*>\s*([+−-]?\d+)', tab)]

    score = re.search(
        r'vp-vc-score-value"[^>]*>\s*([+-]?\d+)\s*/\s*100', tab)
    total = re.search(r'How ([+-]?\d+) was reached', aside)

    print('rows: %s' % rows)
    print('quality on the tab: %s' % (score.group(1) if score else None))
    print('total on the rail: %s' % (total.group(1) if total else None))

    if not rows:
        print('FAIL could not read any ledger row out of the markup')
        fails += 1
    if score is None:
        print('FAIL could not read the quality out of the markup')
        fails += 1
    if rows and score is not None:
        if sum(rows) == int(score.group(1)):
            print('ok   ledger sums to the quality (%d)' % sum(rows))
        else:
            print('FAIL ledger sums to %d, quality reads %s'
                  % (sum(rows), score.group(1)))
            fails += 1
    if total is not None and score is not None:
        if total.group(1) == score.group(1):
            print('ok   the rail and the tab print the same total')
        else:
            print('FAIL rail total %s, tab quality %s'
                  % (total.group(1), score.group(1)))
            fails += 1
sys.exit(1 if fails else 0)
PY
if [ $? -eq 0 ]; then ok "exact-sum on the rendered page"; \
    else no "exact-sum on the rendered page"; fi

# ----------------------------------------- 3. the card and the tab agree
echo "--- the card and the tab agree, computed twice and never shared"
# The four lean labels, which are the only words either surface prints
# for what the record asserts. D11's rename retired MALICIOUS and the
# other three with it; `ValueLean::label()` is where these live.
word() {
    grep -o 'Asserted threat\|Asserted benign\|Contested\|Nothing asserted' \
        "$1" | head -1
}
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
is "lean" "$(word "$CARD")" "$(word "$TAB")"
# The contested tab prints no single quality — that is the layout's
# whole point (`04-dispositions.md` §5: a single number would be the
# mean of two incompatible readings) — so the agreement check for it is
# the arithmetic one in section 2 instead.
if grep -qF 'vp-vc-cases' "$TAB"; then
    ok "the contested tab prints no single quality, by design"
else
    is "quality" "$(score "$CARD")" "$(score "$TAB")"
fi
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
if grep -qF 'vp-vc-cases' "$TAB"; then
    ok "the Overview card carries the quality ($(score "$CARD"))"
elif [ -n "$(score "$TAB")" ]; then
    ok "the quality read is a number ($(score "$TAB"))"
else
    no "the quality extractor read nothing"
fi

# And the tab bar's pill, which is neither of them: it renders on the
# synchronous page build, from a profile the fixture still frames
# (`ValuesController::view`). It named a lean off `disposition` until
# D11's rename, at which point it read nothing and drew *Nothing
# asserted* over a body reading *Contested* — so the page contradicted
# itself in the one place a reader sees both at once.
PAGE="$WORK/scored-view.html"
PB64=$(printf '%s' "$SUBJECT" | base64 | tr '+/' '-_' | tr -d '=')
if [ "$(curl -sk -b "$JAR" -o "$PAGE" -w '%{http_code}' \
    "$BASE/values/view/$PB64")" = "200" ]; then
    # Squeezed first: the tab bar is generously indented and the badge
    # sits ~700 raw characters after the href, nearly all of it spaces.
    PILL=$(tr '\n' ' ' < "$PAGE" | tr -s ' ' \
        | grep -o 'href="#tab-assessment".\{0,300\}' \
        | grep -o 'Asserted threat\|Asserted benign\|Contested\|Nothing asserted' \
        | head -1)
    is "the tab pill and the tab body name one lean" "$PILL" \
        "$(word "$TAB")"
else
    no "the value page did not answer"
fi

# ------------------------------------------- 4. the bare value's silence
echo "--- a value with nothing to assess"
is "bare lean" "$(word "$WORK/bare-viewVerdict.html")" \
    "Nothing asserted"
absent "bare tab names no profile" "$WORK/bare-viewVerdict.html" \
    "Analyst profile"
absent "bare card names no profile" "$WORK/bare-viewVerdictCard.html" \
    "Analyst profile"
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
if grep -qE 'vp-(vc-score|tug-mal|tug-ben)[^"]*"[^>]*width: *-' "$TAB"; then
    no "a bar width went negative — the browser drops the declaration"\
" and the fill keeps whatever width it inherits"
else
    ok "no bar on the tab draws a negative width"
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
    # Any digit, not a date. §9.6 is about the band printing a prefix it
    # has no value for; `20240615` and `5` are both real versions MISP
    # ships, and requiring six digits failed `127.0.0.1`, whose RFC 5735
    # list is honestly at v5.
    if tr '\n' ' ' < "$TAB" | tr -s ' ' \
        | grep -qE 'font-monospace"> v[0-9]'; then
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

# `01-profile.md` §6's inventory, asserted where a reader meets it. Two
# of its rows closed by an edit and three closed by the tab no longer
# reading `ValueProfileFixture` — and the fixture still holds all three
# strings, so a grep over `app/` reports them owing and only a rendered
# page can say they are gone.
echo "--- the shipped copy the inventory retired"
for f in "$TAB" "$ASIDE" "$CARD"; do
    label=$(basename "$f" .html)
    absent "$label: no SUSPICIOUS" "$f" "SUSPICIOUS"
    absent "$label: no decay changer" "$f" "decay takes the score"
    absent "$label: no NIDS curve" "$f" "NIDS decay score"
    absent "$label: profile is not 'weighting'" "$f" "Weighting profile"
    absent "$label: the admin sentence is retracted" "$f" \
        "An instance admin can edit"
done

# The label is only half of that row. The other half is that the name
# beside it is the profile that actually weighted these rows, which is
# what makes it linkable — so it is read out of the markup and compared
# with the one the rail's note names, two requests apart.
echo "--- and the profile it names is the one that weighted it"
NAMED=$(tr '\n' ' ' < "$TAB" \
    | grep -o 'Analyst profile[^<]*<[^>]*>[^<]*<[^>]*>[^<]*' \
    | sed 's/.*vp-meta-strong">//;s/<.*//' | head -1)
NOTED=$(tr '\n' ' ' < "$ASIDE" \
    | grep -o 'Weights come from [^,]*,' \
    | sed 's/Weights come from //;s/,$//' | head -1)
if [ -z "$NAMED" ] && [ -z "$NOTED" ]; then
    ok "nothing weighted on $SUBJECT, so no profile named (skipped)"
else
    is "the tab and the rail name one profile" "$NAMED" "$NOTED"
fi

# §3's replacement for the retracted sentence says how far the profile
# in force reaches, which is the thing the meta line cannot say. Any of
# D3's three scopes is a pass; a note that states none of them is the
# old sentence back in a new shape.
echo "--- the composition note states a scope"
if grep -qF 'vp-comp-note' "$ASIDE"; then
    if grep -qE 'your own profile|your organisation.s profile|the instance default' \
        "$ASIDE"; then
        ok "the note names which of D3's three scopes owns the profile"
    else
        no "the note is drawn but names no scope"
    fi
else
    ok "nothing weighted, so no note (not a failure)"
fi

# ------------------------------------------------- 6. the hero sentence
# D11's one open point, and the only place on the tab where all three
# axes are read together. Two things to assert: that it is there at all,
# and that its one number is the *same* number the relevance card
# prints — because the sentence is a reading of the assessment and not a
# fourth opinion about it.
echo "--- the hero's sentence composes the three axes"
HERO=$(tr '\n' ' ' < "$TAB" \
    | grep -o 'vp-vc-prose[^>]*>[^<]*' | sed 's/.*>//' \
    | sed 's/^ *//;s/ *$//' | head -1)
if [ -z "$HERO" ]; then
    no "the hero drew no sentence"
else
    ok "the hero drew a sentence (${HERO})"
    case "$HERO" in
        *"reads as a threat"*|*"reads as benign"*|*"contradicts itself"*|\
        *"Nothing you can see records"*)
            ok "it opens on the lean" ;;
        *) no "the sentence opens on none of the four leans" ;;
    esac
    case "$HERO" in
        *"well evidenced"*|*"moderately evidenced"*|*"is thin"*|\
        *"Nothing you can see records"*)
            ok "and names the quality band in words, not in points" ;;
        *) no "the sentence names no band" ;;
    esac
    # The number is the one the hero has nowhere else. `−1 / 100` is
    # beside the badge; days appear only here and in the rail's chart.
    #
    # And it is asserted as an *agreement* rather than as a presence:
    # the sentence talks about time exactly when the rail draws the
    # chart. A value whose rows the budget left unread has no relevance
    # to state (`ValueRelevanceTool`, reason `rows_not_read`) — so both
    # must fall silent together, and a sentence that kept talking would
    # be the assessment asserting a shelf life the page declined to
    # draw.
    SAYS_TIME=no
    case "$HERO" in
        *"shelf life"*|*"expires today"*) SAYS_TIME=yes ;;
    esac
    DRAWS_TIME=no
    grep -qF 'Shelf life' "$ASIDE" && DRAWS_TIME=yes
    case "$HERO" in
        *"Nothing you can see records"*)
            ok "nothing recorded, so the sentence states no axis at"\
" all" ;;
        *) is "the sentence and the rail agree about whether there is"\
" a shelf life to state" "$SAYS_TIME" "$DRAWS_TIME" ;;
    esac
fi

# The same cross-panel assertion as §9.5, on the other quantity: the
# sentence's days and the relevance card's days are one number computed
# in two requests. Phase 5 §7.2 is the bug this shape catches.
if [ "$(fetch viewRelevance "$SUBJECT" "$RELV")" = "200" ]; then
    HERO_DAYS=$(printf '%s' "$HERO" \
        | grep -o 'has [0-9]* day\|with [0-9]* day\|out [0-9]* day' \
        | grep -o '[0-9]*' | head -1)
    CARD_DAYS=$(tr '\n' ' ' < "$RELV" \
        | grep -o 'vp-shelf-days"[^>]*>[^<]*' | sed 's/.*>//' \
        | grep -o '[0-9]*' | head -1)
    if [ -z "$HERO_DAYS" ] || [ -z "$CARD_DAYS" ]; then
        ok "no day count on both panels for $SUBJECT (skipped)"
    else
        is "the sentence's days and the relevance card's days" \
            "$HERO_DAYS" "$CARD_DAYS"
    fi
fi

# The contested layout's third key. `conflicts` and `ambiguities` are
# one derivation shown in two places — under the ledger on the agreeing
# layout, under the two cases on the contested one — so whichever layout
# this value drew, the card is the same card and it must not claim the
# items were counted for neither side. A split organisation *is*
# counted, with the asserters, and the heading said otherwise until
# phase 9.
echo "--- what neither case could take"
absent "the card does not claim 'counted for neither side'" "$TAB" \
    "counted for neither side"
if grep -qF 'Settled by rule, not by evidence' "$TAB"; then
    if grep -qE 'organisations? holds? it both ways|warninglists disagree' \
        "$TAB"; then
        ok "it names what was settled and how"
    else
        no "the card is drawn with no item in it"
    fi
else
    ok "nothing on $SUBJECT was settled by rule (not a failure)"
fi

# And the sparse value, which is where `summary` is read first: the
# Overview card prints prose only when there are no ledger rows to list
# instead, so the one-clause sentence is the whole card.
echo "--- and the value with nothing to assess says so in one clause"
BARE_CARD="$WORK/bare-viewVerdictCard.html"
if grep -qF 'Nothing you can see records this value' "$BARE_CARD"; then
    ok "the bare card carries the sentence"
else
    no "the bare card drew no sentence — which is the branch that had"\
" no producer at all before the hero pass"
fi

# The opinion aggregate. Both panels that show one read the Collaboration
# tab's own union rather than a cheaper count of their own — so the check
# that matters is not that a histogram appeared, it is that it agrees
# with the tab the union belongs to, three requests apart.
echo "--- the opinions, against the tab that owns them"
STAND="$WORK/scored-viewAnalystStanding.html"
if [ "$(fetch viewAnalystStanding "$SUBJECT" "$STAND")" = "200" ]; then
    RAIL_MEAN=$(tr '\n' ' ' < "$ASIDE" \
        | grep -o 'opinions · mean [0-9.]*' | grep -o '[0-9.]*$' | head -1)
    TAB_MEAN=$(tr '\n' ' ' < "$STAND" \
        | grep -o 'vpa-mean-value">[^<]*' | sed 's/.*>//' \
        | tr -d ' ' | head -1)
    if [ -z "$RAIL_MEAN" ] && [ -z "$TAB_MEAN" ]; then
        ok "nobody has opined on $SUBJECT, so neither panel draws one"
    elif [ -z "$RAIL_MEAN" ]; then
        ok "no histogram on this layout (the card is the contested"\
" rail's), and the Collaboration tab has the mean"
    else
        is "the rail's histogram and the Collaboration tab's mean" \
            "$RAIL_MEAN" "$TAB_MEAN"
    fi
else
    no "viewAnalystStanding did not answer"
fi

# And the fifth column of *Who says what*, which had no source at all
# until the union arrived. `none stated` is still the right answer where
# nobody has opined — a zero there would read as the strongest possible
# disagreement (§9.2).
if grep -qE 'vp-orgs-opinion|Opinion' "$TAB"; then
    if grep -qF 'none stated' "$TAB" \
        || grep -qE 'vp-orgs-op[^"]*"[^>]*>\s*[0-9]+' "$TAB"; then
        ok "the opinion column says a number or says nothing, never"\
" zero-by-default"
    else
        no "the opinion column drew neither a score nor 'none stated'"
    fi
fi

# ------------------------------------------------- 8. the clock band
# The third axis, at the rank the other two had all along. What this
# section asserts is not that a band appeared — it is that the band and
# the Lifetime card on the Sightings tab, computed in two requests from
# two different facades, say the same thing about one value. §14.3 is
# why: this tab drew `expired, 33 days over` beside `64 days left` on
# that card, and the axis with no shared element was the axis that
# broke. They share one element now, so the check is that nothing
# re-introduces a second rendering.
echo "--- the clock band, against the card that owns the same clock"
REL="$WORK/scored-viewRelevance.html"
REL_CODE=$(fetch viewRelevance "$SUBJECT" "$REL")
is "viewRelevance answers" "$REL_CODE" "200"
if [ "$REL_CODE" = "200" ]; then
    python3 - "$TAB" "$REL" <<'PY'
import re
import sys

tab = open(sys.argv[1], encoding='utf-8', errors='replace').read()
rel = open(sys.argv[2], encoding='utf-8', errors='replace').read()
fails = 0


def strip(html):
    return re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', ' ', html)).strip()


def band(html):
    i = html.find('vp-vc-clock-body')
    return html[i:i + 6000] if i >= 0 else None


def field(html, cls):
    m = re.search(r'class="' + cls + r'"[^>]*>(.*?)</span>', html, re.S)
    return None if m is None else strip(m.group(1))


b = band(tab)
if b is None:
    print('FAIL the Assessment tab drew no clock band on a scored value')
    sys.exit(1)

# The axis can stand down (`rows_not_read`), which is a reading and not
# a failure — but then it must say so, and it must not claim the card
# agrees with it.
if 'stands down' in strip(b):
    print('ok   the axis stands down on this value, and says why')
    if 'carries the same clock in full' in strip(b):
        print('FAIL a stood-down band claims the Lifetime card agrees'
              ' with it — which is the §14.3 contradiction in a'
              ' caption')
        fails += 1
    else:
        print('ok   and it does not claim the Lifetime card agrees')
    sys.exit(1 if fails else 0)

band_state, band_days = field(b, 'vp-shelf-state'), field(b, 'vp-shelf-days')
card_state = field(rel, 'vp-shelf-state')
card_days = field(rel, 'vp-shelf-days')
print('band: %s / %s' % (band_state, band_days))
print('card: %s / %s' % (card_state, card_days))

for name, got, want in (('state', band_state, card_state),
                        ('days', band_days, card_days)):
    if got is None or want is None:
        print('FAIL could not read the %s off both panels' % name)
        fails += 1
    elif got == want:
        print('ok   the band and the Lifetime card agree on the %s'
              ' (%s), two requests apart' % (name, got))
    else:
        print('FAIL the band says %s and the card says %s' % (got, want))
        fails += 1

# The two facts §3.4 requires of any panel printing a TTL: the date the
# clock runs from, and the type that supplied the number. A band that
# prints a state and a day count and neither of these is the aggregate
# this whole pass exists to replace.
text = strip(b)
if re.search(r'(Last confirmed|Added) \d{4}-\d\d-\d\d', text):
    print('ok   the band names the date the clock runs from')
else:
    print('FAIL the band prints a day count with no date behind it')
    fails += 1
if re.search(r'(Set for \S+|no type to take a lifetime|No lifetime set)',
             text):
    print('ok   the band names where the lifetime came from')
else:
    print('FAIL the band does not say which type supplied the TTL')
    fails += 1
if re.search(r'Expires \d{4}-\d\d-\d\d', text):
    print('ok   and the day it expires')
else:
    print('FAIL the band names no expiry date')
    fails += 1

# The corroboration rows are this axis's ledger. Where the clock has
# events the newest one *is* the clock, so the first row has to carry
# the date the provenance line named.
if 'What has reset this clock' in text:
    named = re.search(r'(?:Last confirmed|Added) (\d{4}-\d\d-\d\d)', text)
    rows = re.findall(r'vp-shelf-event-date">\s*([\d-]+)', b)
    print('rows: %s, clock names: %s'
          % (rows[:4], named and named.group(1)))
    if rows and named and rows[0] == named.group(1):
        print('ok   the newest corroboration is the clock (%s)' % rows[0])
    else:
        print('FAIL the list\'s first row and the clock disagree')
        fails += 1
    if len(rows) <= 4:
        print('ok   the band caps the list at four rows (%d drawn)'
              % len(rows))
    else:
        print('FAIL the band drew %d rows, and it caps at four'
              % len(rows))
        fails += 1

sys.exit(1 if fails else 0)
PY
    if [ $? -eq 0 ]; then ok "the clock band, read against the Lifetime"\
" card"; else no "the clock band, read against the Lifetime card"; fi
fi

# The extraction's own regression guard. The Lifetime card handed three
# of its parts to shared elements; everything it kept has to still be
# on it, and a reader of that card would not notice a missing block
# because the card still looks complete without one.
echo "--- and the Lifetime card kept everything it did not hand over"
if [ "$REL_CODE" = "200" ]; then
    for MARK in vp-shelf-track vp-shelf-prov vp-shelf-events vp-dates \
        vp-acl-note-band; do
        if grep -qF "$MARK" "$REL"; then
            ok "the Lifetime card still draws $MARK"
        else
            no "the Lifetime card lost $MARK in the extraction"
        fi
    done
fi

# The value with nothing recorded draws no band at all: the hero has
# already said there is nothing to assess, and a second empty state
# under the first is the gap the hero's own guard avoids.
echo "--- the value with nothing to assess draws no clock band"
BARE_TAB="$WORK/bare-viewVerdict.html"
if grep -qF 'vp-vc-clock' "$BARE_TAB"; then
    no "the bare value drew a clock band saying there is no clock"
else
    ok "no band where the hero has already said it"
fi

# -------------------------------------------------- 9. the lean band
# The third band and the last axis to get its working onto the page.
# The check that matters is the same shape as §8's: the band states an
# arithmetic, and the rows that arithmetic is over are printed in the
# same response — so the two have to agree. `stances` counts the
# organisations that cast a stance and *Who says what* lists the
# organisations with occurrences, and on a context built by the
# engine's own query those are the same set.
echo "--- the lean band, against the table it counts"
python3 - "$TAB" <<'PY'
import re
import sys

tab = open(sys.argv[1], encoding='utf-8', errors='replace').read()
fails = 0


def strip(html):
    return re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', ' ', html)).strip()


i = tab.find('vp-vc-lean-body')
if i < 0:
    print('FAIL the Assessment tab drew no lean band on a scored value')
    sys.exit(1)
band = strip(tab[i:i + 2500])

# The sentence, whichever exit wrote it.
known = (
    'organisations assert this is a threat',
    'organisation asserts this is a threat',
    'report this as harmless',
    'reports this as harmless',
    'Neither side reaches',
    'warninglist marks this a false positive',
    'quoted in the line above',
)
hit = [k for k in known if k in band]
print('sentence: %s' % (hit or None))
if hit:
    print('ok   the band names the exit that decided the lean')
else:
    print('FAIL the band drew no sentence this probe recognises')
    fails += 1

# The counts on the band, against the rows of *Who says what*.
counts = re.search(r'(\d+) organisations? asserts? a threat\s+'
                   r'(\d+) reports? it as harmless', band)
# Anchored on the heading, and not on the first `<tbody>` in the
# response: the agreeing layout prints the ledger as a table too, so
# the first one belongs to quality. Its rows also carry attributes,
# which a `<tr>` pattern misses — the first version of this check read
# the ledger's tbody, counted nothing in it, and reported a
# disagreement that was its own.
head = tab.find('Who says what')
rows = (re.search(r'<tbody>(.*?)</tbody>', tab[head:], re.S)
        if head >= 0 else None)
listed = len(re.findall(r'<tr[\s>]', rows.group(1))) if rows else None
print('band counts: %s, organisations listed: %s'
      % (counts.groups() if counts else None, listed))

if counts is None:
    print('FAIL could not read the two counts off the band')
    fails += 1
elif listed is None:
    print('FAIL could not find the organisations table to check them'
          ' against')
    fails += 1
else:
    threat, benign = int(counts.group(1)), int(counts.group(2))
    if threat + benign == listed:
        print('ok   the band counts the organisations the table lists'
              ' (%d + %d = %d)' % (threat, benign, listed))
    else:
        print('FAIL the band counts %d organisations, the table lists'
              ' %d' % (threat + benign, listed))
        fails += 1

    # And the bar is drawn at the share those counts make, so a reader
    # comparing the picture with the words is not being told two
    # different things.
    fill = re.search(r'vp-vc-lean-fill"\s*style="width: (\d+)%', tab)
    if fill is None:
        print('FAIL the band drew counts but no bar')
        fails += 1
    else:
        want = 0 if threat + benign == 0 else round(
            threat * 100 / (threat + benign))
        if abs(int(fill.group(1)) - want) <= 1:
            print('ok   and the bar is drawn at the share they make'
                  ' (%s%%)' % fill.group(1))
        else:
            print('FAIL the bar reads %s%%, the counts make %d%%'
                  % (fill.group(1), want))
            fails += 1

    # Both supermajority marks, always — one of them is the bar the
    # other side would have had to clear.
    marks = re.findall(r'vp-vc-lean-bar"[^>]*style="left: (\d+)%', tab)
    if len(marks) == 2 and sum(int(m) for m in marks) in (99, 100, 101):
        print('ok   both thresholds are marked, and they mirror (%s)'
              % marks)
    else:
        print('FAIL the thresholds drew as %s' % marks)
        fails += 1

# The escalation exit must not restate the rule printed above it.
if 'quoted in the line above' in band:
    if 'Conflict rule' in tab:
        print('ok   the band defers to the conflict rule, which is on'
              ' the page to defer to')
    else:
        print('FAIL the band points at a rule the page does not print')
        fails += 1

sys.exit(1 if fails else 0)
PY
if [ $? -eq 0 ]; then ok "the lean band, read against Who says what"; \
    else no "the lean band, read against Who says what"; fi

# The same rule as the clock band, for the same reason: the hero has
# already said there is nothing to assess.
echo "--- the value with nothing to assess draws no lean band"
if grep -qF 'vp-vc-lean' "$BARE_TAB"; then
    no "the bare value drew a lean band with nothing to decide"
else
    ok "no lean band where there is no lean"
fi

echo
echo "passed: $PASSED   failed: $FAILED"
[ "$FAILED" -eq 0 ]
