<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;

class FieldEncryption
{
    protected string $key;

    public function __construct()
    {
        // In Produktion aus Secret Tresor laden
        $this->key = base64_decode(config('encryption.field_key'));
    }

    public function encrypt(string $value): string
    {
        $iv = random_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        $cipher = openssl_encrypt($value, 'AES-256-CBC', $this->key, 0, $iv);

        return base64_encode($iv . $cipher);
    }

    public function decrypt(string $payload): ?string
    {
        $data = base64_decode($payload);
        $ivLength = openssl_cipher_iv_length('AES-256-CBC');
        $iv = substr($data, 0, $ivLength);
        $cipher = substr($data, $ivLength);

        try {
            return openssl_decrypt($cipher, 'AES-256-CBC', $this->key, 0, $iv);
        } catch (DecryptException $e) {
            return null;
        }
    }
    
}
