# Documentación de APIs REST - SBL Sistema Interno

## Índice
- [Configuración General](#configuración-general)
- [Autenticación](#autenticación)
- [API de Usuarios](#api-de-usuarios)
- [API de Calibraciones](#api-de-calibraciones)
- [API de Proveedores](#api-de-proveedores)
- [Códigos de Respuesta](#códigos-de-respuesta)
- [Ejemplos de Uso](#ejemplos-de-uso)

## Configuración General

### Base URL
```
http://localhost/SBL_SISTEMA_INTERNO/public/api/
```

### Headers Requeridos
```http
Content-Type: application/json
Accept: application/json
```

### Formato de Respuesta
Todas las APIs retornan respuestas en formato JSON:

**Respuesta Exitosa:**
```json
{
    "success": true,
    "data": { ... },
    "timestamp": "2024-01-15T10:30:00Z"
}
```

**Respuesta con Paginación:**
```json
{
    "success": true,
    "data": [...],
    "pagination": {
        "current_page": 1,
        "per_page": 20,
        "total_pages": 5,
        "total_items": 95,
        "has_next": true,
        "has_prev": false
    },
    "timestamp": "2024-01-15T10:30:00Z"
}
```

**Respuesta de Error:**
```json
{
    "success": false,
    "error": "Mensaje de error",
    "timestamp": "2024-01-15T10:30:00Z"
}
```

### Parámetros Comunes

**Paginación:**
- `page`: Número de página (por defecto: 1)
- `per_page`: Elementos por página (por defecto: 20, máximo: 100)

**Ordenamiento:**
- `sort_by`: Campo por el cual ordenar
- `sort_order`: `asc` o `desc` (por defecto: `asc`)

**Búsqueda:**
- `search`: Término de búsqueda general

## Autenticación

Todas las APIs requieren autenticación mediante sesión. El usuario debe estar logueado en el sistema y tener los permisos correspondientes.

La autenticación se verifica automáticamente mediante:
- Sesión PHP activa
- Usuario asociado a una empresa
- Permisos específicos por endpoint

## API de Usuarios

**Base URL:** `/api/usuarios.php`

### Endpoints

#### 1. Listar Usuarios
```http
GET /api/usuarios.php
```

**Parámetros de Query:**
- `activo`: `1` o `0` - Filtrar por estado activo
- `role_id`: ID del rol - Filtrar por rol
- `portal_id`: ID del portal - Filtrar por portal
- `search`: Término de búsqueda en nombre, apellidos, email

**Ejemplo:**
```http
GET /api/usuarios.php?activo=1&role_id=2&page=1&per_page=10&sort_by=nombre
```

#### 2. Obtener Usuario por ID
```http
GET /api/usuarios.php/{id}
```

#### 3. Crear Usuario
```http
POST /api/usuarios.php
```

**Body (JSON):**
```json
{
    "usuario": "nuevo_usuario",
    "correo": "usuario@empresa.com",
    "nombre": "Juan",
    "apellidos": "Pérez García",
    "puesto": "Técnico de Calibraciones",
    "contrasena": "password123",
    "role_id": 2,
    "portal_id": 1,
    "activo": 1
}
```

#### 4. Actualizar Usuario
```http
PUT /api/usuarios.php/{id}
```

**Body (JSON):**
```json
{
    "nombre": "Juan Carlos",
    "puesto": "Jefe de Calibraciones",
    "activo": 1
}
```

#### 5. Eliminar Usuario
```http
DELETE /api/usuarios.php/{id}
```

#### 6. Obtener Perfil del Usuario Actual
```http
GET /api/usuarios.php/profile
```

#### 7. Cambiar Estado de Usuario
```http
POST /api/usuarios.php/{id}/toggle-status
```

#### 8. Obtener Roles Disponibles
```http
GET /api/usuarios.php/roles
```

## API de Calibraciones

**Base URL:** `/api/calibraciones.php`

### Endpoints

#### 1. Listar Calibraciones
```http
GET /api/calibraciones.php
```

**Parámetros de Query:**
- `estado`: `Pendiente`, `Aprobado`, `Rechazado`
- `tipo`: Tipo de calibración
- `instrumento_id`: ID del instrumento
- `proveedor_id`: ID del proveedor
- `fecha_desde`: Fecha inicio (YYYY-MM-DD)
- `fecha_hasta`: Fecha fin (YYYY-MM-DD)
- `search`: Búsqueda en código/nombre de instrumento o proveedor

**Ejemplo:**
```http
GET /api/calibraciones.php?estado=Pendiente&fecha_desde=2024-01-01&sort_by=fecha_calibracion&sort_order=desc
```

#### 2. Obtener Calibración por ID
```http
GET /api/calibraciones.php/{id}
```

#### 3. Crear Calibración
```http
POST /api/calibraciones.php
```

**Body (JSON):**
```json
{
    "instrumento_id": 123,
    "fecha_calibracion": "2024-01-15",
    "tipo": "Externa",
    "periodo": "12",
    "proveedor_id": 5,
    "costo_total": 150.00,
    "duracion_horas": 2,
    "observaciones": "Calibración rutinaria",
    "lecturas": [
        {
            "punto_medicion": 1,
            "lectura_numero": 1,
            "valor_referencia": 100.0,
            "valor_medido": 99.8,
            "error": -0.2,
            "incertidumbre": 0.1
        }
    ]
}
```

#### 4. Actualizar Calibración
```http
PUT /api/calibraciones.php/{id}
```

#### 5. Eliminar Calibración
```http
DELETE /api/calibraciones.php/{id}
```

#### 6. Aprobar Calibración
```http
POST /api/calibraciones.php/{id}/approve
```

**Body (JSON):**
```json
{
    "resultado": "Conforme"
}
```

#### 7. Rechazar Calibración
```http
POST /api/calibraciones.php/{id}/reject
```

**Body (JSON):**
```json
{
    "observaciones": "Instrumento fuera de tolerancia"
}
```

#### 8. Obtener Estadísticas
```http
GET /api/calibraciones.php/stats
```

#### 9. Obtener Calibraciones Pendientes
```http
GET /api/calibraciones.php/pending
```

#### 10. Obtener Instrumentos Vencidos
```http
GET /api/calibraciones.php/overdue
```

#### 11. Obtener Datos de Dashboard
```http
GET /api/calibraciones.php/dashboard
```

## API de Proveedores

**Base URL:** `/api/proveedores.php`

### Endpoints

#### 1. Listar Proveedores
```http
GET /api/proveedores.php
```

**Parámetros de Query:**
- `activo`: `1` o `0` - Filtrar por estado activo
- `servicio`: Filtrar por servicio ofrecido
- `search`: Búsqueda en nombre, dirección, email, contacto

**Ejemplo:**
```http
GET /api/proveedores.php?activo=1&servicio=calibracion&sort_by=nombre
```

#### 2. Obtener Proveedor por ID
```http
GET /api/proveedores.php/{id}
```

#### 3. Crear Proveedor
```http
POST /api/proveedores.php
```

**Body (JSON):**
```json
{
    "nombre": "Laboratorio XYZ",
    "direccion": "Calle Principal 123, Ciudad",
    "telefono": "+52 55 1234 5678",
    "email": "contacto@laboratorioxyz.com",
    "contacto_principal": "Ing. María García",
    "servicios_ofrecidos": ["Calibración", "Mantenimiento", "Certificación"],
    "certificaciones": ["ISO 17025", "ISO 9001"],
    "activo": 1,
    "notas": "Proveedor confiable con amplia experiencia"
}
```

#### 4. Actualizar Proveedor
```http
PUT /api/proveedores.php/{id}
```

#### 5. Eliminar Proveedor
```http
DELETE /api/proveedores.php/{id}
```

#### 6. Cambiar Estado de Proveedor
```http
POST /api/proveedores.php/{id}/toggle-status
```

#### 7. Obtener Proveedores Activos (para selects)
```http
GET /api/proveedores.php/active
```

#### 8. Obtener Servicios Disponibles
```http
GET /api/proveedores.php/services
```

#### 9. Obtener Estadísticas
```http
GET /api/proveedores.php/stats
```

## Códigos de Respuesta

| Código | Descripción |
|--------|-------------|
| 200 | OK - Operación exitosa |
| 201 | Created - Recurso creado exitosamente |
| 400 | Bad Request - Datos inválidos o faltantes |
| 401 | Unauthorized - No autenticado |
| 403 | Forbidden - Sin permisos |
| 404 | Not Found - Recurso no encontrado |
| 422 | Unprocessable Entity - Error de validación |
| 500 | Internal Server Error - Error del servidor |

## Ejemplos de Uso

### JavaScript (Fetch API)

```javascript
// Función helper para hacer peticiones
async function apiRequest(endpoint, options = {}) {
    const response = await fetch(`/SBL_SISTEMA_INTERNO/public/api/${endpoint}`, {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...options.headers
        },
        ...options
    });
    
    const data = await response.json();
    
    if (!response.ok) {
        throw new Error(data.error || 'Error en la petición');
    }
    
    return data;
}

// Obtener usuarios activos
const usuarios = await apiRequest('usuarios.php?activo=1');

// Crear nueva calibración
const nuevaCalibration = await apiRequest('calibraciones.php', {
    method: 'POST',
    body: JSON.stringify({
        instrumento_id: 123,
        fecha_calibracion: '2024-01-15',
        tipo: 'Externa',
        periodo: '12',
        proveedor_id: 5
    })
});

// Aprobar calibración
await apiRequest('calibraciones.php/123/approve', {
    method: 'POST',
    body: JSON.stringify({
        resultado: 'Conforme'
    })
});
```

### jQuery

```javascript
// Configuración global para AJAX
$.ajaxSetup({
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

// Obtener estadísticas de calibraciones
$.get('/SBL_SISTEMA_INTERNO/public/api/calibraciones.php/stats')
    .done(function(response) {
        console.log('Estadísticas:', response.data);
    })
    .fail(function(xhr) {
        console.error('Error:', xhr.responseJSON.error);
    });

// Crear proveedor
$.ajax({
    url: '/SBL_SISTEMA_INTERNO/public/api/proveedores.php',
    method: 'POST',
    data: JSON.stringify({
        nombre: 'Nuevo Proveedor',
        email: 'proveedor@empresa.com',
        servicios_ofrecidos: ['Calibración', 'Mantenimiento']
    }),
    success: function(response) {
        console.log('Proveedor creado:', response.data);
    },
    error: function(xhr) {
        console.error('Error:', xhr.responseJSON.error);
    }
});
```

### PHP (usando cURL)

```php
function makeApiRequest($endpoint, $method = 'GET', $data = null) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://localhost/SBL_SISTEMA_INTERNO/public/api/$endpoint",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ]
    ]);
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode >= 400) {
        throw new Exception($result['error'] ?? 'Error en la petición');
    }
    
    return $result;
}

// Obtener usuarios
$usuarios = makeApiRequest('usuarios.php?activo=1');

// Crear calibración
$calibracion = makeApiRequest('calibraciones.php', 'POST', [
    'instrumento_id' => 123,
    'fecha_calibracion' => '2024-01-15',
    'tipo' => 'Externa'
]);
```

## Notas Importantes

1. **Permisos**: Cada endpoint requiere permisos específicos. Asegúrate de que el usuario tenga los permisos correctos.

2. **Validación**: Todos los datos de entrada son validados. Los campos requeridos deben proporcionarse.

3. **Transacciones**: Las operaciones complejas (crear calibración con lecturas) se realizan en transacciones para mantener la integridad.

4. **Logs**: Todas las operaciones importantes se registran en el log de actividades.

5. **Paginación**: Para listas grandes, usa paginación para mejorar el rendimiento.

6. **Filtros**: Utiliza los filtros disponibles para obtener exactamente los datos que necesitas.

7. **Errores**: Siempre maneja los errores apropiadamente en tu código cliente.

8. **Fechas**: Las fechas deben enviarse en formato ISO (YYYY-MM-DD) o ISO completo con hora.

9. **Arrays**: Los campos que aceptan arrays (como servicios_ofrecidos) pueden enviarse como array o como string separado por comas.

10. **Soft Delete**: Algunos recursos se eliminan lógicamente (cambiando estado) en lugar de eliminación física.