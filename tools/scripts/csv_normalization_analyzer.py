#!/usr/bin/env python3
"""
Sistema de Análisis de Cambios en Normalización de CSV
======================================================

Este script detecta y analiza todos los cambios realizados por software durante
la normalización de archivos CSV originales, generando reportes detallados para
revisión de las ingenieras.

Funcionalidades:
- Detección de cambios de homologación de términos
- Corrección de ortografía y formato
- Normalización de datos de instrumentos
- Generación de análisis de cambios
- Creación de SQL con formato audit_trail
- README con opciones de cambios para aprobación

Autor: Sistema SBL - Validación de Instrumentos
Fecha: Septiembre 2025
"""

import csv
import json
import os
import re
import datetime as dt
from dataclasses import dataclass, asdict
from pathlib import Path
from typing import List, Dict, Optional, Tuple, Set
import difflib
import unicodedata
import hashlib

# Configuración de directorios
BASE_DIR = Path(__file__).resolve().parent.parent.parent
ARCHIVOS_SQL_DIR = BASE_DIR / "app" / "Modules" / "Internal" / "ArchivosSql"
CSV_ORIGINAL_DIR = ARCHIVOS_SQL_DIR / "Archivos_CSV_originales"
NORMALIZE_DIR = ARCHIVOS_SQL_DIR / "Archivos_Normalize"
ANALYSIS_DIR = BASE_DIR / "tools" / "analysis_results"

# Crear directorio de análisis si no existe
ANALYSIS_DIR.mkdir(exist_ok=True)

@dataclass
class ChangeRule:
    """Representa una regla de cambio aplicada durante la normalización"""
    rule_id: str
    rule_type: str  # 'homologation', 'spelling', 'format', 'data_normalization'
    description: str
    pattern: str
    replacement: str
    confidence: float  # 0.0 - 1.0
    examples: List[str]
    impact_level: str  # 'low', 'medium', 'high'
    requires_approval: bool

@dataclass
class DetectedChange:
    """Representa un cambio detectado en un archivo CSV"""
    file_name: str
    row_number: int
    column_name: str
    original_value: str
    normalized_value: str
    change_rule: ChangeRule
    cell_reference: str  # Ej: "B15"
    instrument_code: str  # Ej: "AER-VA-01"
    timestamp: dt.datetime
    change_hash: str

@dataclass
class AnalysisReport:
    """Reporte completo de análisis de cambios"""
    analysis_id: str
    timestamp: dt.datetime
    total_changes: int
    files_analyzed: List[str]
    changes_by_type: Dict[str, int]
    detected_changes: List[DetectedChange]
    suggested_rules: List[ChangeRule]
    requires_approval: bool

