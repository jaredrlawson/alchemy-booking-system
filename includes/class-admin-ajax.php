<?php
/**
 * Alchemy Admin AJAX Handler
 * Manages services, availability, and ledger actions.
 */

if (!defined('ABSPATH')) exit;

class Alchemy_Admin_AJAX {

    public static function init() {
        $instance = new self();
        
        // Service Management
        add_action('wp_ajax_alchemy_save_service', [$instance, 'save_service']);
        add_action('wp_ajax_alchemy_delete_service', [$instance, 'delete_service']);
        
        // Availability Management
        add_action('wp_ajax_alchemy_update_availability', [$instance, 'update_availability']);
        add_action('wp_ajax_alchemy_bulk_availability', [$instance, 'bulk_availability']);
        
        // Booking Management
        add_action('wp_ajax_alchemy_cancel_booking', [$instance, 'cancel_booking']);
        add_action('wp_ajax_alchemy_bulk_cancel', [$instance, 'bulk_cancel_bookings']);
        add_action('wp_ajax_alchemy_bulk_delete', [$instance, 'bulk_delete_bookings']);
        add_action('wp_ajax_alchemy_delete_booking', [$instance, 'delete_booking']);
        add_action('wp_ajax_alchemy_get_table_page', [$instance, 'get_table_page']);
    }

    /**
     * Mark multiple bookings as cancelled (Admin)
     */
    public function bulk_cancel_bookings() {
        check_ajax_referer('alchemy_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access', 403);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'alchemy_bookings';
        
        // 2. Parse IDs
        $ids_raw = isset($_POST['ids']) ? sanitize_text_field($_POST['ids']) : '';
        $ids = !empty($ids_raw) ? array_map('intval', explode(',', $ids_raw)) : [];

        if (empty($ids)) {
            wp_send_json_error('No bookings selected.');
        }

        $success_count = 0;
        $now = current_time('mysql');
        foreach ($ids as $id) {
            $updated = $wpdb->update($table, 
                ['status' => 'cancelled', 'updated_at' => $now], 
                ['id' => $id]
            );
            if ($updated !== false) {
                $success_count++;
                if (function_exists('alchemy_send_cancellation_notice')) {
                    alchemy_send_cancellation_notice($id);
                }
            }
        }

    $response = ['msg' => "$success_count bookings marked as cancelled."];
    $response['stats'] = Alchemy_Database::get_ledger_stats();
    wp_send_json_success($response);
}

    /**
     * Delete multiple bookings permanently (Admin)
     */
    public function bulk_delete_bookings() {
        check_ajax_referer('alchemy_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized access', 403);

        global $wpdb;
        $table = $wpdb->prefix . 'alchemy_bookings';
        
        $ids_raw = isset($_POST['ids']) ? sanitize_text_field($_POST['ids']) : '';
        $ids = !empty($ids_raw) ? array_map('intval', explode(',', $ids_raw)) : [];

        if (empty($ids)) wp_send_json_error('No bookings selected.');

        foreach ($ids as $id) {
            $wpdb->delete($table, ['id' => $id]);
        }
        
        $response = ['msg' => 'Selected bookings deleted.'];
        $response['stats'] = Alchemy_Database::get_ledger_stats();
        wp_send_json_success($response);
    }

    /**
     * Mark a single booking as cancelled (Admin)
     */
    public function cancel_booking() {
        check_ajax_referer('alchemy_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized access', 403);

        global $wpdb;
        $table = $wpdb->prefix . 'alchemy_bookings';
        $id = intval($_POST['id']);
        $now = current_time('mysql');

        $updated = $wpdb->update($table, 
            ['status' => 'cancelled', 'updated_at' => $now], 
            ['id' => $id]
        );

        if ($updated !== false) {
            if (function_exists('alchemy_send_cancellation_notice')) {
                alchemy_send_cancellation_notice($id);
            }
            
            $response = ['msg' => 'Booking marked as cancelled.'];
            $response['stats'] = Alchemy_Database::get_ledger_stats();
            wp_send_json_success($response);
        }
        wp_send_json_error('Failed to update booking status.');
    }

    /**
     * Updates availability for a range of dates
     */
    public function bulk_availability() {
        check_ajax_referer('alchemy_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied.');

        global $wpdb;
        $table = $wpdb->prefix . 'alchemy_availability';

        $start = sanitize_text_field($_POST['start']);
        $end   = sanitize_text_field($_POST['end']);
        $slots = sanitize_text_field($_POST['slots']);

        if (empty($start) || empty($end)) wp_send_json_error('Invalid date range.');

        $begin = new DateTime($start);
        $finish = new DateTime($end);
        $finish->modify('+1 day'); 

        $interval = new DateInterval('P1D');
        $daterange = new DatePeriod($begin, $interval, $finish);

        foreach($daterange as $date){
            $date_str = $date->format("Y-m-d");
            $wpdb->query($wpdb->prepare(
                "REPLACE INTO `$table` (available_date, time_slots, is_available) VALUES (%s, %s, %d)",
                $date_str, $slots, !empty($slots) ? 1 : 0
            ));
        }
        wp_send_json_success('Bulk availability updated.');
    }

    /**
     * Updates or Toggles availability for a specific date
     */
    public function update_availability() {
        check_ajax_referer('alchemy_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied.');

        global $wpdb;
        $table = $wpdb->prefix . 'alchemy_availability';

        $date_str = sanitize_text_field($_POST['available_date']);
        $slots = sanitize_text_field($_POST['time_slots']);
        $is_avail = !empty($slots) ? 1 : 0;

        $updated = $wpdb->query($wpdb->prepare(
            "REPLACE INTO `$table` (available_date, time_slots, is_available) VALUES (%s, %s, %d)",
            $date_str, $slots, $is_avail
        ));

        if ($updated !== false) wp_send_json_success('Availability updated.');
        else wp_send_json_error('Database error.');
    }

    /**
     * Saves or Updates a service
     */
    public function save_service() {
        check_ajax_referer('alchemy_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

        global $wpdb;
        $table = $wpdb->prefix . 'alchemy_services';

        $data = [
            'title' => sanitize_text_field($_POST['title']),
            'category' => sanitize_text_field($_POST['category']),
            'description' => sanitize_textarea_field($_POST['description']),
            'price' => floatval($_POST['price']),
            'duration' => intval($_POST['duration'])
        ];

        if (!empty($_POST['service_id'])) {
            $wpdb->update($table, $data, ['id' => intval($_POST['service_id'])]);
            wp_send_json_success('Service updated.');
        } else {
            $wpdb->insert($table, $data);
            wp_send_json_success('Service created.');
        }
    }

    /**
     * Deletes a service
     */
    public function delete_service() {
        // Handled via GET currently in dashboard.php for simplicity, 
        // but adding placeholder for AJAX consistency if needed.
        wp_send_json_error('Not implemented via AJAX yet.');
    }

    /**
     * Deletes a booking from the ledger
     */
    public function delete_booking() {
        check_ajax_referer('alchemy_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized access', 403);

        global $wpdb;
        $table = $wpdb->prefix . 'alchemy_bookings';
        $id = intval($_POST['id']);
        $deleted = $wpdb->delete($table, ['id' => $id]);

        if ($deleted) {
            $response = ['msg' => 'Booking deleted.'];
            $response['stats'] = Alchemy_Database::get_ledger_stats();
            wp_send_json_success($response);
        }
        else wp_send_json_error('Delete failed.');
    }
}
