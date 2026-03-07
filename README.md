# Alchemy Booking System

A precision scheduler and booking management plugin for WordPress. This system provides a sophisticated administrative interface for managing service availability and a modern, multi-step booking wizard for customers.

## ✨ Key Features

- **Precision Scheduler:** Manage specific time slots and availability via an interactive calendar.
- **Modern Booking Wizard:** A clean, responsive 4-step process for service selection, scheduling, and payment.
- **Stripe Integration:** Secure payment processing with server-side transaction verification.
- **Smart Style Engine:** Inherit theme typography while maintaining granular control over colors, borders, and shadows via the Admin Dashboard.
- **Live Scroller:** A continuous 30-day date scroller with automatic month tracking and availability visual cues.
- **Smart Scroll UX:** Smooth transitions between booking steps that perfectly clear sticky headers on all devices.

## 🛠 Recent Updates (v2.1.3-beta)

- **Backend Security Patch:** Full Stripe PaymentIntent verification to prevent unauthorized booking entries.
- **UI/UX Polish:** Enhanced scroller text size and grey-out logic for a more intuitive scheduling experience.
- **Customizable Intensity:** New slider control for secondary element borders (Today, Details, and Time Slot buttons).
- **Responsive Layout:** Improved mobile spacing and navigation positioning.

## 🚀 Getting Started

1. **Installation:** Upload the `alchemy-booking` folder to your `/wp-content/plugins/` directory.
2. **Activation:** Activate the plugin through the 'Plugins' menu in WordPress.
3. **Configuration:** 
   - Navigate to **Alchemy Bookings** in the admin sidebar.
   - Enter your Stripe Public and Secret keys in the **Settings** tab.
   - Set your service slots and styling preferences.
4. **Usage:** Place the shortcode `[alchemy_book]` on any page where you want the wizard to appear.

## 🛡 Security

The system follows WordPress security standards, including:
- Nonce verification for all administrative and REST API actions.
- Strict data sanitization and output escaping.
- Prepared SQL statements to prevent injection.
- Backend verification of external payment statuses.

---
*Note: This version is currently in Beta (Active Development).*
