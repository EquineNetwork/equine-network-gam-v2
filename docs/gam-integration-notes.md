# EN GAM v2 — Integration Notes & Hard‑Won Lessons

Reference for how the tricky GAM integrations actually work, **why** the obvious
approaches failed, and the exact request shapes that finally worked. Written after the
v3.3.37–v3.3.48 work and extended through v3.3.82. If you touch line items, ad‑unit
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
| Durable flight‑date store (survives cache expiry, §11) | `Equinenetwork_Gam_V2_API::persist_flight_dates()` / `get_flight_dates_durable()` → option `engam_v2_li_flights` |
| Leaderboard template scoping (§10) | `public/class-equinenetwork-gam-v2-leaderboard.php` (`move()` JS) |
| Carousel schedule gate + ad‑slot skip (§12) | `public/class-equinenetwork-gam-v2-carousel-shortcode.php` (`gate_scheduled()`) · `public/partials/equinenetwork-gam-v2-public-footer.php` (skip `.engam-car-sched[data-engam-sched-off]`) |
| Reports / impressions page (§13) | `admin/partials/engam-reports.php` · `Equinenetwork_Gam_V2_API::get_impressions_report()` (option `engam_v2_impressions_report`) |
| Support page (Quick Start, moved off Dashboard) | `admin/partials/engam-support.php` |
| Brand design system (§14) | `admin/partials/engam-shared-styles.php` · logo assets `admin/img/en-icon-{dark,light}.png` |
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
| 3.3.83 | Onboarding setup wizard (`admin/partials/engam-onboarding.php`) |
| **3.4.0** | **Brand Guide v1.0 rebrand** (§14); new **Reports** (impressions) page (§13) and **Support** page; **leaderboard template scoping** fix (§10); **takeover durable flight dates** (§11); **carousel cache‑proof scheduling** (§12). One version bump for the whole batch. |
| 3.4.1 | Reports: **Refresh Cache** button + sortable Status/Impressions columns. Dashboard top cards → Total Impressions (90d) / GAM Line Items / Total Ad Placements. Removed the "Delete all data on uninstall" option — `uninstall.php` is now a no‑op that always preserves data (a previously‑set "1" can no longer wipe a site now that the toggle is gone). |
| 3.4.2 | Sponsor ID's: one‑click copy‑to‑clipboard button per row. **Mastheads** gained a "Show to admins" toggle (`engam_to_mh_show_to_admins`) + the front‑end admin notice bar, at parity with wraps — the bar is now the shared `Equinenetwork_Gam_V2_Takeover::admin_notice_bar()` helper. |
| 3.4.7 | Masthead scale `transform:scale()` → `zoom` (intended box‑reflow improvement; later reverted — see §15). |
| 3.4.8 | **Masthead desktop crop fix (Bug 1):** global `.equinenetworkad iframe{max-width:728px}` cap also matched the masthead and clipped its 2048px creative to 728px. Scoped to `:not(.engam-masthead-ad)` + `max-width:none` for the masthead, base **and** mobile rules (§15). |
| 3.4.9 | **Masthead mobile under‑scale fix (Bug 2):** scale measured the layout viewport (~980px) not the visual width — under‑scaled and clipped on phones. Use `getBoundingClientRect().width` clamped to `window.innerWidth`; re‑scale on `orientationchange` (§15). |
| 3.4.10 | **Masthead blank‑on‑iPhone fix (Bug 4, final):** iOS Safari doesn't reliably apply `zoom` to a cross‑origin ad iframe → blank black bar (Chrome emulation hid it). Reverted to `transform:scale()` + explicit wrapper height (§15). |
| 3.4.11 | **Activation fatal fix:** `wp_tempnam()` (the no‑Azure sponsor‑sheet XLSX reader) is defined in `wp-admin/includes/file.php`, only auto‑loaded on admin requests — it fataled on the front end / cache‑warming cron. Load the file on demand (`function_exists` guard). |
| 3.4.12 | **Masthead targeting fix:** `masthead_is_targeted()` matched `is_front_page() \|\| is_home()`. `is_home()` is true on the WP **"Posts page"** (Settings → Reading), so a homepage‑only masthead leaked onto the blog index (e.g. The Horse `/news/`). Dropped `is_home()` → "Show on Homepage" = the actual front page only; no regression for sites whose homepage *is* the blog index. |

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

