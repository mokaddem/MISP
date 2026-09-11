#!/bin/bash
#
# Phase 8c's pages, over HTTP.
#
# The render harness (`09c-wiring-harness.php`) asserts the markup with
# no session. What only HTTP can see is the **seam**: that a browser
# gets the template and a REST caller still gets the identical JSON,
# that a write redirects with a flash rather than rendering something,
# that the confirm stands between a fork and an occupied slot, and that
# the form the page draws posts back through CSRF intact.
#
# It creates one fork, exercises it, and deletes it. Run it twice.
#
#   bash prd/analyst-profile/09c-wiring-http-probe.sh \
#        https://localhost admin@admin.test admin
#
set -u

BASE=${1:-https://localhost}
EMAIL=${2:-admin@admin.test}
PASS=${3:-admin}
JAR=$(mktemp)
OUT=$(mktemp)
HDR=$(mktemp)

CHECKS=0
FAILURES=0

say() { printf '%s\n' "$*"; }

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

holds() {
    local label=$1 needle=$2
    CHECKS=$((CHECKS + 1))
    if grep -qF -- "$needle" "$OUT"; then
        say "  ok    $label"
    else
        FAILURES=$((FAILURES + 1))
        say "  FAIL  $label (no '$needle' in the body)"
    fi
}

lacks() {
    local label=$1 needle=$2
    CHECKS=$((CHECKS + 1))
    if grep -qF -- "$needle" "$OUT"; then
        FAILURES=$((FAILURES + 1))
        say "  FAIL  $label (found '$needle')"
    else
        say "  ok    $label"
    fi
}

html() {
    curl -sk -b "$JAR" -c "$JAR" "$BASE$1" -o "$OUT" -w '%{http_code}'
}

json() {
    curl -sk -b "$JAR" -H 'Accept: application/json' "$BASE$1" \
        -o "$OUT" -w '%{http_code}'
}

# A browser POST carries the CSRF token *and* the field hash the page
# it came from generated. Posting a token with an empty hash is what a
# script does, not what a browser does, and `SecurityComponent`
# blackholes it — so every post here reads the real hidden fields out
# of the real form, which is also the only way this probe can tell that
# the page emitted a usable one.
PAGEFILE=$(mktemp)
trap 'rm -f "$JAR" "$OUT" "$HDR" "$PAGEFILE"' EXIT

# hidden_fields <page> <action substring>  -> one `name=value` per line
hidden_fields() {
    curl -sk -b "$JAR" -c "$JAR" "$BASE$1" -o "$PAGEFILE"
    python3 - "$PAGEFILE" "$2" <<'PY'
import html
import re
import sys

page = open(sys.argv[1], encoding='utf-8', errors='replace').read()
wanted = sys.argv[2]
for form in re.finditer(r'<form[^>]*action="([^"]*)"[^>]*>(.*?)</form>',
                        page, re.S):
    if wanted not in html.unescape(form.group(1)):
        continue
    for field in re.finditer(r'<input[^>]*>', form.group(2)):
        tag = field.group(0)
        if 'type="hidden"' not in tag:
            continue
        name = re.search(r'name="([^"]*)"', tag)
        value = re.search(r'value="([^"]*)"', tag)
        if not name:
            continue
        field = html.unescape(name.group(1))
        # The security envelope only. The editor's own hidden inputs
        # are in this form too — the `false` partner of every
        # checkbox, every `__present` marker — and carrying them
        # without the checkboxes beside them would post a document
        # with every signal switched off.
        if field != '_method' and not field.startswith('data[_Token]'):
            continue
        print(field + '=' + html.unescape(value.group(1) if value else ''))
    break
PY
}

# form_post <page> <action> [extra name=value ...]
form_post() {
    local page=$1 url=$2
    shift 2
    local args=()
    local line
    while IFS= read -r line; do
        [ -n "$line" ] && args+=(--data-urlencode "$line")
    done < <(hidden_fields "$page" "$url")
    if [ ${#args[@]} -eq 0 ]; then
        say "  FAIL  no form on $page posts to $url"
        FAILURES=$((FAILURES + 1))
        CHECKS=$((CHECKS + 1))
        printf '000'
        return
    fi
    local pair
    for pair in "$@"; do
        args+=(--data-urlencode "$pair")
    done
    curl -sk -b "$JAR" -c "$JAR" -X POST "$BASE$url" \
        "${args[@]}" -D "$HDR" -o "$OUT" -w '%{http_code}'
}

token_from() {
    hidden_fields "$1" "$2" | grep '^data\[_Token\]\[key\]=' \
        | head -1 | sed 's/^[^=]*=//'
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
    say "FAILED to log in as $EMAIL (HTTP $CODE)"
    exit 2
fi

VALUE=$(printf '%s' '8.8.8.8' | base64 | tr '+/' '-_' | tr -d '=')

say ''
say '== a browser gets pages =='
expect 200 'index renders' "$(html /analystProfiles/index)"
holds 'and it is the workbench, not JSON' 'class="wb"'
holds 'the rail names the resolution order' 'Resolution order'
holds 'the stylesheet is asked for' 'analyst-profile.css'
holds 'and the shared palette with it' 'value-palette.css'

DEFAULT=$(grep -o '/analystProfiles/view/[0-9]*' "$OUT" | head -1 \
    | grep -o '[0-9]*$')
say "        the default is profile $DEFAULT"

expect 200 'view renders' "$(html /analystProfiles/view/$DEFAULT)"
lacks 'and carries no editable field' 'data-ap-field'
expect 200 'edit renders' "$(html /analystProfiles/edit/$DEFAULT)"
holds 'and carries them' 'data-ap-field'
holds 'the rail says which axis each section configures' 'wb-rail-ax'
expect 200 'edit with a value on the bench' \
    "$(html "/analystProfiles/edit/$DEFAULT?value=$VALUE")"
holds 'the bench names the three axes' '>relevance<'
holds 'and draws the shelf' 'ax-fill'
expect 200 'simulate renders' \
    "$(html "/analystProfiles/simulate/$DEFAULT?value=$VALUE")"
holds 'and an unedited candidate says nothing moved' 'No change.'
expect 200 'import renders its form' "$(html /analystProfiles/import)"
holds 'with somewhere to paste a document' 'AnalystProfile][json]'

say ''
say '== a link can open one section =='
expect 200 'edit?section=exclusions' \
    "$(html "/analystProfiles/edit/$DEFAULT?section=exclusions")"
CHECKS=$((CHECKS + 1))
if python3 -c "
import re, sys
page = open('$OUT', encoding='utf-8', errors='replace').read()
open_panes = re.findall(r'wb-sec is-open\"\s*data-sec=\"([a-z]+)\"', page)
sys.exit(0 if open_panes == ['exclusions'] else 1)
"; then
    say '  ok    and the exclusions pane is the only one open'
else
    FAILURES=$((FAILURES + 1))
    say '  FAIL  the exclusions pane did not open on its own'
fi

say ''
say '== the same array, still JSON for a REST caller =='
expect 200 'index over Accept: application/json' \
    "$(json /analystProfiles/index)"
CHECKS=$((CHECKS + 1))
if head -c 1 "$OUT" | grep -q '{'; then
    say '  ok    and it is a JSON object, not a page'
else
    FAILURES=$((FAILURES + 1))
    say '  FAIL  REST did not get JSON'
fi
holds 'carrying the standing per row' '"standing"'

say ''
say '== the ACL check is still clean =='
expect 200 'queryACL answers' \
    "$(json /servers/queryACL/findMissingFunctionNames)"
CHECKS=$((CHECKS + 1))
if [ "$(tr -d ' \n' < "$OUT")" = '[]' ]; then
    say '  ok    no action is missing an ACL entry'
else
    FAILURES=$((FAILURES + 1))
    say '  FAIL  actions are missing ACL entries:'
    head -c 400 "$OUT"
fi

say ''
say '== fork, from the page rather than the API =='
CODE=$(form_post /analystProfiles/index \
    "/analystProfiles/fork/$DEFAULT")
expect 302 'the fork POST redirects' "$CODE"
FORK=$(grep -i '^location:' "$HDR" | grep -o '/analystProfiles/edit/[0-9]*' \
    | grep -o '[0-9]*$')
CHECKS=$((CHECKS + 1))
if [ -n "$FORK" ]; then
    say "  ok    and lands on the editor of the copy (profile $FORK)"
else
    FAILURES=$((FAILURES + 1))
    say '  FAIL  the fork did not redirect to an editor'
    head -c 300 "$HDR"
    exit 1
fi

CODE=$(form_post /analystProfiles/index \
    "/analystProfiles/fork/$DEFAULT")
expect 200 'forking again stops to ask' "$CODE"
holds 'and names the profile it would disable' 'Replace'
holds 'saying that replace never deletes' 'never deletes'

say ''
say '== saving one weight =='
BEFORE=$(json "/analystProfiles/view/$FORK" > /dev/null; \
    python3 -c "
import json
d = json.load(open('$OUT'))
print(d['profile']['revision'])
")
CODE=$(form_post "/analystProfiles/edit/$FORK" \
    "/analystProfiles/edit/$FORK" \
    'data[AnalystProfile][parameters][signals][reporting.independent_orgs][points][cap]=11')
expect 302 'the save redirects' "$CODE"
json "/analystProfiles/view/$FORK" > /dev/null
AFTER=$(python3 -c "
import json
d = json.load(open('$OUT'))
print(d['profile']['revision'])
")
CHECKS=$((CHECKS + 1))
if [ "$AFTER" -gt "$BEFORE" ]; then
    say "  ok    and the revision moved ($BEFORE -> $AFTER)"
else
    FAILURES=$((FAILURES + 1))
    say "  FAIL  the revision did not move ($BEFORE -> $AFTER)"
fi
html "/analystProfiles/edit/$FORK" > /dev/null
holds 'and the pane shows the number that was saved' 'value="11"'

say ''
say '== a paste that will not parse =='
CODE=$(form_post "/analystProfiles/edit/$FORK" "/analystProfiles/edit/$FORK" \
    'data[AnalystProfile][parameters_json]={"signals": [')
expect 200 'the editor comes back rather than redirecting' "$CODE"
holds 'with the parse error on it' 'Not saved.'
holds 'and the paste still in the box' '{&quot;signals&quot;: ['
json "/analystProfiles/view/$FORK" > /dev/null
KEPT=$(python3 -c "
import json
d = json.load(open('$OUT'))
print(d['profile']['revision'])
")
CHECKS=$((CHECKS + 1))
if [ "$KEPT" = "$AFTER" ]; then
    say '  ok    and the stored profile is untouched'
else
    FAILURES=$((FAILURES + 1))
    say "  FAIL  the refused paste moved the revision ($AFTER -> $KEPT)"
fi

say ''
say '== the bench recomputes on its own =='
KEY=$(token_from "/analystProfiles/edit/$FORK?value=$VALUE" \
    "/analystProfiles/edit/$FORK")
CODE=$(curl -sk -b "$JAR" -c "$JAR" -X POST \
    -H 'X-Requested-With: XMLHttpRequest' \
    "$BASE/analystProfiles/simulate/$FORK?value=$VALUE" \
    --data-urlencode "data[_Token][key]=$KEY" \
    --data-urlencode 'data[_Token][fields]=' \
    --data-urlencode 'data[_Token][unlocked]=' \
    --data-urlencode 'data[AnalystProfile][parameters][signals][reporting.independent_orgs][points][cap]=3' \
    -o "$OUT" -w '%{http_code}')
expect 200 'the fragment answers' "$CODE"
lacks 'and it is a fragment, not a page' '<html'
holds 'the assessment head is in it' 'bench-ax3'
holds 'and a row is marked as changed' 'changed'

say ''
say '== enable, disable, delete =='
CODE=$(form_post /analystProfiles/index "/analystProfiles/disable/$FORK")
expect 302 'disable redirects' "$CODE"
CODE=$(form_post /analystProfiles/index "/analystProfiles/enable/$FORK")
expect 302 'enable redirects' "$CODE"
CODE=$(form_post /analystProfiles/index "/analystProfiles/delete/$FORK")
expect 302 'delete redirects' "$CODE"
expect 404 'and the profile is gone' "$(html /analystProfiles/view/$FORK)"

say ''
say '== the instance default is still there =='
expect 200 'the default survived the round' \
    "$(html /analystProfiles/view/$DEFAULT)"

say ''
say "$CHECKS checks, $FAILURES failures"
[ "$FAILURES" -eq 0 ] || exit 1
