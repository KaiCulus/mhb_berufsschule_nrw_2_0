<?php
namespace Kai\MhbBackend20\Common;

class Cipher {
    private static string $method = 'aes-256-cbc';

    public static function encrypt(string $data, string $key): string {
        $ivSize = openssl_cipher_iv_length(self::$method);
        $iv = openssl_random_pseudo_bytes($ivSize);
        $encrypted = openssl_encrypt($data, self::$method, $key, 0, $iv);
        // Wir speichern IV und Ciphertext zusammen in einem Base64-String
        return base64_encode($iv . $encrypted);
    }

    public static function decrypt(string $data, string $key): string {
        $decoded = base64_decode($data);
        $ivSize = openssl_cipher_iv_length(self::$method);
        $iv = substr($decoded, 0, $ivSize);
        $encryptedText = substr($decoded, $ivSize);
        return openssl_decrypt($encryptedText, self::$method, $key, 0, $iv) ?: '';
    }
}