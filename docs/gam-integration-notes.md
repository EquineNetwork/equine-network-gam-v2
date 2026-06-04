# EN GAM v2 — Integration Notes & Hard‑Won Lessons

Reference for how the tricky GAM integrations actually work, **why** the obvious
approaches failed, and the exact request shapes that finally worked. Written after the
v3.3.37–v3.3.48 work. If you touch line items, ad‑unit scoping, the stacker AI
categories, or the Half Page leaderboard, read this first.

All of this lives in `includes/class-equinenetwork-gam-v2-api.php` unless noted.

---

## 0. API basics

- **REST base:** `https://admanager.googleapis.com/v1` (constant `GAM_REST_BASE`).
- **Auth:** service‑account JWT → OAuth2 access token. Scope
  `https://www.googleapis.com/auth/admanager`. Token cached in the
  `engam_v2_access_token` transient (~59 min). See `build_jwt()` / `get_access_token()`.
- **Network code:** stored in option `equinenetwork_gam_v2_id` as a path like
  `/22345131513/nationalteamroping`. The **numeric** network code is everything
  non‑digit stripped (`22345131513`); the **site code** is the last path segment
  (`nationalteamroping`).
- **JSON casing:** the v1 REST API uses **camelCase** field names
  (`customTargetingKeys`, `adTagName`, `nextPageToken`) even though the underlying
  protos are snake_case. Always read/write camelCase.

---

## 1. Per‑site line items (the big one)

### The problem
The dashboard "GAM Line Items" metric either showed the **entire network's 5,570 line
items**, or dropped to **0 after a few hours idle**. We wanted: only the line items
actually running on *this* site's ad unit (e.g. ~4–6 for National Team Roping), and
never a false 0.

### What did NOT work (and why) — don't retry these
1. **Filtering `lineItems.list` by ad unit server‑side.**
   `GET /networks/{net}/lineItems?filter=...` rejects any filter on
   `targeting.inventoryTargeting.targetedAdUnits.adUnit` with
   **HTTP 400 "Field … is not supported for filtering."** Tried both `=` and `:`
   operators. Not supported.
2. **Filtering client‑side from the list response.**
   `lineItems.list` **omits the `targeting` object entirely.** The returned keys are
   only: `name, order, displayName, startTime, endTime, lineItemType, rate, budget,
   goal`. There is nothing to match an ad unit against.
3. **Requesting `targeting` via a `fields` mask.** `fields=lineItems(...,targeting)`
   returned **HTTP 400 "invalid argument."** The mask syntax we needed isn't honored
   the way we expected; abandoned.
4. **Reading computed status from the LineItem resource.** The v1 `LineItem` resource
   has **no status / computedStatus / deliveryStatus field at all** (confirmed against
   the proto and live responses). You cannot get DELIVERING/READY/etc. from
   `lineItems.get`.

### What works — the GAM **Reports API**
This is the same engine behind the GAM UI's "Line items against this inventory" tab. We
create a temporary report scoped to the site's ad unit IDs, run it (async), read the
rows, then delete it. See `run_ad_unit_report()`.

**Flow:**
1. `POST /networks/{net}/reports` — create the report. **Body shape that works:**
   ```jsonc
   {
     "displayName": "EN Plugin — Line Items by Ad Unit (auto)",
     "reportDefinition": {
       "reportType": "HISTORICAL",
       "dateRange":  { "relative": "LAST_90_DAYS" },
       "dimensions": ["LINE_ITEM_ID", "LINE_ITEM_NAME", "LINE_ITEM_COMPUTED_STATUS_NAME"],
       "metrics":    ["AD_SERVER_IMPRESSIONS"],
       "filters": [
         {
           "fieldFilter": {
             "field":     { "dimension": "AD_UNIT_ID" },
             "operation": "IN",
             "values":    [ { "intValue": "23297243907" } ]
           }
         }
       ]
     }
   }
   ```
   - ⚠️ **`dimensions` and `metrics` are SEPARATE arrays of enum‑name STRINGS.** An
     early version used a single `"fields": [{ "dimension": ... }]` array and GAM
     returned **HTTP 400 "Unknown name 'fields' … Cannot find field."** Do not combine
     them.
   - `fieldFilter.field` IS an object (`{ "dimension": "AD_UNIT_ID" }`), and filter
     values use typed wrappers (`{ "intValue": "<id>" }`).
2. `POST /{reportName}:run` with an empty body → returns a **long‑running operation
   (LRO)**.
3. Poll `GET /{operationName}` until `done:true` (bounded ~22 s so we stay within PHP
   limits). On success it carries `response.reportResult`.
