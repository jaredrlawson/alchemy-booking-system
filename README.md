# The Abstract Alchemist Booking System (v2.1.4-beta.1)

A high-precision scheduling and booking management plugin for WordPress, designed for elegant service delivery and robust transaction handling.

## 🚀 Key Features

- **"Triple-Lock" Security:** Advanced protection against duplicate bookings using database UNIQUE KEYs, backend email+time validation, and client-side singleton locks.
- **Modern UI/UX:** Perfectly centered, sharp-text modals with premium background blurring (Stacking Context Isolation).
- **Thematic Animations:** Selectable "Alchemist" verification animations (Pulsing Aura, Scan, Flask) to replace standard spinners.
- **Stripe Integration:** Secure payment processing with server-side reconciliation and fraud prevention.
- **Admin Dashboard:** Native WordPress aesthetic with real-time stats, bulk availability management, and persistent notification banners.
- **Mobile First:** Responsive multi-step wizard optimized for speed and clarity on all devices.
- **GitHub Updater:** One-click updates directly from the GitHub repository for seamless maintenance.

## 🛠 Installation

1. Upload the `alchemy-booking` folder to your `/wp-content/plugins/` directory.
2. Activate the plugin via the **Plugins** menu in WordPress.
3. Configure your Stripe API keys in **Alchemy Bookings -> Settings**.
4. Add the shortcode `[alchemy_book]` to any page or post.

## ⚙️ Configuration

Access the **Alchemy Ledger** from your WordPress admin bar to:
- Manage service categories, prices, and durations.
- Set global and date-specific availability.
- Customize the wizard's colors, fonts, and animations.
- Monitor revenue and booking history.

## 🔒 Security & Performance

- **Database Integrity:** Uses custom tables with strict indexing to ensure data consistency.
- **Transaction Safety:** All payments are verified on the backend before bookings are confirmed.
- **Optimization:** Dynamic CSS and localized JS ensure minimal impact on site loading times.

---
Developed by **Jared Lawson** for *The Abstract Alchemist*.
