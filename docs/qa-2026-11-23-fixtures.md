# QA fixtures, 2026-11-23

Hand-test fixtures for the week of 2026-11-23 (octothorp.es `docs/plans/weeks/2026-11-23-week.md`, "Blocker first: demo content").

Relay for every step: `https://next.octothorp.es/` (shortened to `$R` below). Set it in the shell first:

```sh
R=https://next.octothorp.es
DD=https://nimdaghlian.github.io/devdemo
ID=https://demo.ideastore.dev
```

## Where the files live

devdemo HTML is build output of `~/dev/octodemo` (`opddev`, config `_dev.yml`). demo.ideastore.dev is the same source built with `_config.yml` and deployed from octodemo `main`.

| Fixture | Source | Output |
|---|---|---|
| Reciprocal alias | `octodemo/demos/alias-reciprocal.md` | `$DD/alias-reciprocal`, `$ID/alias-reciprocal` |
| Endorsed backlinks pair | `octodemo/demos/endorsed-backlinks-pair.md` | `$DD/endorsed-backlinks-pair`, `$ID/endorsed-backlinks-pair` |
| Sitemap | `octodemo/sitemap.xml` | `$DD/sitemap.xml` (and `$ID/sitemap.xml`) |
| Partial blobject | `devdemo/fixtures/blobject-partial.json` (hand-authored) | `$DD/fixtures/blobject-partial.json` |
| This guide | `devdemo/docs/` (hand-authored) | n/a |

`_dev.yml` sets `keep_files` so `opddev` does not delete `docs/` and `fixtures/`.

The two-origin fixtures (1 and 4) need the octodemo commit pushed to `main` so demo.ideastore.dev serves its copies. Until then, the demo.ideastore.dev URLs return 404. The fixture pages set `server: alt`, so on both origins their preload goes to next.octothorp.es, not production.

General notes:

- Index with `curl "$R/index?uri=<url>" -H "Origin: <page origin>"`. The response JSON includes a `warnings` array when a statement was withheld.
- Opening a fixture page in a browser indexes it (the page preloads `$R/?uri=...`). Index order matters for fixtures 1 and 4: use curl in the stated order and do not open the pages first.
- The relay applies a per-URL cooldown (default 300 s). Wait 5 minutes before re-indexing the same URL.
- `/index` returns before propagation finishes. Wait a few seconds before running the read queries.
- GitHub Pages and demo.ideastore.dev deploy a few minutes after a push. Fetch the page with curl and check for the expected markup before indexing.
- To repeat a fixture from a clean state, the relay store needs these subjects removed (the smoketest golden wipe on next does this).

---

## 1. Reciprocal alias (#345 consent gate, #357 claim terms)

URLs:

- A: `https://nimdaghlian.github.io/devdemo/alias-reciprocal`
- B: `https://demo.ideastore.dev/alias-reciprocal`

Markup on A (devdemo build):

```html
<a rel="octo:aliases" data-octothorpes="alias-mirror,alias-fixture" href="https://demo.ideastore.dev/alias-reciprocal">This page is also published here</a>
```

Markup on B (demo.ideastore.dev build):

```html
<a rel="octo:aliases" href="https://nimdaghlian.github.io/devdemo/alias-reciprocal">This page is also published here</a>
```

Consent rules that apply (`checkAliasConsent` in `packages/core/indexer.js`):

- A and B are on different origins, so the same-origin shortcut does not apply.
- Every devdemo page declares `<link rel="octo:endorses" href="https://demo.ideastore.dev">`, so nimdaghlian.github.io endorses demo.ideastore.dev. demo.ideastore.dev does not endorse nimdaghlian.github.io.
- Result: A's claim on B has no consent until B claims back (pending). B's claim on A is admitted by endorsement if B is indexed first. Index A first to exercise the pending and reciprocal paths.

The existing one-way fixture `$DD/aliases` (claims `https://rentry.co/op-aliases-demo`, nothing claims back) is unchanged and stays pending permanently.

### State 1: pending

