=== EquineNetwork GAM v2 ===
Contributors: Whitney Mitchell
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 3.4.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Inject Google Ad Manager and serve ads dynamically across an Equine Network site — leaderboards, takeovers, carousels, stackers, and Elementor ad slots, driven live from GAM.

== Description ==

EquineNetwork GAM v2 wires a WordPress site to Google Ad Manager and renders ad
placements with no manual line-item bookkeeping. It pulls active line items and impressions
straight from the GAM API (service-account auth) and serves the right creative per
placement and per page.

**Placements**

* **Leaderboards** — auto-injected (no Elementor container needed) below the site header, at
  the top of the footer, into a specific Elementor header/footer template, or halfway down a
  chosen page ("Half Page").
* **Takeovers** — full-width **Mastheads** above the header and full-page branded **Wrap**
  takeovers, each linked to a GAM line item and inheriting its flight dates (only one active
  at a time).
* **Carousels** — reusable post/manual carousels with interleaved GAM ad slides, dropped onto
  any page with a shortcode and optionally scheduled.
* **Stackers** — a 320×480 in-content slot injected into posts; GAM serves by its own
  AI-category targeting.
* **Elementor "EN Ad Slot"** widget with size presets, and a per-post "EN Campaign" sponsor
  override.

**Data & integrations**

* Live GAM line items + impressions via the Reports API, with 45-minute cron cache warming.
* Sponsor IDs from a SharePoint / OneDrive spreadsheet (Microsoft Graph or a no-Azure
  share-link), plus manual entries; one-time migration from legacy ACF sponsor fields.
* A **Reports** page (impressions by line item) and a guided **Setup Wizard**.

The admin UI follows Equine Network Brand Guide v1.0. Engineering detail — how the GAM
integrations actually work and why the obvious approaches failed — lives in
`docs/gam-integration-notes.md`.

Updates are delivered from the project's GitHub repository via the bundled update checker
(it tracks the `main` branch); this plugin is not distributed on the WordPress.org directory.

== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload `equinenetwork-gam.php` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Place `<?php do_action('plugin_name_hook'); ?>` in your templates

== Frequently Asked Questions ==

= A question that someone might have =

An answer to that question.

= What about foo bar? =

Answer to foo bar dilemma.

== Screenshots ==

1. This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Note that the screenshot is taken from
the /assets directory or the directory that contains the stable readme.txt (tags or trunk). Screenshots in the /assets
directory take precedence. For example, `/assets/screenshot-1.png` would win over `/tags/4.3/screenshot-1.png`
(or jpg, jpeg, gif).
2. This is the second screen shot

== Changelog ==

= 3.4.4 =
* Fixed: the EN Ad Slot Elementor widget showed "No active campaigns found" in the
  Sponsor / Campaign ID picker even when the Sponsor ID's screen listed active sponsors.
  The widget was reading the legacy `equinenetwork_gam_v2_campaigns` option (a defunct
  Campaign Manager with no admin screen) instead of the connected sponsor sheet. It now
  pulls the same source as the Sponsor ID's page and the carousel widget, so every active
  sponsor appears in the dropdown. The selected value is emitted as data-sponsorid and set
  on the GAM `sponlineitemid` targeting key, which is what line items serve on — so picking
  a sponsor here (instead of mis-typing it into Slot Name Override) makes the line item match.

= 3.4.3 =
* Added: Super Leaderboard (728x300 desktop / 320x50 mobile) ad size to the EN Ad Slot widget.

= 3.4.2 =
* Sponsor ID's: each row has a one-click copy icon that copies the Sponsor ID to the
  clipboard (with a "Copied!" confirmation).
* Mastheads: added a "Show to admins" toggle (Visibility), matching Wrap Takeovers — when
  off, admins/editors see the front-end notice bar instead of the live masthead. The bar
  is now a shared helper used by both mastheads and wraps.

