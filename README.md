# पांडुरंग निवास — Pandurang Nivas

The family platform, rebuilt as a Drupal 10 site on MySQL/MariaDB. It replaces
the static HTML version while keeping the original design, and moves the
content — the family register, events, gallery — into the database so the
family can edit it themselves.

---

## Running it

Apache (XAMPP) serves the site on port **8088**; MariaDB holds the data.

```bash
C:/xampp/mysql/bin/mysqld.exe --defaults-file=C:/xampp/mysql/bin/my.ini --standalone
```

```bash
C:/xampp/apache/bin/httpd.exe
```

Then open **http://127.0.0.1:8088/**

| | |
|---|---|
| Marathi (default) | http://127.0.0.1:8088/mr/ |
| English | http://127.0.0.1:8088/en/ |
| Admin | http://127.0.0.1:8088/admin |

### Accounts

| User | Password | Role |
|---|---|---|
| `admin` | `Pandurang@2025` | Administrator + Family Admin |
| `sadasya` | `Sadasya@2025` | Family Member (test account) |

### Database

| | |
|---|---|
| Name | `pandurangnivas` |
| User | `pandurang` |
| Password | `PandurangNivas2025` |
| Host | `127.0.0.1:3306` |
| Charset | `utf8mb4` |

Credentials live in `web/sites/default/settings.php`. Change them before this
goes anywhere public.

---

## How it is put together

### Content types

| Type | Holds | Notable fields |
|---|---|---|
| `family_member` | One person in the register (232 of them) | `field_fm_parent` (self-reference), `field_fm_spouse`, `field_fm_generation`, `field_fm_sex`, `field_fm_legacy_id` |
| `event` | Festivals, gatherings, trips | `field_event_start` / `_end`, `field_event_location`, `field_event_time`, `field_event_rsvp` |
| `gallery_item` | A photo or a video | `field_gi_type`, `field_gi_image`, `field_gi_album`, `field_gi_caption` |
| `album` | A named collection | `field_album_cover` |
| `notification` | The bell-menu announcements | `body` |
| `page` | About Us, Privacy Policy | `body` |

The family tree is a real hierarchy: each member points at their parent through
`field_fm_parent`, so editing a person in the admin UI moves them in the tree.

### Custom module — `web/modules/custom/pandurang`

| Piece | What it does |
|---|---|
| `RsvpManager` | Reads and writes `{pn_event_rsvp}`; one row per member per event |
| `FamilyTreeBuilder` | Turns the member nodes into the nested tree, cached per language |
| `RsvpController` | The POST endpoint and the per-event responses report |
| `FamilyTreeController` | `/family-tree` and its JSON feed |
| `SearchController` | Backs the header search, scoped by permission |
| `HomeController` | Assembles the front page |
| `hook_node_access` | **Forbids** the family-only bundles to anyone without the permission |

Permissions: `view pn private content`, `rsvp to events`, `view pn rsvp report`.

Roles: **Family Member** (sees the private sections, can RSVP) and
**Family Admin** (also curates content and reads the RSVP reports).

### The RSVP table

```sql
CREATE TABLE pn_event_rsvp (
  id      INT AUTO_INCREMENT PRIMARY KEY,
  nid     INT UNSIGNED NOT NULL,
  uid     INT UNSIGNED NOT NULL,
  status  VARCHAR(16) NOT NULL,   -- going | maybe | not_going
  guests  INT UNSIGNED DEFAULT 0,
  note    VARCHAR(255),
  created INT, changed INT,
  UNIQUE KEY pn_rsvp_node_user (nid, uid)
);
```

Responses are cleaned up when the event or the user is deleted.

### Custom theme — `web/themes/custom/pandurang_nivas`

The original `main.css` is used as-is. `css/drupal-overrides.css` maps Drupal's
markup onto the classes that stylesheet already knows, and fixes the two places
the original CSS misbehaved outside a flat HTML site (see the comments in that
file). Twig templates emit the same class names the original markup used, so
the design carries across unchanged.

---

## Languages

Marathi is the site default and English is the translation. Both carry a URL
prefix: `/mr/…` and `/en/…`.

