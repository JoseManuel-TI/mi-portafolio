<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../api/config.php';

$adminUser = trim(env_or_default('ADMIN_USER', ''));
$adminPassHash = trim(env_or_default('ADMIN_PASS_HASH', ''));
$authConfigured = $adminUser !== '' && $adminPassHash !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: mensajes.php');
    exit;
}

$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    if (!$authConfigured) {
        $loginError = 'Configura ADMIN_USER y ADMIN_PASS_HASH en el entorno del servidor.';
    } else {
    $username = trim((string) $_POST['username']);
    $password = (string) $_POST['password'];

    if (hash_equals($adminUser, $username) && password_verify($password, $adminPassHash)) {
        $_SESSION['admin_authenticated'] = true;
        header('Location: mensajes.php');
        exit;
    }

    $loginError = 'Credenciales invalidas.';
    }
}

$isAuthenticated = (bool) ($_SESSION['admin_authenticated'] ?? false);
$messages = [];
$dbError = '';

if ($isAuthenticated) {
    try {
        $pdo = db_connection();
        $stmt = $pdo->query(
            'SELECT id, name, email, message, ip_address, user_agent, created_at
             FROM contact_messages
             ORDER BY created_at DESC'
        );
        $messages = $stmt->fetchAll();
    } catch (Throwable $e) {
        $dbError = 'No se pudieron cargar los mensajes.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Mensajes</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f5f7fa; color: #1f2937; }
        .wrap { max-width: 1100px; margin: 30px auto; padding: 0 16px; }
        .card { background: #fff; border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); padding: 20px; }
        h1 { margin-top: 0; }
        .error { color: #b91c1c; margin: 10px 0; }
        .muted { color: #6b7280; }
        form { display: grid; gap: 10px; max-width: 380px; }
        input, button { font-size: 14px; padding: 10px; border-radius: 6px; border: 1px solid #d1d5db; }
        button { cursor: pointer; border: 0; background: #0f62fe; color: #fff; font-weight: 700; }
        .logout-form { display: inline; }
        .logout-form button { background: #111827; padding: 8px 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 10px; text-align: left; vertical-align: top; }
        th { background: #f9fafb; font-size: 13px; text-transform: uppercase; letter-spacing: 0.04em; }
        td.message { min-width: 260px; white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>Panel de Mensajes</h1>

            <?php if (!$isAuthenticated): ?>
                <p class="muted">Ingresa con tu usuario y password de administrador.</p>
                <?php if (!$authConfigured): ?>
                    <p class="error">Acceso deshabilitado: faltan ADMIN_USER y ADMIN_PASS_HASH.</p>
                <?php endif; ?>
                <?php if ($loginError !== ''): ?>
                    <p class="error"><?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <form method="POST" action="mensajes.php">
                    <label for="username">Usuario</label>
                    <input type="text" id="username" name="username" required>

                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>

                    <button type="submit">Entrar</button>
                </form>
            <?php else: ?>
                <form method="POST" action="mensajes.php" class="logout-form">
                    <input type="hidden" name="logout" value="1">
                    <button type="submit">Cerrar sesion</button>
                </form>

                <?php if ($dbError !== ''): ?>
                    <p class="error"><?php echo htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php elseif (count($messages) === 0): ?>
                    <p class="muted">Aun no hay mensajes guardados.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Mensaje</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($messages as $item): ?>
                                <tr>
                                    <td><?php echo (int) $item['id']; ?></td>
                                    <td><?php echo htmlspecialchars((string) $item['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) $item['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) $item['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="message"><?php echo htmlspecialchars((string) $item['message'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) $item['ip_address'], ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
