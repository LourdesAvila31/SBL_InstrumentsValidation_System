# Análisis de Cambios en Normalización de CSV

**ID de Análisis:** CSV_NORM_20250925_162416
**Última actualización:** 2025-09-25 16:24:16
**Versión:** v20250925_162416
**Archivos analizados:** 4
**Total de cambios detectados:** 1076

---
*Este archivo se actualiza automáticamente con cada análisis. La versión y timestamp indican la última generación.*

## 📋 Resumen por Tipo de Cambio

- **Homologation:** 412 cambios
- **Format:** 664 cambios

## 🔍 Reglas de Normalización Detectadas

### HOM001: Corrección ortográfica de nombres de instrumentos
- **Tipo:** homologation
- **Confianza:** 95%
- **Impacto:** low
- **Estado:** ✅ Automática
- **Ejemplos:**
  - Termohigrometro → Termohigrómetro
  - termohigrometro → termohigrómetro
  - TERMOHIGROMETRO → Termohigrómetro
  - termohigrÓmetro → termohigrómetro
  - TermohigrÓmetro → Termohigrómetro

### HOM002: Estandarización de nombres de marcas
- **Tipo:** homologation
- **Confianza:** 90%
- **Impacto:** medium
- **Estado:** ⚠️ **REQUIERE APROBACIÓN**
- **Ejemplos:**
  - Ohaus → OHAUS
  - ohaus → OHAUS
  - OHAUS → OHAUS
  - Extech Instrument → Extech Instruments
  - Extech → Extech Instruments

### HOM003: Estandarización de nombres de departamentos
- **Tipo:** homologation
- **Confianza:** 85%
- **Impacto:** medium
- **Estado:** ⚠️ **REQUIERE APROBACIÓN**
- **Ejemplos:**
  - Almacen → Almacén
  - almacen → Almacén
  - Desarrollo Analitico → Desarrollo Analítico
  - desarrollo analitico → Desarrollo Analítico
  - Desarrollo Farmaceutico → Desarrollo Farmacéutico

### HOM004: Estandarización de nombres de ubicaciones
- **Tipo:** homologation
- **Confianza:** 85%
- **Impacto:** medium
- **Estado:** ⚠️ **REQUIERE APROBACIÓN**
- **Ejemplos:**
  - Analisis Fisicoquimicos → Análisis Fisicoquímicos
  - analisis fisicoquimicos → Análisis Fisicoquímicos
  - Analisis Instrumental → Análisis Instrumental
  - analisis instrumental → Análisis Instrumental
  - Disolucion → Disolución

### HOM005: Estandarización de estados y status
- **Tipo:** homologation
- **Confianza:** 90%
- **Impacto:** medium
- **Estado:** ⚠️ **REQUIERE APROBACIÓN**
- **Ejemplos:**
  - activo → Activo
  - ACTIVO → Activo
  - Active → Activo
  - active → Activo
  - inactivo → Inactivo

### HOM006: Estandarización de valores placeholder (NA, ND, etc.)
- **Tipo:** homologation
- **Confianza:** 95%
- **Impacto:** low
- **Estado:** ✅ Automática
- **Ejemplos:**
  - ND → ND
  - nd → ND
  - N/D → ND
  - n/d → ND
  - N.D. → ND

### HOM007: Estandarización de unidades de medida
- **Tipo:** homologation
- **Confianza:** 95%
- **Impacto:** low
- **Estado:** ✅ Automática
- **Ejemplos:**
  - grados C → °C
  - ºC → °C
  - celsius → °C
  - Celsius → °C
  - kg/cm2 → kg/cm²

### SPL001: Corrección de errores ortográficos comunes
- **Tipo:** spelling
- **Confianza:** 95%
- **Impacto:** low
- **Estado:** ✅ Automática
- **Ejemplos:**
  - termometro → termómetro
  - bascula → báscula
  - cronometro → cronómetro

### FMT001: Normalización de formato de fechas
- **Tipo:** format
- **Confianza:** 95%
- **Impacto:** high
- **Estado:** ⚠️ **REQUIERE APROBACIÓN**
- **Ejemplos:**
  - 15/03/2024 → 2024-03-15
  - 3-15-24 → 2024-03-15

