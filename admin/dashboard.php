<?php
global $wpdb;
$table_avail = $wpdb->prefix . 'alchemy_availability';
$table_services = $wpdb->prefix . 'alchemy_services';
$table_bookings = $wpdb->prefix . 'alchemy_bookings';

$notices = [];

// POST Handlers
if (isset($_POST['sync_db_tables'])) {
    if (!isset($_POST['alchemy_admin_nonce']) || !wp_verify_nonce($_POST['alchemy_admin_nonce'], 'alchemy_admin_nonce')) {
        wp_die('Security check failed.');
    }
    Alchemy_Database::create_tables();
    $notices[] = ['type' => 'success', 'msg' => "<strong>Database Synced:</strong> Table structures updated."];
}

if (isset($_POST['save_day_slots'])) {
    if (!isset($_POST['alchemy_admin_nonce']) || !wp_verify_nonce($_POST['alchemy_admin_nonce'], 'alchemy_admin_nonce')) {
        wp_die('Security check failed.');
    }

    $raw_date = sanitize_text_field($_POST['target_date']);
    $date = date('Y-m-d', strtotime($raw_date));

    $slots_array = isset($_POST['slots']) ? (array)$_POST['slots'] : [];
    $sanitized_slots = array_map('sanitize_text_field', $slots_array);
    $slots_string = implode(',', $sanitized_slots);

    $wpdb->replace($table_avail, [
        'available_date' => $date,
        'time_slots' => $slots_string,
        'is_available' => !empty($slots_string) ? 1 : 0
    ]);

    $notices[] = ['type' => 'success', 'msg' => "<strong>Success:</strong> Availability for " . esc_html($date) . " updated."];
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const newSlots = <?php echo json_encode($slots_array); ?>;
            const targetDate = "<?php echo esc_js($date); ?>";
            if (window.alchemyAdminData) {
                window.alchemyAdminData.existingAvail[targetDate] = newSlots;
                if (window.calendar) window.calendar.refetchEvents();
            }
        });
    </script>
    <?php
}

if (isset($_POST['add_service'])) {
    if (!isset($_POST['alchemy_admin_nonce']) || !wp_verify_nonce($_POST['alchemy_admin_nonce'], 'alchemy_admin_nonce')) {
        wp_die('Security check failed.');
    }

    $inserted = $wpdb->insert($table_services, [
        'title'       => sanitize_text_field($_POST['title']),
        'category'    => sanitize_text_field($_POST['category']),
        'description' => sanitize_textarea_field($_POST['description']),
        'price'       => floatval($_POST['price']),
        'duration'    => intval($_POST['duration'])
    ]);

    if ($inserted !== false) {
        $notices[] = ['type' => 'success', 'msg' => "<strong>Success:</strong> New service created."];
    } else {
        $notices[] = ['type' => 'error', 'msg' => "<strong>Error:</strong> Failed to create service. Try clicking 'Sync Database' in Settings."];
    }
}

if (isset($_POST['update_service'])) {
    if (!isset($_POST['alchemy_admin_nonce']) || !wp_verify_nonce($_POST['alchemy_admin_nonce'], 'alchemy_admin_nonce')) {
        wp_die('Security check failed.');
    }

    $svc_id = intval($_POST['service_id']);
    $updated = $wpdb->update($table_services, [
        'title'       => sanitize_text_field($_POST['title']),
        'category'    => sanitize_text_field($_POST['category']),
        'description' => sanitize_textarea_field($_POST['description']),
        'price'       => floatval($_POST['price']),
        'duration'    => intval($_POST['duration'])
    ], ['id' => $svc_id]);

    if ($updated !== false) {
        $notices[] = ['type' => 'success', 'msg' => "<strong>Success:</strong> Service updated successfully."];
    } else {
        $notices[] = ['type' => 'error', 'msg' => "<strong>Error:</strong> Failed to update service."];
    }
}

if (isset($_GET['delete_service'])) {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access.');
    }
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'alchemy_delete_service_' . $_GET['delete_service'])) {
        wp_die('Security check failed.');
    }

    $wpdb->delete($table_services, ['id' => intval($_GET['delete_service'])]);
    
    // Redirect to clean URL using our persistent message system
    $redirect_url = remove_query_arg(['delete_service', '_wpnonce'], $_SERVER['REQUEST_URI']);
    $redirect_url = add_query_arg([
        'success_msg' => 'Service permanently deleted.',
        'msg_type'    => 'error',
        'active_tab'  => 'tab-services'
    ], $redirect_url);
    
    wp_redirect($redirect_url);
    exit;
}

