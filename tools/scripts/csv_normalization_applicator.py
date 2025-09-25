#!/usr/bin/env python3
"""
Aplicador de Cambios de Normalización CSV con Audit Trail
=========================================================

Este script aplica los cambios aprobados por las ingenieras durante la normalización
de archivos CSV y genera automáticamente el audit trail correspondiente.

Funcionalidades:
- Lee la aprobación de cambios del README generado
- Aplica solo los cambios aprobados
- Genera audit trail con datos de la ingeniera responsable
- Crea archivo SQL insert_audit_trail_SC.sql para phpMyAdmin
- Mantiene trazabilidad completa de todos los cambios

Autor: Sistema SBL - Validación de Instrumentos
Fecha: Septiembre 2025
"""

import json
import re
import csv
import datetime as dt
from dataclasses import dataclass, asdict
from pathlib import Path
from typing import List, Dict, Optional, Tuple
import hashlib

# Configuración de directorios
BASE_DIR = Path(__file__).resolve().parent.parent.parent
ARCHIVOS_SQL_DIR = BASE_DIR / "app" / "Modules" / "Internal" / "ArchivosSql"
CSV_ORIGINAL_DIR = ARCHIVOS_SQL_DIR / "Archivos_CSV_originales"
NORMALIZE_DIR = ARCHIVOS_SQL_DIR / "Archivos_Normalize"
ANALYSIS_DIR = BASE_DIR / "tools" / "analysis_results"
AUDIT_OUTPUT_DIR = ARCHIVOS_SQL_DIR / "Archivos_BD_SBL" / "SBL_inserts"

# Crear directorios si no existen
AUDIT_OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

@dataclass
class ApprovedChange:
    """Representa un cambio aprobado para aplicar"""
    change_id: str
    file_name: str
    row_number: int
    column_name: str
    cell_reference: str
    original_value: str
    normalized_value: str
    rule_id: str
    rule_description: str
    approval_status: str  # 'approved', 'rejected', 'modified'
    engineer_name: str
    engineer_email: str
    engineer_signature: str
    approval_date: dt.datetime
    comments: str

@dataclass
class AuditTrailEntry:
    """Representa una entrada del audit trail"""
    empresa_id: int
    segmento_actor: str
    fecha_evento: dt.datetime
    seccion: str
    rango_referencia: str
    instrumento_id: Optional[int]
    valor_anterior: Optional[str]
    valor_nuevo: Optional[str]
    usuario_id: Optional[int]
    usuario_correo: str
    usuario_nombre: str
    usuario_firma_interna: str
    instrumento_codigo: Optional[str]
    columna_excel: str
    fila_excel: int

