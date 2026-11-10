# Changelog

All notable changes to this project are documented here.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project follows [Semantic Versioning](https://semver.org/).

## [1.1.0]
### Added
- Bookable items: besides the club house, any number of items (a boule court, surfboards, tables and chairs ...), each with its own description, rules and price. Under **Club House → Items**.
- Units: an item can have several identical units named A, B, C ...; a request gets the first free unit, a housekeeper can move a booking to another unit, and the calendar shows how many units are free. The letter is only shown when an item has more than one unit.
- Free-choice booking: the customer enters their own start and end. Price per booking, hour or day, optional weekend price and weekend days per item, shortest and longest booking, and notice needed. The price is shown while filling in the form and used as the default fee at approval.
- Blocked periods (**Club House → Blocked periods**): block an item, one unit or everything for an interval, optionally repeating every year for seasonal closures, with an optional public text. Existing bookings inside a new period are listed, not changed.
- Per-item purpose field (none, list or free text), key box code and address setting.
- Shortcodes `[clubhouse_calendar item="ID"]`, `[clubhouse_booking_form item="ID"]` and `[clubhouse_calendars]` (calendars of several items side by side).
- Admin "Add booking" works for any item and unit.
- `{item}` and `{unit}` placeholders in emails.
- Swedish translation of all new text and a new section in the built-in help.

### Changed
- The booking form shows date and time first, and the form fields use less vertical space; street and street number share a line.
- Availability is now checked per unit and also respects blocked periods.
- Database version 7: new tables `chp_items` and `chp_blackouts`, and `item_id` and `unit` on bookings. Existing bookings are attached to the club house (item 1) automatically.

### Known limitations
- No item picker: use one shortcode per item, or `[clubhouse_calendars]`.
- Items using fixed booking types share the booking types and prices in Settings.
- Public holidays do not count as weekend days (use the `chp_weekend_day` filter).
- Pending requests do not block a period (only approved ones do).

## [1.0.0]
### Added
- Public month calendar (`[clubhouse_calendar]`) showing booked and preliminary periods without personal data.
- Booking request form (`[clubhouse_booking_form]`) with calendar date pre-fill.
- Housekeeper approval workflow: request, approve, awaiting payment, confirmed, denied, cancelled.
- "Club House Housekeeper" user role.
- Editable HTML email templates with placeholders, including optional key box code.
- Payment instructions, Swish support, booking reminders and booking history.
- Swedish (`sv_SE`) translation.
