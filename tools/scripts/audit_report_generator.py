#!/usr/bin/env python3
"""Generador automático de reportes de auditoría para el sistema SBL.

Este script genera reportes detallados de auditoría basados en los datos
históricos de calibraciones, instrumentos y actividades del sistema.

Características:
- Reportes de cumplimiento de calibraciones
- Análisis de tendencias temporales
- Detección de instrumentos críticos
- Exportación a múltiples formatos (CSV, Excel)
- Análisis estadístico básico

Uso:
```bash
python tools/scripts/audit_report_generator.py --empresa-id 1 --output storage/audit_reports/
```
"""

from __future__ import annotations

import argparse
import csv
import datetime as dt
from dataclasses import dataclass
from pathlib import Path
from typing import Dict, List, Optional, Tuple, Any
import json

try:
    import pandas as pd
    HAS_PANDAS = True
except ImportError:
    HAS_PANDAS = False
    pd = None

from sbl_utils import (
    setup_logging, get_repo_root, get_csv_originales_dir, 
    get_normalize_dir, CSVHandler, DateParser, TextNormalizer
)

@dataclass
class InstrumentStatus:
    """Estado de un instrumento en el sistema."""
    codigo: str
    descripcion: str
    ubicacion: str
    ultima_calibracion: Optional[dt.date]
    proxima_calibracion: Optional[dt.date]
    estado_cumplimiento: str
    dias_vencimiento: Optional[int]
    frecuencia_meses: Optional[int]
    criticidad: str

@dataclass
class AuditMetrics:
    """Métricas de auditoría del sistema."""
    total_instrumentos: int
    instrumentos_al_dia: int
    instrumentos_vencidos: int
    instrumentos_proximos_vencer: int
    porcentaje_cumplimiento: float
    promedio_dias_vencimiento: float
    instrumentos_criticos: int

