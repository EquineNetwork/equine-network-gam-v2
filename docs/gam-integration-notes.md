# EN GAM v2 — Integration Notes & Hard‑Won Lessons

Reference for how the tricky GAM integrations actually work, **why** the obvious
approaches failed, and the exact request shapes that finally worked. Written after the
v3.3.37–v3.3.48 work and extended through v3.3.84. If you touch line items, ad‑unit
scoping, the takeover line‑item picker, the stacker AI categories, or the Half Page
leaderboard, read this first.

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

If **anything** in this flow fails, `fetch_line_items()` returns the report's `WP_Error`
rather than caching anything. ⚠️ **Do not "fall back" to the full `lineItems.list`** — an
early version did, which dumped all ~5,500 network line items into the picker (and, since
the list has no status, none could be filtered out). The list is *not* a usable per‑site
source; see §9.

### Flight dates: backfilled from the list
The report rows carry **no start/end dates**. `fetch_line_items()` therefore also pulls
`lineItems.list` once and builds a `gam_id → {startTime, endTime}` map, then backfills
`start_time`/`end_time` onto the report rows. (The list omits targeting and status but
**does** return `startTime`/`endTime`.) The takeover wrap uses these dates to auto‑expire
— see §9.


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
| Takeover line‑item picker + GAM‑ID lookup | `admin/partials/engam-takeovers.php` (picker JS) · `Equinenetwork_Gam_V2_API::lookup_line_item()` · AJAX `ajax_lookup_line_item()` (`wp_ajax_engam_v2_lookup_line_item`) |
| Takeover serve gate + flight‑date auto‑expiry | `public/class-equinenetwork-gam-v2-takeover.php` (`entry_is_live()`, `gam_line_item_flight()`) |
| Tab name list (XLSX + Graph) | `Equinenetwork_Gam_V2_API::list_worksheet_names()` → `get_ms_link_worksheet_names()` / `get_ms_worksheet_names()` |
| Tab name AJAX endpoint | `Equinenetwork_Gam_V2_Admin::ajax_ms_tabs()` (`wp_ajax_engam_v2_ms_tabs`) |
| Sponsor dropdown label format ("Name - id") | `admin/class-equinenetwork-gam-v2-metabox.php::get_campaign_options()` and `public/class-equinenetwork-gam-v2-carousel-render.php::sponsor_options()` |

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
| 3.3.53 | Worksheet/Tab Name gains `engam_v2_ms_tabs` AJAX endpoint + `list_worksheet_names()` to fetch real tab names from the file; first UI used `<datalist>` (superseded by v3.3.54) |
| 3.3.54 | Tab picker: `<datalist>` → real `<select>` + hidden input (datalist only shows suggestions matching the current typed value — not a true dropdown); version bump to deliver the fix via the update checker |
| 3.3.55 | **Leaderboard size-mapping fix**: `validSizes()` now normalizes a single `[w,h]` pair, so the Elementor widget's leaderboard slot builds a viewport size map and GAM can't serve a 320x50 creative on desktop (see §8) |
| 3.3.79 | Takeover picker first attempt: merged the full `lineItems.list` into the report results so new/not‑yet‑delivered items would show. **Side effect: ~5,500 items** because the list can't be scoped (superseded) |
| 3.3.80 | Scoped the merge to the site's ad units via list targeting + inherited GAM flight dates for wrap auto‑expiry. **The scoping silently matched 0** — the list has no targeting (see §9) |
| 3.3.81 | Added the **full‑list probe** to `diagnose()` (targeting present? dates present? resolving to this site? sample fields) — this is what proved the list returns no targeting/status |
| 3.3.82 | **Final fix**: dropped list‑based scoping; added **direct GAM‑ID lookup** (`lookup_line_item()`, single `GET`) so not‑yet‑delivered items are wired up by ID and persisted durably; `fetch_line_items()` simplified to report + date backfill + durable manual items (see §9) |

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

### Sponsor dropdown labels (v3.3.52)

All three sponsor dropdowns — the post-editor "EN Sponsor ID" meta box
(`admin/class-equinenetwork-gam-v2-metabox.php`), the Carousel widget's "Lock to Sponsor"
control, and the Takeover widget — display options as **`Advertiser Name - sponsorship_id`**
(e.g. `Bimeda - videotips_hr_bimeda`, `WF Young (ShowSheen) - showsheen_wf_young`).