class CSVNormalizationAnalyzer:
    """Analizador principal de cambios en normalización de CSV"""
    
    def __init__(self):
        self.homologation_rules = self._load_homologation_rules()
        self.spelling_corrections = self._load_spelling_corrections()
        self.format_rules = self._load_format_rules()
        self.capitalization_rules = self._load_capitalization_rules()
        self.detected_changes: List[DetectedChange] = []
        
    def _load_homologation_rules(self) -> Dict[str, ChangeRule]:
        """Carga reglas de homologación de términos desde el archivo de configuración"""
        # Intentar cargar desde archivo de configuración
        config_file = BASE_DIR / "tools" / "config" / "normalization_config.json"
        rules = {}
        
        try:
            with open(config_file, 'r', encoding='utf-8') as f:
                config = json.load(f)
            
            custom_rules = config.get('custom_rules', {})
            
            # Instrumentos con ortografía corregida
            if 'instrument_spelling_corrections' in custom_rules:
                instrument_patterns = []
                instrument_examples = []
                for wrong, correct in custom_rules['instrument_spelling_corrections'].items():
                    instrument_patterns.append(wrong)
                    instrument_examples.append(f"{wrong} → {correct}")
                
                rules["instrument_spelling"] = ChangeRule(
                    rule_id="HOM001",
                    rule_type="homologation",
                    description="Corrección ortográfica de nombres de instrumentos",
                    pattern="|".join(re.escape(p) for p in instrument_patterns),
                    replacement="Corrección automática",
                    confidence=0.95,
                    examples=instrument_examples[:5],  # Máximo 5 ejemplos
                    impact_level="low",
                    requires_approval=False
                )
            
            # Marcas estandarizadas
            if 'brand_standardization' in custom_rules:
                brand_patterns = []
                brand_examples = []
                for wrong, correct in custom_rules['brand_standardization'].items():
                    brand_patterns.append(wrong)
                    brand_examples.append(f"{wrong} → {correct}")
                
                rules["brand_standardization"] = ChangeRule(
                    rule_id="HOM002",
                    rule_type="homologation",
                    description="Estandarización de nombres de marcas",
                    pattern="|".join(re.escape(p) for p in brand_patterns),
                    replacement="Estandarización automática",
                    confidence=0.90,
                    examples=brand_examples[:5],
                    impact_level="medium",
                    requires_approval=True
                )
            
            # Departamentos estandarizados
            if 'department_standardization' in custom_rules:
                dept_patterns = []
                dept_examples = []
                for wrong, correct in custom_rules['department_standardization'].items():
                    dept_patterns.append(wrong)
                    dept_examples.append(f"{wrong} → {correct}")
                
                rules["department_standardization"] = ChangeRule(
                    rule_id="HOM003",
                    rule_type="homologation",
                    description="Estandarización de nombres de departamentos",
                    pattern="|".join(re.escape(p) for p in dept_patterns),
                    replacement="Estandarización automática",
                    confidence=0.85,
                    examples=dept_examples[:5],
                    impact_level="medium",
                    requires_approval=True
                )
            
            # Ubicaciones estandarizadas
            if 'location_standardization' in custom_rules:
                loc_patterns = []
                loc_examples = []
                for wrong, correct in custom_rules['location_standardization'].items():
                    loc_patterns.append(wrong)
                    loc_examples.append(f"{wrong} → {correct}")
                
                rules["location_standardization"] = ChangeRule(
                    rule_id="HOM004",
                    rule_type="homologation",
                    description="Estandarización de nombres de ubicaciones",
                    pattern="|".join(re.escape(p) for p in loc_patterns),
                    replacement="Estandarización automática",
                    confidence=0.85,
                    examples=loc_examples[:5],
                    impact_level="medium",
                    requires_approval=True
                )
            
            # Estados/Status estandarizados
            if 'status_standardization' in custom_rules:
                status_patterns = []
                status_examples = []
                for wrong, correct in custom_rules['status_standardization'].items():
                    status_patterns.append(wrong)
                    status_examples.append(f"{wrong} → {correct}")
                
                rules["status_standardization"] = ChangeRule(
                    rule_id="HOM005",
                    rule_type="homologation",
                    description="Estandarización de estados y status",
                    pattern="|".join(re.escape(p) for p in status_patterns),
                    replacement="Estandarización automática",
                    confidence=0.90,
                    examples=status_examples[:5],
                    impact_level="medium",
                    requires_approval=True
                )
            
            # Placeholders estandarizados
            if 'placeholder_standardization' in custom_rules:
                ph_patterns = []
                ph_examples = []
                for wrong, correct in custom_rules['placeholder_standardization'].items():
                    ph_patterns.append(wrong)
                    ph_examples.append(f"{wrong} → {correct}")
                
                rules["placeholder_standardization"] = ChangeRule(
                    rule_id="HOM006",
                    rule_type="homologation",
                    description="Estandarización de valores placeholder (NA, ND, etc.)",
                    pattern="|".join(re.escape(p) for p in ph_patterns),
                    replacement="Estandarización automática",
                    confidence=0.95,
                    examples=ph_examples[:5],
                    impact_level="low",
                    requires_approval=False
                )
            
            # Unidades estandarizadas
            if 'units_standardization' in custom_rules:
                unit_patterns = []
                unit_examples = []
                for wrong, correct in custom_rules['units_standardization'].items():
                    unit_patterns.append(wrong)
                    unit_examples.append(f"{wrong} → {correct}")
                
                rules["units_standardization"] = ChangeRule(
                    rule_id="HOM007",
                    rule_type="homologation",
                    description="Estandarización de unidades de medida",
                    pattern="|".join(re.escape(p) for p in unit_patterns),
                    replacement="Estandarización automática",
                    confidence=0.95,
                    examples=unit_examples[:5],
                    impact_level="low",
                    requires_approval=False
                )
            
        except (FileNotFoundError, json.JSONDecodeError, KeyError) as e:
            print(f"⚠️  Error al cargar configuración personalizada: {e}")
            print("📋 Usando reglas predeterminadas...")
            
            # Reglas predeterminadas como fallback
            rules = {
                "units_temperature": ChangeRule(
                    rule_id="HOM001",
                    rule_type="homologation",
                    description="Homologación de unidades de temperatura",
                    pattern=r"°C|ºC|grados?\s*[Cc]|celsius",
                    replacement="°C",
                    confidence=0.95,
                    examples=["grados C → °C", "ºC → °C", "celsius → °C"],
                    impact_level="low",
                    requires_approval=False
                ),
                "units_pressure": ChangeRule(
                    rule_id="HOM002",
                    rule_type="homologation",
                    description="Homologación de unidades de presión", 
                    pattern=r"kg/cm2|Kg/cm2|kgf/cm2|kg\s*/\s*cm2",
                    replacement="kg/cm²",
                    confidence=0.90,
                    examples=["kg/cm2 → kg/cm²", "Kg/cm2 → kg/cm²"],
                    impact_level="low",
                    requires_approval=False
                )
            }
        
        return rules
    
    def _load_spelling_corrections(self) -> Dict[str, ChangeRule]:
        """Carga reglas de corrección ortográfica desde configuración JSON"""
        rules = {}
        
        # Cargar configuración desde archivo JSON
        config_path = BASE_DIR / "tools" / "config" / "normalization_config.json"
        if config_path.exists():
            try:
                with open(config_path, 'r', encoding='utf-8') as f:
                    config = json.load(f)
                
                # Reglas de instrumentos
                instrument_corrections = config.get('normalization_rules', {}).get('instrument_spelling_corrections', {})
                if instrument_corrections:
                    rules["instruments"] = ChangeRule(
                        rule_id="SPL001",
                        rule_type="spelling",
                        description="Corrección ortográfica de nombres de instrumentos",
                        pattern="|".join(re.escape(key) for key in instrument_corrections.keys()),
                        replacement="Ortografía corregida con tildes",
                        confidence=0.95,
                        examples=[f"{k} → {v}" for k, v in list(instrument_corrections.items())[:5]],
                        impact_level="medium",
                        requires_approval=False
                    )
                
                # Reglas de marcas
                brand_corrections = config.get('normalization_rules', {}).get('brand_standardization', {})
                if brand_corrections:
                    rules["brands"] = ChangeRule(
                        rule_id="SPL002",
                        rule_type="spelling",
                        description="Estandarización de nombres de marcas",
                        pattern="|".join(re.escape(key) for key in brand_corrections.keys()),
                        replacement="Marcas estandarizadas",
                        confidence=0.90,
                        examples=[f"{k} → {v}" for k, v in list(brand_corrections.items())[:5]],
                        impact_level="medium",
                        requires_approval=False
                    )
                
                # Reglas de departamentos
                dept_corrections = config.get('normalization_rules', {}).get('department_standardization', {})
                if dept_corrections:
                    rules["departments"] = ChangeRule(
                        rule_id="SPL003",
                        rule_type="spelling",
                        description="Estandarización de nombres de departamentos", 
                        pattern="|".join(re.escape(key) for key in dept_corrections.keys()),
                        replacement="Departamentos estandarizados",
                        confidence=0.85,
                        examples=[f"{k} → {v}" for k, v in list(dept_corrections.items())[:5]],
                        impact_level="low",
                        requires_approval=False
                    )
                    
            except (FileNotFoundError, json.JSONDecodeError) as e:
                print(f"Error cargando configuración: {e}")
        
        # Reglas por defecto si no hay configuración
        if not rules:
            rules = {
                "default": ChangeRule(
                    rule_id="SPL001",
                    rule_type="spelling",
                    description="Corrección de errores ortográficos comunes",
                    pattern=r"termometro|bascula|cronometro|manometro|luxometro|tacometro|micrometro|viscosimetro|multimetro|densimetro|durometro|fusiometro|amperimetro|anemometro|balometro",
                    replacement="Con acentos correctos",
                    confidence=0.95,
                    examples=["termometro → termómetro", "bascula → báscula", "cronometro → cronómetro"],
                    impact_level="low",
                    requires_approval=False
                )
            }
            
        return rules
    
    def _load_format_rules(self) -> Dict[str, ChangeRule]:
        """Carga reglas de formato y normalización"""
        rules = {
            "date_format": ChangeRule(
                rule_id="FMT001",
                rule_type="format",
                description="Normalización de formato de fechas",
                pattern=r"\d{1,2}[-/]\d{1,2}[-/]\d{2,4}",
                replacement="YYYY-MM-DD",
                confidence=0.95,
                examples=["15/03/2024 → 2024-03-15", "3-15-24 → 2024-03-15"],
                impact_level="high",
                requires_approval=True
            ),
            "numeric_precision": ChangeRule(
                rule_id="FMT002",
                rule_type="format",
                description="Normalización de precisión numérica",
                pattern=r"\d+\.\d{3,}",
                replacement="X.XX (2 decimales)",
                confidence=0.80,
                examples=["25.456789 → 25.46", "1.2345 → 1.23"],
                impact_level="medium",
                requires_approval=True
            ),
            "whitespace_cleanup": ChangeRule(
                rule_id="FMT003",
                rule_type="format",
                description="Limpieza de espacios en blanco",
                pattern=r"\s{2,}|^\s+|\s+$",
                replacement="Espacio único o sin espacios",
                confidence=1.0,
                examples=["  texto   → texto", "inicio  fin → inicio fin"],
                impact_level="low",
                requires_approval=False
            )
        }
        return rules
    
    def _load_capitalization_rules(self) -> Dict[str, ChangeRule]:
        """Carga reglas de capitalización desde configuración JSON"""
        rules = {}
        
        # Cargar configuración desde archivo JSON
        config_path = BASE_DIR / "tools" / "config" / "normalization_config.json"
        if config_path.exists():
            try:
                with open(config_path, 'r', encoding='utf-8') as f:
                    config = json.load(f)
                
                cap_rules = config.get('normalization_rules', {}).get('capitalization_rules', {})
                if cap_rules:
                    rules["proper_nouns"] = ChangeRule(
                        rule_id="CAP001",
                        rule_type="capitalization",
                        description="Capitalización correcta de nombres propios e instrumentos",
                        pattern=r"^[a-z]|(?<=\s)[a-z]",
                        replacement="Primera letra mayúscula",
                        confidence=0.95,
                        examples=["termohigrómetro → Termohigrómetro", "fluke → Fluke"],
                        impact_level="low",
                        requires_approval=False
                    )
                    
                    acronyms = cap_rules.get('preserve_acronyms', [])
                    if acronyms:
                        rules["acronyms"] = ChangeRule(
                            rule_id="CAP002",
                            rule_type="capitalization",
                            description="Preservar acrónimos en mayúsculas",
                            pattern="|".join(acronyms),
                            replacement="Mantener mayúsculas",
                            confidence=1.0,
                            examples=["ir → IR", "usb → USB", "lcd → LCD"],
                            impact_level="low",
                            requires_approval=False
                        )
                        
            except (FileNotFoundError, json.JSONDecodeError) as e:
                print(f"Error cargando reglas de capitalización: {e}")
        
        return rules
    
    def _normalize_text_with_capitalization(self, text: str) -> str:
        """Aplica reglas de capitalización y caracteres especiales a un texto"""
        if not text or not isinstance(text, str):
            return text
            
        # Cargar configuración
        config_path = BASE_DIR / "tools" / "config" / "normalization_config.json"
        if not config_path.exists():
            return text
            
        try:
            with open(config_path, 'r', encoding='utf-8') as f:
                config = json.load(f)
            
            # Aplicar correcciones ortográficas de instrumentos
            instrument_corrections = config.get('normalization_rules', {}).get('instrument_spelling_corrections', {})
            for incorrect, correct in instrument_corrections.items():
                if incorrect in text:
                    text = text.replace(incorrect, correct)
            
            # Aplicar estandarización de marcas
            brand_corrections = config.get('normalization_rules', {}).get('brand_standardization', {})
            for incorrect, correct in brand_corrections.items():
                if incorrect in text:
                    text = text.replace(incorrect, correct)
            
            # Aplicar estandarización de departamentos
            dept_corrections = config.get('normalization_rules', {}).get('department_standardization', {})
            for incorrect, correct in dept_corrections.items():
                if incorrect in text:
                    text = text.replace(incorrect, correct)
            
            # Aplicar reglas de capitalización
            cap_rules = config.get('normalization_rules', {}).get('capitalization_rules', {})
            if cap_rules.get('first_letter_uppercase', False):
                # Capitalizar primera letra de cada palabra importante
                words = text.split()
                capitalized_words = []
                
                preserve_acronyms = cap_rules.get('preserve_acronyms', [])
                
                for word in words:
                    # Si es un acrónimo, mantenerlo en mayúsculas
                    if word.upper() in preserve_acronyms:
                        capitalized_words.append(word.upper())
                    # Si es una palabra normal, capitalizar primera letra
                    elif len(word) > 0:
                        capitalized_words.append(word[0].upper() + word[1:].lower())
                    else:
                        capitalized_words.append(word)
                
                text = ' '.join(capitalized_words)
            
            return text
            
        except (FileNotFoundError, json.JSONDecodeError) as e:
            print(f"Error aplicando normalización de texto: {e}")
            return text
    
    def analyze_csv_file(self, file_path: Path) -> List[DetectedChange]:
        """Analiza un archivo CSV individual en busca de cambios de normalización"""
        changes = []
        file_name = file_path.name
        
        try:
            with open(file_path, 'r', encoding='utf-8') as file:
                csv_reader = csv.reader(file)
                headers = next(csv_reader, [])
                
                for row_num, row in enumerate(csv_reader, start=2):  # Start at 2 (header is row 1)
                    for col_num, cell_value in enumerate(row):
                        if col_num < len(headers):
                            column_name = headers[col_num]
                            cell_ref = f"{self._column_number_to_letter(col_num + 1)}{row_num}"
                            
                            # Aplicar todas las reglas de análisis
                            detected = self._analyze_cell_value(
                                file_path, file_name, row_num, column_name, cell_value, cell_ref
                            )
                            changes.extend(detected)
                            
        except Exception as e:
            print(f"Error analizando {file_path}: {e}")
            
        return changes
    
    def _analyze_cell_value(self, file_path: Path, file_name: str, row_num: int, column_name: str, 
                           original_value: str, cell_ref: str) -> List[DetectedChange]:
        """Analiza el valor de una celda individual"""
        changes = []
        
        if not original_value or original_value.strip() == "":
            return changes
            
        # Extraer código del instrumento para esta fila
        instrument_code = self._extract_instrument_code(file_path, row_num)
        
        # Verificar si el campo debe ser excluido de normalización
        if self._should_exclude_field(column_name, original_value):
            return changes
            
        # Aplicar reglas de homologación
        for rule in self.homologation_rules.values():
            normalized = self._apply_rule(original_value, rule)
            if normalized != original_value:
                changes.append(self._create_change(
                    file_name, row_num, column_name, original_value, 
                    normalized, rule, cell_ref, instrument_code
                ))
        
        # Aplicar reglas de corrección ortográfica
        for rule in self.spelling_corrections.values():
            normalized = self._apply_rule(original_value, rule)
            if normalized != original_value:
                changes.append(self._create_change(
                    file_name, row_num, column_name, original_value,
                    normalized, rule, cell_ref, instrument_code
                ))
        
        # Aplicar reglas de formato
        for rule in self.format_rules.values():
            normalized = self._apply_rule(original_value, rule)
            if normalized != original_value:
                changes.append(self._create_change(
                    file_name, row_num, column_name, original_value,
                    normalized, rule, cell_ref, instrument_code
                ))
        
        # Aplicar reglas de capitalización y caracteres especiales
        normalized_text = self._normalize_text_with_capitalization(original_value)
        if normalized_text != original_value:
            # Crear regla dinámica para esta corrección
            cap_rule = ChangeRule(
                rule_id="CAP_AUTO",
                rule_type="capitalization",
                description="Corrección automática de capitalización y tildes",
                pattern="auto-detected",
                replacement=normalized_text,
                confidence=0.90,
                examples=[f"{original_value} → {normalized_text}"],
                impact_level="low",
                requires_approval=False
            )
            changes.append(self._create_change(
                file_name, row_num, column_name, original_value,
                normalized_text, cap_rule, cell_ref, instrument_code
            ))
        
        return changes
    
    def _apply_rule(self, value: str, rule: ChangeRule) -> str:
        """Aplica una regla específica a un valor"""
        if rule.rule_type == "homologation":
            return re.sub(rule.pattern, rule.replacement, value, flags=re.IGNORECASE)
        elif rule.rule_type == "spelling":
            return re.sub(rule.pattern, rule.replacement, value, flags=re.IGNORECASE)
        elif rule.rule_type == "format":
            if rule.rule_id == "FMT001":  # Fechas
                return self._normalize_date(value)
            elif rule.rule_id == "FMT002":  # Precisión numérica
                return self._normalize_numeric_precision(value)
            elif rule.rule_id == "FMT003":  # Espacios
                return re.sub(r'\s+', ' ', value.strip())
        
        return value
    
    def _normalize_date(self, value: str) -> str:
        """Normaliza formato de fecha"""
        # Patrones de fecha comunes
        patterns = [
            (r'(\d{1,2})[/-](\d{1,2})[/-](\d{4})', r'\3-\2-\1'),  # DD/MM/YYYY -> YYYY-MM-DD
            (r'(\d{1,2})[/-](\d{1,2})[/-](\d{2})', r'20\3-\2-\1'),  # DD/MM/YY -> 20YY-MM-DD
        ]
        
        for pattern, replacement in patterns:
            if re.match(pattern, value):
                return re.sub(pattern, replacement, value)
        
        return value
    
    def _normalize_numeric_precision(self, value: str) -> str:
        """Normaliza precisión numérica a 2 decimales"""
        try:
            if '.' in value and value.replace('.', '').replace('-', '').isdigit():
                num = float(value)
                return f"{num:.2f}"
        except ValueError:
            pass
        return value
    
    def _extract_instrument_code(self, file_path: Path, row_num: int) -> str:
        """Extrae el código del instrumento de una fila específica"""
        try:
            with open(file_path, 'r', encoding='utf-8') as file:
                csv_reader = csv.reader(file)
                headers = next(csv_reader, [])
                
                # Buscar columna "Código" específicamente, ya que todos los archivos principales la tienen
                code_col_index = -1
                
                for i, header in enumerate(headers):
                    if header.strip().lower() == 'código':
                        code_col_index = i
                        break
                
                # Si no encontramos "Código", buscar alternativas
                if code_col_index == -1:
                    alternative_columns = ['codigo', 'code', 'codigo_instrumento', 'instrument_code', 'hoja', 'id']
                    for i, header in enumerate(headers):
                        if header.lower().strip() in alternative_columns:
                            code_col_index = i
                            break
                
                # Para el archivo AT_instrumentos (que es un log), usar la columna "Hoja"
                if file_path.name.startswith('AT_') and code_col_index == -1:
                    for i, header in enumerate(headers):
                        if header.lower().strip() == 'hoja':
                            code_col_index = i
                            break
                
                # Si aún no encontramos, usar primera columna como fallback
                if code_col_index == -1:
                    code_col_index = 0
                
                # Leer hasta la fila específica
                for current_row_num, row in enumerate(csv_reader, start=2):
                    if current_row_num == row_num:
                        if code_col_index < len(row):
                            code = row[code_col_index].strip()
                            # Si está vacío o es "NA", usar identificador de fila
                            if not code or code.upper() in ['NA', 'ND', 'NULL', '']:
                                return f"{file_path.stem}_ROW_{row_num}"
                            return code
                        break
                        
        except Exception as e:
            print(f"Error extrayendo código de instrumento de {file_path.name}: {e}")
            
        return f"{file_path.stem}_ROW_{row_num}"  # Fallback con nombre de archivo
    
    def _create_change(self, file_name: str, row_num: int, column_name: str,
                      original: str, normalized: str, rule: ChangeRule, 
                      cell_ref: str, instrument_code: Optional[str] = None) -> DetectedChange:
        """Crea un objeto DetectedChange"""
        change_data = f"{file_name}:{row_num}:{column_name}:{original}:{normalized}"
        change_hash = hashlib.md5(change_data.encode()).hexdigest()[:8]
        
        # Si no se proporciona código de instrumento, usar referencia de fila
        if not instrument_code:
            instrument_code = f"ROW_{row_num}"
        
        return DetectedChange(
            file_name=file_name,
            row_number=row_num,
            column_name=column_name,
            original_value=original,
            normalized_value=normalized,
            change_rule=rule,
            cell_reference=cell_ref,
            instrument_code=instrument_code,
            timestamp=dt.datetime.now(),
            change_hash=change_hash
        )
    
    def analyze_all_csv_files(self) -> AnalysisReport:
        """Analiza todos los archivos CSV originales"""
        all_changes = []
        files_analyzed = []
        
        csv_files = [
            "AT_instrumentos_original_v2.csv",
            "LM_instrumentos_original_v2.csv", 
            "PR_instrumentos_original_v2.csv",
            "CERT_instrumentos_original_v2.csv"
        ]
        
        for file_name in csv_files:
            file_path = CSV_ORIGINAL_DIR / file_name
            if file_path.exists():
                print(f"Analizando {file_name}...")
                changes = self.analyze_csv_file(file_path)
                all_changes.extend(changes)
                files_analyzed.append(file_name)
                print(f"  Encontrados {len(changes)} cambios potenciales")
        
        # Generar estadísticas
        changes_by_type = {}
        requires_approval = False
        
        for change in all_changes:
            rule_type = change.change_rule.rule_type
            changes_by_type[rule_type] = changes_by_type.get(rule_type, 0) + 1
            if change.change_rule.requires_approval:
                requires_approval = True
        
        analysis_id = f"CSV_NORM_{dt.datetime.now().strftime('%Y%m%d_%H%M%S')}"
        
        report = AnalysisReport(
            analysis_id=analysis_id,
            timestamp=dt.datetime.now(),
            total_changes=len(all_changes),
            files_analyzed=files_analyzed,
            changes_by_type=changes_by_type,
            detected_changes=all_changes,
            suggested_rules=list(self.homologation_rules.values()) + 
                           list(self.spelling_corrections.values()) + 
                           list(self.format_rules.values()),
            requires_approval=requires_approval
        )
        
        return report
    
    def _column_number_to_letter(self, col_num: int) -> str:
        """Convierte número de columna a letra (1=A, 2=B, etc.)"""
        result = ""
        while col_num > 0:
            col_num -= 1
            result = chr(col_num % 26 + ord('A')) + result
            col_num //= 26
        return result
    
    def generate_analysis_files(self, report: AnalysisReport):
        """Genera todos los archivos de análisis"""
        timestamp_str = report.timestamp.strftime("%Y%m%d_%H%M%S")
        
        # 1. Archivo de análisis detallado (JSON)
        analysis_file = ANALYSIS_DIR / f"analisis_cambios_{timestamp_str}.json"
        with open(analysis_file, 'w', encoding='utf-8') as f:
            json.dump(asdict(report), f, indent=2, ensure_ascii=False, default=str)
        
        # 2. Archivo SQL con formato audit_trail
        sql_file = ANALYSIS_DIR / f"propuesta_audit_trail_{timestamp_str}.sql"
        self._generate_audit_trail_sql(report, sql_file)
        
        # 3. README con opciones de cambios
        readme_file = ANALYSIS_DIR / f"README_cambios_{timestamp_str}.md"
        self._generate_readme(report, readme_file)
        
        # 4. Resumen ejecutivo
        summary_file = ANALYSIS_DIR / f"resumen_ejecutivo_{timestamp_str}.md"
        self._generate_executive_summary(report, summary_file)
        
        # 5. Lista detallada por instrumento
        instrument_file = ANALYSIS_DIR / f"cambios_por_instrumento_{timestamp_str}.md"
        self._generate_instrument_report(report, instrument_file)
        
        print(f"\n✅ Archivos generados:")
        print(f"   📊 Análisis detallado: {analysis_file}")
        print(f"   🗄️  SQL audit_trail: {sql_file}")
        print(f"   📖 README opciones: {readme_file}")
        print(f"   📋 Lista por instrumento: {instrument_file}")
        print(f"   📋 Resumen ejecutivo: {summary_file}")
    
    def _generate_audit_trail_sql(self, report: AnalysisReport, sql_file: Path):
        """Genera archivo SQL con formato audit_trail"""
        with open(sql_file, 'w', encoding='utf-8') as f:
            f.write("-- Propuesta de registros audit_trail para cambios de normalización CSV\n")
            f.write(f"-- Generado automáticamente el {report.timestamp}\n")
            f.write(f"-- ID de análisis: {report.analysis_id}\n")
            f.write(f"-- Total de cambios detectados: {report.total_changes}\n\n")
            
            f.write("START TRANSACTION;\n\n")
            
            for i, change in enumerate(report.detected_changes, 1):
                f.write(f"-- Cambio {i}: {change.change_rule.description}\n")
                f.write("INSERT INTO audit_trail (\n")
                f.write("    empresa_id,\n")
                f.write("    segmento_actor,\n")
                f.write("    fecha_evento,\n")
                f.write("    seccion,\n")
                f.write("    rango_referencia,\n")
                f.write("    valor_anterior,\n")
                f.write("    valor_nuevo,\n")
                f.write("    usuario_correo,\n")
                f.write("    usuario_nombre,\n")
                f.write("    usuario_firma_interna,\n")
                f.write("    instrumento_codigo,\n")
                f.write("    columna_excel,\n")
                f.write("    fila_excel\n")
                f.write(") VALUES (\n")
                f.write("    1, -- empresa_id\n")
                f.write("    'Sistema de Normalización', -- segmento_actor\n")
                f.write(f"    '{change.timestamp.strftime('%Y-%m-%d %H:%M:%S')}', -- fecha_evento\n")
                f.write(f"    'Normalización CSV - {change.file_name}', -- seccion\n")
                f.write(f"    '{change.cell_reference}', -- rango_referencia\n")
                f.write(f"    {self._sql_escape(change.original_value)}, -- valor_anterior\n")
                f.write(f"    {self._sql_escape(change.normalized_value)}, -- valor_nuevo\n")
                f.write("    'sistema@sblpharma.com', -- usuario_correo (pendiente aprobación)\n")
                f.write("    'Sistema de Normalización Automática', -- usuario_nombre\n")
                f.write("    NULL, -- usuario_firma_interna (pendiente)\n")
                f.write("    NULL, -- instrumento_codigo (se determinará)\n")
                f.write(f"    '{change.cell_reference[:-len(str(change.row_number))]}', -- columna_excel\n")
                f.write(f"    {change.row_number} -- fila_excel\n")
                f.write(");\n\n")
            
            f.write("-- COMMIT; -- Descomenta esta línea después de la aprobación\n")
            f.write("ROLLBACK; -- Comentar esta línea después de la aprobación\n")
    
    def _generate_readme(self, report: AnalysisReport, readme_file: Path):
        """Genera README con opciones de cambios para aprobación"""
        with open(readme_file, 'w', encoding='utf-8') as f:
            f.write("# Análisis de Cambios en Normalización de CSV\n\n")
            f.write(f"**ID de Análisis:** {report.analysis_id}\n")
            f.write(f"**Fecha:** {report.timestamp.strftime('%Y-%m-%d %H:%M:%S')}\n")
            f.write(f"**Archivos analizados:** {len(report.files_analyzed)}\n")
            f.write(f"**Total de cambios detectados:** {report.total_changes}\n\n")
            
            f.write("## 📋 Resumen por Tipo de Cambio\n\n")
            for change_type, count in report.changes_by_type.items():
                f.write(f"- **{change_type.title()}:** {count} cambios\n")
            
            f.write("\n## 🔍 Reglas de Normalización Detectadas\n\n")
            
            for rule in report.suggested_rules:
                approval_status = "⚠️ **REQUIERE APROBACIÓN**" if rule.requires_approval else "✅ Automática"
                f.write(f"### {rule.rule_id}: {rule.description}\n")
                f.write(f"- **Tipo:** {rule.rule_type}\n")
                f.write(f"- **Confianza:** {rule.confidence:.0%}\n")
                f.write(f"- **Impacto:** {rule.impact_level}\n")
                f.write(f"- **Estado:** {approval_status}\n")
                f.write(f"- **Ejemplos:**\n")
                for example in rule.examples:
                    f.write(f"  - {example}\n")
                f.write("\n")
            
            f.write("## ✅ Opciones de Aprobación\n\n")
            f.write("Para cada regla que requiere aprobación, marque su decisión:\n\n")
            
            approval_rules = [r for r in report.suggested_rules if r.requires_approval]
            for rule in approval_rules:
                f.write(f"### {rule.rule_id}: {rule.description}\n")
                f.write("- [ ] ✅ **APROBAR** - Aplicar esta regla de normalización\n")
                f.write("- [ ] ❌ **RECHAZAR** - No aplicar esta regla\n")
                f.write("- [ ] 🔧 **MODIFICAR** - Aplicar con modificaciones (especificar abajo)\n\n")
                f.write("**Comentarios:**\n")
                f.write("```\n[Espacio para comentarios de la ingeniera]\n```\n\n")
            
            f.write("## 👩‍🔬 Sección de Aprobación\n\n")
            f.write("**Ingeniera Responsable:**\n")
            f.write("- Nombre: ________________________\n")
            f.write("- Correo: ________________________\n")
            f.write("- Firma: ________________________\n")
            f.write("- Fecha: ________________________\n\n")
            
            f.write("**Decisión Final:**\n")
            f.write("- [ ] Aprobar todas las reglas automáticas\n")
            f.write("- [ ] Aprobar solo las reglas seleccionadas arriba\n")
            f.write("- [ ] Rechazar todo el análisis\n\n")
            
            f.write("## 📁 Archivos Relacionados\n\n")
            f.write(f"- **Análisis detallado (JSON):** `analisis_cambios_{report.timestamp.strftime('%Y%m%d_%H%M%S')}.json`\n")
            f.write(f"- **SQL propuesto:** `propuesta_audit_trail_{report.timestamp.strftime('%Y%m%d_%H%M%S')}.sql`\n")
            f.write(f"- **Resumen ejecutivo:** `resumen_ejecutivo_{report.timestamp.strftime('%Y%m%d_%H%M%S')}.md`\n")
            f.write(f"- **📋 Lista por instrumento:** `cambios_por_instrumento_{report.timestamp.strftime('%Y%m%d_%H%M%S')}.md`\n")
            f.write(f"\n> 💡 **Recomendación:** Comience revisando el archivo 'Lista por instrumento' para ver los cambios organizados por código de equipo.\n")
    
    def _generate_executive_summary(self, report: AnalysisReport, summary_file: Path):
        """Genera resumen ejecutivo para las ingenieras"""
        with open(summary_file, 'w', encoding='utf-8') as f:
            f.write("# Resumen Ejecutivo - Análisis de Normalización CSV\n\n")
            f.write(f"**Fecha:** {report.timestamp.strftime('%d de %B de %Y')}\n")
            f.write(f"**ID de Análisis:** {report.analysis_id}\n\n")
            
            f.write("## 🎯 Objetivo\n")
            f.write("Identificar y documentar todos los cambios automáticos realizados durante la normalización ")
            f.write("de archivos CSV originales para garantizar la trazabilidad y cumplimiento de audit trail.\n\n")
            
            f.write("## 📊 Resultados Clave\n")
            f.write(f"- **{report.total_changes}** cambios detectados en total\n")
            f.write(f"- **{len(report.files_analyzed)}** archivos analizados\n")
            f.write(f"- **{len([r for r in report.suggested_rules if r.requires_approval])}** reglas requieren aprobación manual\n")
            f.write(f"- **{len([r for r in report.suggested_rules if not r.requires_approval])}** reglas automáticas\n\n")
            
            if report.requires_approval:
                f.write("## ⚠️ Acción Requerida\n")
                f.write("Este análisis incluye cambios que **REQUIEREN APROBACIÓN MANUAL** de una ingeniera ")
                f.write("antes de proceder con la normalización.\n\n")
                f.write("**Siguiente paso:** Revisar el archivo README_cambios para aprobar o rechazar cada regla.\n\n")
            
            f.write("## 📈 Distribución de Cambios\n")
            for change_type, count in report.changes_by_type.items():
                percentage = (count / report.total_changes * 100) if report.total_changes > 0 else 0
                f.write(f"- **{change_type.title()}:** {count} cambios ({percentage:.1f}%)\n")
            
            f.write("\n## 🔄 Próximos Pasos\n")
            f.write("1. **Revisión:** Una ingeniera debe revisar el README_cambios\n")
            f.write("2. **Aprobación:** Marcar las reglas aprobadas/rechazadas\n")
            f.write("3. **Ejecución:** Ejecutar el script de aplicación de cambios\n")
            f.write("4. **Audit Trail:** Los cambios se registrarán automáticamente\n")
    
    def _sql_escape(self, value: str) -> str:
        """Escapa valor para SQL"""
        if value is None:
            return "NULL"
        escaped = value.replace("'", "''")
        return f"'{escaped}'"
    
    def _generate_instrument_report(self, report: AnalysisReport, output_file: Path):
        """Genera reporte detallado de cambios por código de instrumento"""
        # Agrupar cambios por código de instrumento
        changes_by_instrument = {}
        
        for change in report.detected_changes:
            instrument_code = change.instrument_code
            if instrument_code not in changes_by_instrument:
                changes_by_instrument[instrument_code] = []
            changes_by_instrument[instrument_code].append(change)
        
        with open(output_file, 'w', encoding='utf-8') as f:
            f.write("# 📋 Lista de Cambios por Código de Instrumento\n\n")
            f.write(f"**Fecha de análisis:** {report.timestamp.strftime('%Y-%m-%d %H:%M:%S')}\n\n")
            f.write(f"**Total de instrumentos afectados:** {len(changes_by_instrument)}\n")
            f.write(f"**Total de cambios detectados:** {report.total_changes}\n\n")
            
            f.write("---\n\n")
            
            # Estadísticas rápidas
            f.write("## 📊 Resumen por Tipo de Cambio\n\n")
            type_counts = {}
            for change in report.detected_changes:
                change_type = change.change_rule.rule_type
                type_counts[change_type] = type_counts.get(change_type, 0) + 1
            
            for change_type, count in type_counts.items():
                f.write(f"- **{change_type.title()}:** {count} cambios\n")
            
            f.write("\n---\n\n")
            
            # Lista detallada por instrumento
            f.write("## 🔧 Cambios por Instrumento\n\n")
            f.write("*Haga clic en cada instrumento para ver los detalles de los cambios:*\n\n")
            
            # Ordenar por código de instrumento
            sorted_instruments = sorted(changes_by_instrument.keys())
            
            for instrument_code in sorted_instruments:
                changes = changes_by_instrument[instrument_code]
                
                f.write(f"### 🔩 **{instrument_code}**\n\n")
                f.write(f"**Cambios detectados:** {len(changes)}\n\n")
                
                # Agrupar por archivo
                files_in_instrument = {}
                for change in changes:
                    file_name = change.file_name
                    if file_name not in files_in_instrument:
                        files_in_instrument[file_name] = []
                    files_in_instrument[file_name].append(change)
                
                for file_name, file_changes in files_in_instrument.items():
                    f.write(f"**Archivo:** `{file_name}`\n\n")
                    
                    # Lista de cambios en tabla
                    f.write("| Celda | Campo | Valor Original | Valor Corregido | Tipo |\n")
                    f.write("|-------|-------|----------------|------------------|------|\n")
                    
                    for change in file_changes:
                        f.write(f"| {change.cell_reference} | {change.column_name} | ")
                        f.write(f"`{change.original_value}` | `{change.normalized_value}` | ")
                        f.write(f"{change.change_rule.rule_type} |\n")
                    
                    f.write("\n")
                
                # Detalles adicionales
                f.write("**Tipos de cambios en este instrumento:**\n")
                instrument_types = {}
                for change in changes:
                    rule_type = change.change_rule.rule_type
                    if rule_type not in instrument_types:
                        instrument_types[rule_type] = []
                    instrument_types[rule_type].append(change)
                
                for rule_type, type_changes in instrument_types.items():
                    f.write(f"- **{rule_type.title()}:** {len(type_changes)} cambio(s)\n")
                    examples = type_changes[:3]  # Mostrar hasta 3 ejemplos
                    for example in examples:
                        f.write(f"  - `{example.original_value}` → `{example.normalized_value}`\n")
                    if len(type_changes) > 3:
                        f.write(f"  - ... y {len(type_changes) - 3} más\n")
                
                f.write("\n---\n\n")
            
            # Resumen al final
            f.write("## 📋 Resumen de Aprobación por Instrumento\n\n")
            f.write("*Para uso de las ingenieras de validación:*\n\n")
            f.write("| Código Instrumento | Cambios | Estado | Aprobado por | Fecha |\n")
            f.write("|--------------------|---------|---------|--------------|-------|\n")
            
            for instrument_code in sorted_instruments:
                changes_count = len(changes_by_instrument[instrument_code])
                f.write(f"| {instrument_code} | {changes_count} | ⏳ Pendiente | | |\n")
            
            f.write("\n")
            f.write("**Estados posibles:**\n")
            f.write("- ⏳ **Pendiente:** Esperando revisión\n")
            f.write("- ✅ **Aprobado:** Cambios revisados y aprobados\n")
            f.write("- ❌ **Rechazado:** Cambios necesitan revisión\n")
            f.write("- 🔄 **En revisión:** Bajo evaluación\n\n")
            
            # Instrucciones finales
            f.write("---\n\n")
            f.write("## 📝 Instrucciones para Ingenieras\n\n")
            f.write("1. **Revisar cada instrumento** individualmente usando su código\n")
            f.write("2. **Verificar cambios** contra documentación técnica\n")
            f.write("3. **Actualizar estado** en la tabla de resumen\n")
            f.write("4. **Documentar aprobación** con firma y fecha\n")
            f.write("5. **Ejecutar cambios aprobados** usando el script de aplicación\n\n")
            
            f.write("*Archivo generado automáticamente por el Sistema de Normalización CSV*\n")

def main():
    """Función principal"""
    print("🔍 Iniciando análisis de cambios en normalización de CSV...")
    print("=" * 60)
    
    analyzer = CSVNormalizationAnalyzer()
    report = analyzer.analyze_all_csv_files()
    
    print(f"\n📊 Análisis completado:")
    print(f"   • Archivos analizados: {len(report.files_analyzed)}")
    print(f"   • Cambios detectados: {report.total_changes}")
    print(f"   • Requiere aprobación: {'Sí' if report.requires_approval else 'No'}")
    
    analyzer.generate_analysis_files(report)
    
    if report.requires_approval:
        print("\n⚠️  IMPORTANTE: Este análisis contiene cambios que requieren aprobación manual.")
        print("   Por favor, revise el archivo README generado y complete la sección de aprobación.")
    else:
        print("\n✅ Todos los cambios detectados pueden aplicarse automáticamente.")
    
    print("\n🎯 Siguiente paso: Ejecutar 'csv_normalization_applicator.py' después de la aprobación.")

if __name__ == "__main__":
    main()