1. `curl "$R/index?uri=$DD/alias-reciprocal" -H "Origin: https://nimdaghlian.github.io"`
2. Expected: 200, with a `warnings` entry: `Alias claim from $DD/alias-reciprocal to $ID/alias-reciprocal recorded but not published: $ID/alias-reciprocal has not reciprocated or endorsed https://nimdaghlian.github.io.`
3. `curl "$R/get/everything/aliased?s=$DD/alias-reciprocal"` → `{"results":[]}`.
4. `curl "$R/get/everything/linked?s=$DD/alias-reciprocal&st=alias"` → no Alias entry.
5. Store check (local store only, no /get route reads claims): `<A> octo:aliasClaim <B>` exists, annotated with a claim record holding `octo:claimTerm` for `~/alias-mirror` and `~/alias-fixture`. `/get/pages/thorped?o=alias-mirror` must not return A (pending claim terms never appear as `octo:octothorpes`).

### State 2: live (reciprocal)

1. Confirm B is deployed: `curl -s $ID/alias-reciprocal | grep octo:aliases`.
2. `curl "$R/index?uri=$ID/alias-reciprocal" -H "Origin: https://demo.ideastore.dev"`
3. Expected: 200, no alias warning.
4. `curl "$R/get/everything/aliased?s=$ID/alias-reciprocal"` → B, with octothorpes entry `{"uri":"$DD/alias-reciprocal","type":"Alias"}` and no terms.
5. `curl "$R/get/everything/aliased?s=$DD/alias-reciprocal"` → A, with octothorpes entry `{"uri":"$ID/alias-reciprocal","type":"Alias","terms":["alias-mirror","alias-fixture"]}`.
6. Step 5 is the #357 check. A was only indexed while pending; its terms reach the live A→B alias through the claim record. Before the #357 fix, the `terms` array was missing here.
7. Re-index A after the cooldown. Expected: no warning, both aliases still present, terms unchanged.

### State 3: revoked

1. In `~/dev/octodemo/demos/alias-reciprocal.md`, set `devdemo_claims: false`. Run `opddev`, commit and push devdemo. A now renders "This copy has withdrawn its alias claim." with no `octo:aliases` link.
2. After the deploy, re-index A (cooldown permitting): `curl "$R/index?uri=$DD/alias-reciprocal" -H "Origin: https://nimdaghlian.github.io"`
3. Expected: reconcile revokes both directions (`deleter.revokeAlias`).
   - `/get/everything/aliased?s=$DD/alias-reciprocal` → empty
   - `/get/everything/aliased?s=$ID/alias-reciprocal` → empty
4. Known follow-on: B still declares its claim. If B is re-indexed now, its claim is admitted again by endorsement (nimdaghlian.github.io endorses demo.ideastore.dev) and B→A goes live without A. This is correct under the current rules. To revoke from the other side instead, set `ideastore_claims: false` and push octodemo `main`.
5. Restore: set the flag back to `true`, rebuild and push.

---

## 2. Sitemap (#177 sitemap harmonizer recipe)

URL: `https://nimdaghlian.github.io/devdemo/sitemap.xml`

Content: a standard `<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">` with 7 `<url><loc>` entries. Four of them have a `<lastmod>`:

- `$DD/` (no lastmod)
- `$DD/aliases` (2026-10-26)
- `$DD/alias-reciprocal` (2026-11-23)
- `$DD/endorsed-backlinks-pair` (2026-11-23)
- `$DD/link-types`
- `$DD/relationship-terms`
- `$DD/link-subtypes` (2026-10-26)

The source uses `{{ site.url }}`, so demo.ideastore.dev serves the same sitemap with its own origin.

Steps:

1. Parse only. Run the sitemap through the `sitemap` harmonizer without writing: orchestra-pit (`$R/debug/orchestra-pit?uri=$DD/sitemap.xml&as=sitemap`), or `batchHarmonize` with `mode: 'return-only'`, or locally from the octothorp.es repo root:
   ```sh
   node --input-type=module -e "
   import { harmonizeSource } from 'octothorpes'
   import fs from 'node:fs'
   const h = JSON.parse(fs.readFileSync('static/harmonizers/sitemap.json','utf8'))
   const xml = await (await fetch('https://nimdaghlian.github.io/devdemo/sitemap.xml')).text()
   console.log(JSON.stringify((await harmonizeSource(xml, h)).octothorpes.map(o => o.uri)))
   "
   ```