= 3.4.1 =
* Reports: added a Refresh Cache button (masthead + empty state) so impressions can be
  pulled without going to Settings; made the Status and Impressions columns sortable.
* Dashboard: top three stat cards are now Total Impressions (90d), GAM Line Items, and
  Total Ad Placements.
* Removed the "Delete all data on uninstall" option — uninstall now always preserves plugin
  data (settings, credentials, saved ads, sponsor IDs). A site that previously had the
  toggle set to "1" will no longer wipe data now that the control is gone.

= 3.4.0 =
Brand refresh + two new pages + three behavioral fixes (shipped as one version bump).

* Branding: full admin-UI rebrand to Brand Guide v1.0 — Space Grotesk + IBM Plex Sans
  fonts, new lime (#C8FF00), 8px rounded cards, softened borders, pastel status badges,
  light table headers, and the real EN logo icons in every header. Applied through the
  shared design system (admin/partials/engam-shared-styles.php) so every screen updates
  together.
* New: Reports page (EN Ads → Reports) — total GAM impressions (last 90 days) plus an
  impressions-by-line-item list (sorted high→low, status, "View in GAM"). Uses the
  AD_SERVER_IMPRESSIONS metric the ad-unit report already fetched.
* New: Support page (EN Ads → Support) — the Quick Start guide moved off the Dashboard,
  plus quick links and a contact card. Dashboard now ends cleanly after the placement
  cards.
* Fixed (Leaderboards): a leaderboard assigned to a specific Elementor header/footer
  template now renders ONLY on pages where that template is active. Removed the fallback
  that injected it after the generic header on every page, which caused duplicate /
  wrong-page leaderboards. Added a one-leaderboard-per-header guard.
* Fixed (Takeovers): wrap takeovers now keep their schedule, admin notice bar, and
  start/stop enforcement working even after the 1-hour line-item cache expires (a durable
  flight-date store). Previously a wrap could keep serving past its GAM end date on a cold
  cache, the admin Schedule column went blank, and the front-end bar showed
  "Now → No end date".
* Fixed (Carousels): scheduled carousels now appear/disappear at the exact scheduled time
  even on fully cached (e.g. Kinsta) pages — the schedule is evaluated in the browser at
  view time. Out-of-schedule carousels no longer request ads. Also fixed a latent bug
  where WordPress content filters broke inline shortcode scripts (encoding "&&"), which
  had silently disabled the manual-deactivate container collapse.
* Removed internal record IDs from the Leaderboards and Takeovers list tables (cleaner UI;
  IDs still used under the hood).

= 3.3.2 – 3.3.83 =
Iterative release line (not individually logged here). Highlights: per-site GAM line-item
detection via the Reports API and 45-min cron cache warming; "Half Page" leaderboard
placement; Stackers single-card redesign with read-only GAM AI categories; SharePoint /
OneDrive sponsor-sheet integration (Microsoft Graph + no-Azure share-link path) with a
worksheet-tab picker; takeover line-item picker with direct GAM-ID lookup and wrap
auto-expiry from GAM flight dates; ACF sponsor-ID migration; manual sponsor entries; and an
onboarding setup wizard. Full engineering detail in docs/gam-integration-notes.md.

= 3.3.1 =
* Added: GAM Line Items panel on the Leaderboards page. Reads the cached line
  item list and filters for any line item with "leaderboard" in the name, then
  displays them read-only with name, delivery status, flight dates, and GAM ID.
  Same pattern as the Stackers page panel introduced in 3.3.0.

= 3.3.0 =
* Added: GAM Line Items panel on the Stackers page. Reads the cached line
  item list and filters for any line item with "stacker" in the name, then
  displays them read-only with name, delivery status, flight dates, and GAM
  ID. Sorted with Delivering first, then Ready, then Paused/Completed.
  Shows a prompt to refresh the cache if none are found. This lets anyone
  on the team see what stacker campaigns are running in GAM without needing
  a GAM login.

= 3.2.9 =
* Fixed: Wrap takeover BG banner not filling full width. The scale was capped
  at 1.0 (Math.min) which prevented the image from scaling up when the viewport
  was wider than the creative. The BG now always scales to exactly fill the
  available width between the side panels. Also fixed the BG panel left offset
  so it sits flush against the right edge of the left panel rather than starting
  at the body edge.

= 3.2.8 =
* Changed: Stacker form simplified — no GAM line item picker. The plugin
  injects the stacker slot (/networkId/sitename/stacker, 320x480) into post
  content and GAM handles all targeting and creative selection. The stacker
  list shows the full GAM ad unit path for reference.
* Changed: "Child Ad Unit" field removed — slot name is always "stacker".
* Changed: "Stacker active" toggle removed from the edit form. New stackers
  default to active; existing stackers preserve their state on edit.
  Activation is controlled exclusively from the list, matching how takeovers work.

= 3.2.7 =
* Fixed: Leaderboards admin page showed a false "Conflict" warning claiming leaderboards
  would not display while a Wrap Takeover was active. Leaderboards have always rendered
  independently on the front end — the warning was incorrect and has been removed.

= 3.2.6 =
* Added: GAM slot mismatch detection. When GAM serves a different line item than
  the one configured in a wrap takeover, the plugin now detects this automatically
  using the lineItemId exposed in GPT's slotRenderEnded event and alerts in two ways:
  1. Real-time red bar on the front end, visible to any logged-in admin browsing
     the site — shows which takeover is affected, what was configured, and what
     was actually served.
  2. Persistent warning notice in the WP admin dashboard, visible to all admins
     who have edit_posts capability. The notice lists every affected takeover,
     the configured vs. served line item IDs, the time detected, and a link to the
     page where it was detected. Each warning is individually dismissible, and a
     "Dismiss All" button clears the full list.
  Root cause this catches: a competing active GAM line item (e.g. one left running
  from a previous test) winning the auction ahead of the configured line item.
  Without this, team members without GAM access had no way to know the wrong ad
  was serving.

= 3.2.5 =
* Fixed: Wrap takeover panels never requested ads — console showed
  "[GPT] Error in googletag.display: could not find div with id
  engam-wrap-slot-left in DOM for slot". The slot divs were only injected into
  the DOM inside the slotRenderEnded handler, but googletag.display() runs
  before any render event fires, so the divs didn't exist yet and the slots
  never requested. The panels are now injected (hidden, display:none) right
  before the display() calls; they reveal themselves once a creative renders.

= 3.2.4 =
* Fixed: A masthead or wrap takeover could show as "Active" with no GAM line
  item selected (and therefore nothing to deliver). An entry with no line item
  is now never treated as live: the admin list shows a "No Line Item" status
  instead of Active, the front end never renders it, and it no longer suppresses
  the leaderboards. Introduced a single entry_is_live() helper used by all
  active checks (has_active, has_active_wrap, get_active) so the rule is
  enforced consistently in one place.

= 3.2.3 =
* Fixed: Masthead GAM line item selection silently lost on save. The masthead
  and wrap forms share one <form>, and both had a hidden input named
  engam_to_gam_line_item_id. Even when the wrap section was hidden, its empty
  input still submitted and — being last in the DOM — overwrote the masthead's
  value (PHP keeps the last duplicate-named field). The wrap input is now named
  engam_to_wrap_gam_line_item_id and read separately, so each type keeps its own
  line item. This bug affected every site, not just fresh installs.
* Changed: Removed the Active/Status toggle from the masthead and wrap takeover
  edit forms. Activation is now controlled solely from the Activate/Deactivate
  buttons in the list (the list still shows the Status column). Editing an entry
  preserves its current active state; new entries default to active.
* Audit: Verified all three GPT slot-registration paths (footer ad loop, wrap
  panels, and masthead) use the per-slot null guards introduced in 3.2.1/3.2.2,
  so one misconfigured slot can never block the rest on any site.

= 3.2.2 =
* Fixed: Wrap takeovers not displaying when no GAM ad unit slot name was set.
  The wrap's 3 GPT panel slots (left/right/bg) were only defined inside an
  `if (slotName)` block, so an empty slot name meant none of the slots ever
  registered and the wrap silently rendered nothing. Wrap slots now target the
  network root ad unit directly when no slot name is provided, and panels are
  differentiated purely by the pos targeting key (left / right / bg). Same
  null-guard pattern as the 3.2.1 leaderboard fix is applied to each slot.
* Removed: the "GAM Ad Unit Slot Name" field from the wrap takeover form. It is
  no longer needed — the wrap reads its ad unit from the network ID and pos
  targeting automatically. Existing records with a slot name still honor it.
* Changed: Wrap takeover Page Targeting is now three multi-select search
  fields — Pages, Posts, and Categories — replacing the old combined
  pages/posts picker and the free-text category-slug input. Categories search
  live via a new engam_v2_search_terms AJAX endpoint and store as slugs for
  backward compatibility with existing targeting.

= 3.2.1 =
* Fixed: GAM ad slots (leaderboards, mastheads, etc.) failing to fire on some
  sites. When any single ad widget on a page had invalid or zero-area sizes,
  googletag.defineSlot() returned null and the following .addService() call
  threw a TypeError. Because all slots register inside one googletag.cmd queue,
  that one error aborted the entire queue and prevented every other slot —
  including the leaderboards — from registering, so no ad requests were ever
  sent to GAM. Hardened the registration loop so it is resilient to bad slots:
  - Added a null guard after defineSlot(); a slot that fails to register is now
    skipped with `continue` instead of crashing the whole queue.
  - Use an explicit null check for the 'fluid' fallback instead of the `||`
    operator so size 0 / empty arrays resolve to 'fluid' correctly.
  - Filter zero-area sizes ([0,0]) out of size mappings before passing them to
    GPT (these came from misconfigured widgets and produced console warnings).
  - Track registered div IDs to prevent "div already associated with another
    slot" duplicate-registration errors.
  - Only set the sponlineitemid targeting key when a sponsor ID is present,
    avoiding "Invalid value ... {sponlineitemid: [null]}" warnings.
  Note: this did not surface on the original test site because that site's
  pages had no misconfigured ad widgets to trigger the initial defineSlot
  failure. The fix makes slot registration self-contained per slot so one bad
  widget can never block the rest.

= 3.2.0 =
* Leaderboard padding lock now defaults to linked, with a flat black SVG lock icon.

= 1.0 =
* A change since the previous version.
* Another change.

= 0.5 =
* List versions from most recent at top to oldest at bottom.

== Upgrade Notice ==

= 1.0 =
Upgrade notices describe the reason a user should upgrade.  No more than 300 characters.

= 0.5 =
This version fixes a security related bug.  Upgrade immediately.

== Arbitrary section ==

You may provide arbitrary sections, in the same format as the ones above.  This may be of use for extremely complicated
plugins where more information needs to be conveyed that doesn't fit into the categories of "description" or
"installation."  Arbitrary sections will be shown below the built-in sections outlined above.

== A brief Markdown Example ==

Ordered list:

1. Some feature
1. Another feature
1. Something else about the plugin

Unordered list:

* something
* something else
* third thing

Here's a link to [WordPress](http://wordpress.org/ "Your favorite software") and one to [Markdown's Syntax Documentation][markdown syntax].
Titles are optional, naturally.

[markdown syntax]: http://daringfireball.net/projects/markdown/syntax
            "Markdown is what the parser uses to process much of the readme file"

Markdown uses email style notation for blockquotes and I've been told:
> Asterisks for *emphasis*. Double it up  for **strong**.

`<?php code(); // goes in backticks ?>`