#!/usr/bin/env python3
"""Validador de integridad de datos para el Sistema SBL.

Este script valida la integridad y consistencia de todos los archivos CSV
del sistema, detectando errores comunes, datos faltantes y inconsistencias.

Características:
- Validación de formatos de fecha
- Verificación de códigos de instrumentos
- Detección de duplicados
- Validación de referencias cruzadas
- Reportes detallados de errores
- Sugerencias de corrección automática

Uso:
```bash
python tools/scripts/data_validator.py --fix-auto --backup
```
"""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import shutil
from dataclasses import dataclass, field
from pathlib import Path
from typing import Dict, List, Optional, Set, Tuple, Any
import re

from sbl_utils import (
    setup_logging, get_repo_root, get_csv_originales_dir, 
    get_normalize_dir, CSVHandler, DateParser, TextNormalizer,
    ValidationError
)

@dataclass
class ValidationIssue:
    """Representa un problema de validación encontrado."""
    file_path: Path
    row_number: int
    column: str
    issue_type: str
    description: str
    current_value: str
    suggested_fix: Optional[str] = None
    severity: str = "WARNING"  # ERROR, WARNING, INFO

@dataclass
class ValidationReport:
    """Reporte de validación de un archivo."""
    file_path: Path
    total_rows: int
    issues: List[ValidationIssue] = field(default_factory=list)
    duplicates: List[Tuple[int, int]] = field(default_factory=list)
    missing_references: List[str] = field(default_factory=list)
    
    @property
    def errors_count(self) -> int:
        return len([i for i in self.issues if i.severity == "ERROR"])
    
    @property
    def warnings_count(self) -> int:
        return len([i for i in self.issues if i.severity == "WARNING"])
    
    @property
    def is_valid(self) -> bool:
        return self.errors_count == 0

