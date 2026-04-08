<?php

namespace Kai\MhbBackend20\Common;

/**
 * Cipher
 *
 * Stellt symmetrische Verschlüsselung und Entschlüsselung für sensible Daten bereit.
 *
 * Algorithmus: AES-256-GCM
 *   - GCM (Galois/Counter Mode) ist authenticated encryption:
 *     Der Auth-Tag stellt sicher, dass verschlüsselte Daten nicht unbemerkt
 *     manipuliert werden können (schützt vor Padding-Oracle und Bit-Flip-Angriffen).
 *   - Gegenüber dem früheren AES-256-CBC bietet GCM integrierten Manipulationsschutz
 *     ohne separaten HMAC.
 *
 * Gespeichertes Format (Base64-kodiert):
 *   [ IV (12 Bytes) | Auth-Tag (16 Bytes) | Ciphertext (variabel) ]
 *
 * Schlüssel-Ableitung:
 *   Der Rohschlüssel aus der .env wird via SHA-256 auf exakt 32 Bytes gebracht,
 *   damit OpenSSL ihn korrekt als AES-256-Schlüssel interpretiert — unabhängig
 *   von der Länge des konfigurierten Strings.
 */
class Cipher
{
    private static string $method  = 'aes-256-gcm';
    private static int    $tagSize = 16; // GCM Auth-Tag ist immer 16 Bytes

    /**
     * Verschlüsselt einen String mit AES-256-GCM.
     *
     * Jeder Aufruf generiert einen neuen zufälligen IV — gleiche Eingaben
     * erzeugen deshalb unterschiedliche Ciphertexte (semantische Sicherheit).
     *
     * @param string $data Klartextdaten
     * @param string $key  Rohschlüssel aus der .env (beliebige Länge)
     * @return string Base64-kodierter String: IV + Auth-Tag + Ciphertext
     * @throws \RuntimeException Bei OpenSSL-Fehler
     */
    public static function encrypt(string $data, string $key): string
    {
        // Schlüssel auf 32 Bytes ableiten (AES-256 benötigt exakt 32 Bytes)
        $derivedKey = hash('sha256', $key, true);
        $ivSize     = openssl_cipher_iv_length(self::$method);
        $iv         = openssl_random_pseudo_bytes($ivSize);
        $tag        = '';

        $encrypted = openssl_encrypt($data, self::$method, $derivedKey, 0, $iv, $tag);

        if ($encrypted === false) {
            throw new \RuntimeException('Verschlüsselung fehlgeschlagen.');
        }

        // IV, Auth-Tag und Ciphertext zusammen speichern — alles wird zum Entschlüsseln benötigt
        return base64_encode($iv . $tag . $encrypted);
    }

    /**
     * Entschlüsselt einen mit encrypt() erzeugten String.
     *
     * Der Auth-Tag wird automatisch geprüft — schlägt die Prüfung fehl
     * (Daten manipuliert oder falscher Schlüssel), wird eine Exception geworfen.
     *
     * @param string $data Base64-kodierter Ciphertext (Ausgabe von encrypt())
     * @param string $key  Derselbe Rohschlüssel der bei encrypt() verwendet wurde
     * @return string Entschlüsselter Klartext
     * @throws \RuntimeException Wenn Entschlüsselung oder Auth-Tag-Prüfung fehlschlägt
     */
    public static function decrypt(string $data, string $key): string
    {
        // Schlüssel identisch zu encrypt() ableiten
        $derivedKey = hash('sha256', $key, true);
        $decoded    = base64_decode($data);
        $ivSize     = openssl_cipher_iv_length(self::$method);

        // Format wiederherstellen: [ IV | Tag | Ciphertext ]
        $iv            = substr($decoded, 0, $ivSize);
        $tag           = substr($decoded, $ivSize, self::$tagSize);
        $encryptedText = substr($decoded, $ivSize + self::$tagSize);

        $result = openssl_decrypt($encryptedText, self::$method, $derivedKey, 0, $iv, $tag);

        if ($result === false) {
            // false bedeutet: entweder falscher Schlüssel oder Daten wurden manipuliert
            throw new \RuntimeException('Entschlüsselung fehlgeschlagen — Daten möglicherweise beschädigt oder manipuliert.');
        }

        return $result;
    }
}