### FMT002: Normalización de precisión numérica
- **Tipo:** format
- **Confianza:** 80%
- **Impacto:** medium
- **Estado:** ⚠️ **REQUIERE APROBACIÓN**
- **Ejemplos:**
  - 25.456789 → 25.46
  - 1.2345 → 1.23

### FMT003: Limpieza de espacios en blanco
- **Tipo:** format
- **Confianza:** 100%
- **Impacto:** low
- **Estado:** ✅ Automática
- **Ejemplos:**
  -   texto   → texto
  - inicio  fin → inicio fin

## ✅ Opciones de Aprobación

Para cada regla que requiere aprobación, marque su decisión:

### HOM002: Estandarización de nombres de marcas
- [ ] ✅ **APROBAR** - Aplicar esta regla de normalización
- [ ] ❌ **RECHAZAR** - No aplicar esta regla
- [ ] 🔧 **MODIFICAR** - Aplicar con modificaciones (especificar abajo)

**Comentarios:**
```
[Espacio para comentarios de la ingeniera]
```

### HOM003: Estandarización de nombres de departamentos
- [ ] ✅ **APROBAR** - Aplicar esta regla de normalización
- [ ] ❌ **RECHAZAR** - No aplicar esta regla
- [ ] 🔧 **MODIFICAR** - Aplicar con modificaciones (especificar abajo)

**Comentarios:**
```
[Espacio para comentarios de la ingeniera]
```

### HOM004: Estandarización de nombres de ubicaciones
- [ ] ✅ **APROBAR** - Aplicar esta regla de normalización
- [ ] ❌ **RECHAZAR** - No aplicar esta regla
- [ ] 🔧 **MODIFICAR** - Aplicar con modificaciones (especificar abajo)

**Comentarios:**
```
[Espacio para comentarios de la ingeniera]
```

### HOM005: Estandarización de estados y status
- [ ] ✅ **APROBAR** - Aplicar esta regla de normalización
- [ ] ❌ **RECHAZAR** - No aplicar esta regla
- [ ] 🔧 **MODIFICAR** - Aplicar con modificaciones (especificar abajo)

**Comentarios:**
```
[Espacio para comentarios de la ingeniera]
```

### FMT001: Normalización de formato de fechas
- [ ] ✅ **APROBAR** - Aplicar esta regla de normalización
- [ ] ❌ **RECHAZAR** - No aplicar esta regla
- [ ] 🔧 **MODIFICAR** - Aplicar con modificaciones (especificar abajo)

**Comentarios:**
```
[Espacio para comentarios de la ingeniera]
```

### FMT002: Normalización de precisión numérica
- [ ] ✅ **APROBAR** - Aplicar esta regla de normalización
- [ ] ❌ **RECHAZAR** - No aplicar esta regla
- [ ] 🔧 **MODIFICAR** - Aplicar con modificaciones (especificar abajo)

**Comentarios:**
```
[Espacio para comentarios de la ingeniera]
```

## 👩‍🔬 Sección de Aprobación

**Ingeniera Responsable:**
- Nombre: ________________________
- Correo: ________________________
- Firma: ________________________
- Fecha: ________________________

**Decisión Final:**
- [ ] Aprobar todas las reglas automáticas
- [ ] Aprobar solo las reglas seleccionadas arriba
- [ ] Rechazar todo el análisis

## 📁 Archivos Relacionados

- **Análisis detallado (JSON):** `analisis_cambios_normalizacion.json`
- **SQL propuesto:** `propuesta_audit_trail.sql`
- **Resumen ejecutivo:** `resumen_ejecutivo.md`
- **📋 Lista por instrumento:** `cambios_por_instrumento.md`
- **📊 Datos CSV:** `cambios_detallados.csv`

> 💡 **Recomendación:** Comience revisando el archivo 'Lista por instrumento' para ver los cambios organizados por código de equipo.
> 📊 **Para análisis:** Use el archivo CSV para filtros y análisis avanzados en Excel.
