# Ejemplos de Pruebas de APIs REST

## Configuración de Entorno de Pruebas

### 1. Usando Postman

**Configurar Colección Base:**
1. Crear nueva colección "SBL APIs"
2. Configurar variables de entorno:
   - `base_url`: `http://localhost/SBL_SISTEMA_INTERNO/public/api`
   - `user_id`: ID del usuario actual
   - `empresa_id`: ID de la empresa

**Headers Globales:**
```
Content-Type: application/json
Accept: application/json
```

### 2. Usando Thunder Client (VS Code)

**Configuración del archivo de entorno:**
```json
{
  "SBL_LOCAL": {
    "base_url": "http://localhost/SBL_SISTEMA_INTERNO/public/api",
    "content_type": "application/json"
  }
}
```

## Pruebas de API de Usuarios

### 1. Listar Usuarios Activos

**Request:**
```http
GET {{base_url}}/usuarios.php?activo=1&per_page=5
```

**Respuesta Esperada:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "usuario": "admin",
            "correo": "admin@empresa.com",
            "nombre": "Administrador",
            "apellidos": "Sistema",
            "puesto": "Administrador General",
            "activo": 1,
            "role_name": "Administrador",
            "portal_name": "Portal Interno"
        }
    ],
    "pagination": {
        "current_page": 1,
        "per_page": 5,
        "total_pages": 1,
        "total_items": 1
    }
}
```

### 2. Crear Usuario de Prueba

**Request:**
```http
POST {{base_url}}/usuarios.php
Content-Type: application/json

{
    "usuario": "test_user",
    "correo": "test@empresa.com",
    "nombre": "Usuario",
    "apellidos": "De Prueba",
    "puesto": "Técnico de Calibraciones",
    "contrasena": "password123",
    "role_id": 2,
    "portal_id": 1,
    "activo": 1
}
```

### 3. Obtener Perfil de Usuario

**Request:**
```http
GET {{base_url}}/usuarios.php/profile
```

### 4. Actualizar Usuario

**Request:**
```http
PUT {{base_url}}/usuarios.php/2
Content-Type: application/json

{
    "puesto": "Jefe de Calibraciones",
    "activo": 1
}
```

## Pruebas de API de Calibraciones

### 1. Obtener Estadísticas

**Request:**
```http
GET {{base_url}}/calibraciones.php/stats
```

**Respuesta Esperada:**
```json
{
    "success": true,
    "data": {
        "total": 45,
        "pendientes": 8,
        "aprobadas": 35,
        "rechazadas": 2,
        "vencidas": 3,
        "por_vencer": 5,
        "costo_total": 12500.00,
        "duracion_promedio": 2.5,
        "por_tipo": [
            {"tipo": "Externa", "cantidad": 30},
            {"tipo": "Interna", "cantidad": 15}
        ],
        "por_mes": [
            {"mes": "2024-01", "cantidad": 8, "costo": 2400.00}
        ]
    }
}
```

### 2. Crear Calibración

**Request:**
```http
POST {{base_url}}/calibraciones.php
Content-Type: application/json

{
    "instrumento_id": 1,
    "fecha_calibracion": "2024-01-15",
    "tipo": "Externa",
    "periodo": "12",
    "proveedor_id": 1,
    "costo_total": 150.00,
    "duracion_horas": 2,
    "observaciones": "Calibración de prueba",
    "lecturas": [
        {
            "punto_medicion": 1,
            "lectura_numero": 1,
            "valor_referencia": 100.0,
            "valor_medido": 99.8,
            "error": -0.2,
            "incertidumbre": 0.1,
            "observaciones": "Lectura dentro de tolerancia"
        },
        {
            "punto_medicion": 2,
            "lectura_numero": 1,
            "valor_referencia": 200.0,
            "valor_medido": 199.9,
            "error": -0.1,
            "incertidumbre": 0.15
        }
    ]
}
```

### 3. Obtener Calibraciones Pendientes

**Request:**
```http
GET {{base_url}}/calibraciones.php/pending
```

### 4. Aprobar Calibración

**Request:**
```http
POST {{base_url}}/calibraciones.php/1/approve
Content-Type: application/json

{
    "resultado": "Conforme"
}
```

### 5. Buscar Calibraciones con Filtros

**Request:**
```http
GET {{base_url}}/calibraciones.php?estado=Pendiente&fecha_desde=2024-01-01&search=termómetro&sort_by=fecha_calibracion&sort_order=desc
```

## Pruebas de API de Proveedores

### 1. Crear Proveedor

**Request:**
```http
POST {{base_url}}/proveedores.php
Content-Type: application/json

