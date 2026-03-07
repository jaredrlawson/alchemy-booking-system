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
        
        // Availability Management (Essential for the Calendar)
        add_action('wp_ajax_alchemy_update_availability', [$instance, 'update_availability']);
        
        // Booking Management (Essential for the Ledger)
        add_action('wp_ajax_alchemy_delete_booking', [$instance, 'delete_booking']);
    }

    /**
     * Updates or Toggles availability for a specific date
     */
    public function update_availability() {
        check_ajax_referer('alchemy_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'alchemy_availability';
        
        $date  = sanitize_text_field($_POST['date']);
        $slots = sanitize_text_field($_POST['slots']); // Expects comma separated string
        $active = intval($_POST['is_available']);

        // Use REPLACE INTO to handle the UNIQUE KEY on available_date automatically
        $result = $wpdb->query($wpdb->prepare(
            "REPLACE INTO `$table` (available_date, time_slots, is_available) VALUES (%s, %s, %d)",
            $date, $slots, $active
        ));

        if ($result !== false) {
            wp_send_json_success(['message' => 'Availability updated.']);
        } else {
            wp_send_json_error('Database error updating availability.');
        }
    }

    /**
     * Saves or updates a service
     */
    public function save_service() {
        check_ajax_referer('alchemy_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'alchemy_services';

        $id          = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $title       = sanitize_text_field($_POST['title']);
        $description = wp_kses_post($_POST['description']); 
        $price       = floatval($_POST['price']);
        $duration    = intval($_POST['duration']);

        $data = [
            'title'       => $title,
            'description' => $description,
            'price'       => $price,
            'duration'    => $duration
        ];

        if ($id > 0) {
            $updated = $wpdb->update($table, $data, ['id' => $id]);
            wp_send_json_success('Service updated.');
        } else {
            $inserted = $wpdb->insert($table, $data);
            wp_send_json_success('Service created.');
        }
    }

    /**
     * Deletes a service
     */
    public function delete_service() {
        check_ajax_referer('alchemy_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

        global $wpdb;
        $table = $wpdb->prefix . 'alchemy_services';
        $id = intval($_POST['id']);
        $wpdb->delete($table, ['id' => $id]);
        wp_send_json_success('Service removed.');
    }

    /**
     * Deletes a booking from the ledger
     */
    public function delete_booking() {
        check_ajax_referer('alchemy_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

        global $wpdb;
        $table = $wpdb->prefix . 'alchemy_bookings';
        $id = intval($_POST['id']);
        $deleted = $wpdb->delete($table, ['id' => $id]);

        if ($deleted) {
            wp_send_json_success('Booking deleted.');
        } else {
            wp_send_json_error('Delete failed.');
        }
    }
}