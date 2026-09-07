#!/bin/bash
#
# Phase 8a's REST contract, over HTTP.
#
# The shell probe (`09a-contract-live-probe.php`) asserts the model and
# the engine. What only HTTP can see is the **transport**: the status
# code a refusal answers with, the shape of the payload, and whether an
# action is reachable at all. That mattered here — the first
# implementation set 400 on `$this->response` and handed the body to
# `RestResponse->viewData()`, which builds a fresh response and always
# passes 200, so every refusal answered *200 with `saved: false`* and
# told an automated caller the save had happened.
#
# It creates one fork, exercises it, and deletes it. Run it twice.
#
#   bash prd/analyst-profile/09a-contract-http-probe.sh \
#        https://localhost admin@admin.test admin
#
set -u

BASE=${1:-https://localhost}
EMAIL=${2:-admin@admin.test}
PASS=${3:-admin}
JAR=$(mktemp)
OUT=$(mktemp)
trap 'rm -f "$JAR" "$OUT"' EXIT

CHECKS=0
FAILURES=0

say() { printf '%s\n' "$*"; }

# Expect an HTTP code from a request, and print the first error the body
# carries so a failure says what the endpoint thought was wrong.
expect() {
    local want=$1 label=$2 code=$3
    CHECKS=$((CHECKS + 1))
    if [ "$code" = "$want" ]; then
        say "  ok    $label ($code)"
    else
        FAILURES=$((FAILURES + 1))
        say "  FAIL  $label (expected $want, got $code)"
        head -c 300 "$OUT"
        say ''
    fi
}

get() {
    curl -sk -b "$JAR" -H 'Accept: application/json' "$BASE$1" \
        -o "$OUT" -w '%{http_code}'
}

post() {
    curl -sk -b "$JAR" -H 'Accept: application/json' \
        -H 'Content-Type: application/json' -X POST "$BASE$1" \
        -d "${2:-}" -o "$OUT" -w '%{http_code}'
}

# A field out of the last response body.
field() {
    python3 -c "
import json, sys
d = json.load(open('$OUT'))
cur = d
for key in '$1'.split('.'):
    if isinstance(cur, list):
        cur = cur[int(key)]
    else:
        cur = cur.get(key)
    if cur is None:
        break
print('' if cur is None else cur)
" 2>/dev/null
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
    --data-urlencode "_method=POST" \
    --data-urlencode "data[_Token][key]=$(grab key)" \
    --data-urlencode "data[_Token][fields]=$(grab fields)" \
    --data-urlencode "data[_Token][unlocked]=$(grab unlocked)" \
    --data-urlencode "data[User][email]=$EMAIL" \
    --data-urlencode "data[User][password]=$PASS")
if [ "$CODE" != "302" ]; then
    say "FAILED to log in as $EMAIL (HTTP $CODE)"
    exit 2
fi

say ''
say '== the index and the board =='
expect 200 'index answers' "$(get /analystProfiles/index)"
IN_FORCE=$(field in_force.id)
say "        in force: profile $IN_FORCE"
CHECKS=$((CHECKS + 1))
if [ -n "$IN_FORCE" ]; then
    say '  ok    and names the profile in force'
else
    FAILURES=$((FAILURES + 1))
    say '  FAIL  index named no profile in force'
fi

DEFAULT=$(field profiles.0.id)
expect 200 'view of the default' "$(get /analystProfiles/view/$DEFAULT)"
expect 200 'export of the default' \
    "$(get /analystProfiles/export/$DEFAULT)"
expect 404 'a profile that does not exist' \
    "$(get /analystProfiles/view/99999)"

say ''
say '== the ACL check itself is clean =='
CODE=$(get /servers/queryACL/findMissingFunctionNames)
expect 200 'queryACL answers' "$CODE"
CHECKS=$((CHECKS + 1))
if [ "$(tr -d ' \n' < "$OUT")" = '[]' ]; then
    say '  ok    and reports no action without an ACL entry'
else
    FAILURES=$((FAILURES + 1))
    say '  FAIL  actions are missing ACL entries:'
    head -c 400 "$OUT"
fi

say ''
say '== fork, and the refusals on the copy =='
expect 200 'fork the default' \
    "$(post /analystProfiles/fork/$DEFAULT \
        '{"AnalystProfile":{"name":"8a http probe"}}')"
FORK=$(field profile.id)
say "        fork: profile $FORK"
DESC=$(field profile.description | head -c 11)
CHECKS=$((CHECKS + 1))
if [ "$DESC" = 'Forked from' ]; then
    say '  ok    whose description says where it came from'
else
    FAILURES=$((FAILURES + 1))
    say "  FAIL  the fork's description starts \"$DESC\""
fi

expect 200 'a rename saves' \
    "$(post /analystProfiles/edit/$FORK \
        '{"AnalystProfile":{"name":"8a http probe renamed"}}')"
CHECKS=$((CHECKS + 1))
if [ "$(field parameters_changed)" = 'False' ] \
    || [ "$(field parameters_changed)" = 'false' ]; then
    say '  ok    and reports that it moved no parameters'
else
    FAILURES=$((FAILURES + 1))
    say '  FAIL  a rename claimed to change the parameters'
fi

expect 200 'a weight saves' \
    "$(post /analystProfiles/edit/$FORK \
        '{"AnalystProfile":{"parameters":{"signals":{"reporting.independent_orgs":{"points":{"per_org":9}}}}}}')"
REV=$(field profile.revision)
say "        revision now $REV"

# Every one of these must be refused, and refused the same way.
expect 403 'medium above high' \
    "$(post /analystProfiles/edit/$FORK \
        '{"AnalystProfile":{"parameters":{"thresholds":{"quality_bands":{"high":30,"medium":60}}}}}')"
expect 403 'a band beyond the attainable bound' \
    "$(post /analystProfiles/edit/$FORK \
        '{"AnalystProfile":{"parameters":{"thresholds":{"quality_bands":{"high":900}}}}}')"
expect 403 'a malformed paste' \
    "$(post /analystProfiles/edit/$FORK \
        '{"AnalystProfile":{"parameters_json":"{\"signals\": [}"}}')"
