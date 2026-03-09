<?php
/**
 * Plugin Name: The Abstract Alchemist Booking
 * Description: Precision scheduler with Admin Availability Calendar.
 * Version: 2.1.4-beta.1
 * Plugin URI: https://github.com/jaredrlawson/alchemy-booking-system
 * GitHub Plugin URI: https://github.com/jaredrlawson/alchemy-booking-system
 */

if (!defined('ABSPATH')) exit;

define('ALCHEMY_PATH', plugin_dir_path(__FILE__));
define('ALCHEMY_URL', plugin_dir_url(__FILE__));

require_once ALCHEMY_PATH . 'includes/class-database.php';
require_once ALCHEMY_PATH . 'includes/class-booking.php';
require_once ALCHEMY_PATH . 'includes/class-payments.php';
require_once ALCHEMY_PATH . 'includes/class-reminders.php';
require_once ALCHEMY_PATH . 'includes/class-admin-ajax.php';
require_once ALCHEMY_PATH . 'includes/class-github-updater.php';

new Alchemy_GitHub_Updater(__FILE__);

Alchemy_Admin_AJAX::init();
if (is_admin()) {
    Alchemy_Database::create_tables(); // Run migrations safely in admin only
}

register_activation_hook(__FILE__, ['Alchemy_Database', 'create_tables']);

/**
 * Returns clean availability map for the public scroller
 */
function alchemy_get_availability_map() {
    global $wpdb;
    $table_avail = $wpdb->prefix . 'alchemy_availability';
    $table_bookings = $wpdb->prefix . 'alchemy_bookings';
    
    // 1. Get base availability from admin ledger
    $results = $wpdb->get_results("SELECT available_date, time_slots FROM `$table_avail` WHERE is_available = 1");
    
    // 2. Get all confirmed bookings to subtract from availability
    $booked_results = $wpdb->get_results($wpdb->prepare(
        "SELECT booking_time FROM `$table_bookings` WHERE status = %s",
        'confirmed'
    ));
    $booked_times = array_column($booked_results, 'booking_time');

    $date_map = [];
    $current_timestamp = current_time('timestamp');
    $current_date = date('Y-m-d', $current_timestamp);

    foreach($results as $r) {
        if (!empty($r->time_slots)) {
            $iso_date = date('Y-m-d', strtotime($r->available_date));
            $all_slots = array_map('trim', explode(',', $r->time_slots));
            
            // Generate the label used by JS for the booking string comparison
            $timestamp = strtotime($r->available_date);
            $date_label = date('D, F j, Y', $timestamp); 

            $available_slots = [];
            foreach ($all_slots as $slot) {
                // 1. Check if already booked
                $full_booking_string = $date_label . ' ' . $slot;
                if (in_array($full_booking_string, $booked_times)) {
                    continue;
                }

                // 2. Check if time has passed (only for today)
                if ($iso_date === $current_date) {
                    $slot_time = strtotime($iso_date . ' ' . $slot);
                    if ($slot_time <= $current_timestamp) {
                        continue;
                    }
                }

                $available_slots[] = $slot;
            }

            if (!empty($available_slots)) {
                $date_map[$iso_date] = $available_slots;
            }
        }
    }
    return $date_map;
}

// Admin Scripts
add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'alchemy-dashboard') === false) return;
    
    wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js', [], '6.1.8');
    wp_enqueue_style('alchemy-admin-style', ALCHEMY_URL . 'admin/css/admin-style.css', [], time());
    wp_enqueue_script('alchemy-admin-script', ALCHEMY_URL . 'admin/js/admin-script.js', ['jquery', 'fullcalendar'], time(), true);

    wp_localize_script('alchemy-admin-script', 'alchemyAdminData', [
        'globalSlots' => array_map('trim', explode(',', get_option('alchemy_hours', '09:00 AM, 11:00 AM, 01:30 PM, 03:30 PM'))),
        'existingAvail' => (object)alchemy_get_availability_map()
    ]);
});