**Why:** the HR tab in `Sponsorship IDs.xlsx` has several rows with the same advertiser name
(multiple "WF Young" entries, multiple "Bimeda" entries, etc.). With name-only labels there
was no way to tell which dropdown entry was which campaign.

**Rules:**
- If `name === id` (no separate display name), the label is just the id.
- Separator is a plain hyphen (` - `), not an em-dash.
- Sponsorship IDs are descriptive slugs, not numbers (e.g. `videotips_hr_bimeda`).
- Both `get_campaign_options()` (metabox) and `sponsor_options()` (carousel renderer) apply
  the same logic so all three dropdowns stay consistent.

### Worksheet tab name picker (v3.3.53 / v3.3.54)

The "Worksheet/Tab Name" field in the Sponsor Spreadsheet card is a real `<select>` dropdown
populated on settings-page load from the file's actual tab names via AJAX.

**Why `<datalist>` didn't work (the v3.3.53 mistake):**
The first build (v3.3.53) used `<input type="text"> + <datalist>`. HTML `<datalist>` is a
type-ahead suggestion aid — it only surfaces options that **match the current input value as
typed**. With "HR" already in the field and the arrow clicked, the browser showed only "HR"
back. Replaced with a proper `<select>` in v3.3.54.

**The pattern (dynamic `<select>` in a WP settings form):**
```html
<!-- Hidden input is the only named field — it's what save_settings() reads -->
<input type="hidden" name="engam_v2_ms_sheet_name" id="engam-ms-sheet-value" value="HR">
<!-- Select shown after AJAX resolves; updates the hidden input on change -->
<select id="engam-ms-sheet-select" style="display:none"> … </select>
<!-- Text fallback shown until AJAX resolves, or if no file URL is configured -->
<input type="text" id="engam-ms-sheet" value="HR" placeholder="HR">
```
The `<select>` is **unnamed** — it never submits. `tabSelect.addEventListener('change', …)`
syncs the selection into the hidden input. The text fallback syncs via
`tabText.addEventListener('input', …)`. The save handler is unchanged.

**`engam_v2_ms_tabs` AJAX endpoint:**
- Registered in `Equinenetwork_Gam_V2_Admin::__construct()` as `wp_ajax_engam_v2_ms_tabs`.
- Handler: `ajax_ms_tabs()` — verifies nonce + `manage_options`, calls
  `Equinenetwork_Gam_V2_API::list_worksheet_names( $force )`.
- `list_worksheet_names()` routes to `get_ms_worksheet_names()` (Graph path) or
  `get_ms_link_worksheet_names()` (share-link path) depending on `is_ms_configured()`.
- `get_ms_link_worksheet_names()` downloads the XLSX via `ms_link_download()`, calls
  `xlsx_sheet_names()`, and caches the result 12 h in transient `engam_v2_ms_worksheets`
  (`CACHE_MS_SHEETS`). `$force = true` skips the cache.
- Response: `{ "success": true, "data": { "tabs": ["AC","AHFEH",…], "current": "HR" } }`.

**When the endpoint fires:**
1. Automatically on settings page load when a file URL is saved.
2. Automatically after a successful Test Connection (so a URL change is reflected immediately).
3. On demand via the "Refresh" button (`force=1` skips the 12 h cache).

**Edge case — saved tab not in returned list:**
`fillTabs()` checks whether the option stored in `engam_v2_ms_sheet_name` is present in the
fetched tab array. If not (tab was renamed in Excel), it inserts the saved value as the first
selected option so it isn't silently discarded on the next Save.

---

## 8. Ad slot sizes & the viewport size mapping (don't reintroduce the 320x50-on-desktop bug)

Every ad slot is a `<div class="equinenetworkad" data-sizeDesktop=… data-sizeMobile=…
data-sizes=…>` consumed by the loop in
`public/partials/equinenetwork-gam-v2-public-footer.php`. For a slot that has different
desktop and mobile sizes (the **leaderboard**: 728x90 desktop / 320x50 mobile), GPT needs a
**viewport size mapping** or GAM may serve *any* size in the slot's size array at *any*
width — e.g. a 320x50 creative dropped into a 728x90 desktop leaderboard.

