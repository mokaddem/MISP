# PRD: Analyst Profile — phase 1, the store

**Built 2026-09-07.** Depends on nothing; gates every other phase. The
picture is [`01-profile.md`](01-profile.md); decisions D1–D5 are what this
phase implements, and it closed **Q7 as D13** (§3.3).

What shipped: migration 160 and the table in all three places
(`AppModel::DB_CHANGES`, `INSTALL/MYSQL.sql`, `db_schema.json`),
`app/Model/AnalystProfile.php`, and the shipped default at
`app/files/analyst-profiles/default-v1.json`. **The controller did not** —
see §6, amended.

The exit criterion is met and checked:
[`02-store-resolve-harness.php`](02-store-resolve-harness.php) runs 33
assertions with no database — the resolution order, the disabled-default
`null`, the per-request cache, the ownership triple, D13's permission matrix,
and the shipped default's inertness. §7 records what still needs a live
instance.

## 1. What ships

One table, one model, one resolution function, one shipped default, and a fork
action. No UI — that is phase 8. No scoring — that is phase 2.

The exit criterion is narrow and testable: **`AnalystProfile::resolveFor($user)`
returns exactly one profile for every user on the instance, including a user
whose org has no profile and who has none themselves.**

## 2. The table

```sql
CREATE TABLE IF NOT EXISTS `analyst_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uuid` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `org_id` int(11) DEFAULT NULL,
  `default` tinyint(1) NOT NULL DEFAULT 0,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `version` int(11) NOT NULL DEFAULT 1,
  `revision` int(11) NOT NULL DEFAULT 1,
  `parameters` longtext DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `name` (`name`),
  KEY `user_id` (`user_id`),
  KEY `org_id` (`org_id`),
  KEY `default` (`default`),
  KEY `enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Notes on the choices, since each was available another way:

- **`utf8mb4`**, not `decaying_models`' `utf8mb3`. Profile names and
  descriptions are analyst-authored free text and will contain emoji sooner or
  later. `collections` (2.5) already uses `utf8mb4`; follow the newer table.
- **`parameters` is `longtext`**, not `text`. A reference map with a few
  hundred org uuids plus a full TTL table will exceed `text`'s 64 KB less
  comfortably than it looks, and `decaying_models`' `text` is a limit inherited
  rather than chosen.
- **`version` is an `int`**, not `decaying_models`' `varchar(255)`. That column
  is compared with `>` in `DecayingModel::update()` (`DecayingModel.php:180`).
  PHP 8 compares two numeric strings numerically, so plain integers there are
  safe — `'10' > '9'` is true. A **dotted** version is not: `'1.10' > '1.9'`
  evaluates to `false`, so a profile at `1.9` would never be updated by a
  shipped `1.10`. An `int` column forecloses the hazard rather than relying on
  every future author putting integers in a `varchar`.
- **`version` and `revision` are two counters, deliberately** (decided
  2026-09-03, `review-2026-09-02.md` B1). `version` matches the shipped file
  and is moved only by `updateDefaults()` — the upstream match key.
  `revision` increments on any change to `parameters`, from any editor, and
  is the discriminator phase 10's materialised assessments key on
  (`11-restsearch.md` §4.2). One column doing both jobs collides: a site
  admin edits the default and its counter moves, so a shipped update either
  never applies or applies and cannot be told from a no-op. With two, the
  shipped v3 still applies (`version` compare) and the local edit is still
  visible (`revision` moved).
- **No `all_orgs`** (D4). It plays no part in resolution under D3.
- **No `parent_uuid`** (D5). Forking carries no lineage.
- **`default` is a reserved word** in MySQL and needs backticks in every
  query. `decaying_models`, `warninglists` and `dashboards` all already have a
  column named this, so the precedent is established and consistency wins over
  avoiding the quoting.

### 2.1 The ownership triple

`user_id`, `org_id` and `default` encode the scope. **Exactly one is set**:

| Scope | `user_id` | `org_id` | `default` |
|---|---|---|---|
| Instance default | `NULL` | `NULL` | `1` |
| Organisation | `NULL` | set | `0` |
| User | set | `NULL` | `0` |

