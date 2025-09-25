# Guía Rápida - Sistema de Normalización CSV

## 🚀 Inicio Rápido (5 minutos)

### 1. Ejecutar Análisis
```bash
cd c:\xampp\htdocs\SBL_SISTEMA_INTERNO
python tools\scripts\csv_normalization_analyzer.py
```

### 2. Revisar y Aprobar
1. Abrir el archivo `tools\analysis_results\README_cambios_XXXXXXXXX.md`
2. Marcar con `[x]` las reglas a aprobar
3. **Completar obligatoriamente:**
   ```markdown
   **Ingeniera Responsable:**
   - Nombre: Tu Nombre Completo
   - Correo: tu.email@sblpharma.com
   - Firma: Tu Firma
   - Fecha: 2025-09-25
   ```

### 3. Aplicar Cambios
```bash
python tools\scripts\csv_normalization_applicator.py
```

### 4. Importar en phpMyAdmin
1. Abrir phpMyAdmin
2. Importar: `app\Modules\Internal\ArchivosSql\Archivos_BD_SBL\SBL_inserts\insert_audit_trail_SC.sql`

## ✅ ¡Listo!

Los cambios ahora están registrados en el audit trail con trazabilidad completa.

---

**¿Necesitas más detalles?** 📖 Revisa `README_sistema_normalizacion.md`