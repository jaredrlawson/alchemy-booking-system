<?php
/**
 * Handles REST API Routes for Services, Intent Creation, 
 * Booking Saves, and Live Availability Sync.
 */

add_action('rest_api_init', function () {
    // Route to fetch all services
    register_rest_route('alchemy/v1', '/services', [
        'methods' => 'GET',
        'callback' => 'alchemy_api_get_services',
        'permission_callback' => '__return_true'
    ]);

    // Route to fetch latest availability (for the auto-refresh scroller)
    register_rest_route('alchemy/v1', '/availability', [
        'methods' => 'GET',
        'callback' => 'alchemy_api_get_availability',
        'permission_callback' => '__return_true'
    ]);

    // Route for Stripe Payment Intent
    register_rest_route('alchemy/v1', '/create-intent', [
        'methods' => 'POST',
        'callback' => 'alchemy_api_create_intent',
        'permission_callback' => function() {
            return true; // Public can initiate payment
        }
    ]);

    // Route to save the final booking
    register_rest_route('alchemy/v1', '/save-booking', [
        'methods' => 'POST',
        'callback' => 'alchemy_api_save_booking',
        'permission_callback' => function() {
            return true; // Public can save booking after payment
        }
    ]);

    // Admin-only route for syncing availability (used in dashboard)
    register_rest_route('alchemy/v1', '/availability-sync', [
        'methods' => 'GET',
        'callback' => 'alchemy_api_get_availability',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);
});

/**
 * Returns clean availability map for the public scroller
 */
function alchemy_api_get_availability() {
    // This calls the helper function defined in your main plugin file
    if (function_exists('alchemy_get_availability_map')) {
        return (object)alchemy_get_availability_map();
    }
    
    // Fallback logic if helper is missing
    global $wpdb;
    $table = $wpdb->prefix . 'alchemy_availability';
    $results = $wpdb->get_results("SELECT available_date, time_slots FROM $table WHERE is_available = 1");
    $map = [];
    foreach($results as $r) {
        $clean_date = date('Y-m-d', strtotime($r->available_date));
        $map[$clean_date] = array_map('trim', explode(',', $r->time_slots));
    }
    return (object)$map;
}

/**
 * Returns list of services
 */
function alchemy_api_get_services() {
    global $wpdb;
    $table = $wpdb->prefix . 'alchemy_services';
    return $wpdb->get_results("SELECT * FROM $table");
}

/**
 * Creates Stripe Payment Intent
 */
function alchemy_api_create_intent($request) {
    $params = $request->get_json_params();
    $amount = floatval($params['amount'] ?? 0);
    
    if ($amount <= 0) {
        return new WP_Error('invalid_amount', 'Amount must be greater than zero', ['status' => 400]);
    }

    $payments = new Alchemy_Payments();
    $intent = $payments->create_intent($amount);
    
    if (!$intent) {
        return new WP_Error('stripe_error', 'Could not create payment intent', ['status' => 500]);
    }

    return ['clientSecret' => $intent->client_secret];
}

/**
 * Saves booking to database after payment
 */
function alchemy_api_save_booking($request) {
    global $wpdb;
    $params = $request->get_json_params();
    
    // 1. Basic Validation
    if (empty($params['name']) || empty($params['email']) || empty($params['service_id']) || empty($params['payment_intent_id'])) {
        return new WP_Error('missing_data', 'Required fields are missing (including payment ID)', ['status' => 400]);
    }

    $service_id = intval($params['service_id']);
    $table_services = $wpdb->prefix . 'alchemy_services';
    $service = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$table_services` WHERE id = %d", $service_id));

    if (!$service) {
        return new WP_Error('invalid_service', 'The selected service does not exist', ['status' => 400]);
    }

    // 2. Stripe Verification (CRITICAL SECURITY PATCH)
    $payments = new Alchemy_Payments();
    $intent = $payments->retrieve_intent(sanitize_text_field($params['payment_intent_id']));

    if (!$intent || $intent->status !== 'succeeded') {
        error_log('Alchemy Security: Failed booking attempt. Payment not succeeded for Intent ID: ' . ($params['payment_intent_id'] ?? 'N/A'));
        return new WP_Error('payment_not_verified', 'Payment verification failed. Please contact support.', ['status' => 403]);
    }

    // Optional: Verify amount matches service price
    $amount_paid = $intent->amount / 100;
    if ($amount_paid < ($service->price * 0.9)) { // Allow 10% buffer for potential future discounts/taxes
         return new WP_Error('payment_mismatch', 'Payment amount mismatch.', ['status' => 403]);
    }
    
    $inserted = $wpdb->insert($wpdb->prefix . 'alchemy_bookings', [
        'customer_name'  => sanitize_text_field($params['name']),
        'customer_email' => sanitize_email($params['email']),
        'customer_phone' => sanitize_text_field($params['phone'] ?? ''),
        'service_id'     => $service_id,
        'booking_time'   => sanitize_text_field($params['date']),
        'notes'          => sanitize_textarea_field($params['notes'] ?? ''),
        'status'         => 'confirmed'
    ]);

    if ($inserted) {
        $booking_id = $wpdb->insert_id;
        
        // Trigger instant confirmation emails
        if (function_exists('alchemy_send_instant_confirmation')) {
            alchemy_send_instant_confirmation($booking_id);
        }
        
        return ['success' => true, 'booking_id' => $booking_id];
    }

    return new WP_Error('db_error', 'Failed to save booking', ['status' => 500]);
}