{
    "nombre": "Laboratorio de Pruebas XYZ",
    "direccion": "Av. Tecnológica 123, Col. Industrial, Ciudad de México",
    "telefono": "+52 55 1234 5678",
    "email": "contacto@labxyz.com",
    "contacto_principal": "Ing. María García López",
    "servicios_ofrecidos": ["Calibración", "Mantenimiento", "Certificación ISO"],
    "certificaciones": ["ISO 17025", "ISO 9001", "CENAM"],
    "activo": 1,
    "notas": "Laboratorio especializado en instrumentos de medición con más de 10 años de experiencia"
}
```

### 2. Obtener Proveedores Activos

**Request:**
```http
GET {{base_url}}/proveedores.php/active
```

### 3. Obtener Estadísticas de Proveedores

**Request:**
```http
GET {{base_url}}/proveedores.php/stats
```

### 4. Cambiar Estado de Proveedor

**Request:**
```http
POST {{base_url}}/proveedores.php/1/toggle-status
```

## Pruebas de Casos de Error

### 1. Usuario Sin Permisos

**Request:**
```http
DELETE {{base_url}}/usuarios.php/1
```

**Respuesta Esperada:**
```json
{
    "success": false,
    "error": "Sin permisos para eliminar usuarios",
    "timestamp": "2024-01-15T10:30:00Z"
}
```

### 2. Recurso No Encontrado

**Request:**
```http
GET {{base_url}}/calibraciones.php/99999
```

**Respuesta Esperada:**
```json
{
    "success": false,
    "error": "Calibración no encontrada",
    "timestamp": "2024-01-15T10:30:00Z"
}
```

### 3. Datos Faltantes

**Request:**
```http
POST {{base_url}}/usuarios.php
Content-Type: application/json

{
    "nombre": "Juan"
}
```

**Respuesta Esperada:**
```json
{
    "success": false,
    "error": "Campo requerido faltante: usuario",
    "timestamp": "2024-01-15T10:30:00Z"
}
```

## Scripts de Prueba Automatizada

### JavaScript (Node.js con axios)

```javascript
const axios = require('axios');

class SBLApiTester {
    constructor() {
        this.baseURL = 'http://localhost/SBL_SISTEMA_INTERNO/public/api';
        this.headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
    }

    async testUsersAPI() {
        console.log('🧪 Probando API de Usuarios...');
        
        try {
            // Listar usuarios
            const users = await axios.get(`${this.baseURL}/usuarios.php?activo=1`, { headers: this.headers });
            console.log('✅ Listar usuarios:', users.data.data.length, 'usuarios encontrados');

            // Obtener estadísticas
            const stats = await axios.get(`${this.baseURL}/usuarios.php/stats`, { headers: this.headers });
            console.log('✅ Estadísticas de usuarios:', stats.data.data);

        } catch (error) {
            console.error('❌ Error en API de usuarios:', error.response?.data?.error || error.message);
        }
    }

    async testCalibrationsAPI() {
        console.log('🧪 Probando API de Calibraciones...');
        
        try {
            // Obtener estadísticas
            const stats = await axios.get(`${this.baseURL}/calibraciones.php/stats`, { headers: this.headers });
            console.log('✅ Estadísticas de calibraciones:', stats.data.data);

            // Obtener calibraciones pendientes
            const pending = await axios.get(`${this.baseURL}/calibraciones.php/pending`, { headers: this.headers });
            console.log('✅ Calibraciones pendientes:', pending.data.data.length);

            // Obtener instrumentos vencidos
            const overdue = await axios.get(`${this.baseURL}/calibraciones.php/overdue`, { headers: this.headers });
            console.log('✅ Instrumentos vencidos:', overdue.data.data.length);

        } catch (error) {
            console.error('❌ Error en API de calibraciones:', error.response?.data?.error || error.message);
        }
    }

    async testProvidersAPI() {
        console.log('🧪 Probando API de Proveedores...');
        
        try {
            // Listar proveedores activos
            const providers = await axios.get(`${this.baseURL}/proveedores.php/active`, { headers: this.headers });
            console.log('✅ Proveedores activos:', providers.data.data.length);

            // Obtener servicios disponibles
            const services = await axios.get(`${this.baseURL}/proveedores.php/services`, { headers: this.headers });
            console.log('✅ Servicios disponibles:', services.data.data);

            // Obtener estadísticas
            const stats = await axios.get(`${this.baseURL}/proveedores.php/stats`, { headers: this.headers });
            console.log('✅ Estadísticas de proveedores:', stats.data.data);

        } catch (error) {
            console.error('❌ Error en API de proveedores:', error.response?.data?.error || error.message);
        }
    }

    async runAllTests() {
        console.log('🚀 Iniciando pruebas de APIs SBL...\n');
        
        await this.testUsersAPI();
        console.log('');
        await this.testCalibrationsAPI();
        console.log('');
        await this.testProvidersAPI();
        
        console.log('\n✨ Pruebas completadas');
    }
}

// Ejecutar pruebas
const tester = new SBLApiTester();
tester.runAllTests();
```

### PHP

```php
<?php

class SBLApiTester {
    private $baseUrl = 'http://localhost/SBL_SISTEMA_INTERNO/public/api';
    
