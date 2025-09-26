# API REST de Seguridad - Sistema GAMP 5

## Descripción General

Esta API REST proporciona acceso programático a todas las funcionalidades del sistema de administración de seguridad, implementado según las mejores prácticas de GAMP 5 y normativas GxP.

## Base URL

```
https://[tu-dominio]/api/security
```

## Autenticación

La API utiliza autenticación JWT (JSON Web Tokens). Todos los endpoints excepto `/auth/login` requieren un token válido en el header `Authorization`.

```
Authorization: Bearer <token>
```

## Códigos de Respuesta

- `200` - Éxito
- `201` - Creado
- `400` - Solicitud incorrecta
- `401` - No autorizado
- `403` - Prohibido
- `404` - No encontrado
- `409` - Conflicto
- `500` - Error interno del servidor

## Formato de Respuesta

### Respuesta Exitosa
```json
{
  "success": true,
  "data": {
    // Datos de respuesta
  },
  "timestamp": "2024-01-15T10:30:00Z"
}
```

### Respuesta de Error
```json
{
  "success": false,
  "error": {
    "message": "Descripción del error",
    "code": 400
  },
  "timestamp": "2024-01-15T10:30:00Z"
}
```

## Endpoints

### 1. Autenticación (`/auth`)

#### `POST /auth/login`
Autentica un usuario y retorna tokens de acceso.

**Request:**
```json
{
  "username": "usuario",
  "password": "contraseña",
  "mfa_code": "123456" // Opcional, requerido si MFA está habilitado
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "refresh_token": "refresh_token_here",
    "expires_in": 3600,
    "user": {
      "id": 1,
      "username": "usuario",
      "role": "Administrador"
    }
  }
}
```

#### `POST /auth/refresh`
Renueva un token de acceso usando el refresh token.

**Request:**
```json
{
  "refresh_token": "refresh_token_here"
}
```

#### `POST /auth/logout`
Invalida el token actual.

#### `GET /auth/verify`
Verifica la validez del token actual.

### 2. Autenticación Multifactor (`/mfa`)

#### `POST /mfa/setup`
Configura MFA para el usuario actual.

**Response:**
```json
{
  "success": true,
  "data": {
    "qr_code": "data:image/png;base64,iVBORw0KGgoAAAANS...",
    "secret": "JBSWY3DPEHPK3PXP",
    "backup_codes": ["12345678", "87654321", ...]
  }
}
```

#### `POST /mfa/verify`
Verifica un código MFA.

**Request:**
```json
{
  "code": "123456"
}
```

#### `POST /mfa/disable`
Desactiva MFA para el usuario.

**Request:**
```json
{
  "password": "contraseña_actual"
}
```

#### `GET /mfa/backup-codes`
Obtiene códigos de respaldo no utilizados.

#### `POST /mfa/backup-codes`
Regenera códigos de respaldo.

#### `GET /mfa/status/{user_id?}`
Obtiene el estado de MFA del usuario.

### 3. Auditoría (`/audit`)

#### `GET /audit/logs`
Obtiene logs de auditoría con filtros opcionales.

**Query Parameters:**
- `user_id` - ID del usuario
- `action` - Acción específica
- `date_from` - Fecha desde (YYYY-MM-DD)
- `date_to` - Fecha hasta (YYYY-MM-DD)
- `level` - Nivel de log (INFO, WARNING, ERROR, CRITICAL)
- `category` - Categoría del log
- `page` - Página (default: 1)
- `limit` - Límite por página (default: 50, max: 1000)

**Response:**
```json
{
  "success": true,
  "data": {
    "logs": [
      {
        "id": 1,
        "user_id": 1,
        "action": "LOGIN_SUCCESS",
        "details": {...},
        "level": "INFO",
        "category": "ACCESS_CONTROL",
        "created_at": "2024-01-15T10:30:00Z"
      }
    ],
    "total": 1500,
    "page": 1,
    "limit": 50,
    "pages": 30
  }
}
```

#### `POST /audit/search`
Búsqueda avanzada en logs de auditoría.

