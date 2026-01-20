<?php
namespace Kai\MhbBackend20\Common;

class Cipher {
    private static string $method = 'aes-256-cbc';

    /**
     * Entschlüsselt die Daten, die wir als Base64(IV + Ciphertext) gespeichert haben.
     */
    public static function decrypt(string $data, string $key): string {
        $data = base64_decode($data);
        $ivSize = openssl_cipher_iv_length(self::$method);
        
        // IV und verschlüsselten Text trennen
        $iv = substr($data, 0, $ivSize);
        $encryptedText = substr($data, $ivSize);
        
        return openssl_decrypt($encryptedText, self::$method, $key, 0, $iv);
    }
}