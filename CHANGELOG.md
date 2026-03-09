Alchemy Booking System Changelog

v2.1.4-beta.1 (Current)

• CRITICAL: "Triple-Lock" Duplicate Prevention: Implemented database UNIQUE KEY constraints, backend "Same Person + Same Time" checks, and client-side processing locks to physically prevent duplicate ledger entries.
• UI Enhancement: Modern Modal Centering: Rebuilt the popup system using Stacking Context Isolation. All modals (Details, Alerts, Admin) are now perfectly centered on Desktop and Mobile with razor-sharp text over a blurred background.
• New Feature: Custom Verification Animations: Added three selectable animations in the dashboard (Pulsing Aura, Alchemist's Scan, Filling Flask) to replace the default spinner.
• UX Enhancement: Persistent Dashboard Notices: Success and error banners now stay visible across page reloads and automatically scroll to the top for immediate visibility.
• New Feature: Automatic GitHub Updates: Integrated a custom updater that detects new releases directly from the GitHub repository for one-click plugin updates.
• UI Refinement: Standardized font sizes (20px) for all primary buttons and service headings. Reduced category headings to 32px for a more sophisticated look.
• Bug Fix: Restored the "Back to Services" navigation and fixed the font selector override issue.
• Bug Fix: Resolved "Stripe 429 Too Many Requests" errors by implementing immediate Payment Element destruction and debounced redirect handling.

v2.1.3-beta.2

• CRITICAL BUG FIX: Resolved AJAX "403 Forbidden" errors by fixing nonce collisions and aligning JavaScript parameters with backend expectations.
• UI Modernization: Upgraded the Time Slot management modal to a clean, grid-based pill layout with interactive active states.
• UX Enhancement: Implemented a dynamic "Success Banner" system using AJAX for immediate feedback when saving availability or managing services.
• Admin Polish: Restyled dashboard pagination to match the native WordPress aesthetic (squarer buttons, WP Blue theme).
• Layout Refinement: Optimized mobile modal spacing, improved heading line-heights, and ensured inline calendar headers on smaller screens.
• Performance: Transitioned Service Management to a full AJAX workflow to eliminate unnecessary page reloads.

v2.1.3-beta.1

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
