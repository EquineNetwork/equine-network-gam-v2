# CLAUDE.md — EquineNetwork GAM v2

Working notes for anyone (including a fresh Claude session) picking up this plugin.
Deep engineering detail lives in **`docs/gam-integration-notes.md`** — read it before
touching line items, ad‑unit scoping, takeovers, stackers, or leaderboard placement.

## What this is

A WordPress plugin that wires an Equine Network site to **Google Ad Manager** and renders
ad placements driven live from the GAM API (service‑account auth). Placement types:
**Leaderboards**, **Takeovers** (Masthead + Wrap), **Carousels**, **Stackers**, an Elementor
**EN Ad Slot** widget, and a per‑post **EN Campaign** sponsor override. Sponsor IDs come from
a SharePoint/OneDrive spreadsheet (Microsoft Graph or a no‑Azure share link).

Runs on multiple EN sites (Horse&Rider, National Team Roping, The Team Roping Journal, EQUUS…).

- **Current version:** `3.4.12` (set in `equinenetwork-gam-v2.php` — header `Version:` **and**
  the `EQUINENETWORK_GAM_V2_VERSION` constant; keep both in sync).
- **Dev branch:** `claude/friendly-sagan-2GrGy`.

## ⚠️ Deploy mechanism — how changes reach live sites

The bundled **plugin‑update‑checker** is pinned to the **`main` branch**
(`$engam_updater->setBranch('main')` in `equinenetwork-gam-v2.php`). There are **no GitHub
Releases** and **no CI**. So:

> **To ship:** bump the version (both spots) → commit → push the branch → open a PR → **merge
> to `main`**. Sites then update via **wp‑admin → Plugins/Updates → Check Again**, then
> **Clear Caches** (Kinsta/WP Engine).

Merging to `main` updates **every** site tracking `main`, not just one. Confirm intent before
merging. Commit/PR conventions: end commit messages and PR bodies with the session URL line
(the harness enforces this).

### Pre‑release checks (do these before merging code changes to `main`)

There is no CI, and the dev harness verifies front‑end rendering but doesn't always exercise
the **activation / cron** path on a pristine install — which is how the `wp_tempnam()` fatal
reached a live host (§ that bug: an admin‑only function called in a front‑end/cron context).
Two cheap checks catch most of this class of issue; **ask the user to run them on the branch
before merge** (they're interactive desktop/WP.org tools — a headless container can't run
them):

