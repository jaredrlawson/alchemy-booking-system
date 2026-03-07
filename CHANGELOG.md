Alchemy Booking System Changelog
v2.1.3-beta (Active Development)

• CRITICAL SECURITY PATCH: Implemented backend Stripe PaymentIntent verification to prevent booking bypass fraud.
• UI Enhancement: Restored the continuous 30-day scroller with visual grey-out for unavailable days and larger scroller text.
• UX Enhancement: "Smart Scroll" feature implemented to ensure step transitions land perfectly below sticky headers on mobile and desktop.
• New Design Control: Added "Secondary Border Intensity" slider to dashboard for fine-tuned control over outline elements.
• Styling Consistency: Standardized black outline style for "Details", "Today", and "Time Slots", while locking Step 3 text inputs to square corners.
• Optimization: Refined Font Inheritance logic to prevent layout shift and invisible text issues.

v2.1.3

• CSS Variable Global Injection: Implemented `:root` variable logic that carries settings from the Admin Dashboard to the Public Booking Wizard.

• Flush Tab Integration: Eliminated the margin gap between `.nav-tab-wrapper` and `.alchemy-tabs-container` for a seamless UI.

• Real-time Color Logic: Updated `admin-script.js` with event listeners for instant color previews.

• Refined Reset System: Added a one-click "Reset to Default" button.

v2.1.0

• Theme Mod Integration: Introduced logic to detect `accent_color` and `primary_color` from active WordPress themes.

• Dynamic Style Engine: Developed the PHP-to-CSS bridge for hex color overrides.

v2.0.0

• Code Modularization: Separated CSS and JS into dedicated files.

• Admin Layout Upgrade: Implemented Flexbox-based dashboard with 43px sidebar padding.

• Toast Notification System: Added JS-based notifications for save actions.

v1.5.0

• Payment Gateway: Finalized Stripe API integration.

• Security Hardening: Implemented Nonce verification and strict data sanitization.

v1.0.0

• Initial release with core architecture and database tables.
