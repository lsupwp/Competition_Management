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
     * Encode ID to obfuscated string (fixed length)
     */
    public static function encode(int $id): string
    {
        self::init();
        
        // Pad to fixed length (10 digits max)
        $idStr = str_pad((string)$id, 10, '0', STR_PAD_LEFT);
        
        // XOR encryption
        $encrypted = '';
        $keyLength = strlen(self::$key);
        
        for ($i = 0; $i < 10; $i++) {
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
        
        // Add padding back for base64
        $encoded = strtr($encoded, '-_', '+/');
        $padding = strlen($encoded) % 4;
        if ($padding) {
            $encoded .= str_repeat('=', 4 - $padding);
        }
        
        $encrypted = base64_decode($encoded);
        
        if ($encrypted === false || strlen($encrypted) !== 10) {
            return null;
        }
        
        // XOR decrypt
        $decrypted = '';
        $keyLength = strlen(self::$key);
        
        for ($i = 0; $i < 10; $i++) {
            $decrypted .= chr(ord($encrypted[$i]) ^ ord(self::$key[$i % $keyLength]));
        }
        
        // Validate it's all digits
        if (!ctype_digit($decrypted)) {
            return null;
        }
        
        // Remove leading zeros and convert to int
        return (int)ltrim($decrypted, '0') ?: 0;
    }
}
