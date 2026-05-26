<?php

declare(strict_types=1);

namespace Domain\Auth\Services;

/**
 * Serviço utilitário para geração e validação de códigos TOTP (RFC 6238).
 */
final class AuthTotpService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Gera uma secret Base32 segura para uso em apps autenticadores.
     */
    public function generateSecret(int $length = 32): string
    {
        $bytes = random_bytes((int) ceil($length * 5 / 8));
        $secret = $this->base32Encode($bytes);

        return substr($secret, 0, $length);
    }

    /**
     * Gera a URI otpauth padrão para configuração de autenticadores.
     */
    public function generateOtpAuthUrl(string $email, string $secret, string $issuer = 'Agentflix'): string
    {
        $encodedIssuer = rawurlencode($issuer);
        $label = rawurlencode($email);

        return "otpauth://totp/{$encodedIssuer}:{$label}?secret={$secret}&issuer={$encodedIssuer}";
    }

    /**
     * Valida um código TOTP com janela de tolerância de tempo.
     */
    public function verify(string $secret, string $code, int $window = 1, int $period = 30, int $digits = 6): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $timeSlice = (int) floor(time() / $period);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->generateCode($secret, $timeSlice + $offset, $digits), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gera o código atual para uma secret (útil para testes automatizados).
     */
    public function currentCode(string $secret, int $digits = 6): string
    {
        $timeSlice = (int) floor(time() / 30);

        return $this->generateCode($secret, $timeSlice, $digits);
    }

    /** Gera o codigo OTP para um time-slice especifico usando HMAC-SHA1. */
    private function generateCode(string $secret, int $timeSlice, int $digits): string
    {
        $binarySecret = $this->base32Decode($secret);
        if ($binarySecret === '') {
            return str_repeat('0', $digits);
        }

        $counter = pack('N*', 0, $timeSlice);
        $hash = hash_hmac('sha1', $counter, $binarySecret, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $segment = substr($hash, $offset, 4);

        $value = unpack('N', $segment);
        if ($value === false) {
            return str_repeat('0', $digits);
        }

        $truncated = $value[1] & 0x7FFFFFFF;
        $otp = (string) ($truncated % (10 ** $digits));

        return str_pad($otp, $digits, '0', STR_PAD_LEFT);
    }

    /** Codifica bytes binarios em string Base32 conforme RFC 4648. */
    private function base32Encode(string $data): string
    {
        $bits = '';
        $encoded = '';

        for ($i = 0, $length = strlen($data); $i < $length; $i++) {
            $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }

        for ($i = 0, $length = strlen($bits); $i < $length; $i += 5) {
            $chunk = substr($bits, $i, 5);
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $encoded .= self::BASE32_ALPHABET[bindec($chunk)];
        }

        return $encoded;
    }

    /** Decodifica string Base32 para bytes binarios, ignorando caracteres invalidos. */
    private function base32Decode(string $secret): string
    {
        $cleanSecret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        if ($cleanSecret === '') {
            return '';
        }

        $bits = '';
        $decoded = '';

        for ($i = 0, $length = strlen($cleanSecret); $i < $length; $i++) {
            $index = strpos(self::BASE32_ALPHABET, $cleanSecret[$i]);
            if ($index === false) {
                return '';
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        for ($i = 0, $length = strlen($bits); $i + 8 <= $length; $i += 8) {
            $decoded .= chr(bindec(substr($bits, $i, 8)));
        }

        return $decoded;
    }
}