CHECKS=$((CHECKS + 1))
if [ -n "$(field data.parse.line)" ]; then
    say "  ok    naming the line ($(field data.parse.line))"
else
    FAILURES=$((FAILURES + 1))
    say '  FAIL  the parse error carried no line'
fi
expect 403 'a float where the invariant needs an integer' \
    "$(post /analystProfiles/edit/$FORK \
        '{"AnalystProfile":{"parameters":{"signals":{"reporting.independent_orgs":{"points":{"per_org":1.5}}}}}}')"
expect 403 'a supermajority that is not a majority' \
    "$(post /analystProfiles/edit/$FORK \
        '{"AnalystProfile":{"parameters":{"thresholds":{"lean_supermajority":0.4}}}}')"

# And the document is untouched by all of them.
get "/analystProfiles/view/$FORK" >/dev/null
AFTER=$(field profile.revision)
CHECKS=$((CHECKS + 1))
if [ "$AFTER" = "$REV" ]; then
    say "  ok    and none of the refusals moved the revision ($AFTER)"
else
    FAILURES=$((FAILURES + 1))
    say "  FAIL  revision moved from $REV to $AFTER during refusals"
fi

say ''
say '== the simulator =='
V=$(printf %s 8.8.8.8 | base64 -w0)
expect 200 'simulate with no candidate change' \
    "$(get "/analystProfiles/simulate/$FORK?value=$V")"
CHECKS=$((CHECKS + 1))
if [ "$(field detail.sums.ok)" = 'True' ]; then
    say '  ok    and both ledger columns sum to their own quality'
else
    FAILURES=$((FAILURES + 1))
    say '  FAIL  a ledger column did not add up'
fi
say "        context builds: $(field context_builds)"

expect 200 'simulate a weight change' \
    "$(post "/analystProfiles/simulate/$FORK?value=$V" \
        '{"AnalystProfile":{"parameters":{"signals":{"lifecycle.warninglist":{"enabled":false}}}}}')"
CHECKS=$((CHECKS + 1))
if [ "$(field context_builds)" = '1' ]; then
    say '  ok    sharing one context, because the exclusions match'
else
    FAILURES=$((FAILURES + 1))
    say "  FAIL  expected one context build, got $(field context_builds)"
fi

expect 200 'simulate an exclusions change' \
    "$(post "/analystProfiles/simulate/$FORK?value=$V" \
        '{"AnalystProfile":{"parameters":{"exclusions":{"orgs.own":{"enabled":true}}}}}')"
CHECKS=$((CHECKS + 1))
if [ "$(field context_builds)" = '2' ]; then
    say '  ok    building two, because the candidate changed the rows'
else
    FAILURES=$((FAILURES + 1))
    say "  FAIL  expected two context builds, got $(field context_builds)"
fi

say ''
say '== the comparison set =='
expect 200 'pin a value' "$(post /analystProfiles/pin/$V)"
expect 200 'pin it again' "$(post /analystProfiles/pin/$V)"
CHECKS=$((CHECKS + 1))
if [ "$(field comparison_set.1)" = '' ]; then
    say '  ok    without duplicating it'
else
    FAILURES=$((FAILURES + 1))
    say '  FAIL  the set holds the same value twice'
fi
expect 200 'unpin it' "$(post /analystProfiles/unpin/$V)"
CHECKS=$((CHECKS + 1))
if [ "$(field comparison_set.0)" = '' ]; then
    say '  ok    and the set is empty again'
else
    FAILURES=$((FAILURES + 1))
    say '  FAIL  the value survived being unpinned'
fi

say ''
say '== the guards =='
expect 403 'the instance default cannot be deleted' \
    "$(post /analystProfiles/delete/$DEFAULT)"
expect 405 'forking is not a GET' "$(get /analystProfiles/fork/$DEFAULT)"

say ''
say '== leaving the instance as it was =='
expect 200 'delete the fork' "$(post /analystProfiles/delete/$FORK)"
expect 404 'and it is gone' "$(get /analystProfiles/view/$FORK)"
CODE=$(get /analystProfiles/index)
CHECKS=$((CHECKS + 1))
if [ "$(field in_force.id)" = "$IN_FORCE" ]; then
    say "  ok    the profile in force is $IN_FORCE again"
else
    FAILURES=$((FAILURES + 1))
    say "  FAIL  in force is now $(field in_force.id), was $IN_FORCE"
fi

say ''
say "$CHECKS checks, $FAILURES failures"
exit $((FAILURES == 0 ? 0 : 1))
