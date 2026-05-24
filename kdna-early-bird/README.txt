=== KDNA Early Bird Pricing for MemberPress ===
Contributors: krulldna
Tags: memberpress, pricing, early bird, offers, elementor
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Run limited early bird offers on MemberPress memberships without coupons. The plugin quietly hands MemberPress the early bird price while an offer is live, then steps aside.

== Description ==

KDNA Early Bird Pricing is a companion plugin for MemberPress. The administrator creates pricing rules in the WordPress admin. While a rule's early bird offer is running, the plugin hands MemberPress the early bird price everywhere MemberPress reads its price: the pricing table, the registration form, and the actual charge. When the offer ends, the plugin stops interfering and MemberPress shows its own full price again.

Because no coupon is ever involved, the buyer never sees a "coupon code is not valid" message. The switch is seamless.

= How it works =

* Full price lives in the membership itself. The plugin only stores the early bird price and overrides downward while the offer is live, then steps aside.
* An offer ends when either its purchase cap is reached or the time limit passes, whichever happens first.
* Purchase counting is per membership. If a rule covers more than one membership, each membership has its own cap and its own count.
* One off payments only. Existing buyers are never re-billed.
* Fail safe direction. The only thing the plugin ever does is lower the price during the offer. If anything is uncertain it does nothing, and the membership falls back to its own full price.

= Test override =

Each membership row has an optional test override count. Empty means count real purchases. A number means the plugin uses that number instead, so the client can confirm 50 still shows the early bird price and 51 shows the full price. A bright admin warning banner appears across wp-admin whenever any override is filled in, so it cannot be left on by accident. Clearing the field returns to counting real sales.

= Elementor widget =

Includes an Elementor widget called "Early Bird Pricing" that shows the live offer state for a chosen rule and membership: the current price, an optional struck through full price, spots remaining, and either days remaining or a live countdown. Separate label text for the active and ended states, plus an optional toggle to hide the widget when the offer ends. Full styling controls including typography, spacing, badge styling, countdown styling, button styling with hover states, and responsive controls. The price engine works with or without Elementor.

= Performance =

* The membership index is autoloaded so the price filter does no rule queries on a normal page load.
* Per membership completed purchase counts are cached in a five minute transient as a safety net and refreshed by MemberPress transaction status hooks, so no counting query runs on a normal page load.
* The Elementor widget CSS and JS only load on pages where the widget is actually present.

== Installation ==

1. Upload the `kdna-early-bird` folder to the `/wp-content/plugins/` directory, or upload the zip file via Plugins, Add New, Upload Plugin.
2. Activate the plugin through the Plugins menu in WordPress.
3. Make sure MemberPress is active. If it is not, the plugin will show an admin notice and stay out of the way until MemberPress is available.
4. In the WordPress admin, go to MemberPress, Early Bird Pricing to create your first rule.

== Frequently Asked Questions ==

= Does this use MemberPress coupons? =

No. The plugin overrides the membership price directly via the MemberPress price meta key, so no coupon is ever involved and buyers never see a coupon related error message.

= What happens if MemberPress is not installed? =

The plugin loads, shows an admin notice, and changes nothing on the front end. As soon as MemberPress is activated, the plugin starts working normally.

= Are existing buyers re-billed at the new price? =

No. Only new purchases see the early bird price. Existing transactions are not modified.

= Can I run two rules on the same membership? =

A membership should sit in only one active rule. If you have it in two active rules, the first active match wins and the others are ignored for that membership. The status panel on the rule edit screen tells you when this is happening.

= How do I confirm the price switch works without making real purchases? =

Set the test override count for a row to one below the cap and confirm the early bird price shows everywhere. Then change the test override to the cap value or higher and confirm the full price shows. Clear the field when you are done. The bright admin warning banner reminds you whenever an override is set.

= Where does the widget CSS load? =

Only on pages where the Early Bird Pricing Elementor widget is present. Elementor handles the conditional enqueueing via the widget's get_style_depends and get_script_depends.

== Screenshots ==

1. Rule list under MemberPress, Early Bird Pricing.
2. Rule edit screen with repeatable membership rows.
3. Live status panel showing per membership offer state and the price currently being served.
4. The Elementor widget shown on a pricing page.

== Changelog ==

= 1.0.0 =
* Initial release. Rules custom post type under the MemberPress menu, seamless price override via the MemberPress price meta key, per membership completed purchase counting with caching, test override count with a bright admin warning banner, live status panel on the rule edit screen, and an Elementor widget with full styling controls.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
