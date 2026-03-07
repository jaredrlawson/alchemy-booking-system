<?php
global $wpdb;
$table_avail = $wpdb->prefix . 'alchemy_availability';
$table_services = $wpdb->prefix . 'alchemy_services';
$table_bookings = $wpdb->prefix . 'alchemy_bookings';

$notices = []; 

// POST Handlers
if (isset($_POST['sync_db_tables'])) {
    if (!isset($_POST['alchemy_admin_nonce']) || !wp_verify_nonce($_POST['alchemy_admin_nonce'], 'alchemy_save_configs')) {
        wp_die('Security check failed.');
    }
    Alchemy_Database::create_tables();
    $notices[] = ['type' => 'success', 'msg' => "<strong>Database Synced:</strong> Table structures updated."];
}

if (isset($_POST['save_day_slots'])) {
    if (!isset($_POST['alchemy_admin_nonce']) || !wp_verify_nonce($_POST['alchemy_admin_nonce'], 'alchemy_save_slots')) {
        wp_die('Security check failed.');
    }
    
    $raw_date = sanitize_text_field($_POST['target_date']);
    $date = date('Y-m-d', strtotime($raw_date));
    
    $slots_array = isset($_POST['slots']) ? $_POST['slots'] : [];
    $slots_string = implode(',', array_map('sanitize_text_field', $slots_array));
    
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
    if (!isset($_POST['alchemy_admin_nonce']) || !wp_verify_nonce($_POST['alchemy_admin_nonce'], 'alchemy_save_service')) {
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
    if (!isset($_POST['alchemy_admin_nonce']) || !wp_verify_nonce($_POST['alchemy_admin_nonce'], 'alchemy_save_service')) {
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
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'alchemy_delete_service_' . $_GET['delete_service'])) {
        wp_die('Security check failed.');
    }

    $wpdb->delete($table_services, ['id' => intval($_GET['delete_service'])]);
    $notices[] = ['type' => 'warning', 'msg' => "Service deleted."];
}

if (isset($_POST['save_alchemy_configs'])) {
    if (!isset($_POST['alchemy_admin_nonce']) || !wp_verify_nonce($_POST['alchemy_admin_nonce'], 'alchemy_save_configs')) {
        wp_die('Security check failed.');
    }

    update_option('alchemy_heading_color', sanitize_hex_color($_POST['heading_color']));
    update_option('alchemy_border_color', sanitize_hex_color($_POST['border_color']));
    update_option('alchemy_border_opacity', intval($_POST['border_opacity']));
    update_option('alchemy_card_shadow', isset($_POST['card_shadow']) ? '1' : '0');
    update_option('alchemy_shadow_intensity', intval($_POST['shadow_intensity']));
    update_option('alchemy_button_color', sanitize_hex_color($_POST['button_color']));
    update_option('alchemy_button_hover_color', sanitize_hex_color($_POST['button_hover_color']));
    update_option('alchemy_button_text_color', sanitize_hex_color($_POST['button_text_color']));
    update_option('alchemy_secondary_border_intensity', intval($_POST['secondary_border_intensity']));
    update_option('alchemy_selected_day_color', sanitize_hex_color($_POST['selected_day_color']));
    update_option('alchemy_button_font', sanitize_text_field($_POST['button_font']));
    update_option('alchemy_inherit_font', isset($_POST['inherit_font']) ? '1' : '0');
    update_option('alchemy_use_theme_styles', isset($_POST['use_theme']) ? '1' : '0');
    update_option('alchemy_button_style', sanitize_text_field($_POST['button_style']));
    update_option('alchemy_border_radius', intval($_POST['border_radius']));
    update_option('alchemy_stripe_pub_key', sanitize_text_field($_POST['stripe_pub']));
    update_option('alchemy_stripe_sec_key', sanitize_text_field($_POST['stripe_sec']));
    update_option('alchemy_hours', sanitize_text_field($_POST['global_slots']));
    
    $notices[] = ['type' => 'success', 'msg' => "<strong>Settings Saved:</strong> Your configurations have been updated."];
}

// --- Pagination Logic ---
$svc_per_page = 5;
$book_per_page = 10;

$svc_page = isset($_GET['svc_p']) ? max(1, intval($_GET['svc_p'])) : 1;
$book_page = isset($_GET['book_p']) ? max(1, intval($_GET['book_p'])) : 1;

$svc_offset = ($svc_page - 1) * $svc_per_page;
$book_offset = ($book_page - 1) * $book_per_page;