## 10. Leaderboard template scoping (v3.4.0)

**Symptom (reported on Horse&Rider):** a leaderboard assigned to a specific Elementor
header template (e.g. "Performance Report Header") showed on **every** page — and where a
generic "Header" leaderboard was also active, **two** leaderboards stacked after the site
header (duplicate ads).

**Root cause** — the front‑end injector `move()` in
`public/class-equinenetwork-gam-v2-leaderboard.php`. For a template‑scoped position
(`header_tmpl_<id>` / `footer_tmpl_<id>`) it looked for the template wrapper
`.elementor-<id>` on the page; **when that wrapper was absent it fell back to the generic
`<header>` and injected anyway.** So template scoping was never enforced, and the fallback
is what stacked duplicates next to a generic‑header leaderboard.

**Fix** — a template‑assigned leaderboard now renders **only** where its template is the
active header/footer:
- if `header_tmpl_<id>` and `.elementor-<id>` is **not** on the page → **drop the slot**
  (`node.remove()`); never fall back to the generic header.
- a one‑leaderboard‑per‑header/footer guard (`data-engam-lb-done`) so two slots can never
  stack on the same element.
- generic `header` / `footer` positions remain a true catch‑all for whoever explicitly
  picks them. Templates with **no** assignment get nothing.

**Verified** with a synthetic DOM test (active template kept + placed after its header;
non‑matching template removed; duplicate generic dropped). Real‑Elementor validation still
wants a staging check.

---

## 11. Takeover flight dates survive cache expiry (v3.4.0)

A *wrap* takeover stores no schedule of its own — it inherits the flight window from its
linked GAM line item, resolved by `gam_line_item_flight()`, which read **only** the 1‑hour
`engam_v2_line_items` cache. When that cache expired, three things broke at once:

1. **admin Takeovers list** Schedule column went blank (`— → —`);
2. **front‑end admin notice bar** showed `Now → No end date` for wraps (it read the wrap's
   *empty* stored schedule and never inherited the GAM dates — a second, separate bug);
3. **`entry_is_live()` lost start/stop enforcement** — with no flight to compare against it
   returned "live", so a wrap could serve **past its GAM end date** (or before its start)
   any time the cache happened to be cold. (Ad‑delivery correctness, not just display.)

**Fix** — a **durable** last‑known flight‑date store, `engam_v2_li_flights` (an option, not
a transient), keyed by GAM ID:
- `Equinenetwork_Gam_V2_API::persist_flight_dates()` merges it on every `fetch_line_items()`
  and every `lookup_line_item()` (merge, not replace — a line item that drops out of the
  per‑site list keeps its last‑known dates).
- `gam_line_item_flight()` falls back to it when the 1‑hour cache is cold.
- the admin notice bar now inherits the GAM window for wraps; the admin list folds the
  durable store into its line‑item map.

Still "live from GAM" — the durable copy is just a safety net. **Verified** with a 5‑case
test (cold cache → flight resolves; in‑flight live; expired NOT live; not‑yet‑started NOT
live; missing → null) and a live dev check with the transient deleted.

---

## 12. Carousel cache‑proof scheduling (v3.4.0)

**Goal:** drop `[en_carousel id="…"]` on a page; on the schedule **start** it appears, on the
**end** it disappears (the schedule activates/deactivates it); with no schedule, the manual
Activate/Deactivate toggle on the list page controls it. `Carousel_Render::is_visible()`
already implemented exactly that (schedule overrides the manual flag) and the shortcode
honored it — **server‑side**.

**The catch: full‑page caching (Kinsta).** A server‑side decision is frozen into the cached
HTML, so the on/off transition lagged until the page cache regenerated.

**Fix — gate scheduled carousels in the browser** (`gate_scheduled()` in
`public/class-equinenetwork-gam-v2-carousel-shortcode.php`):
- scheduled carousels render their markup **always**, wrapped in `.engam-car-sched` with the
  window emitted as **UTC epochs** (`get_gmt_from_date()` → `strtotime`), `style="display:none"`.
- an inline script compares the viewer's clock (`Date.now()`) to the window: in‑schedule →
  reveal; out → mark `data-engam-sched-off` and collapse the wrapping Elementor container.
  Transition now depends on **view time, not cache time**.