**Request:**
```json
{
  "search_term": "login failed",
  "date_from": "2024-01-01",
  "date_to": "2024-01-15",
  "level": "ERROR"
}
```

#### `POST /audit/export`
Exporta logs de auditoría a CSV.

**Request:**
```json
{
  "date_from": "2024-01-01",
  "date_to": "2024-01-15",
  "format": "csv"
}
```

#### `GET /audit/integrity`
Verifica la integridad de los logs de auditoría.

**Query Parameters:**
- `date_from` - Fecha desde
- `date_to` - Fecha hasta

### 4. Gestión de Contraseñas (`/password`)

#### `POST /password/change`
Cambia la contraseña del usuario actual.

**Request:**
```json
{
  "current_password": "contraseña_actual",
  "new_password": "nueva_contraseña"
}
```

#### `POST /password/reset`
Resetea la contraseña de un usuario (requiere permisos de admin).

**Request:**
```json
{
  "user_id": 123
}
```

#### `GET /password/policy`
Obtiene la política de contraseñas actual.

#### `PUT /password/policy`
Actualiza la política de contraseñas (requiere permisos de admin).

**Request:**
```json
{
  "min_length": 8,
  "require_uppercase": true,
  "require_lowercase": true,
  "require_numbers": true,
  "require_special_chars": true,
  "max_age_days": 90,
  "history_count": 5
}
```

#### `POST /password/validate`
Valida una contraseña contra la política actual.

**Request:**
```json
{
  "password": "contraseña_a_validar"
}
```

#### `GET /password/history/{user_id?}`
Obtiene el historial de cambios de contraseña.

### 5. Monitoreo de Seguridad (`/monitor`)

#### `GET /monitor/dashboard`
Obtiene el dashboard de seguridad con métricas en tiempo real.

**Response:**
```json
{
  "success": true,
  "data": {
    "active_threats": 5,
    "blocked_ips": 12,
    "failed_logins_24h": 45,
    "successful_logins_24h": 320,
    "system_health_score": 95,
    "last_backup": "2024-01-15T02:00:00Z",
    "alerts": [
      {
        "id": 1,
        "type": "BRUTE_FORCE",
        "severity": "HIGH",
        "message": "Multiple failed login attempts detected",
        "created_at": "2024-01-15T10:25:00Z"
      }
    ]
  }
}
```

#### `GET /monitor/threats`
Obtiene amenazas activas.

**Query Parameters:**
- `timeframe` - Marco temporal (1h, 24h, 7d, 30d)
- `severity` - Severidad (LOW, MEDIUM, HIGH, CRITICAL)

#### `GET /monitor/metrics`
Obtiene métricas de seguridad.

**Query Parameters:**
- `period` - Período (hour, day, week, month)

#### `GET /monitor/alerts`
Obtiene alertas de seguridad.

**Query Parameters:**
- `status` - Estado (unacknowledged, acknowledged, resolved)
- `limit` - Límite de resultados

#### `POST /monitor/alerts`
Confirma una alerta de seguridad.

**Request:**
```json
{
  "alert_id": 123,
  "comment": "Falso positivo - tráfico legítimo"
}
```

#### `POST /monitor/scan`
Ejecuta un escaneo de seguridad.

**Request:**
```json
{
  "scan_type": "full" // full, vulnerabilities, permissions
}
```

### 6. Respaldos (`/backup`)

#### `POST /backup/create`
Crea un nuevo respaldo.

**Request:**
```json
{
  "type": "FULL" // FULL o INCREMENTAL
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "backup_id": "FULL_2024-01-15_02-00-00_abc123",
    "backup_path": "/storage/backups/FULL_2024-01-15_02-00-00_abc123",
    "size_mb": 1250.75,
    "results": {
      "database": {"success": true, "size": 850000000},
      "files": {"success": true, "size": 400000000},
      "config": {"success": true, "size": 5000}
    }
  }
}
```

#### `GET /backup/list`
Lista respaldos disponibles.

**Query Parameters:**
- `type` - Tipo de respaldo (FULL, INCREMENTAL)
- `status` - Estado (COMPLETED, FAILED, VERIFIED)
- `limit` - Límite de resultados
- `offset` - Desplazamiento