    private function makeRequest($endpoint, $method = 'GET', $data = null) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/' . $endpoint,
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
        
        return [
            'code' => $httpCode,
            'data' => json_decode($response, true)
        ];
    }
    
    public function testDashboardData() {
        echo "🧪 Probando endpoints de dashboard...\n";
        
        // Dashboard de calibraciones
        $response = $this->makeRequest('calibraciones.php/dashboard');
        if ($response['code'] === 200) {
            echo "✅ Dashboard de calibraciones: OK\n";
            $data = $response['data']['data'];
            echo "   - Total calibraciones: {$data['resumen']['total_calibraciones']}\n";
            echo "   - Pendientes: {$data['resumen']['pendientes']}\n";
            echo "   - Vencidas: {$data['resumen']['vencidas']}\n";
        } else {
            echo "❌ Error en dashboard de calibraciones\n";
        }
        
        // Estadísticas de proveedores
        $response = $this->makeRequest('proveedores.php/stats');
        if ($response['code'] === 200) {
            echo "✅ Estadísticas de proveedores: OK\n";
            $data = $response['data']['data'];
            echo "   - Total proveedores: {$data['total']}\n";
            echo "   - Activos: {$data['activos']}\n";
        } else {
            echo "❌ Error en estadísticas de proveedores\n";
        }
    }
    
    public function testPagination() {
        echo "\n🧪 Probando paginación...\n";
        
        $response = $this->makeRequest('usuarios.php?per_page=2&page=1');
        if ($response['code'] === 200) {
            echo "✅ Paginación de usuarios: OK\n";
            $pagination = $response['data']['pagination'];
            echo "   - Página actual: {$pagination['current_page']}\n";
            echo "   - Por página: {$pagination['per_page']}\n";
            echo "   - Total elementos: {$pagination['total_items']}\n";
        } else {
            echo "❌ Error en paginación\n";
        }
    }
    
    public function testFilters() {
        echo "\n🧪 Probando filtros...\n";
        
        // Filtrar calibraciones por estado
        $response = $this->makeRequest('calibraciones.php?estado=Pendiente&per_page=5');
        if ($response['code'] === 200) {
            echo "✅ Filtro por estado: OK\n";
            echo "   - Calibraciones pendientes encontradas: " . count($response['data']['data']) . "\n";
        } else {
            echo "❌ Error en filtro por estado\n";
        }
        
        // Buscar proveedores
        $response = $this->makeRequest('proveedores.php?search=lab&activo=1');
        if ($response['code'] === 200) {
            echo "✅ Búsqueda de proveedores: OK\n";
            echo "   - Resultados encontrados: " . count($response['data']['data']) . "\n";
        } else {
            echo "❌ Error en búsqueda de proveedores\n";
        }
    }
    
    public function runTests() {
        echo "🚀 Iniciando pruebas de APIs SBL...\n\n";
        
        $this->testDashboardData();
        $this->testPagination();
        $this->testFilters();
        
        echo "\n✨ Pruebas completadas\n";
    }
}

// Ejecutar pruebas
$tester = new SBLApiTester();
$tester->runTests();
```

## Checklist de Pruebas

### Funcionalidad Básica
- [ ] GET - Listar recursos con paginación
- [ ] GET - Obtener recurso por ID
- [ ] POST - Crear nuevo recurso
- [ ] PUT - Actualizar recurso existente
- [ ] DELETE - Eliminar recurso

### Filtros y Búsqueda
- [ ] Filtros por estado/tipo
- [ ] Búsqueda por texto
- [ ] Filtros por fechas
- [ ] Combinación de filtros

### Paginación y Ordenamiento
- [ ] Paginación básica
- [ ] Ordenamiento ascendente/descendente
- [ ] Límites de paginación

### Endpoints Especiales
- [ ] Estadísticas
- [ ] Dashboard data
- [ ] Recursos relacionados
- [ ] Acciones especiales (aprobar, rechazar, etc.)

### Manejo de Errores
- [ ] Recursos no encontrados (404)
- [ ] Permisos insuficientes (403)
- [ ] Datos inválidos (400)
- [ ] Campos requeridos faltantes

### Seguridad
- [ ] Verificación de permisos
- [ ] Validación de datos de entrada
- [ ] Sanitización de parámetros
- [ ] Control de acceso por empresa

## Herramientas Recomendadas

1. **Postman** - Para pruebas manuales y documentación
2. **Thunder Client** - Plugin de VS Code para pruebas rápidas
3. **curl** - Para scripts de línea de comandos
4. **PHPUnit** - Para pruebas automatizadas en PHP
5. **Jest** - Para pruebas del frontend que consume las APIs

## Notas de Rendimiento

- Las APIs están optimizadas para consultas eficientes
- Usar paginación para listas grandes
- Los JOINs están limitados para evitar problemas de rendimiento
- Las consultas complejas usan índices apropiados
- El logging es asíncrono para no afectar el rendimiento