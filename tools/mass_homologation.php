<?php
/**
 * Herramienta de Homologación Masiva SBL
 * Aplica correcciones de homologación a múltiples archivos HTML
 */

class MassHomologationTool {
    
    private $baseDir;
    private $processed = 0;
    private $errors = 0;
    
    public function __construct($baseDir) {
        $this->baseDir = $baseDir;
    }
    
    public function processAll() {
        echo "=== HERRAMIENTA DE HOMOLOGACIÓN MASIVA ===\n";
        echo "Base Directory: {$this->baseDir}\n";
        echo "Iniciando procesamiento...\n\n";
        
        // Obtener lista de archivos HTML prioritarios
        $priorityFiles = [
            'public/admin_dashboard.html',
            'public/apps/internal/sidebar.html',
            'public/apps/internal/topbar.html',
            'public/apps/service/sidebar.html',
            'public/apps/service/topbar.html',
            'public/apps/tenant/sidebar.html',
            'public/apps/tenant/topbar.html',
        ];
        
        // Procesar archivos prioritarios primero
        foreach ($priorityFiles as $file) {
            $fullPath = $this->baseDir . '/' . $file;
            if (file_exists($fullPath)) {
                $this->processFile($fullPath, $file, true);
            }
        }
        
        // Procesar otros archivos HTML
        $this->scanAndProcess($this->baseDir . '/public');
        
        echo "\n=== RESUMEN DE PROCESAMIENTO ===\n";
        echo "Archivos procesados: {$this->processed}\n";
        echo "Errores encontrados: {$this->errors}\n";
        echo "Homologación masiva completada.\n";
    }
    
    private function scanAndProcess($dir) {
        if (!is_dir($dir)) return;
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir)
        );
        
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'html') {
                $relativePath = str_replace($this->baseDir . '/', '', $file->getPathname());
                
                // Saltar archivos ya procesados
                if (!strpos($relativePath, 'admin_dashboard.html') && 
                    !strpos($relativePath, 'sidebar.html') && 
                    !strpos($relativePath, 'topbar.html')) {
                    $this->processFile($file->getPathname(), $relativePath, false);
                }
            }
        }
    }
    
    private function processFile($filePath, $relativePath, $isPriority = false) {
        try {
            $content = file_get_contents($filePath);
            $originalContent = $content;
            $modified = false;
            
            echo ($isPriority ? "🔥 " : "📄 ") . "Procesando: $relativePath\n";
            
            // 1. Agregar master-theme.css si no existe
            if (!strpos($content, 'master-theme.css')) {
                $content = str_replace(
                    '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">',
                    '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">' . "\n" .
                    '    <link rel="stylesheet" href="assets/styles/master-theme.css">',
                    $content
                );
                $modified = true;
                echo "   ✅ Master theme CSS añadido\n";
            }
            
            // 2. Corregir colores deprecados
            $deprecatedColors = [
                '#667eea' => 'var(--sbl-accent)',
                '#764ba2' => 'var(--sbl-secondary)',
                '#1a1a2e' => 'var(--sbl-primary)',
                '#16213e' => 'var(--sbl-secondary)'
            ];
            
            foreach ($deprecatedColors as $old => $new) {
                if (strpos($content, $old) !== false) {
                    $content = str_replace($old, $new, $content);
                    $modified = true;
                    echo "   🎨 Color $old corregido a $new\n";
                }
            }
            
            // 3. Corregir ancho del sidebar
            if (strpos($content, 'width: 250px') !== false) {
                $content = str_replace('width: 250px', 'width: 200px', $content);
                $modified = true;
                echo "   📏 Ancho sidebar corregido a 200px\n";
            }
            
            // 4. Agregar responsive design básico si no existe
            if (!strpos($content, '@media') && strpos($content, '</style>')) {
                $responsiveCSS = "\n        /* Responsive Design SBL */\n" .
                               "        @media (max-width: 600px) {\n" .
                               "            .sidebar { width: 100%; height: auto; position: relative; }\n" .
                               "            .main-content { margin-left: 0; width: 100%; }\n" .
                               "            .dashboard-grid { grid-template-columns: 1fr; }\n" .
                               "        }\n" .
                               "        @media (min-width: 601px) and (max-width: 900px) {\n" .
                               "            .sidebar { width: 200px; }\n" .
                               "            .main-content { margin-left: 200px; }\n" .
                               "            .dashboard-grid { grid-template-columns: repeat(2, 1fr); }\n" .
                               "        }\n";
                
                $content = str_replace('    </style>', $responsiveCSS . '    </style>', $content);
                $modified = true;
                echo "   📱 Responsive design añadido\n";
            }
            
            // 5. Verificar estructura base URL script
            if (!strpos($content, 'BASE_URL') && strpos($content, '<head>')) {
                $baseUrlScript = "\n    <script>\n" .
                               "        (function() {\n" .
                               "            var marker = '/public/';\n" .
                               "            var path = window.location.pathname || '';\n" .
                               "            var index = path.indexOf(marker);\n" .
                               "            var base = index !== -1 ? path.slice(0, index + marker.length) : '/';\n" .
                               "            if (!base.endsWith('/')) {\n" .
                               "                base += '/';\n" .
                               "            }\n" .
                               "            window.BASE_URL = base === '/' ? '' : base.replace(/\$/, '');\n" .
                               "            document.write('<base href=\"' + base + '\">');\n" .
                               "        })();\n" .
                               "    </script>\n";
                
                $content = str_replace('<head>', '<head>' . $baseUrlScript, $content);
                $modified = true;
                echo "   🔗 Base URL script añadido\n";
            }
            
            // Guardar cambios si hubo modificaciones
            if ($modified && $content !== $originalContent) {
                file_put_contents($filePath, $content);
                echo "   💾 Archivo actualizado exitosamente\n";
                $this->processed++;
            } else if (!$modified) {
                echo "   ℹ️  Sin cambios necesarios\n";
            }
            
        } catch (Exception $e) {
            echo "   ❌ Error procesando $relativePath: " . $e->getMessage() . "\n";
            $this->errors++;
        }
        
        echo "\n";
    }
}

// Ejecutar herramienta
$baseDir = dirname(__DIR__); // Subir un nivel desde tools/
$tool = new MassHomologationTool($baseDir);
$tool->processAll();
?>