#!/usr/bin/env python3
"""Script maestro para ejecutar todos los procesos del Sistema SBL.

Este script orquesta la ejecución de todos los componentes del sistema:
- Validación de datos
- Normalización de archivos CSV
- Generación de SQL de inserción
- Generación de reportes de auditoría
- Limpieza y mantenimiento

Características:
- Ejecución secuencial con manejo de errores
- Logging detallado de todo el proceso
- Opciones para ejecutar solo partes específicas
- Generación de reportes de resumen
- Verificación de prerrequisitos

Uso:
```bash
python tools/scripts/run_all_processes.py --full --backup
```
"""

from __future__ import annotations

import argparse
import datetime as dt
import subprocess
import sys
from pathlib import Path
from typing import List, Optional, Dict, Any
import json

from sbl_utils import setup_logging, get_repo_root

class SBLSystemOrchestrator:
    """Orquestador principal del sistema SBL."""
    
    def __init__(self, empresa_id: int = 1, backup: bool = False):
        self.empresa_id = empresa_id
        self.backup = backup
        self.repo_root = get_repo_root(__file__)
        self.logger = setup_logging("sbl_orchestrator")
        
        # Directorios
        self.tools_dir = self.repo_root / "tools" / "scripts"
        self.output_dir = self.repo_root / "storage" / "process_runs"
        self.output_dir.mkdir(parents=True, exist_ok=True)
        
        # Estado del proceso
        self.process_results: Dict[str, Any] = {}
        self.start_time = dt.datetime.now()
        
        # Scripts disponibles
        self.available_scripts = {
            'setup_environment': 'setup_python_environment.py',
            'validate_data': 'data_validator.py',
            'generate_cert_calibrations': 'generate_cert_calibrations.py',
            'generate_insert_instrumentos': 'generate_insert_instrumentos.py',
            'generate_plan_riesgos': 'generate_plan_riesgos.py',
            'audit_report': 'audit_report_generator.py',
        }
    
    def check_prerequisites(self) -> bool:
        """Verifica que todos los prerrequisitos estén instalados."""
        self.logger.info("Verificando prerrequisitos del sistema...")
        
        # Verificar Python
        if sys.version_info < (3, 8):
            self.logger.error("Python 3.8+ requerido")
            return False
        
        # Verificar que los scripts existan
        missing_scripts = []
        for script_name, script_file in self.available_scripts.items():
            script_path = self.tools_dir / script_file
            if not script_path.exists():
                missing_scripts.append(script_file)
        
        if missing_scripts:
            self.logger.error(f"Scripts faltantes: {missing_scripts}")
            return False
        
        # Verificar directorios necesarios
        required_dirs = [
            self.repo_root / "app" / "Modules" / "Internal" / "ArchivosSql",
            self.repo_root / "storage",
        ]
        
        for dir_path in required_dirs:
            if not dir_path.exists():
                self.logger.warning(f"Directorio faltante: {dir_path}")
                dir_path.mkdir(parents=True, exist_ok=True)
                self.logger.info(f"Directorio creado: {dir_path}")
        
        self.logger.info("✅ Prerrequisitos verificados")
        return True
    
    def run_script(self, script_key: str, args: List[str] = None, required: bool = True) -> bool:
        """Ejecuta un script específico."""
        if script_key not in self.available_scripts:
            self.logger.error(f"Script desconocido: {script_key}")
            return False
        
        script_file = self.available_scripts[script_key]
        script_path = self.tools_dir / script_file
        
        if not script_path.exists():
            message = f"Script no encontrado: {script_path}"
            if required:
                self.logger.error(message)
                return False
            else:
                self.logger.warning(message)
                return True
        
        self.logger.info(f"Ejecutando: {script_file}")
        
        # Construir comando
        cmd = [sys.executable, str(script_path)]
        if args:
            cmd.extend(args)
        
        # Añadir argumentos comunes
        if script_key != 'setup_environment':
            if '--empresa-id' not in (args or []):
                cmd.extend(['--empresa-id', str(self.empresa_id)])
        
        try:
            # Ejecutar script
            start_time = dt.datetime.now()
            result = subprocess.run(
                cmd, 
                capture_output=True, 
                text=True, 
                check=True,
                cwd=self.repo_root
            )
            
            end_time = dt.datetime.now()
            duration = (end_time - start_time).total_seconds()
            
            # Registrar resultado
            self.process_results[script_key] = {
                'status': 'success',
                'duration': duration,
                'stdout': result.stdout,
                'stderr': result.stderr,
                'timestamp': end_time.isoformat()
            }
            
            self.logger.info(f"✅ {script_file} completado en {duration:.1f}s")
            
            # Mostrar salida si es informativa
            if result.stdout.strip():
                for line in result.stdout.strip().split('\n')[:5]:  # Primeras 5 líneas
                    self.logger.info(f"  {line}")
            
            return True
            
        except subprocess.CalledProcessError as e:
            end_time = dt.datetime.now()
            duration = (end_time - start_time).total_seconds()
            
            # Registrar error
            self.process_results[script_key] = {
                'status': 'error',
                'duration': duration,
                'stdout': e.stdout,
                'stderr': e.stderr,
                'return_code': e.returncode,
                'timestamp': end_time.isoformat()
            }
            
            message = f"❌ Error en {script_file} (código: {e.returncode})"
            if required:
                self.logger.error(message)
                if e.stderr:
                    self.logger.error(f"Error: {e.stderr}")
                return False
            else:
                self.logger.warning(message)
                return True
                
        except Exception as e:
            # Error inesperado
            self.process_results[script_key] = {
                'status': 'exception',
                'duration': 0,
                'error': str(e),
                'timestamp': dt.datetime.now().isoformat()
            }
            
            message = f"❌ Excepción en {script_file}: {e}"
            if required:
                self.logger.error(message)
                return False
            else:
                self.logger.warning(message)
                return True
    
    def run_setup_process(self) -> bool:
        """Ejecuta el proceso de configuración inicial."""
        self.logger.info("🚀 Iniciando configuración del entorno...")
        
        # Instalar dependencias
        if not self.run_script('setup_environment', ['--dev'] if self.backup else [], required=False):
            self.logger.warning("Setup del entorno falló, continuando...")
        
        return True
    
    def run_validation_process(self) -> bool:
        """Ejecuta el proceso de validación de datos."""
        self.logger.info("🔍 Iniciando validación de datos...")
        
        args = []
        if self.backup:
            args.append('--backup')
        
        return self.run_script('validate_data', args, required=True)
    
    def run_generation_process(self) -> bool:
        """Ejecuta los procesos de generación de archivos."""
        self.logger.info("⚙️ Iniciando generación de archivos...")
        
        success = True
        
        # Generar calibraciones de certificados
        if not self.run_script('generate_cert_calibrations', [], required=False):
            success = False
        
        # Generar inserts de instrumentos
        if not self.run_script('generate_insert_instrumentos', [], required=False):
            success = False
        
        # Generar plan de riesgos
        if not self.run_script('generate_plan_riesgos', [], required=False):
            success = False
        
        return success
    
    def run_reporting_process(self) -> bool:
        """Ejecuta el proceso de generación de reportes."""
        self.logger.info("📊 Iniciando generación de reportes...")
        
        return self.run_script('audit_report', [], required=False)
    
    def generate_summary_report(self) -> Path:
        """Genera un reporte resumen de toda la ejecución."""
        timestamp = self.start_time.strftime("%Y%m%d_%H%M%S")
        report_file = self.output_dir / f"process_summary_{timestamp}.md"
        
        self.logger.info(f"Generando reporte resumen: {report_file}")
        
        total_duration = (dt.datetime.now() - self.start_time).total_seconds()
        successful_processes = len([r for r in self.process_results.values() if r['status'] == 'success'])
        failed_processes = len([r for r in self.process_results.values() if r['status'] in ['error', 'exception']])
        
        with open(report_file, 'w', encoding='utf-8') as f:
            f.write("# REPORTE DE EJECUCIÓN COMPLETA - SISTEMA SBL\n\n")
            f.write(f"**Fecha de ejecución:** {self.start_time.strftime('%Y-%m-%d %H:%M:%S')}\n")
            f.write(f"**Duración total:** {total_duration:.1f} segundos\n")
            f.write(f"**Empresa ID:** {self.empresa_id}\n")
            f.write(f"**Backup habilitado:** {'Sí' if self.backup else 'No'}\n\n")
            
            # Resumen de resultados
            f.write("## RESUMEN DE RESULTADOS\n\n")
            f.write(f"- **Procesos exitosos:** {successful_processes}\n")
            f.write(f"- **Procesos fallidos:** {failed_processes}\n")
            f.write(f"- **Total de procesos:** {len(self.process_results)}\n\n")
            
            # Estado general
            if failed_processes == 0:
                f.write("🟢 **ESTADO GENERAL: EXITOSO** - Todos los procesos completados\n\n")
            elif failed_processes <= 2:
                f.write("🟡 **ESTADO GENERAL: PARCIAL** - Algunos procesos fallaron\n\n")
            else:
                f.write("🔴 **ESTADO GENERAL: FALLIDO** - Múltiples procesos fallaron\n\n")
            
            # Detalle por proceso
            f.write("## DETALLE POR PROCESO\n\n")
            
            for script_key, result in self.process_results.items():
                script_name = self.available_scripts[script_key]
                status_icon = "✅" if result['status'] == 'success' else "❌"
                
                f.write(f"### {status_icon} {script_name}\n\n")
                f.write(f"- **Estado:** {result['status'].upper()}\n")
                f.write(f"- **Duración:** {result.get('duration', 0):.1f} segundos\n")
                f.write(f"- **Timestamp:** {result['timestamp']}\n")
                
                if result['status'] == 'error':
                    f.write(f"- **Código de salida:** {result.get('return_code', 'N/A')}\n")
                    if result.get('stderr'):
                        f.write(f"- **Error:** {result['stderr'][:200]}...\n")
                
                if result['status'] == 'exception':
                    f.write(f"- **Excepción:** {result.get('error', 'Desconocida')}\n")
                
                # Mostrar salida exitosa resumida
                if result['status'] == 'success' and result.get('stdout'):
                    stdout_lines = result['stdout'].strip().split('\n')
                    if len(stdout_lines) > 0:
                        f.write("- **Salida:** ")
                        # Buscar líneas informativas
                        info_lines = [line for line in stdout_lines if any(word in line.lower() for word in ['generado', 'completado', 'procesado', 'éxito'])]
                        if info_lines:
                            f.write(f"{info_lines[-1]}\n")
                        else:
                            f.write(f"{stdout_lines[-1]}\n")
                
                f.write("\n")
            
            # Archivos generados
            f.write("## ARCHIVOS GENERADOS\n\n")
            
            possible_outputs = [
                self.repo_root / "app" / "Modules" / "Internal" / "ArchivosSql" / "Archivos_BD_SBL" / "SBL_inserts",
                self.repo_root / "storage" / "audit_reports",
                self.repo_root / "storage" / "validation_reports",
            ]
            
            for output_dir in possible_outputs:
                if output_dir.exists():
                    recent_files = []
                    for file_path in output_dir.glob("*"):
                        if file_path.is_file():
                            # Verificar si es reciente (generado hoy)
                            file_time = dt.datetime.fromtimestamp(file_path.stat().st_mtime)
                            if file_time.date() == dt.date.today():
                                recent_files.append((file_path, file_time))
                    
                    if recent_files:
                        f.write(f"### {output_dir.name}\n\n")
                        for file_path, file_time in sorted(recent_files, key=lambda x: x[1], reverse=True):
                            f.write(f"- `{file_path.name}` ({file_time.strftime('%H:%M:%S')})\n")
                        f.write("\n")
            
            # Recomendaciones
            f.write("## RECOMENDACIONES\n\n")
            
            if failed_processes > 0:
                f.write("1. **Revisar procesos fallidos** antes de usar los archivos generados\n")
                f.write("2. **Verificar logs detallados** para identificar causas de errores\n")
                f.write("3. **Ejecutar individualmente** los scripts que fallaron para diagnóstico\n")
            
            if successful_processes > 0:
                f.write("4. **Validar archivos generados** antes de importar a base de datos\n")
                f.write("5. **Crear respaldo** de datos actuales antes de aplicar cambios\n")
            
            f.write("6. **Programar ejecución regular** de estos procesos para mantener datos actualizados\n")
        
        return report_file
    
    def save_process_log(self) -> Path:
        """Guarda un log detallado en formato JSON."""
        timestamp = self.start_time.strftime("%Y%m%d_%H%M%S")
        log_file = self.output_dir / f"process_log_{timestamp}.json"
        
        log_data = {
            'execution_info': {
                'start_time': self.start_time.isoformat(),
                'end_time': dt.datetime.now().isoformat(),
                'total_duration': (dt.datetime.now() - self.start_time).total_seconds(),
                'empresa_id': self.empresa_id,
                'backup_enabled': self.backup,
                'python_version': f"{sys.version_info.major}.{sys.version_info.minor}.{sys.version_info.micro}",
                'working_directory': str(self.repo_root)
            },
            'process_results': self.process_results
        }
        
        with open(log_file, 'w', encoding='utf-8') as f:
            json.dump(log_data, f, indent=2, ensure_ascii=False)
        
        return log_file
    
    def run_full_process(self) -> bool:
        """Ejecuta todo el proceso completo del sistema."""
        self.logger.info("🎯 Iniciando proceso completo del Sistema SBL")
        
        # Verificar prerrequisitos
        if not self.check_prerequisites():
            self.logger.error("Prerrequisitos no cumplidos, abortando")
            return False
        
        success = True
        
        # 1. Configuración inicial
        if not self.run_setup_process():
            self.logger.warning("Configuración inicial falló, continuando...")
        
        # 2. Validación de datos
        if not self.run_validation_process():
            self.logger.error("Validación de datos falló")
            success = False
            # Continuar con precaución
        
        # 3. Generación de archivos
        if not self.run_generation_process():
            self.logger.error("Generación de archivos falló")
            success = False
        
        # 4. Generación de reportes
        if not self.run_reporting_process():
            self.logger.warning("Generación de reportes falló")
        
        # 5. Generar reportes finales
        summary_report = self.generate_summary_report()
        process_log = self.save_process_log()
        
        # Resumen final
        total_duration = (dt.datetime.now() - self.start_time).total_seconds()
        successful_count = len([r for r in self.process_results.values() if r['status'] == 'success'])
        total_count = len(self.process_results)
        
        self.logger.info(f"🏁 Proceso completo terminado en {total_duration:.1f} segundos")
        self.logger.info(f"📈 Resultados: {successful_count}/{total_count} procesos exitosos")
        self.logger.info(f"📄 Reporte resumen: {summary_report}")
        self.logger.info(f"📋 Log detallado: {process_log}")
        
        return success
    
    def run_partial_process(self, processes: List[str]) -> bool:
        """Ejecuta solo procesos específicos."""
        self.logger.info(f"🎯 Ejecutando procesos específicos: {processes}")
        
        if not self.check_prerequisites():
            return False
        
        success = True
        
        for process in processes:
            if process == 'setup':
                success &= self.run_setup_process()
            elif process == 'validate':
                success &= self.run_validation_process()
            elif process == 'generate':
                success &= self.run_generation_process()
            elif process == 'report':
                success &= self.run_reporting_process()
            elif process in self.available_scripts:
                success &= self.run_script(process)
            else:
                self.logger.error(f"Proceso desconocido: {process}")
                success = False
        
        # Generar reporte de resultados
        self.generate_summary_report()
        self.save_process_log()
        
        return success


