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

        $table_bookings     = "{$wpdb->prefix}alchemy_bookings";
        $table_services     = "{$wpdb->prefix}alchemy_services";
        $table_availability = "{$wpdb->prefix}alchemy_availability";

        $sql = [];

        /**
         * Bookings Table
         * Added 'reminder_sent' for the cron job logic.
         * Added 'payment_intent_id' for Stripe reconciliation.
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
            PRIMARY KEY  (id)
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