2. Expected: 7 `{"type":"link","uri":...}` entries, one per `<loc>`, in sitemap order. This was checked against the local build on 2026-11-23.
3. POST the lifted URIs as `urls`: `curl -X POST "$R/index/batch" -H "Origin: https://nimdaghlian.github.io" -H "Content-Type: application/json" -d '{"urls":[...the 7 URIs...]}'` → 200 with per-item results and a summary.
4. Bug check: `curl "$R/get/everything/linked?s=$DD/sitemap.xml"` must return `{"results":[]}`. If the sitemap URL appears as a subject, the sitemap blobject was write-ingested, which is the bug.

Note: on 2026-11-23, `$R/harmonizer/sitemap` returned 500 on next. If orchestra-pit cannot resolve `as=sitemap`, use the local `harmonizeSource` command above.

---

## 3. Partial blobject (#43 / #180, `reconcile: false`)

File: `https://nimdaghlian.github.io/devdemo/fixtures/blobject-partial.json` (repo path `fixtures/blobject-partial.json`).

```json
{
  "@id": "https://nimdaghlian.github.io/devdemo/link-types",
  "title": "Link Types - OP DEV DEMO",
  "description": "OP supports typed page-to-page relationships: bookmarks, citations, buttons, and custom types.",
  "image": "https://nimdaghlian.github.io/devdemo/assets/octomark.png",
  "octothorpes": [
    "demo",
    "blobject-partial",
    { "type": "bookmark", "uri": "https://aftermath.site/website-musk-twitter-facebook-internet" },
    { "type": "bookmark", "uri": "https://docs.octothorp.es/how-to-write-statements", "terms": ["partial-fixture"] }
  ]
}
```

Overlap with what `$DD/link-types` declares when indexed from HTML:

| Statement | Page (HTML) | Fixture |
|---|---|---|
| hashtag `demo` | yes | yes |
| Bookmark → aftermath.site | yes | yes |
| Cite → nora.zone/manifesto.html | yes | no |
| Link → octothorpes.neocities.org | yes | no |
| Link → octothorped-wordpress.pikapod.net/how-it-works | yes | no |
| hashtag `blobject-partial` | no | yes |
| Bookmark → docs.octothorp.es/how-to-write-statements, term `partial-fixture` | no | yes |

Title, description and image match the page, so metadata does not change.

Which path to use: the subject is an http(s) URL. `submitBlobject` (`POST /index` with `mode: blobject`, and the `blobjects` array of `POST /index/batch`) rejects http(s) subjects ("http(s) subjects are fetchable and must be indexed, not submitted"), and next has `policies.access.submissions: never`. Run this fixture in-process through core `ingestBlobject`, against the local store. The file passes the blobject handler and `ingestBlobject` with both `reconcile` values; this was dry-run against a fake store on 2026-11-23.

Steps (from the octothorp.es repo root, local store up, `.env` pointing at it):

1. Baseline: index the real page into the local store. `curl "http://localhost:5173/index?uri=$DD/link-types"`. Then `curl "http://localhost:5173/get/everything/linked?s=$DD/link-types"` lists the Cite, the aftermath Bookmark, and both Links.
2. Partial upsert:
   ```sh
   node --env-file=.env --input-type=module -e "
   import fs from 'node:fs'
   import { createSparqlClient, createIndexer } from 'octothorpes'
   const sparql = createSparqlClient({ endpoint: process.env.sparql_endpoint, user: process.env.sparql_user, password: process.env.sparql_password })
   const indexer = createIndexer({ insert: sparql.insert, query: sparql.query, queryBoolean: sparql.queryBoolean, queryArray: sparql.queryArray, instance: process.env.instance })
   const b = JSON.parse(fs.readFileSync('../devdemo/fixtures/blobject-partial.json', 'utf8'))
   await indexer.ingestBlobject(b, { instance: process.env.instance, reconcile: false })
   "
   ```