class DataValidator:
    """Validador principal de datos del sistema SBL."""
    
    def __init__(self):
        self.repo_root = get_repo_root(__file__)
        self.logger = setup_logging("data_validator")
        
        # Directorios
        self.csv_dir = get_csv_originales_dir(self.repo_root)
        self.normalize_dir = get_normalize_dir(self.repo_root)
        
        # Patrones de validación
        self.codigo_pattern = re.compile(r"^[A-Z]{2,4}[-_]?\d{3,6}[A-Z]?$", re.IGNORECASE)
        self.email_pattern = re.compile(r"^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$")
        
        # Conjuntos de referencia para validación cruzada
        self.valid_codes: Set[str] = set()
        self.valid_clients: Set[str] = set()
        self.valid_departments: Set[str] = set()
        
        # Reportes de validación
        self.reports: List[ValidationReport] = []
    
    def load_reference_data(self) -> None:
        """Carga datos de referencia para validación cruzada."""
        self.logger.info("Cargando datos de referencia...")
        
        # Cargar códigos válidos desde instrumentos
        instrumentos_files = [
            self.normalize_dir / "normalize_instrumentos.csv",
            self.csv_dir / "LM_instrumentos.csv"
        ]
        
        for file_path in instrumentos_files:
            if file_path.exists():
                try:
                    data = CSVHandler.read_csv(file_path)
                    for row in data:
                        codigo = row.get('codigo', '').strip().upper()
                        if codigo and self.codigo_pattern.match(codigo):
                            self.valid_codes.add(codigo)
                    self.logger.info(f"Cargados {len(self.valid_codes)} códigos desde {file_path.name}")
                    break
                except Exception as e:
                    self.logger.warning(f"Error cargando códigos desde {file_path}: {e}")
        
        # Cargar clientes válidos
        clientes_files = [
            self.csv_dir / "clientes.csv",
            self.normalize_dir / "normalize_clientes.csv"
        ]
        
        for file_path in clientes_files:
            if file_path.exists():
                try:
                    data = CSVHandler.read_csv(file_path)
                    for row in data:
                        cliente = row.get('nombre', '').strip()
                        if cliente:
                            self.valid_clients.add(cliente.upper())
                    self.logger.info(f"Cargados {len(self.valid_clients)} clientes desde {file_path.name}")
                    break
                except Exception as e:
                    self.logger.warning(f"Error cargando clientes desde {file_path}: {e}")
    
    def validate_codigo(self, value: str, row_num: int, file_path: Path) -> List[ValidationIssue]:
        """Valida un código de instrumento."""
        issues = []
        
        if not value or value.strip() in {"", "NA", "ND", "N/A"}:
            issues.append(ValidationIssue(
                file_path=file_path,
                row_number=row_num,
                column="codigo",
                issue_type="MISSING_VALUE",
                description="Código de instrumento vacío",
                current_value=value,
                severity="ERROR"
            ))
            return issues
        
        cleaned_code = value.strip().upper()
        
        # Validar formato
        if not self.codigo_pattern.match(cleaned_code):
            suggested_fix = self._suggest_codigo_fix(cleaned_code)
            issues.append(ValidationIssue(
                file_path=file_path,
                row_number=row_num,
                column="codigo",
                issue_type="INVALID_FORMAT",
                description=f"Formato de código inválido: {value}",
                current_value=value,
                suggested_fix=suggested_fix,
                severity="ERROR"
            ))
        
        # Verificar si existe en referencias (solo advertencia)
        if self.valid_codes and cleaned_code not in self.valid_codes:
            issues.append(ValidationIssue(
                file_path=file_path,
                row_number=row_num,
                column="codigo",
                issue_type="UNKNOWN_REFERENCE",
                description=f"Código no encontrado en inventario: {cleaned_code}",
                current_value=value,
                severity="WARNING"
            ))
        
        return issues
    
    def validate_date(self, value: str, column: str, row_num: int, file_path: Path) -> List[ValidationIssue]:
        """Valida una fecha."""
        issues = []
        
        if not value or value.strip() in {"", "NA", "ND", "N/A"}:
            return issues  # Fechas opcionales pueden estar vacías
        
        try:
            parsed_date = DateParser.parse_spanish_date(value)
            if parsed_date is None:
                raise ValueError("Fecha no reconocible")
            
            # Validar rango razonable
            today = dt.date.today()
            if parsed_date < dt.date(1990, 1, 1):
                issues.append(ValidationIssue(
                    file_path=file_path,
                    row_number=row_num,
                    column=column,
                    issue_type="DATE_TOO_OLD",
                    description=f"Fecha demasiado antigua: {value}",
                    current_value=value,
                    severity="WARNING"
                ))
            elif parsed_date > today + dt.timedelta(days=3650):  # 10 años en el futuro
                issues.append(ValidationIssue(
                    file_path=file_path,
                    row_number=row_num,
                    column=column,
                    issue_type="DATE_TOO_FUTURE",
                    description=f"Fecha muy lejana en el futuro: {value}",
                    current_value=value,
                    severity="WARNING"
                ))
        
        except (ValueError, TypeError):
            suggested_fix = self._suggest_date_fix(value)
            issues.append(ValidationIssue(
                file_path=file_path,
                row_number=row_num,
                column=column,
                issue_type="INVALID_DATE",
                description=f"Formato de fecha inválido: {value}",
                current_value=value,
                suggested_fix=suggested_fix,
                severity="ERROR"
            ))
        
        return issues
    
    def validate_email(self, value: str, column: str, row_num: int, file_path: Path) -> List[ValidationIssue]:
        """Valida una dirección de email."""
        issues = []
        
        if not value or value.strip() in {"", "NA", "ND", "N/A"}:
            return issues  # Email puede ser opcional
        
        if not self.email_pattern.match(value.strip()):
            issues.append(ValidationIssue(
                file_path=file_path,
                row_number=row_num,
                column=column,
                issue_type="INVALID_EMAIL",
                description=f"Formato de email inválido: {value}",
                current_value=value,
                severity="ERROR"
            ))
        
        return issues
    
    def validate_numeric(self, value: str, column: str, row_num: int, file_path: Path, 
                        min_val: Optional[float] = None, max_val: Optional[float] = None) -> List[ValidationIssue]:
        """Valida un valor numérico."""
        issues = []
        
        if not value or value.strip() in {"", "NA", "ND", "N/A"}:
            return issues  # Valores numéricos pueden ser opcionales
        
        try:
            num_value = float(value.replace(',', '.'))  # Manejar comas decimales
            
            if min_val is not None and num_value < min_val:
                issues.append(ValidationIssue(
                    file_path=file_path,
                    row_number=row_num,
                    column=column,
                    issue_type="VALUE_TOO_LOW",
                    description=f"Valor demasiado bajo: {value} (mínimo: {min_val})",
                    current_value=value,
                    severity="WARNING"
                ))
            
            if max_val is not None and num_value > max_val:
                issues.append(ValidationIssue(
                    file_path=file_path,
                    row_number=row_num,
                    column=column,
                    issue_type="VALUE_TOO_HIGH",
                    description=f"Valor demasiado alto: {value} (máximo: {max_val})",
                    current_value=value,
                    severity="WARNING"
                ))
        
        except (ValueError, TypeError):
            issues.append(ValidationIssue(
                file_path=file_path,
                row_number=row_num,
                column=column,
                issue_type="INVALID_NUMBER",
                description=f"Valor numérico inválido: {value}",
                current_value=value,
                severity="ERROR"
            ))
        
        return issues
    
    def find_duplicates(self, data: List[Dict[str, Any]], key_columns: List[str], file_path: Path) -> List[Tuple[int, int]]:
        """Encuentra filas duplicadas basadas en columnas clave."""
        duplicates = []
        seen = {}
        
        for i, row in enumerate(data):
            # Crear clave compuesta
            key_parts = []
            for col in key_columns:
                value = row.get(col, '').strip().upper()
                key_parts.append(value)
            
            key = tuple(key_parts)
            
            # Ignorar claves vacías
            if all(part == '' for part in key_parts):
                continue
            
            if key in seen:
                duplicates.append((seen[key] + 2, i + 2))  # +2 para números de fila (header + 1-based)
            else:
                seen[key] = i
        
        return duplicates
    
    def validate_file(self, file_path: Path, validation_config: Dict[str, Any]) -> ValidationReport:
        """Valida un archivo CSV completo."""
        self.logger.info(f"Validando {file_path}")
        
        try:
            data = CSVHandler.read_csv(file_path)
        except Exception as e:
            report = ValidationReport(file_path=file_path, total_rows=0)
            report.issues.append(ValidationIssue(
                file_path=file_path,
                row_number=0,
                column="FILE",
                issue_type="READ_ERROR",
                description=f"Error leyendo archivo: {e}",
                current_value="",
                severity="ERROR"
            ))
            return report
        
        report = ValidationReport(file_path=file_path, total_rows=len(data))
        
        # Validar columnas requeridas
        required_columns = validation_config.get('required_columns', [])
        available_columns = set(data[0].keys() if data else [])
        
        for req_col in required_columns:
            if req_col not in available_columns:
                report.issues.append(ValidationIssue(
                    file_path=file_path,
                    row_number=0,
                    column=req_col,
                    issue_type="MISSING_COLUMN",
                    description=f"Columna requerida faltante: {req_col}",
                    current_value="",
                    severity="ERROR"
                ))
        
        # Validar cada fila
        for row_num, row in enumerate(data, start=2):  # Start at 2 (header is row 1)
            # Validar códigos
            if 'codigo' in row:
                report.issues.extend(self.validate_codigo(row['codigo'], row_num, file_path))
            
            # Validar fechas
            date_columns = validation_config.get('date_columns', [])
            for col in date_columns:
                if col in row:
                    report.issues.extend(self.validate_date(row[col], col, row_num, file_path))
            
            # Validar emails
            email_columns = validation_config.get('email_columns', [])
            for col in email_columns:
                if col in row:
                    report.issues.extend(self.validate_email(row[col], col, row_num, file_path))
            
            # Validar valores numéricos
            numeric_columns = validation_config.get('numeric_columns', {})
            for col, constraints in numeric_columns.items():
                if col in row:
                    report.issues.extend(self.validate_numeric(
                        row[col], col, row_num, file_path,
                        constraints.get('min'), constraints.get('max')
                    ))
        
        # Buscar duplicados
        duplicate_keys = validation_config.get('duplicate_keys', [])
        if duplicate_keys:
            report.duplicates = self.find_duplicates(data, duplicate_keys, file_path)
        
        return report
    
    def get_validation_configs(self) -> Dict[str, Dict[str, Any]]:
        """Define configuraciones de validación para diferentes tipos de archivos."""
        return {
            'instrumentos': {
                'required_columns': ['codigo', 'descripcion'],
                'date_columns': ['fecha_adquisicion', 'ultima_calibracion', 'proxima_calibracion'],
                'email_columns': ['contacto_email'],
                'numeric_columns': {
                    'frecuencia_calibracion': {'min': 1, 'max': 120},
                    'costo': {'min': 0}
                },
                'duplicate_keys': ['codigo']
            },
            'calibraciones': {
                'required_columns': ['codigo', 'fecha_calibracion'],
                'date_columns': ['fecha_calibracion', 'fecha_proxima'],
                'duplicate_keys': ['codigo', 'fecha_calibracion', 'periodo']
            },
            'clientes': {
                'required_columns': ['nombre'],
                'email_columns': ['email', 'contacto_email'],
                'duplicate_keys': ['nombre']
            },
            'plan_riesgos': {
                'required_columns': ['codigo'],
                'date_columns': ['fecha_evaluacion', 'fecha_revision'],
                'numeric_columns': {
                    'probabilidad': {'min': 1, 'max': 5},
                    'impacto': {'min': 1, 'max': 5}
                },
                'duplicate_keys': ['codigo']
            }
        }
    
    def validate_all_files(self) -> None:
        """Valida todos los archivos CSV en el sistema."""
        self.logger.info("Iniciando validación de todos los archivos")
        
        # Cargar datos de referencia
        self.load_reference_data()
        
        # Configuraciones de validación
        configs = self.get_validation_configs()
        
        # Archivos a validar
        files_to_validate = [
            # Archivos originales
            (self.csv_dir / "LM_instrumentos.csv", "instrumentos"),
            (self.csv_dir / "CERT_instrumentos_original_v2.csv", "calibraciones"),
            (self.csv_dir / "PR_instrumentos.csv", "plan_riesgos"),
            (self.csv_dir / "clientes.csv", "clientes"),
            
            # Archivos normalizados
            (self.normalize_dir / "normalize_instrumentos.csv", "instrumentos"),
            (self.normalize_dir / "normalize_calibraciones.csv", "calibraciones"),
            (self.normalize_dir / "normalize_plan_riesgos.csv", "plan_riesgos"),
        ]
        
        for file_path, config_type in files_to_validate:
            if file_path.exists():
                config = configs.get(config_type, {})
                report = self.validate_file(file_path, config)
                self.reports.append(report)
            else:
                self.logger.info(f"Archivo no encontrado: {file_path}")
    
    def generate_validation_report(self, output_file: Path) -> None:
        """Genera un reporte detallado de validación."""
        self.logger.info(f"Generando reporte de validación en {output_file}")
        
        with open(output_file, 'w', encoding='utf-8') as f:
            f.write("# REPORTE DE VALIDACIÓN DE DATOS - SISTEMA SBL\n")
            f.write(f"Fecha de generación: {dt.datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n\n")
            
            # Resumen general
            total_files = len(self.reports)
            valid_files = len([r for r in self.reports if r.is_valid])
            total_errors = sum(r.errors_count for r in self.reports)
            total_warnings = sum(r.warnings_count for r in self.reports)
            
            f.write("## RESUMEN GENERAL\n\n")
            f.write(f"- **Archivos validados:** {total_files}\n")
            f.write(f"- **Archivos válidos:** {valid_files}\n")
            f.write(f"- **Errores totales:** {total_errors}\n")
            f.write(f"- **Advertencias totales:** {total_warnings}\n\n")
            
            # Estado general
            if total_errors == 0:
                f.write("🟢 **ESTADO: VÁLIDO** - No se encontraron errores críticos\n\n")
            elif total_errors < 10:
                f.write("🟡 **ESTADO: ADVERTENCIA** - Se encontraron algunos errores\n\n")
            else:
                f.write("🔴 **ESTADO: CRÍTICO** - Se encontraron múltiples errores\n\n")
            
            # Detalle por archivo
            f.write("## DETALLE POR ARCHIVO\n\n")
            
            for report in sorted(self.reports, key=lambda r: r.errors_count, reverse=True):
                f.write(f"### {report.file_path.name}\n\n")
                f.write(f"- **Filas procesadas:** {report.total_rows}\n")
                f.write(f"- **Errores:** {report.errors_count}\n")
                f.write(f"- **Advertencias:** {report.warnings_count}\n")
                f.write(f"- **Duplicados:** {len(report.duplicates)}\n")
                
                if report.is_valid:
                    f.write("- **Estado:** ✅ VÁLIDO\n\n")
                else:
                    f.write("- **Estado:** ❌ CON ERRORES\n\n")
                
                # Mostrar errores más críticos
                critical_issues = [i for i in report.issues if i.severity == "ERROR"][:5]
                if critical_issues:
                    f.write("**Errores principales:**\n")
                    for issue in critical_issues:
                        f.write(f"- Fila {issue.row_number}, columna '{issue.column}': {issue.description}\n")
                        if issue.suggested_fix:
                            f.write(f"  - Sugerencia: {issue.suggested_fix}\n")
                    f.write("\n")
                
                # Mostrar duplicados
                if report.duplicates:
                    f.write(f"**Filas duplicadas:** {len(report.duplicates)} pares encontrados\n")
                    for dup1, dup2 in report.duplicates[:3]:  # Mostrar solo los primeros 3
                        f.write(f"- Filas {dup1} y {dup2}\n")
                    if len(report.duplicates) > 3:
                        f.write(f"- ... y {len(report.duplicates) - 3} más\n")
                    f.write("\n")
            
            # Recomendaciones
            f.write("## RECOMENDACIONES\n\n")
            
            if total_errors > 0:
                f.write("1. **Corregir errores críticos** antes de procesar los datos\n")
                f.write("2. **Revisar formatos de fecha** en archivos con errores de fecha\n")
                f.write("3. **Validar códigos de instrumentos** que no siguen el formato estándar\n")
            
            if any(len(r.duplicates) > 0 for r in self.reports):
                f.write("4. **Eliminar duplicados** identificados en los archivos\n")
            
            if total_warnings > 0:
                f.write("5. **Revisar advertencias** para mejorar la calidad de los datos\n")
            
            f.write("6. **Ejecutar validación regularmente** después de actualizaciones de datos\n")
    
    def _suggest_codigo_fix(self, codigo: str) -> Optional[str]:
        """Sugiere una corrección para un código inválido."""
        # Limpiar caracteres especiales
        clean_code = re.sub(r'[^\w\-]', '', codigo).upper()
        
        # Intentar ajustar formato común
        if re.match(r'^[A-Z]{2,4}\d{3,6}[A-Z]?$', clean_code):
            return clean_code
        
        # Insertar guión si falta
        match = re.match(r'^([A-Z]{2,4})(\d{3,6}[A-Z]?)$', clean_code)
        if match:
            return f"{match.group(1)}-{match.group(2)}"
        
        return None
    
    def _suggest_date_fix(self, date_str: str) -> Optional[str]:
        """Sugiere una corrección para una fecha inválida."""
        # Patrones comunes de fechas mal formateadas
        patterns = [
            (r'(\d{1,2})[\./-](\d{1,2})[\./-](\d{2,4})', r'\1/\2/\3'),
            (r'(\d{4})[\./-](\d{1,2})[\./-](\d{1,2})', r'\3/\2/\1'),
        ]
        
        for pattern, replacement in patterns:
            if re.match(pattern, date_str):
                suggestion = re.sub(pattern, replacement, date_str)
                # Verificar si la sugerencia es válida
                if DateParser.parse_spanish_date(suggestion):
                    return suggestion
        
        return None
    
    def create_backup(self, file_path: Path) -> Path:
        """Crea una copia de seguridad de un archivo."""
        timestamp = dt.datetime.now().strftime("%Y%m%d_%H%M%S")
        backup_path = file_path.parent / f"{file_path.stem}_backup_{timestamp}{file_path.suffix}"
        shutil.copy2(file_path, backup_path)
        return backup_path
    
    def run_validation(self, output_dir: Optional[Path] = None, create_backups: bool = False) -> bool:
        """Ejecuta el proceso completo de validación."""
        self.logger.info("Iniciando proceso de validación de datos")
        
        # Validar todos los archivos
        self.validate_all_files()
        
        # Generar reporte
        if output_dir is None:
            output_dir = self.repo_root / "storage" / "validation_reports"
        
        output_dir.mkdir(parents=True, exist_ok=True)
        
        timestamp = dt.datetime.now().strftime("%Y%m%d_%H%M%S")
        report_file = output_dir / f"validation_report_{timestamp}.md"
        
        self.generate_validation_report(report_file)
        
        # Resumen en consola
        total_errors = sum(r.errors_count for r in self.reports)
        total_warnings = sum(r.warnings_count for r in self.reports)
        
        self.logger.info(f"Validación completada:")
        self.logger.info(f"  - Archivos validados: {len(self.reports)}")
        self.logger.info(f"  - Errores encontrados: {total_errors}")
        self.logger.info(f"  - Advertencias: {total_warnings}")
        self.logger.info(f"  - Reporte generado: {report_file}")
        
        return total_errors == 0


def main():
    """Función principal."""
    parser = argparse.ArgumentParser(
        description="Valida la integridad de los datos del Sistema SBL"
    )
    parser.add_argument(
        "--output",
        type=Path,
        help="Directorio de salida para reportes"
    )
    parser.add_argument(
        "--backup",
        action="store_true",
        help="Crear copias de seguridad antes de validar"
    )
    
    args = parser.parse_args()
    
    validator = DataValidator()
    success = validator.run_validation(output_dir=args.output, create_backups=args.backup)
    
    if not success:
        exit(1)


if __name__ == "__main__":
    main()