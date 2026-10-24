# Acceso remoto temporal para la práctica

El acceso remoto utiliza Cloudflare Quick Tunnel hacia una copia compilada del frontend en `127.0.0.1:4173`. Vite Preview reenvía `/api` a Laravel en `127.0.0.1:8001`. El servidor de desarrollo del puerto 5173 puede seguir utilizándose para editar, pero el enlace público sirve los archivos de `dist`.

## Consultar el enlace y las credenciales

- URL de la sesión: `.runtime/url-publica.txt`.
- Administrador de evaluación y contraseña privada: `.runtime/acceso-tunel.txt`.
- Diagnóstico del túnel: `.runtime/tunnel-error.log`.
- Registro de la conexión actualmente iniciada: `.runtime/cloudflare-session.log`.
- Diagnóstico del frontend compilado: `.runtime/preview-error.log`.

Abrir la URL HTTPS en la otra computadora. No necesita estar en la misma red. La computadora que aloja Laravel debe permanecer encendida y conectada a Internet, con el backend, Preview y cloudflared activos.

La preparación cambia la contraseña pública de `admin.prueba@uls.edu.sv` y cierra sus sesiones. La cuenta personal del administrador no se modifica. No ejecutar `uls:demo-admin --reset` mientras esté publicado: restauraría la contraseña pública del README.

## Invitaciones

La preparación configura `APP_URL` y `FRONTEND_URL` con el dominio temporal, activa cookies seguras y desactiva el diagnóstico público (`APP_DEBUG=false`). Las invitaciones nuevas usan el enlace HTTPS. Reenviar las invitaciones anteriores que todavía apunten a localhost o a una IP de la red.

El envío real de mensajes sigue dependiendo del transporte SMTP configurado. No se cambian las credenciales Mailjet. Los enlaces de invitación existentes no se actualizan dentro de correos ya entregados.

## Detener el acceso público

Desde la raíz, ejecutar en PowerShell:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .runtime/detener-tunel.ps1
```

Se detienen el túnel y Preview y se restauran las URLs, las cookies y el diagnóstico anteriores. Se conserva la contraseña privada del administrador de evaluación. Los datos académicos permanecen guardados.

## Volver a publicar

Los archivos auxiliares y el ejecutable están en `.runtime`, excluido de Git. Esta preparación corresponde a esta computadora; no forma parte de la instalación habitual del docente.

1. Detener la sesión pública anterior con el comando indicado.
2. Comprobar que Laravel esté activo en el puerto 8001.
3. Si hubo cambios visuales, ejecutar `npm.cmd run build`.
4. Ejecutar:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .runtime/iniciar-tunel.ps1
```

5. Consultar la nueva URL y contraseña en los archivos locales. El dominio temporal y la contraseña pueden cambiar; compartir los valores nuevos y reenviar las invitaciones necesarias.

## Límites

Es una sesión de demostración, no un despliegue permanente. El enlace es público y el acceso a los datos sigue protegido por el login de la aplicación. No compartir los archivos `.env`, `.runtime` o los registros. Una red que bloquee las conexiones salientes de cloudflared puede impedir establecer el túnel.

Referencia: [Cloudflare Quick Tunnels](https://developers.cloudflare.com/tunnel/get-started/quick-tunnels/).