3. Expected after `reconcile: false`: the linked query shows the original Cite, the aftermath Bookmark, both Links, and the new Bookmark to docs.octothorp.es with `terms: ["partial-fixture"]`. `/get/pages/thorped?o=blobject-partial` returns link-types. Nothing was deleted.
4. Run step 2 again with `reconcile: true` (or omit the option; true is the default).
5. Expected after `reconcile: true`: the Cite to nora.zone and both Links are gone. The fixture's statements remain (demo, blobject-partial, both Bookmarks).
6. Restore: re-index the page from HTML (step 1, after the cooldown). This reconciles back to the page's own statements and retracts `blobject-partial` and the docs.octothorp.es Bookmark.

---

## 4. Endorsed backlinks across two origins (#213)

URLs:

- T: `https://nimdaghlian.github.io/devdemo/endorsed-backlinks-pair`
- X: `https://demo.ideastore.dev/endorsed-backlinks-pair`

Markup on T (devdemo build):

```html
<a rel="octo:octothorpes" href="https://demo.ideastore.dev/endorsed-backlinks-pair">The copy of this page on the other origin</a>
```

Markup on X (demo.ideastore.dev build):

```html
<a rel="octo:octothorpes" href="https://nimdaghlian.github.io/devdemo/endorsed-backlinks-pair">The copy of this page on the other origin</a>
```

Endorsements in place (from `_includes/octocode.html` on every page):

- devdemo pages: `<link rel="octo:endorses" href="https://demo.ideastore.dev">` and `https://octothorpes.neocities.org`.
- demo.ideastore.dev pages: `https://octothorpes.neocities.org` (and itself, which is dropped as same-origin). It does not endorse nimdaghlian.github.io.

Rule under test (#213 ruling 2026-10-19): a cross-origin generic Link is promoted to a Backlink when the target origin endorses the linking origin, or when the target already links back (reciprocal mention). Otherwise it stays a plain Link. The flat link is always written.

The three steps below cover all three cases. Index in this order.

### Step 1: unendorsed, unreciprocated (control)

1. `curl "$R/index?uri=$DD/endorsed-backlinks-pair" -H "Origin: https://nimdaghlian.github.io"`
2. `curl "$R/get/everything/linked?s=$DD/endorsed-backlinks-pair"` → octothorpes include `{"uri":"$ID/endorsed-backlinks-pair","type":"Link"}`.
3. `curl "$R/get/everything/backlinked?s=$DD/endorsed-backlinks-pair"` → no entry for X. demo.ideastore.dev does not endorse devdemo and X is not indexed yet.
4. This index also records `<https://nimdaghlian.github.io> octo:endorses <https://demo.ideastore.dev>`, which step 2 depends on.

### Step 2: endorsed

1. Confirm X is deployed: `curl -s $ID/endorsed-backlinks-pair | grep endorsed-backlinks-pair`.
2. `curl "$R/index?uri=$ID/endorsed-backlinks-pair" -H "Origin: https://demo.ideastore.dev"`
3. `curl "$R/get/everything/linked?s=$ID/endorsed-backlinks-pair"` → entries for T with `"type":"Link"` and `"type":"Backlink"` (one row per subtype).
4. `curl "$R/get/everything/backlinked?s=$ID/endorsed-backlinks-pair"` → X, with T as a Backlink. The link was promoted because nimdaghlian.github.io endorses demo.ideastore.dev.

### Step 3: reciprocal

1. Wait out the cooldown, then re-index T: `curl "$R/index?uri=$DD/endorsed-backlinks-pair" -H "Origin: https://nimdaghlian.github.io"`
2. `curl "$R/get/everything/backlinked?s=$DD/endorsed-backlinks-pair"` → T, with X as a Backlink. demo.ideastore.dev still does not endorse devdemo; the promotion comes from X linking back. T's markup did not change, so this also checks that the #267 unchanged-page skip still re-runs the Link promotion gate.

If X is indexed before T, step 1 cannot be observed: T's link is promoted on its first index because X already links back.

The older single-origin page `$DD/endorsed-backlinks` still carries the draft setup notes. It links devdemo → demo.ideastore.dev `/backlinks` and `/no-backlinks`, neither of which links back, so those links stay plain Links.