class CSVNormalizationApplicator:
    """Aplicador de cambios de normalización aprobados"""
    
    def __init__(self):
        self.approved_changes: List[ApprovedChange] = []
        self.audit_entries: List[AuditTrailEntry] = []
    
    def load_approval_data(self, readme_file: Path) -> Dict:
        """Carga los datos de aprobación del README"""
        if not readme_file.exists():
            raise FileNotFoundError(f"No se encontró el archivo de aprobación: {readme_file}")
        
        with open(readme_file, 'r', encoding='utf-8') as f:
            content = f.read()
        
        # Extraer datos de la ingeniera responsable
        engineer_data = self._extract_engineer_data(content)
        
        # Extraer decisiones de aprobación
        approvals = self._extract_approval_decisions(content)
        
        return {
            'engineer': engineer_data,
            'approvals': approvals,
            'content': content
        }
    
    def _extract_engineer_data(self, content: str) -> Dict:
        """Extrae los datos de la ingeniera del README"""
        engineer_data = {
            'name': '',
            'email': '',
            'signature': '',
            'date': dt.datetime.now()
        }
        
        # Buscar sección de aprobación
        approval_section = re.search(
            r'## 👩‍🔬 Sección de Aprobación\s*\n\n(.*?)(?=\n##|\Z)', 
            content, re.DOTALL
        )
        
        if approval_section:
            section_text = approval_section.group(1)
            
            # Extraer nombre
            name_match = re.search(r'Nombre:\s*([^\n]+)', section_text)
            if name_match:
                engineer_data['name'] = name_match.group(1).strip().replace('_', '').strip()
            
            # Extraer correo
            email_match = re.search(r'Correo:\s*([^\n]+)', section_text)
            if email_match:
                engineer_data['email'] = email_match.group(1).strip().replace('_', '').strip()
            
            # Extraer firma
            signature_match = re.search(r'Firma:\s*([^\n]+)', section_text)
            if signature_match:
                engineer_data['signature'] = signature_match.group(1).strip().replace('_', '').strip()
            
            # Extraer fecha
            date_match = re.search(r'Fecha:\s*([^\n]+)', section_text)
            if date_match:
                date_str = date_match.group(1).strip().replace('_', '').strip()
                if date_str:
                    try:
                        engineer_data['date'] = dt.datetime.strptime(date_str, '%Y-%m-%d')
                    except ValueError:
                        engineer_data['date'] = dt.datetime.now()
        
        return engineer_data
    
    def _extract_approval_decisions(self, content: str) -> Dict[str, str]:
        """Extrae las decisiones de aprobación del README"""
        approvals = {}
        
        # Buscar todas las secciones de reglas con checkboxes
        rule_sections = re.finditer(
            r'### ([A-Z]+\d+): ([^\n]+)\n(.*?)(?=\n### |\n## |\Z)', 
            content, re.DOTALL
        )
        
        for match in rule_sections:
            rule_id = match.group(1)
            rule_desc = match.group(2)
            section_content = match.group(3)
            
            # Verificar qué opción está marcada
            if re.search(r'- \[x\] ✅ \*\*APROBAR\*\*', section_content, re.IGNORECASE):
                approvals[rule_id] = 'approved'
            elif re.search(r'- \[x\] ❌ \*\*RECHAZAR\*\*', section_content, re.IGNORECASE):
                approvals[rule_id] = 'rejected'
            elif re.search(r'- \[x\] 🔧 \*\*MODIFICAR\*\*', section_content, re.IGNORECASE):
                approvals[rule_id] = 'modified'
            else:
                approvals[rule_id] = 'pending'
        
        return approvals
    
    def load_original_analysis(self, analysis_file: Path) -> Dict:
        """Carga el análisis original desde JSON"""
        if not analysis_file.exists():
            raise FileNotFoundError(f"No se encontró el archivo de análisis: {analysis_file}")
        
        with open(analysis_file, 'r', encoding='utf-8') as f:
            return json.load(f)
    
    def process_approved_changes(self, approval_data: Dict, original_analysis: Dict):
        """Procesa los cambios aprobados y genera las entradas de audit trail"""
        engineer = approval_data['engineer']
        approvals = approval_data['approvals']
        
        if not engineer['name'] or not engineer['email']:
            raise ValueError("Los datos de la ingeniera responsable son obligatorios")
        
        # Procesar cada cambio detectado
        for change_data in original_analysis['detected_changes']:
            rule_id = change_data['change_rule']['rule_id']
            
            # Verificar si esta regla fue aprobada
            if rule_id in approvals and approvals[rule_id] == 'approved':
                # Crear entrada de cambio aprobado
                approved_change = ApprovedChange(
                    change_id=change_data['change_hash'],
                    file_name=change_data['file_name'],
                    row_number=change_data['row_number'],
                    column_name=change_data['column_name'],
                    cell_reference=change_data['cell_reference'],
                    original_value=change_data['original_value'],
                    normalized_value=change_data['normalized_value'],
                    rule_id=rule_id,
                    rule_description=change_data['change_rule']['description'],
                    approval_status='approved',
                    engineer_name=engineer['name'],
                    engineer_email=engineer['email'],
                    engineer_signature=engineer['signature'],
                    approval_date=engineer['date'],
                    comments=""
                )
                
                self.approved_changes.append(approved_change)
                
                # Crear entrada de audit trail
                audit_entry = AuditTrailEntry(
                    empresa_id=1,
                    segmento_actor='Ingenieras de Validación',
                    fecha_evento=dt.datetime.now(),
                    seccion=f'Normalización CSV - {change_data["file_name"]}',
                    rango_referencia=change_data['cell_reference'],
                    instrumento_id=None,
                    valor_anterior=change_data['original_value'],
                    valor_nuevo=change_data['normalized_value'],
                    usuario_id=None,
                    usuario_correo=engineer['email'],
                    usuario_nombre=engineer['name'],
                    usuario_firma_interna=engineer['signature'],
                    instrumento_codigo=None,
                    columna_excel=change_data['cell_reference'][:-len(str(change_data['row_number']))],
                    fila_excel=change_data['row_number']
                )
                
                self.audit_entries.append(audit_entry)
    
    def generate_audit_trail_sql(self) -> Path:
        """Genera el archivo SQL insert_audit_trail_SC.sql"""
        timestamp = dt.datetime.now().strftime("%Y%m%d_%H%M%S")
        output_file = AUDIT_OUTPUT_DIR / "insert_audit_trail_SC.sql"
        
        with open(output_file, 'w', encoding='utf-8') as f:
            f.write("-- Audit Trail para Cambios de Normalización CSV Aprobados\n")
            f.write(f"-- Generado automáticamente el {dt.datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n")
            f.write(f"-- Total de cambios aprobados: {len(self.audit_entries)}\n")
            
            if self.approved_changes:
                engineer = self.approved_changes[0]  # Todos tienen la misma ingeniera
                f.write(f"-- Ingeniera responsable: {engineer.engineer_name} ({engineer.engineer_email})\n")
                f.write(f"-- Fecha de aprobación: {engineer.approval_date.strftime('%Y-%m-%d')}\n")
            
            f.write("\n-- IMPORTANTE: Este archivo está listo para importar en phpMyAdmin\n")
            f.write("-- Verificar los datos antes de ejecutar\n\n")
            
            f.write("START TRANSACTION;\n\n")
            
            # Generar INSERTs en chunks de 100 para mejor rendimiento
            chunk_size = 100
            for i in range(0, len(self.audit_entries), chunk_size):
                chunk = self.audit_entries[i:i + chunk_size]
                
                f.write(f"-- Lote {i//chunk_size + 1} de {(len(self.audit_entries) + chunk_size - 1)//chunk_size}\n")
                f.write("INSERT INTO audit_trail (\n")
                f.write("    empresa_id,\n")
                f.write("    segmento_actor,\n")
                f.write("    fecha_evento,\n")
                f.write("    seccion,\n")
                f.write("    rango_referencia,\n")
                f.write("    instrumento_id,\n")
                f.write("    valor_anterior,\n")
                f.write("    valor_nuevo,\n")
                f.write("    usuario_id,\n")
                f.write("    usuario_correo,\n")
                f.write("    usuario_nombre,\n")
                f.write("    usuario_firma_interna,\n")
                f.write("    instrumento_codigo,\n")
                f.write("    columna_excel,\n")
                f.write("    fila_excel\n")
                f.write(") VALUES\n")
                
                values = []
                for entry in chunk:
                    value_line = (
                        f"    ({entry.empresa_id}, "
                        f"{self._sql_escape(entry.segmento_actor)}, "
                        f"'{entry.fecha_evento.strftime('%Y-%m-%d %H:%M:%S')}', "
                        f"{self._sql_escape(entry.seccion)}, "
                        f"{self._sql_escape(entry.rango_referencia)}, "
                        f"{entry.instrumento_id or 'NULL'}, "
                        f"{self._sql_escape(entry.valor_anterior)}, "
                        f"{self._sql_escape(entry.valor_nuevo)}, "
                        f"{entry.usuario_id or 'NULL'}, "
                        f"{self._sql_escape(entry.usuario_correo)}, "
                        f"{self._sql_escape(entry.usuario_nombre)}, "
                        f"{self._sql_escape(entry.usuario_firma_interna)}, "
                        f"{self._sql_escape(entry.instrumento_codigo)}, "
                        f"{self._sql_escape(entry.columna_excel)}, "
                        f"{entry.fila_excel})"
                    )
                    values.append(value_line)
                
                f.write(",\n".join(values))
                f.write(";\n\n")
            
            f.write("-- Verificar los registros insertados\n")
            f.write("SELECT COUNT(*) as 'Registros insertados' FROM audit_trail \n")
            f.write("WHERE usuario_correo IN (")
            
            unique_emails = set(entry.usuario_correo for entry in self.audit_entries)
            email_list = ", ".join(f"'{email}'" for email in unique_emails)
            f.write(email_list)
            f.write(")\n")
            f.write("AND fecha_evento >= CURDATE();\n\n")
            
            f.write("-- Solo descomentar COMMIT después de verificar los datos\n")
            f.write("COMMIT;\n")
            f.write("-- ROLLBACK; -- Usar para cancelar si hay errores\n")
        
        return output_file
    
    def generate_summary_report(self) -> Path:
        """Genera reporte resumen de cambios aplicados"""
        timestamp = dt.datetime.now().strftime("%Y%m%d_%H%M%S")
        summary_file = ANALYSIS_DIR / f"resumen_cambios_aplicados_{timestamp}.md"
        
        with open(summary_file, 'w', encoding='utf-8') as f:
            f.write("# Resumen de Cambios de Normalización Aplicados\n\n")
            f.write(f"**Fecha de aplicación:** {dt.datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n")
            f.write(f"**Total de cambios aplicados:** {len(self.approved_changes)}\n\n")
            
            if self.approved_changes:
                engineer = self.approved_changes[0]
                f.write("## 👩‍🔬 Ingeniera Responsable\n")
                f.write(f"- **Nombre:** {engineer.engineer_name}\n")
                f.write(f"- **Correo:** {engineer.engineer_email}\n")
                f.write(f"- **Firma:** {engineer.engineer_signature}\n")
                f.write(f"- **Fecha de aprobación:** {engineer.approval_date.strftime('%Y-%m-%d')}\n\n")
            
            # Agrupar cambios por archivo
            files_summary = {}
            for change in self.approved_changes:
                if change.file_name not in files_summary:
                    files_summary[change.file_name] = []
                files_summary[change.file_name].append(change)
            
            f.write("## 📁 Cambios por Archivo\n\n")
            for file_name, changes in files_summary.items():
                f.write(f"### {file_name}\n")
                f.write(f"**Total de cambios:** {len(changes)}\n\n")
                
                f.write("| Celda | Columna | Valor Original | Valor Normalizado | Regla |\n")
                f.write("|-------|---------|----------------|-------------------|-------|\n")
                
                for change in changes[:10]:  # Mostrar máximo 10 por archivo
                    f.write(f"| {change.cell_reference} | {change.column_name} | ")
                    f.write(f"{change.original_value[:30]}{'...' if len(change.original_value) > 30 else ''} | ")
                    f.write(f"{change.normalized_value[:30]}{'...' if len(change.normalized_value) > 30 else ''} | ")
                    f.write(f"{change.rule_id} |\n")
                
                if len(changes) > 10:
                    f.write(f"\n*... y {len(changes) - 10} cambios más*\n")
                f.write("\n")
            
            f.write("## ✅ Estado del Audit Trail\n")
            f.write(f"- **Registros generados:** {len(self.audit_entries)}\n")
            f.write("- **Archivo SQL:** `insert_audit_trail_SC.sql`\n")
            f.write("- **Estado:** Listo para importar en phpMyAdmin\n\n")
            
            f.write("## 🔄 Próximos Pasos\n")
            f.write("1. **Verificar** el archivo `insert_audit_trail_SC.sql`\n")
            f.write("2. **Importar** en phpMyAdmin para actualizar la base de datos\n")
            f.write("3. **Verificar** que los registros se insertaron correctamente\n")
            f.write("4. **Archivar** estos documentos como evidencia del proceso\n")
        
        return summary_file
    
    def _sql_escape(self, value) -> str:
        """Escapa valores para SQL"""
        if value is None:
            return "NULL"
        if isinstance(value, str):
            escaped = value.replace("'", "''").replace("\\", "\\\\")
            return f"'{escaped}'"
        return f"'{str(value)}'"
    
    def find_latest_analysis_files(self) -> Tuple[Optional[Path], Optional[Path]]:
        """Encuentra los archivos de análisis más recientes"""
        if not ANALYSIS_DIR.exists():
            return None, None
        
        # Buscar archivos de análisis
        json_files = list(ANALYSIS_DIR.glob("analisis_cambios_*.json"))
        readme_files = list(ANALYSIS_DIR.glob("README_cambios_*.md"))
        
        if not json_files or not readme_files:
            return None, None
        
        # Tomar los más recientes
        latest_json = max(json_files, key=lambda x: x.stat().st_mtime)
        latest_readme = max(readme_files, key=lambda x: x.stat().st_mtime)
        
        return latest_json, latest_readme

