#!/usr/bin/env python3
"""
Sistema de configuración automática para Developer Private Section
Automatiza la configuración del sistema y validación de componentes
"""

import os
import sys
import subprocess
import json
import time
from pathlib import Path
from datetime import datetime

class DeveloperSystemSetup:
    def __init__(self):
        self.base_path = Path(__file__).parent.parent.parent
        self.config = {
            'database': {
                'host': 'localhost',
                'port': 3306,
                'database': 'sbl_sistema_interno',
                'user': 'root',
                'password': ''
            },
            'paths': {
                'app': self.base_path / 'app',
                'public': self.base_path / 'public',
                'storage': self.base_path / 'storage',
                'tools': self.base_path / 'tools'
            },
            'modules': [
                'DeveloperAuth',
                'DeveloperDashboard', 
                'IncidentChangeManager',
                'DocumentationManager',
                'VendorManager',
                'AlertManager'
            ]
        }
        
    def log(self, message, level='INFO'):
        """Log con timestamp"""
        timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        print(f"[{timestamp}] {level}: {message}")
        
    def check_php_syntax(self, file_path):
        """Validar sintaxis PHP"""
        try:
            result = subprocess.run(
                ['php', '-l', str(file_path)], 
                capture_output=True, 
                text=True
            )
            if result.returncode == 0:
                self.log(f"✓ Sintaxis PHP válida: {file_path.name}")
                return True
            else:
                self.log(f"✗ Error de sintaxis PHP en {file_path.name}: {result.stderr}", 'ERROR')
                return False
        except Exception as e:
            self.log(f"Error al validar {file_path.name}: {str(e)}", 'ERROR')
            return False
    
    def validate_modules(self):
        """Validar módulos PHP del sistema Developer"""
        self.log("Validando módulos del sistema Developer...")
        
        module_paths = {
            'DeveloperAuth': self.config['paths']['app'] / 'Modules' / 'Internal' / 'Developer' / 'DeveloperAuth.php',
            'DeveloperDashboard': self.config['paths']['app'] / 'Modules' / 'Internal' / 'Developer' / 'DeveloperDashboard.php',
            'IncidentChangeManager': self.config['paths']['app'] / 'Modules' / 'Internal' / 'Developer' / 'IncidentChangeManager.php',
            'DocumentationManager': self.config['paths']['app'] / 'Modules' / 'Internal' / 'Developer' / 'DocumentationManager.php',
            'VendorManager': self.config['paths']['app'] / 'Modules' / 'Internal' / 'Developer' / 'VendorManager.php',
            'AlertManager': self.config['paths']['app'] / 'Modules' / 'Internal' / 'Developer' / 'AlertManager.php'
        }
        
        valid_modules = 0
        for module_name, module_path in module_paths.items():
            if module_path.exists():
                if self.check_php_syntax(module_path):
                    valid_modules += 1
                else:
                    self.log(f"Módulo {module_name} tiene errores de sintaxis", 'WARNING')
            else:
                self.log(f"Módulo {module_name} no encontrado en {module_path}", 'ERROR')
        
        self.log(f"Módulos validados: {valid_modules}/{len(module_paths)}")
        return valid_modules == len(module_paths)
    
    def validate_api_endpoints(self):
        """Validar endpoints de la API Developer"""
        self.log("Validando endpoints de la API...")
        
        api_endpoints = [
            'dashboard.php',
            'incidents.php', 
            'documents.php',
            'vendors.php',
            'alerts.php'
        ]
        
        api_path = self.config['paths']['public'] / 'backend' / 'developer'
        valid_endpoints = 0
        
        for endpoint in api_endpoints:
            endpoint_path = api_path / endpoint
            if endpoint_path.exists():
                if self.check_php_syntax(endpoint_path):
                    valid_endpoints += 1
            else:
                self.log(f"Endpoint {endpoint} no encontrado", 'WARNING')
        
        self.log(f"Endpoints validados: {valid_endpoints}/{len(api_endpoints)}")
        return valid_endpoints > 0
    
    def validate_frontend(self):
        """Validar interfaz frontend"""
        self.log("Validando interfaz frontend...")
        
        frontend_path = self.config['paths']['public'] / 'apps' / 'developer' / 'index.html'
        
        if frontend_path.exists():
            self.log("✓ Interfaz frontend encontrada")
            
            # Validar contenido básico
            content = frontend_path.read_text(encoding='utf-8')
            
            required_elements = [
                'bootstrap',
                'chart.js',
                'developer-dashboard',
                'incident-management',
                'document-management'
            ]
            
            missing_elements = []
            for element in required_elements:
                if element.lower() not in content.lower():
                    missing_elements.append(element)
            
            if missing_elements:
                self.log(f"Elementos faltantes en frontend: {', '.join(missing_elements)}", 'WARNING')
            else:
                self.log("✓ Todos los elementos frontend están presentes")
            
            return len(missing_elements) == 0
        else:
            self.log("✗ Interfaz frontend no encontrada", 'ERROR')
            return False
    
    def test_database_connection(self):
        """Probar conexión a base de datos"""
        self.log("Probando conexión a base de datos...")
        
        try:
            import mysql.connector
            
            connection = mysql.connector.connect(
                host=self.config['database']['host'],
                port=self.config['database']['port'],
                database=self.config['database']['database'],
                user=self.config['database']['user'],
                password=self.config['database']['password']
            )
            
            if connection.is_connected():
                cursor = connection.cursor()
                
                # Verificar tablas requeridas
                required_tables = [
                    'incidents',
                    'change_requests', 
                    'documents',
                    'vendors',
                    'alert_rules',
                    'alerts'
                ]
                
                cursor.execute("SHOW TABLES")
                existing_tables = [table[0] for table in cursor.fetchall()]
                
                missing_tables = []
                for table in required_tables:
                    if table not in existing_tables:
                        missing_tables.append(table)
                
                if missing_tables:
                    self.log(f"Tablas faltantes: {', '.join(missing_tables)}", 'WARNING')
                    self.log("Ejecute el script SQL developer_private_section_schema.sql", 'INFO')
                else:
                    self.log("✓ Todas las tablas requeridas están presentes")
                
                connection.close()
                return len(missing_tables) == 0
                
        except ImportError:
            self.log("mysql-connector-python no está instalado", 'WARNING')
            self.log("Instale con: pip install mysql-connector-python", 'INFO')
            return False
        except Exception as e:
            self.log(f"Error de conexión a base de datos: {str(e)}", 'ERROR')
            return False
    
    def setup_directories(self):
        """Crear directorios necesarios"""
        self.log("Configurando estructura de directorios...")
        
        directories = [
            self.config['paths']['storage'] / 'developer',
            self.config['paths']['storage'] / 'developer' / 'logs',
            self.config['paths']['storage'] / 'developer' / 'documents',
            self.config['paths']['storage'] / 'developer' / 'exports',
            self.config['paths']['storage'] / 'developer' / 'backups'
        ]
        
        for directory in directories:
            if not directory.exists():
                directory.mkdir(parents=True, exist_ok=True)
                self.log(f"✓ Directorio creado: {directory}")
            else:
                self.log(f"✓ Directorio existe: {directory}")
    
    def create_config_file(self):
        """Crear archivo de configuración"""
        config_path = self.config['paths']['storage'] / 'developer' / 'config.json'
        
        developer_config = {
            'system': {
                'version': '1.0.0',
                'environment': 'development',
                'debug': True,
                'timezone': 'America/Mexico_City'
            },
            'database': self.config['database'],
            'monitoring': {
                'enabled': True,
                'interval_seconds': 300,
                'retention_days': 30
            },
            'alerts': {
                'enabled': True,
                'email_notifications': True,
                'slack_notifications': False,
                'sms_notifications': False
            },
            'security': {
                'session_timeout': 3600,
                'max_login_attempts': 5,
                'password_policy': {
                    'min_length': 8,
                    'require_special_chars': True,
                    'require_numbers': True
                }
            },
            'api': {
                'rate_limit': 1000,
                'timeout': 30,
                'cors_enabled': True
            }
        }
        
        with open(config_path, 'w', encoding='utf-8') as f:
            json.dump(developer_config, f, indent=2, ensure_ascii=False)
        
        self.log(f"✓ Archivo de configuración creado: {config_path}")
    
    def run_initial_setup(self):
        """Ejecutar configuración inicial completa"""
        self.log("=== INICIANDO CONFIGURACIÓN DEL SISTEMA DEVELOPER ===")
        
        setup_steps = [
            ("Configurando directorios", self.setup_directories),
            ("Creando archivo de configuración", self.create_config_file),
            ("Validando módulos PHP", self.validate_modules),
            ("Validando endpoints API", self.validate_api_endpoints),
            ("Validando interfaz frontend", self.validate_frontend),
            ("Probando conexión a base de datos", self.test_database_connection)
        ]
        
        successful_steps = 0
        total_steps = len(setup_steps)
        
        for step_name, step_function in setup_steps:
            self.log(f"\n--- {step_name} ---")
            try:
                if step_function():
                    successful_steps += 1
                    self.log(f"✓ {step_name} completado exitosamente")
                else:
                    self.log(f"⚠ {step_name} completado con advertencias", 'WARNING')
            except Exception as e:
                self.log(f"✗ Error en {step_name}: {str(e)}", 'ERROR')
        
        self.log(f"\n=== RESUMEN DE CONFIGURACIÓN ===")
        self.log(f"Pasos completados: {successful_steps}/{total_steps}")
        
        if successful_steps == total_steps:
            self.log("🎉 Sistema Developer configurado exitosamente!", 'SUCCESS')
            self.log("Puede acceder al sistema en: http://localhost/SBL_SISTEMA_INTERNO/public/apps/developer/")
        elif successful_steps >= total_steps * 0.8:
            self.log("⚠ Sistema parcialmente configurado. Revise las advertencias.", 'WARNING')
        else:
            self.log("❌ Configuración incompleta. Revise los errores.", 'ERROR')
    
    def generate_test_data(self):
        """Generar datos de prueba"""
        self.log("Generando datos de prueba...")
        
        try:
            import mysql.connector
            
            connection = mysql.connector.connect(
                host=self.config['database']['host'],
                port=self.config['database']['port'],
                database=self.config['database']['database'],
                user=self.config['database']['user'],
                password=self.config['database']['password']
            )
            
            cursor = connection.cursor()
            
            # Datos de prueba para incidencias
            test_incidents = [
                ("Sistema lento en horas pico", "El sistema presenta lentitud durante las horas de mayor uso", "medium"),
                ("Error de conexión a base de datos", "Conexión intermitente a la base de datos principal", "high"),
                ("Fallo en módulo de reportes", "El módulo de reportes no genera archivos PDF", "critical")
            ]
            
            for title, description, severity in test_incidents:
                cursor.execute("""
                    INSERT IGNORE INTO incidents (title, description, severity, reporter_id, category)
                    VALUES (%s, %s, %s, 1, 'system')
                """, (title, description, severity))
            
            # Datos de prueba para documentos
            test_documents = [
                ("SOP Backup Diario", "sop", "Procedimiento para realizar backup diario del sistema", "1.0"),
                ("Manual de Usuario AppCare", "appcare", "Manual completo para el uso de AppCare", "2.1"),
                ("Handover Migración Base de Datos", "handover", "Documentación de entrega para migración de BD", "1.0")
            ]
            
            for title, doc_type, content, version in test_documents:
                cursor.execute("""
                    INSERT IGNORE INTO documents (title, document_type, content, version, author_id, status)
                    VALUES (%s, %s, %s, %s, 1, 'active')
                """, (title, doc_type, content, version))
            
            # Datos de prueba para proveedores
            test_vendors = [
                ("TechSolutions Inc", "tech@solutions.com", "development", "active"),
                ("SecureIT Services", "contact@secureit.com", "security", "active"),
                ("CloudHost Pro", "support@cloudhost.com", "hosting", "active")
            ]
            
            for name, email, service_type, status in test_vendors:
                cursor.execute("""
                    INSERT IGNORE INTO vendors (name, contact_email, service_type, status, created_by)
                    VALUES (%s, %s, %s, %s, 1)
                """, (name, email, service_type, status))
            
            connection.commit()
            connection.close()
            
            self.log("✓ Datos de prueba generados exitosamente")
            return True
            
        except Exception as e:
            self.log(f"Error generando datos de prueba: {str(e)}", 'ERROR')
            return False
    
    def validate_permissions(self):
        """Validar permisos de archivos y directorios"""
        self.log("Validando permisos del sistema...")
        
        critical_paths = [
            self.config['paths']['storage'],
            self.config['paths']['storage'] / 'developer',
            self.config['paths']['app'] / 'Modules' / 'Internal' / 'Developer'
        ]
        
        for path in critical_paths:
            if path.exists():
                if os.access(path, os.R_OK | os.W_OK):
                    self.log(f"✓ Permisos correctos: {path}")
                else:
                    self.log(f"⚠ Permisos insuficientes: {path}", 'WARNING')
            else:
                self.log(f"✗ Ruta no existe: {path}", 'ERROR')

def main():
    """Función principal"""
    print("🔧 SISTEMA DE CONFIGURACIÓN DEVELOPER PRIVATE SECTION")
    print("=" * 60)
    
    setup = DeveloperSystemSetup()
    
    if len(sys.argv) > 1:
        command = sys.argv[1].lower()
        
        if command == 'install':
            setup.run_initial_setup()
        elif command == 'validate':
            setup.validate_modules()
            setup.validate_api_endpoints()
            setup.validate_frontend()
        elif command == 'testdata':
            setup.generate_test_data()
        elif command == 'permissions':
            setup.validate_permissions()
        elif command == 'dbtest':
            setup.test_database_connection()
        else:
            print("Comandos disponibles:")
            print("  install    - Configuración inicial completa")
            print("  validate   - Validar componentes del sistema")
            print("  testdata   - Generar datos de prueba")
            print("  permissions- Validar permisos de archivos")
            print("  dbtest     - Probar conexión a base de datos")
    else:
        setup.run_initial_setup()

if __name__ == "__main__":
    main()