$total_services = $wpdb->get_var("SELECT COUNT(*) FROM $table_services");
$total_bookings = $wpdb->get_var("SELECT COUNT(*) FROM $table_bookings");

$services = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_services LIMIT %d, %d", $svc_offset, $svc_per_page));
$bookings = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_bookings ORDER BY created_at DESC LIMIT %d, %d", $book_offset, $book_per_page));

// Financial Overview Math (All-time or period based - keeping as is but using totals)
$revenue_7_days = $wpdb->get_var($wpdb->prepare("
    SELECT SUM(s.price)
    FROM $table_bookings b
    JOIN $table_services s ON b.service_id = s.id
    WHERE b.status = %s
    AND b.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
", 'confirmed')) ?: 0;

$revenue_30_days = $wpdb->get_var($wpdb->prepare("
    SELECT SUM(s.price)
    FROM $table_bookings b
    JOIN $table_services s ON b.service_id = s.id
    WHERE b.status = %s
    AND b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
", 'confirmed')) ?: 0;
// Recent Activity Feed (Last 5 events)
$recent_activity = $wpdb->get_results("
    SELECT b.customer_name, s.title, b.created_at, b.status
    FROM $table_bookings b
    JOIN $table_services s ON b.service_id = s.id
    ORDER BY b.created_at DESC
    LIMIT 5
");

// Check current key status for the UI
$stored_pub = get_option('alchemy_stripe_pub_key', '');
$stored_sec = get_option('alchemy_stripe_sec_key', '');

// Tab Memory Logic (Auto-switch if pagination is clicked)
$active_tab = isset($_POST['active_tab']) ? sanitize_text_field($_POST['active_tab']) : 'tab-availability';
if (isset($_GET['svc_p'])) $active_tab = 'tab-services';
if (isset($_GET['book_p'])) $active_tab = 'tab-bookings';
?>

<div class="wrap alchemy-admin-wrap">
    <div class="alchemy-header-flex">
        <h1>Alchemy Dashboard</h1>
        <button type="button" class="button alc-sync-btn" onclick="syncAdminAvailability()">Sync Ledger Data</button>
    </div>

    <?php foreach ($notices as $notice): ?>
        <div class="alc-notice alc-notice-<?php echo esc_attr($notice['type']); ?>">
            <p><?php echo wp_kses_post($notice['msg']); ?></p>
        </div>
    <?php endforeach; ?>

    <div class="alchemy-dashboard-layout">
        <div class="alchemy-main-content">
            <h2 class="nav-tab-wrapper">
                <a href="#tab-availability" class="nav-tab <?php echo $active_tab === 'tab-availability' ? 'nav-tab-active' : ''; ?>" data-tab="tab-availability">Availability</a>
                <a href="#tab-services" class="nav-tab <?php echo $active_tab === 'tab-services' ? 'nav-tab-active' : ''; ?>" data-tab="tab-services">Services</a>
                <a href="#tab-bookings" class="nav-tab <?php echo $active_tab === 'tab-bookings' ? 'nav-tab-active' : ''; ?>" data-tab="tab-bookings">Bookings</a>
                <a href="#tab-settings" class="nav-tab <?php echo $active_tab === 'tab-settings' ? 'nav-tab-active' : ''; ?>" data-tab="tab-settings">Settings</a>
            </h2>

            <div class="alchemy-tabs-container">
                <div id="tab-availability" class="alchemy-tab-content <?php echo $active_tab === 'tab-availability' ? 'active-content' : ''; ?>">
                    <div class="postbox"><div class="inside">
                        <p class="description">Click a date on the calendar to open the Service Slot manager.</p>
                        <div id="alchemy-calendar"></div>
                    </div></div>
                </div>

                <div id="tab-services" class="alchemy-tab-content <?php echo $active_tab === 'tab-services' ? 'active-content' : ''; ?>">
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
                                    'add_args'  => ['book_p' => $book_page] // Preserve other pagination
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
                                    <?php wp_nonce_field('alchemy_save_service', 'alchemy_admin_nonce'); ?>
                                    
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

                            <!-- Right: Live Preview -->
                            <div class="alc-editor-preview">
                                <span class="alc-preview-label">Live Preview (Mobile/Public)</span>
                                <div class="alc-admin-preview-card">
                                    <div class="card-header-row">
                                        <h3 id="preview-card-title">Service Name</h3>
                                        <span class="card-price" id="preview-card-price">$0</span>
                                    </div>
                                    <div class="card-duration" id="preview-card-duration">0 mins</div>
                                    <div class="card-description" id="preview-card-desc">Your service description will appear here...</div>
                                    <div class="card-footer">
                                        <button type="button" class="btn-details">Details</button>
                                        <button type="button" class="btn-book">Book Now</button>
                                    </div>
                                </div>
                                <p class="description" style="margin-top:15px;">Your primary color will apply automatically on the front-end.</p>
                            </div>
                        </div>
                    </div></div>
                </div>

                <div id="tab-bookings" class="alchemy-tab-content <?php echo $active_tab === 'tab-bookings' ? 'active-content' : ''; ?>">
                    <div class="postbox"><div class="inside">
                        <h3>Active Ledger</h3>
                        <table class="wp-list-table widefat fixed striped">
                            <thead><tr><th>Customer</th><th>Service</th><th>Time</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach($bookings as $b): ?>
                                <tr>
                                    <td><?php echo esc_html($b->customer_name); ?><br><small><?php echo esc_html($b->customer_email); ?></small></td>
                                    <td>
                                        <?php 
                                            // Get service title for this booking (we need to fetch all services for this to work correctly across pages)
                                            $all_svcs = $wpdb->get_results("SELECT id, title FROM $table_services");
                                            $svc = array_filter($all_svcs, function($s) use ($b) { return $s->id == $b->service_id; });
                                            $svc = reset($svc);
                                            echo $svc ? esc_html($svc->title) : 'Unknown Service';
                                        ?>
                                    </td>
                                    <td><?php echo esc_html($b->booking_time); ?></td>
                                    <td><span class="badge"><?php echo esc_html($b->status); ?></span></td>
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
                                    'add_args'  => ['svc_p' => $svc_page] // Preserve other pagination
                                ]); ?>
                            </div></div>
                        <?php endif; ?>
                    </div></div>
                </div>

                <div id="tab-settings" class="alchemy-tab-content <?php echo $active_tab === 'tab-settings' ? 'active-content' : ''; ?>">
                    <div class="postbox"><div class="inside">
                        <h3>Plugin Configurations</h3>
                        <form method="POST">
                            <input type="hidden" name="active_tab" class="alc-active-tab-input" value="tab-settings">
                            <?php wp_nonce_field('alchemy_save_configs', 'alchemy_admin_nonce'); ?>
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
                                            <input type="color" class="alc-color-swatch" value="<?php echo esc_attr(get_option('alchemy_heading_color', '#111111')); ?>">
                                            <input type="text" name="heading_color" class="alc-color-hex" value="<?php echo esc_attr(get_option('alchemy_heading_color', '#111111')); ?>">
                                        </div>
                                        <p class="description">Color for 'Step' headings and Category titles.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Border Color & Strength</th>
                                    <td>
                                        <div class="alc-color-group" style="margin-bottom:10px;">
                                            <input type="color" class="alc-color-swatch" value="<?php echo esc_attr(get_option('alchemy_border_color', '#cccccc')); ?>">
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
                                        <select name="button_style" class="alc-full-width" style="max-width: 250px;">
                                            <option value="solid" <?php selected(get_option('alchemy_button_style', 'solid'), 'solid'); ?>>Solid Color (Default)</option>
                                            <option value="outline" <?php selected(get_option('alchemy_button_style', 'solid'), 'outline'); ?>>Outline / Minimal</option>
                                            <option value="theme" <?php selected(get_option('alchemy_button_style', 'solid'), 'theme'); ?>>Full Theme Button Match</option>
                                        </select>
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
                                            <input type="color" class="alc-color-swatch" value="<?php echo esc_attr(get_option('alchemy_button_color', '#111111')); ?>">
                                            <input type="text" name="button_color" class="alc-color-hex" value="<?php echo esc_attr(get_option('alchemy_button_color', '#111111')); ?>">
                                        </div>
                                        <p class="description">Background color for primary buttons.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Button Hover Color</th>
                                    <td>
                                        <div class="alc-color-group">
                                            <input type="color" class="alc-color-swatch" value="<?php echo esc_attr(get_option('alchemy_button_hover_color', '#c5a000')); ?>">
                                            <input type="text" name="button_hover_color" class="alc-color-hex" value="<?php echo esc_attr(get_option('alchemy_button_hover_color', '#c5a000')); ?>">
                                        </div>
                                        <p class="description">Color when hovering over buttons or selecting time slots.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Button Text Color</th>
                                    <td>
                                        <div class="alc-color-group">
                                            <input type="color" class="alc-color-swatch" value="<?php echo esc_attr(get_option('alchemy_button_text_color', '#ffffff')); ?>">
                                            <input type="text" name="button_text_color" class="alc-color-hex" value="<?php echo esc_attr(get_option('alchemy_button_text_color', '#ffffff')); ?>">
                                        </div>
                                        <p class="description">Text color for primary buttons.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Selected Day Color</th>
                                    <td>
                                        <div class="alc-color-group">
                                            <input type="color" class="alc-color-swatch" value="<?php echo esc_attr(get_option('alchemy_selected_day_color', '#c5a000')); ?>">
                                            <input type="text" name="selected_day_color" class="alc-color-hex" value="<?php echo esc_attr(get_option('alchemy_selected_day_color', '#c5a000')); ?>">
                                        </div>
                                        <p class="description">Color for the active day in the date scroller.</p>
                                    </td>
                                </tr>
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
                                <tr>
                                    <th>Global Service Slots</th>
                                    <td>
                                        <input type="text" name="global_slots" value="<?php echo esc_attr(get_option('alchemy_hours', '09:00 AM, 11:00 AM, 01:30 PM, 03:30 PM')); ?>">
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
                            <?php wp_nonce_field('alchemy_save_configs', 'alchemy_admin_nonce'); ?>
                            <input type="submit" name="sync_db_tables" class="button" value="Sync Database Structures">
                        </form>
                    </div></div>
                </div>
            </div>
        </div>

        <div class="alchemy-sidebar">
            <div class="postbox alchemy-ledger-box">
                <h2 class="hndle">Ledger Overview</h2>
                <div class="inside">
                    <div class="alc-stat-row">
                        <span>Total Bookings</span>
                        <strong><?php echo esc_html($total_bookings); ?></strong>
                    </div>
                    <div class="alc-stat-row">
                        <span>Active Services</span>
                        <strong><?php echo esc_html($total_services); ?></strong>
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
                        <p class="description">No recent activity found.</p>
                    <?php else: ?>
                        <ul class="alc-activity-list">
                            <?php foreach ($recent_activity as $act): ?>
                                <li>
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                    <div class="alc-act-content">
                                        <strong><?php echo esc_html($act->customer_name); ?></strong> booked <em><?php echo esc_html($act->title); ?></em>
                                        <small><?php echo human_time_diff(strtotime($act->created_at), current_time('timestamp')); ?> ago</small>
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
        <h3 id="modal-date-label">Manage Slots</h3>
        <form method="POST">
            <input type="hidden" name="active_tab" class="alc-active-tab-input" value="tab-availability">
            <?php wp_nonce_field('alchemy_save_slots', 'alchemy_admin_nonce'); ?>
            <input type="hidden" name="target_date" id="target_date_input">
            <div id="slots-checkbox-list" class="modal-slots-container"></div>
            <div class="modal-footer">
                <button type="button" class="button" onclick="closeAdminModal()">Cancel</button>
                <input type="submit" name="save_day_slots" class="button button-primary" value="Save Slots">
            </div>
        </form>
    </div>
</div>

<div id="alc-edit-service-modal" class="alchemy-admin-modal">
    <div class="alchemy-admin-modal-overlay" onclick="closeEditServiceModal()"></div>
    <div class="alchemy-admin-modal-content">
        <h3>Edit Service</h3>
        <form method="POST">
            <input type="hidden" name="active_tab" class="alc-active-tab-input" value="tab-services">
            <?php wp_nonce_field('alchemy_save_service', 'alchemy_admin_nonce'); ?>
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
            <div class="modal-footer">
                <button type="button" class="button" onclick="closeEditServiceModal()">Cancel</button>
                <input type="submit" name="update_service" class="button button-primary" value="Update Service">
            </div>
        </form>
    </div>
</div>

<script>
async function syncAdminAvailability() {
    try {
        const response = await fetch('<?php echo rest_url("alchemy/v1/availability-sync"); ?>', {
            headers: {
                'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>'
            }
        });
        if (response.ok) {
            const data = await response.json();
            window.alchemyAdminData.existingAvail = data;
            if (window.calendar) window.calendar.refetchEvents();
            alert('Ledger Sync Complete: Calendar updated.');
        }
    } catch (e) {
        console.error('Sync failed', e);
    }
}
</script>