def main():
    """Función principal"""
    print("🔧 Iniciando aplicación de cambios de normalización aprobados...")
    print("=" * 70)
    
    applicator = CSVNormalizationApplicator()
    
    # Buscar archivos de análisis más recientes
    analysis_file, readme_file = applicator.find_latest_analysis_files()
    
    if not analysis_file or not readme_file:
        print("❌ Error: No se encontraron archivos de análisis.")
        print("   Ejecute primero 'csv_normalization_analyzer.py'")
        return
    
    print(f"📄 Usando análisis: {analysis_file.name}")
    print(f"📋 Usando aprobación: {readme_file.name}")
    
    try:
        # Cargar datos
        print("\n🔍 Cargando datos de aprobación...")
        approval_data = applicator.load_approval_data(readme_file)
        
        print("📊 Cargando análisis original...")
        original_analysis = applicator.load_original_analysis(analysis_file)
        
        # Verificar que hay datos de ingeniera
        engineer = approval_data['engineer']
        if not engineer['name'] or not engineer['email']:
            print("❌ Error: Faltan datos de la ingeniera responsable en el README.")
            print("   Complete la sección '👩‍🔬 Sección de Aprobación' y vuelva a ejecutar.")
            return
        
        print(f"👩‍🔬 Ingeniera responsable: {engineer['name']} ({engineer['email']})")
        
        # Procesar cambios aprobados
        print("\n⚙️  Procesando cambios aprobados...")
        applicator.process_approved_changes(approval_data, original_analysis)
        
        approved_count = len(applicator.approved_changes)
        if approved_count == 0:
            print("⚠️  No se encontraron cambios aprobados.")
            print("   Verifique que ha marcado [x] las opciones ✅ APROBAR en el README.")
            return
        
        print(f"✅ Procesados {approved_count} cambios aprobados")
        
        # Generar archivos de salida
        print("\n📝 Generando audit trail...")
        sql_file = applicator.generate_audit_trail_sql()
        
        print("📊 Generando reporte resumen...")
        summary_file = applicator.generate_summary_report()
        
        print(f"\n🎉 ¡Proceso completado exitosamente!")
        print(f"📁 Archivos generados:")
        print(f"   🗄️  SQL para phpMyAdmin: {sql_file}")
        print(f"   📋 Resumen de cambios: {summary_file}")
        
        print(f"\n📊 Estadísticas:")
        print(f"   • Cambios aplicados: {approved_count}")
        print(f"   • Registros audit_trail: {len(applicator.audit_entries)}")
        print(f"   • Ingeniera responsable: {engineer['name']}")
        
        print(f"\n🔄 Próximo paso:")
        print(f"   Importar el archivo '{sql_file.name}' en phpMyAdmin")
        
    except Exception as e:
        print(f"❌ Error: {str(e)}")
        return

if __name__ == "__main__":
    main()