# Guía de Homologación del Diseño - Sistema SBL Pharma

## 📋 Resumen

Esta guía establece los estándares de diseño homologado para todos los archivos HTML del sistema SBL Pharma, cumpliendo con las normativas ISO 17025/NOM-059.

## 🎨 Paleta de Colores Estándar

### Colores Principales
- **Primary**: `#0d575a` - Azul oscuro principal
- **Secondary**: `#217b9b` - Azul medio 
- **Accent**: `#0c8c98` - Azul activo para hover/estados
- **Light**: `#a3defd` - Azul claro
- **Lighter**: `#e6f6fb` - Azul muy claro para fondos

### Colores de Estado
- **Success**: `#27ae60`
- **Warning**: `#f39c12`
- **Danger**: `#e74c3c`
- **Info**: `#3498db`

### Colores de Fondo
- **Main**: `#f4f6f8` - Fondo principal
- **White**: `#ffffff` - Fondo blanco
- **Light**: `#ecf0f1` - Fondo claro

## 🔧 Implementación en Nuevos Archivos HTML

### 1. Estructura Base del HEAD
```html
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <script>
        (function() {
            var marker = '/public/';
            var path = window.location.pathname || '';
            var index = path.indexOf(marker);
            var base = index !== -1 ? path.slice(0, index + marker.length) : '/';
            if (!base.endsWith('/')) {
                base += '/';
            }
            window.BASE_URL = base === '/' ? '' : base.replace(/\/$/, '');
            document.write('<base href="' + base + '">');
        })();
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Título de la página | Sistema SBL Pharma</title>
    
    <!-- CDNs requeridos -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    
    <!-- CSS del sistema -->
    <link rel="stylesheet" href="assets/styles/[internal|tenant|service].css">
</head>
```

### 2. Variables CSS Disponibles
En el archivo `master-theme.css` están disponibles las siguientes variables:

```css
:root {
    --sbl-primary: #0d575a;
    --sbl-secondary: #217b9b;
    --sbl-accent: #0c8c98;
    --sbl-light: #a3defd;
    --sbl-lighter: #e6f6fb;
    --sbl-dark: #094146;
    --sbl-font-family: 'Montserrat', Arial, sans-serif;
    --sbl-sidebar-width: 200px;
    --sbl-border-radius: 10px;
    --sbl-box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
```

### 3. Componentes Estándar

#### Sidebar
```html
<div class="sidebar">
    <div class="sidebar-logo">
        <img src="assets/img/logo-sbl.png" alt="SBL Pharma">
    </div>
    <nav class="sidebar-nav">
        <a href="#" class="nav-link active">
            <i class="fas fa-home"></i>
            Inicio
        </a>
        <!-- Más enlaces... -->
    </nav>
</div>
```

#### Topbar
```html
<div class="topbar">
    <h2 class="topbar-title">Título de la Sección</h2>
    <div class="topbar-actions">
        <!-- Acciones de la barra superior -->
    </div>
</div>
```

#### Cards
```html
<div class="card">
    <div class="card-header">
        <h5 class="card-title">Título de la Card</h5>
    </div>
    <div class="card-body">
        <!-- Contenido -->
    </div>
</div>
```

#### Login Box
```html
<div class="login-wrapper">
    <div class="login-box">
        <div class="login-logo">
            <img src="assets/img/logo-sbl.png" alt="SBL Pharma">
        </div>
        <h2 class="login-title">Título</h2>
        <p class="login-subtitle">Subtítulo</p>
        <!-- Formulario -->
    </div>
</div>
```

## 📱 Responsive Design

El sistema incluye breakpoints responsive:
- Desktop: > 768px (sidebar fija)
- Mobile: ≤ 768px (sidebar colapsable)

## 🚀 Clases de Utilidad

### Colores
- `.text-sbl-primary` - Texto color primario
- `.text-sbl-secondary` - Texto color secundario
- `.bg-sbl-primary` - Fondo color primario
- `.bg-sbl-light` - Fondo color claro

### Visibilidad
- `.is-hidden` - Ocultar elemento
- `.is-visible` - Mostrar elemento

### Componentes Especiales
- `.dashboard-card` - Cards con hover effect
- `.stat-card` - Cards para estadísticas
- `.activity-item` - Items de actividad con borde izquierdo
- `.notification-item` - Items de notificación

## ✅ Checklist de Homologación

Al crear o actualizar un archivo HTML, verificar:

- [ ] Incluye el script de BASE_URL
- [ ] Usa Bootstrap 5.3.0
- [ ] Incluye FontAwesome 6.4.0
- [ ] Usa fuente Montserrat
- [ ] Importa el CSS correspondiente (internal/tenant/service)
- [ ] Usa variables CSS en lugar de colores hardcodeados
- [ ] El sidebar tiene 200px de ancho
- [ ] Los colores siguen la paleta estándar
- [ ] Es responsive para móviles
- [ ] Los botones siguen el estilo estándar
- [ ] Las cards usan el estilo homologado

## 📝 Ejemplos de Archivos Homologados

### Archivos de Referencia
- `public/apps/internal/index.html` - Dashboard principal interno
- `public/apps/internal/usuarios/login.html` - Login estándar
- `public/apps/tenant/calibraciones/add_calibration.html` - Formulario estándar

### Archivos Actualizados
- `public/apps/ticket-system/index.html` - Sistema de tickets
- `public/apps/system-retirement/index.html` - Sistema de retiro
- `public/apps/internal/developer/dashboard.html` - Dashboard desarrollador

## 🔄 Proceso de Actualización

1. **Identificar archivos no homologados**
2. **Actualizar HEAD** con estructura estándar
3. **Reemplazar colores** con variables CSS
4. **Actualizar componentes** según estándares
5. **Probar responsive** en dispositivos móviles
6. **Verificar funcionalidad** completa

## 📚 Recursos

- `assets/styles/master-theme.css` - Tema maestro
- `assets/styles/styles.css` - Estilos específicos
- Variables CSS documentadas en master-theme.css
- Componentes reutilizables definidos

---

**Nota**: Esta homologación garantiza consistencia visual y cumplimiento con normativas ISO 17025/NOM-059 en todo el sistema SBL Pharma.