- Interface strings live in `data/pandurang.mr.po` (89 strings).
- Content is translated per node through Drupal's own translation UI.
- Config that shows Marathi text (view titles, menu links, field labels) is
  translated through the language override collection.

**Family member names carry a transliterated English translation.** The source
register holds only Devanagari, so `16-transliterate-member-names.php`
generated a Latin-script name and spouse name for every member. It uses
ICU's `Any-Latin` and then applies Marathi conventions on top: IAST diacritics
become the usual digraphs (ś → sh, c → ch, jñ → dny), and the inherent final
vowel is dropped, so महादेव is *Mahadev* rather than *Mahadeva*.

Those spellings are a **starting point, not an authority**. Rule-based
transliteration cannot settle internal schwa deletion, so confirmed spellings
live in a `PN_KNOWN_SPELLINGS` table at the top of the script — matched one
word at a time, so an entry there applies wherever that word appears. The
family surname is in it (चव्हाण is *Chavan*, not the rule's *Chavhan*), along
with *Dnyandev* and *Dinkar*.

Corrections can go either place: add repeated names and surnames to that
table, and edit one-offs in the admin UI. Re-running the script leaves
hand-edited names alone; pass `-- --force` when the rules themselves change and
the stored names are stale machine output rather than someone's edit.

---

## Setup scripts

Everything was built by these, in order. They are all idempotent, so re-running
one updates rather than duplicates.

```bash
php vendor/bin/drush.php php:script scripts/01-language.php
```

| Script | Does |
|---|---|
| `01-language.php` | Marathi as default, `/mr` and `/en` prefixes |
| `02-content-types.php` | The content types and their fields |
| `02b-body-fields.php` | Body fields on event / album / notification |
| `03-translation-displays-roles.php` | Content translation, form and view displays, roles |
| `04-import-family-tree.php` | Superseded by 18 — the original 253-person JSON import |
| `05-import-content.php` | Events, gallery, albums, notifications, pages |
| `06-views.php` | The gallery and events views |
| `07-menus-blocks-frontpage.php` | Menus, block placement, front page, aliases |
| `08-import-translations.php` | The Marathi interface strings |
| `10-events-displays.php` | Upcoming / recent event blocks |
| `11-date-formats.php` | Date-only formatting for event dates |
| `12-config-translations.php` | English config overrides |
| `13-field-label-translations.php` | Marathi field labels |
| `14-footer-menu.php` | The six footer quick links |
| `15-fix-image-translatability.php` | Makes the image fields shared across languages |
| `16-transliterate-member-names.php` | English names for every member (`-- --dry` to preview) |
| `17-events-calendar-block.php` | Places the event calendar on the events page |
| `18-replace-family-tree.php` | Replaces the register from the 2022 spreadsheet (`-- --dry` to preview) |

### A note on translatable fields

Image fields are deliberately **non-translatable**. The same photograph serves
both languages, and a translatable image field leaves every non-default
language with an empty value — which is exactly what hid the gallery, event and
album images on `/en` pages until `15-fix-image-translatability.php` was run.
If you add an image field later, mark it `'translatable' => FALSE` in
`02-content-types.php`.

Text that genuinely differs per language — titles, body, captions, locations,
timings — stays translatable.

---

## Things the family should know

**The register comes from `FAMILY TREE [MM] 21.10.2022.xlsx`** — 232 people
across seven generations, rooted at धोंडोजीं लक्ष्मण चव्हाण. It replaced an
earlier 253-person import that started a generation later, at पांडुरंग; that
data is kept in `data/backups/` should anything need checking against it.

The spreadsheet is a drawn tree rather than a table: a cell's **column is its
generation** and its parent is the nearest name up and to the left. Each cell
reads `GIVEN FATHER SURNAME + SPOUSE`, and a married daughter carries her
married surname in brackets — `रजनी (मोरे) व्यंकटेश चव्हाण`. Names are stored
exactly as written, brackets included.

Three things are worth knowing about it:

1. **Sex is only recorded implicitly.** A person listed with a wife is taken as
   male, a bracketed married surname marks a daughter. That leaves **77 people
   with no sex recorded** — mostly unmarried children — and the tree simply
   does not colour-code them rather than guessing.
2. **दिनकर पांडुरंग चव्हाण has two wives**, `तारामती + नवी`, kept in the one
   spouse field as the sheet has it.
3. **One entry is a description, not a name**: `आत्या` (paternal aunt) in
   generation 3.

To re-import after editing the spreadsheet, re-parse it with
`scripts/lib-parse-family-tree-xlsx.php` and re-run `18-replace-family-tree.php`.

**The event dates are the ones from the source content** — August 2025 through
March 2024. They are all in the past now, so the front page falls back to
showing the most recent events. Editing `field_event_start` on the two annual
events (गणेशोत्सव, कौटुंबिक सहल) rolls them forward, and the "upcoming" block
picks them up on its own.

**The gallery placeholders are gone.** The original JavaScript pointed at
Unsplash stock photos; the gallery now uses the family's own pictures from
`images/webpics/`.

---

## Changes made outside this folder

| File | Change | Backup |
|---|---|---|
| `C:/xampp/php/php.ini` | Enabled `intl` and `sodium` | `php.ini.bak-pandurang` |
| `C:/xampp/apache/conf/extra/httpd-vhosts.conf` | Added the port-8088 vhost | `httpd-vhosts.conf.bak-pandurang` |
| `C:/xampp/apache/conf/httpd.conf` | Untouched | `httpd.conf.bak-pandurang` |

7-Zip was installed via winget to read the source archive.

MariaDB's `mysql` system tables were corrupt (`Aria` engine, bad page
checksums) and blocked user creation; they were repaired with `REPAIR TABLE`.
The repair discarded 3 unreadable rows from `mysql.db`, which held
database-level grants. `pma`'s grant on the `phpmyadmin` database was
re-applied. If anything else used a per-database grant — the pre-existing
`pandunbu_webportal` database, for instance — its grant will need re-granting.

