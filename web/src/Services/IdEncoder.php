<?php

namespace App\Services;

class IdEncoder
{
    private static $key;
    
    /**
     * Initialize with secret key from environment
     */
    private static function init(): void
    {
        if (self::$key === null) {
            self::$key = $_ENV['APP_KEY'] ?? 'default-secret-key-change-in-production';
        }
    }
    
    /**
     * Encode ID to obfuscated string
     */
    public static function encode(int $id): string
    {
        self::init();
        
        // Simple XOR encryption + base64
        $encrypted = '';
        $keyLength = strlen(self::$key);
        $idStr = (string)$id;
        
        for ($i = 0; $i < strlen($idStr); $i++) {
            $encrypted .= chr(ord($idStr[$i]) ^ ord(self::$key[$i % $keyLength]));
        }
        
        return rtrim(strtr(base64_encode($encrypted), '+/', '-_'), '=');
    }
    
    /**
     * Decode obfuscated string back to ID
     */
    public static function decode(string $encoded): ?int
    {
        self::init();
        
        // Add padding back
        $encoded = strtr($encoded, '-_', '+/');
        $padding = strlen($encoded) % 4;
        if ($padding) {
            $encoded .= str_repeat('=', 4 - $padding);
        }
        
        $encrypted = base64_decode($encoded);
        
        if ($encrypted === false) {
            return null;
        }
        
        // Decrypt
        $decrypted = '';
        $keyLength = strlen(self::$key);
        
        for ($i = 0; $i < strlen($encrypted); $i++) {
            $decrypted .= chr(ord($encrypted[$i]) ^ ord(self::$key[$i % $keyLength]));
        }
        
        // Validate it's a number
        if (!ctype_digit($decrypted)) {
            return null;
        }
        
        return (int)$decrypted;
    }
}
