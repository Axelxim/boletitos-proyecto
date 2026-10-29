# Pendiente QR Admin

- Fecha: 2026-07-09
- Modulo: `/admin/validacion`

## Estado actual

Se integro escaneo con camara usando una libreria dedicada:

- `@zxing/browser`
- `navigator.mediaDevices.getUserMedia`

La validacion manual por codigo sigue funcionando correctamente.

## Pendiente

Validar el comportamiento en dispositivos reales Android e iOS para ajustar permisos, rendimiento de camara y deteccion en condiciones de luz variables.

## Motivo

La dependencia ya fue instalada y el flujo nativo anterior fue reemplazado por un lector dedicado de mejor compatibilidad.

## Siguiente paso sugerido

1. Probar escaneo en telefonos reales y navegador de escritorio
2. Ajustar mensajes de error o estados de lectura segun resultados
3. Mantener la validacion manual como respaldo permanente