if (isset($_POST['save_alchemy_configs'])) {
    error_log('Alchemy Debug: Save Configs Triggered. Nonce: ' . ($_POST['alchemy_admin_nonce'] ?? 'missing'));
    if (!isset($_POST['alchemy_admin_nonce']) || !wp_verify_nonce($_POST['alchemy_admin_nonce'], 'alchemy_admin_nonce')) {
        error_log('Alchemy Debug: Nonce Verification Failed');
        wp_die('Security check failed.');
    }

    error_log('Alchemy Debug: Saving colors. Button Color: ' . ($_POST['button_color'] ?? 'not set'));
    
    // Use swatch value as fallback if hex input is missing or empty
    $h_color = !empty($_POST['heading_color']) ? $_POST['heading_color'] : ($_POST['heading_color_swatch'] ?? '#111111');
    $b_color = !empty($_POST['border_color']) ? $_POST['border_color'] : ($_POST['border_color_swatch'] ?? '#cccccc');
    $btn_color = !empty($_POST['button_color']) ? $_POST['button_color'] : ($_POST['button_color_swatch'] ?? '#111111');
    $btn_hover = !empty($_POST['button_hover_color']) ? $_POST['button_hover_color'] : ($_POST['button_hover_color_swatch'] ?? '#c5a000');
    $btn_text = !empty($_POST['button_text_color']) ? $_POST['button_text_color'] : ($_POST['button_text_color_swatch'] ?? '#ffffff');
    $sel_day = !empty($_POST['selected_day_color']) ? $_POST['selected_day_color'] : ($_POST['selected_day_color_swatch'] ?? '#c5a000');

    update_option('alchemy_heading_color', sanitize_hex_color($h_color));
    update_option('alchemy_border_color', sanitize_hex_color($b_color));
    update_option('alchemy_border_opacity', intval($_POST['border_opacity']));
    update_option('alchemy_card_shadow', isset($_POST['card_shadow']) ? '1' : '0');
    update_option('alchemy_shadow_intensity', intval($_POST['shadow_intensity']));
    update_option('alchemy_button_color', sanitize_hex_color($btn_color));
    update_option('alchemy_button_hover_color', sanitize_hex_color($btn_hover));
    update_option('alchemy_button_text_color', sanitize_hex_color($btn_text));
    update_option('alchemy_secondary_border_intensity', intval($_POST['secondary_border_intensity']));
    update_option('alchemy_selected_day_color', sanitize_hex_color($sel_day));
    update_option('alchemy_button_font', sanitize_text_field($_POST['button_font']));
    update_option('alchemy_inherit_font', isset($_POST['inherit_font']) ? '1' : '0');
    update_option('alchemy_use_theme_styles', isset($_POST['use_theme']) ? '1' : '0');
    update_option('alchemy_button_style', sanitize_text_field($_POST['button_style']));
    update_option('alchemy_loader_style', sanitize_text_field($_POST['loader_style']));
    update_option('alchemy_border_radius', intval($_POST['border_radius']));
    update_option('alchemy_stripe_pub_key', sanitize_text_field($_POST['stripe_pub']));
    update_option('alchemy_stripe_sec_key', sanitize_text_field($_POST['stripe_sec']));
    update_option('alchemy_hours', sanitize_text_field($_POST['global_slots']));

    $notices[] = ['type' => 'success', 'msg' => "<strong>Settings Saved:</strong> Your configurations have been updated."];
}

if (isset($_GET['success_msg'])) {
    $type = isset($_GET['msg_type']) ? sanitize_text_field($_GET['msg_type']) : 'success';
    $label = 'Success';
    if ($type === 'warning') $label = 'Warning';
    if ($type === 'error') $label = 'Danger';
    $notices[] = ['type' => $type, 'msg' => "<strong>{$label}:</strong> " . sanitize_text_field($_GET['success_msg'])];
    ?>
    <script>
        // Clean URL parameters without reloading so refresh doesn't repeat the message
        const url = new URL(window.location);
        url.searchParams.delete('success_msg');
        url.searchParams.delete('msg_type');
        window.history.replaceState({}, '', url);
    </script>
    <?php
}

// --- Pagination Logic ---
$svc_per_page = 5;
$book_per_page = wp_is_mobile() ? 5 : 10;

$svc_page = isset($_GET['svc_p']) ? max(1, intval($_GET['svc_p'])) : 1;
$book_page = isset($_GET['book_p']) ? max(1, intval($_GET['book_p'])) : 1;

$svc_offset = ($svc_page - 1) * $svc_per_page;
$book_offset = ($book_page - 1) * $book_per_page;

$total_services = $wpdb->get_var("SELECT COUNT(*) FROM $table_services");
$total_bookings = $wpdb->get_var("SELECT COUNT(*) FROM $table_bookings");

$services = $wpdb->get_results("SELECT * FROM $table_services ORDER BY id DESC LIMIT " . intval($svc_offset) . ", " . intval($svc_per_page));
$bookings = $wpdb->get_results("SELECT * FROM $table_bookings ORDER BY created_at DESC LIMIT " . intval($book_offset) . ", " . intval($book_per_page));

