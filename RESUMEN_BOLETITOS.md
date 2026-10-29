# Boletitos - Resumen del Proyecto

## Estado general

Boletitos es una plataforma web para venta de boletos para eventos.

Tecnologias definidas:

- Backend: CodeIgniter 4
- Frontend: Angular + Ionic
- Base de datos: MySQL
- Arquitectura: API REST
- Patron principal: MVC
- Control de versiones: GitHub

Actualmente el trabajo realizado se ha concentrado en el backend.
El frontend se dejo para una etapa posterior.

## Reglas de desarrollo seguidas

- No modificar `.env`
- No modificar estructura de base de datos
- No ejecutar migraciones
- No instalar dependencias nuevas
- No cambiar nombres de tablas, columnas o rutas sin autorizacion
- Mantener MVC
- Controladores delgados
- Logica de negocio en `Services`
- Acceso a base de datos mediante `Models`
- Respuesta API estandar en JSON
- Validaciones en todos los endpoints

## Estructura base del backend

Se trabajo sobre una organizacion por capas dentro de `backend/app`:

- `Controllers/Api`
- `Models`
- `Services`
- `Filters`
- `Helpers`
- `Config`

Tambien quedaron preparados filtros y utilidades para crecimiento incremental del proyecto.

## Base ya implementada

### Infraestructura API

- Configuracion CORS para:
  - `http://localhost:8100`
  - `http://localhost:4200`
- Respuesta JSON estandar:

```json
{
  "success": true,
  "message": "Mensaje de respuesta",
  "data": {},
  "errors": null
}
```

- Respuesta de error estandar:

```json
{
  "success": false,
  "message": "Mensaje de error",
  "data": null,
  "errors": {}
}
```

- `BaseApiController` para respuestas reutilizables
- Endpoint de prueba:
  - `GET /api/ping`

### Helpers/utilidades base

Se dejaron funciones reutilizables para:

- Generacion de folios unicos
- Normalizacion de mensajes
- Obtencion segura de IP del cliente
- Obtencion de user agent
- Sanitizacion simple de cadenas
- Timestamps con la zona horaria configurada
- Manejo de usuario autenticado en request

### Seguridad inicial

Filtros preparados e implementados:

- `AuthFilter`
  - Lee `Authorization: Bearer TOKEN`
  - Valida token provisional
  - Inyecta el usuario autenticado en el flujo

- `AdminFilter`
  - Requiere usuario autenticado
  - Valida que el rol sea `admin`

Endpoint de prueba administrativo:

- `GET /api/admin/ping`

## Bitacora

Se implemento soporte base de bitacora usando la tabla existente `bitacora`.

Incluye:

- `BitacoraModel`
- `BitacoraService`

Se registra actividad relevante en modulos como:

- autenticacion
- tipos de boletos
- ordenes
- pagos
- boletos
- validaciones

## Modulos implementados

### Roles

Tabla usada: `roles`

Implementado:

- `RolModel`
- `RolService`
- `RolesController`

Rutas:

- `GET /api/roles`
- `GET /api/roles/{id}`

### Usuarios

Tabla usada: `usuarios`

Implementado:

- `UsuarioModel`
- `UsuarioService`
- `UsuariosController`

Rutas:

- `GET /api/usuarios`
- `GET /api/usuarios/{id}`

Notas:

- rutas protegidas por `AuthFilter`
- no se devuelve `password`
- incluye informacion del rol cuando aplica

### Autenticacion base

Implementado:

- registro
- login
- token provisional firmado

Rutas:

- `POST /api/auth/register`
- `POST /api/auth/login`

Registro:

- valida nombre, correo y password
- correo unico
- password con hash seguro
- rol `usuario` por defecto

Login:

- valida correo y password
- valida estado activo
- devuelve usuario sin password
- devuelve token provisional

Bitacora:

- registro exitoso
- login exitoso
- login fallido

### Categorias

Tabla usada: `categorias`

Implementado:

- `CategoriaModel`
- `CategoriaService`
- `CategoriasController`

Rutas publicas:

- `GET /api/categorias`
- `GET /api/categorias/{id}`

Rutas admin:

- `POST /api/admin/categorias`
- `PUT /api/admin/categorias/{id}`
- `DELETE /api/admin/categorias/{id}`

### Sedes

Tabla usada: `sedes`

Implementado:

- `SedeModel`
- `SedeService`
- `SedesController`

Rutas publicas:

- `GET /api/sedes`
- `GET /api/sedes/{id}`

Rutas admin:

- `POST /api/admin/sedes`
- `PUT /api/admin/sedes/{id}`
- `DELETE /api/admin/sedes/{id}`

### Eventos

Tabla usada: `eventos`

Implementado:

- `EventoModel`
- `EventoService`
- `EventosController`

Rutas publicas:

- `GET /api/eventos`
- `GET /api/eventos/{id}`

Rutas admin:

- `POST /api/admin/eventos`
- `PUT /api/admin/eventos/{id}`
- `DELETE /api/admin/eventos/{id}`

Notas:

- en publico solo se muestran eventos `publicado`
- en admin se manejan todos los estados
- incluye relacion con categoria y sede

### Tipos de boletos

Tabla usada: `tipos_boletos`

Implementado:

- `TipoBoletoModel`
- `TipoBoletoService`
- `TiposBoletosController`

Ruta publica:

- `GET /api/eventos/{evento_id}/tipos-boletos`

Rutas admin:

- `GET /api/admin/tipos-boletos`
- `GET /api/admin/tipos-boletos/{id}`
- `POST /api/admin/tipos-boletos`
- `PUT /api/admin/tipos-boletos/{id}`
- `DELETE /api/admin/tipos-boletos/{id}`

