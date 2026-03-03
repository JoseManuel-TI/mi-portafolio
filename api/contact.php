<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Metodo no permitido.']);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

function send_owner_notification(string $fromName, string $fromEmail, string $message): void
{
    $toEmail = env_or_default('CONTACT_TO', 'jcmanueldj@gmail.com');
    $toName = env_or_default('SITE_NAME', 'Jose Manuel');
    $subjectPrefix = env_or_default('CONTACT_SUBJECT_PREFIX', '[Portfolio]');
    $subject = trim($subjectPrefix . ' Nuevo mensaje de contacto');

    $safeFromName = preg_replace('/[\r\n]+/', ' ', $fromName);
    $safeFromEmail = preg_replace('/[\r\n]+/', '', $fromEmail);

    $plainBody = "Recibiste un nuevo mensaje desde el formulario:\n\n";
    $plainBody .= "Nombre: {$safeFromName}\n";
    $plainBody .= "Email: {$safeFromEmail}\n\n";
    $plainBody .= "Mensaje:\n{$message}\n";

    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    $htmlBody = '<div style="font-family:Arial,Helvetica,sans-serif;background:#f4f8ff;padding:24px;">'
        . '<div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #d8e6ff;border-radius:12px;overflow:hidden;">'
        . '<div style="background:#0056b3;color:#fff;padding:16px 20px;font-size:18px;font-weight:700;">Nuevo mensaje desde tu portfolio</div>'
        . '<div style="padding:20px;color:#243447;line-height:1.6;">'
        . '<p style="margin:0 0 10px;"><strong>Nombre:</strong> ' . htmlspecialchars($safeFromName, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p style="margin:0 0 18px;"><strong>Email:</strong> ' . htmlspecialchars($safeFromEmail, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p style="margin:0 0 8px;"><strong>Mensaje:</strong></p>'
        . '<div style="background:#f8fbff;border:1px solid #e2ecff;border-radius:10px;padding:12px 14px;">' . $safeMessage . '</div>'
        . '</div></div></div>';

    $sent = smtp_send_html($toEmail, $toName, $subject, $plainBody, $htmlBody);
    if (!$sent) {
        error_log('No se pudo enviar notificacion de contacto a ' . $toEmail);
    }
}

function send_auto_reply(string $toEmail, string $toName): void
{
    $enabled = strtolower(env_or_default('AUTO_REPLY_ENABLED', '1'));
    if (!in_array($enabled, ['1', 'true', 'yes', 'on'], true)) {
        return;
    }

    $siteName = env_or_default('SITE_NAME', 'Jose Manuel Portafolio');
    $fromEmail = env_or_default('MAIL_FROM', 'no-reply@localhost');
    $replyTo = env_or_default('MAIL_REPLY_TO', 'no-reply@localhost');
    $subject = env_or_default('AUTO_REPLY_SUBJECT', 'Gracias por tu mensaje');

    $safeName = preg_replace('/[\r\n]+/', ' ', $toName);
    $safeSiteName = preg_replace('/[\r\n]+/', ' ', $siteName);

    $plainBody = "Hola {$safeName},\n\n";
    $plainBody .= "Gracias por contactarte con {$safeSiteName}. Recibimos tu mensaje correctamente.\n";
    $plainBody .= "Te responderemos a la brevedad.\n\n";
    $plainBody .= "Saludos,\n{$safeSiteName}\n";

    $htmlBody = '<div style="font-family:Arial,Helvetica,sans-serif;background:#f4f8ff;padding:24px;">'
        . '<div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #d8e6ff;border-radius:12px;overflow:hidden;">'
        . '<div style="background:#007bff;color:#fff;padding:16px 20px;font-size:18px;font-weight:700;">Gracias por escribirnos</div>'
        . '<div style="padding:20px;color:#243447;line-height:1.6;">'
        . '<p style="margin:0 0 12px;">Hola <strong>' . htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
        . '<p style="margin:0 0 10px;">Recibimos tu mensaje correctamente y ya estamos revisando tu consulta.</p>'
        . '<p style="margin:0 0 16px;">Te responderemos a la brevedad.</p>'
        . '<p style="margin:0;color:#4b5d73;">Saludos,<br><strong>' . htmlspecialchars($safeSiteName, ENT_QUOTES, 'UTF-8') . '</strong></p>'
        . '</div></div></div>';

    // El envio de mail no debe romper el flujo de guardado del formulario.
    $sent = smtp_send_html($toEmail, $safeName, $subject, $plainBody, $htmlBody);
    if (!$sent) {
        error_log('No se pudo enviar el auto-reply a ' . $toEmail);
    }
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));
$company = trim((string) ($_POST['company'] ?? ''));
$startedAt = (int) ($_POST['started_at'] ?? 0);

if ($company !== '') {
    echo json_encode(['ok' => true]);
    exit;
}

$elapsed = time() - $startedAt;
if ($startedAt <= 0 || $elapsed < 2 || $elapsed > 86400) {
    http_response_code(422);
    echo json_encode([
        'message' => 'No se pudo validar el formulario. Recarga la pagina e intenta de nuevo.',
        'errors' => [
            ['field' => 'message', 'message' => 'No se pudo validar el formulario.']
        ]
    ]);
    exit;
}

if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
    http_response_code(422);
    echo json_encode([
        'message' => 'El nombre debe tener entre 2 y 80 caracteres.',
        'errors' => [
            ['field' => 'name', 'message' => 'El nombre debe tener entre 2 y 80 caracteres.']
        ]
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode([
        'message' => 'El correo electronico no es valido.',
        'errors' => [
            ['field' => 'email', 'message' => 'El correo electronico no es valido.']
        ]
    ]);
    exit;
}

if (mb_strlen($message) < 10 || mb_strlen($message) > 2000) {
    http_response_code(422);
    echo json_encode([
        'message' => 'El mensaje debe tener entre 10 y 2000 caracteres.',
        'errors' => [
            ['field' => 'message', 'message' => 'El mensaje debe tener entre 10 y 2000 caracteres.']
        ]
    ]);
    exit;
}

$ipAddress = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
$userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

try {
    $pdo = db_connection();
    $stmt = $pdo->prepare(
        'INSERT INTO contact_messages (name, email, message, ip_address, user_agent)
         VALUES (:name, :email, :message, :ip_address, :user_agent)'
    );

    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':message' => $message,
        ':ip_address' => $ipAddress,
        ':user_agent' => $userAgent,
    ]);

    send_owner_notification($name, $email, $message);
    send_auto_reply($email, $name);

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'message' => 'No se pudo guardar el mensaje. Revisa que MySQL este iniciado en MAMP.'
    ]);
}
