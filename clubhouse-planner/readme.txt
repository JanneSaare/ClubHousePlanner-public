=== ClubHouse Planner ===
Contributors: janne saare
Tags: booking, calendar, reservation, club house, scheduling
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.3
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Booking and scheduling for a club house: public calendar, booking requests and a housekeeper approval workflow.

== Description ==

ClubHouse Planner lets visitors see when a club house is free and send a booking request. Housekeepers approve or deny requests, register payment and the requester is kept informed by email at every step.

* Public month calendar with booked and preliminary periods (no personal data shown)
* Booking request form; clicking a day in the calendar pre-fills the date
* Approval workflow: request received, awaiting payment, confirmed, denied, cancelled
* "Club House Housekeeper" role for people who handle bookings
* Editable HTML emails with placeholders, optional key box code in the confirmation
* Payment instructions, booking history and reminders
* Swedish translation included

== Installation ==

1. Upload the `clubhouse-planner` folder to `/wp-content/plugins/`, or install the zip via Plugins > Add New.
2. Activate **ClubHouse Planner**.
3. Configure notification emails and payment instructions under **Club House > Settings**.
4. Add `[clubhouse_calendar]` and `[clubhouse_booking_form]` to your pages.

== Frequently Asked Questions ==

= How do I show the calendar and the form? =

Use the shortcodes `[clubhouse_calendar]` and `[clubhouse_booking_form]`.

= Who gets notified of new requests? =

The notification addresses in the settings and all users with the Club House Housekeeper role.

= How do I add another language? =

Use `languages/clubhouse-planner.pot` as a template. The text domain is `clubhouse-planner`.

== Changelog ==

= 1.0.0 =
* Initial release.
