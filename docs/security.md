# Seguridad y privacidad

## Alcance

Este documento describe los controles de las Fases 1 y 2. No sustituye una auditoría de seguridad ni un análisis de riesgos formal. Las carencias conocidas se enumeran al final para que no se confundan con garantías.

## Autenticación

- Sesiones gestionadas por Laravel con `SESSION_DRIVER=database`.
- Contraseñas con hash de Laravel (bcrypt por defecto).
- Inicio de sesión por correo y contraseña.
- La sesión se regenera después de autenticar (`session()->regenerate()`), mitigando fijación de sesión.
- El cierre de sesión invalida la sesión y regenera el token CSRF.
- Recuperación de contraseña mediante token de un solo uso enviado por correo.
- Los correos se normalizan a minúsculas antes de buscar o crear cuentas.

### Política de contraseñas

- Registro: mínimo 12 caracteres, con letras y números, y confirmación.
- Longitud máxima: 72 caracteres (límite práctico de bcrypt).
- El administrador inicial debe tener al menos 12 caracteres; si no, el seeder no lo crea.
- No se permite registrar correos duplicados.

### Límites de intentos

| Acción | Límite | Ventana |
| --- | --- | --- |
| Inicio de sesión | 5 intentos por correo + IP | 60 segundos |
| Registro | 5 intentos por IP | 600 segundos |

Al superar el límite se devuelve un mensaje genérico y no se revela si el correo existe.

## Autorización

- Roles: `admin` y `user`.
- Estados: `active` y `suspended`.
- Middleware `active`: bloquea cuentas suspendidas.
- Middleware `admin`: restringe `/admin/*`.
- Un usuario suspendido no puede iniciar sesión ni permanecer autenticado en rutas protegidas.
- El panel de administración permite cambiar rol y estado, con estas protecciones:
  - Un administrador no puede suspender su propia cuenta.
  - Un administrador no puede cambiar su propio rol.
  - No se puede dejar el sistema sin al menos un administrador activo.
- Las acciones administrativas se registran en `audit_logs`.

### Acceso a documentos

- Cada documento pertenece a un único usuario (`user_id`).
- `DocumentPolicy` permite ver, descargar, reprocesar y eliminar solo al propietario.
- **Los administradores no reciben acceso al contenido documental.** Su panel no incluye rutas de documentos ajenos y la política no contempla una excepción administrativa.
- El listado filtra siempre por el usuario autenticado.
- La descarga pasa por una ruta autorizada de Laravel, no por Nginx ni por una URL pública.
- El archivo se sirve con `X-Content-Type-Options: nosniff` y el nombre original como nombre de descarga.

## Comunicación entre servicios

- FastAPI escucha solo en la red interna de Docker.
- El puerto de depuración se publica únicamente en `127.0.0.1:8001`.
- `X-Internal-Token` se compara en tiempo constante.
- El token debe tener al menos 32 caracteres.
- El token no se registra en logs ni se devuelve en respuestas.
- Los errores internos devuelven un mensaje genérico; el detalle queda en logs del servidor.

## Cabeceras de seguridad

