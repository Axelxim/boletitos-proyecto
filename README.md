# Boletitos

Plataforma web para publicar eventos, vender boletos digitales, administrar órdenes y validar accesos mediante QR.

El proyecto se divide en dos aplicaciones:

- `frontend/`: aplicación Ionic + Angular.
- `backend/`: API REST con CodeIgniter 4 y MySQL.

## Requisitos

- Node.js 20 o superior y npm.
- PHP 8.2 o superior, con las extensiones `intl`, `mbstring`, `mysqli`, `curl` y `openssl`.
- Composer 2.
- MySQL 8 o MariaDB.

## Configuración inicial

Clona el repositorio e instala las dependencias de cada aplicación:

```bash
git clone https://github.com/Axelxim/boletitos-proyecto.git
cd boletitos-proyecto

cd backend
composer install

cd ../frontend
npm install
```

### Variables de entorno del backend

Crea `backend/.env` y completa las credenciales. Este archivo está ignorado por Git: nunca subas contraseñas, llaves SMTP o secretos de PayPal.

Configuración mínima de ejemplo:

```env
CI_ENVIRONMENT = development

# URL del frontend usada para los enlaces de confirmación de correo.
app.frontendURL = 'http://localhost:8100'

database.default.hostname = localhost
database.default.database = boletitos
database.default.username = root
database.default.password =
database.default.DBDriver = MySQLi
database.default.port = 3306

# Opcional para envío de correos de confirmación.
email.fromEmail = 'no-reply@example.com'
email.fromName = 'Boletitos'
email.protocol = 'smtp'
email.SMTPHost = 'smtp.example.com'
email.SMTPUser = 'no-reply@example.com'
email.SMTPPass = 'TU_CONTRASENA_O_APP_PASSWORD'
email.SMTPPort = 587
email.SMTPCrypto = 'tls'
email.mailType = 'html'

# Opcional para pagos.
PAYPAL_CLIENT_ID =
PAYPAL_CLIENT_SECRET =
PAYPAL_MODE = sandbox
```

Si usas una base remota de Hostinger, usa el hostname proporcionado por el panel y autoriza tu IP en **Remote MySQL**. Para permitir conexiones desde cualquier red se puede registrar `%` como *Access host*; úsalo solamente en entornos de desarrollo y con una contraseña fuerte.

### Base de datos

Crea previamente una base llamada `boletitos` (o utiliza el nombre configurado en `.env`) y ejecuta:

```bash
cd backend
php spark migrate
php spark db:seed BoletitosInitialSeeder
```

Para cargar datos de demostración (categorías, sedes y eventos):

```bash
php spark db:seed EventosPruebaSeeder
```

## Ejecución local

Abre dos terminales desde la raíz del proyecto.

### Backend — puerto 8080

```bash
cd backend
php spark serve --port 8080
```

La API quedará disponible en `http://localhost:8080/api`.

Si tu proveedor de MySQL requiere conexión IPv4 y el nombre de host no resuelve correctamente, puedes usar el enrutador local incluido:

```bash
cd backend
php -S localhost:8080 router_local.php
```

### Frontend — puerto 8100

```bash
cd frontend
npm start -- --port 8100
```

Abre `http://localhost:8100` en el navegador. La configuración de desarrollo ya apunta a `http://localhost:8080/api` en `frontend/src/environments/environment.ts`.

## Cuenta administradora

Los roles iniciales se crean al ejecutar `BoletitosInitialSeeder`. Por seguridad, las cuentas administrativas no se incluyen en el repositorio: crea una desde el panel administrativo o directamente en la base de datos con una contraseña cifrada usando `password_hash()` de PHP.

## Comandos útiles

```bash
# Backend
cd backend
php spark migrate:status
php spark routes
composer test

# Frontend
cd frontend
npm run build
npm test
npm run lint
```

## Flujo de versiones

Antes de subir cambios:

```bash
git status
git add .
git commit -m "Describe el cambio"
git push origin main
```

Verifica siempre que `.env`, `node_modules/`, `vendor/` y archivos generados no entren al commit.