- the footer ad‑init **skips** `.equinenetworkad` inside a `.engam-car-sched[data-engam-sched-off]`
  wrapper, so an out‑of‑schedule carousel never requests ads. (The gate runs before GPT.)
- **non‑scheduled** carousels keep server‑side behavior — a manual toggle isn't time‑based, so
  it legitimately takes effect on the next cache refresh.

**⚠️ Gotcha — `&&` in inline shortcode scripts.** Scripts returned from a shortcode pass
through WordPress content filters, which **HTML‑encode `&&` to `&#038;&`** → JS syntax error
("Invalid or unexpected token") → the script silently doesn't run. This had also been
breaking the original manual‑deactivate container‑collapse script. **Never use `&&` (or other
raw `&`) in inline scripts emitted by a shortcode** — use nested `if`s / guard clauses. Both
scripts were rewritten accordingly.

**Verified** end‑to‑end on a real dev front‑end page across all five schedule/active combos.

---

## 13. Reports / impressions page (v3.4.0)

`admin/partials/engam-reports.php` (menu: **EN Ads → Reports**) shows total GAM impressions
(last 90 days) plus an impressions‑by‑line‑item list (sorted high→low, status badge, "View
in GAM").

**Where the data comes from:** the ad‑unit report (§1) already requested the
`AD_SERVER_IMPRESSIONS` metric purely to *detect* which line items run on the site — and
**threw the number away**. Now `run_ad_unit_report()` captures it per row
(`report_metric_int()`, tolerant of the v1 `metricValueGroups` and legacy `metricValues`
shapes) and persists a sorted breakdown + 90‑day total to the option
`engam_v2_impressions_report`. `get_impressions_report()` reads it for the page, so it
refreshes whenever the line‑item cache rebuilds (Settings → Refresh Cache, or the cron warm).

**Not yet:** per‑placement‑type impressions (cards are placement *types*; GAM reports by line
item — would need the report re‑scoped by ad unit + ad‑unit→slot name mapping). The metric
JSON path should be reconfirmed against a live GAM response (written defensively for now).

---

## 14. Brand foundation — Brand Guide v1.0 (v3.4.0)

A full admin‑UI rebrand, applied through the shared design system so it cascades to every
screen, with per‑screen inline cleanups on top.

- **Single source of truth:** `admin/partials/engam-shared-styles.php` — the `eg-` design
  system (CSS custom properties on `#engam-v2-wrap`). Change tokens here, every screen
  follows.
- **Tokens:** display font **Space Grotesk**, UI font **IBM Plex Sans** (Google Fonts);
  lime `#d0ff00` → **`#C8FF00`**; chrome `#111` / `#0d0d0d`; borders **`#E8E8E8`**; surface
  `#F5F5F5`; **8px** card radius / **6px** input‑button radius; pastel status badges
  (active `#E8F5C8`, scheduled blue, expired/error `#FDE8E8`); **light table headers** (the
  black header bars are gone).
- **Logo:** real EN icon assets `admin/img/en-icon-dark.png` (lime‑on‑black, for the dark
  mastheads/sidebar) and `en-icon-light.png` (black‑on‑lime, for light surfaces), per the
  guide's two‑surface rule (header/footer/sidebar stay permanently dark; inner content is
  white). The masthead `.eg-logo` renders the dark icon.
- **Two new pages** shipped with it: **Reports** (§13) and **Support** (Quick Start guide
  moved off the Dashboard + quick links + contact card).

**Dead code:** `admin/partials/equinenetwork-gam-v2-admin-display.php` is unused scaffold
(not included anywhere — the metabox renders inline in its class). Candidate for deletion.

---

## 15. Masthead rendering: the cropped/blank banner saga (v3.4.7–v3.4.10)

The homepage masthead (slot `homepagetakeover`, served as a **2048×300** creative) took four
versions to render correctly on every device. Four *distinct* bugs hid behind the same
"masthead looks wrong" symptom — fixing one revealed the next. The order of diagnosis matters,
so the whole chain is recorded here.

### Background: how the masthead scales

`masthead_html()` (`public/class-equinenetwork-gam-v2-takeover.php`) emits
`<div class="engam-masthead" style="width:100%;overflow:hidden;…">` wrapping a slot div, with
`data-sizes` defaulting to `[[2048,300]]` on a cold cache (real sizes warmed via the
`engam_warm_slot_sizes` event). There is **no viewport size mapping** on the masthead, so GAM
serves the same 2048‑wide creative on every device. The footer ad‑init
(`public/partials/equinenetwork-gam-v2-public-footer.php`, `slotRenderEnded`) then scales that
2048px creative down to the wrapper width.