Validaciones aplicadas:

- evento existente
- precio no negativo
- cantidades no negativas
- `cantidad_disponible <= cantidad_total`
- en publico solo activos y con disponibilidad

### Ordenes

Tabla usada: `ordenes`

Implementado:

- `OrdenModel`
- `OrdenService`
- `OrdenesController`

Rutas:

- `GET /api/admin/ordenes`
- `GET /api/admin/ordenes/{id}`
- `POST /api/ordenes`

Comportamiento:

- crea orden en estado `pendiente`
- genera `folio`
- permite `usuario_id` nullable
- registra bitacora

### Detalle de orden

Tabla usada: `orden_detalles`

Implementado:

- `OrdenDetalleModel`
- `OrdenDetalleService`

Ruta:

- `POST /api/ordenes/{orden_id}/detalles`

Comportamiento:

- valida orden pendiente
- valida tipo de boleto activo
- valida `max_por_compra`
- valida disponibilidad
- toma `precio_unitario` desde BD
- recalcula subtotal, comision y total

### Pagos simulados

Tabla usada: `pagos`

Implementado:

- `PagoModel`
- `PagoService`
- integrado en `OrdenesController`

Ruta:

- `POST /api/ordenes/{orden_id}/pago-simulado`

Estados soportados:

- `aprobado`
- `rechazado`
- `cancelado`

Comportamiento:

- si aprobado: orden `pagada`
- si rechazado: orden `fallida`
- si cancelado: orden `cancelada`

### Generacion de boletos

Tabla usada: `boletos`

Implementado:

- `BoletoModel`
- `BoletoService`

Ruta:

- `POST /api/ordenes/{orden_id}/generar-boletos`

Comportamiento:

- solo si la orden esta `pagada`
- evita duplicados por orden
- genera un boleto por unidad comprada
- crea `folio` unico
- crea `codigo_qr` provisional unico
- estado inicial `vigente`
- descuenta `cantidad_disponible`

### Validacion de boletos

Tablas usadas:

- `boletos`
- `validaciones_boletos`

Implementado:

- `ValidacionBoletoModel`
- `ValidacionBoletoService`
- `ValidacionesBoletosController`

Ruta admin:

- `POST /api/admin/boletos/validar`

Comportamiento:

- recibe `codigo_qr`
- busca boleto
- si esta `vigente`, lo marca `usado`
- guarda `fecha_uso`
- registra resultado de validacion

Nota:

- se corrigio un error 500 provocado por `boleto_id = NULL` cuando el QR no existia

### Consultas de boletos

Implementado:

- `BoletosController`
- ampliacion de `BoletoService`

Rutas:

- `GET /api/ordenes/{orden_id}/boletos`
- `GET /api/admin/boletos`
- `GET /api/admin/boletos/{id}`

Incluye relaciones cuando existen:

- orden
- tipo_boleto
- evento

### Dashboard administrativo

Implementado:

- `DashboardService`
- `DashboardController`

Ruta admin:

- `GET /api/admin/dashboard/resumen`

Devuelve:

- `total_eventos`
- `eventos_publicados`
- `total_ordenes`
- `ordenes_pagadas`
- `total_ventas`
- `total_boletos_generados`
- `boletos_vigentes`
- `boletos_usados`
- `boletos_cancelados`

## Rutas clave del sistema

### Publicas

- `GET /api/ping`
- `POST /api/auth/register`
- `POST /api/auth/login`
- `GET /api/roles`
- `GET /api/roles/{id}`
- `GET /api/categorias`
- `GET /api/categorias/{id}`
- `GET /api/sedes`
- `GET /api/sedes/{id}`
- `GET /api/eventos`
- `GET /api/eventos/{id}`
- `GET /api/eventos/{evento_id}/tipos-boletos`
- `POST /api/ordenes`
- `POST /api/ordenes/{orden_id}/detalles`
- `POST /api/ordenes/{orden_id}/pago-simulado`
- `POST /api/ordenes/{orden_id}/generar-boletos`
- `GET /api/ordenes/{orden_id}/boletos`

### Protegidas con AuthFilter

- `GET /api/usuarios`
- `GET /api/usuarios/{id}`

### Protegidas con AuthFilter + AdminFilter

- `GET /api/admin/ping`
- `GET /api/admin/dashboard/resumen`
- CRUD admin de categorias
- CRUD admin de sedes
- CRUD admin de eventos
- CRUD admin de tipos de boletos
- `GET /api/admin/ordenes`
- `GET /api/admin/ordenes/{id}`
- `POST /api/admin/boletos/validar`
- `GET /api/admin/boletos`
- `GET /api/admin/boletos/{id}`

## Estado actual del proyecto

Backend:

- base API establecida
- autenticacion provisional funcionando
- filtros de seguridad funcionando
- bitacora funcionando
- modulos principales del flujo de venta implementados
- dashboard admin basico implementado

Frontend:

- pendiente
- se definio Angular + Ionic, pero no se ha trabajado formalmente todavia

## Pendientes naturales para la siguiente etapa

- frontend Angular + Ionic
- JWT real en lugar de token provisional
- modulo de eventos publicos en frontend
- proceso de compra en frontend
- administracion frontend
- integracion real de pagos
- generacion real de QR
- envio de correos
- politicas mas finas de autorizacion
- posibles reportes administrativos

## Archivo de referencia

Este documento resume el estado acumulado del proyecto hasta este momento y sirve como punto de continuidad para seguir trabajando modulo por modulo sin perder contexto.