// Public Scripts
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('dashicons');
    $v = time(); 
    
    wp_enqueue_style('alchemy-public-style', ALCHEMY_URL . 'public/css/booking-wizard.css', [], $v);
    
    // Config Options
    $head_color    = get_option('alchemy_heading_color', '#111111');
    $border_color  = get_option('alchemy_border_color', '#cccccc');
    $border_opacity = intval(get_option('alchemy_border_opacity', '100')) / 100;
    $button_color  = get_option('alchemy_button_color', '#111111');
    $button_text   = get_option('alchemy_button_text_color', '#ffffff');
    $button_font_key = get_option('alchemy_button_font', 'inherit');
    $hover_color   = get_option('alchemy_button_hover_color', '#c5a000');
    $select_color  = get_option('alchemy_selected_day_color', '#c5a000');
    $use_shadow    = get_option('alchemy_card_shadow', '0');
    $shadow_int    = intval(get_option('alchemy_shadow_intensity', '8')) / 100;
    $use_theme     = get_option('alchemy_use_theme_styles', '0');
    $button_style  = get_option('alchemy_button_style', 'solid');
    $border_radius = get_option('alchemy_border_radius', '12');
    $inherit_font  = get_option('alchemy_inherit_font', '1');
    $sec_intensity = intval(get_option('alchemy_secondary_border_intensity', '100')) / 100;
    $rgba_black    = "rgba(0,0,0,$sec_intensity)";

    $font_map = [
        'montserrat' => "'Montserrat', sans-serif",
        'roboto'     => "'Roboto', sans-serif",
        'opensans'   => "'Open Sans', sans-serif",
        'lato'       => "'Lato', sans-serif",
        'playfair'   => "'Playfair Display', serif",
        'ebgaramond' => "'EB Garamond', serif",
        'system'     => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
        'georgia'    => "Georgia, serif",
        'arial'      => "Arial, sans-serif",
        'times'      => "'Times New Roman', serif"
    ];

    $final_font = ($inherit_font === '1') ? 'inherit' : (isset($font_map[$button_font_key]) ? $font_map[$button_font_key] : 'inherit');

    if ($inherit_font !== '1' && in_array($button_font_key, ['montserrat', 'roboto', 'opensans', 'lato', 'playfair', 'ebgaramond'])) {
        $g_font_key = str_replace('ebgaramond', 'EB+Garamond', str_replace('opensans', 'Open+Sans', str_replace('playfair', 'Playfair+Display', $button_font_key)));
        wp_enqueue_style('alchemy-google-font', "https://fonts.googleapis.com/css2?family=$g_font_key:wght@400;600;700;800&display=swap");
    }
    
    list($r, $g, $b) = sscanf($border_color, "#%02x%02x%02x");
    $rgba_border = "rgba($r, $g, $b, $border_opacity)";
    $shadow_css = ($use_shadow === '1') ? "box-shadow: 0 10px 30px rgba(0,0,0,$shadow_int) !important;" : "box-shadow: none !important;";

    $custom_css = "
        :root { 
            --alc-gold: $button_color; 
            --alc-hover: $hover_color;
            --alc-selected: $select_color;
            --alc-btn-text: $button_text;
            --alc-global-font: $final_font;
            --alc-head: $head_color;
            --alc-border: $rgba_border;
            --alc-radius: {$border_radius}px;
        }
        .alchemy-booking-wizard { opacity: 0; transition: opacity 0.3s ease; }
        .alchemy-booking-wizard.alc-revealed { opacity: 1 !important; }
        .alchemy-booking-wizard *:not(.dashicons), 
        .alchemy-booking-wizard input, .alchemy-booking-wizard textarea, .alchemy-booking-wizard select, .alchemy-booking-wizard button, .alchemy-booking-wizard label, .alchemy-booking-wizard span:not(.dashicons), .alchemy-booking-wizard p, .alchemy-booking-wizard h2, .alchemy-booking-wizard h3 { 
            font-family: var(--alc-global-font) !important; 
        }
        .alchemy-booking-wizard .alc-view-heading, .alchemy-booking-wizard .alc-category-heading, .alchemy-booking-wizard .alc-month-row h2, .alchemy-booking-wizard .alc-summary-card h3, .alchemy-booking-wizard .alc-summary-total, .alchemy-booking-wizard .alc-summary-total span, .alchemy-booking-wizard .card-price, .alchemy-booking-wizard .alc-available-times-head { 
            color: var(--alc-head) !important; 
        }
        .alchemy-booking-wizard input::placeholder, .alchemy-booking-wizard textarea::placeholder {
            color: #999 !important;
            opacity: 1;
        }
        .alchemy-booking-wizard .alc-category-heading { border-bottom: none !important; }
        .dashicons-calendar-alt { color: var(--alc-head) !important; }
        .alchemy-booking-wizard .date-day-item.selected { border-bottom-color: var(--alc-selected) !important; color: var(--alc-selected) !important; }
        .alchemy-booking-wizard .alc-payment-box { border: 1px solid #ddd !important; border-radius: 16px !important; padding: 20px !important; }
        .alchemy-booking-wizard .alchemy-service-card, .alchemy-booking-wizard .alc-form-container, .alchemy-booking-wizard .alc-step-container, .alchemy-booking-wizard .alc-summary-sidebar, .alchemy-booking-wizard .alc-summary-card, .alchemy-booking-wizard .btn-book, .alchemy-booking-wizard .btn-details, .alchemy-booking-wizard .alc-pill-btn, .alchemy-booking-wizard .time-slot-btn, .alchemy-booking-wizard .alc-btn-primary { border-radius: var(--alc-radius) !important; }
        .alchemy-booking-wizard .alc-form-field, .alchemy-booking-wizard .btn-details, .alchemy-booking-wizard .alc-pill-btn, .alchemy-booking-wizard .time-slot-btn { 
            border: 1px solid $rgba_black !important; color: #000 !important; background: transparent !important; font-weight: 700 !important; 
        }
        .alchemy-booking-wizard .alc-form-field, .alchemy-booking-wizard textarea { border-radius: 0px !important; font-weight: 400 !important; }
        .alchemy-booking-wizard .alchemy-service-card, .alchemy-booking-wizard .alc-form-container, .alchemy-booking-wizard .alc-step-container, .alchemy-booking-wizard .alc-summary-card { 
            border-color: var(--alc-border) !important; $shadow_css 
        }
        .alchemy-booking-wizard input { border-radius: 0px !important; }
        .btn-book, .btn-details, .time-slot-btn, .alc-pill-btn, .scroller-nav-btn, .alc-btn-primary, .alc-link-btn { transition: all 0.25s ease !important; }
        .btn-book, .alc-btn-primary { 
            background: var(--alc-gold) !important; color: var(--alc-btn-text) !important; border: none !important; padding: 15px 30px !important; font-weight: 700 !important; cursor: pointer !important; display: inline-block; font-size: 20px !important; 
        }
        .alc-btn-primary:hover, .btn-book:hover, .alc-link-btn:hover { background: var(--alc-hover) !important; color: var(--alc-btn-text) !important; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .alc-link-btn:hover { background: none !important; color: var(--alc-hover) !important; box-shadow: none !important; transform: none !important; }
        .btn-details { font-size: 20px !important; }
        .btn-details:hover, .alc-pill-btn:hover, .time-slot-btn:hover { border-color: var(--alc-hover) !important; color: var(--alc-hover) !important; background: transparent !important; }
        .scroller-nav-btn:hover { border-color: var(--alc-hover) !important; color: var(--alc-hover) !important; background: #fff !important; }
    ";
    
    if ($button_style === 'outline') {
        $custom_css .= ".btn-book, .alc-btn-primary { background: transparent !important; color: var(--alc-gold) !important; border: 2px solid var(--alc-gold) !important; } .btn-book:hover, .alc-btn-primary:hover { background: var(--alc-hover) !important; color: var(--alc-btn-text) !important; border-color: var(--alc-hover) !important; }";
    }
    
    if ($use_theme === '1') {
        $custom_css .= ".alchemy-booking-wizard .btn-book, .alchemy-booking-wizard .alc-btn-primary, .alchemy-booking-wizard .alchemy-service-card { opacity: 0; transition: opacity 0.4s ease !important; } .alchemy-booking-wizard.alc-revealed .btn-book, .alchemy-booking-wizard.alc-revealed .alc-btn-primary, .alchemy-booking-wizard.alc-revealed .alchemy-service-card { opacity: 1; } :root { --alc-gold: var(--e-global-color-primary, var(--wp--preset--color--primary, var(--ast-global-color-0, var(--primary-color, var(--accent-color, $button_color))))); } .btn-book, .alc-btn-primary { background-color: var(--alc-gold) !important; border: 1px solid var(--alc-gold) !important; }";
    }
    
    wp_add_inline_style('alchemy-public-style', $custom_css);

    wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, false);
    wp_enqueue_script('alchemy-booking-js', ALCHEMY_URL . 'public/js/booking-wizard.js', ['jquery', 'stripe-js'], $v, true);
    
    wp_localize_script('alchemy-booking-js', 'alchemyData', [
        'root' => esc_url_raw(rest_url('alchemy/v1/')),
        'nonce' => wp_create_nonce('wp_rest'),
        'stripePubKey' => get_option('alchemy_stripe_pub_key', ''),
        'availableDates' => (object)alchemy_get_availability_map(),
        'useTheme' => get_option('alchemy_use_theme_styles', '0'),
        'headingColor' => get_option('alchemy_heading_color', '#111111'),
        'borderColor' => get_option('alchemy_border_color', '#cccccc'),
        'buttonColor' => get_option('alchemy_button_color', '#111111'),
        'hoverColor' => get_option('alchemy_button_hover_color', '#c5a000'),
        'buttonText' => get_option('alchemy_button_text_color', '#ffffff'),
        'secondaryIntensity' => intval(get_option('alchemy_secondary_border_intensity', '100')),
        'borderRadius' => intval(get_option('alchemy_border_radius', '12')),
        'buttonFont' => $final_font,
        'inheritFont' => get_option('alchemy_inherit_font', '1'),
        'loaderStyle' => get_option('alchemy_loader_style', 'aura'),
        'selectedDayColor' => get_option('alchemy_selected_day_color', '#c5a000')
    ]);
});

add_action('admin_menu', function() {
    add_menu_page('Alchemy Ledger', 'Alchemy Bookings', 'manage_options', 'alchemy-dashboard', 'alchemy_render_dashboard', 'dashicons-media-spreadsheet', 26);
});

function alchemy_render_dashboard() {
    include ALCHEMY_PATH . 'admin/dashboard.php';
}

add_shortcode('alchemy_book', function() {
    ob_start();
    include ALCHEMY_PATH . 'public/partials/booking-form.php';
    return ob_get_clean();
});
