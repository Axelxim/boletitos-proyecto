<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Confirma tu correo</title>
</head>
<body style="margin:0;padding:0;background:#f7f1df;font-family:Arial,Helvetica,sans-serif;color:#071426;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f7f1df;padding:24px 0;">
        <tr>
            <td align="center">
                <table width="680" cellpadding="0" cellspacing="0" style="max-width:680px;width:100%;background:#050505;border:1px solid #c9a227;border-radius:20px;overflow:hidden;">
                    <tr>
                        <td style="background:#c9a227;height:10px;"></td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 8px;color:#f4d06f;font-size:12px;font-weight:bold;letter-spacing:3px;text-transform:uppercase;">Bienvenido a Boletitos</p>
                            <h1 style="margin:0;color:#fff7e6;font-size:32px;">Confirma tu correo</h1>
                            <p style="margin:16px 0 0;color:#fff7e6;font-size:16px;line-height:1.6;">
                                Hola <?= esc($usuario['nombre'] ?? 'usuario') ?>, gracias por crear tu cuenta. Antes de iniciar sesion debes confirmar tu correo electronico.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:24px;">
                                <tr>
                                    <td style="padding:18px;border:1px solid rgba(201,162,39,0.25);border-radius:16px;background:#111111;">
                                        <p style="margin:0 0 10px;color:#f4d06f;font-size:12px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;">Activa tu cuenta</p>
                                        <p style="margin:0 0 20px;color:#fff7e6;line-height:1.6;">
                                            Usa el siguiente boton para confirmar tu direccion de correo y activar tu cuenta de comprador.
                                        </p>
                                        <p style="margin:0 0 20px;">
                                            <a href="<?= esc($verificationUrl) ?>" style="display:inline-block;background:#c9a227;color:#050505;text-decoration:none;padding:14px 22px;border-radius:12px;font-weight:bold;">
                                                Confirmar mi correo
                                            </a>
                                        </p>
                                        <p style="margin:0 0 10px;color:#fff7e6;line-height:1.6;">
                                            Si el boton no funciona, copia y pega este enlace en tu navegador:
                                        </p>
                                        <p style="margin:0;color:#f4d06f;word-break:break-all;line-height:1.6;"><?= esc($verificationUrl) ?></p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:24px 0 0;color:#cbb98b;font-size:13px;line-height:1.6;">
                                Este enlace expirara el <?= esc($expiracion) ?>. Si no solicitaste esta cuenta, puedes ignorar este correo.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