Nginx y el middleware `SecurityHeaders` añaden:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`
- `X-Permitted-Cross-Domain-Policies: none`
- `server_tokens off` en Nginx

**No hay Content-Security-Policy (CSP) todavía.** Debe añadirse y probarse antes de exponer la aplicación fuera de localhost.

## Carga de archivos

La carga se valida en varias capas:

1. **Tamaño:** máximo 25 MB (`SCITRACE_MAX_UPLOAD_KB`, `LIVEWIRE_MAX_UPLOAD_KB` y `AI_MAX_DOCUMENT_BYTES`).
2. **Extensión:** solo `.docx` y `.pdf`.
3. **MIME declarado:** Laravel usa `mimes:docx,pdf`; FastAPI acepta únicamente los MIME de DOCX/PDF o `application/octet-stream`.
4. **Firma real:** Laravel comprueba que el contenido empiece por `%PDF-` (PDF) o `PK\x03\x04` (DOCX/ZIP). Un archivo renombrado se rechaza aunque el MIME declarado sea correcto.
5. **Consentimiento:** el formulario exige aceptar expresamente que existe autorización para procesar el documento y que no se usará para entrenar sin autorización adicional.
6. **Integridad:** se calcula SHA-256 al cargar y se vuelve a calcular en el job antes de llamar al motor.

Los archivos temporales de Livewire usan el disco privado `local`, no `public`. Si la creación del registro falla, el archivo almacenado se elimina.

### Límites del motor

- `AI_MAX_DOCUMENT_BYTES` (25 MB) y `AI_MAX_SEGMENTS` (10 000) evitan consumos desproporcionados.
- `AI_EXTRACTION_TIMEOUT_SECONDS` (120) limita el tiempo de la llamada.
- El archivo se procesa en memoria y no se persiste en FastAPI.
- Los errores controlados devuelven códigos estables sin incluir rutas internas ni trazas.

## Almacenamiento privado

- Los documentos van a `storage/app/private`, fuera de `public/`.
- No se crea enlace simbólico público para documentos.
- Los nombres originales se guardan como metadatos; el archivo usa un nombre generado.
- Se calcula SHA-256 para integridad.
- La descarga, cuando exista, deberá pasar por una ruta autorizada de Laravel, nunca por Nginx directamente.

## Secretos y configuración

- `.env` está en `.gitignore`; `.env.example` solo contiene marcadores.
- `scripts/bootstrap.ps1` genera secretos aleatorios localmente.
- No se incluyen credenciales reales en el repositorio.
- En producción:
  - `APP_DEBUG=false`
  - `APP_ENV=production`
  - `SESSION_SECURE_COOKIE=true` detrás de HTTPS
  - `SESSION_ENCRYPT=true` si el despliegue lo requiere
  - `AI_ENVIRONMENT=production` para desactivar `/docs`
  - Contraseñas y tokens gestionados fuera del repositorio

## Registro y auditoría

Se registran, entre otros:

- `auth.login`
- `auth.login_failed` (con hash del correo, no el correo en claro)
- `auth.registered`
- `auth.logout`
- `auth.password_reset`
- `admin.user.status_changed`
- `admin.user.role_changed`
- `document.uploaded`
- `document.processed`
- `document.processing_failed`
- `document.reprocessed`
- `document.deleted`

Los logs no deben incluir contraseñas, tokens ni contenido de documentos. El formateador JSON de FastAPI evita registrar cuerpos. La auditoría de documentos registra metadatos (extensión, tamaño, códigos de error), no el texto extraído.

## Privacidad y LOPDP (Ecuador)

Diseño orientado a la Ley Orgánica de Protección de Datos Personales:

- **Minimización:** solo se recogen los datos necesarios para la cuenta y el análisis.
- **Finalidad:** los documentos se usan para el análisis solicitado, no para publicidad.
- **Consentimiento:** la carga exige una declaración explícita; se registra `metadata.consent` y `metadata.consent_at`. No se usarán documentos para entrenar modelos sin autorización adicional y registrada.
- **Acceso:** solo el propietario puede ver, descargar, reprocesar o eliminar su documento.
- **Retención:** valor previsto de 365 días (`SCITRACE_DOCUMENT_RETENTION_DAYS`), aún no aplicado automáticamente.
- **Trazabilidad:** auditoría de accesos y acciones administrativas.
- **Derechos:** el diseño debe permitir acceso, rectificación, eliminación y portabilidad; la interfaz completa llegará en fases posteriores.

Los documentos académicos pueden contener datos personales o información confidencial. No deben subirse a servicios de terceros sin base legal y salvaguardas.

## Riesgos y carencias conocidas

| Riesgo | Estado |
| --- | --- |
| Sin TLS en local | Aceptado solo para `127.0.0.1`; producción requiere HTTPS |
| Sin CSP | Pendiente antes de exponer públicamente |
| Sin verificación de correo | Pendiente |
| Sin 2FA | Pendiente |
| Sin escaneo antimalware de archivos | Pendiente; la validación de firma no sustituye un antivirus |
| Sin cifrado de disco gestionado por la aplicación | Depende de la infraestructura |
| Sin límite de intentos específico en la carga de documentos | Mitigado por autenticación, tamaño máximo y cola; revisar en Fase 6 |
| Sin aislamiento de procesos para documentos hostiles | El motor procesa en un contenedor separado; falta endurecer límites de CPU/memoria |
| Sin backups automatizados | Pendiente (Fase 6) |
| Sin purga de retención | Pendiente (Fase 6) |
| Sin WAF ni protección DDoS | Fuera del alcance inicial |

## Reporte de vulnerabilidades

No publiques detalles explotables en un issue público. Contacta al responsable del repositorio por un canal privado y adjunta:

- Versión o commit afectado.
- Pasos de reproducción.
- Impacto estimado.
- Mitigación sugerida, si la conoces.