4. `GET /{reportResult}:fetchRows?pageSize=1000` (paginated). Each row has
   `dimensionValues[]`; pull scalars out with `report_value_string()` (handles
   `stringValue` / `intValue` / `doubleValue`).
5. `DELETE /{reportName}` — best‑effort cleanup so the GAM Reports UI stays tidy.

If **anything** in this flow fails, `fetch_line_items()` falls back to the full
unfiltered `lineItems.list` so the dashboard is never empty/erroring.

### Resolving the site's ad unit IDs
`get_site_ad_unit_resources()`:
- List `GET /networks/{net}/adUnits` (paged, `pageSize=500`).
- Match the unit whose **`adUnitCode`** equals the site code (last segment of the
  network path), case‑insensitive → that's the **root** ad unit.
- Add its **direct children** (units whose `parentAdUnit` === the root's resource name).
- Returns full resource names like `networks/22345131513/adUnits/23297243907`; we
  `basename()` them to numeric IDs for the report's `AD_UNIT_ID` filter.
- Cached 1 h in transient `engam_v2_site_unit_res`.

### Status filtering (DELIVERING / READY / PAUSED only)
Because the LineItem resource has no status, we add the
**`LINE_ITEM_COMPUTED_STATUS_NAME`** dimension to the report and filter rows in PHP
(`is_active_status()`):
- **Keep** if the status name (lower‑cased) contains `deliver`, `ready`, or `paus`
  → covers Delivering, Delivery extended, Ready, Paused, Paused (inventory released).
- **Drop** Completed, Archived, Inactive, Pending approval, Draft, Canceled, Disapproved.
- An **empty** status is kept (never hide a real line item over a missing field).
- Substring matching is deliberate — it survives enum spelling / locale differences.

### Caching & the "0 after idle" fix
- Results cached 1 h in transient `engam_v2_line_items` (`CACHE_KEY`/`CACHE_DURATION`).
- A **45‑minute WP‑Cron** job refreshes the cache *before* the 1‑hour transient
  expires, so it never lapses to 0. Defined in
  `includes/class-equinenetwork-gam-v2.php → define_cron_hooks()`:
  - custom schedule `engam_45min` (45 × `MINUTE_IN_SECONDS`),
  - action `engam_v2_refresh_line_items` → `get_line_items(true)`,
  - **self‑healing** `init` hook re‑schedules the event if it ever drops (e.g. after a
    plugin update). Also scheduled on activation, cleared on deactivation
    (`...-activator.php` / `...-deactivator.php`).

---

## 2. Stacker "AI Categories" (read‑only list from GAM)

Shown on the Stackers admin page. GAM's v1 API **does not expose a line item's
targeting**, so we *cannot* list which categories each stacker line item targets. What
we *can* do — and what the page shows — is the **taxonomy of `ai_category` values
defined in GAM** (the universe the targeting is built on). See
`get_ai_category_values()`.

**Flow:**
1. **Find the key.** Scan `GET /networks/{net}/customTargetingKeys?pageSize=1000`
   (paged) and match the one whose **`adTagName`** == `ai_category` (case‑insensitive).
   - We scan rather than use `?filter=adTagName="ai_category"` to avoid AIP‑160
     filter‑syntax pitfalls (those bit us repeatedly on the line‑items work).
   - `ai_category` is the same key the front end sets via
     `gamSlot.setTargeting('ai_category', …)` (see the public footer partial).
2. **List its values** via the **nested** collection:
   `GET /{keyName}/customTargetingValues?pageSize=1000` (paged), where `{keyName}` is the
   full resource name `networks/{net}/customTargetingKeys/{id}`.
3. Use each value's **`displayName`** (fallback `adTagName`); de‑dupe, natcase‑sort.
4. Cache 12 h in transient `engam_v2_ai_category_values` (`CACHE_AI_CATS`). Empty result
   cached 1 h so we don't re‑scan every page load.

**Key/Value field names (camelCase):** `name`, `adTagName`, `displayName`
(`customTargetingKeyId` exists but is deprecated/output‑only). Values also have
`customTargetingKey` (parent resource name) and `matchType`.

Rendered as read‑only black pills on the consolidated Stackers card
(`admin/partials/engam-stackers.php`).

---

## 3. Half Page leaderboard placement (mid‑page injection)

`Equinenetwork_Gam_V2_Leaderboard` (`public/class-equinenetwork-gam-v2-leaderboard.php`).
A leaderboard `position` of `midpoint` (labelled **"Half Page"** in the UI) renders the
band, then JS moves it into the page body of the targeted page(s).

- **Page targeting:** `page_matches()` compares the queried object's ID/slug against the
  admin "Target Page" value (now a single‑select dropdown that stores the page **ID**;
  legacy slug values still match).
- **Placement JS (in `render_leaderboards()`):**
  - If a CSS selector is set ("Insert Before"), insert before the **middle matching
    element by count** — deterministic, the recommended path.
  - Otherwise, walk down into the **dominant content container** (the child holding
    ≥60% of the height — i.e. the listings, not the `[filter, listings]` split) and
    insert before its **middle child by count**. Count‑based middle is robust even
    before images load and heights settle.
  - `spanFull()` forces the band to full width (`width:100%; align-self:stretch;
    grid-column:1 / -1`) so it can't shrink into a flex/grid column. **This was the bug
    that pinned it top‑left** — it had been inserted as a narrow flex *item*.
- **Empty‑slot safety:** an unfilled mid‑page slot collapses **only its own band** and
  must **never** walk up to the page's Elementor containers (that would hide the
  calendar). Guarded in `public/partials/equinenetwork-gam-v2-public-footer.php` via the
  `.engam-leaderboard-midpoint` check.

