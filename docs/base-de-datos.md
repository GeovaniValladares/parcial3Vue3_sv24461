# Diagrama físico de la base de datos

SQLite para ejecución local; migraciones Eloquent compatibles con MySQL. Las tablas de soporte de Laravel son `sessions`, `password_reset_tokens`, `cache`, `cache_locks`, `jobs`, `job_batches` y `failed_jobs`.

```mermaid
erDiagram
 users ||--o{ academic_groups : imparte
 users ||--o{ grades : registra
 users o|--o| students : acceso_estudiantil
 students ||--o{ enrollments : inscribe
 academic_groups ||--o{ enrollments : contiene
 students ||--o{ grades : recibe
 academic_groups ||--o{ grades : evalua
 users {
  bigint id PK
  varchar name
  varchar email UK
  varchar password "hash bcrypt"
  varchar role "admin, teacher o student"
  varchar phone
  boolean must_change_password
  text totp_secret "cifrado con APP_KEY"
  timestamp totp_confirmed_at
  bigint totp_last_counter
  varchar invitation_token UK "SHA-256"
  timestamp invitation_expires_at
  timestamp last_login_at
 }
 students {
  bigint id PK
  bigint user_id FK "único, nullable para fichas anteriores"
  varchar student_code UK
  varchar name
  varchar email UK
  varchar career
 }
 academic_groups {
  bigint id PK
  varchar code UK
  varchar subject
  varchar cycle
  bigint teacher_id FK
 }
 enrollments {
  bigint id PK
  bigint student_id FK
  bigint academic_group_id FK
 }
 grades {
  bigint id PK
  bigint student_id FK
  bigint academic_group_id FK
  tinyint evaluation
  decimal score "4,2"
  date recorded_on
  text observations
  bigint created_by FK
 }
```

Todas las tablas académicas, salvo `enrollments`, incluyen `created_at` y `updated_at`. La matrícula es única por estudiante y grupo. La nota es única por estudiante, grupo y evaluación. Las claves foráneas restringen eliminar docentes, estudiantes y grupos con historial. Solo las matrículas se eliminan en cascada.

La captura usa tres parciales por asignatura, con notas de 0 a 10. En esta práctica, el promedio es aritmético de las notas filtradas y la aprobación requiere al menos 6.00. No se aplica una ponderación institucional no especificada. El gráfico agrupa por estudiante, por lo que cada estudiante cuenta una sola vez en la consulta.

La tabla `Solicitud_de_constancias` de la referencia corresponde a otro trámite. Este proyecto implementa Consulta de Notas y usa un modelo relacional académico con inscripciones.
