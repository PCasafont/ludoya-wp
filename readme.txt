=== Ludoya ===
Contributors: ludoya
Tags: board games, events, club, meetups, calendar
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show your Ludoya events, game collection and play stats on your site, and run your event admin from wp-admin.

== Description ==

Ludoya is a board game community platform. This plugin connects your WordPress site to your Ludoya
organisation through the public API, so your club's site and your Ludoya account stop being two
places you have to keep in step.

Publish:

* Upcoming and past events, as cards or a list
* A single event page with its real sign-up form
* The games your club owns
* Play statistics: plays, hours, players, most-played games
* Your venues

Administer, without leaving WordPress:

* Create, edit, publish, cancel and delete events
* Attach a sign-up form template to an event
* Sign somebody up, by Ludoya account or by name and email

Every view is a shortcode and a block, and every template can be overridden from your theme.

A Ludoya organisation account on the Business plan is required: that is what can create an API key.

== Third party services ==

This plugin sends requests to the Ludoya API at https://api.ludoya.com, authenticated
with an API key you create in Ludoya. Reads send no visitor data. If you switch front-end sign-ups
on, the name and email a visitor types into the sign-up form are sent to Ludoya to register their
place, which creates a Ludoya account for that address.

Ludoya terms: https://ludoya.com/terms — privacy policy: https://ludoya.com/privacy

== Installation ==

1. Install and activate the plugin.
2. In Ludoya, open your organisation profile, then Developer, and create an API key.
3. In WordPress, go to Ludoya, Settings, paste the key and press Test connection.

== Frequently Asked Questions ==

= Can visitors sign up from my site? =

Yes, but it is off by default. A sign-up creates a Ludoya account for the email address given, so
turn it on only once your privacy notice says so. The form has a consent checkbox, a honeypot and a
per-visitor throttle.

= Can I see who signed up? =

Not here. The public API reports how many people signed up and lets you add or remove them, but it
does not list them by name. Open the event in Ludoya for the roster.

= Will editing here overwrite what my staff change in the Ludoya app? =

No. Saves go out as a partial update carrying the version the screen loaded, so a concurrent edit is
reported back to you instead of overwriting anybody. Where the edit screen cannot show you what an
event currently has, leaving the field empty means "leave this alone", never "make it empty".

= Does every visitor cost an API call? =

No. Responses are cached for five minutes by default, and any edit you make in wp-admin clears the
cache at once.

== Changelog ==

= 0.3.0 =
* Sub-events. A bigger event — a convention, a festival, a games weekend — is managed as one thing:
  the events list nests every sub-event under its parent, the parent's edit screen lists its
  programme with an *Add sub-event* button, a new sub-event starts inside its parent's dates and
  venue, and publishing or deleting the parent does the same to all of them.
* The single-event view shows a parent's programme as cards, and a sub-event links back to the
  event it is part of.
* The events block and shortcode can be limited to one table or room, for a venue that wants each
  room's programme on its own page.

= 0.2.0 =
* First public release.
* Six views — events, one event, sign-up form, collection, play stats and venues — each available
  as a block and as a shortcode, and each overridable from your theme.
* Event administration from wp-admin: create, edit, publish, cancel and delete events, attach a
  sign-up form template, and add or remove participants.
* Event cards follow the Ludoya app: cover art, "Today"/"Tomorrow" dating coloured by how close the
  event is, venue and game, and tags for seats left, full and cancelled.
* Spanish and Catalan translations, using the same wording as the app.
