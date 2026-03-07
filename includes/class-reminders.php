<?php
/**
 * Alchemy Reminders & Notifications
 * Handles automated cron reminders and instant confirmation emails.
 */

if (!defined('ABSPATH')) exit;

// 1. Setup Cron Schedule on plugin load
if (!wp_next_scheduled('alchemy_cron_hook')) {
    wp_schedule_event(time(), 'hourly', 'alchemy_cron_hook');
}

add_action('alchemy_cron_hook', 'alchemy_send_automated_reminders');

/**
 * Sends reminders for appointments happening tomorrow
 */
function alchemy_send_automated_reminders() {
    global $wpdb;
    
    // Calculate the target date (Tomorrow)
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $table = $wpdb->prefix . 'alchemy_bookings';
    
    // Security: Prepared statement to find confirmed bookings for tomorrow 
    // that haven't received a reminder yet.
    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `$table` 
         WHERE booking_time LIKE %s 
         AND status = 'confirmed' 
         AND reminder_sent = 0",
        '%' . $wpdb->esc_like($tomorrow) . '%'
    ));

    if (empty($bookings)) return;

    foreach ($bookings as $b) {
        $subject = "Reminder: Your Service with The Alchemist";
        
        // Prepare HTML Body
        $body = "
            <div style='font-family: sans-serif; line-height: 1.6; color: #333; max-width: 600px;'>
                <h2 style='color: #111;'>Hello " . esc_html($b->customer_name) . ",</h2>
                <p>This is a friendly reminder for your upcoming appointment scheduled for:</p>
                <p style='font-size: 18px; font-weight: bold; background: #f9f9f9; padding: 15px; border-left: 4px solid #111;'>
                    " . esc_html($b->booking_time) . "
                </p>
                <p>We look forward to seeing you soon! If you need to reschedule, please contact us as soon as possible.</p>
                <br>
                <p>Best regards,<br><strong>" . esc_html(get_bloginfo('name')) . "</strong></p>
            </div>";

        $sent = alchemy_send_html_mail($b->customer_email, $subject, $body);

        if ($sent) {
            // Mark as sent so they don't get spammed every hour
            $wpdb->update($table, ['reminder_sent' => 1], ['id' => $b->id]);
        }
    }
}

/**
 * Helper function for standardized HTML emails
 */
function alchemy_send_html_mail($to, $subject, $message) {
    $admin_email = get_option('admin_email');
    $site_name   = get_bloginfo('name');

    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $site_name . ' <' . $admin_email . '>',
        'Reply-To: ' . $admin_email
    );

    return wp_mail($to, $subject, $message, $headers);
}

/**
 * Triggered immediately after a successful payment/save
 */
function alchemy_send_instant_confirmation($booking_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'alchemy_bookings';
    $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$table` WHERE id = %d", $booking_id));

    if ($booking) {
        $subject = "Booking Confirmed: " . get_bloginfo('name');
        $body = "<h2>Your Booking is Confirmed!</h2>
                 <p>Hi " . esc_html($booking->customer_name) . ",</p>
                 <p>We've received your payment and your spot is secured for " . esc_html($booking->booking_time) . ".</p>";
        
        alchemy_send_html_mail($booking->customer_email, $subject, $body);
        
        // Also notify the admin
        alchemy_send_html_mail(get_option('admin_email'), "New Booking Alert", "New booking received from " . esc_html($booking->customer_name));
    }
}