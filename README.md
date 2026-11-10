# ClubHousePlanner
Booking and planning system for a Club House and other bookable items (a boule court, surfboards ...), built as a WordPress plugin. Plugin is primary written for Swedish language and payment resources as Swish.

## Requirements
- WordPress 7.1 or newer
- PHP 8.3 or newer

## Installation
Copy (or symlink) the `clubhouse-planner` folder to `wp-content/plugins/` and activate **ClubHouse Planner**.

## Usage
- `[clubhouse_calendar]` – public month calendar with booked/preliminary periods (no personal data is exposed).
- `[clubhouse_booking_form]` – booking request form. Clicking a day in the calendar pre-fills the form date.
- `[clubhouse_calendar item="2"]` and `[clubhouse_booking_form item="2"]` – the calendar and form of another item (the item number is shown under **Club House → Items**). Without `item` the first item is used.
- `[clubhouse_calendars]` – the calendars of all active items side by side (they stack on narrow screens). Use `items="1,2"` to choose which. Put it above the booking forms so a picked date is visible in the form.
- Admin: **Club House → Bookings** to handle requests, **Club House → Settings** for notification emails and payment instructions.
- Give housekeepers the **Club House Housekeeper** role. They can log in and handle bookings and are notified on new requests.

## Booking flow
| Step | Status | Email to requester |
|------|--------|--------------------|
| Request submitted | Request received (`pending`) | Request received (housekeepers notified) |
| Housekeeper approves | Awaiting payment (`awaiting_payment`) | Approved + payment instructions |
| Housekeeper registers payment | Confirmed (`confirmed`) | Booking confirmed (+ key box code, if set) |
| Deny / cancel | `denied` / `cancelled` | Denied / cancelled with reason |

`awaiting_payment` and `confirmed` block the period; new requests overlapping them are rejected.

## Items and units
Administrators add things to book under **Club House → Items**. The club house is item 1 and exists from the start.
- **Units:** an item can have several identical units (A, B, C ...). A request gets the first free unit; a housekeeper can move a booking to another unit. The letter is only shown when there is more than one unit.
- **Booking:** *Fixed booking types* uses the whole day / day / evening types from Settings. *The customer chooses start and end* lets the customer enter their own times.
- **Price (free-choice items):** per booking, hour or day, with an optional weekend price and a choice of which days count as weekend. A booking of 24 hours or less counts as one day, also across midnight; longer bookings are counted in blocks of 24 hours. Optional shortest and longest booking and notice needed.
- **Purpose, address and key box code** can be set per item. An item can also be deactivated; its bookings are kept.

## Blocked periods
Under **Club House → Blocked periods** an item (or one unit, or all items) can be blocked for an interval, for example repairs or a closed season. Tick *Repeat every year* for seasonal closures. Customers see "Not available" or your public text. Bookings already inside a new period are listed after saving but not changed.

## Key box code
Set **Key box code** (Swedish: *Nyckelboxkod*) under **Club House → Settings**. The code is added to the Confirmed email (sent once payment is registered, or straight away for free bookings) through the `{key_box}` placeholder. Leave the setting empty to leave it out. If the Confirmed email text has been customised, add `{key_box}` to it or use "Reset to default".

## Translations
Files live in `clubhouse-planner/languages/` (text domain `clubhouse-planner`). Swedish (`sv_SE`) is included; it is used when the site or user language is set to Svenska.
- `clubhouse-planner.pot` – template for new languages.
- `clubhouse-planner-sv_SE.po` – editable Swedish source (e.g. in Poedit).
- `clubhouse-planner-sv_SE.l10n.php` – what WordPress (6.5+) actually loads.

After editing a `.po` file, regenerate the PHP file with WP-CLI: `wp i18n make-php clubhouse-planner/languages`.

## Documentation
`Docs/` contains a Swedish end-user manual for housekeepers (*Instruktioner för Stugan*). The plugin also has a built-in help page under **Club House**.

## Contributing
Issues and pull requests are welcome. Please keep user-facing strings translatable (text domain `clubhouse-planner`) and update the `.pot`/`.po` files when adding or changing them.

## License
Released under the [GNU General Public License v2.0 or later](LICENSE), the same license as WordPress.
