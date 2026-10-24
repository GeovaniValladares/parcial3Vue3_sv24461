# Consulta de Notas ULS

Sistema de práctica con API REST Laravel 12 (PHP 8.2+), Vue 3, Vuetify 3, AnimateIcons Lucide, VueUse QR, OTP Input, Chart.js, DomPDF y PDF.js. Los datos se guardan en SQLite, no en el navegador.

## Ejecutar

Requisitos: PHP 8.2+ con `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`, `dom` y `zip`; Composer; Node 20.19+ o 22.12+.

Desde la raíz:

```powershell
npm.cmd install
cd backend
composer install
Copy-Item .env.example .env # solo si aún no existe .env
php artisan key:generate # solo al instalar; conservar APP_KEY después
New-Item database/database.sqlite -ItemType File -ErrorAction SilentlyContinue
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8001
```

En una segunda terminal, desde la raíz:

```powershell
npm.cmd run dev
```

Abrir http://127.0.0.1:5173. Usar siempre `127.0.0.1` para conservar la sesión. Vite reenvía `/api` a Laravel en el puerto 8001. También puedes ejecutar `iniciar.ps1`, que arranca ambos procesos en segundo plano y guarda sus registros en `.runtime`.

## Primer acceso

La semilla genera un administrador `admin@uls.edu.sv` con contraseña aleatoria y enlace de activación local en **`.runtime/acceso-inicial.txt`**, archivo privado excluido de Git. No envía correos al sembrar datos. Incluye 6 docentes, 12 estudiantes, 3 grupos y 72 notas ficticias de septiembre y octubre de 2026. No ejecutes `migrate:fresh` sobre datos que quieras conservar.

**Administrador:** abre el login e ingresa el correo y tu contraseña (la temporal del archivo si aún no la has cambiado). No requiere código de Google Authenticator. En el primer acceso, el panel muestra el modal obligatorio para cambiar la clave.

**Estudiantes y docentes:**

1. Abre el botón del correo de bienvenida, personalizado según tu rol.
2. En Google Authenticator, pulsa `+` y escanea el QR. También existe una clave manual.
3. Confirma el código de seis dígitos. Entrarás directamente al panel.
4. En el modal del panel, ingresa la contraseña temporal del correo y elige una nueva de al menos 12 caracteres, con mayúsculas, minúsculas, números y símbolos. El panel no habilita las funciones hasta guardarla; puedes salir de la sesión si necesitas volver después.
5. En los siguientes accesos, ingresa correo, contraseña y un nuevo código de Google Authenticator.

Si la invitación inicial venció y aún no activaste la cuenta:

```powershell
cd backend
php artisan uls:activacion-local admin@uls.edu.sv
```

Para crear otro administrador y enviar su invitación real con SMTP:

```powershell
php artisan uls:admin correo@ejemplo.com --name="Nombre del administrador"
```

## Correos con Mailjet

### Restablecer una contraseña o un autenticador perdido

En **Docentes** o **Estudiantes**, el administrador puede pulsar **Restablecer QR** y confirmar el destinatario. Se envía al correo registrado un enlace de un solo uso, válido por 72 horas, para configurar un QR nuevo, junto con una contraseña temporal. El usuario confirma el nuevo código en Google Authenticator, entra al panel y debe cambiar esa clave temporal.

El restablecimiento reemplaza la contraseña, el secreto del autenticador y cualquier invitación anterior. Cierra las sesiones del usuario e invalida los intentos OTP y enlaces de recuperación de contraseña anteriores. Las notas y matrículas se conservan. Si falla el envío, la operación se revierte y el acceso anterior permanece vigente.

**Reenviar correo** conserva el autenticador de las cuentas activas; **Restablecer QR** lo reemplaza. Esta última acción solo está disponible para administradores y se aplica a docentes y estudiantes. El administrador sigue ingresando sin OTP, según la configuración solicitada.

La instalación local usa `backend/.env`, excluido de Git. Las credenciales entregadas están configuradas allí; no aparecen en Vue ni en `.env.example`. SMTP: `in-v3.mailjet.com`, puerto `587`, esquema `smtp` con STARTTLS automático. El remitente configurado debe estar validado en Mailjet.

En otras instalaciones, completa `MAIL_USERNAME` (API key), `MAIL_PASSWORD` (secret key), `MAIL_FROM_ADDRESS` y `FRONTEND_URL` en `.env`, luego ejecuta `php artisan config:clear`. Para pruebas sin envío real, usa `MAIL_MAILER=array`. Para inspeccionar correos localmente se puede usar `log`, pero ese modo escribe contraseñas temporales y enlaces en el registro: usar solo en práctica y no compartir esos registros.

