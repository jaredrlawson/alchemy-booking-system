<?php
/**
 * Alchemy Database Class
 * Handles table creation, schema migrations, and data integrity.
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class Alchemy_Database {

    /**
     * Creates or updates the plugin database tables.
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset = $wpdb->get_charset_collate();

        // Generate unique salts for security tokens on first install
        if (!get_option('alchemy_cancel_salt')) {
            update_option('alchemy_cancel_salt', wp_generate_password(32, true, true));
        }
        if (!get_option('alchemy_booking_id')) {
            update_option('alchemy_booking_id', wp_generate_password(12, true, true));
        }

        $table_bookings     = "{$wpdb->prefix}alchemy_bookings";
        $table_services     = "{$wpdb->prefix}alchemy_services";
        $table_availability = "{$wpdb->prefix}alchemy_availability";

        $sql = [];

        /**
         * Bookings Table
         * Added 'reminder_sent' for the cron job logic.
         * Added 'payment_intent_id' for Stripe reconciliation.
         * Added UNIQUE KEY on payment_intent_id to prevent database-level duplication.
         */
        $sql[] = "CREATE TABLE $table_bookings (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            customer_name varchar(255) NOT NULL,
            customer_email varchar(255) NOT NULL,
            customer_phone varchar(50) DEFAULT '',
            service_id int(11) NOT NULL,
            booking_time varchar(255) NOT NULL,
            notes text,
            status varchar(50) DEFAULT 'confirmed',
            payment_intent_id varchar(255) DEFAULT '',
            reminder_sent tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY payment_intent_id (payment_intent_id)
        ) $charset;";

        // Services Table
        $sql[] = "CREATE TABLE $table_services (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            category varchar(100) DEFAULT 'General',
            description text,
            duration int(11) NOT NULL,
            price decimal(10,2) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset;";

        // Availability Table
        $sql[] = "CREATE TABLE $table_availability (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            available_date date NOT NULL,
            time_slots text NOT NULL,
            is_available tinyint(1) DEFAULT 1,
            PRIMARY KEY  (id),
            UNIQUE KEY available_date (available_date)
        ) $charset;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach($sql as $s) {
            dbDelta($s);
        }

        // Run manual migrations for existing installations
        self::run_migrations();
    }

    /**
     * Manual migrations for existing tables that dbDelta might miss.
     */
    public static function run_migrations() {
        global $wpdb;
        $table_bookings = "{$wpdb->prefix}alchemy_bookings";
        $table_services = "{$wpdb->prefix}alchemy_services";

        // 1. Ensure 'description' exists in services
        self::ensure_column_exists($table_services, 'description', 'text AFTER `title`');
        self::ensure_column_exists($table_services, 'category', "varchar(100) DEFAULT 'General' AFTER `title` ");

        // 2. Ensure 'reminder_sent' exists in bookings for the cron job
        self::ensure_column_exists($table_bookings, 'reminder_sent', "tinyint(1) DEFAULT 0 AFTER `status` ");

        // 3. Ensure 'payment_intent_id' exists for Stripe tracking
        self::ensure_column_exists($table_bookings, 'payment_intent_id', "varchar(255) DEFAULT '' AFTER `status` ");

        // Add UNIQUE constraint manually if dbDelta missed it
        $has_index = $wpdb->get_var($wpdb->prepare(
            "SHOW INDEX FROM `$table_bookings` WHERE Key_name = %s",
            'payment_intent_id'
        ));
        if (!$has_index) {
            // Remove duplicates before applying unique key to prevent failure
            // Using a simple query that doesn't rely on complex joins for maximum compatibility
            $wpdb->query("DELETE FROM `$table_bookings` WHERE id NOT IN (SELECT max_id FROM (SELECT MAX(id) as max_id FROM `$table_bookings` GROUP BY payment_intent_id) as t) AND payment_intent_id != ''");
            $wpdb->query("ALTER TABLE `$table_bookings` ADD UNIQUE KEY `payment_intent_id` (`payment_intent_id`)");
        }

        // 4. Ensure 'updated_at' exists
        self::ensure_column_exists($table_bookings, 'updated_at', "datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at` ");
    }

    /**
     * Get current ledger statistics (bookings, revenue, etc)
     */
    public static function get_ledger_stats() {
        global $wpdb;
        $table_bookings = "{$wpdb->prefix}alchemy_bookings";
        $table_services = "{$wpdb->prefix}alchemy_services";

        $stats = [];
        $confirmed_status = 'confirmed';
        $stats['total_bookings'] = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_bookings WHERE status = %s", $confirmed_status)) ?: 0;
        $stats['total_services'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_services") ?: 0;
        
        $stats['revenue_7_days'] = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(s.price)
            FROM $table_bookings b
            JOIN $table_services s ON b.service_id = s.id
            WHERE b.status = %s
            AND b.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ", $confirmed_status)) ?: 0;

        $stats['revenue_30_days'] = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(s.price)
            FROM $table_bookings b
            JOIN $table_services s ON b.service_id = s.id
            WHERE b.status = %s
            AND b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ", $confirmed_status)) ?: 0;

        return $stats;
    }

    /**
     * Helper to add a column if it doesn't exist.
     */
    private static function ensure_column_exists($table_name, $column_name, $definition) {
        global $wpdb;

        // Table and Column names cannot be passed as %s in prepare() for the query itself, 
        // but we can prepare the SELECT from INFORMATION_SCHEMA.
        $check = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s 
             AND TABLE_NAME = %s 
             AND COLUMN_NAME = %s", 
            DB_NAME,
            $table_name,
            $column_name
        ));

        if (empty($check)) {
            // Strictly sanitize identifiers
            $safe_table = preg_replace('/[^a-zA-Z0-9_]/', '', $table_name);
            $safe_column = preg_replace('/[^a-zA-Z0-9_]/', '', $column_name);
            
            // Note: $definition contains SQL keywords like 'text AFTER `title`'.
            // Since this is internal plugin logic, we trust the $definition but wrap identifiers.
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN `$safe_column` $definition");
        }
    }
}