A user profile does **not** also carry its owner's `org_id`. It is tempting —
it would make "all profiles in my org" one query — but it makes the triple
ambiguous, and the org is reachable through `users.org_id` anyway. Enforced in
`beforeValidate()` rather than by a constraint, because MySQL cannot express
"exactly one of three is non-null" portably.

### 2.2 Migration

`AppModel::DB_CHANGES` (`AppModel.php:75`) is a flat list of migration numbers
with a `requires_logout` flag; the latest is `159 => false`. This phase adds
`160 => false` and a `case 160:` in `updateDatabase()` (`AppModel.php:411`,
switch cases through `2724`) pushing the `CREATE TABLE` above onto `$sqlArray`.

`requires_logout` is `false` — nothing about a new table invalidates a session.

Add the table to `INSTALL/MYSQL.sql` in alphabetical position (between
`allowedlist` and `analyst_data_blocklists`) so a fresh install and an upgraded
one converge. Run `Admin schemaDiagnostics` afterwards; it compares the live
schema against `db_schema.json`, which also needs the new table.

## 3. The model

`app/Model/AnalystProfile.php`.

```php
public function resolveFor(array $user)          // exactly one profile, always
public function fetchProfiles(array $user, array $filters = [])
public function fetchProfile(array $user, $id)
public function isEditableByCurrentUser(array $user, array $profile)
public function forkProfile(array $user, $id, $name = null)
public function updateDefaults($force = false)   // load shipped JSON
public function validateParameters($parameters)  // phase 2 owns the contract
```

### 3.1 `resolveFor()` — the one function every reader calls

```
SELECT * FROM analyst_profiles
WHERE enabled = 1 AND (
      (user_id = :me)
   OR (org_id  = :my_org)
   OR (`default` = 1))
ORDER BY (user_id IS NOT NULL) DESC, (org_id IS NOT NULL) DESC
LIMIT 1
```

One query, one row, no application-side fallback chain. The `ORDER BY` encodes
D3's nearest-owner-wins: a user-owned row sorts first, then org-owned, then the
default.

**Built with the tie-break in PHP instead, 2026-09-07.** At most three rows
can match — one per scope, because §3.1's one-enabled-profile-per-owner rule
caps each — so the implementation reads them and ranks them rather than
ordering in SQL. CakePHP 2 quotes identifiers inside an `order` string, so
`(user_id IS NOT NULL) DESC` there is a quoting hazard rather than a readable
expression, and it is the kind that fails at runtime on a page nobody tests
twice. **What this section actually forbids is preserved**: it is still one
statement, not three sequential ones walking the scopes. Asserted as such —
the harness counts the statements resolveFor() issues, including across 27
panel calls.

**It must never return nothing.** Three ways it could, each handled here rather
than by every caller:

- **The default is disabled.** An admin can disable it. Resolution then falls
  through to nothing, and the Verdict tab has no profile — which under §5.3 of
  the main PRD means *no profile name and no score*. That is a legitimate
  state, and it is the honest reading of "the instance has turned verdict
  scoring off". `resolveFor()` returns `null` and the page renders the UNKNOWN
  disposition with no ledger. **Callers must handle `null`**; it is not an
  error path.
- **Two user rows for one user.** Prevented by a validation rule, not a unique
  key — a partial unique index on `user_id` where `default = 0` is not
  portable. `beforeValidate()` rejects a second enabled profile for the same
  owner.
- **A profile with unparseable `parameters`.** Returns the row; the *engine*
  degrades (phase 2), because a store that silently substitutes a different
  profile than the one the hero names would break §5.3's honesty rule.

Cache the resolution per request. It is called once per panel and there are up
to twenty-seven panels on a page load.

### 3.2 `isEditableByCurrentUser()`

Mirrors `DecayingModel::isEditableByCurrentUser()` (`DecayingModel.php:192`):

```
site admin                                          → always
the shipped default (`default` = 1)                 → site admin only
an org profile, and the user is in that org         → per Q7
the user's own profile                              → the user
```

### 3.3 Q7 — which permission gates ownership: **decided 2026-09-07, D13**