### The mapping
Built per slot in the footer, keyed off `data-sizeDesktop` / `data-sizeMobile`:
```js
googletag.sizeMapping()
    .addSize([728, 0], validDsk)   // viewport ≥ 728px wide → desktop sizes
    .addSize([0,   0], validMob)   // narrower → mobile sizes
    .build();
```
It is applied with `gamSlot.defineSizeMapping(mapping)` **only when both `validDsk` and
`validMob` are non-null.** `data-sizes` (the union of all sizes) is still what
`defineSlot()` is called with; the mapping just restricts eligibility per viewport.

### The trap: two emitters, two array shapes
There are two places that emit these attributes, and they historically used **different
shapes**:

| Emitter | `data-sizeDesktop` shape |
|---|---|
| `public/class-equinenetwork-gam-v2-leaderboard.php` (standalone leaderboard band) | `[[728,90]]` — **array of pairs** ✅ |
| `elementor/class-equinenetwork-gam-v2-widget.php` (EN Ad Slot widget) | `[728,90]` — **single pair** ⚠️ |
| `public/class-equinenetwork-gam-v2-public.php` (stacker) | `[320,480]` — **single pair** ⚠️ |

`validSizes()` originally filtered for **array** elements only, so a single pair like
`[728,90]` (whose elements are numbers) filtered down to empty → returned `null` →
`if (validDsk && validMob)` was false → **the size mapping was silently never built** for the
widget. The widget's leaderboard therefore had no desktop/mobile restriction, and GAM could
serve 320x50 on desktop. The standalone leaderboard band was unaffected because it already
wrapped its sizes as `[[…]]`.

### The fix (v3.3.55)
`validSizes()` normalizes a single pair into an array of pairs before filtering:
```js
if (typeof arr[0] === 'number') arr = [arr];   // [728,90] → [[728,90]]
```
This is the central choke point all slots pass through, so it fixes the widget leaderboard
**and** the stacker, and is backward-compatible with the already-correct `[[…]]` shape (e.g.
`med_half`, takeover). When touching ad sizes, keep `validSizes()` tolerant of both shapes
rather than forcing every emitter to agree.

---

## 9. Takeover line‑item picker, GAM‑ID lookup, and wrap auto‑expiry (v3.3.79–v3.3.82)

The Masthead/Wrap takeover editor lets you link a takeover to the **GAM line item** that
delivers it, so the flight schedule shows in the UI and the wrap can start/stop itself.
The picker is in `admin/partials/engam-takeovers.php`; the data is the cached
`engam_v2_line_items` list (§1).

### The problem we hit
A brand‑new wrap line item (status **Ready**, **0 impressions**) **could not be found in
the picker**. Two compounding reasons:
1. The picker list comes from the **delivery report** (§1), which only returns line items
   that have actually *served* in the last 90 days. A zero‑impression item produces no
   report row, so it's simply not there.
2. There is **no way to scope the full `lineItems.list` to this site** to fill the gap —
   the list response contains **no targeting and no status** (proven by the §1 probe;
   fields are only `name, order, displayName, startTime, endTime, lineItemType, rate,
   budget, goal`). So you can't tell from the list which items run on this site.

