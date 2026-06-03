=== Plugin Name ===
Contributors: 
Requires at least: 3.0.1
Tested up to: 3.4
Stable tag: 4.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Here is a short description of the plugin.  This should be no more than 150 characters.  No markup here.

== Description ==

This is the long description.  No limit, and you can use Markdown (as well as in the following sections).

For backwards compatibility, if this section is missing, the full length of the short description will be used, and
Markdown parsed.

A few notes about the sections above:

*   "Contributors" is a comma separated list of wp.org/wp-plugins.org usernames
*   "Tags" is a comma separated list of tags that apply to the plugin
*   "Requires at least" is the lowest version that the plugin will work on
*   "Tested up to" is the highest version that you've *successfully used to test the plugin*. Note that it might work on
higher versions... this is just the highest one you've verified.
*   Stable tag should indicate the Subversion "tag" of the latest stable version, or "trunk," if you use `/trunk/` for
stable.

    Note that the `readme.txt` of the stable tag is the one that is considered the defining one for the plugin, so
if the `/trunk/readme.txt` file says that the stable tag is `4.3`, then it is `/tags/4.3/readme.txt` that'll be used
for displaying information about the plugin.  In this situation, the only thing considered from the trunk `readme.txt`
is the stable tag pointer.  Thus, if you develop in trunk, you can update the trunk `readme.txt` to reflect changes in
your in-development version, without having that information incorrectly disclosed about the current stable version
that lacks those changes -- as long as the trunk's `readme.txt` points to the correct stable tag.

    If no stable tag is provided, it is assumed that trunk is stable, but you should specify "trunk" if that's where
you put the stable version, in order to eliminate any doubt.

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