---

## 4. GAM v1 quick‑reference gotchas

| Thing | Reality |
|---|---|
| Filter `lineItems.list` by ad unit | ❌ HTTP 400 "not supported for filtering" |
| `targeting` in `lineItems.list` response | ❌ omitted entirely |
| Line item status on the resource | ❌ no status field in v1 LineItem |
| Report dimensions/metrics | ✅ **separate** `dimensions` & `metrics` string arrays — NOT a combined `fields` array |
| Report filter field | ✅ `fieldFilter.field` = `{ "dimension": "AD_UNIT_ID" }`, values `{ "intValue": "…" }` |
| Line item status (for filtering) | ✅ report dimension `LINE_ITEM_COMPUTED_STATUS_NAME` |
| Run a report | ✅ `:run` returns an LRO; poll the operation, then `:fetchRows` |
| Custom targeting value listing | ✅ nested `…/customTargetingKeys/{id}/customTargetingValues` |
| AIP‑160 `filter=` params | ⚠️ flaky/unsupported on several fields — prefer scanning + matching in PHP |
| REST JSON casing | camelCase |

---

## 5. Where things live

| Concern | Location |
|---|---|
| Auth, line items, report flow, ad‑unit resolution, status filter, AI categories | `includes/class-equinenetwork-gam-v2-api.php` |
| 45‑min cron warming + self‑heal | `includes/class-equinenetwork-gam-v2.php` (`define_cron_hooks`) |
| Cron (un)scheduling | `includes/class-equinenetwork-gam-v2-activator.php` / `-deactivator.php` |
| Leaderboards (incl. Half Page placement JS) | `public/class-equinenetwork-gam-v2-leaderboard.php` |
| Empty‑slot collapse guard | `public/partials/equinenetwork-gam-v2-public-footer.php` |
| Stackers admin (one card + AI category pills) | `admin/partials/engam-stackers.php` |
| Settings (GAM API card, flat‑icon redesign) | `admin/partials/engam-settings.php` |
| Dashboard placement cards | `admin/partials/engam-dashboard.php` |
| Diagnostics (Test Connection) | `Equinenetwork_Gam_V2_API::diagnose()` → admin AJAX `ajax_test_connection()` |

**Tip:** the **Test Connection** button runs `diagnose()`, which executes the full
ad‑unit report with step‑by‑step logging (ad unit IDs, create/run/poll/fetch, a
per‑status breakdown, and the kept count). It's the fastest way to see what GAM is
actually returning.

---

## 6. Version history (the relevant fixes)

| Version | Change |
|---|---|
| 3.3.37 | Cron cache warming (fixes "0 after idle"); first per‑site filter attempt |
| 3.3.38–3.3.41 | Failed list‑filter / targeting‑mask attempts (see §1 "what didn't work") |
| 3.3.42 | Switched to Reports API (still had the `fields` bug) |
| 3.3.43 | **Fixed report body**: `dimensions` + `metrics` arrays instead of `fields` |
| 3.3.44 | Status filter via `LINE_ITEM_COMPUTED_STATUS_NAME` (delivering/ready/paused) |
| 3.3.45 | Half Page leaderboard position (page‑targeted mid‑content injection) |
| 3.3.46 | Renamed that position to "Half Page" |
| 3.3.47 | Admin UI pass; Stackers one‑card + read‑only AI categories from GAM |
| 3.3.48 | Half Page lands inside listings (dominant‑container descent); black pills |
| 3.3.49 | "View in GAM" column added to Carousels + Takeovers lists |
| 3.3.50 | Sponsor sheet: Microsoft Graph (Azure app) OneDrive/SharePoint path |
| 3.3.51 | Sponsor sheet: no‑Azure "Anyone with the link" path (download + XLSX parse) |
| 3.3.52 | Sponsor dropdowns show "Name - Sponsorship ID" (e.g. `Bimeda - videotips_hr_bimeda`) so duplicate advertiser names are distinguishable |

