# The Abstract Alchemist Booking - Project Context

## Project Overview
A precision scheduler and booking management plugin for WordPress. It provides an administrative interface for managing service availability and a front-end booking wizard for customers.

### Architecture & Key Technologies
- **Main Plugin File:** `alchemy-booking.php` (Initialization, Scripts, Shortcodes).
- **Core Logic:** Located in `includes/` using a class-based structure:
  - `Alchemy_Database`: Handles table creation and schema migrations.
  - `Alchemy_Admin_AJAX`: Manages administrative operations via WordPress AJAX.
  - `Alchemy_Booking`: Handles REST API routes for the booking wizard.
  - `Alchemy_Payments`: Manages Stripe PaymentIntent creation.
  - `Alchemy_Reminders`: Handles automated email reminders via WordPress Cron.
- **Admin Dashboard:** Located in `admin/` using `FullCalendar` for availability visualization.
- **Public Interface:** Located in `public/` using a multi-step booking wizard in JavaScript.
- **Third-Party Libraries:**
  - `FullCalendar` (loaded via CDN).
  - `Stripe PHP SDK` (locally bundled in `assets/stripe-php/`).
  - `Dashicons` (WordPress built-in).

## Building and Running
As a standard WordPress plugin, no complex build steps are required.

- **Installation:** Upload the directory to `/wp-content/plugins/alchemy-booking/`.
- **Activation:** Activate via the WordPress Plugins menu.
- **Shortcode:** Use `[alchemy_book]` on any page to display the booking wizard.
- **Admin Menu:** Access configuration via "Alchemy Bookings" in the WordPress admin bar.
- **Manual Testing:** Perform a booking via the wizard and verify that it appears in the **Active Ledger** under the **Bookings** tab in the dashboard.
- **Cron Job:** The plugin uses an hourly cron job for reminders. Verify its status via a plugin like "WP Control" if troubleshooting automated emails.

## Development Conventions
- **Security:** 
  - **Nonces:** Always use WordPress nonces for CSRF protection in forms and AJAX/REST calls (e.g., `wp_nonce_field` and `X-WP-Nonce`).
  - **Database:** Use `$wpdb->prepare()` for all SQL queries to prevent injection.
  - **Capabilities:** Check for `manage_options` before performing administrative actions.
- **Sanitization:** All user inputs must be sanitized using `sanitize_text_field`, `sanitize_email`, or `sanitize_textarea_field`.
- **Escaping:** All dynamic outputs to HTML, attributes, or JS must be escaped (`esc_html`, `esc_attr`, `esc_js`).
- **REST API:** Frontend interactions utilize the WordPress REST API namespace `alchemy/v1`.
- **Styling:** Custom styles are located in `public/css/booking-wizard.css` and `admin/css/admin-style.css`.
- **Database Schema:** Uses custom tables prefixed with `{$wpdb->prefix}alchemy_`. Refer to `includes/class-database.php` for schema definitions.
