<?php
// PayHere Payment Gateway Configuration (Sandbox Mode)

define('PAYHERE_MERCHANT_ID', '1238503');
define('PAYHERE_MERCHANT_SECRET', 'MTIwNzQ5NjY2MTE4NjE4OTAzNDYzNDk3MDE1MTIxNTYyOTY1MzA0');
define('PAYHERE_SANDBOX', true);
define('PAYHERE_URL', 'https://sandbox.payhere.lk/pay/checkout');
define('PAYHERE_CURRENCY', 'LKR');

/**
 * Generate PayHere md5 hash for checkout form
 */
function generate_payhere_hash($order_id, $amount, $currency = 'LKR') {
    $merchant_id = PAYHERE_MERCHANT_ID;
    $merchant_secret = PAYHERE_MERCHANT_SECRET;
    $formatted_amount = number_format((float)$amount, 2, '.', '');
    
    $secret_hash = strtoupper(md5($merchant_secret));
    $hash_string = $merchant_id . $order_id . $formatted_amount . $currency . $secret_hash;
    
    return strtoupper(md5($hash_string));
}

/**
 * Verify PayHere notify callback md5 hash
 */
function verify_payhere_hash($merchant_id, $order_id, $payhere_amount, $payhere_currency, $status_code, $received_md5sig) {
    $merchant_secret = PAYHERE_MERCHANT_SECRET;
    $secret_hash = strtoupper(md5($merchant_secret));
    
    $local_md5sig = strtoupper(md5(
        $merchant_id . 
        $order_id . 
        $payhere_amount . 
        $payhere_currency . 
        $status_code . 
        $secret_hash
    ));
    
    return ($local_md5sig === $received_md5sig);
}

/**
 * Get dynamic base URL for callbacks
 */
function get_site_base_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    $script_dir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $script_dir = str_replace('\\', '/', $script_dir);
    if ($script_dir === '/' || $script_dir === '.') {
        $script_dir = '';
    }
    
    return $protocol . "://" . $host . $script_dir;
}
