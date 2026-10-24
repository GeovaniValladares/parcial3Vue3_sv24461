# Guía de evaluación — Consulta de Notas ULS

Instalar y ejecutar siguiendo el [README](../README.md). Mantener abiertos el backend en `127.0.0.1:8001` y el frontend en `127.0.0.1:5173`.

## Acceso directo de evaluación

| Campo | Valor |
|---|---|
| URL | http://127.0.0.1:5173/login |
| Correo | `admin.prueba@uls.edu.sv` |
| Contraseña | `PruebaULS@2026!` |

Esta cuenta entra al panel sin QR, sin código OTP y sin cambio inicial obligatorio. Si no existe, ejecutar `php artisan uls:demo-admin` dentro de `backend`. Para restaurar exclusivamente sus credenciales, ejecutar `php artisan uls:demo-admin --reset`. Ambos comandos son para evaluación local.

## 1. Recorrido inicial

La instalación nueva contiene datos ficticios: 6 docentes, 12 estudiantes, 3 grupos, 36 matrículas y 72 notas de septiembre y octubre de 2026. Las contraseñas de los docentes ficticios no son públicas; crear cuentas nuevas para comprobar su acceso.

| Acción | Resultado esperado |
|---|---|
| Entrar con el administrador de prueba | Panel sin solicitud de autenticador. |
| Contraer y expandir el menú | Se mantiene disponible la navegación por módulos. |
| Abrir Gestión de notas | Tabla con cinco registros por página y controles de búsqueda. |
| Abrir Docentes y Estudiantes | Crear, editar, estado de activación y acciones de correo. |
| Abrir Grupos académicos | Materia, ciclo, docente e integrantes del grupo. |
| Abrir Mi perfil | Datos propios y opciones de contraseña. |

## 2. PDF, Excel y estadísticas

1. En **Informes y estadísticas**, seleccionar octubre de 2026 y consultar. Si se está evaluando una base previamente modificada, los totales pueden variar.
2. Revisar el gráfico de estudiantes aprobados y reprobados. El umbral de aprobación es 6; se consideran los estudiantes con notas en la selección.
3. Generar el PDF: debe abrirse incrustado, con identidad institucional, filtros y tabla. Probar su descarga.
4. Exportar Excel y abrir el archivo `.xlsx` en Excel o un visor compatible. Verificar metadatos superiores, encabezado azul, totales verdes, columnas ordenadas y notas numéricas.
5. Abrir la vista de impresión del libro: papel A4 horizontal, ajuste a una página de ancho y encabezados repetidos. Si hay muchas filas puede ocupar varias páginas de alto.

## 3. Preparar las pruebas de correo

Para evaluar sin una cuenta SMTP, utilizar en `backend/.env`:

```dotenv
MAIL_MAILER=log
```

Ejecutar `php artisan config:clear` y reiniciar el servidor PHP. Las nuevas instalaciones ya usan este valor desde `.env.example`. Este modo guarda los mensajes en `backend/storage/logs/laravel.log`; no entrega mensajes a un buzón real.

Abrir ese archivo en el editor, buscar el destinatario de prueba y revisar el último mensaje correspondiente. El correo contiene el enlace `/activar/...` y la contraseña temporal. El registro utiliza formato de correo MIME: si aparecen líneas terminadas en `=` o secuencias como `=3D`, corresponden a la codificación quoted-printable y deben decodificarse para recuperar el enlace completo. En el contenido HTML, las entidades como `&amp;` representan caracteres de la contraseña. Un visor de correo MIME permite leer el mensaje renderizado.

Como alternativa disponible para una cuenta **todavía sin activar**, el comando local siguiente regenera el acceso y guarda enlace y contraseña como texto legible:

```powershell
php artisan uls:activacion-local docente.evaluacion@example.invalid
```

El comando escribe **`.runtime/acceso-inicial.txt` y reemplaza su contenido anterior**. Guardar previamente ese archivo si se desea conservarlo. También invalida el enlace anterior de esa cuenta. No se necesita este comando para el administrador de prueba; no usarlo en cuentas personales ni en cuentas ya activadas.

Para probar recepción real, seguir la configuración Mailjet del README y utilizar una dirección controlada por el evaluador. Los dominios `example.invalid` de esta guía son exclusivamente para pruebas con `log`.

## 4. Docente y grupo de evaluación

