<?php
/**
 * Encryption Helper for Sensitive Data
 * Uses AES-256-GCM for secure encryption of tokens and passwords
 */

/**
 * Get or generate encryption key
 * Stored in database settings for persistence
 */
function getEncryptionKey() {
    static $key = null;
    
    if ($key !== null) {
        return $key;
    }
    
    $db = initDatabase();
    $storedKey = getSetting($db, 'encryption_key');
    
    if (!$storedKey) {
        // Generate new encryption key (32 bytes for AES-256)
        $rawKey = random_bytes(32);
        $base64Key = base64_encode($rawKey);
        setSetting($db, 'encryption_key', $base64Key);
        $key = $rawKey;
        return $rawKey;
    } else {
        $decoded = base64_decode($storedKey, true);
        if ($decoded === false || strlen($decoded) !== 32) {
            // Invalid key, regenerate
            $rawKey = random_bytes(32);
            $base64Key = base64_encode($rawKey);
            setSetting($db, 'encryption_key', $base64Key);
            $key = $rawKey;
            return $rawKey;
        }
        $key = $decoded;
        return $decoded;
    }
}

/**
 * Encrypt sensitive data
 * 
 * @param string $data Data to encrypt
 * @return string|null Encrypted data in format: nonce:tag:ciphertext (base64 encoded)
 */
function encryptData($data) {
    if (empty($data)) {
        return null;
    }
    
    try {
        $key = getEncryptionKey();
        
        // Generate random nonce (12 bytes for GCM)
        $nonce = random_bytes(12);
        
        // Encrypt using AES-256-GCM
        $ciphertext = openssl_encrypt(
            $data,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );
        
        if ($ciphertext === false) {
            error_log("Encryption failed: " . openssl_error_string());
            return null;
        }
        
        // Combine nonce, tag, and ciphertext
        $encrypted = base64_encode($nonce . $tag . $ciphertext);
        
        return $encrypted;
        
    } catch (Exception $e) {
        error_log("Encryption error: " . $e->getMessage());
        return null;
    }
}

/**
 * Decrypt sensitive data
 * 
 * @param string $encryptedData Encrypted data from encryptData()
 * @return string|null Decrypted data or null on failure
 */
function decryptData($encryptedData) {
    if (empty($encryptedData)) {
        return null;
    }
    
    try {
        $key = getEncryptionKey();
        
        // Decode from base64
        $decoded = base64_decode($encryptedData);
        
        if ($decoded === false || strlen($decoded) < 28) {
            // Minimum: 12 bytes nonce + 16 bytes tag
            return null;
        }
        
        // Extract nonce (12 bytes), tag (16 bytes), and ciphertext
        $nonce = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        $ciphertext = substr($decoded, 28);
        
        // Decrypt using AES-256-GCM
        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );
        
        if ($plaintext === false) {
            error_log("Decryption failed: " . openssl_error_string());
            return null;
        }
        
        return $plaintext;
        
    } catch (Exception $e) {
        error_log("Decryption error: " . $e->getMessage());
        return null;
    }
}

/**
 * Encrypt GitHub token before storing
 * 
 * @param string $token GitHub personal access token
 * @return string|null Encrypted token
 */
function encryptGitHubToken($token) {
    return encryptData($token);
}

/**
 * Decrypt GitHub token for use
 * 
 * @param string $encryptedToken Encrypted token from database
 * @return string|null Decrypted token
 */
function decryptGitHubToken($encryptedToken) {
    return decryptData($encryptedToken);
}

/**
 * Securely hash passwords (for SFTP, etc.)
 * Uses bcrypt with automatic salt
 * 
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password against hash
 * 
 * @param string $password Plain text password
 * @param string $hash Hashed password from database
 * @return bool True if password matches
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Mask sensitive data for display
 * Shows only first and last few characters
 * 
 * @param string $data Sensitive data to mask
 * @param int $showChars Number of characters to show at start/end
 * @return string Masked data
 */
function maskSensitiveData($data, $showChars = 4) {
    if (empty($data)) {
        return '';
    }
    
    $length = strlen($data);
    
    if ($length <= $showChars * 2) {
        return str_repeat('*', $length);
    }
    
    $start = substr($data, 0, $showChars);
    $end = substr($data, -$showChars);
    $masked = $start . str_repeat('*', $length - ($showChars * 2)) . $end;
    
    return $masked;
}

/**
 * Sanitize GitHub repository URL for display
 * Removes any embedded tokens
 * 
 * @param string $url Repository URL
 * @return string Sanitized URL
 */
function sanitizeGitHubUrl($url) {
    // Remove any tokens from URL (format: https://TOKEN@github.com/...)
    $sanitized = preg_replace('/https:\/\/[^@]+@github\.com/', 'https://github.com', $url);
    return $sanitized;
}