---

## Before this goes live

- Change every password above, and the database credentials in `settings.php`.
- Set `$settings['trusted_host_patterns']` in `settings.php`.
- Error display is already off. While developing, turn it back on with
  `drush config:set system.logging error_level verbose` — and off again before
  the site is public.
- Put the real contact details in the footer (`templates/layout/page.html.twig`)
  and the real social links.
- Serve over HTTPS and set `$settings['file_private_path']` if the gallery
  should not be reachable by direct URL — right now the images sit under
  `sites/default/files/gallery/`, which is publicly readable even though the
  gallery *pages* are not.

---

## Responsive layout

Verified with no sideways scrolling and no element overflow across
360 / 414 / 768 / 1024 / 1280 / 1600 px, on the front page, family tree,
gallery, events, member pages, standing pages and the login form.

| Width | Layout |
|---|---|
| ≥ 769px | Full horizontal navigation |
| ≤ 768px | Off-canvas drawer behind the hamburger; footer and cards stack |
| ≤ 520px | Notification bell drops out of the header |
| ≤ 430px | Header tightens: smaller logo, compact language pill |
| ≤ 360px | Site name ellipsises rather than pushing the menu button off-screen |

Two things in the ported CSS had to be corrected to make any of this work, and
are worth knowing about if that stylesheet is edited again:

**`nav` was styled as a singleton.** `main.css` turns *any* `<nav>` into a
fixed off-canvas drawer below 768px. The static site had exactly one; Drupal
wraps every menu block in one, so the rule also caught the footer menu and the
block nested inside the primary navigation — the whole Quick Links column was
being parked 300px off the right edge of every phone. The reset is scoped so
only `#main-nav` becomes a drawer.

**A closed drawer must not sit outside the viewport.** Parking it at
`right: -320px`, or at `right: 0` with `transform: translateX(100%)`, still
extends the scrollable area, and the page could be dragged sideways by the
drawer's width. No ancestor `overflow` can clip it either, because a fixed
element's containing block is the viewport. It therefore rests at `right: 0`
with `visibility: hidden` and **no transform**, and the slide is played as an
animation while it is open, where it costs nothing.
