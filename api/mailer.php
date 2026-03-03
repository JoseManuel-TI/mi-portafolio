<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function smtp_enabled(): bool
{
    $value = strtolower(env_or_default('SMTP_ENABLED', '0'));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function smtp_send_plain_text(string $toEmail, string $toName, string $subject, string $body): bool
{
    return smtp_send_message($toEmail, $toName, $subject, $body, null);
}

function smtp_send_html(string $toEmail, string $toName, string $subject, string $plainBody, string $htmlBody): bool
{
    return smtp_send_message($toEmail, $toName, $subject, $plainBody, $htmlBody);
}

function smtp_send_message(string $toEmail, string $toName, string $subject, string $plainBody, ?string $htmlBody): bool
{
    if (!smtp_enabled()) {
        return false;
    }

    $host = env_or_default('SMTP_HOST', '');
    $port = (int) env_or_default('SMTP_PORT', '587');
    $user = env_or_default('SMTP_USER', '');
    $pass = env_or_default('SMTP_PASS', '');
    $encryption = strtolower(env_or_default('SMTP_ENCRYPTION', 'tls')); // tls|ssl|none
    $timeout = (int) env_or_default('SMTP_TIMEOUT', '15');

    $fromEmail = env_or_default('MAIL_FROM', '');
    $fromName = env_or_default('SITE_NAME', 'Website');
    $replyTo = env_or_default('MAIL_REPLY_TO', $fromEmail);

    if ($host === '' || $fromEmail === '') {
        error_log('SMTP no configurado: faltan SMTP_HOST o MAIL_FROM.');
        return false;
    }

    $transportHost = $encryption === 'ssl' ? "ssl://{$host}" : $host;
    $socket = @stream_socket_client(
        "{$transportHost}:{$port}",
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT
    );

    if (!$socket) {
        error_log("SMTP connect error: {$errno} {$errstr}");
        return false;
    }

    stream_set_timeout($socket, $timeout);

    if (!smtp_expect($socket, [220])) {
        fclose($socket);
        return false;
    }

    if (!smtp_command($socket, 'EHLO localhost', [250])) {
        fclose($socket);
        return false;
    }

    if ($encryption === 'tls') {
        if (!smtp_command($socket, 'STARTTLS', [220])) {
            fclose($socket);
            return false;
        }

        $cryptoOk = stream_socket_enable_crypto(
            $socket,
            true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT
        );
        if ($cryptoOk !== true) {
            error_log('SMTP STARTTLS fallo al iniciar cifrado.');
            fclose($socket);
            return false;
        }

        if (!smtp_command($socket, 'EHLO localhost', [250])) {
            fclose($socket);
            return false;
        }
    }

    if ($user !== '' && $pass !== '') {
        if (!smtp_command($socket, 'AUTH LOGIN', [334])) {
            fclose($socket);
            return false;
        }
        if (!smtp_command($socket, base64_encode($user), [334])) {
            fclose($socket);
            return false;
        }
        if (!smtp_command($socket, base64_encode($pass), [235])) {
            fclose($socket);
            return false;
        }
    }

    if (!smtp_command($socket, 'MAIL FROM:<' . sanitize_email_header($fromEmail) . '>', [250])) {
        fclose($socket);
        return false;
    }
    if (!smtp_command($socket, 'RCPT TO:<' . sanitize_email_header($toEmail) . '>', [250, 251])) {
        fclose($socket);
        return false;
    }
    if (!smtp_command($socket, 'DATA', [354])) {
        fclose($socket);
        return false;
    }

    $message = build_email_message(
        $fromEmail,
        $fromName,
        $toEmail,
        $toName,
        $replyTo,
        $subject,
        $plainBody,
        $htmlBody
    );

    $data = preg_replace('/(?m)^\./', '..', $message) . "\r\n.\r\n";
    fwrite($socket, $data);

    if (!smtp_expect($socket, [250])) {
        fclose($socket);
        return false;
    }

    smtp_command($socket, 'QUIT', [221]);
    fclose($socket);
    return true;
}

function build_email_message(
    string $fromEmail,
    string $fromName,
    string $toEmail,
    string $toName,
    string $replyTo,
    string $subject,
    string $plainBody,
    ?string $htmlBody
): string {
    $safeFromName = sanitize_header_value($fromName);
    $safeToName = sanitize_header_value($toName);
    $safeSubject = sanitize_header_value($subject);
    $safeFromEmail = sanitize_email_header($fromEmail);
    $safeToEmail = sanitize_email_header($toEmail);
    $safeReplyTo = sanitize_email_header($replyTo);

    $headers = [];
    $headers[] = "From: {$safeFromName} <{$safeFromEmail}>";
    $headers[] = "To: {$safeToName} <{$safeToEmail}>";
    $headers[] = "Reply-To: {$safeReplyTo}";
    $headers[] = "Subject: {$safeSubject}";
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Date: ' . date('r');

    $plain = normalize_line_breaks($plainBody);

    if ($htmlBody === null || trim($htmlBody) === '') {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        return implode("\r\n", $headers) . "\r\n\r\n" . $plain;
    }

    $boundary = 'b1_' . md5((string) microtime(true) . random_int(1000, 9999));
    $html = normalize_line_breaks($htmlBody);

    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

    $body = [];
    $body[] = '--' . $boundary;
    $body[] = 'Content-Type: text/plain; charset=UTF-8';
    $body[] = 'Content-Transfer-Encoding: 8bit';
    $body[] = '';
    $body[] = $plain;
    $body[] = '--' . $boundary;
    $body[] = 'Content-Type: text/html; charset=UTF-8';
    $body[] = 'Content-Transfer-Encoding: 8bit';
    $body[] = '';
    $body[] = $html;
    $body[] = '--' . $boundary . '--';

    return implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $body);
}

function normalize_line_breaks(string $text): string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $text);
    return str_replace("\n", "\r\n", $normalized);
}

function sanitize_header_value(string $value): string
{
    return trim((string) preg_replace('/[\r\n]+/', ' ', $value));
}

function sanitize_email_header(string $email): string
{
    return trim((string) preg_replace('/[\r\n<>]+/', '', $email));
}

function smtp_command($socket, string $command, array $expectedCodes): bool
{
    fwrite($socket, $command . "\r\n");
    return smtp_expect($socket, $expectedCodes);
}

function smtp_expect($socket, array $expectedCodes): bool
{
    $response = smtp_read_response($socket);
    if ($response === '') {
        error_log('SMTP respuesta vacia.');
        return false;
    }

    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        error_log('SMTP respuesta inesperada: ' . trim($response));
        return false;
    }

    return true;
}

function smtp_read_response($socket): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    return $response;
}
