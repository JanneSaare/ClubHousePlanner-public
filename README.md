# ClubHousePlanner
Booking and planning system for a Club House, built as a WordPress plugin. Plugin is primary written for Swedish language and payment resources as Swish.

## Requirements
- WordPress 7.1 or newer
- PHP 8.3 or newer

## Installation
Copy (or symlink) the `clubhouse-planner` folder to `wp-content/plugins/` and activate **ClubHouse Planner**.

## Usage
- `[clubhouse_calendar]` – public month calendar with booked/preliminary periods (no personal data is exposed).
- `[clubhouse_booking_form]` – booking request form. Clicking a day in the calendar pre-fills the form date.
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
