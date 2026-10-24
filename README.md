# Consulta de Notas ULS

Sistema académico de práctica para la Universidad Luterana Salvadoreña, desarrollado con **Laravel 12, Vue 3 y Vuetify 3**. Permite administrar docentes, estudiantes, grupos, matrículas y notas; usar Google Authenticator; generar informes PDF y Excel; y descargar la boleta de inscripción del estudiante.

## Documentación

| Documento | Contenido |
|---|---|
| [Documentación del sistema](docs/documentacion-sistema.md) | Arquitectura, tecnologías, ubicación del código, módulos, permisos, reglas y flujos. |
| [Guía de prueba para el docente](docs/guia-pruebas-docente.md) | Recorrido de evaluación con resultados esperados, prueba de QR y correos sin SMTP. |
| [Base de datos](docs/base-de-datos.md) | Diagrama físico, relaciones y restricciones. |

## 1. Requisitos

- PHP **8.2 o superior**, compatible con `backend/composer.lock`.
- Composer 2.
- Node.js **22** y npm; el proyecto se verificó con Node 22.19.
- Extensiones PHP: `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`, `dom`, `xml`, `xmlwriter`, `curl` y `zip`, además de las extensiones habituales de PHP. ZIP se utiliza para instalar las dependencias con Composer.
- Un navegador actualizado. Google Authenticator en un teléfono solo es necesario para probar docentes y estudiantes.
- Acceso a Internet para descargar las dependencias en la primera instalación.

Se utiliza **SQLite**: no hay que crear una base MySQL, importar un SQL ni iniciar Apache para la prueba local. La base se crea en `backend/database/database.sqlite`.

Verificar las herramientas:

```powershell
php -v
php -m
composer --version
node --version
npm.cmd --version
```

Si PHP no carga las extensiones indicadas, localizar su configuración con `php --ini`, habilitarlas en el `php.ini` correspondiente y reiniciar la terminal o el servidor PHP.

## 2. Instalar el proyecto

Clonar o descomprimir la entrega. Abrir una terminal **PowerShell en la raíz del proyecto**, donde está `package.json`.

### Frontend

```powershell
npm.cmd ci
```

### Backend y datos de práctica

```powershell
cd backend
composer install

if (-not (Test-Path .env)) { Copy-Item .env.example .env }
if (Select-String -Path .env -Pattern '^APP_KEY=\s*$' -Quiet) { php artisan key:generate --no-interaction }
if (-not (Test-Path database/database.sqlite)) { New-Item database/database.sqlite -ItemType File | Out-Null }

php artisan config:clear
php artisan migrate --seed --no-interaction
```

El `.env.example` viene preparado con:

```dotenv
APP_ENV=local
APP_URL=http://127.0.0.1:8001
FRONTEND_URL=http://127.0.0.1:5173
DB_CONNECTION=sqlite
SESSION_DRIVER=database
CACHE_STORE=database
MAIL_MAILER=log
```

**Conservar `APP_KEY` si la instalación ya tiene datos**: cifra los secretos del autenticador. Las instrucciones anteriores solo generan una clave cuando está vacía. No usar `migrate:fresh` sobre la base que se desea conservar.

En una instalación nueva, la semilla crea 6 docentes, 12 estudiantes, 3 grupos, 36 matrículas y 72 notas ficticias de **septiembre y octubre de 2026**, además del administrador inicial y el administrador de evaluación. No envía correos. Las cuentas académicas ficticias no tienen una contraseña pública ni un autenticador configurado.

## 3. Ejecutar

Mantener abiertas estas dos terminales:

**Terminal 1 — dentro de `backend`:**

```powershell
php artisan serve --host=127.0.0.1 --port=8001
```

**Terminal 2 — en la raíz del proyecto:**

```powershell
npm.cmd run dev -- --port 5173 --strictPort
```

Abrir **http://127.0.0.1:5173/login**. Usar el mismo host `127.0.0.1` en ambos servicios. Vite reenvía `/api` al backend del puerto 8001.

En Windows también se puede ejecutar `powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\iniciar.ps1` desde la raíz, una vez instaladas las dependencias. Este script inicia ambos servicios sin ventanas adicionales y guarda sus registros en `.runtime`. Si un puerto ya está ocupado, no inicia otra instancia. Para una evaluación es más sencillo utilizar las dos terminales anteriores, donde los errores quedan visibles.

## 4. Administrador de prueba para el docente

| Dato | Valor |
|---|---|
| Correo | **admin.prueba@uls.edu.sv** |
| Contraseña | **PruebaULS@2026!** |
| Rol | Administrador |
| Código QR / OTP | **No requerido** |
| Cambio inicial de clave | **No requerido en esta cuenta de evaluación** |

Esta cuenta es pública y se crea únicamente en el entorno local de evaluación. Inicia directamente en el panel con correo y contraseña. La cuenta personal `admin@uls.edu.sv` y sus credenciales se conservan.

Si la base ya existía, crear únicamente el administrador de prueba sin modificar los datos académicos:

```powershell
cd backend
php artisan uls:demo-admin
```

Si se cambió su contraseña durante la evaluación, restaurar **solo esa cuenta**:

```powershell
php artisan uls:demo-admin --reset
```

La restauración cierra las sesiones de ese administrador de prueba. Repetir la semilla o ejecutar el comando sin `--reset` no cambia una contraseña que ya se hubiera modificado.

## 5. Prueba rápida

