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

Every attribute is optional: a bare shortcode renders with sensible defaults, and each block's
sidebar exposes the common ones. The full list, per shortcode, is under [Every attribute](#every-attribute).

\* The two marked ones need an *event*. Either name one with `id`, or leave it out and let the
shortcode read the event from the link that opened the page — see the two patterns below.
`[ludoya_signup]` additionally stays hidden until you switch sign-ups on.

Blocks render through the shortcodes, so there is one implementation of each view rather than a PHP
one and a JavaScript one that drift apart — and no build step in this repo.

The single-event view also emits schema.org Event markup (JSON-LD) — name, dates in the event's
own time zone, venue, image, and cancellation status — which is what makes an event eligible for
rich results in search.

### Showing an event on your own site

By default a card sends the visitor to app.ludoya.com. There are two ways to keep them here, and
which one you pick decides whether *everything* underneath the event stays here too.

**One page that serves every event** — the one to use for a programme, a convention, anything with
sub-events. Set this up once and you are done.

1. Make a page called **Event** holding the *Ludoya event* block, and **leave its settings empty** —
   do not pick an event.
2. On every page listing your events, select the *Ludoya events* block and set **Event page** to
   that page.
3. To open one event from your own menu, a button or a poster, link to that page with the event's
   id: `https://yoursite.example/event/?ludoya_event=<event id>`.

That is the whole setup. The page works out what to show from the link it was opened with
(`?ludoya_event=…`), so when it renders an event's programme, the sub-event cards point back at
*itself* with the child's id — and so do that child's own cards, to any depth. A convention → a zone
→ a tournament all stay on your site, and only the sign-up at the end decides between
app.ludoya.com and the embedded form (`show_signup`, under **Ludoya → Settings**).

**A page dedicated to one event** — for a single open day you want in your menu, with its own
address and your own words around it.

1. In **Ludoya → Events**, find the event and press **Copy shortcode** under its name. (It is also
   on that event's edit screen, under *Give this event its own page*.)
2. Paste the result — `[ludoya_event id="…"]` — into any page.

That page shows that one event whatever anybody clicks elsewhere, which is also its limitation: a
page pinned to an `id` has nowhere local to send a click, so anything inside it — its programme
included — leaves for app.ludoya.com. Use it for an event that is a leaf, not for one with a
programme under it.

Two things that look like a broken plugin and are not:

- **Using `[ludoya_event id="…"]` for the main event of a convention.** It is the obvious move,
  because the id is right there on the copy button — and it ends the chain at the first click.
  The shared page above is the only one that can keep it going.
- **Forgetting `event_page` on the list.** With the shared page built correctly but the list still
  unset, cards go to app.ludoya.com exactly as if you had done nothing at all — no error, no
  warning, nothing to tell the two cases apart.

### A convention, a festival, a games weekend

A big event on Ludoya is one parent event with sub-events under it — the tournaments, the demo
tables, the Saturday-night session — each with its own dates, seats and sign-ups. The plugin
treats it as one thing:

- **Ludoya → Events** nests every sub-event under its parent (and a zone's tables under the zone),
  in the order they happen, with a count on the parent row.
- The parent's edit screen lists its programme and has an **Add sub-event** button; the row actions
  in the list have one too. A new sub-event starts inside its parent's dates and at its venue — the
  date inputs will not let it leave the parent's range, because the API refuses that anyway.
- Publishing or deleting the parent does the same to everything under it; cancelling is per event,
  so a called-off tournament does not take the weekend down with it. A sub-event under a draft
  parent starts as a draft, and goes live with the parent.
- On the site, the *Ludoya event* view shows a parent's programme as cards under its description,
  and a sub-event carries a link back to the event it is part of.
- A venue with several rooms can give each room its own page: the *Ludoya events* block has an
  **Only this table or room** setting (the shortcode takes `spot="…"`), which lists whatever is
  scheduled there, sub-events included.

### What each kind of event asks for

The event form shows the settings that belong to the kind you picked, and nothing else. Two kinds
carry a setup of their own, because Ludoya cannot run them without it:

- **Play booth** — the drop-in table where visitors play whatever is free. It needs a **session
  length**, how many **players per session**, and a start and an end time (its grid is built between
  them); optionally how many games run side by side, and whether staff seat people or Ludoya does.
  It has no capacity of its own — the seats are per session.
- **Tournament** — **format**, how many **rounds**, players per table, what each placement scores,
  and the tiebreakers. The form sets up one phase, which is a whole tournament for most clubs; a cut
  into a final table is a second phase, added in the Ludoya app. Editing a tournament that already
  has a setup defaults to *Leave as it is*, so a save that was only moving the dates cannot flatten
  a final somebody built there.

A tournament that reaches this screen with no setup at all — pushed by another system, or made
before this was possible — says so at the top, because an unconfigured tournament cannot be run and
nothing else would tell you.

The other kinds drop what they have no use for: a scheduled game is the only one with a
Demonstrator and a Game Master, and a tournament's field comes from its format rather than a
minimum.

### Every attribute

Every attribute is optional. Blocks expose the common ones in their sidebar; the shortcodes take
them all.

#### `[ludoya_events]` — a list of your events

| Attribute | Default | What it does |
| --- | --- | --- |
| `limit` | `6` | How many upcoming events to show. |
| `past` | `0` | How many past events to show after them, latest first. `0` shows none. |
| `type` | *(all)* | Only these kinds, comma-separated: `MEETUP`, `PLANNED_PLAY`, `TOURNAMENT`, `PLAY_BOOTH`. |
| `include_sub` | `0` | `1` lists sub-events too. By default only top-level events show; a convention's programme stays inside the convention. |
| `spot` | *(anywhere)* | Only the events held on this table or room, sub-events included whatever `include_sub` says. Takes the spot's id — the block offers a select of every table of every venue, and the ids are also what `[ludoya_locations]` and the API's locations endpoint return. |
| `layout` | `cards` | `cards` or `list`. |
| `event_page` | *(none)* | Page carrying `[ludoya_event]` to link each card to, as a page id, a slug or a URL. Without it cards link to the event on app.ludoya.com. |
| `heading` | *(none)* | A heading above the list. |
| `empty` | *No events scheduled right now.* | Text shown when there is nothing to list. |

Upcoming events run soonest first, past ones latest first. A club's monthly programme:

```
[ludoya_events limit="12" past="3" event_page="event" heading="What's on"]
```

One room of a venue, on its own page:

```
[ludoya_events spot="<spot id>" heading="In the workshop"]
```

#### `[ludoya_event]` — one event, with its sign-up form

| Attribute | Default | What it does |
| --- | --- | --- |
| `id` | *(from the link)* | The event to show. Leave it out on a shared "Event" page: the shortcode reads `?ludoya_event=…` from the link that opened the page. |
| `show_signup` | `1` | `0` hides the sign-up form even when sign-ups are on. |
| `back_url` | *(none)* | Adds an "All events" link back to this URL. |

A parent event lists its programme (its sub-events) as cards; a sub-event links back to the event
it is part of.

#### `[ludoya_signup]` — the sign-up form on its own

| Attribute | Default | What it does |
| --- | --- | --- |
| `event` | *(from the link)* | The event to sign up for; read from `?ludoya_event=…` when omitted. |

Renders nothing until sign-ups are switched on in **Ludoya → Settings**.

#### `[ludoya_collection]` — the games you own

| Attribute | Default | What it does |
| --- | --- | --- |
| `limit` | `24` | How many games. |
| `filter` | `ownership=OWNED` | A filter in the API's own grammar, e.g. `ownership=OWNED;minPlayers=2`. |
| `sort` | `NAME,ASC` | Property and direction. Properties: `NAME`, `YEAR_PUBLISHED`, `PLAYER_COUNT`, `PLAY_TIME`, `COMPLEXITY`, `BGG_RATING`, `LUDOYA_RATING`, `RATING`, `PLAY_COUNT`, `LAST_PLAYED`, `OWNERSHIP_SINCE`. |
| `search` | *(none)* | Only games whose name matches. |
| `layout` | `grid` | `grid` or `list`. |
| `heading` | *(none)* | A heading above the games. |

#### `[ludoya_stats]` — how much you have played, and what

| Attribute | Default | What it does |
| --- | --- | --- |
| `period` | `ONE_YEAR` | `SEVEN_DAYS`, `THIRTY_DAYS`, `ONE_MONTH`, `ONE_YEAR` or `ALL_TIME`. |
| `top_games` | `5` | How many most-played games to list. |
| `heading` | *(none)* | A heading above the numbers. |

#### `[ludoya_locations]` — where you play

| Attribute | Default | What it does |
| --- | --- | --- |
| `heading` | *(none)* | A heading above the venues. |

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
