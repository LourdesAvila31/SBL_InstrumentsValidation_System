# Selector de Portal Developer - Documentación

## Descripción
El Selector de Portal Developer es una interfaz unificada que permite a los usuarios elegir entre dos portales especializados de desarrollo dentro del SBL Sistema Interno.

## Ubicación
- **Archivo principal**: `/public/apps/internal/developer/selector.html`
- **Estilos específicos**: `/public/assets/styles/developer-selector.css`
- **Acceso**: A través del menú lateral principal en la sección "DEVELOPER"

## Portales Disponibles

### 1. Developer Dashboard (Administrativo)
- **Ubicación**: `/public/apps/internal/developer/dashboard.html`
- **Tipo**: Superadministrador
- **Características**:
  - Gestión completa de usuarios
  - Configuración avanzada del sistema
  - Monitoreo administrativo
  - Gestión de documentos corporativos
  - Auditoría completa
  - Gestión de backups
  - Control de incidentes críticos

### 2. Developer Private Section (Operativo)
- **Ubicación**: `/public/apps/developer/index.html`
- **Tipo**: Operativo
- **Características**:
  - Dashboard de monitoreo en tiempo real
  - Gestión de incidencias técnicas
  - Control de cambios (Change Management)
  - Documentación operativa
  - Gestión de proveedores
  - Sistema de alertas automáticas
  - Métricas y KPIs operativos

## Funcionalidades del Selector

### Navegación
- **Acceso unificado**: Punto único de entrada para ambos portales
- **Enlaces rápidos**: Acceso directo a secciones específicas
- **Responsive**: Adaptado para dispositivos móviles y tablets

### Interfaz
- **Tarjetas interactivas**: Cada portal tiene su propia tarjeta con hover effects
- **Iconografía diferenciada**: 
  - Dashboard: Escudo (fa-shield-alt) - Seguridad administrativa
  - Private Section: Laptop Code (fa-laptop-code) - Desarrollo operativo
- **Badges identificativos**:
  - Superadministrador (amarillo/dorado)
  - Operativo (azul/cián)

### Información Contextual
- **Cards informativas**: Explicación de las capacidades del sistema
- **Enlaces rápidos**: Acceso a otras secciones relacionadas
- **Notificaciones**: Feedback visual al usuario

## Integración con el Sistema

### Navegación Principal
El selector está integrado en el menú lateral principal del SBL Sistema Interno:
```html
<a class="nav-link" href="apps/internal/developer/selector.html" data-section="developer" data-requires-permission="developer">
    <i class="fa fa-code"></i>
    <span>DEVELOPER</span>
</a>
```

### Control de Acceso
- **Permiso requerido**: `developer`
- **Atributo**: `data-requires-permission="developer"`
- **Validación**: A través del sistema de roles existente

## Tecnologías Utilizadas

### Frontend
- **Bootstrap 5.3.0**: Framework CSS responsivo
- **Font Awesome 6.4.2**: Iconografía
- **Montserrat Font**: Tipografía corporativa
- **CSS3**: Animaciones y efectos avanzados

### JavaScript
- **Vanilla JS**: Sin dependencias externas
- **Responsive Sidebar**: Gestión de navegación móvil
- **Navigation Functions**: Redirección inteligente entre portales

## Personalización

### Colores Corporativos
```css
:root {
    --sbl-primary: #0d575a;
    --sbl-secondary: #217b9b;
    --sbl-success: #28a745;
    --sbl-light: #f8f9fa;
}
```

### Efectos Visuales
- **Hover Effects**: Elevación y escala de tarjetas
- **Animaciones**: fadeInUp para elementos
- **Glow Effects**: Resplandor en hover
- **Smooth Transitions**: Transiciones suaves (0.3s ease)

## Mantenimiento

### Archivos a Actualizar
1. **selector.html**: Contenido principal y estructura
2. **developer-selector.css**: Estilos específicos
3. **index.html**: Navegación principal (si cambia la ruta)

### Consideraciones
- **BASE_URL**: Configuración automática de rutas
- **Responsive**: Pruebas en móviles y tablets
- **Cross-browser**: Compatibilidad con navegadores modernos
- **Performance**: Optimización de imágenes y CSS

## URLs de Acceso

### Desarrollo Local
- **Selector**: `http://localhost/SBL_SISTEMA_INTERNO/public/apps/internal/developer/selector.html`
- **Dashboard**: `http://localhost/SBL_SISTEMA_INTERNO/public/apps/internal/developer/dashboard.html`
- **Private Section**: `http://localhost/SBL_SISTEMA_INTERNO/public/apps/developer/index.html`

### Producción
Las URLs se ajustarán automáticamente según la configuración del BASE_URL del sistema.

## Próximas Mejoras

1. **Analytics**: Tracking de uso de cada portal
2. **Favoritos**: Permitir marcar portal preferido
3. **Quick Actions**: Acciones rápidas desde el selector
4. **Recent Activity**: Mostrar actividad reciente del usuario
5. **Customization**: Personalización por usuario

---

**Fecha de creación**: $(date)
**Versión**: 1.0
**Autor**: SBL Development Team