1. Entrar con el administrador de evaluación.
2. Abrir **Gestión de notas**: comprobar búsqueda y paginación de cinco registros.
3. Abrir **Informes y estadísticas**, seleccionar **octubre de 2026** y pulsar **Consultar**.
4. Generar el PDF, revisarlo dentro del visor y descargarlo.
5. Pulsar **Exportar Excel**. Abrir el `.xlsx`: encabezado institucional, totales verdes y tabla ordenada. La impresión está configurada en A4 horizontal, una página de ancho y encabezados repetidos.
6. Revisar **Docentes**, **Estudiantes**, **Grupos académicos**, **Mi perfil** y el menú contraíble.
7. Pulsar **Salir del sistema** y comprobar que una ruta privada solicita iniciar sesión.

El recorrido completo, incluido QR, cambio de clave, boleta y recuperación de acceso, está en la [guía para el docente](docs/guia-pruebas-docente.md).

## 6. Probar correos y QR sin Mailjet

Con `MAIL_MAILER=log`, el sistema genera el contenido del correo en **`backend/storage/logs/laravel.log`**, sin enviarlo por Internet. Así se puede crear un docente o estudiante y consultar en el registro su contraseña temporal y enlace `/activar/...`.

El enlace muestra el QR. Al confirmar el código de Google Authenticator, el usuario entra al panel y debe cambiar su clave temporal; aparece la bienvenida con confeti. En los siguientes accesos, docentes y estudiantes usan correo, contraseña y OTP. **El administrador sigue sin OTP.**

Los registros locales contienen credenciales temporales y enlaces personales de prueba; no deben incorporarse a la entrega pública. La guía detalla cómo localizar únicamente las líneas relevantes. `MAIL_MAILER=array` se reserva para pruebas automáticas: descarta el correo y no permite consultarlo en el registro.

## 7. Envío real con Mailjet — opcional

Para recibir correos en un buzón real, configurar en `backend/.env` las credenciales de la cuenta Mailjet autorizada:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=in-v3.mailjet.com
MAIL_PORT=587
MAIL_USERNAME=API_KEY_DE_MAILJET
MAIL_PASSWORD=CLAVE_SECRETA_DE_MAILJET
MAIL_FROM_ADDRESS=remitente_verificado@ejemplo.com
MAIL_FROM_NAME="Consulta de Notas ULS"
```

Después:

```powershell
php artisan config:clear
php artisan uls:smtp-check
```

Reiniciar el backend si estaba ejecutándose. El remitente debe estar validado en Mailjet y el proceso PHP debe tener acceso de red al puerto 587. La comprobación de SMTP no envía correos ni garantiza la entrega en bandeja de entrada; para comprobarla se utiliza una cuenta académica de prueba con un correo controlado por el evaluador. No se incluyen claves SMTP reales en esta documentación.

## 8. Verificación automática

Desde la raíz:

```powershell
npm.cmd run build
node --test tests/gradeWorkbook.test.mjs
cd backend
php artisan test
```

La suite PHP utiliza SQLite **en memoria**, definido en `backend/phpunit.xml`, y correo simulado; no borra la base local ni envía mensajes reales. Las pruebas de Excel crean y reabren el `.xlsx` para revisar sus estilos, tipos de datos y configuración de impresión.

`npm run build` produce `dist`. Para esta prueba se debe usar Vite en desarrollo: `dist/index.html` no se abre directamente ni reemplaza al backend PHP. Un despliegue de producción necesita servir el frontend y la API bajo un mismo origen o configurar el proxy del servidor.

## 9. Problemas frecuentes

| Problema | Revisar |
|---|---|
| `npm.ps1` bloqueado en PowerShell | Utilizar `npm.cmd` como en esta guía. |
| `could not find driver` | Habilitar `pdo_sqlite` en el PHP de la terminal; revisar con `php --ini` y `php -m`. |
| Error de versión PHP | Usar PHP 8.2+ y ejecutar `composer install` con ese mismo PHP. |
| Puerto ocupado o frontend en 5174 | Detener la instancia anterior o utilizar los puertos 5173/8001; `--strictPort` evita cambiar de puerto silenciosamente. |
| No aparecen notas o el gráfico está vacío | Seleccionar septiembre u octubre de 2026 y consultar; los datos iniciales tienen esas fechas. |
| El administrador de prueba no entra | Ejecutar `php artisan uls:demo-admin --reset` en `APP_ENV=local`. |
| No llega un correo | Con `log` el correo está en el archivo de registro; solo `smtp` realiza envíos reales. |
| Error 503 al crear o restablecer una cuenta | Revisar SMTP/red/remitente, o usar `MAIL_MAILER=log` y ejecutar `config:clear`. Si falla el envío, la operación se revierte. |
| OTP inválido o ya utilizado | Revisar la hora automática del teléfono, la cuenta ULS seleccionada y esperar al próximo código. |
| Error 419 | Recargar e iniciar sesión; mantener `127.0.0.1` en ambas URLs. |
| Se cambiaron estilos pero se ve la versión anterior | Recargar con `Ctrl + F5`. |

## Estructura principal

```text
src/                    Frontend Vue: vistas, componentes, servicios y estilos
public/                 Logos y fondo vectorial del acceso
backend/app/            Controladores, modelos, servicios, middleware y comandos
backend/routes/         Endpoints de la API
backend/database/       Migraciones, semillas y SQLite local
backend/resources/views/ Plantillas de correo y documentos PDF
backend/tests/          Pruebas PHP
tests/                  Pruebas del Excel
docs/                   Documentación y guía de evaluación
```

Los archivos `.env`, la base SQLite, los registros, `vendor`, `node_modules`, `dist` y `.runtime` están excluidos del control de versiones. Las dependencias y la base se reconstruyen siguiendo esta guía.