**No new permission flag.** A user profile needs no grant, an organisation
profile needs `perm_admin`, and the shipped default stays site-admin only —
the third candidate below, as recommended.

Two reasons, and the second is the one that settled it:

- **The common case needs no grant.** An analyst tuning their own weights
  changes only their own page, which is the same reasoning that leaves
  `user_settings` ungated. A feature that requires an admin action before
  anyone can try it does not get tried.
- **It is the reversible direction.** Adding `perm_analyst_profile` later is
  additive — a column, a role-seed default of `0`, an ACL entry. Shipping the
  flag and then withdrawing it is a migration plus a role-seed change plus an
  instance's granted roles to unpick. When two options differ mainly in
  confidence, take the one whose reversal is cheap.

The counter-argument stands and is not dismissed: MISP's convention is a
`perm_*` flag per capability, and `perm_analyst_data` (2.5) shows the
convention is live rather than legacy. But D13 does not break it for a new
capability — it declines to *invent* a capability for something that already
maps onto org admin, which is how MISP gates most other configure-for-my-org
actions.

`perm_decaying` was the third option and is rejected outright: riding it would
silently widen every existing grant of it, and an instance that gave it out
narrowly for decay models never consented to profile ownership. Worth noting
it is `'readonlyenabled' => true` (`Role.php:323`), so it is considered safe
for read-only roles — which would have made it a *good* home if it were not
already spoken for.

Implemented in `AnalystProfile::isEditableByCurrentUser()`, with the matrix
asserted in the harness. `isReadableByCurrentUser()` is its companion and was
not in the spec: `fetchProfile()` needs it, because a profile is readable by
its owner, its org and everyone (the default) but editable by fewer.

**The three candidates, as they were weighed:**

- **Ride `perm_decaying`.** No schema change, and it is semantically the
  closest flag MISP has — it already means *"may own a scoring model"*. Risk:
  an instance that granted it narrowly for decay now grants profile ownership
  too, silently.
- **A new `perm_analyst_profile`.** Precise, and a schema change plus a row in
  `roles`' seed `INSERT` (`INSTALL/MYSQL.sql:1874`) plus an ACL entry. Every
  existing role defaults to `0`, so nobody can own a profile until an admin
  grants it — which is safe and also means the feature ships switched off.
- **`perm_admin` for org-scoped, nothing for user-scoped.** A user profile
  affects only its owner's page, so arguably needs no permission at all; an org
  profile affects colleagues, so it is an org-admin action.

**Chosen: the third**, for the reasons above. `queryACL/findMissingFunctionNames`
is still owed, and it is owed by **phase 8** rather than here, because this
phase shipped no controller for it to check.

## 4. The shipped default

`app/files/analyst-profiles/*.json`, mirroring
`app/files/misp-decaying-models/models/`. One file for v1.

`updateDefaults()` follows `DecayingModel::update()` (`DecayingModel.php:164`)
with its version-comparison bug fixed:

```
for each shipped file:
    if a row with that uuid exists:
        if $force or shipped.version > existing.version:   # int compare
            overwrite name, description, parameters, version
            bump `revision`                                # parameters changed
            leave `enabled` alone                          # admin's choice
    else:
        insert with `default` = 1, `enabled` = 1
```

An upstream update **overwrites local edits to the default**, and that is the
rule rather than an accident: the default tracks upstream, and an admin who
wants durable divergence forks (§3.2 makes fork the path for everyone else
already). The overwrite is detectable — a `revision` that moved since the
last import means the default had local edits — so the update log names it
instead of silently absorbing it.

Three things this must not do:

- **Never touch a fork.** Forks have their own uuid and are invisible to this
  loop. That is D5 working as intended.
- **Never re-enable a disabled default.** An admin who disabled verdict scoring
  should not have it switched back on by an upgrade.
- **Never delete a default whose file has gone.** A shipped profile removed
  upstream stays in the database, because an org may have forked from it and
  the hero may still name it. It becomes an ordinary row that nothing updates.

