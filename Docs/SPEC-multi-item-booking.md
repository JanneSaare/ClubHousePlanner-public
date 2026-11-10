# Spec: Bookable items (multi-item support)

Status: **draft for review** – no code changed yet.

## 1. Goal

ClubHouse Planner today books one resource, the clubhouse, in three fixed slots (whole day, day, evening). This spec generalises it so an administrator can define any number of **items** (clubhouse, boule court, surfboards, ...) that customers can book.

Decisions made so far:

- Extend the existing plugin (no rename). The clubhouse becomes item #1.
- All items use the same flow: `pending → awaiting_payment → confirmed` (+ `denied` / `cancelled`).
- Every item has a **quantity** (≥ 1). Units are lettered A, B, C ... and the letter is only shown when quantity > 1.
- "Weekend" is defined **per item**. Weekend fee is optional per item.
- Fee unit is chosen per item: per booking, per hour or per day.
- Admins can block intervals (unavailable, seasonal) per item, per unit, or for everything.
- The public calendar never exposes personal data.

## 2. Concepts

| Concept | Meaning |
|---|---|
| **Item** | A bookable thing with its own rules and prices, e.g. "Surfboard". |
| **Unit** | One of the `quantity` identical copies of an item, lettered A, B, C, ... AA. Not stored as rows; derived from `quantity`. |
| **Booking mode** | `slots` = customer picks a date and a predefined slot (today's behaviour). `free` = customer enters start and end date/time. |
| **Blackout** | An interval where an item (or one unit, or all items) cannot be booked. |

## 3. Data model

DB_VERSION is bumped from 4 to 5. Migration runs from `CHP_Activator::create_tables()` (dbDelta) plus a one-time data step guarded by the `chp_db_version` option.

### 3.1 `chp_items` (new)

| Column | Type | Notes |
|---|---|---|
| id | bigint unsigned PK | |
| name | varchar(190) | Shown to customers |
| description | text NULL | Shown in the form |
| image_id | bigint unsigned NULL | WP attachment id |
| active | tinyint(1) default 1 | Inactive items are hidden from customers, history is kept |
| sort_order | int default 0 | |
| quantity | smallint unsigned default 1 | Number of units, 1–52 (hard cap to keep letter naming sane) |
| booking_mode | varchar(10) | `slots` or `free` |
| slots | text NULL (JSON) | Only for `slots` mode: same structure as today's `slots` setting (enabled, start, end, caption, fee) |
| fee_unit | varchar(10) | `booking`, `hour`, `day` (used in `free` mode; `slots` mode keeps a fee per slot) |
| fee_weekday | int unsigned default 0 | SEK |
| fee_weekend | int unsigned NULL | NULL = same as weekday |
| weekend_days | varchar(20) | Comma separated ISO weekdays, default `6,7` (Sat, Sun). Items without a weekend fee can ignore it |
| purpose_mode | varchar(10) default 'none' | `none`, `list` or `text` |
| purposes | text NULL (JSON) | Dropdown values when `purpose_mode = list` |
| min_duration | int unsigned NULL | Minutes, optional |
| max_duration | int unsigned NULL | Minutes; NULL = unlimited. UI lets the admin enter hours or days |
| min_notice | int unsigned NULL | Optional: hours before start a booking may still be requested |
| key_box_code | varchar(100) NULL | Optional override of the global code |
| created_at, updated_at | datetime | |

### 3.2 `chp_blackouts` (new)

| Column | Type | Notes |
|---|---|---|
| id | bigint unsigned PK | |
| item_id | bigint unsigned NULL | NULL = all items |
| unit | varchar(3) NULL | NULL = all units of the item |
| start_at, end_at | datetime | Site time, `[start, end)` |
| yearly | tinyint(1) default 0 | Repeats every year (seasonal closure, e.g. 1 Nov–31 Mar). See 5.3 |
| reason | varchar(190) | Internal admin note, never shown publicly |
| public_text | varchar(190) NULL | Shown in the public calendar; empty = "Not available" |
| created_at | datetime | |

Index: `(item_id, start_at, end_at)`.

### 3.3 `chp_bookings` (changed)

Add:

| Column | Type | Notes |
|---|---|---|
| item_id | bigint unsigned NOT NULL | FK by convention; index `(item_id, start_at, end_at)` |
| unit | varchar(3) NOT NULL default 'A' | Assigned unit letter |

Existing `slot` stays (empty for `free` bookings). Existing `start_at` / `end_at` stay the source of truth for the booked period.

### 3.4 Migration (existing installs)

1. Create the new tables / columns.
2. Insert item #1 from current settings:
   - name = "Club House" (translatable; the admin can rename it)
   - quantity 1, booking_mode `slots`. Phase 1: item `slots` is left NULL, meaning "use the global `chp_settings['slots']`", so the existing settings page keeps working. Phase 2 copies the slots into the item and moves the editor.
   - The name is the plain string "Club House" (not translated, because the migration can run before translations load).
3. `UPDATE chp_bookings SET item_id = 1, unit = 'A' WHERE item_id = 0`.
4. Keep the old `slots` setting in `chp_settings` untouched for one release so a downgrade is possible. Read slot config from the item afterwards.
5. Single-item installs must look and behave exactly as before (no item picker, no unit letter, same emails).

## 4. Units and availability

- `CHP_Items::unit_labels( $item )` returns `['A', 'B', ...]` from quantity (Z → AA, AB, ...).
- `CHP_Items::show_unit( $item )` is `quantity > 1`. All display code (calendar, emails, admin list and detail, log notes) goes through one helper `CHP_Bookings::item_label( $booking )` → "Surfboard" or "Surfboard B".
- **Free units** for `[start, end)`: units of the item not covered by a blocking booking (`awaiting_payment`, `confirmed`) or an applicable blackout.
- A request is bookable if at least one unit is free. On create, the first free unit is assigned (lowest letter).
- The housekeeper can reassign a unit in the booking detail (only to a free unit); the change is written to the booking log.
- Reducing `quantity` is refused while future non-cancelled bookings use a unit that would disappear.
- `CHP_Bookings::has_overlap()` is replaced by `CHP_Bookings::free_units( item_id, start, end, exclude_id )`. `in_range()` gets an `item_id` parameter.
- Race condition: unit assignment and insert happen inside one transaction with `SELECT ... FOR UPDATE` on the item row, so two simultaneous requests cannot take the same unit.

## 5. Rules

### 5.1 Booking time validation (server side, always)

For `free` mode:

1. start < end, start not in the past, respects `min_notice`.
2. Duration within `min_duration` .. `max_duration`.
3. Does not overlap an applicable blackout (see 5.3).
4. At least one unit is free.

For `slots` mode: same as today (valid slot, not in the past), plus rules 3 and 4.

### 5.2 Fee calculation

`CHP_Pricing::calculate( $item, $start, $end, $is_local )` returns SEK.

- **Duration of 24 h or less counts as one day**, even when it crosses midnight (decided). Longer bookings are split into consecutive 24 h blocks (rounded up), and each block is priced by the day it starts on.
- `fee_unit = booking`: if the duration is 24 h or less, the **start day** decides weekend or weekday fee. If it is longer than 24 h, the weekend fee applies when the booking touches any weekend day (decided: the weekend fee overrides the start day; see 9.1 for the interpretation to confirm).
- `fee_unit = day`: each 24 h block as above, weekend fee for blocks starting on a weekend day.
- `fee_unit = hour`: every started hour is charged at the rate of the day it falls on.
- `fee_weekend = NULL` means the weekday fee applies on all days.
- `slots` mode keeps its fee per slot. Slot items use the same "weekend overrides" rule as `fee_unit = booking`.
- Local residents: the existing local-street list and `is_local_address` stay **global** and apply to all items for now (decided). `$is_local` is passed into `calculate()` and handled in one place, so a per-item option can be added later without redesign.
- The calculated fee is stored in `fee` when the housekeeper approves, as today, and can still be edited.
- Public holidays are out of scope for v1 (a `chp_weekend_day` filter is provided as a hook).

### 5.3 Blackouts

- An interval is blocked if it overlaps `[start_at, end_at)` of any applicable blackout (matching item and unit, or NULL).
- `yearly = 1`: the month/day range repeats every year, including ranges that wrap over New Year. Evaluated by expanding the rule for the years touching the queried range.
- Creating a blackout over existing bookings does not cancel them. The admin sees a warning listing the affected bookings and decides what to do.
- Blocked intervals show in the public calendar as "Not available" by default. The admin can enter an optional **public text** (e.g. "Closed for the season") that is shown instead. The internal `reason` is never exposed.

## 6. Front end

### 6.1 Shortcodes

- `[clubhouse_calendar item="3"]` and `[clubhouse_booking_form item="3"]`. `item` can be an id or slug (add `slug` column if slugs are wanted; ids are enough for v1).
- Without `item`: the form shows an item picker (hidden if only one active item); the calendar shows an item selector with the first item preselected.
- Clicking a day in the calendar pre-fills date and item in the form (as today).

### 6.2 Booking form

- `slots` mode: date + slot (as today).
- `free` mode: start date/time and end date/time (native inputs, `step` and limits from the item), max length text, live price estimate, validation message when over the limit or blocked.
- If quantity > 1 the customer does **not** pick a unit; the system assigns it. The success message and emails name it ("Surfboard B").
- **Purpose field is controlled per item** (decided): `purpose_mode` = `none` (field hidden, e.g. surfboards; purpose stored as the item name), `list` (dropdown from a per-item list, as the clubhouse does today) or `text` (free text, e.g. tables and chairs). The clubhouse keeps its current list as `list`.
- Address and local-street handling stay the same for all items for now (global street list). A per-item `ask_address` toggle is a possible later step.

### 6.3 REST

- `GET /clubhouse/v1/calendar?start&end&item` returns periods per item. For quantity > 1 each day also gets `free_units` and `total_units`; a day is "full" only when `free_units = 0`. No personal data.
- `GET /clubhouse/v1/items` (public): active items with name, description, mode, limits and a price summary. Used by the picker and the live price.
- `GET /clubhouse/v1/price?item&start&end` returns the estimate (same `CHP_Pricing`, never trusted on submit).
- Rate limiting, honeypot, nonce and the minimum form time are kept unchanged.

## 7. Admin

- **Club House → Items**: list, add, edit, deactivate. Fields as in 3.1. Slot editor shown only in `slots` mode (moves out of the global settings page).
- **Club House → Blocked periods**: list and add/edit/delete blackouts with item and unit selector, yearly checkbox and reason.
- **Bookings list / detail / add**: item and unit column, item filter, reassign unit, item selector in "add booking". Capability stays `manage_clubhouse_bookings` for all of it.
- Settings page keeps global items (emails, payment text, Swish, house rules page, widths).
- Mail placeholders: add `{item}` and `{unit}`; `{key_box}` uses the item override if set. Existing templates keep working (no `{item}` required).
- Help page and the Swedish manual get a section for items and blocked periods.

## 8. Phases

Each phase is shippable and keeps the clubhouse working.

1. **Items and units (no visible change).** `CHP_Items`, tables, migration, `item_id` / `unit` on bookings, `free_units()` replacing `has_overlap()`, unit assignment, per-item `in_range()`. Single-item install behaves exactly as before. Tests for migration and concurrency.
2. **Admin for items.** Items page, booking list item/unit display and reassign, mail placeholders. *Built (not yet tested on a live site):* name, description, quantity, purpose field (none/list/text), per-item key box code, order and active; unit reassignment on the booking; `{item}` and `{unit}` placeholders; item shown in the list, detail and mail summary only when it adds information. *Moved to a later phase:* the slot editor stays in Settings (global) until a second slots-mode item needs its own slots, and pricing, weekend and duration fields arrive with Phase 4 where they take effect. Items other than the first cannot be booked from the public form until Phase 5.
3. **Blackouts.** Table, admin page, enforcement in validation and calendar, yearly repeat, warning over existing bookings. *Built (not yet tested on a live site):* `chp_blackouts`, enforcement inside `free_units()` (so booking, approval, add-booking and unit changes all respect it), a "blocked" message in the form and admin, the **Blocked periods** page (available to housekeepers), yearly repeat including periods that wrap over New Year, public text, and a list of affected bookings after saving. The public calendar shows only blocks that cover the whole item; a block on a single unit does not mark the day. Feb 29 as a yearly start moves to Mar 1 in non-leap years.
4. **Free-time mode and pricing.** `CHP_Pricing`, weekend days, max/min length, form inputs, live price, REST `price` and `items`. *Built (not yet tested on a live site):* `CHP_Pricing` with the decided rules (calendar-day counting so a clock change does not add a day), per-item mode, price, weekend price and days, shortest/longest booking and notice, a free-time form (from/to, live price, no address), per-item purpose field, multi-day entries in the public calendar, `item="ID"` on both shortcodes, REST `price`. The calculated price is stored when the request is made and used as the default at approval. *Not built:* REST `items` (not needed until the Phase 5 picker), weekend override of slot fees for fixed-type items (those keep their price per slot), admin "add booking" for free-time items, and public holidays (a `chp_weekend_day` filter is provided).
5. **Front-end polish.** Item picker, per-item calendar, "x of N available", per-item purpose field, translations (`.pot`, `sv_SE`), docs, `readme.txt`, CHANGELOG.

Versioning: one minor release per phase (1.1.0 after phase 1, and so on), or merge phases when it makes sense. The phase list is expected to grow; new ideas are added as phases rather than reworking earlier ones. Work happens on a feature branch per phase.

## 8b. Parked ideas (to be scheduled)

- **Pending requests should block the period.** Today only `awaiting_payment` and `confirmed` block, so a new request can be made on a day that already has a pending one (noticed 2026-11-10). Consider adding `pending` to `blocking_statuses()` (per item, or global). Things to decide first: a pending request that is never handled would block the day indefinitely (auto-expiry or reminder?), whether the calendar should still show it as "Preliminary", and how `claim_unit()` and the "taken" message change. With quantity > 1 it would mean pending requests reserve a unit.

## 9. Decisions and remaining points

Decided:

1. A booking of 24 h or less is one day, even across midnight.
2. Weekend fee overrides the start-day rule for bookings longer than 24 h.
3. The same global local-street list applies to all items for now.
4. Purpose field is configured per item (none / list / text).
5. All units of an item share price and rules. Different prices = different items (Green 20 kr, Blue 25 kr are two items).
6. Blackouts show "Not available" by default, with an optional public text.
7. Ship in several steps; the scope may grow as new ideas come up. Each phase in section 8 is its own release (1.1, 1.2, ...).

To confirm (interpretation of decision 2): for a `fee_unit = booking` item, a booking longer than 24 h that touches any weekend day gets the weekend fee for the whole booking. For `day` and `hour` items the price is already calculated per block, so this only matters for per-booking items. If you meant something else, say so before Phase 4.