> Dead ends already tried (don't repeat): merging the whole list (→ ~5,500 items);
> scoping the list by `targeting.inventoryTargeting.targetedAdUnits` (→ matches 0, the
> field isn't returned); a `fields` mask to force targeting (→ HTTP 400).

### The fix — direct GAM‑ID lookup
A single‑item `GET /networks/{net}/lineItems/{id}` **does** return the full resource
(name, displayName, startTime, endTime, …). So the picker has a **"…or paste a GAM line
item ID"** control next to the search box.

- `Equinenetwork_Gam_V2_API::lookup_line_item( $gam_id )` — strips non‑digits, `GET`s the
  single line item, maps it via `map_one_line_item()`.
- It **persists** the item in the durable option **`engam_v2_li_manual`** (`OPTION_MANUAL_LI`),
  keyed by `gam_id`, *and* drops it into the `engam_v2_line_items` cache for immediate use.
- `fetch_line_items()` **merges `engam_v2_li_manual` into every rebuild** (deduped by
  `gam_id`). This is the key durability step — otherwise the next hourly/cron cache rebuild
  (report‑only) would drop the manually‑wired item and it would vanish from the picker again.
- AJAX: `wp_ajax_engam_v2_lookup_line_item` → `Equinenetwork_Gam_V2_Admin::ajax_lookup_line_item()`
  (nonce `engam_v2_ajax`, cap `edit_others_posts`). The picker JS posts the ID, then fills the
  hidden ID field + label, updates the "View in GAM" link, and pushes the item into
  `window.engamLineItems` so re‑opening the dropdown shows it.
- Where to get the ID: the number after `line_item_id=` in the GAM line‑item URL.
- `engam_v2_li_manual` is added to `uninstall.php` cleanup. `clear_cache()` does **not**
  remove it (it's not a cache).

The normal search still covers the ~50 delivering items; paste‑ID is the escape hatch for
not‑yet‑delivered ones.

### Wrap auto‑expiry from GAM flight dates
Wraps don't store their own schedule (only mastheads have schedule fields). Pre‑v3.3.80,
`entry_is_live()` only read the stored `schedule_start`/`schedule_end`, so a wrap stayed
"Active" **forever** regardless of the GAM flight — the creative would stop in GAM but the
plugin kept rendering the wrap chrome and suppressing other ads.

`entry_is_live()` (and the admin badge `engam_to_status()`) now **fall back to the linked
GAM line item's flight window** when no schedule is stored:
- looked up via `gam_line_item_flight( $gam_id )`, which reads the cached line item by `gam_id`;
- GAM timestamps are **timezone‑aware (RFC3339)**, so they are compared against **UTC**
  (`time()`), *not* `current_time('timestamp')` (which is WP‑local and only correct for the
  naive masthead schedule strings). Don't collapse these two "now" values.
- This is why the flight‑date backfill in §1 matters — without dates on the cached item,
  there's nothing to expire against.

**Timing caveat:** expiry is driven by the cached line item, and the cache refreshes ~hourly
(45‑min cron, §1), so a wrap deactivates within ~an hour of its GAM end, not to the second.

### Not the plugin: the mobile "gray gap" above the wrap
Reported as a gray band between the wrap's background ad and the site header on mobile.
**It's the Elementor header template, not this plugin.** The empty header container
`.elementor-element-f85613b` (header `19125`) has a mobile rule `--min-height: 70px`
(`@media (max-width: 767px)`) with a dark background, reserving 70px with nothing in it.
Fix it in Elementor (clear that container's mobile min‑height / make it desktop‑only). Do
**not** add a plugin CSS override for it — the rule lives in the theme's header and a
plugin hack would silently break if the header is rebuilt.

---

## 10. Admin notice bar inherits GAM flight dates (v3.3.84)

When a wrap takeover is active but `show_to_admins` is false, logged‑in admins see a
fixed bottom **notice bar** instead of the full takeover (`TAKEOVER ACTIVE (admins see
this bar only): … — <start> → <end>`). This lives in the wrap branch of
`Equinenetwork_Gam_V2_Takeover::render()` (`public/class-equinenetwork-gam-v2-takeover.php`).

**The bug:** the bar formatted its dates from the stored `schedule_start` /
`schedule_end` fields only. Wraps never store those (only mastheads have schedule
fields — see §9), so the bar always fell back to its placeholder defaults and read
`Now → No end date`, even when the takeover admin list's **Schedule** column correctly
showed the inherited GAM flight window (e.g. `Jun 2, 2026 → Jul 1, 2026`).

**The fix:** resolve the linked GAM line item's flight dates first (via the existing
`gam_line_item_flight( $gam_id )` helper), falling back to any stored schedule, then to
the `Now` / `No end date` placeholders. This mirrors the resolution logic already used by
the admin list's Schedule column (`admin/partials/engam-takeovers.php`, ~line 294), so the
front‑end bar and the admin list now agree.

- Date format was normalized from `M j, Y g:i a` to `M j, Y` to match the list column.
- This is **display only** — `entry_is_live()` already inherited the flight window for the
  active/expiry decision (§9). This change just makes the admin bar's *label* consistent
  with that logic and with the admin list.

Shipped in PR #49 (branch `claude/serene-babbage-4s317n`).