class AuditReportGenerator:
    """Generador de reportes de auditoría."""
    
    def __init__(self, empresa_id: int = 1):
        self.empresa_id = empresa_id
        self.repo_root = get_repo_root(__file__)
        self.logger = setup_logging("audit_report_generator")
        
        # Directorios
        self.csv_dir = get_csv_originales_dir(self.repo_root)
        self.normalize_dir = get_normalize_dir(self.repo_root)
        self.output_dir = self.repo_root / "storage" / "audit_reports"
        self.output_dir.mkdir(parents=True, exist_ok=True)
        
        # Datos cargados
        self.instrumentos: List[Dict[str, Any]] = []
        self.calibraciones: List[Dict[str, Any]] = []
        self.instrument_status: List[InstrumentStatus] = []
    
    def load_data(self) -> None:
        """Carga los datos necesarios desde los archivos CSV."""
        self.logger.info("Cargando datos para el reporte de auditoría...")
        
        # Cargar instrumentos normalizados
        instrumentos_file = self.normalize_dir / "normalize_instrumentos.csv"
        if instrumentos_file.exists():
            self.instrumentos = CSVHandler.read_csv(instrumentos_file)
            self.logger.info(f"Cargados {len(self.instrumentos)} instrumentos")
        else:
            self.logger.warning(f"No se encontró {instrumentos_file}")
        
        # Cargar calibraciones (buscar en varios posibles archivos)
        calibraciones_files = [
            self.normalize_dir / "normalize_calibraciones.csv",
            self.csv_dir / "CERT_instrumentos_original_v2.csv",
            self.repo_root / "storage" / "calibraciones_export.csv"
        ]
        
        for cal_file in calibraciones_files:
            if cal_file.exists():
                try:
                    cal_data = CSVHandler.read_csv(cal_file)
                    self.calibraciones.extend(cal_data)
                    self.logger.info(f"Cargadas {len(cal_data)} calibraciones desde {cal_file.name}")
                except Exception as e:
                    self.logger.warning(f"Error cargando {cal_file}: {e}")
    
    def analyze_instrument_status(self) -> None:
        """Analiza el estado de cumplimiento de cada instrumento."""
        self.logger.info("Analizando estado de instrumentos...")
        
        today = dt.date.today()
        
        for instrumento in self.instrumentos:
            codigo = instrumento.get('codigo', '')
            if not codigo:
                continue
            
            # Buscar calibraciones para este instrumento
            calibraciones_instrumento = [
                cal for cal in self.calibraciones 
                if cal.get('codigo', '').upper() == codigo.upper()
            ]
            
            # Encontrar última calibración
            ultima_calibracion = None
            proxima_calibracion = None
            frecuencia_meses = None
            
            if calibraciones_instrumento:
                fechas_calibracion = []
                for cal in calibraciones_instrumento:
                    fecha_str = cal.get('fecha_calibracion') or cal.get('fecha')
                    if fecha_str:
                        fecha = DateParser.parse_spanish_date(fecha_str)
                        if fecha:
                            fechas_calibracion.append(fecha)
                
                if fechas_calibracion:
                    ultima_calibracion = max(fechas_calibracion)
                    
                    # Intentar determinar frecuencia
                    freq_str = instrumento.get('frecuencia_calibracion') or '12'
                    try:
                        frecuencia_meses = int(freq_str) if freq_str.isdigit() else 12
                    except (ValueError, AttributeError):
                        frecuencia_meses = 12
                    
                    # Calcular próxima calibración
                    if frecuencia_meses:
                        proxima_calibracion = DateParser.add_months(
                            ultima_calibracion, frecuencia_meses
                        )
            
            # Determinar estado de cumplimiento
            estado_cumplimiento = "SIN_DATOS"
            dias_vencimiento = None
            
            if proxima_calibracion:
                dias_vencimiento = (proxima_calibracion - today).days
                
                if dias_vencimiento > 30:
                    estado_cumplimiento = "AL_DIA"
                elif dias_vencimiento > 0:
                    estado_cumplimiento = "PROXIMO_VENCER"
                else:
                    estado_cumplimiento = "VENCIDO"
            
            # Determinar criticidad
            descripcion = instrumento.get('descripcion', '').lower()
            ubicacion = instrumento.get('ubicacion', '').lower()
            
            criticidad = "NORMAL"
            if any(term in descripcion for term in ['critico', 'vital', 'principal']):
                criticidad = "CRITICA"
            elif any(term in ubicacion for term in ['produccion', 'proceso', 'linea']):
                criticidad = "ALTA"
            
            status = InstrumentStatus(
                codigo=codigo,
                descripcion=instrumento.get('descripcion', ''),
                ubicacion=instrumento.get('ubicacion', ''),
                ultima_calibracion=ultima_calibracion,
                proxima_calibracion=proxima_calibracion,
                estado_cumplimiento=estado_cumplimiento,
                dias_vencimiento=dias_vencimiento,
                frecuencia_meses=frecuencia_meses,
                criticidad=criticidad
            )
            
            self.instrument_status.append(status)
        
        self.logger.info(f"Analizados {len(self.instrument_status)} instrumentos")
    
    def calculate_metrics(self) -> AuditMetrics:
        """Calcula métricas generales de auditoría."""
        total = len(self.instrument_status)
        
        if total == 0:
            return AuditMetrics(0, 0, 0, 0, 0.0, 0.0, 0)
        
        al_dia = len([s for s in self.instrument_status if s.estado_cumplimiento == "AL_DIA"])
        vencidos = len([s for s in self.instrument_status if s.estado_cumplimiento == "VENCIDO"])
        proximos = len([s for s in self.instrument_status if s.estado_cumplimiento == "PROXIMO_VENCER"])
        criticos = len([s for s in self.instrument_status if s.criticidad == "CRITICA"])
        
        porcentaje_cumplimiento = (al_dia / total) * 100
        
        # Calcular promedio de días de vencimiento (solo para instrumentos con datos)
        dias_vencimiento = [s.dias_vencimiento for s in self.instrument_status if s.dias_vencimiento is not None]
        promedio_dias = sum(dias_vencimiento) / len(dias_vencimiento) if dias_vencimiento else 0.0
        
        return AuditMetrics(
            total_instrumentos=total,
            instrumentos_al_dia=al_dia,
            instrumentos_vencidos=vencidos,
            instrumentos_proximos_vencer=proximos,
            porcentaje_cumplimiento=porcentaje_cumplimiento,
            promedio_dias_vencimiento=promedio_dias,
            instrumentos_criticos=criticos
        )
    
    def generate_summary_report(self, metrics: AuditMetrics, output_file: Path) -> None:
        """Genera reporte resumen de auditoría."""
        self.logger.info(f"Generando reporte resumen en {output_file}")
        
        with open(output_file, 'w', encoding='utf-8') as f:
            f.write("# REPORTE DE AUDITORÍA - SISTEMA SBL\n")
            f.write(f"Fecha de generación: {dt.datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n")
            f.write(f"Empresa ID: {self.empresa_id}\n\n")
            
            f.write("## RESUMEN EJECUTIVO\n\n")
            f.write(f"- **Total de instrumentos:** {metrics.total_instrumentos}\n")
            f.write(f"- **Instrumentos al día:** {metrics.instrumentos_al_dia}\n")
            f.write(f"- **Instrumentos vencidos:** {metrics.instrumentos_vencidos}\n")
            f.write(f"- **Instrumentos próximos a vencer:** {metrics.instrumentos_proximos_vencer}\n")
            f.write(f"- **Porcentaje de cumplimiento:** {metrics.porcentaje_cumplimiento:.1f}%\n")
            f.write(f"- **Promedio días hasta vencimiento:** {metrics.promedio_dias_vencimiento:.0f}\n")
            f.write(f"- **Instrumentos críticos:** {metrics.instrumentos_criticos}\n\n")
            
            # Análisis por estado
            f.write("## ANÁLISIS POR ESTADO\n\n")
            estados = {}
            for status in self.instrument_status:
                estado = status.estado_cumplimiento
                estados[estado] = estados.get(estado, 0) + 1
            
            for estado, count in estados.items():
                porcentaje = (count / metrics.total_instrumentos) * 100
                f.write(f"- **{estado}:** {count} instrumentos ({porcentaje:.1f}%)\n")
            
            # Instrumentos críticos vencidos
            criticos_vencidos = [
                s for s in self.instrument_status 
                if s.criticidad == "CRITICA" and s.estado_cumplimiento == "VENCIDO"
            ]
            
            if criticos_vencidos:
                f.write("\n## ⚠️ INSTRUMENTOS CRÍTICOS VENCIDOS\n\n")
                for status in criticos_vencidos:
                    f.write(f"- **{status.codigo}**: {status.descripcion}\n")
                    f.write(f"  - Ubicación: {status.ubicacion}\n")
                    if status.ultima_calibracion:
                        f.write(f"  - Última calibración: {status.ultima_calibracion}\n")
                    if status.dias_vencimiento:
                        f.write(f"  - Días vencido: {abs(status.dias_vencimiento)}\n")
                    f.write("\n")
            
            # Recomendaciones
            f.write("## RECOMENDACIONES\n\n")
            
            if metrics.porcentaje_cumplimiento < 80:
                f.write("🔴 **CRÍTICO**: El porcentaje de cumplimiento está por debajo del 80%.\n")
                f.write("   Revisar inmediatamente los instrumentos vencidos.\n\n")
            elif metrics.porcentaje_cumplimiento < 90:
                f.write("🟡 **ADVERTENCIA**: El porcentaje de cumplimiento está por debajo del 90%.\n")
                f.write("   Planificar calibraciones para mejorar el cumplimiento.\n\n")
            else:
                f.write("🟢 **BUENO**: El porcentaje de cumplimiento es satisfactorio.\n\n")
            
            if metrics.instrumentos_vencidos > 0:
                f.write(f"- Priorizar la calibración de {metrics.instrumentos_vencidos} instrumentos vencidos\n")
            
            if metrics.instrumentos_proximos_vencer > 0:
                f.write(f"- Planificar la calibración de {metrics.instrumentos_proximos_vencer} instrumentos próximos a vencer\n")
            
            if criticos_vencidos:
                f.write(f"- **URGENTE**: Calibrar inmediatamente {len(criticos_vencidos)} instrumentos críticos vencidos\n")
    
    def generate_detailed_csv(self, output_file: Path) -> None:
        """Genera reporte detallado en formato CSV."""
        self.logger.info(f"Generando reporte detallado CSV en {output_file}")
        
        with open(output_file, 'w', newline='', encoding='utf-8') as f:
            fieldnames = [
                'codigo', 'descripcion', 'ubicacion', 'criticidad',
                'ultima_calibracion', 'proxima_calibracion', 'estado_cumplimiento',
                'dias_vencimiento', 'frecuencia_meses'
            ]
            
            writer = csv.DictWriter(f, fieldnames=fieldnames)
            writer.writeheader()
            
            for status in sorted(self.instrument_status, key=lambda x: x.codigo):
                writer.writerow({
                    'codigo': status.codigo,
                    'descripcion': status.descripcion,
                    'ubicacion': status.ubicacion,
                    'criticidad': status.criticidad,
                    'ultima_calibracion': status.ultima_calibracion.isoformat() if status.ultima_calibracion else '',
                    'proxima_calibracion': status.proxima_calibracion.isoformat() if status.proxima_calibracion else '',
                    'estado_cumplimiento': status.estado_cumplimiento,
                    'dias_vencimiento': status.dias_vencimiento or '',
                    'frecuencia_meses': status.frecuencia_meses or ''
                })
    
    def generate_excel_report(self, output_file: Path) -> None:
        """Genera reporte en formato Excel (si pandas está disponible)."""
        if not HAS_PANDAS:
            self.logger.warning("pandas no disponible, omitiendo reporte Excel")
            return
        
        self.logger.info(f"Generando reporte Excel en {output_file}")
        
        # Convertir datos a DataFrame
        data = []
        for status in self.instrument_status:
            data.append({
                'Código': status.codigo,
                'Descripción': status.descripcion,
                'Ubicación': status.ubicacion,
                'Criticidad': status.criticidad,
                'Última Calibración': status.ultima_calibracion,
                'Próxima Calibración': status.proxima_calibracion,
                'Estado': status.estado_cumplimiento,
                'Días hasta Vencimiento': status.dias_vencimiento,
                'Frecuencia (meses)': status.frecuencia_meses
            })
        
        df = pd.DataFrame(data)
        
        # Crear archivo Excel con múltiples hojas
        with pd.ExcelWriter(output_file, engine='openpyxl') as writer:
            # Hoja principal con todos los datos
            df.to_excel(writer, sheet_name='Todos los Instrumentos', index=False)
            
            # Hoja de instrumentos vencidos
            vencidos_df = df[df['Estado'] == 'VENCIDO']
            if not vencidos_df.empty:
                vencidos_df.to_excel(writer, sheet_name='Vencidos', index=False)
            
            # Hoja de instrumentos críticos
            criticos_df = df[df['Criticidad'] == 'CRITICA']
            if not criticos_df.empty:
                criticos_df.to_excel(writer, sheet_name='Críticos', index=False)
            
            # Hoja de resumen por estado
            resumen_estado = df['Estado'].value_counts().reset_index()
            resumen_estado.columns = ['Estado', 'Cantidad']
            resumen_estado.to_excel(writer, sheet_name='Resumen por Estado', index=False)
    
    def generate_json_report(self, metrics: AuditMetrics, output_file: Path) -> None:
        """Genera reporte en formato JSON para integración con APIs."""
        self.logger.info(f"Generando reporte JSON en {output_file}")
        
        # Convertir datos a formato JSON serializable
        instruments_data = []
        for status in self.instrument_status:
            instruments_data.append({
                'codigo': status.codigo,
                'descripcion': status.descripcion,
                'ubicacion': status.ubicacion,
                'criticidad': status.criticidad,
                'ultima_calibracion': status.ultima_calibracion.isoformat() if status.ultima_calibracion else None,
                'proxima_calibracion': status.proxima_calibracion.isoformat() if status.proxima_calibracion else None,
                'estado_cumplimiento': status.estado_cumplimiento,
                'dias_vencimiento': status.dias_vencimiento,
                'frecuencia_meses': status.frecuencia_meses
            })
        
        report_data = {
            'metadata': {
                'fecha_generacion': dt.datetime.now().isoformat(),
                'empresa_id': self.empresa_id,
                'version_sistema': '1.0.0'
            },
            'metricas': {
                'total_instrumentos': metrics.total_instrumentos,
                'instrumentos_al_dia': metrics.instrumentos_al_dia,
                'instrumentos_vencidos': metrics.instrumentos_vencidos,
                'instrumentos_proximos_vencer': metrics.instrumentos_proximos_vencer,
                'porcentaje_cumplimiento': round(metrics.porcentaje_cumplimiento, 2),
                'promedio_dias_vencimiento': round(metrics.promedio_dias_vencimiento, 1),
                'instrumentos_criticos': metrics.instrumentos_criticos
            },
            'instrumentos': instruments_data
        }
        
        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(report_data, f, ensure_ascii=False, indent=2)
    
    def generate_reports(self, output_dir: Optional[Path] = None) -> None:
        """Genera todos los reportes de auditoría."""
        if output_dir is None:
            output_dir = self.output_dir
        
        output_dir.mkdir(parents=True, exist_ok=True)
        
        # Generar timestamp para los archivos
        timestamp = dt.datetime.now().strftime("%Y%m%d_%H%M%S")
        
        # Calcular métricas
        metrics = self.calculate_metrics()
        
        # Generar diferentes tipos de reportes
        self.generate_summary_report(
            metrics, 
            output_dir / f"audit_summary_{timestamp}.md"
        )
        
        self.generate_detailed_csv(
            output_dir / f"audit_detailed_{timestamp}.csv"
        )
        
        self.generate_json_report(
            metrics,
            output_dir / f"audit_data_{timestamp}.json"
        )
        
        # Excel solo si pandas está disponible
        if HAS_PANDAS:
            try:
                self.generate_excel_report(
                    output_dir / f"audit_report_{timestamp}.xlsx"
                )
            except Exception as e:
                self.logger.warning(f"Error generando Excel: {e}")
        
        self.logger.info(f"Reportes generados en {output_dir}")
        self.logger.info(f"Métricas principales:")
        self.logger.info(f"  - Total instrumentos: {metrics.total_instrumentos}")
        self.logger.info(f"  - Cumplimiento: {metrics.porcentaje_cumplimiento:.1f}%")
        self.logger.info(f"  - Instrumentos vencidos: {metrics.instrumentos_vencidos}")
    
    def run(self, output_dir: Optional[Path] = None) -> None:
        """Ejecuta el proceso completo de generación de reportes."""
        self.logger.info("Iniciando generación de reportes de auditoría")
        
        self.load_data()
        self.analyze_instrument_status()
        self.generate_reports(output_dir)
        
        self.logger.info("Proceso de auditoría completado")


def main():
    """Función principal."""
    parser = argparse.ArgumentParser(
        description="Genera reportes de auditoría para el sistema SBL"
    )
    parser.add_argument(
        "--empresa-id",
        type=int,
        default=1,
        help="ID de la empresa para el reporte"
    )
    parser.add_argument(
        "--output",
        type=Path,
        help="Directorio de salida para los reportes"
    )
    
    args = parser.parse_args()
    
    generator = AuditReportGenerator(empresa_id=args.empresa_id)
    generator.run(output_dir=args.output)


if __name__ == "__main__":
    main()