# PRD: Analyst Profile — phase 1, the store

**Specification. Nothing built.** Depends on nothing; gates every other phase.
The picture is [`01-profile.md`](01-profile.md); decisions D1–D5 are what this
phase implements.

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

### 3.3 Q7 — which permission gates ownership

**Open.** Three candidates:

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

**Recommendation: the third**, with `perm_admin` for org profiles. It is the
only one where the common case — an analyst tuning their own weights — needs no
grant, which matters because a feature that requires an admin action before
anyone can try it will not get tried. The counter-argument is that MISP's
convention is a `perm_*` flag per capability, and this breaks it.

**Not decided here.** Whichever is chosen, run
`queryACL/findMissingFunctionNames` afterwards — it reports any new controller
action with no ACL entry.

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
            leave `enabled` alone                          # admin's choice
    else:
        insert with `default` = 1, `enabled` = 1
```

Three things this must not do:

- **Never touch a fork.** Forks have their own uuid and are invisible to this
  loop. That is D5 working as intended.
- **Never re-enable a disabled default.** An admin who disabled verdict scoring
  should not have it switched back on by an upgrade.
- **Never delete a default whose file has gone.** A shipped profile removed
  upstream stays in the database, because an org may have forked from it and
  the hero may still name it. It becomes an ordinary row that nothing updates.

Called from `Admin runUpdates` and exposed as an `update` action on the
controller, the same as `DecayingModelController::update()`.

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
new uuid, version = 1
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

`AnalystProfilesController` (phase 8 builds the views; the actions are listed
here because their ACL entries land with this phase):

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

1. `parallel-lint` over the new model and controller.
2. `Admin runUpdates`, then `Admin schemaDiagnostics` — no diff.
3. `resolveFor()` for four users: one with a personal profile, one whose org
   has one, one with neither, and a site admin. Four single queries, four
   distinct expected rows.
4. Disable the default with no other profile present; `resolveFor()` returns
   `null` and does not throw.
5. Fork the default as a non-admin; confirm the copy is editable and the
   original is not.
6. `updateDefaults($force = false)` twice in a row — the second is a no-op.
   Bump the shipped file's version and confirm the third run updates it and
   leaves `enabled` alone.
7. `queryACL/findMissingFunctionNames` — no missing entries.
8. Insert a row with two of the three ownership columns set; validation
   rejects it.

## 8. Out of scope

- Any UI (phase 8).
- Any use of `parameters` (phases 2–7). This phase treats it as opaque JSON and
  validates only that it parses.
- Syncing profiles between instances. The uuid exists for export/import and
  for matching a shipped default, not for a sync channel.
- Q7, recommended but not decided.