**Corrected 2026-09-07, on building it.** This said *"called from `Admin
runUpdates`, the same as `DecayingModelController::update()`"*, and those are
two different things — neither of which is where MISP actually loads shipped
JSON. `DecayingModel::update()` has exactly one caller, its own controller
(`DecayingModelController.php:19`), which is why a decaying model only
appears after somebody presses a button.

The real registry is **`Server::updateJSON()`** (`Server.php:5368`): a list
of models, each asked for `update()`, driven by `cake Admin updateJSON` and
by the UI's *Update all JSON structures*. `AnalystProfile` joins
`Galaxy`, `Noticelist`, `Warninglist`, `Taxonomy`, `ObjectTemplate` and
`ObjectRelationship` there, and `update($force = false)` is the
registry-facing name that delegates to `updateDefaults()`.

That choice matters more than a naming detail: without it a fresh instance
has **no** profile, so `resolveFor()` returns `null` for every user and the
Assessment tab is blank out of the box — technically the honest "scoring is
off" state of §3.1, but arrived at by omission rather than by an admin's
decision. Following the decaying-model precedent literally would have shipped
exactly that.

*Noticed in passing and left alone:* `AdminShell::updateJSONLite()` calls
`$this->Server->updateJSON(true)` while that method takes no parameter, so
lite and full do identical work. Pre-existing, harmless, and not this
phase's.

### 4.1 What the default profile contains

Its `parameters` must reproduce **the fixture's own verdicts**, near enough to
be recognisable. That is the acceptance test for phases 2–6 and the reason the
default has to be authored against real numbers rather than invented: the four
demo values are the regression set, and `185.234.219.24` scoring 84 under the
shipped default is how anyone knows the engine works.

Exact weights are phase 2's output. The name is `default-v1`, **not**
`default-v3` — the fixture's string is a literal from an artboard, and shipping
a real profile that claims to be a third version is a lie about its history.
Phase 9 changes the fixture and the copy together.

## 5. Fork

One action, `forkProfile($user, $id, $name)`:

```
read the source profile (must be readable by $user)
copy name → "<source name> (copy)" unless $name given
copy description, parameters verbatim
new uuid, version = 1, revision = 1
owner = $user's id            (a user fork; an org fork is the same with org_id)
enabled = 1
default = 0
```

**Fork is one click** (D5's consequence, `00-discovery.md` §9.1): the shipped
default is uneditable by an ordinary analyst, so fork is the *only* way in.
`DecayingModelController` offers `export` + `import` instead, which is a round
trip through a file, and is why nobody forks a decaying model either.

**A user may hold one enabled profile.** Forking again while one exists is
either refused with a pointer to the existing one, or offered as "replace" —
phase 8's UI decision. The model enforces the invariant; the controller decides
the message.

## 6. REST and export/import

**Amended 2026-09-07: the controller and its ACL entries moved to phase 8.**
This section had the views land in phase 8 while the ACL entries landed here,
so that phase 8 would not open with an ACL gap. Built, that inverts: MISP's
ACL is a whitelist keyed by controller and action, so entries added now would
name actions that do not exist — dead rows that `findMissingFunctionNames`
does not catch, because it reports the opposite direction. The gap this was
guarding against is one `queryACL` run at the top of phase 8.

`AnalystProfilesController` (**phase 8**; the actions are listed here because
the model exposes exactly what they need):

| Action | Method | Notes |
|---|---|---|
| `index` | GET | own, org's, and the default |
| `view/:id` | GET | |
| `add` | POST | subject to Q7 |
| `edit/:id` | POST | subject to §3.2 |
| `delete/:id` | POST | never the default |
| `fork/:id` | POST | §5 |
| `enable/:id`, `disable/:id` | POST | |
| `export/:id` | GET | the JSON, with uuid and version |
| `import` | POST | new uuid on import unless the uuid is absent locally |
| `update` | POST | §4, site admin only |

`import` deliberately re-uuids a profile that already exists locally, rather
than overwriting. An imported profile is a *copy of someone else's judgement*,
and overwriting a local row of the same uuid would silently rewrite the profile
the hero has been naming.

## 7. Verification

**Run 2026-09-07, in two passes.** Everything passes except 2b, which
cannot run here (see below), and 7, which moved to phase 8 with the
controller (§6).

- **Without a database**:
  [`02-store-resolve-harness.php`](02-store-resolve-harness.php), 33 checks.
- **Against the dev instance**, once migration 160 was applied:
  [`02-store-live-probe.php`](02-store-live-probe.php), 36 checks, run twice
  to prove it leaves the instance as it found it.

**The live pass earned its keep: it found two defects the harness could not
see, both in the same place.** The harness stubs `save()` to return `true`,
so it could not notice that *no save of an existing profile worked at all*.

1. **`beforeValidate()` seeded columns on update as well as create.** It
   defaulted `default` to `0` whenever the key was absent, and CakePHP writes
   the fields it is handed — so a partial save of the shipped default, a
   rename or phase 8's edit form, would have quietly stopped it being the
   default. Now seeded only when there is no `id`.
2. **`__validateOwnership()` judged the submitted fields rather than the
   resulting row.** A save carrying `id`, `name` and `parameters` names none
   of the three ownership columns, so the rule counted zero owners and
   rejected it. Every partial update failed. It now returns early when a save
   does not touch ownership, and merges with the stored row when it does.

**And a third, which is why the first two were invisible.**
`updateDefaults()` reported `'updated'` without checking the save result, so
the shipped-default update path was failing validation and telling its caller
it had succeeded. It now logs the validation errors and returns `'failed'`.
That was the bug that made the other two hard to see rather than merely
present — and it is the one worth remembering, because a phase that returns
an outcome map is inviting exactly this.

A fourth, found while fixing them: **`Model::create()` before an update
re-seeds `$this->data` from the column defaults**, so `default` arrives as
`0` and merges over the stored `1`. `create()` is for inserts. The update
branch sets `$this->id` and nothing else.

| # | What | Status |
|---|---|---|
| # | What | Status |
|---|---|---|
| 1 | Lint over the new model | **pass** — `php -l` on `AnalystProfile.php` and `AppModel.php`. `parallel-lint` is not installed in this worktree (`app/Vendor` absent), so the project's usual command could not run |
| 2a | `Admin runUpdates` applies migration 160 | **pass** — run 2026-09-07; `db_version` 159 → 160 and the table exists |
| 2b | `Admin schemaDiagnostics` — no diff | **not runnable through this instance, and the reason is worth keeping.** The dev mount covers `app/` only, so the container compares the live schema against **its image's** `db_schema.json` — `db_version 143`, not this tree's 160 — and root-level files never reach it. Two consequences: the check would report nothing about `analyst_profiles` however correct the table was, and it is *silent* rather than wrong, because `schemaDiagnostics` prints only tables it finds in the expected schema. A missing table *is* a critical diagnostic (`Server.php:3676`, `error_type => missing_table`); it just cannot fire for a table the expected schema has never heard of. Whoever runs this properly should also know the instance carries four unrelated diffs already (`bookmarks.url`, `galaxy_clusters.description`, `roles.perm_sync_authoritative`, `taxii_servers.skip_proxy`), so the assertion is *no new diff*, never *no diff* |
| 3 | `resolveFor()` for four user shapes | **pass** — harness, and again live against the instance's own five users, each resolving to exactly one profile. One statement each rather than the four the item assumed |
| 4 | The default disabled with nothing else: `null`, no throw | **pass** — harness and live |
| 5 | Fork the default as a non-admin; the copy is editable, the original is not | **pass** — live, as user 2 (no `perm_admin`, no `perm_site_admin`). Also asserted: a fresh uuid with no lineage, counters reset, parameters verbatim, the fork immediately being what its owner resolves to, a *second* enabled fork refused, and an org fork refused without `perm_admin` |
| 6 | `updateDefaults()` twice is a no-op; a version bump applies and leaves `enabled` alone | **pass** — live: `created`, then `skipped`, then `updated` after a version bump, with a disabled default left disabled and `revision` moved. This is the item that found all three defects above |
| 7 | `queryACL/findMissingFunctionNames` | **moved to phase 8** — no controller shipped |
| 8 | Two of three ownership columns set: rejected | **pass** — harness (six combinations) and live through CakePHP's own validation, which is where the merged-row rule above had to be got right |
| 9 | Rename leaves `revision`; editing `parameters` bumps it; a shipped update over a local edit names the overwrite | **pass** — live: a rename leaves `revision`, `bumpRevision()` moves it by one and leaves `version` alone. `overwrote_edits` is the outcome for the local-edit case |

**Two measurements about the dev loop, both of which cost time here.**

**`app/` is not the whole of the dev mount, and `app/files/` is not in the
source half of it.** The mount list is per-directory —
`app/Console`, `app/Controller`, `app/Locale`, `app/Model`, `app/View`,
`app/webroot` and six `app/Lib/*` subdirectories come from the worktree,
while **`app/files` and `app/Config` come from the docker repo instead**,
because they hold runtime data and instance configuration rather than code.
So `app/files/analyst-profiles/default-v1.json` never reaches a dev
container, and `updateDefaults()` found nothing until the file was placed at
the mounted path by hand.

That is a dev artifact and not a design error, and it is worth saying why:
`app/files/<thing>` **is** MISP's convention for shipped JSON —
`taxonomies`, `warninglists`, `misp-objects`, `misp-galaxy` and
`misp-decaying-models` all live there, all as git submodules, which is
exactly how the image comes to have them and how the mount comes to overlay
a persisted copy. A shipped profile belongs there. It is only invisible to
*this* loop.

**Nothing outside `app/` is mounted at all**, which is the second one. This
is the first phase in the campaign to ship a root-level file — three of them,
counting `INSTALL/MYSQL.sql` and `db_schema.json` — so a migration is the
first kind of change the dev loop cannot see, and it fails silently:
`schemaDiagnostics` reads the container's own `db_schema.json` and simply
never mentions a table its expected schema has not heard of. Any later phase
adding a table inherits both; phase 10 adds one
(`11-restsearch.md` §4).

Two things the harness checks that the list did not ask for, both because
they are cheap to break later: **the per-request cache** — 27 panel calls
cost one statement, which is what §3.1 asks for in prose and nothing else
asserts — and **the shipped default's inertness**, that its `auto_run` is
empty and its `locality_posture` is `local_only`. The second matters more than it
looks: a default shipping modules in `auto_run` would make opening a value
page contact third parties, and phase 28 established that an enrichment run
is a press and never a page load. `08-enrichment.md` §2 had already called
for it; this is the assertion.

## 8. Out of scope

- Any UI (phase 8).
- **The controller and its ACL entries**, moved to phase 8 (§6, amended).
- Any use of `parameters` (phases 2–7). This phase treats it as opaque JSON and
  validates only that it parses.
- Syncing profiles between instances. The uuid exists for export/import and
  for matching a shipped default, not for a sync channel.
- **The default's weights.** `default-v1.json` shipped the six signals the
  design authored against the regression set, not §6's eleven — phase 2
  authors the catalogue and its calibration, and inventing five sets of
  numbers here would have shipped guesses that read as decisions (§4.1).
  **Superseded 2026-09-07:** phase 2 replaced the file with the eleven and
  bumped `version` to 2, so `updateDefaults()` carries the catalogue onto
  instances that already loaded the provisional one
  ([`03-signals.md`](03-signals.md) §7.5).

## 9. What phase 1 hands on

- **`resolveFor()` may return `null`, and it is not an error path.** Every
  caller from phase 2 onward handles it. It means a site admin disabled the
  instance default, which is how assessment scoring is switched off.
- **A row can carry `parameters_unparseable`.** `afterFind` deliberately does
  *not* copy `DecayingModel::afterFind`, which substitutes an empty array for
  unparseable JSON — that would score the value under a profile the hero is
  not naming. The flag is there so phase 2 can degrade visibly.
- **`revision`, not `version`, is the edit counter.** Phase 10's materialised
  assessments key on it; `bumpRevision()` is the only thing that should move
  it, and a rename must not.
- **`isReadableByCurrentUser()` exists alongside `isEditableByCurrentUser()`**
  and they are not the same test. The default is readable by everyone and
  editable by a site admin.
- **`updateDefaults()` returns a per-uuid outcome map**, including
  `overwrote_edits` when it discarded local changes to the default. Whatever
  calls it from `Admin runUpdates` should log that rather than drop it.
