<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Compra confirmada</title>
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
                            <p style="margin:0 0 8px;color:#f4d06f;font-size:12px;font-weight:bold;letter-spacing:3px;text-transform:uppercase;">Compra confirmada</p>
                            <h1 style="margin:0;color:#fff7e6;font-size:32px;">Boletitos</h1>
                            <p style="margin:16px 0 0;color:#fff7e6;font-size:16px;line-height:1.6;">
                                Hola <?= esc($orden['nombre_comprador'] ?? 'cliente') ?>, tu compra fue confirmada correctamente.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:24px;">
                                <tr>
                                    <td style="padding:18px;border:1px solid rgba(201,162,39,0.25);border-radius:16px;background:#111111;">
                                        <p style="margin:0 0 10px;color:#f4d06f;font-size:12px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;">Resumen de compra</p>
                                        <p style="margin:0 0 8px;color:#fff7e6;"><strong>Folio de orden:</strong> <?= esc($orden['folio'] ?? 'N/D') ?></p>
                                        <p style="margin:0 0 8px;color:#fff7e6;"><strong>Evento:</strong> <?= esc($orden['evento']['titulo'] ?? 'No disponible') ?></p>
                                        <p style="margin:0 0 8px;color:#fff7e6;"><strong>Fecha:</strong> <?= esc($orden['evento']['fecha_inicio'] ?? 'No disponible') ?></p>
                                        <p style="margin:0 0 8px;color:#fff7e6;"><strong>Sede / lugar:</strong> <?= esc($orden['evento']['sede']['nombre'] ?? $orden['evento']['lugar'] ?? 'No disponible') ?></p>
                                        <p style="margin:0;color:#fff7e6;"><strong>Total pagado:</strong> MX$<?= esc(number_format((float) ($orden['total'] ?? 0), 2)) ?></p>
                                    </td>
                                </tr>
                            </table>

                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:24px;">
                                <tr>
                                    <td>
                                        <p style="margin:0 0 12px;color:#f4d06f;font-size:12px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;">Tus boletos</p>
                                    </td>
                                </tr>
                                <?php foreach (($orden['boletos_generados'] ?? []) as $boleto): ?>
                                    <tr>
                                        <td style="padding:16px 18px;border:1px solid rgba(201,162,39,0.2);border-radius:16px;background:#0e0e0e;color:#fff7e6;">
                                            <p style="margin:0 0 8px;"><strong>Folio:</strong> <?= esc($boleto['folio'] ?? 'N/D') ?></p>
                                            <p style="margin:0 0 8px;"><strong>Tipo:</strong> <?= esc($boleto['tipo_boleto']['nombre'] ?? 'No disponible') ?></p>
                                            <p style="margin:0;"><strong>Codigo QR:</strong> <?= esc($boleto['codigo_qr'] ?? 'No disponible') ?></p>
                                        </td>
                                    </tr>
                                    <tr><td style="height:12px;"></td></tr>
                                <?php endforeach; ?>
                            </table>

                            <p style="margin:24px 0 0;color:#cbb98b;font-size:13px;line-height:1.6;">
                                Conserva este correo como referencia. El codigo QR se envia en texto mientras se integra la imagen QR.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