### Bug 1 — desktop right‑crop (v3.4.8, the real first fix)

**Symptom:** "PRECISE NUTRIT…" cut off on the right; wrapper stuck at a fixed height.
**Cause:** a global rule in `public/partials/equinenetwork-gam-v2-public-header.php`,
`​.equinenetworkad iframe { max-width: 728px !important }`, meant to cap **in‑content
leaderboards**, *also* matched the masthead (its wrapper carries the shared `equinenetworkad`
class) and clamped the 2048px creative to 728px. Confirmed live by unchecking the rule in
DevTools.
**Fix:** scope the cap to `.equinenetworkad:not(.engam-masthead-ad)` and add
`.engam-masthead-ad iframe { max-width: none !important }`, for **both** the base rule and the
`@media (max-width:728px)` mobile rule (which clamps to 320px).

> **Gotcha:** `.equinenetworkad` is the shared class on *every* ad type (leaderboards,
> stackers, carousels, masthead, wrap panels). Any `.equinenetworkad …` rule is global to all
> placements — scope per‑type rules with the type's modifier class (`.engam-masthead-ad`,
> `.engam-stacker`, `.engam-car`, …) or `:not()`. When an ad is **clipped at native size**
> (not shrunk), suspect a `max-width`/`width` cap **before** the JS scale technique.

### Bug 2 — mobile under‑scale clip (v3.4.9)

**Symptom:** after Bug 1, fine on desktop but a clipped black bar on phones.
**Cause:** the scale read `document.documentElement.clientWidth`, which on mobile can report
the **layout viewport (~980px)** instead of the real **~390px visual width** → the 2048px
creative was under‑scaled, overflowed, and the `overflow:hidden` wrapper clipped it. (Removing
the 728px cap *exposed* this — before, the cap accidentally limited the damage.)
**Fix:** measure `mastheadWrap.getBoundingClientRect().width` and clamp to
`window.innerWidth` (the true visual viewport on mobile); re‑scale on `resize` /
`orientationchange` + settle timers.

### Bug 3 — the `zoom` detour (v3.4.7, reverted in v3.4.10)

v3.4.7 switched the scale from `transform:scale()` to **`zoom`**, reasoning that `zoom`
reflows the box so the `overflow:hidden` wrapper self‑sizes (no manual height, no
`transform-origin`). This is *true in Chrome* — but see Bug 4. It was never the crop fix
(Bug 1 was), and it introduced the iOS failure below.

### Bug 4 — blank black bar on real iPhones (v3.4.10, the final fix)

**Symptom:** rendered perfectly in **Chrome device‑emulation** (iPhone preset, 390px:
`zoom 0.19`, iframe 390×57, full banner) but was a **blank black bar on an actual iPhone**.
**Cause:** **iOS Safari (WebKit) does not reliably apply CSS `zoom` to a cross‑origin ad
iframe** — the scaled creative never paints. Chrome's emulation uses Chrome's engine, so it
**cannot reproduce WebKit rendering bugs** — that gap is exactly what hid this. The "works in
responsive mode, broken on the device" signature is the tell.
**Fix:** scale with **`transform: scale()`** (solidly supported on WebKit for iframes), keep
the robust `getBoundingClientRect()`/`innerWidth` measurement from v3.4.9, and — because
`transform` does **not** reflow — set the wrapper height explicitly to `adH * scale`.

> **Gotcha (the durable lesson):** **scale ad iframes with `transform: scale()`, not `zoom`.**
> `zoom` is a WebKit trap for cross‑origin iframes. And **device‑emulation in Chrome/Firefox
> validates layout, not engine behavior** — a masthead/ad rendering bug must be confirmed on a
> real iOS Safari device (or BrowserStack), never just responsive mode.

### Current state (v3.4.10)

`transform: scale(avail / adW)` with `transform-origin: top left`; `avail =
min(getBoundingClientRect().width, window.innerWidth)`; wrapper height set to
`round(adH * scale)`; re‑scale on `resize` + `orientationchange` + 250ms/1s timers. Verified
edge‑to‑edge on desktop, Chrome mobile emulation, and a real iPhone.
