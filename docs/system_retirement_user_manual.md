# Manual del Usuario - Sistema de Retiro de Sistema Computarizado

## Tabla de Contenidos
1. [Introducción](#introducción)
2. [Acceso al Sistema](#acceso-al-sistema)
3. [Interfaz de Usuario](#interfaz-de-usuario)
4. [Proceso Paso a Paso](#proceso-paso-a-paso)
5. [Funciones Avanzadas](#funciones-avanzadas)
6. [Solución de Problemas](#solución-de-problemas)
7. [Preguntas Frecuentes](#preguntas-frecuentes)

## Introducción

### ¿Qué es el Sistema de Retiro?
El Sistema de Retiro de Sistema Computarizado es una herramienta especializada que permite realizar el retiro controlado y documentado de sistemas computarizados, cumpliendo con las normativas GAMP 5 y los requisitos regulatorios GxP.

### Beneficios para el Usuario
- ✅ **Proceso Guiado**: Interfaz intuitiva paso a paso
- ✅ **Cumplimiento Automático**: Generación automática de documentación normativa
- ✅ **Trazabilidad Completa**: Registro detallado de todas las actividades
- ✅ **Seguridad Garantizada**: Destrucción segura de datos sensibles
- ✅ **Reportes Profesionales**: Documentos listos para auditorías

### Casos de Uso Típicos
- **Retiro de Sistemas Obsoletos**: Sistemas que ya no son necesarios
- **Actualización de Infraestructura**: Migración a nuevas tecnologías
- **Consolidación de Sistemas**: Fusión de múltiples sistemas
- **Cumplimiento Regulatorio**: Retiro por cambios normativos
- **Cierre de Facilidades**: Desmantelamiento de instalaciones

## Acceso al Sistema

### URL de Acceso
```
http://localhost:8000/apps/system-retirement/index.html
```

### Proceso de Login
1. **Acceder al Sistema Principal**: Ingresar al portal SBL con sus credenciales habituales
2. **Navegación**: Ir al menú "Aplicaciones" → "Sistema de Retiro"
3. **Verificación de Permisos**: El sistema verificará automáticamente sus permisos de acceso

### Permisos Requeridos
Para usar el sistema necesita al menos uno de estos roles:
- **Developer**: Acceso completo a todas las funciones
- **SuperAdmin**: Acceso completo a todas las funciones
- **Admin**: Acceso a la mayoría de funciones (excepto destrucción)
- **Supervisor**: Solo visualización y monitoreo

### Primera Vez en el Sistema
Al acceder por primera vez verá:
- **Tutorial Interactivo**: Guía rápida de 5 minutos
- **Dashboard Vacío**: Sin procesos activos
- **Menú de Ayuda**: Acceso a documentación y soporte

## Interfaz de Usuario

### Diseño General
La interfaz está organizada en:
- **Header Superior**: Información del sistema y usuario
- **Barra de Progreso**: Estado visual del proceso actual
- **Panel de Navegación**: Pestañas para diferentes etapas
- **Área Principal**: Formularios y contenido específico
- **Panel de Estado**: Información en tiempo real

### Elementos de la Interfaz

#### Header Superior
```
[Logo SBL] Sistema de Retiro | Usuario: [Su Nombre] | [Notificaciones] [Ayuda] [Salir]
```

#### Barra de Progreso
```
Planificación → Migración → Destrucción → Verificación → Aprobación
    [●]          [○]         [○]          [○]          [○]
  Completado   Pendiente   Pendiente    Pendiente    Pendiente
```

#### Panel de Navegación (Pestañas)
1. **🏁 Iniciar Retiro**: Planificación y configuración inicial
2. **📊 Migrar Datos**: Configuración y transferencia de datos
3. **🗑️ Destruir Datos**: Eliminación segura de información
4. **✅ Verificar**: Validación del proceso completado
5. **📈 Monitorear**: Seguimiento y reportes

### Códigos de Color del Sistema
- **🟢 Verde**: Proceso completado exitosamente
- **🟡 Amarillo**: Proceso en progreso o advertencia
- **🔴 Rojo**: Error o proceso fallido
- **🔵 Azul**: Información o proceso pendiente
- **🟣 Púrpura**: Proceso aprobado o certificado

### Iconografía Estándar
- **📋**: Formulario o configuración
- **⚙️**: Configuraciones avanzadas  
- **📄**: Documentos y reportes
- **🔒**: Seguridad y permisos
- **⏰**: Tiempo y fechas
- **📊**: Estadísticas y métricas
- **❓**: Ayuda e información

## Proceso Paso a Paso

### Paso 1: Iniciar Proceso de Retiro

#### Acceder a la Pestaña "Iniciar Retiro"
Haga clic en la primera pestaña para comenzar el proceso.

#### Completar Información Básica
```
📋 Información del Sistema
┌─────────────────────────────────────────────────────────────┐
│ Nombre del Sistema: [________________________]              │
│ Versión del Sistema: [_____________]                        │
│ Fecha de Desactivación: [__/__/____] 📅                    │
│ Motivo del Retiro: [_________________________________]      │
│                   [_________________________________]      │
└─────────────────────────────────────────────────────────────┘
```

**Campos Obligatorios:**
- **Nombre del Sistema**: Identificación única y descriptiva
- **Fecha de Desactivación**: Debe ser fecha futura
- **Motivo del Retiro**: Justificación detallada del proceso

**Ejemplos de Nombres de Sistema:**
- "Sistema de Calibración de Instrumentos v2.1"
- "Portal de Gestión de Clientes - Módulo Facturación"
- "Base de Datos de Certificados de Calidad"

#### Configurar Opciones del Proceso
```
⚙️ Configuraciones de Retiro
┌─────────────────────────────────────────────────────────────┐
│ ☑️ Realizar migración de datos críticos                     │
│ ☑️ Ejecutar destrucción segura de datos sensibles          │
│ ☑️ Generar certificados de destrucción                     │
│ ☑️ Requerir verificación post-retiro                       │
│ ☑️ Solicitar aprobaciones múltiples                        │
└─────────────────────────────────────────────────────────────┘
```

**Recomendaciones:**
- ✅ **Siempre marque "Verificación post-retiro"** para cumplimiento
- ✅ **Use "Aprobaciones múltiples"** para sistemas críticos
- ⚠️ **"Destrucción segura"** es irreversible - confirme antes de marcar

#### Evaluación de Riesgos
```
🔍 Evaluación de Riesgos
┌─────────────────────────────────────────────────────────────┐
│ Impacto del Retiro: ○ Bajo  ○ Medio  ● Alto                │
│                                                             │
│ Riesgos Identificados:                                      │
│ [____________________________________________]              │
│ [____________________________________________]              │
│                                                             │
│ Medidas de Mitigación:                                      │
│ [____________________________________________]              │
│ [____________________________________________]              │
└─────────────────────────────────────────────────────────────┘
```

**Ejemplos de Riesgos:**
- "Pérdida de datos históricos de calibración"
- "Interrupción temporal de servicios dependientes"
- "Necesidad de reentrenamiento de usuarios"

**Ejemplos de Mitigación:**
- "Migración completa a sistema de reemplazo"
- "Cronograma de retiro durante mantenimiento programado"
- "Capacitación previa del personal afectado"

#### Finalizar Planificación
```
[Guardar Borrador] [Cancelar] [Iniciar Proceso de Retiro]
```

**Al hacer clic en "Iniciar Proceso de Retiro":**
1. Se valida la información ingresada
2. Se crea un ID único de proceso
3. Se genera el audit trail inicial
4. Se habilitan las siguientes pestañas

**Confirmación de Creación:**
```
✅ Proceso de Retiro Iniciado Exitosamente
ID del Proceso: RET-2024-001234
Estado: EN_PLANIFICACION
Creado por: [Su Nombre]
Fecha: 15/01/2024 10:30:00
```

### Paso 2: Migrar Datos (Opcional)

#### ¿Cuándo usar la migración?
- **Datos Críticos**: Información que debe preservarse
- **Registros Históricos**: Datos requeridos por regulaciones
- **Referencias Futuras**: Información que puede necesitarse
- **Cumplimiento**: Datos requeridos para auditorías

#### Configurar Fuentes de Datos

##### Agregar Fuente de Base de Datos
```
📊 Nueva Fuente de Datos - Base de Datos
┌─────────────────────────────────────────────────────────────┐
│ Nombre: [Instrumentos de Calibración_______________]        │
│ Tipo: ● Base de Datos  ○ Archivos  ○ Configuración        │
│                                                             │
│ Tabla: [instrumentos_calibracion] 🔍                      │
│ Condiciones: [WHERE activo = 1 AND fecha >= '2020-01-01'] │
│                                                             │
│ [Probar Conexión] [Estimar Registros]                     │
│ Estado: ✅ Conexión exitosa - 1,245 registros             │
└─────────────────────────────────────────────────────────────┘
```

##### Agregar Fuente de Archivos
```
📁 Nueva Fuente de Datos - Archivos
┌─────────────────────────────────────────────────────────────┐
│ Nombre: [Certificados PDF_________________________]        │
│ Tipo: ○ Base de Datos  ● Archivos  ○ Configuración        │
│                                                             │
│ Ruta: [/app/storage/certificados] 📂                      │
│ Incluir: [*.pdf, *.xlsx] (separados por comas)           │
│ Excluir: [*.tmp, *.log] (opcional)                       │
│                                                             │
│ [Explorar Carpeta] [Contar Archivos]                      │
│ Estado: ✅ Ruta válida - 89 archivos (245 MB)             │
└─────────────────────────────────────────────────────────────┘
```

##### Lista de Fuentes Configuradas
```
📋 Fuentes de Datos Configuradas
┌─────────────────────────────────────────────────────────────┐
│ 1. Instrumentos de Calibración (Base de Datos)             │
│    └── 1,245 registros - Estado: ✅ Listo                  │
│                                                             │
│ 2. Certificados PDF (Archivos)                             │
│    └── 89 archivos - Estado: ✅ Listo                      │
│                                                             │
│ 3. Configuración del Sistema (Configuración)               │
│    └── 23 parámetros - Estado: ✅ Listo                    │
│                                                             │
│ [+ Agregar Fuente] [Editar] [Eliminar]                    │
└─────────────────────────────────────────────────────────────┘
```

#### Configurar Parámetros de Migración
```
⚙️ Configuración de Migración
┌─────────────────────────────────────────────────────────────┐
│ Ruta de Destino:                                           │
│ [/backup/retirement/RET-2024-001234/migrated_data] 📂     │
│                                                             │
│ Opciones:                                                   │
│ ☑️ Habilitar compresión (reduce tamaño 60-80%)            │
│ ☑️ Habilitar encriptación (requerido para datos sensibles)│
│ ☑️ Generar checksums (verificar integridad)               │
│ ☑️ Crear índice de archivos                                │
│                                                             │
│ Contraseña de Encriptación:                                │
│ [******************] [Generar Aleatoria]                   │
└─────────────────────────────────────────────────────────────┘
```

#### Ejecutar Migración
```
[Vista Previa] [Ejecutar Migración]
```

**Durante la migración verá:**
```
🔄 Migración en Progreso...
┌─────────────────────────────────────────────────────────────┐
│ Progreso General: ████████████████░░░░ 80% (3/4 fuentes)   │
│                                                             │
│ Fuente Actual: Certificados PDF                            │
│ Progreso: ██████████████████████████ 100% (89/89 archivos) │
│                                                             │
│ Tiempo Transcurrido: 00:05:32                              │
│ Tiempo Estimado Restante: 00:01:25                         │
│                                                             │
│ ✅ Instrumentos de Calibración - 1,245 registros           │
│ ✅ Certificados PDF - 89 archivos                          │
│ 🔄 Configuración del Sistema - 15/23 parámetros            │
│ ⏳ Logs del Sistema - Pendiente                            │
└─────────────────────────────────────────────────────────────┘
```

**Al completar la migración:**
```
✅ Migración Completada Exitosamente
┌─────────────────────────────────────────────────────────────┐
│ Tiempo Total: 00:07:18                                      │
│ Datos Migrados: 1,357 elementos (2.1 GB)                   │
│ Datos Comprimidos: 650 MB (economía del 69%)               │
│                                                             │
│ Resumen por Fuente:                                         │
│ ✅ Instrumentos: 1,245 registros - Checksum: ✅            │
│ ✅ Certificados: 89 archivos - Checksum: ✅                │
│ ✅ Configuración: 23 parámetros - Checksum: ✅             │
│                                                             │
│ [Descargar Reporte] [Ver Detalles]                        │
└─────────────────────────────────────────────────────────────┘
```

### Paso 3: Destruir Datos Sensibles

#### ⚠️ ADVERTENCIA IMPORTANTE
```
🚨 ATENCIÓN: La destrucción de datos es IRREVERSIBLE
┌─────────────────────────────────────────────────────────────┐
│ La destrucción segura eliminará permanentemente los datos  │
│ especificados. Este proceso NO puede deshacerse.           │
│                                                             │
│ ✅ Confirme que la migración está completa                  │
│ ✅ Verifique que no necesitará estos datos                 │
│ ✅ Obtenga aprobación gerencial si es requerida            │
│                                                             │
│ [He leído y entiendo ☑️] [Continuar]                      │
└─────────────────────────────────────────────────────────────┘
```

#### Configurar Objetivos de Destrucción

##### Agregar Objetivo de Destrucción
```
🗑️ Nuevo Objetivo de Destrucción
┌─────────────────────────────────────────────────────────────┐
│ Nombre: [Base de Datos de Instrumentos_______________]      │
│ Tipo: ● Base de Datos  ○ Archivos  ○ Disco  ○ Partición   │
│                                                             │
│ Ruta/Conexión: [mysql://user:pass@localhost/calibracion]  │
│                                                             │
│ Tablas Específicas (opcional):                             │
│ [instrumentos, calibraciones, certificados]               │
│                                                             │
│ [Validar Objetivo] [Estimar Tamaño]                       │
│ Estado: ✅ Objetivo válido - 245 MB de datos               │
└─────────────────────────────────────────────────────────────┘
```

##### Lista de Objetivos de Destrucción
```
🎯 Objetivos de Destrucción Configurados
┌─────────────────────────────────────────────────────────────┐
│ 1. Base de Datos de Instrumentos                           │
│    └── Tipo: Base de Datos - Tamaño: 245 MB               │
│                                                             │
│ 2. Archivos de Configuración                               │
│    └── Tipo: Archivos - Tamaño: 12 MB                     │
│                                                             │
│ 3. Logs de Auditoría                                       │
│    └── Tipo: Archivos - Tamaño: 89 MB                     │
│                                                             │
│ Total a Destruir: 346 MB                                   │
│ [+ Agregar Objetivo] [Editar] [Eliminar]                  │
└─────────────────────────────────────────────────────────────┘
```

#### Seleccionar Método de Destrucción

```
🔧 Método de Destrucción
┌─────────────────────────────────────────────────────────────┐
│ ● Borrado Seguro (SECURE_WIPE)                             │
│   ├── Sobreescritura múltiple con patrones aleatorios     │
│   ├── Cumple estándares DoD 5220.22-M y NIST 800-88      │
│   └── Recomendado para la mayoría de casos                │
│                                                             │
│ ○ Borrado Criptográfico (CRYPTOGRAPHIC_ERASE)             │
│   ├── Eliminación de claves de encriptación               │
│   ├── Rápido y efectivo para datos encriptados            │
│   └── Requiere hardware compatible                        │
│                                                             │
│ ○ Destrucción Física (PHYSICAL_DESTRUCTION)               │
│   ├── Destrucción física del medio de almacenamiento      │
│   ├── Máxima seguridad para datos ultra-sensibles         │
│   └── Requiere proceso manual externo                     │
└─────────────────────────────────────────────────────────────┘
```

#### Configurar Parámetros de Destrucción
```
⚙️ Configuración de Destrucción Segura
┌─────────────────────────────────────────────────────────────┐
│ Número de Pasadas: [3] (mínimo recomendado)               │
│ ├── 1 pasada: Rápido, seguridad básica                    │
│ ├── 3 pasadas: Recomendado, cumple estándares             │
│ └── 7+ pasadas: Máxima seguridad, más lento               │
│                                                             │
│ Patrón de Sobreescritura:                                   │
│ ● Aleatorio (recomendado)                                   │
│ ○ Zeros (más rápido)                                       │
│ ○ DoD 5220.22-M (estándar militar)                        │
│                                                             │
│ Opciones Adicionales:                                      │
│ ☑️ Verificar destrucción (obligatorio para cumplimiento)   │
│ ☑️ Generar certificado de destrucción                      │
│ ☑️ Intentar recuperación como prueba                       │
└─────────────────────────────────────────────────────────────┘
```

#### Ejecutar Destrucción
```
⚠️ Confirmación Final de Destrucción
┌─────────────────────────────────────────────────────────────┐
│ Está a punto de destruir PERMANENTEMENTE:                  │
│                                                             │
│ • Base de Datos de Instrumentos (245 MB)                   │
│ • Archivos de Configuración (12 MB)                        │
│ • Logs de Auditoría (89 MB)                               │
│                                                             │
│ Método: Borrado Seguro (3 pasadas)                        │
│ Tiempo Estimado: 25-30 minutos                            │
│                                                             │
│ Para confirmar, escriba: CONFIRMO DESTRUCCION              │
│ [________________________]                                 │
│                                                             │
│ [Cancelar] [EJECUTAR DESTRUCCIÓN]                         │
└─────────────────────────────────────────────────────────────┘
```

**Durante la destrucción:**
```
🔥 Destrucción en Progreso...
┌─────────────────────────────────────────────────────────────┐
│ ⚠️ NO INTERRUMPA ESTE PROCESO                               │
│                                                             │
│ Progreso General: ██████████░░░░░░░░░░ 50% (2/3 objetivos) │
│                                                             │
│ Objetivo Actual: Archivos de Configuración                 │
│ Pasada: 2/3 - ████████████████████████████ 100%           │
│                                                             │
│ Tiempo Transcurrido: 00:15:42                              │
│ Tiempo Estimado Restante: 00:12:18                         │
│                                                             │
│ ✅ Base de Datos - Destruido y verificado                  │
│ 🔄 Archivos Config - Pasada 2/3 en progreso                │
│ ⏳ Logs Auditoría - Pendiente                              │
└─────────────────────────────────────────────────────────────┘
```

**Al completar la destrucción:**
```
✅ Destrucción Completada Exitosamente
┌─────────────────────────────────────────────────────────────┐
│ Tiempo Total: 00:28:35                                      │
│ Datos Destruidos: 346 MB en 3 objetivos                   │
│                                                             │
│ Resumen de Destrucción:                                     │
│ ✅ Base de Datos - 3 pasadas - Verificación: EXITOSA       │
│ ✅ Archivos Config - 3 pasadas - Verificación: EXITOSA     │
│ ✅ Logs Auditoría - 3 pasadas - Verificación: EXITOSA      │
│                                                             │
│ 📜 Certificados de Destrucción: 3 generados                │
│                                                             │
│ [Descargar Certificados] [Ver Detalles] [Imprimir]        │
└─────────────────────────────────────────────────────────────┘
```

### Paso 4: Verificación Post-Retiro

#### Ejecutar Verificación Automática
```
[Ejecutar Verificación Post-Retiro]
```

La verificación se ejecuta automáticamente y valida:

#### 1. Verificación de Migración
```
✅ Verificación de Migración de Datos
┌─────────────────────────────────────────────────────────────┐
│ Total de Fuentes: 4                                        │
│ Migraciones Exitosas: 4                                    │
│                                                             │
│ Checksums de Integridad:                                   │
│ ✅ Instrumentos: SHA256 coincidente                        │
│ ✅ Certificados: SHA256 coincidente                        │
│ ✅ Configuración: SHA256 coincidente                       │
│ ✅ Logs: SHA256 coincidente                                │
│                                                             │
│ Accesibilidad de Datos:                                    │
│ ✅ Todos los archivos migrados son accesibles              │
│                                                             │
│ Estado: APROBADO ✅                                         │
└─────────────────────────────────────────────────────────────┘
```

#### 2. Verificación de Destrucción
```
✅ Verificación de Destrucción de Datos
┌─────────────────────────────────────────────────────────────┐
│ Total de Objetivos: 3                                      │
│ Destrucciones Verificadas: 3                               │
│                                                             │
│ Pruebas de Recuperación:                                   │
│ ✅ Base de Datos: IRRECUPERABLE                            │
│ ✅ Archivos Config: IRRECUPERABLE                          │
│ ✅ Logs Auditoría: IRRECUPERABLE                           │
│                                                             │
│ Certificados de Destrucción:                               │
│ ✅ 3 certificados generados y validados                    │
│                                                             │
│ Estado: APROBADO ✅                                         │
└─────────────────────────────────────────────────────────────┘
```

#### 3. Verificación de Desactivación del Sistema
```
✅ Verificación de Desactivación del Sistema
┌─────────────────────────────────────────────────────────────┐
│ Servicios del Sistema:                                      │
│ ✅ Servicio web: DETENIDO                                   │
│ ✅ Base de datos: DESCONECTADA                             │
│ ✅ Servicios auxiliares: DETENIDOS                         │
│                                                             │
│ Acceso de Usuarios:                                         │
│ ✅ Interfaces web: INACCESIBLES                             │
│ ✅ APIs del sistema: DESHABILITADAS                        │
│ ✅ Acceso SSH/FTP: BLOQUEADO                               │
│                                                             │
│ Conectividad de Red:                                        │
│ ✅ Puertos cerrados: 80, 443, 3306, 22                    │
│ ✅ Firewall actualizado                                    │
│                                                             │
│ Estado: APROBADO ✅                                         │
└─────────────────────────────────────────────────────────────┘
```

#### 4. Verificación de Documentación
```
✅ Verificación de Documentación
┌─────────────────────────────────────────────────────────────┐
│ Documentos Requeridos: 5                                   │
│ Documentos Completados: 5                                  │
│                                                             │
│ Lista de Documentos:                                        │
│ ✅ Plan de retiro documentado                               │
│ ✅ Resultados de migración                                  │
│ ✅ Certificados de destrucción                             │
│ ✅ Reportes de verificación                                │
│ ✅ Registros de auditoría                                  │
│                                                             │
│ Firmas Digitales:                                          │
│ ✅ Todas las firmas requeridas están presentes             │
│                                                             │
│ Estado: APROBADO ✅                                         │
└─────────────────────────────────────────────────────────────┘
```

#### Resumen de Verificación
```
🎉 Verificación Post-Retiro COMPLETADA
┌─────────────────────────────────────────────────────────────┐
│ Todas las verificaciones han pasado exitosamente.          │
│                                                             │
│ ✅ Migración de Datos: APROBADO                            │
│ ✅ Destrucción de Datos: APROBADO                          │
│ ✅ Desactivación del Sistema: APROBADO                     │
│ ✅ Documentación: APROBADO                                 │
│                                                             │
│ El proceso de retiro está listo para aprobación final.     │
│                                                             │
│ [Proceder a Aprobación] [Generar Reporte Final]           │
└─────────────────────────────────────────────────────────────┘
```

### Paso 5: Aprobación Final

#### Seleccionar Tipo de Aprobación
```
📋 Tipo de Aprobación
┌─────────────────────────────────────────────────────────────┐
│ ● Aprobación Técnica                                       │
│   └── Validación técnica del proceso de retiro            │
│                                                             │
│ ○ Aprobación de Calidad                                    │
│   └── Revisión de cumplimiento normativo                  │
│                                                             │
│ ○ Aprobación Gerencial                                     │
│   └── Autorización ejecutiva del retiro                   │
│                                                             │
│ ○ Aprobación Final                                         │
│   └── Cierre definitivo del proceso                       │
└─────────────────────────────────────────────────────────────┘
```

#### Formulario de Aprobación
```
✍️ Aprobación Técnica
┌─────────────────────────────────────────────────────────────┐
│ ID del Proceso: RET-2024-001234                            │
│ Solicitado por: Juan Pérez (Developer)                     │
│ Fecha de Solicitud: 15/01/2024 14:30:00                   │
│                                                             │
│ Resumen del Proceso:                                        │
│ • Sistema: Sistema de Calibración v2.1                     │
│ • Datos Migrados: 1,357 elementos (2.1 GB)               │
│ • Datos Destruidos: 346 MB en 3 objetivos                │
│ • Verificaciones: 4/4 aprobadas                           │
│                                                             │
│ Comentarios de Aprobación:                                 │
│ [_________________________________________________]         │
│ [_________________________________________________]         │
│                                                             │
│ Firma Digital (su contraseña):                             │
│ [******************]                                        │
│                                                             │
│ [Rechazar] [Aprobar Proceso de Retiro]                    │
└─────────────────────────────────────────────────────────────┘
```

#### Confirmación de Aprobación
```
✅ Aprobación Registrada Exitosamente
┌─────────────────────────────────────────────────────────────┐
│ Tipo: Aprobación Técnica                                   │
│ Aprobado por: María González (SuperAdmin)                  │
│ Fecha: 15/01/2024 15:45:00                                 │
│ Firma Digital: ✅ Verificada                               │
│                                                             │
│ El proceso requiere las siguientes aprobaciones adicionales:│
│ ⏳ Aprobación de Calidad                                   │
│ ⏳ Aprobación Final                                        │
│                                                             │
│ [Solicitar Próxima Aprobación] [Ver Estado Completo]      │
└─────────────────────────────────────────────────────────────┘
```

#### Estado Final del Proceso
```
🎊 Proceso de Retiro COMPLETADO
┌─────────────────────────────────────────────────────────────┐
│ ID: RET-2024-001234                                        │
│ Estado: COMPLETADO ✅                                       │
│ Fecha de Finalización: 15/01/2024 16:20:00                │
│                                                             │
│ Aprobaciones Obtenidas:                                     │
│ ✅ Técnica - María González - 15/01/2024 15:45:00          │
│ ✅ Calidad - Carlos Ruiz - 15/01/2024 16:10:00            │
│ ✅ Final - Ana Torres - 15/01/2024 16:20:00                │
│                                                             │
│ Documentos Generados:                                       │
│ 📄 Reporte Final de Retiro                                │
│ 📄 Certificados de Destrucción (3)                        │
│ 📄 Evidencia de Verificación                              │
│ 📄 Audit Trail Completo                                   │
│                                                             │
│ [Descargar Todos los Documentos] [Archivar Proceso]       │
└─────────────────────────────────────────────────────────────┘
```

## Funciones Avanzadas

### Monitoreo en Tiempo Real

#### Dashboard de Procesos Activos
```
📊 Dashboard - Procesos de Retiro Activos
┌─────────────────────────────────────────────────────────────┐
│ Procesos Totales: 12    Activos: 3    Completados: 9      │
│                                                             │
│ RET-2024-001234  Sistema Calibración     🟢 COMPLETADO    │
│ RET-2024-001235  Portal Clientes         🟡 EN_PROGRESO   │
│ RET-2024-001236  Base Datos Legacy       🔴 ERROR         │
│ RET-2024-001237  Sistema Inventario      🔵 PLANIFICACION │
│                                                             │
│ [🔄 Actualizar] [📊 Métricas] [📈 Reportes]              │
└─────────────────────────────────────────────────────────────┘
```

#### Vista Detallada de Proceso
```
🔍 Detalle del Proceso: RET-2024-001235
┌─────────────────────────────────────────────────────────────┐
│ Sistema: Portal de Clientes v3.2                           │
│ Estado: EN_PROGRESO (Fase: Destrucción)                    │
│ Iniciado: 15/01/2024 09:00:00                             │
│ Progreso: ████████████████████░░░░ 80%                     │
│                                                             │
│ Últimas Actividades:                                       │
│ 16:15 - Iniciada destrucción de logs de acceso            │
│ 16:10 - Completada migración de datos de usuarios         │
│ 16:05 - Verificada integridad de backup                   │
│                                                             │
│ Siguiente Acción: Verificación de destrucción             │
│ Tiempo Estimado: 15 minutos                               │
│                                                             │
│ [Ver Audit Trail] [Pausar Proceso] [Generar Reporte]     │
└─────────────────────────────────────────────────────────────┘
```

### Reportes y Documentación

#### Generador de Reportes
```
📄 Generador de Reportes
┌─────────────────────────────────────────────────────────────┐
│ Tipo de Reporte:                                           │
│ ● Reporte Ejecutivo                                        │
│ ○ Reporte Técnico Detallado                               │
│ ○ Reporte de Cumplimiento Regulatorio                     │
│ ○ Certificado de Destrucción                              │
│                                                             │
│ Proceso: [RET-2024-001234 ▼]                              │
│                                                             │
│ Formato de Salida:                                         │
│ ● PDF  ○ Word  ○ Excel  ○ HTML                            │
│                                                             │
│ Opciones:                                                   │
│ ☑️ Incluir audit trail                                     │
│ ☑️ Incluir evidencia fotográfica                          │
│ ☑️ Incluir certificados de destrucción                    │
│ ☑️ Aplicar firma digital al documento                     │
│                                                             │
│ [Vista Previa] [Generar Reporte]                          │
└─────────────────────────────────────────────────────────────┘
```

#### Archivo de Documentos
```
📁 Archivo de Documentos - RET-2024-001234
┌─────────────────────────────────────────────────────────────┐
│ 📄 reporte_final_retiro.pdf (2.3 MB)      15/01/2024      │
│ 📄 certificado_destruccion_db.pdf (245 KB) 15/01/2024     │
│ 📄 certificado_destruccion_files.pdf (198 KB) 15/01/2024  │
│ 📄 evidencia_verificacion.pdf (1.1 MB)    15/01/2024      │
│ 📄 audit_trail_completo.xlsx (987 KB)     15/01/2024      │
│ 📄 plan_retiro_original.pdf (456 KB)      15/01/2024      │
│                                                             │
│ Total: 6 documentos (5.2 MB)                              │
│                                                             │
│ [Descargar Todo] [Compartir] [Archivar] [Imprimir]       │
└─────────────────────────────────────────────────────────────┘
```

### Configuraciones Avanzadas

#### Plantillas de Retiro
```
📝 Gestión de Plantillas
┌─────────────────────────────────────────────────────────────┐
│ Plantillas Disponibles:                                    │
│                                                             │
│ 🏭 Sistema de Producción                                   │
│   ├── Migración: Base de datos + Archivos críticos       │
│   ├── Destrucción: Borrado seguro 7 pasadas              │
│   └── Aprobaciones: Técnica + Calidad + Gerencial        │
│                                                             │
│ 🧪 Sistema de Laboratorio                                  │
│   ├── Migración: Datos de análisis + Certificados        │
│   ├── Destrucción: Borrado seguro 3 pasadas              │
│   └── Aprobaciones: Técnica + Calidad                     │
│                                                             │
│ 💼 Sistema Administrativo                                  │
│   ├── Migración: Configuraciones únicamente              │
│   ├── Destrucción: Borrado estándar                       │
│   └── Aprobaciones: Técnica únicamente                    │
│                                                             │
│ [+ Nueva Plantilla] [Editar] [Eliminar] [Aplicar]        │
└─────────────────────────────────────────────────────────────┘
```

#### Programación de Retiros
```
⏰ Programación de Retiros
┌─────────────────────────────────────────────────────────────┐
│ Retiros Programados:                                       │
│                                                             │
│ 📅 22/01/2024 02:00 - Sistema Legacy ERP                  │
│    └── Estado: PROGRAMADO ⏳                               │
│                                                             │
│ 📅 29/01/2024 03:30 - Base Datos Histórica                │
│    └── Estado: PROGRAMADO ⏳                               │
│                                                             │
│ 📅 05/02/2024 01:00 - Sistema Backup Antiguo             │
│    └── Estado: PROGRAMADO ⏳                               │
│                                                             │
│ Ventana de Mantenimiento:                                  │
│ 🕐 Lunes a Viernes: 01:00 - 05:00                         │
│ 🕐 Fines de semana: 00:00 - 06:00                         │
│                                                             │
│ [+ Programar Retiro] [Editar] [Cancelar]                  │
└─────────────────────────────────────────────────────────────┘
```

## Solución de Problemas

### Problemas Comunes y Soluciones

#### Error: "No se puede acceder al sistema"
```
❌ Error de Acceso
Síntoma: La página no carga o muestra error 403
Causas Posibles:
• Permisos de usuario insuficientes
• Sesión expirada
• Sistema en mantenimiento

Soluciones:
1. Verificar que tenga rol Developer, SuperAdmin o Admin
2. Cerrar sesión y volver a iniciar
3. Contactar al administrador del sistema
4. Verificar URL: /apps/system-retirement/index.html
```

#### Error: "Proceso no encontrado"
```
❌ Error RET-003
Síntoma: "El proceso especificado no existe"
Causas Posibles:
• ID de proceso incorrecto
• Proceso eliminado por otro usuario
• Error de base de datos

Soluciones:
1. Verificar el ID en la pestaña "Monitorear"
2. Buscar en el historial de procesos
3. Iniciar un nuevo proceso si es necesario
4. Contactar soporte técnico si persiste
```

#### Error: "Migración fallida"
```
❌ Error RET-004
Síntoma: La migración se detiene o falla
Causas Posibles:
• Problemas de conectividad de red
• Espacio insuficiente en destino
• Permisos de archivo incorrectos
• Fuente de datos no disponible

Soluciones:
1. Verificar conectividad de red
2. Comprobar espacio libre en destino (mínimo 2x el tamaño)
3. Verificar permisos de lectura/escritura
4. Probar conexión a fuente de datos
5. Reintentar migración con configuración reducida
```

#### Error: "Destrucción incompleta"
```
❌ Error RET-005
Síntoma: La destrucción no se completa
Causas Posibles:
• Archivos en uso por otros procesos
• Permisos insuficientes
• Disco protegido contra escritura
• Hardware con problemas

Soluciones:
1. Cerrar todas las aplicaciones relacionadas
2. Detener servicios que usen los datos
3. Verificar permisos de administrador
4. Comprobar estado del disco duro
5. Intentar con método de destrucción diferente
```

#### Error: "Verificación fallida"
```
❌ Error RET-006
Síntoma: Las verificaciones post-retiro fallan
Causas Posibles:
• Migración incompleta
• Destrucción parcial
• Servicios aún activos
• Documentación faltante

Soluciones:
1. Revisar logs de migración
2. Verificar resultado de destrucción
3. Confirmar que servicios estén detenidos
4. Completar documentación faltante
5. Ejecutar verificación individual por componente
```

### Herramientas de Diagnóstico

#### Verificador de Sistema
```
🔧 Verificador de Sistema
┌─────────────────────────────────────────────────────────────┐
│ [Ejecutar Diagnóstico Completo]                            │
│                                                             │
│ Resultados del Diagnóstico:                                │
│ ✅ Conectividad de Base de Datos: OK                       │
│ ✅ Permisos de Usuario: OK                                 │
│ ✅ Espacio en Disco: 245 GB libres                        │
│ ✅ Servicios del Sistema: Activos                          │
│ ⚠️  Versión PHP: 7.4 (recomendada 8.0+)                   │
│                                                             │
│ Recomendaciones:                                           │
│ • Actualizar PHP a versión 8.0 o superior                 │
│ • Configurar backup automático                            │
│                                                             │
│ [Exportar Diagnóstico] [Ejecutar Reparación]              │
└─────────────────────────────────────────────────────────────┘
```

#### Visor de Logs
```
📋 Visor de Logs del Sistema
┌─────────────────────────────────────────────────────────────┐
│ Filtros: [Último día ▼] [Todos los niveles ▼] [Buscar...] │
│                                                             │
│ 16:45:32 [INFO ] Proceso RET-001234 completado exitoso    │
│ 16:44:12 [WARN ] Migración lenta en fuente 'certificados' │
│ 16:43:45 [INFO ] Iniciada destrucción de base de datos    │
│ 16:40:23 [ERROR] Timeout en conexión a fuente externa     │
│ 16:39:54 [INFO ] Usuario mgonzalez aprobó retiro          │
│ 16:35:12 [DEBUG] Checksum verificado: SHA256 OK           │
│                                                             │
│ [🔄 Auto-refresh] [💾 Descargar Logs] [🗑️ Limpiar]       │
└─────────────────────────────────────────────────────────────┘
```

### Contacto de Soporte

#### Información de Contacto
```
📞 Soporte Técnico SBL
┌─────────────────────────────────────────────────────────────┐
│ 🆘 Soporte Inmediato (24/7):                              │
│ ├── Teléfono: +1-800-SBL-HELP                             │
│ ├── Email: support@sbl-sistema.com                        │
│ └── Chat: www.sbl-sistema.com/chat                        │
│                                                             │
│ 📧 Soporte por Email:                                      │
│ ├── General: info@sbl-sistema.com                         │
│ ├── Técnico: tech@sbl-sistema.com                         │
│ └── Comercial: sales@sbl-sistema.com                      │
│                                                             │
│ 🌐 Recursos Online:                                        │
│ ├── Documentación: docs.sbl-sistema.com                   │
│ ├── Videos: youtube.com/sblsistema                        │
│ └── FAQ: faq.sbl-sistema.com                              │
└─────────────────────────────────────────────────────────────┘
```

#### Información a Incluir en Solicitudes
Cuando contacte soporte, incluya:
- **ID del Proceso**: RET-2024-XXXXXX
- **Descripción del Problema**: Qué estaba haciendo cuando ocurrió
- **Mensajes de Error**: Texto exacto del error
- **Capturas de Pantalla**: Si es posible
- **Información del Sistema**: Versión, navegador, SO
- **Pasos para Reproducir**: Cómo recrear el problema

## Preguntas Frecuentes

### Preguntas Generales

#### ¿Puedo cancelar un proceso de retiro una vez iniciado?
**R:** Sí, puede cancelar un proceso antes de que comience la destrucción. Una vez iniciada la destrucción, el proceso no puede revertirse. Use el botón "Cancelar Proceso" en la pestaña de monitoreo.

#### ¿Cuánto tiempo toma un retiro completo?
**R:** Depende del tamaño de los datos:
- **Pequeño** (<1 GB): 30-60 minutos
- **Mediano** (1-10 GB): 1-3 horas  
- **Grande** (10-100 GB): 3-8 horas
- **Muy Grande** (>100 GB): 8+ horas

#### ¿Qué permisos necesito para usar el sistema?
**R:** Necesita al menos uno de estos roles:
- **Developer**: Acceso completo
- **SuperAdmin**: Acceso completo
- **Admin**: Acceso limitado (sin destrucción directa)
- **Supervisor**: Solo lectura y monitoreo

### Preguntas Técnicas

#### ¿Puedo migrar datos a sistemas externos?
**R:** Sí, el sistema soporta migración a:
- Otras bases de datos MySQL/PostgreSQL
- Sistemas de archivos locales o en red
- Servicios en la nube (con configuración)
- APIs de sistemas externos

#### ¿Qué métodos de destrucción están disponibles?
**R:** Tres métodos principales:
1. **Borrado Seguro**: Sobreescritura múltiple (recomendado)
2. **Borrado Criptográfico**: Eliminación de claves de encriptación
3. **Destrucción Física**: Destrucción del hardware (manual)

#### ¿Cómo verifico que los datos fueron realmente destruidos?
**R:** El sistema incluye:
- Intentos automáticos de recuperación de datos
- Verificación de patrones de sobreescritura
- Generación de certificados digitales
- Audit trail completo del proceso

### Preguntas sobre Cumplimiento

#### ¿El sistema cumple con GAMP 5?
**R:** Sí, completamente. Incluye:
- Gestión de riesgos documentada
- Validación de cada etapa
- Trazabilidad completa
- Controles de calidad integrados

#### ¿Genera documentación para auditorías?
**R:** Sí, genera automáticamente:
- Reportes ejecutivos y técnicos
- Certificados de destrucción
- Evidencia de verificación
- Audit trail completo con firmas digitales

#### ¿Cuánto tiempo se conservan los registros?
**R:** Por defecto 7 años (2,555 días), configurable según sus políticas de retención de datos.

### Preguntas sobre Problemas

#### ¿Qué hago si la migración falla?
**R:** 
1. Revisar logs de error en la pestaña de monitoreo
2. Verificar conectividad y permisos
3. Intentar migración por partes más pequeñas
4. Contactar soporte si persiste el problema

#### ¿Puedo recuperar datos después de la destrucción?
**R:** **NO**. La destrucción segura es irreversible por diseño. Por eso el sistema requiere:
- Confirmación explícita antes de destruir
- Verificación de que la migración esté completa
- Aprobaciones múltiples para sistemas críticos

#### ¿Qué hago si el proceso se queda "colgado"?
**R:**
1. Esperar 10-15 minutos (procesos pueden ser lentos)
2. Revisar logs para identificar el problema
3. Contactar soporte técnico inmediatamente
4. **NO** forzar el cierre del proceso durante destrucción

---

## Información Adicional

### Actualizaciones del Manual
Este manual se actualiza regularmente. Para obtener la última versión:
- Versión actual: 1.0.0
- Última actualización: Enero 2024
- Próxima revisión: Abril 2024

### Entrenamiento
SBL ofrece cursos de entrenamiento:
- **Básico**: 2 horas - Uso general del sistema
- **Avanzado**: 4 horas - Configuraciones y troubleshooting
- **Administrador**: 8 horas - Instalación y mantenimiento

### Licenciamiento
- El sistema requiere licencia válida de SBL
- Contacte a sales@sbl-sistema.com para información sobre licencias
- Renovación anual incluye actualizaciones y soporte

---

**Manual del Usuario - Sistema de Retiro GAMP 5**  
**Versión**: 1.0.0  
**Fecha**: Enero 2024  
**© 2024 SBL - Todos los derechos reservados**