1. Como administrador, crear un docente con nombre **Docente de evaluación** y correo `docente.evaluacion@example.invalid` en modo `log`. Revisar la bienvenida institucional y el estado pendiente.
2. Crear un grupo, por ejemplo **EVAL-01**, materia **Programación de evaluación**, ciclo **II-2026**, asignado al docente recién creado. Usar un código diferente si ya existe.
3. Obtener el enlace de activación y contraseña temporal mediante el mensaje local o el comando descrito arriba.
4. Abrir el enlace en una ventana privada para conservar la sesión del administrador en la ventana normal.
5. Verificar el texto de bienvenida y la instrucción para escanear el QR. Agregarlo a Google Authenticator e ingresar sus seis dígitos. Mantener la hora automática del teléfono activada.
6. Al confirmar, se abre el panel y aparece el cambio obligatorio de contraseña. Introducir la contraseña temporal y una nueva de al menos 12 caracteres, con mayúsculas, minúsculas, número y símbolo. Un ejemplo exclusivo de práctica es `DocentePrueba@2026!`.
7. Guardar: debe aparecer la bienvenida con confeti. El movimiento puede reducirse según las preferencias de accesibilidad del navegador.
8. Cerrar sesión y entrar con correo y nueva clave. Ahora se solicita el código del autenticador. Esperar un código nuevo si el anterior ya se utilizó.
9. Revisar que el docente solo pueda trabajar con sus grupos asignados.

## 5. Estudiante, inscripción y notas

1. En la ventana del administrador, crear **Estudiante de evaluación**, correo `estudiante.evaluacion@example.invalid`, carné **EVAL001**, carrera **Licenciatura en ciencias de la computacion**, e inscribirlo en EVAL-01. Si los datos ya existen, usar otros únicos.
2. Revisar edición, estado de activación y reenvío del correo. Para una cuenta pendiente, el reenvío renueva su invitación: utilizar el último enlace.
3. Como administrador o como docente asignado, registrar una nota **8.5**, parcial **1**, con la fecha de hoy para ese estudiante y grupo.
4. Intentar guardar una nota fuera de 0–10 o repetir el mismo estudiante, grupo y parcial. Debe rechazarse con una validación y conservarse la información válida.
5. Cerrar la sesión docente de la ventana privada. Abrir el enlace de activación del estudiante, configurar su QR y cambiar la contraseña temporal al entrar al panel, siguiendo el mismo flujo anterior.
6. Confirmar que el estudiante ve sus datos, grupos y notas. No debe aparecer **Informes y estadísticas** ni tener acceso a informes globales.
7. Abrir su **boleta de inscripción**, revisar el PDF incrustado con logo, datos personales, ciclo y asignaturas; descargarlo.
8. Revisar Mi perfil y cerrar sesión. El acceso posterior requiere su contraseña nueva y OTP.

Para revisar el registro recién creado en informes, seleccionar el mes de su fecha de evaluación. Octubre de 2026 corresponde a los datos iniciales, no necesariamente a la fecha de ejecución de esta práctica.

## 6. Recuperación de acceso

Realizar estas pruebas únicamente con las cuentas recién creadas:

| Caso | Pasos y resultado esperado |
|---|---|
| Olvidó la contraseña, conserva el autenticador | Usar recuperación desde el login, revisar el mensaje y abrir su enlace antes de 30 minutos. Cambiar la contraseña. El autenticador existente se conserva. |
| Perdió el autenticador | Como administrador, usar la acción de restablecer acceso/QR del docente o estudiante. Se genera una nueva invitación con contraseña temporal y QR; se cierran sus sesiones anteriores. |
| Configuración del nuevo QR | Abrir la invitación más reciente, registrar el nuevo QR y completar la configuración y cambio de clave. El QR anterior deja de servir. |
| Solo necesita otro correo | Reenviar correo. Una cuenta ya activa recibe indicaciones para acceder; esta acción no sustituye el restablecimiento de QR. |
| Falla de envío SMTP | Si falla el transporte de correo, se informa el error y se revierte la creación o el restablecimiento. Con `log` se puede evaluar sin depender de Internet. |

## 7. Finalizar

Pulsar **Salir del sistema**. Intentar abrir una ruta privada: debe solicitar acceso nuevamente. Los datos de evaluación quedan guardados para revisarlos; no es necesario reiniciar ni borrar la base.

Las pruebas automáticas están indicadas en el README. Usan una base SQLite en memoria y correo simulado; no envían mensajes a los destinatarios de la práctica ni borran la base local. No entregar `.env`, registros de correo ni archivos privados de `.runtime`.