---

## 7. Sponsor spreadsheet (OneDrive / SharePoint / Google Sheets)

Lives in `includes/class-equinenetwork-gam-v2-api.php`; UI in `admin/partials/engam-settings.php`
("Sponsor Spreadsheet" card). Feeds the "Lock to Sponsor" dropdowns, the carousel renderer,
the metabox, and the campaigns list — all via **`get_sponsor_options()`**, which returns
`[ ['id'=>sponsorId, 'name'=>advertiser], … ]` for rows whose Status = "Active", cached 1 h
in the `engam_v2_sponsor_options` transient.

### Source priority (in `get_sponsor_options()`)
1. **Microsoft Graph** — when all of `engam_v2_ms_{tenant_id,client_id,client_secret}` **and**
   `engam_v2_ms_file_url` are set (`is_ms_configured()`).
2. **Share-link (no Azure)** — when only `engam_v2_ms_file_url` is set.
3. **Google Sheets CSV (legacy)** — when only `engam_v2_sheet_csv_url` is set.

The shared row → sponsor extraction is **`extract_sponsors_from_rows()`** (header auto-detect +
column heuristic), used by all three so behaviour is identical regardless of source.

### Why the team's OneDrive sheet was tricky
- The "sheet" is **`Sponsorship IDs.xlsx` on `equinenetwork.sharepoint.com`** (Microsoft 365),
  not Google Sheets. Excel/SharePoint has **no "Publish to web as CSV"** equivalent.
- **A Google service account cannot authenticate to OneDrive.** Google Cloud and Microsoft
  Entra are separate identity systems — sharing the file to the `…iam.gserviceaccount.com`
  address grants nothing the plugin can use.
- The proper Microsoft equivalent (Graph app-only) needs an **Entra app registration +
  `Files.Read.All` + admin consent**. The user hit **"You do not have access" (401)** on the
  App registrations blade — app registration is admin-only in that tenant, and the admin-consent
  step requires a Global Admin regardless. So Graph is blocked without IT involvement.

### The no-Azure path that actually shipped (the file is shared "Anyone with the link")
- **`ms_link_download($url)`** — appends `download=1` to the share URL and GETs it. Validates the
  body starts with the ZIP signature `PK`; if it's HTML it's a sign-in page (link isn't truly
  anonymous) → returns a friendly WP_Error.
- **`xlsx_rows($binary, $sheet)`** — parses XLSX **with built-in `ZipArchive` + `SimpleXML`** (no
  PhpSpreadsheet dependency):
  - `xl/workbook.xml` + `xl/_rels/workbook.xml.rels` → map tab **name → worksheet XML** (the
    `r:id` attribute is in the relationships namespace: `$s->attributes($rel_ns)->id`).
  - `xl/sharedStrings.xml` → string table; `t="s"` cells store an **index** into it. Rich-text
    `<si>` entries (multiple `<r><t>` runs) are concatenated.
  - Cells are placed by **column letter** (`xlsx_col_index()`), padding gaps so columns align.
- **`xlsx_sheet_names()`** lists tabs (used by Test Connection so a wrong tab name is obvious).
- Default ns elements are accessed directly in SimpleXML; only the prefixed `r:id` needs the
  namespace-qualified accessor.

### Gotchas
| Thing | Reality |
|---|---|
| Google SA → OneDrive | ❌ different identity systems; sharing to the SA email does nothing |
| Excel "Publish to web as CSV" | ❌ no such feature (unlike Google Sheets) |
| App registration in their tenant | ❌ admin-only (401); admin consent also needs a Global Admin |
| `?download=1` on an anonymous share link | ✅ returns the raw `.xlsx` bytes server-side |
| Parsing `.xlsx` | ✅ `ZipArchive` + `SimpleXML`, no external library |
| Header row | ✅ auto-detected (skips the naming-convention title row at the top) |
| Tab name | ⚠️ case-insensitive match, falls back to first sheet; Test Connection lists all tabs |
| Anonymous link disabled / changed to "People in org" | ❌ breaks the link path → switch to Graph |