Crear un docente desde el panel envía un correo con un botón de ingreso, contraseña temporal y enlace de QR de un solo uso, válido por 72 horas. Las cuentas pendientes admiten reenviar la invitación. Las cuentas activas también admiten reenviar el correo de acceso; conservan su contraseña y autenticador. Tanto docentes como estudiantes muestran su estado de activación y permiten editar sus datos, incluido el correo. Al cambiar el correo se envía una invitación o aviso al nuevo destinatario. La carrera de todos los estudiantes es `Licenciatura en ciencias de la computacion`. Crear un estudiante también crea su cuenta y envía el correo de activación. Para fichas creadas con la versión anterior, pulsa **Reenviar correo** para crear su acceso y enviar la primera invitación; no se modifican sus notas ni inscripciones. El rol estudiante solo puede consultar sus propias notas y grupos; no tiene acceso a informes, estadísticas, PDF ni CSV.

Las notas se envían individualmente al correo registrado del estudiante con el botón de sobre. Las pruebas automatizadas usan correo simulado y no envían mensajes reales.

## Funciones y permisos

| Función | Administrador | Docente |
|---|---|---|
| Gestionar docentes y estudiantes | Sí | No |
| Crear, editar y eliminar grupos | Sí | No |
| Ver grupos | Todos | Solo asignados |
| Registrar, editar, eliminar y enviar notas | Todos | Solo sus grupos e inscritos |
| Informes PDF, CSV y gráfico mensual | Todos | Solo sus grupos |

Los estudiantes acceden con el mismo flujo de QR, OTP y cambio de clave; únicamente consultan su información. No pueden modificar ni enviar notas, ni acceder a informes o estadísticas. Todos los roles disponen de **Mi perfil**, con edición de nombre y teléfono, datos de la cuenta y cambio de contraseña. El correo se modifica desde la administración. El menú lateral se puede contraer a iconos y expandir; en móvil se abre con el botón de menú.

Tablas con cinco registros por página, búsquedas, formularios con validación, confirmación de borrado y mensajes de resultado. PDF con visor PDF.js y descarga; CSV compatible con Excel y protección de fórmulas. Los filtros de informes se aplican al consultar; los botones de exportación usan los filtros seleccionados.

Sesiones HTTP-only, protección CSRF, limitación de intentos, desafío OTP de cinco minutos, secretos TOTP cifrados, tokens de invitación con hash y contraseñas con hash. El backend bloquea las funciones académicas hasta cambiar la contraseña. En producción usar HTTPS, `APP_DEBUG=false`, cookies seguras, copias de la base de datos y conservar `APP_KEY`; desplegar frontend y API con el mismo origen.

## Validación

### Bienvenida y boleta de inscripción

Al guardar correctamente la contraseña inicial aparece una bienvenida personalizada con confeti y el botón **Comenzar mi experiencia**. La animación respeta la preferencia de movimiento reducido. La bienvenida no se repite al recargar ni al cambiar la contraseña posteriormente desde el perfil.

Los estudiantes disponen de **Mi inscripción**, con selección del ciclo, vista PDF incrustada y descarga de la **boleta de inscripción**. El documento incluye el logo institucional, datos del estudiante, carrera, ciclo, asignaturas inscritas, grupos, docentes y fecha de emisión. Solo utiliza inscripciones registradas; no inventa horarios, créditos ni estados de pago. Si todavía no existen inscripciones, se muestra un aviso en lugar de generar una boleta vacía.

Las rutas `/api/enrollment` y `/api/enrollment/pdf` consultan al estudiante de la sesión. No aceptan identificadores de otros estudiantes. Esta boleta personal está permitida para el alumno; los informes y estadísticas generales siguen restringidos a docentes y administradores.

```powershell
npm.cmd run build
cd backend
php artisan test
```

Las pruebas cubren TOTP con vector RFC, activación y caducidad, login con dos factores, rechazo de reutilización, cambio obligatorio de clave, aislamiento docente, validación y duplicados de notas, correo simulado y exportación PDF/CSV.

La base de pruebas se ejecuta en SQLite en memoria para conservar los datos locales. También se comprobó el recorrido de acceso en Chrome, la carga de cinco notas por página, el formulario de captura, el renderizado del PDF y el menú en una pantalla móvil de 390 píxeles. La autenticación SMTP se verificó sin enviar un correo real.

Diagrama físico y decisiones académicas: [docs/base-de-datos.md](docs/base-de-datos.md). No se implementa el trámite de constancias de la imagen, ya que el alcance solicitado es Consulta de Notas.

Referencias: [VueUse QR](https://vueuse.org/integrations/useqrcode/), [AnimateIcons para Vue](https://animateicons.in/icons/docs/vue), [SMTP de Mailjet](https://dev.mailjet.com/docs/smtp-relay/configuration).


El botón **Salir del sistema** está en la barra superior y también en el cambio inicial de contraseña. Cierra la sesión del servidor y regresa al login. El backend local utiliza el puerto 8001 para evitar una instancia anterior bloqueada en el 8000. Si aparece un bloqueo de red SMTP, ejecuta el backend desde una terminal normal con acceso de red; el diagnóstico autenticado `/api/system/mail-status` permite verificar SMTP desde el proceso web sin enviar mensajes.
# parcial3Vue3_sv24461
