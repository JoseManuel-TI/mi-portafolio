# mi-portafolio

Portafolio personal con formulario de contacto guardado en MySQL (sin Formspree).

## Configuracion rapida (MAMP)

1. Crear base y tabla:
   - Importa `database/schema.sql` en phpMyAdmin o ejecutalo en MySQL.
2. Ajustar credenciales si hace falta:
   - `api/config.php` usa por defecto `127.0.0.1:8889`, DB `mi_portafolio`, usuario `root`, password `root`.
   - Puedes sobreescribir con variables de entorno: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`.
3. Abrir `contacto.html` y enviar un mensaje.

## Endpoint

- `POST /api/contact.php`
- Campos esperados: `name`, `email`, `message`
- Auto-reply: envia email de confirmacion al usuario si `AUTO_REPLY_ENABLED=1`

## Ver mensajes guardados

```sql
SELECT id, name, email, message, created_at
FROM contact_messages
ORDER BY id DESC;
```

## Panel admin

- URL: `http://localhost:8888/mi-portafolio/admin/mensajes.php`
- Requiere variables de entorno (obligatorias):
  - `ADMIN_USER`
  - `ADMIN_PASS_HASH`
- Generar hash de password:
  - `php -r "echo password_hash('tu_password_segura', PASSWORD_DEFAULT), PHP_EOL;"`
- Alternativa recomendada (VS Code, sin tocar Apache):
  - Copia `config/local.php.example` como `config/local.php`
  - Completa:
    - `ADMIN_USER` con tu usuario
    - `ADMIN_PASS_HASH` con el hash generado

## Auto reply email

- Configuracion en `config/local.php`:
  - `AUTO_REPLY_ENABLED` (`1` o `0`)
  - `SITE_NAME`
  - `MAIL_FROM`
  - `MAIL_REPLY_TO`
  - `AUTO_REPLY_SUBJECT`
  - `SMTP_ENABLED` (`1` o `0`)
  - `SMTP_HOST`
  - `SMTP_PORT`
  - `SMTP_USER`
  - `SMTP_PASS`
  - `SMTP_ENCRYPTION` (`tls`, `ssl` o `none`)
  - `SMTP_TIMEOUT`
- Para activar envio real:
  1. Completa credenciales SMTP reales en `config/local.php`.
  2. Pon `SMTP_ENABLED` en `1`.
  3. Mantén `AUTO_REPLY_ENABLED` en `1`.