1. **Plugin Check** (<https://wordpress.org/plugins/plugin-check/>) — flags admin‑only
   functions used without loading the file, missing escaping/i18n, forbidden functions, etc.
   Report and resolve any **errors** (warnings: judgement call).
2. **WP Studio smoke test** — on a fresh local site: **activate the plugin** and load a
   **front‑end page** + a wp‑admin EN Ads screen. This surfaces activation/cron fatals and
   white‑screens (like the `wp_tempnam()` one) that pure CSS/markup testing misses.

A **plugin‑SDK** (shipping ~mid‑2026) is expected to streamline this local test/validate loop —
prefer it once available if it can run in this container.

## Brand — Brand Guide v1.0

Single source of truth: **`admin/partials/engam-shared-styles.php`** (the `eg-` design system,
CSS custom properties on `#engam-v2-wrap`). Tokens:

- Fonts: **Space Grotesk** (display/headings), **IBM Plex Sans** (UI) — via Google Fonts.
- Lime **`#C8FF00`** (not the old `#d0ff00`); chrome `#111` / `#0d0d0d`; borders `#E8E8E8`;
  surface `#F5F5F5`; muted `#888`.
- **8px** card radius, **6px** input/button radius; **light** table headers; pastel status
  badges (active `#E8F5C8`, scheduled blue, expired/error `#FDE8E8`).
- Logos: `admin/img/en-icon-dark.png` (lime‑on‑black, dark surfaces) and `en-icon-light.png`
  (black‑on‑lime, light surfaces). Header/footer/sidebar stay dark; inner content is white.

## Admin screens (menu: "EN Ads")

Registered in `admin/class-equinenetwork-gam-v2-admin.php` (`add_menu()`), each a partial:

| Screen | Partial |
|---|---|
| Dashboard | `admin/partials/engam-dashboard.php` |
| Reports (impressions) | `admin/partials/engam-reports.php` |
| Leaderboards | `admin/partials/engam-leaderboards.php` |
| Takeovers | `admin/partials/engam-takeovers.php` |
| Carousels | `admin/partials/engam-carousels.php` |
| Stackers | `admin/partials/engam-stackers.php` |
| Sponsor ID's | `admin/partials/engam-campaigns.php` |
| Settings | `admin/partials/engam-settings.php` |
| Support | `admin/partials/engam-support.php` |
| Onboarding wizard (modal, `admin_footer`) | `admin/partials/engam-onboarding.php` |

Front end: `public/class-equinenetwork-gam-v2-{leaderboard,takeover,carousel-render,carousel-shortcode}.php`
and the ad‑init in `public/partials/equinenetwork-gam-v2-public-footer.php`. The GAM API
(auth, line items, Reports API, impressions, sponsor sheet) is
`includes/class-equinenetwork-gam-v2-api.php`.

`admin/partials/equinenetwork-gam-v2-admin-display.php` is **dead scaffold** (not included
anywhere) — safe to delete.

## Gotchas (learned the hard way)

- **No `&&` in inline scripts returned from a shortcode.** WordPress content filters encode
  `&&` → `&#038;&`, which is a JS syntax error and the script silently doesn't run. Use nested
  `if`s / guard clauses. (Admin partials are echoed directly and are fine.) See §12 of the
  engineering notes.
- **`.equinenetworkad` is shared by every ad type** (leaderboards, stackers, carousels,
  masthead, wrap panels). Any `.equinenetworkad …` CSS is global to all placements — the
  `max-width:728px` iframe cap in `public/partials/equinenetwork-gam-v2-public-header.php`
  silently clipped the 2048px **masthead** to 728px until it was scoped with
  `:not(.engam-masthead-ad)`. Scope per‑type rules with the type's modifier class (§15).
- **Scale ad iframes with `transform: scale()`, not `zoom`.** iOS Safari (WebKit) doesn't
  reliably apply `zoom` to a cross‑origin ad iframe — it rendered the masthead as a blank
  black bar on real iPhones while Chrome device‑emulation showed it fine. The masthead scale
  lives in `…public-footer.php` (`slotRenderEnded`); it uses `getBoundingClientRect().width`
  clamped to `window.innerWidth` and sets the wrapper height explicitly (§15).
- **Device‑emulation validates layout, not engine behavior.** Chrome/Firefox responsive mode
  uses their own engine, so it cannot reproduce iOS Safari/WebKit rendering bugs. Confirm
  masthead/ad rendering on a **real iPhone** (or BrowserStack), not just responsive mode (§15).
- **`is_home()` ≠ the front page.** It's true on the WP **"Posts page"** (Settings → Reading),
  e.g. The Horse's `/news/`. The masthead "Show on Homepage" toggle once matched
  `is_front_page() || is_home()`, which leaked the masthead onto that blog index. "Homepage"
  targeting should be **`is_front_page()` only** (`masthead_is_targeted()` in
  `public/class-equinenetwork-gam-v2-takeover.php`); other pages go under "Additional Pages".
- **Admin‑only WP functions need their file loaded in front‑end/cron contexts.** `wp_tempnam()`
  (and friends in `wp-admin/includes/file.php`) aren't auto‑loaded outside admin screens —
  guard with `function_exists()` + `require_once ABSPATH . 'wp-admin/includes/file.php'`. This
  is the `wp_tempnam()` activation white‑screen (fixed v3.4.11); Plugin Check flags the class.
- **Indentation is per‑file**: some files use tabs, some spaces. Match the file you're editing.
- **GAM flight dates** are resolved through a durable store (`engam_v2_li_flights`) so they
  survive the 1‑hour line‑items cache expiring — don't reintroduce a cache‑only lookup (§11).
- **Impressions** (Reports page + Dashboard "Total Impressions") come from the ad‑unit report
  that runs inside `fetch_line_items()`; it refreshes on the 45‑min cron and on manual Refresh
  Cache, stored in option `engam_v2_impressions_report` (§13).

## Local dev / test harness

The container is ephemeral — recreate the test site each session. Two complementary tools live
in **`tools/dev/`** (tailored to the Claude Code web container: Node at `/opt/node22`,
Playwright browsers at `/opt/pw-browsers`; `wordpress.org` is network‑blocked but GitHub works,
so WordPress core + wp‑cli are pulled from GitHub):

1. **`tools/dev/setup-wp.sh`** — downloads WordPress (GitHub mirror) + wp‑cli + the SQLite
   drop‑in, installs to `/tmp/wpsite` (admin/admin), symlinks this plugin in, and runs the
   seed. Visit by starting `php -S localhost:8765 -t /tmp/wpsite`.
2. **`tools/dev/seed.php`** — seeds realistic data (GAM id, fake credentials so `is_configured()`
   is true, SharePoint config, sample leaderboards/takeovers/carousels/stackers, and the cache
   transients `engam_v2_line_items` / `engam_v2_sponsor_options` / `engam_v2_ai_category_values`
   / `engam_v2_impressions_report` so screens render populated without a live GAM).
3. **`tools/dev/shoot.mjs`** — logs into wp‑admin via Playwright and screenshots a page, e.g.
   `node tools/dev/shoot.mjs dashboard admin.php?page=equinenetwork-gam-v2 /tmp/out.png`.

For pure‑markup/CSS work without a DB, you can also render a partial through a WordPress‑function
stub — but the real WP install above is preferred because it runs the actual save/POST logic and
classes. Always `php -l` changed files; verify behavioral changes by driving the real front end
(see how the carousel‑schedule and leaderboard‑scoping fixes were tested in the engineering notes).

## Open follow‑ups

- Support page contact email is a placeholder: `adops@equinenetwork.com`.
- The three 3.4.0 behavioral fixes (leaderboard scoping, takeover flight dates, carousel
  cache‑proof scheduling) were verified by synthetic + dev tests; validate on a real
  Elementor + Kinsta‑cache site when convenient.
- Per‑placement‑type impressions on the Dashboard cards is still a scoped follow‑up (needs the
  report re‑scoped by ad unit + ad‑unit→slot name mapping).
