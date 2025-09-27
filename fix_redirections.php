<?php

/**
 * Script de Corrección de Rutas y Redirecciones
 * Corrige automáticamente las rutas de archivos HTML y JS para que funcionen
 * con la configuración actual de XAMPP (documento root = SBL_SISTEMA_INTERNO)
 * 
 * @version 1.0
 * @author Sistema SBL
 * @date 2025-09-26
 */

class PathRedirectionFixer
{
    private $baseDir;
    private $corrections = [];
    private $logFile;

    public function __construct()
    {
        $this->baseDir = __DIR__;
        $this->logFile = $this->baseDir . '/storage/logs/path_corrections.log';
        $this->ensureLogDirectory();
    }

    /**
     * Ejecutar corrección completa de rutas
     */
    public function fixAllPaths(): array
    {
        $this->log('INFO', 'Iniciando corrección de rutas y redirecciones');
        
        $results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'files_processed' => 0,
            'corrections_made' => 0,
            'errors' => [],
            'corrections' => []
        ];

        try {
            // Corregir archivos HTML
            $htmlResults = $this->fixHTMLFiles();
            $results['files_processed'] += $htmlResults['files_processed'];
            $results['corrections_made'] += $htmlResults['corrections_made'];
            $results['corrections'] = array_merge($results['corrections'], $htmlResults['corrections']);

            // Corregir archivos JavaScript
            $jsResults = $this->fixJavaScriptFiles();
            $results['files_processed'] += $jsResults['files_processed'];
            $results['corrections_made'] += $jsResults['corrections_made'];
            $results['corrections'] = array_merge($results['corrections'], $jsResults['corrections']);

            // Corregir archivos PHP
            $phpResults = $this->fixPHPFiles();
            $results['files_processed'] += $phpResults['files_processed'];
            $results['corrections_made'] += $phpResults['corrections_made'];
            $results['corrections'] = array_merge($results['corrections'], $phpResults['corrections']);

            $this->log('INFO', 'Corrección de rutas completada', [
                'files_processed' => $results['files_processed'],
                'corrections_made' => $results['corrections_made']
            ]);

        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
            $this->log('ERROR', 'Error durante corrección de rutas', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Corregir archivos HTML
     */
    private function fixHTMLFiles(): array
    {
        $results = ['files_processed' => 0, 'corrections_made' => 0, 'corrections' => []];
        
        // Buscar archivos HTML en sistema-interno
        $htmlFiles = $this->findFiles('sistema-interno/public/apps/internal', '*.html');
        
        foreach ($htmlFiles as $file) {
            $relativePath = str_replace($this->baseDir . '/', '', $file);
            $content = file_get_contents($file);
            $originalContent = $content;
            
            // Correcciones específicas para HTML
            $corrections = [
                // Assets
                'href="assets/styles/' => 'href="/sistema-interno/public/assets/styles/',
                'src="assets/js/' => 'src="/sistema-interno/public/assets/js/',
                'href="assets/images/' => 'href="/sistema-interno/public/assets/images/',
                
                // Scripts de configuración
                "var marker = '/public/';" => "var marker = '/sistema-interno/public/';",
                
                // APIs
                'href="/public/api/' => 'href="/public/api/',
                'src="/public/api/' => 'src="/public/api/',
                
                // Backend calls
                'url: "backend/' => 'url: "/backend/',
                'action="backend/' => 'action="/backend/',
                
                // Rutas relativas problemáticas
                '../../../backend/' => '/backend/',
                '../../backend/' => '/backend/',
                '../backend/' => '/backend/',
                
                // Corrección de base href
                "document.write('<base href=\"' + base + '\">');" => "// Base URL corregido para configuración actual\n            window.BASE_URL = '/sistema-interno/public/';\n            document.write('<base href=\"/sistema-interno/public/\">');"
            ];
            
            $fileCorrectionCount = 0;
            foreach ($corrections as $search => $replace) {
                if (strpos($content, $search) !== false) {
                    $content = str_replace($search, $replace, $content);
                    $fileCorrectionCount++;
                    $results['corrections'][] = [
                        'file' => $relativePath,
                        'type' => 'HTML',
                        'search' => $search,
                        'replace' => $replace
                    ];
                }
            }
            
            // Guardar si hubo cambios
            if ($content !== $originalContent) {
                file_put_contents($file, $content);
                $results['corrections_made'] += $fileCorrectionCount;
                $this->log('INFO', "Archivo HTML corregido: $relativePath", ['corrections' => $fileCorrectionCount]);
            }
            
            $results['files_processed']++;
        }
        
        return $results;
    }

    /**
     * Corregir archivos JavaScript
     */
    private function fixJavaScriptFiles(): array
    {
        $results = ['files_processed' => 0, 'corrections_made' => 0, 'corrections' => []];
        
        // Buscar archivos JS
        $jsFiles = array_merge(
            $this->findFiles('sistema-interno/public/assets/js', '*.js'),
            $this->findFiles('public/assets/js', '*.js')
        );
        
        foreach ($jsFiles as $file) {
            $relativePath = str_replace($this->baseDir . '/', '', $file);
            $content = file_get_contents($file);
            $originalContent = $content;
            
            // Correcciones específicas para JavaScript
            $corrections = [
                // API calls
                "url: 'backend/" => "url: '/backend/",
                'url: "backend/' => 'url: "/backend/',
                "fetch('backend/" => "fetch('/backend/",
                'fetch("backend/' => 'fetch("/backend/',
                
                // Asset paths
                "'/assets/" => "'/sistema-interno/public/assets/",
                '"/assets/' => '"/sistema-interno/public/assets/',
                
                // API endpoints
                "'/api/" => "'/public/api/",
                '"/api/' => '"/public/api/'
            ];
            
            $fileCorrectionCount = 0;
            foreach ($corrections as $search => $replace) {
                if (strpos($content, $search) !== false) {
                    $content = str_replace($search, $replace, $content);
                    $fileCorrectionCount++;
                    $results['corrections'][] = [
                        'file' => $relativePath,
                        'type' => 'JavaScript',
                        'search' => $search,
                        'replace' => $replace
                    ];
                }
            }
            
            if ($content !== $originalContent) {
                file_put_contents($file, $content);
                $results['corrections_made'] += $fileCorrectionCount;
                $this->log('INFO', "Archivo JS corregido: $relativePath", ['corrections' => $fileCorrectionCount]);
            }
            
            $results['files_processed']++;
        }
        
        return $results;
    }

    /**
     * Corregir archivos PHP
     */
    private function fixPHPFiles(): array
    {
        $results = ['files_processed' => 0, 'corrections_made' => 0, 'corrections' => []];
        
        // Buscar archivos PHP críticos
        $phpFiles = array_merge(
            $this->findFiles('backend', '*.php'),
            [$this->baseDir . '/index.php'],
            [$this->baseDir . '/test.php']
        );
        
        foreach ($phpFiles as $file) {
            if (!file_exists($file)) continue;
            
            $relativePath = str_replace($this->baseDir . '/', '', $file);
            $content = file_get_contents($file);
            $originalContent = $content;
            
            // Correcciones específicas para PHP
            $corrections = [
                // Includes y requires con rutas relativas problemáticas
                "require_once '../" => "require_once __DIR__ . '/../",
                "include_once '../" => "include_once __DIR__ . '/../",
                "require '../" => "require __DIR__ . '/../",
                "include '../" => "include __DIR__ . '/../",
                
                // Redirecciones HTTP
                "Location: public/" => "Location: /public/",
                "Location: sistema-interno/" => "Location: /sistema-interno/",
                "Location: backend/" => "Location: /backend/"
            ];
            
            $fileCorrectionCount = 0;
            foreach ($corrections as $search => $replace) {
                if (strpos($content, $search) !== false) {
                    $content = str_replace($search, $replace, $content);
                    $fileCorrectionCount++;
                    $results['corrections'][] = [
                        'file' => $relativePath,
                        'type' => 'PHP',
                        'search' => $search,
                        'replace' => $replace
                    ];
                }
            }
            
            if ($content !== $originalContent) {
                file_put_contents($file, $content);
                $results['corrections_made'] += $fileCorrectionCount;
                $this->log('INFO', "Archivo PHP corregido: $relativePath", ['corrections' => $fileCorrectionCount]);
            }
            
            $results['files_processed']++;
        }
        
        return $results;
    }

    /**
     * Buscar archivos con patrón
     */
    private function findFiles(string $directory, string $pattern): array
    {
        $fullPath = $this->baseDir . '/' . $directory;
        if (!is_dir($fullPath)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullPath)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && fnmatch($pattern, $file->getFilename())) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Verificar rutas existentes
     */
    public function verifyPaths(): array
    {
        $verification = [
            'timestamp' => date('Y-m-d H:i:s'),
            'assets_check' => [],
            'api_check' => [],
            'backend_check' => [],
            'recommendations' => []
        ];

        // Verificar assets
        $assetPaths = [
            'CSS Sistema Interno' => 'sistema-interno/public/assets/styles/internal.css',
            'CSS Master Theme' => 'public/assets/styles/master-theme.css',
            'JS Assets' => 'sistema-interno/public/assets/js'
        ];

        foreach ($assetPaths as $name => $path) {
            $fullPath = $this->baseDir . '/' . $path;
            $verification['assets_check'][$name] = [
                'path' => $path,
                'exists' => file_exists($fullPath) || is_dir($fullPath),
                'full_path' => $fullPath
            ];
        }

        // Verificar APIs
        $apiPaths = [
            'API Usuarios' => 'public/api/usuarios.php',
            'API Calibraciones' => 'public/api/calibraciones.php',
            'API Proveedores' => 'public/api/proveedores.php'
        ];

        foreach ($apiPaths as $name => $path) {
            $fullPath = $this->baseDir . '/' . $path;
            $verification['api_check'][$name] = [
                'path' => $path,
                'exists' => file_exists($fullPath),
                'url' => "http://localhost/$path"
            ];
        }

        // Verificar backend
        $backendPaths = [
            'Backend Instrumentos' => 'backend/instrumentos',
            'Core Sistema' => 'app/Core'
        ];

        foreach ($backendPaths as $name => $path) {
            $fullPath = $this->baseDir . '/' . $path;
            $verification['backend_check'][$name] = [
                'path' => $path,
                'exists' => is_dir($fullPath)
            ];
        }

        // Generar recomendaciones
        $verification['recommendations'] = $this->generateRecommendations($verification);

        return $verification;
    }

    /**
     * Generar recomendaciones basadas en verificación
     */
    private function generateRecommendations(array $verification): array
    {
        $recommendations = [];

        // Verificar assets faltantes
        foreach ($verification['assets_check'] as $name => $check) {
            if (!$check['exists']) {
                $recommendations[] = [
                    'type' => 'missing_asset',
                    'message' => "Archivo/directorio faltante: $name",
                    'action' => "Crear o verificar ruta: {$check['path']}"
                ];
            }
        }

        // Verificar APIs
        foreach ($verification['api_check'] as $name => $check) {
            if (!$check['exists']) {
                $recommendations[] = [
                    'type' => 'missing_api',
                    'message' => "API faltante: $name",
                    'action' => "Verificar archivo: {$check['path']}"
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Crear archivo de configuración de rutas
     */
    public function createPathConfiguration(): void
    {
        $config = [
            'base_url' => '/',
            'sistema_interno' => '/sistema-interno/public/',
            'assets' => '/sistema-interno/public/assets/',
            'api' => '/public/api/',
            'backend' => '/backend/',
            'uploads' => '/storage/uploads/',
            'created_at' => date('Y-m-d H:i:s'),
            'xampp_config' => [
                'document_root' => 'SBL_SISTEMA_INTERNO',
                'base_path' => '/',
                'note' => 'XAMPP configurado con document root en SBL_SISTEMA_INTERNO'
            ]
        ];

        $configFile = $this->baseDir . '/app/Core/path_config.json';
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        $this->log('INFO', 'Archivo de configuración de rutas creado', ['file' => $configFile]);
    }

    /**
     * Logging
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        
        $logEntry = "[$timestamp] [$level] $message";
        if ($contextStr) {
            $logEntry .= " Context: $contextStr";
        }
        $logEntry .= PHP_EOL;

        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Asegurar directorio de logs
     */
    private function ensureLogDirectory(): void
    {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
}

// Ejecutar si se llama directamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $fixer = new PathRedirectionFixer();
    
    echo "<h2>🔧 Corrección de Rutas y Redirecciones - Sistema SBL</h2>";
    
    // Verificar rutas actuales
    echo "<h3>📋 Verificación de Rutas Actuales</h3>";
    $verification = $fixer->verifyPaths();
    echo "<pre>" . json_encode($verification, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</pre>";
    
    // Ejecutar correcciones
    echo "<h3>🛠️ Ejecutando Correcciones</h3>";
    $results = $fixer->fixAllPaths();
    echo "<pre>" . json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</pre>";
    
    // Crear configuración
    echo "<h3>⚙️ Creando Configuración de Rutas</h3>";
    $fixer->createPathConfiguration();
    echo "<p>✅ Archivo de configuración creado en app/Core/path_config.json</p>";
    
    echo "<h3>🎯 Resumen</h3>";
    echo "<ul>";
    echo "<li>Archivos procesados: {$results['files_processed']}</li>";
    echo "<li>Correcciones realizadas: {$results['corrections_made']}</li>";
    echo "<li>Errores: " . count($results['errors']) . "</li>";
    echo "</ul>";
    
    if (!empty($results['errors'])) {
        echo "<h4>❌ Errores encontrados:</h4>";
        echo "<ul>";
        foreach ($results['errors'] as $error) {
            echo "<li>$error</li>";
        }
        echo "</ul>";
    }
    
    echo "<h3>🔗 URLs Actualizadas</h3>";
    echo "<ul>";
    echo "<li><strong>Sistema Interno:</strong> <a href='/sistema-interno/public/apps/internal/instrumentos/list_gages.html' target='_blank'>Lista de Instrumentos</a></li>";
    echo "<li><strong>APIs:</strong> <a href='/public/api/usuarios.php' target='_blank'>API Usuarios</a></li>";
    echo "<li><strong>Monitoreo:</strong> <a href='/health_dashboard.php' target='_blank'>Dashboard de Salud</a></li>";
    echo "</ul>";
}
?>