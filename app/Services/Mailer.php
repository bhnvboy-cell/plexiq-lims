<?php

namespace App\Services;

use App\Models\EmailConfig;

/**
 * Native PHP SMTP mailer. No external dependencies.
 */
class Mailer
{
    /** @var resource|null */
    private $socket = null;

    private string $lastError = '';

    public function lastError(): string
    {
        return $this->lastError;
    }

    /**
     * Reversibly encrypt a value using the APP_KEY (AES-256-CBC).
     */
    public static function encrypt(string $plain): string
    {
        $key = self::appKey();
        $iv = openssl_random_pseudo_bytes(16);
        $cipher = openssl_encrypt($plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return 'enc:' . base64_encode($iv . $cipher);
    }

    /**
     * Decrypt a value previously produced by encrypt().
     * Falls back gracefully for legacy hashed values.
     */
    public static function decrypt(string $payload): ?string
    {
        if (!str_starts_with($payload, 'enc:')) {
            return null;
        }
        $raw = base64_decode(substr($payload, 4), true);
        if ($raw === false || strlen($raw) <= 16) {
            return null;
        }
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $plain = openssl_decrypt($cipher, 'aes-256-cbc', self::appKey(), OPENSSL_RAW_DATA, $iv);
        return $plain === false ? null : $plain;
    }

    private static function appKey(): string
    {
        $key = env('APP_KEY', '');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            return $decoded !== false ? $decoded : str_pad($key, 32, 'x');
        }
        return str_pad(substr(hash('sha256', $key), 0, 32), 32, 'x');
    }

    /**
     * Send an HTML email using the default SMTP configuration.
     */
    public function send(string $to, string $subject, string $htmlBody, array $options = []): bool
    {
        $config = EmailConfig::getDefault();
        if (!$config) {
            $this->lastError = 'No default SMTP configuration found.';
            return false;
        }

        $host = $config['smtp_host'];
        $port = (int)($config['smtp_port'] ?? 587);
        $encryption = strtolower($config['smtp_encryption'] ?? 'tls');
        $username = $config['smtp_username'] ?? '';
        $password = $config['smtp_password'] ? self::decrypt($config['smtp_password']) : '';
        $fromAddress = $config['from_address'] ?? '';
        $fromName = $config['from_name'] ?? '';

        if (empty($host) || empty($fromAddress)) {
            $this->lastError = 'SMTP host and from address are required.';
            return false;
        }

        $timeout = (int)env('MAIL_TIMEOUT', 15);
        $remote = $encryption === 'ssl' ? 'ssl://' . $host . ':' . $port : $host . ':' . $port;

        $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $this->socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$this->socket) {
            $this->lastError = "SMTP connection failed: $errstr ($errno)";
            return false;
        }
        stream_set_timeout($this->socket, $timeout);

        try {
            if (!$this->expect(220)) {
                $this->lastError = 'SMTP greeting failed: ' . $this->lastError;
                return false;
            }

            $this->sendCmd('EHLO ' . (gethostname() ?: 'localhost'));
            $ehlo = $this->readResponse();
            if (str_starts_with($ehlo, '421') || str_starts_with($ehlo, '5')) {
                // try HELO
                $this->sendCmd('HELO ' . (gethostname() ?: 'localhost'));
                $this->expect(250);
            }

            // STARTTLS
            if ($encryption === 'tls') {
                $this->sendCmd('STARTTLS');
                if ($this->expect(220)) {
                    $crypto = stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                    if (!$crypto) {
                        $this->lastError = 'TLS negotiation failed.';
                        return false;
                    }
                    $this->sendCmd('EHLO ' . (gethostname() ?: 'localhost'));
                    $this->expect(250);
                }
            }

            // Auth
            if (!empty($username)) {
                $this->sendCmd('AUTH LOGIN');
                if (!$this->expect(334)) {
                    $this->lastError = 'SMTP server does not support AUTH LOGIN.';
                    return false;
                }
                $this->sendCmd(base64_encode($username));
                if (!$this->expect(334)) {
                    $this->lastError = 'SMTP auth rejected username.';
                    return false;
                }
                $this->sendCmd(base64_encode((string)$password));
                if (!$this->expect(235)) {
                    $this->lastError = 'SMTP authentication failed (bad username or password).';
                    return false;
                }
            }

            $this->sendCmd('MAIL FROM: <' . $fromAddress . '>');
            if (!$this->expect(250)) {
                $this->lastError = 'MAIL FROM rejected.';
                return false;
            }

            $this->sendCmd('RCPT TO: <' . $to . '>');
            if (!$this->expect(250)) {
                $this->lastError = 'RCPT TO rejected.';
                return false;
            }

            $this->sendCmd('DATA');
            if (!$this->expect(354)) {
                $this->lastError = 'DATA command rejected.';
                return false;
            }

            $boundary = 'b' . bin2hex(random_bytes(12));
            $headers = "From: =?UTF-8?B?" . base64_encode($fromName ?: $fromAddress) . "?= <" . $fromAddress . ">\r\n"
                . "To: <" . $to . ">\r\n"
                . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
                . "MIME-Version: 1.0\r\n"
                . "Content-Type: multipart/alternative; boundary=\"" . $boundary . "\"\r\n"
                . "Date: " . date('r') . "\r\n";

            $plain = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</h1>', '</h2>', '</h3>'], "\n", $htmlBody));
            $body = "--" . $boundary . "\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
                . $plain . "\r\n"
                . "--" . $boundary . "\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($htmlBody)) . "\r\n"
                . "--" . $boundary . "--\r\n";

            $this->sendCmd($headers . "\r\n" . $body . "\r\n.");
            if (!$this->expect(250)) {
                $this->lastError = 'Message body rejected.';
                return false;
            }

            $this->sendCmd('QUIT');
            $this->readResponse();
            return true;
        } catch (\Throwable $e) {
            $this->lastError = 'SMTP error: ' . $e->getMessage();
            return false;
        } finally {
            if (is_resource($this->socket)) {
                @fclose($this->socket);
            }
        }
    }

    private function sendCmd(string $cmd): void
    {
        @fwrite($this->socket, $cmd . "\r\n");
    }

    private function readResponse(): string
    {
        $response = '';
        while (($line = @fgets($this->socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        return trim($response);
    }

    private function expect(int $code): bool
    {
        $response = $this->readResponse();
        $this->lastError = $response;
        return str_starts_with($response, (string)$code);
    }
}
