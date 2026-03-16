<?php
namespace Kai\MhbBackend20\Common;

class Cipher {
    private static string $method  = 'aes-256-gcm';
    private static int    $tagSize = 16; // GCM auth tag is always 16 bytes

    public static function encrypt(string $data, string $key): string {
        $derivedKey = hash('sha256', $key, true);
        $ivSize     = openssl_cipher_iv_length(self::$method);
        $iv         = openssl_random_pseudo_bytes($ivSize);
        $tag        = '';

        $encrypted = openssl_encrypt(
            $data, self::$method, $derivedKey, 0, $iv, $tag
        );

        // Layout: [ IV (12 bytes) | TAG (16 bytes) | CIPHERTEXT ]
        return base64_encode($iv . $tag . $encrypted);
    }

    public static function decrypt(string $data, string $key): string {
        $derivedKey = hash('sha256', $key, true); // must match encrypt()
        $decoded    = base64_decode($data);
        $ivSize     = openssl_cipher_iv_length(self::$method);

        // Extract each segment in the same order encrypt() wrote them
        $iv            = substr($decoded, 0, $ivSize);
        $tag           = substr($decoded, $ivSize, self::$tagSize);
        $encryptedText = substr($decoded, $ivSize + self::$tagSize);

        $result = openssl_decrypt(
            $encryptedText, self::$method, $derivedKey, 0, $iv, $tag
        );

        if ($result === false) {
            throw new \RuntimeException('Decryption failed — data may be corrupt or tampered with.');
        }

        return $result;
    }
}