#### `POST /backup/verify`
Verifica la integridad de un respaldo.

**Request:**
```json
{
  "backup_id": "FULL_2024-01-15_02-00-00_abc123"
}
```

#### `POST /backup/restore`
Restaura desde un respaldo (requiere permisos de admin).

**Request:**
```json
{
  "backup_id": "FULL_2024-01-15_02-00-00_abc123",
  "components": ["database", "files", "config"],
  "test_mode": false
}
```

#### `GET /backup/statistics`
Obtiene estadísticas de respaldos.

#### `GET /backup/schedule`
Obtiene la programación de respaldos automáticos.

#### `POST /backup/schedule`
Actualiza la programación de respaldos.

### 7. Cumplimiento Normativo (`/compliance`)

#### `GET /compliance/report`
Genera un reporte de cumplimiento.

**Query Parameters:**
- `type` - Tipo de reporte (general, gamp5, 21cfr11, gdpr)
- `date_from` - Fecha desde
- `date_to` - Fecha hasta

**Response:**
```json
{
  "success": true,
  "data": {
    "report_type": "gamp5",
    "period": {
      "from": "2024-01-01",
      "to": "2024-01-15"
    },
    "generated_at": "2024-01-15T10:30:00Z",
    "data": {
      "data_integrity": {
        "audit_trail_completeness": {
          "percentage": 99.8,
          "compliant": true
        },
        "data_backup_compliance": {
          "success_rate": 100.0,
          "compliant": true
        }
      },
      "validation_status": {
        "system_validation": "Current",
        "last_validation_date": "2024-01-01",
        "next_validation_due": "2025-01-01"
      }
    }
  }
}
```

#### `GET /compliance/gamp5`
Estado de cumplimiento GAMP 5.

#### `GET /compliance/21cfr11`
Estado de cumplimiento 21 CFR Part 11.

#### `GET /compliance/gdpr`
Estado de cumplimiento GDPR.

#### `GET /compliance/audit-trail`
Evaluación del audit trail para cumplimiento.

## Códigos de Ejemplo

### JavaScript/Fetch
```javascript
// Login
const loginResponse = await fetch('/api/security/auth/login', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    username: 'usuario',
    password: 'contraseña'
  })
});

const loginData = await loginResponse.json();
const token = loginData.data.access_token;

// Usar token en requests posteriores
const dashboardResponse = await fetch('/api/security/monitor/dashboard', {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});
```

### PHP/cURL
```php
// Login
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://tu-dominio/api/security/auth/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'username' => 'usuario',
    'password' => 'contraseña'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$data = json_decode($response, true);
$token = $data['data']['access_token'];

// Usar token
curl_setopt($ch, CURLOPT_URL, 'https://tu-dominio/api/security/monitor/dashboard');
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $token"
]);

curl_close($ch);
```

### Python/Requests
```python
import requests

# Login
login_response = requests.post('https://tu-dominio/api/security/auth/login', 
    json={
        'username': 'usuario',
        'password': 'contraseña'
    }
)

token = login_response.json()['data']['access_token']

# Usar token
headers = {'Authorization': f'Bearer {token}'}
dashboard_response = requests.get('https://tu-dominio/api/security/monitor/dashboard', 
    headers=headers
)
```

## Rate Limiting

La API implementa rate limiting para prevenir abuso:
- 100 requests por minuto por IP para endpoints generales
- 10 requests por minuto para endpoints de autenticación
- 5 requests por minuto para operaciones críticas (backup/restore)

## Versionado

La API sigue versionado semántico. La versión actual es v1. Futuras versiones mantendrán compatibilidad hacia atrás cuando sea posible.

## Soporte y Contacto

Para soporte técnico o preguntas sobre la API, contactar al equipo de desarrollo del sistema.

## Changelog

### v1.0.0 (2024-01-15)
- Implementación inicial de la API REST
- Todos los módulos de seguridad incluidos
- Cumplimiento completo con GAMP 5 y normativas GxP