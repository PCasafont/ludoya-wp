# Ludoya for WordPress

A WordPress plugin that talks to the [Ludoya](https://ludoya.com) public API (`public/v1`). It does
two jobs:

- **Publish** — put your club's events, game collection, play stats and venues on your own site,
  as shortcodes or blocks, styled by your theme.
- **Administer** — create, edit, publish, cancel and delete events from wp-admin, and sign people
  up, without leaving WordPress.

Requires a Ludoya organisation account on the **Business** plan (that is what can mint an API key)
and WordPress 6.2+ / PHP 7.4+.

## Install

1. Copy this folder into `wp-content/plugins/ludoya` and activate it.
2. In Ludoya, open your organisation profile → Developer → create an API key (`ldy_…`).
3. In WordPress, go to **Ludoya → Settings**, paste the key, save, and press *Test connection*.

For a stricter setup, keep the key out of the database:

```php
// wp-config.php
define( 'LUDOYA_API_KEY', 'ldy_…' );
```

The settings field then disappears and the constant wins.

## Putting it on a page

Edit a page, press **+**, search for "Ludoya", pick one of six blocks. Each has its settings in the
sidebar — how many events, which layout, which period — so there is nothing to memorise.

| To show | Block | Shortcode |
| --- | --- | --- |
| A list of your events | Ludoya events | `[ludoya_events]` |
| One event, with its sign-up form | Ludoya event | `[ludoya_event]` \* |
| A sign-up form on its own | Ludoya sign-up form | `[ludoya_signup]` \* |
| The games you own | Ludoya collection | `[ludoya_collection]` |
| How much you have played, and what | Ludoya stats | `[ludoya_stats]` |
| Where you play | Ludoya locations | `[ludoya_locations]` |

**Nothing here takes arguments.** Every attribute has a default, so a bare shortcode renders — the
attributes below are for when you want something other than the default.

\* The two marked ones need an *event*. Either name one with `id`, or leave it out and let the
shortcode read the event from the link that opened the page — see the two patterns below.
`[ludoya_signup]` additionally stays hidden until you switch sign-ups on.

Blocks render through the shortcodes, so there is one implementation of each view rather than a PHP
one and a JavaScript one that drift apart — and no build step in this repo.

### Showing an event on your own site

By default a card sends the visitor to app.ludoya.com. Two ways to keep them here; most clubs use both.

**One page that serves every event** — for a programme that changes every month.

1. Make a page called **Event** holding the *Ludoya event* block, and leave its settings empty.
2. On the page listing your events, select the *Ludoya events* block and set **Event page** to it.

Cards then link to your own page, which works out which event to show from the link it was opened
with (`?ludoya_event=…`).

**A page dedicated to one event** — for a tournament or an open day you want in your menu, with its
own address and your own words around it.

1. In **Ludoya → Events**, find the event and press **Copy shortcode** under its name. (It is also
   on that event's edit screen, under *Give this event its own page*.)
2. Paste the result — `[ludoya_event id="…"]` — into any page.

That page shows only that event, whatever anybody clicks elsewhere. There is no need to fetch the id
by hand; nothing in wp-admin asks you to type one.

### Every attribute

The blocks cover the common ones. The shortcodes take a few more:

| Shortcode | Attributes |
| --- | --- |
| `[ludoya_events]` | `limit` (6), `past` (0), `type` (any of `MEETUP`, `PLANNED_PLAY`, `TOURNAMENT`, `PLAY_BOOTH`), `include_sub` (0), `layout` (`cards` or `list`), `event_page`, `heading`, `empty` |
| `[ludoya_event]` | `id` (read from the link when empty), `show_signup` (1), `back_url` |
| `[ludoya_signup]` | `event` (read from the link when empty) |
| `[ludoya_collection]` | `limit` (24), `filter` (`ownership=OWNED`), `sort` (`NAME,ASC`), `search`, `layout` (`grid` or `list`), `heading` |
| `[ludoya_stats]` | `period` (`ONE_YEAR`; also `ALL_TIME`, `ONE_MONTH`, `THIRTY_DAYS`, `SEVEN_DAYS`), `top_games` (5), `heading` |
| `[ludoya_locations]` | `heading` |

`sort` properties are the API's own names, in upper case: `NAME`, `YEAR_PUBLISHED`, `PLAYER_COUNT`,
`PLAY_TIME`, `COMPLEXITY`, `BGG_RATING`, `LUDOYA_RATING`, `RATING`, `PLAY_COUNT`, `LAST_PLAYED`,
`OWNERSHIP_SINCE`.

## Front-end sign-ups

Off by default. Turning it on (**Settings → Sign-ups**) lets a visitor sign up with a name and an
email address; Ludoya mints or reuses the account behind that address so the person can claim it
later. Because that creates an account for somebody, the form:

- is gated on a consent checkbox whose wording you control,
- carries a honeypot field and a per-IP throttle,
- renders the event's real questions (`USER_FIELD`, text, choice, scale and grid) and lets the API
  validate the answers, so a required question is enforced where it is defined.

Say so in your privacy notice before you switch it on.

## Languages

Ships with Spanish (`es_ES`) and Catalan (`ca`), matching the terminology the Ludoya app itself uses
— Evento/Esdeveniment, Colección/Col·lecció, Torneo/Torneig — so the two surfaces do not invent two
vocabularies for the same thing. Everything follows the site's **Settings → General → Site language**.

Dates and times follow the site's own date and time format, so a Catalan site still showing
`12:00 pm` wants **Settings → General → Time format** set to `H:i`.

To add a locale, or to re-extract after changing a string:

```bash
docker compose run --rm --entrypoint wp cli i18n make-pot     /var/www/html/wp-content/plugins/ludoya     /var/www/html/wp-content/plugins/ludoya/languages/ludoya.pot --domain=ludoya --exclude=ludoya,tests
# translate languages/ludoya-<locale>.po, then
docker compose run --rm --entrypoint wp cli i18n make-mo  /var/www/html/wp-content/plugins/ludoya/languages
docker compose run --rm --entrypoint wp cli i18n make-php /var/www/html/wp-content/plugins/ludoya/languages
```

`.po` is the source, `.mo` is what WordPress reads, and `.l10n.php` is the faster form WordPress 6.5
and later prefers. All three are committed, so a site does not need a build step.

## Templates

Every front-end view is a template you can override from your theme. Copy any file out of
`templates/` into `your-theme/ludoya/` and edit it there:

```
templates/events-list.php   →  your-theme/ludoya/events-list.php
templates/event-card.php    →  your-theme/ludoya/event-card.php
templates/event-single.php  →  your-theme/ludoya/event-single.php
templates/signup-form.php   →  your-theme/ludoya/signup-form.php
templates/collection.php    →  your-theme/ludoya/collection.php
templates/stats.php         →  your-theme/ludoya/stats.php
templates/locations.php     →  your-theme/ludoya/locations.php
```

## How it treats your data

**Editing is PATCH + If-Match.** Your staff also edit these events in the Ludoya app. A save from
here sends only the fields this screen owns, and sends the ETag it loaded with, so a concurrent edit
comes back as *"this event changed in Ludoya, reload"* instead of silently overwriting somebody.

**Blank does not always mean empty.** A field shown with its current value is one you can clear by
emptying it. Where the edit screen cannot show you what an event currently has, an empty input means
*leave this alone* rather than *make it empty*, so a save never wipes something you could not see.

**Caching.** Every GET is cached for the configured time (5 minutes by default). Without it, a busy
page would spend an API call per visitor against a per-minute rate limit. Any write from wp-admin
drops the whole cache immediately.

**Errors are never shown to visitors.** A misconfigured key or a rate limit renders as nothing at
all on the front end; administrators see the message.

## What it deliberately does not do

- **List participants by name.** `public/v1` exposes a participant count, and endpoints to add and
  remove people, but no roster. Open the event in Ludoya for that.
- **Author form templates.** The question editor lives in the Ludoya app. This plugin lists the
  templates, attaches one to an event, and deletes ones you no longer use.
- **Sync anything on a schedule.** It reads through a short cache and writes when you press save.

## Licence

GPL-2.0-or-later.

## Local development

A throwaway WordPress lives in `docker-compose.yml`. The repo root is mounted as
`wp-content/plugins/ludoya`, so an edit is live on the next request — no rebuild, no copy.

```bash
docker compose up -d        # http://localhost:8888 — logs in with admin / admin, throwaway and local only
docker compose down         # stop
docker compose down -v      # stop and wipe the site (fresh install next time)
```

The `cli` service installs WordPress and activates the plugin on first boot, then exits. Use it for
one-off WP-CLI commands too:

```bash
docker compose run --rm --entrypoint wp cli plugin list
docker compose run --rm --entrypoint wp cli option get ludoya_settings
```

`WP_DEBUG` and the debug log are on; read them with
`docker compose exec wordpress tail -f wp-content/debug.log`.

The smoke suite covers the mechanisms whose correctness is not obvious from reading them — which
keys a save may send, the local-time round trip, the language-code parser, answer ordering, cache
isolation. Run it after touching any of those:

```bash
docker compose exec wordpress php /var/www/html/wp-content/plugins/ludoya/tests/smoke.php
```

The API and app URLs are not settings — there is only one Ludoya, and a wrong value there looks
exactly like a broken key. A development site can still override them from `wp-config.php` with
`LUDOYA_API_BASE` and `LUDOYA_SITE_BASE`, which is a deliberate act rather than a stray keystroke.
