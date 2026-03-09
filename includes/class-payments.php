<?php
/**
 * Alchemy Payments Class
 * Handles Stripe PaymentIntent creation with error logging and metadata.
 */

if (!defined('ABSPATH')) exit;

class Alchemy_Payments {

    /**
     * Creates a Stripe Payment Intent
     * * @param float $amount The dollar amount (e.g., 50.00)
     * @param array $meta Additional info like customer name/email
     * @return object|false
     */
    public function create_intent($amount, $meta = []) {
        // 1. Validation
        $amount = floatval($amount);
        if ($amount <= 0) {
            error_log('Alchemy Security: Invalid payment amount: ' . $amount);
            return false;
        }

        // 2. Fetch Keys - Trimmed to prevent "copy-paste space" errors
        $secret_key = trim(get_option('alchemy_stripe_sec_key', ''));

        if (empty($secret_key)) {
            error_log('Alchemy Error: Stripe Secret Key is missing.');
            return false;
        }

        // 3. Load Stripe SDK
        $stripe_init_path = ALCHEMY_PATH . 'assets/stripe-php/init.php';
        if (file_exists($stripe_init_path)) {
            require_once $stripe_init_path;
        } else {
            error_log('Alchemy Error: Stripe SDK missing at ' . $stripe_init_path);
            return false;
        }

        // 4. Initialize
        \Stripe\Stripe::setApiKey($secret_key);

        try {
            $intent_params = [
                'amount'   => (int)round($amount * 100), // Convert to cents
                'currency' => 'usd',
                'automatic_payment_methods' => ['enabled' => true],
                'metadata' => array_merge([
                    'Source' => 'The Abstract Alchemist Booking',
                    'Customer' => sanitize_text_field($meta['name'] ?? 'Unknown'),
                    'Email' => sanitize_email($meta['email'] ?? 'N/A')
                ], $meta)
            ];

            $intent = \Stripe\PaymentIntent::create($intent_params);
            
            return $intent;

        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Log the specific Stripe error for debugging
            error_log('Alchemy Stripe API Error: ' . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log('Alchemy General Payment Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves a Stripe Payment Intent by ID
     * @param string $intent_id
     * @return object|false
     */
    public function retrieve_intent($intent_id) {
        $secret_key = trim(get_option('alchemy_stripe_sec_key', ''));
        if (empty($secret_key)) return false;

        $stripe_init_path = ALCHEMY_PATH . 'assets/stripe-php/init.php';
        if (file_exists($stripe_init_path)) {
            require_once $stripe_init_path;
        } else {
            return false;
        }

        \Stripe\Stripe::setApiKey($secret_key);

        try {
            return \Stripe\PaymentIntent::retrieve($intent_id);
        } catch (Exception $e) {
            error_log('Alchemy Stripe Retrieve Error: ' . $e->getMessage());
            return false;
        }
    }
}