def main():
    """Función principal."""
    parser = argparse.ArgumentParser(
        description="Ejecuta todos los procesos del Sistema SBL",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Ejemplos de uso:
  python run_all_processes.py --full                    # Proceso completo
  python run_all_processes.py --processes validate generate  # Solo validar y generar
  python run_all_processes.py --backup --empresa-id 2        # Con backup para empresa 2
        """
    )
    
    parser.add_argument(
        "--full",
        action="store_true",
        help="Ejecutar proceso completo (configuración, validación, generación, reportes)"
    )
    
    parser.add_argument(
        "--processes",
        nargs="+",
        help="Procesos específicos a ejecutar: setup, validate, generate, report, o nombres de scripts"
    )
    
    parser.add_argument(
        "--empresa-id",
        type=int,
        default=1,
        help="ID de la empresa (default: 1)"
    )
    
    parser.add_argument(
        "--backup",
        action="store_true",
        help="Crear copias de seguridad antes de procesar"
    )
    
    args = parser.parse_args()
    
    # Validar argumentos
    if not args.full and not args.processes:
        parser.error("Debe especificar --full o --processes")
    
    # Crear orquestador
    orchestrator = SBLSystemOrchestrator(
        empresa_id=args.empresa_id,
        backup=args.backup
    )
    
    # Ejecutar proceso
    if args.full:
        success = orchestrator.run_full_process()
    else:
        success = orchestrator.run_partial_process(args.processes)
    
    # Salir con código apropiado
    sys.exit(0 if success else 1)


if __name__ == "__main__":
    main()