// Financial Overview Math
$ledger_stats = Alchemy_Database::get_ledger_stats();
$total_bookings_confirmed = $ledger_stats['total_bookings'];
$total_services_active = $ledger_stats['total_services'];
$revenue_7_days = $ledger_stats['revenue_7_days'];
$revenue_30_days = $ledger_stats['revenue_30_days'];
// Recent Activity Feed (Last 5 events)
$recent_activity = $wpdb->get_results($wpdb->prepare("
    SELECT b.customer_name, s.title, b.created_at, b.updated_at, b.status
    FROM $table_bookings b
    LEFT JOIN $table_services s ON b.service_id = s.id
    ORDER BY COALESCE(b.updated_at, b.created_at) DESC
    LIMIT %d", 5));



// Check current key status for the UI
$stored_pub = get_option('alchemy_stripe_pub_key', '');
$stored_sec = get_option('alchemy_stripe_sec_key', '');

// Tab Memory Logic (Auto-switch if pagination is clicked)
$active_tab = 'tab-availability';
if (isset($_GET['active_tab'])) {
    $active_tab = sanitize_text_field($_GET['active_tab']);
} elseif (isset($_GET['book_p'])) { // Check book_p first
    $active_tab = 'tab-bookings';
} elseif (isset($_GET['svc_p'])) { // Then svc_p
    $active_tab = 'tab-services';
} elseif (isset($_POST['active_tab'])) {
    $active_tab = sanitize_text_field($_POST['active_tab']);
}
?>

<div class="wrap alchemy-admin-wrap">
    <div class="alchemy-header-flex">
        <h1>Alchemy Dashboard</h1>
        <button type="button" class="button alc-sync-btn" onclick="syncAdminAvailability()">Sync Ledger Data</button>
    </div>

    <?php foreach ($notices as $notice): ?>
        <div class="alc-notice alc-notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
            <p><?php echo wp_kses_post($notice['msg']); ?></p>
            <button type="button" class="notice-dismiss" onclick="this.parentElement.remove()"><span class="screen-reader-text">Dismiss this notice.</span></button>
        </div>
    <?php endforeach; ?>
    <div id="alc-notices-container"></div>

    <div class="alchemy-dashboard-layout">
        <div class="alchemy-main-content">
            <h2 class="nav-tab-wrapper">
                <a href="#tab-availability" class="nav-tab" data-tab="tab-availability">Availability</a>
                <a href="#tab-services" class="nav-tab" data-tab="tab-services">Services</a>
                <a href="#tab-bookings" class="nav-tab" data-tab="tab-bookings">Bookings</a>
                <a href="#tab-settings" class="nav-tab" data-tab="tab-settings">Settings</a>
            </h2>

            <div class="alchemy-tabs-container">
                <div id="tab-availability" class="alchemy-tab-content">
                    <div class="postbox"><div class="inside">
                        <div class="alc-bulk-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px;">
                            <div>
                                <p class="description">Click a date to manage slots, or use <strong>Bulk Actions</strong> to apply slots to multiple days.</p>
                            </div>
                            <div class="alc-bulk-actions-wrap" style="text-align:right;">
                                <button type="button" id="alc-bulk-toggle" class="button">Bulk Range Select</button>
                                <div id="alc-bulk-panel" style="display:none; margin-top:10px; padding:15px; background:#f9f9f9; border:1px solid #ddd; border-radius:4px; text-align:left; max-width:350px;">
                                    <p style="margin-top:0;"><strong>Step 1:</strong> Select a start and end date on the calendar.</p>
                                    <p><strong>Step 2:</strong> Choose slots to apply to ALL days in that range.</p>
                                    <div id="alc-bulk-slots" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(100px, 1fr)); gap:8px; margin:15px 0;">
                                        <?php
                                        $global_slots = array_map('trim', explode(',', get_option('alchemy_hours', '09:00 AM, 11:00 AM, 01:30 PM, 03:30 PM')));
                                        foreach($global_slots as $gs): ?>
                                            <label style="font-size:12px;"><input type="checkbox" class="alc-bulk-slot-check" value="<?php echo esc_attr($gs); ?>"> <?php echo esc_html($gs); ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="alc-bulk-footer" style="display:flex; justify-content:space-between; margin-top:15px;">
                                        <button type="button" id="alc-bulk-cancel" class="button">Cancel</button>
                                        <button type="button" id="alc-bulk-apply" class="button button-primary">Apply to Range</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="alchemy-calendar"></div>
                    </div></div>
                </div>

                <div id="tab-services" class="alchemy-tab-content">
                    <div class="postbox"><div class="inside">
                        <h3>Service Menu</h3>
                        <table class="wp-list-table widefat fixed striped">
                            <thead><tr><th>Service Name</th><th>Category</th><th>Price</th><th>Mins</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach($services as $s): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($s->title); ?></strong></td>
                                    <td><span class="badge"><?php echo esc_html($s->category ?: 'General'); ?></span></td>
                                    <td>$<?php echo number_format($s->price, 0); ?></td>
                                    <td><?php echo esc_html($s->duration); ?></td>
                                    <td>
                                        <button type="button" class="button button-small" onclick='openEditServiceModal(<?php echo json_encode($s); ?>)'>Edit</button>
                                        <a href="<?php echo esc_url(wp_nonce_url("?page=alchemy-dashboard&delete_service=" . $s->id, 'alchemy_delete_service_' . $s->id)); ?>" class="button button-small alc-delete-text" onclick="return confirm('Delete this service?')">Delete</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <?php if ($total_services > $svc_per_page): ?>
                            <div class="tablenav"><div class="tablenav-pages">
                                <?php echo paginate_links([
                                    'base'      => add_query_arg('svc_p', '%#%'),
                                    'format'    => '',
                                    'prev_text' => __('&laquo;'),
                                    'next_text' => __('&raquo;'),
                                    'total'     => ceil($total_services / $svc_per_page),
                                    'current'   => $svc_page,
                                    'add_args'  => ['book_p' => $book_page, 'active_tab' => 'tab-services'] // Preserve other pagination and active tab
                                ]); ?>
                            </div></div>
                        <?php endif; ?>

                        <hr class="alc-divider">

                        <h4>Add New Service</h4>
                        <div class="alc-service-editor-flex">
                            <!-- Left: Vertical Form -->
                            <div class="alc-editor-form">
                                <form method="POST" id="alc-add-service-form">
                                    <input type="hidden" name="active_tab" class="alc-active-tab-input" value="tab-services">
                                    <?php wp_nonce_field('alchemy_admin_nonce', 'alchemy_admin_nonce'); ?>

                                    <div class="alc-field-group alc-mb-15">
                                        <label>Service Name</label>
                                        <input type="text" name="title" id="preview-input-title" class="alc-full-width" placeholder="e.g. Ritual Deep Tissue" required>
                                    </div>

                                    <div class="alc-field-group alc-mb-15">
                                        <label>Category</label>
                                        <select name="category" id="preview-input-category" class="alc-full-width">
                                            <option value="ZOOM/Facetime Readings">ZOOM/Facetime Readings</option>
                                            <option value="Phone Readings">Phone Readings</option>
                                            <option value="In-Person">In-Person</option>
                                            <option value="General">General</option>
                                        </select>
                                    </div>

                                    <div class="alc-field-group alc-mb-15">
                                        <label>Description (Short summary for the card)</label>
                                        <textarea name="description" id="preview-input-desc" placeholder="Briefly describe this service..." rows="3" style="width:100%;"></textarea>
                                    </div>

                                    <div class="alc-form-grid" style="margin-bottom: 20px;">
                                        <div class="alc-field-group">
                                            <label>Price ($)</label>
                                            <input type="number" name="price" id="preview-input-price" class="short-box" placeholder="150" required>
                                        </div>
                                        <div class="alc-field-group">
                                            <label>Mins</label>
                                            <input type="number" name="duration" id="preview-input-duration" class="short-box" placeholder="60" required>
                                        </div>
                                    </div>

                                    <div class="alc-form-submit">
                                        <input type="submit" name="add_service" class="button button-primary" value="Create Service">
                                    </div>
                                </form>
                            </div>

                            <div class="alc-editor-preview">
                                <span class="alc-preview-label">Live Preview (Mobile/Public)</span>
                                <div class="alc-admin-preview-card">
                                    <div class="card-header-row">
                                        <h3 id="preview-card-title">Service Name</h3>
                                        <span class="card-price" id="preview-card-price">$0</span>
                                    </div>
                                    <div class="card-duration"><span class="dashicons dashicons-clock"></span> <span id="preview-card-duration">0 mins</span></div>
                                    <div class="card-footer">
                                        <button type="button" class="btn-details" onclick="previewDescriptionPopup()">Details</button>
                                        <button type="button" class="btn-book">Book Now</button>
                                    </div>
                                </div>
                                <p class="description" style="margin-top:15px;">Your primary color will apply automatically on the front-end.</p>
                            </div>
                        </div>
                    </div></div>
                </div>

                <div id="tab-bookings" class="alchemy-tab-content">
                    <div class="postbox"><div class="inside">
                        <h3>Active Ledger</h3>
                        <input type="hidden" id="alchemy_ledger_nonce" name="alchemy_admin_nonce" value="<?php echo wp_create_nonce('alchemy_admin_nonce'); ?>">

                        <div class="tablenav top" style="margin-bottom: 10px; height: auto;">
                            <div class="alignleft actions bulkactions" style="display: flex; gap: 5px; align-items: stretch;">
                                <label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label>
                                <select name="action" id="alc-ledger-bulk-action" style="height: 32px; min-height: 32px; line-height: 1; padding: 0 8px; margin: 0; box-sizing: border-box;">
                                    <option value="-1">Bulk Actions</option>
                                    <option value="cancel">Cancel Selected</option>
                                    <option value="delete">Delete Permanently</option>
                                </select>
                                <input type="button" id="doaction" class="button action" value="Apply" onclick="applyLedgerBulkAction()" style="height: 32px; min-height: 32px; line-height: 30px; margin: 0; box-sizing: border-box;">
                            </div>
                            <br class="clear">
                        </div>

                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <td id="cb" class="manage-column column-cb check-column"><input id="cb-select-all-1" type="checkbox" onclick="toggleAllLedgerCheckboxes(this)"></td>
                                    <th>Customer</th>
                                    <th>Service</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                // Pre-fetch services to avoid N+1 queries in the loop
                                $all_svcs_raw = $wpdb->get_results("SELECT id, title FROM $table_services");
                                $all_svcs = [];
                                foreach($all_svcs_raw as $s) { $all_svcs[$s->id] = $s->title; }

                                foreach($bookings as $b): ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <div class="alc-mobile-header-row">
                                            <div class="alc-mobile-cb-label">
                                                <input type="checkbox" class="ledger-checkbox" value="<?php echo $b->id; ?>">
                                                <span class="alc-mobile-only-text">Select this booking</span>
                                            </div>
                                            <div class="alc-ledger-actions">
                                                <?php if ($b->status !== 'cancelled'): ?>
                                                    <button type="button" class="button button-small" onclick="cancelBooking(<?php echo $b->id; ?>)">Cancel</button>
                                                <?php endif; ?>
                                                <button type="button" class="button button-small alc-delete-text" onclick="deleteBooking(<?php echo $b->id; ?>)">Delete</button>
                                            </div>
                                        </div>
                                    </th>
                                    <td data-label="Customer"><?php echo esc_html($b->customer_name); ?><br><small><?php echo esc_html($b->customer_email); ?></small></td>
                                    <td data-label="Service">
                                        <?php
                                            echo isset($all_svcs[$b->service_id]) ? esc_html($all_svcs[$b->service_id]) : 'Unknown Service';
                                        ?>
                                    </td>
                                    <td data-label="Time"><?php echo esc_html($b->booking_time); ?></td>
                                    <td data-label="Status">
                                        <?php $status_class = ($b->status === 'confirmed') ? 'badge-confirmed' : 'badge-pending'; ?>
                                        <span class="badge <?php echo $status_class; ?>"><?php echo esc_html($b->status); ?></span>
                                    </td>
                                    <td class="alc-desktop-actions" data-label="Manage">
                                        <?php if ($b->status !== 'cancelled'): ?>
                                            <button type="button" class="button button-small" onclick="cancelBooking(<?php echo $b->id; ?>)">Cancel</button>
                                        <?php endif; ?>
                                        <button type="button" class="button button-small alc-delete-text" onclick="deleteBooking(<?php echo $b->id; ?>)">Delete</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if ($total_bookings > $book_per_page): ?>
                            <div class="tablenav"><div class="tablenav-pages">
                                <?php echo paginate_links([
                                    'base'      => add_query_arg('book_p', '%#%'),
                                    'format'    => '',
                                    'prev_text' => __('&laquo;'),
                                    'next_text' => __('&raquo;'),
                                    'total'     => ceil($total_bookings / $book_per_page),
                                    'current'   => $book_page,
                                    'add_args'  => ['svc_p' => $svc_page, 'active_tab' => 'tab-bookings'] // Preserve other pagination and active tab
                                ]); ?>
                            </div></div>
                        <?php endif; ?>
                    </div></div>
                </div>

                <div id="tab-settings" class="alchemy-tab-content">
                    <div class="postbox"><div class="inside">
                        <h3>Plugin Configurations</h3>
                        <form method="POST">
                            <input type="hidden" name="active_tab" class="alc-active-tab-input" value="tab-settings">
                            <?php wp_nonce_field('alchemy_admin_nonce', 'alchemy_admin_nonce'); ?>
                            <table class="form-table">
                                <tr>
                                    <th>Theme Styling</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="use_theme" value="1" <?php checked(get_option('alchemy_use_theme_styles', '0'), '1'); ?>>
                                            Inherit theme fonts and colors (if supported)
                                        </label>
                                        <p class="description">If enabled, the booking wizard will try to use your theme's primary color and typography.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Global Font Family</th>
                                    <td>
                                        <?php $current_font = get_option('alchemy_button_font', 'inherit'); ?>
                                        <select name="button_font" class="alc-full-width" style="margin-bottom:10px; max-width: 250px;">
                                            <option value="inherit" <?php selected($current_font, 'inherit'); ?>>Theme Default</option>
                                            <option value="system" <?php selected($current_font, 'system'); ?>>System Sans-Serif</option>
                                            <option value="montserrat" <?php selected($current_font, 'montserrat'); ?>>Montserrat</option>
                                            <option value="roboto" <?php selected($current_font, 'roboto'); ?>>Roboto</option>
                                            <option value="opensans" <?php selected($current_font, 'opensans'); ?>>Open Sans</option>
                                            <option value="lato" <?php selected($current_font, 'lato'); ?>>Lato</option>
                                            <option value="playfair" <?php selected($current_font, 'playfair'); ?>>Playfair Display</option>
                                            <option value="ebgaramond" <?php selected($current_font, 'ebgaramond'); ?>>EB Garamond</option>
                                            <option value="georgia" <?php selected($current_font, 'georgia'); ?>>Georgia</option>
                                            <option value="arial" <?php selected($current_font, 'arial'); ?>>Arial</option>
                                            <option value="times" <?php selected($current_font, 'times'); ?>>Times New Roman</option>
                                        </select>
                                        <br>
                                        <label>
                                            <input type="checkbox" name="inherit_font" value="1" <?php checked(get_option('alchemy_inherit_font', '1'), '1'); ?>>
                                            Automatically inherit site's primary font globally
                                        </label>
                                        <p class="description">Note: Uncheck the box above to use the dropdown selection.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Heading Text Color</th>
                                    <td>
                                        <div class="alc-color-group">
                                            <input type="color" name="heading_color_swatch" class="alc-color-swatch" value="<?php echo esc_attr(get_option('alchemy_heading_color', '#111111')); ?>">
                                            <input type="text" name="heading_color" class="alc-color-hex" value="<?php echo esc_attr(get_option('alchemy_heading_color', '#111111')); ?>">
                                        </div>
                                        <p class="description">Color for 'Step' headings and Category titles.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Border Color & Strength</th>
                                    <td>
                                        <div class="alc-color-group" style="margin-bottom:10px;">
                                            <input type="color" name="border_color_swatch" class="alc-color-swatch" value="<?php echo esc_attr(get_option('alchemy_border_color', '#cccccc')); ?>">
                                            <input type="text" name="border_color" class="alc-color-hex" value="<?php echo esc_attr(get_option('alchemy_border_color', '#cccccc')); ?>">
                                        </div>
                                        <div class="alc-slider-group">
                                            <label>Border Strength (Opacity)</label>
                                            <input type="range" name="border_opacity" min="0" max="100" value="<?php echo esc_attr(get_option('alchemy_border_opacity', '100')); ?>">
                                            <span class="alc-slider-val"><?php echo esc_attr(get_option('alchemy_border_opacity', '100')); ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Secondary Border Intensity</th>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:10px;">
                                            <input type="range" name="secondary_border_intensity" min="0" max="100" value="<?php echo esc_attr(get_option('alchemy_secondary_border_intensity', '100')); ?>" style="width:200px;">
                                            <span class="alc-slider-val-secondary"><?php echo esc_attr(get_option('alchemy_secondary_border_intensity', '100')); ?>%</span>
                                        </div>
                                        <p class="description">Opacity for the borders of black-outline elements (Today, Details, Time Slots, and Text Boxes). Text color remains solid black.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Card Shadows</th>
                                    <td>
                                        <div style="margin-bottom:10px;">
                                            <label>
                                                <input type="checkbox" name="card_shadow" value="1" <?php checked(get_option('alchemy_card_shadow', '0'), '1'); ?>>
                                                Enable soft depth shadows behind service cards
                                            </label>
                                        </div>
                                        <div class="alc-slider-group">
                                            <label>Shadow Intensity</label>
                                            <input type="range" name="shadow_intensity" min="0" max="50" value="<?php echo esc_attr(get_option('alchemy_shadow_intensity', '8')); ?>">
                                            <span class="alc-slider-val-shadow"><?php echo esc_attr(get_option('alchemy_shadow_intensity', '8')); ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Button Style</th>
                                    <td>
                                        <?php $btn_style = get_option('alchemy_button_style', 'solid'); ?>
                                        <select name="button_style" class="alc-full-width" style="max-width: 250px;">
                                            <option value="solid" <?php selected($btn_style, 'solid'); ?>>Solid Color (Default)</option>
                                            <option value="outline" <?php selected($btn_style, 'outline'); ?>>Outline / Minimal</option>
                                            <option value="theme" <?php selected($btn_style, 'theme'); ?>>Full Theme Button Match</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Verification Animation</th>
                                    <td>
                                        <?php $loader_style = get_option('alchemy_loader_style', 'aura'); ?>
                                        <select name="loader_style" class="alc-full-width" style="max-width: 250px;">
                                            <option value="aura" <?php selected($loader_style, 'aura'); ?>>Pulsing Aura (Elegant)</option>
                                            <option value="scan" <?php selected($loader_style, 'scan'); ?>>Alchemist's Scan (Modern)</option>
                                            <option value="flask" <?php selected($loader_style, 'flask'); ?>>Filling Flask (Thematic)</option>
                                        </select>
                                        <p class="description">Select the animation shown during payment verification.</p>
                                    </td>
                                </tr>

                                <tr>
                                    <th>Corner Roundness (px)</th>
                                    <td>
                                        <input type="number" name="border_radius" min="0" max="25" value="<?php echo esc_attr(get_option('alchemy_border_radius', '12')); ?>" style="width: 60px;">
                                        <span class="description">Applies to buttons and cards (Max 25 for Pill Shape).</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Button Background</th>
                                    <td>
                                        <div class="alc-color-group">
                                            <input type="color" name="button_color_swatch" class="alc-color-swatch" value="<?php echo esc_attr(get_option('alchemy_button_color', '#111111')); ?>">
                                            <input type="text" name="button_color" class="alc-color-hex" value="<?php echo esc_attr(get_option('alchemy_button_color', '#111111')); ?>">
                                        </div>
                                        <p class="description">Background color for primary buttons.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Button Hover Color</th>
                                    <td>
                                        <div class="alc-color-group">
                                            <input type="color" name="button_hover_color_swatch" class="alc-color-swatch" value="<?php echo esc_attr(get_option('alchemy_button_hover_color', '#c5a000')); ?>">
                                            <input type="text" name="button_hover_color" class="alc-color-hex" value="<?php echo esc_attr(get_option('alchemy_button_hover_color', '#c5a000')); ?>">
                                        </div>
                                        <p class="description">Color when hovering over buttons or selecting time slots.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Button Text Color</th>
                                    <td>
                                        <div class="alc-color-group">
                                            <input type="color" name="button_text_color_swatch" class="alc-color-swatch" value="<?php echo esc_attr(get_option('alchemy_button_text_color', '#ffffff')); ?>">
                                            <input type="text" name="button_text_color" class="alc-color-hex" value="<?php echo esc_attr(get_option('alchemy_button_text_color', '#ffffff')); ?>">
                                        </div>
                                        <p class="description">Text color for primary buttons.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Selected Day Color</th>
                                    <td>
                                        <div class="alc-color-group">
                                            <input type="color" name="selected_day_color_swatch" class="alc-color-swatch" value="<?php echo esc_attr(get_option('alchemy_selected_day_color', '#c5a000')); ?>">
                                            <input type="text" name="selected_day_color" class="alc-color-hex" value="<?php echo esc_attr(get_option('alchemy_selected_day_color', '#c5a000')); ?>">
                                        </div>
                                        <p class="description">Color for the active day in the date scroller.</p>
                                    </td>
                                </tr>
                            </table>

                            <hr class="alc-divider">
                            <h3>Global Time Slots</h3>
                            <table class="form-table">
                                <tr>
                                    <th>Available Times</th>
                                    <td>
                                        <input type="text" name="global_slots" value="<?php echo esc_attr(get_option('alchemy_hours', '09:00 AM, 11:00 AM, 01:30 PM, 03:30 PM')); ?>" class="alc-full-width">
                                        <p class="description">Comma-separated list of default times available for booking.</p>
                                    </td>
                                </tr>
                            </table>

                            <hr class="alc-divider">
                            <h3>Stripe API Integration</h3>
                            <table class="form-table">
                                <tr>
                                    <th>Stripe Public Key</th>
                                    <td>
                                        <input type="text" name="stripe_pub" value="<?php echo esc_attr($stored_pub); ?>" class="alc-full-width">
                                        <span class="key-status <?php echo $stored_pub ? 'status-valid' : 'status-invalid'; ?>">
                                            <?php echo $stored_pub ? '✔ Key Registered' : '✘ Key Missing'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Stripe Secret Key</th>
                                    <td>
                                        <input type="password" name="stripe_sec" value="<?php echo esc_attr($stored_sec); ?>" class="alc-full-width">
                                        <span class="key-status <?php echo $stored_sec ? 'status-valid' : 'status-invalid'; ?>">
                                            <?php echo $stored_sec ? '✔ Key Registered' : '✘ Key Missing'; ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                            <p><input type="submit" name="save_alchemy_configs" class="button button-primary" value="Save Settings"></p>
                        </form>

                        <hr class="alc-divider">
                        <h3>Database Maintenance</h3>
                        <p class="description">If you recently uploaded new files and services are not saving, click the button below to update your database tables.</p>
                        <form method="POST">
                            <input type="hidden" name="active_tab" value="tab-settings">
                            <?php wp_nonce_field('alchemy_admin_nonce', 'alchemy_admin_nonce'); ?>
                            <input type="submit" name="sync_db_tables" class="button alc-btn-sync" value="Sync Database Structures">

                        </form>
                    </div></div>
                </div>
            </div>
        </div>

        <div class="alchemy-sidebar">
            <div class="postbox alchemy-ledger-box" id="alchemy-ledger-stats">
                <h2 class="hndle">Ledger Overview</h2>
                <div class="inside">
                    <div class="alc-stat-row">
                        <span>Total Bookings</span>
                        <strong><?php echo esc_html($total_bookings_confirmed); ?></strong>
                    </div>
                    <div class="alc-stat-row">
                        <span>Active Services</span>
                        <strong><?php echo esc_html($total_services_active); ?></strong>
                    </div>
                    <div class="alc-stat-row">
                        <span>Upcoming Revenue (7d)</span>
                        <strong class="alc-text-success">$<?php echo number_format($revenue_7_days, 2); ?></strong>
                    </div>
                    <div class="alc-stat-row">
                        <span>Monthly Revenue</span>
                        <strong>$<?php echo number_format($revenue_30_days, 2); ?></strong>
                    </div>
                </div>
            </div>

            <div class="postbox alchemy-ledger-box">
                <h2 class="hndle">Recent Activity</h2>
                <div class="inside">
                    <?php if (empty($recent_activity)): ?>
                        <p class="description">No recent activity recorded yet.</p>
                    <?php else: ?>
                        <ul class="alc-activity-list">
                            <?php foreach ($recent_activity as $act): ?>
                                <li>
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                    <div class="alc-act-content">
                                        <strong><?php echo esc_html($act->customer_name); ?></strong>
                                        <?php if ($act->status === 'cancelled'): ?>
                                            <span style="color: #d63638;">cancelled</span> <em><?php echo esc_html($act->title); ?></em>
                                        <?php else: ?>
                                            booked <em><?php echo esc_html($act->title); ?></em>
                                        <?php endif; ?>
                                        <small><?php echo human_time_diff(strtotime($act->updated_at ?: $act->created_at), current_time('timestamp')); ?> ago</small>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="alc-time-modal" class="alchemy-admin-modal">
    <div class="alchemy-admin-modal-overlay" onclick="closeAdminModal()"></div>
    <div class="alchemy-admin-modal-content">
        <span class="alc-modal-close" onclick="closeAdminModal()">&times;</span>
        <h3 id="alc-modal-date-label">Manage Time Slots</h3>
        <p class="alc-modal-date-subtitle" id="alc-modal-date-display"></p>
        <form method="POST" onsubmit="event.preventDefault(); saveAdminAvailability();">
            <input type="hidden" name="active_tab" class="alc-active-tab-input" value="tab-availability">
            <?php wp_nonce_field('alchemy_admin_nonce', 'alchemy_admin_nonce'); ?>
            <input type="hidden" name="target_date" id="alc-modal-date-input">

            <div id="slots-checkbox-list" class="modal-slots-container">
                <?php
                $slots_str = get_option('alchemy_hours', '09:00 AM, 11:00 AM, 01:30 PM, 03:30 PM');
                $all_slots = array_map('trim', explode(',', $slots_str));
                foreach ($all_slots as $s): ?>
                    <label class="alc-slot-pill">
                        <input type="checkbox" class="alc-time-slot-check" value="<?php echo esc_attr($s); ?>">
                        <?php echo esc_html($s); ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="modal-footer">
                <button type="button" class="button button-primary" onclick="saveAdminAvailability()">Save Slots</button>
            </div>        </form>
    </div>
</div>

<div id="alc-edit-service-modal" class="alchemy-admin-modal">
    <div class="alchemy-admin-modal-overlay" onclick="closeEditServiceModal()"></div>
    <div class="alchemy-admin-modal-content">
        <span class="alc-modal-close" onclick="closeEditServiceModal()">&times;</span>
        <h3>Edit Service</h3>
        <form method="POST" id="alc-edit-service-form">
            <input type="hidden" name="active_tab" class="alc-active-tab-input" value="tab-services">
            <?php wp_nonce_field('alchemy_admin_nonce', 'alchemy_admin_nonce'); ?>
            <input type="hidden" name="service_id" id="edit-svc-id">

            <label class="alc-modal-label">Service Name</label>
            <input type="text" name="title" id="edit-svc-title" class="alc-modal-input" required>

            <label class="alc-modal-label">Category</label>
            <select name="category" id="edit-svc-category" class="alc-modal-input" style="height:35px;">
                <option value="ZOOM/Facetime Readings">ZOOM/Facetime Readings</option>
                <option value="Phone Readings">Phone Readings</option>
                <option value="In-Person">In-Person</option>
                <option value="General">General</option>
            </select>

            <label class="alc-modal-label">Description</label>
            <textarea name="description" id="edit-svc-description" rows="4" class="alc-modal-input"></textarea>
            <div class="alc-modal-flex">
                <div class="alc-flex-1">
                    <label class="alc-modal-label">Price ($)</label>
                    <input type="number" step="0.01" name="price" id="edit-svc-price" class="alc-full-width" required>
                </div>
                <div class="alc-flex-1">
                    <label class="alc-modal-label">Duration (Mins)</label>
                    <input type="number" name="duration" id="edit-svc-duration" class="alc-full-width" required>
                </div>
            </div>
            <div class="modal-footer" style="display:flex; justify-content:space-between; margin-top:20px;">
                <button type="button" class="button" onclick="closeEditServiceModal()">Cancel</button>
                <input type="submit" name="update_service" class="button button-primary" value="Update Service">
            </div>
        </form>
    </div>
</div>

<div id="alc-desc-preview-modal" class="alchemy-admin-modal">
    <div class="alchemy-admin-modal-overlay" onclick="closeDescPreviewModal()"></div>
    <div class="alchemy-admin-modal-content">
        <span class="alc-modal-close" onclick="closeDescPreviewModal()">&times;</span>
        <h3 id="preview-desc-title">Service Details</h3>
        <div id="preview-desc-content"></div>
    </div>
</div>

<div class="alc-modal alc-admin-alert-modal" style="display:none; position:fixed; z-index:999999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); align-items:center; justify-content:center; backdrop-filter:blur(4px);">
    <div class="alchemy-admin-modal-content" style="text-align:center; position:relative;">
        <span class="alc-modal-close" onclick="closeAdminAlert()">&times;</span>
        <div style="font-size:40px; color:#d33; margin-bottom:15px;" class="alc-alert-icon">✕</div>
        <h3 id="admin-alert-title">Attention</h3>
        <p id="admin-alert-msg"></p>
        <button type="button" class="button button-primary" onclick="closeAdminAlert()" style="margin-top:20px;">OK</button>
    </div>
</div>

<script>
async function syncAdminAvailability() {
    try {
        const response = await fetch('<?php echo rest_url("alchemy/v1/availability-sync-admin"); ?>', {
            headers: {
                'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>'
            }
        });
        if (response.ok) {
            const data = await response.json();
            window.alchemyAdminData.existingAvail = data;
            if (window.calendar) window.calendar.refetchEvents();
            showAlchemyNotice('<strong>Success:</strong> Ledger Sync Complete. Calendar updated.', 'success');
        }
    } catch (e) {
        console.error('Sync failed', e);
    }
}
</script>
