<?php
// config/crypto.php

// 1. Load local secret key if config.local.php exists
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// 2. Fallback key for initial dev setup (avoids fatal errors if config.local.php is missing)
if (!defined('ENCRYPTION_KEY')) {
    define('ENCRYPTION_KEY', 'dev_default_key_change_in_prod_32ch'); 
}

/**
 * Encrypts an ID for safe use in URL parameters
 *
 * @param int|string $data The raw ID to encrypt
 * @return string|false Encrypted URL token or false on failure
 */
if (!function_exists('encrypt_id')) {
    function encrypt_id(int|string $data): string|false {
        if (empty($data)) return false;
        
        $cipher = 'aes-256-cbc';
        $ivLength = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivLength);
        
        $encrypted = openssl_encrypt((string)$data, $cipher, ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv);
        
        if ($encrypted === false) return false;

        // Output URL-safe base64 string
        return rtrim(strtr(base64_encode($iv . $encrypted), '+/', '-_'), '=');
    }
}

/**
 * Decrypts a URL token back into the original ID
 *
 * @param string $data Encrypted token string
 * @return string|false Decrypted ID string or false on failure
 */
if (!function_exists('decrypt_id')) {
    function decrypt_id(string $data): string|false {
        if (empty($data)) return false;

        $cipher = 'aes-256-cbc';
        $ivLength = openssl_cipher_iv_length($cipher);
        
        // Fix Base64 padding
        $data = strtr($data, '-_', '+/');
        $padding = strlen($data) % 4;
        if ($padding) {
            $data .= str_repeat('=', 4 - $padding);
        }
        
        $decoded = base64_decode($data, true);
        if ($decoded === false || strlen($decoded) <= $ivLength) {
            return false;
        }

        $iv = substr($decoded, 0, $ivLength);
        $ciphertext = substr($decoded, $ivLength);

        return openssl_decrypt($ciphertext, $cipher, ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv);
    }
}

/**
 * Helper function to generate clean encrypted URLs
 *
 * @param string $page Target PHP file (e.g., 'edit_division.php')
 * @param int|string $id Database ID to encrypt
 * @param string $paramName URL query parameter key
 * @return string Formatted URL string
 */
if (!function_exists('e_url')) {
    function e_url(string $page, int|string $id, string $paramName = 'token'): string {
        $encrypted = encrypt_id($id);
        return $page . '?' . $paramName . '=' . urlencode((string)$encrypted);
    }
}