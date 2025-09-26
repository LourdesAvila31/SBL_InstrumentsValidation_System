/**
 * Developer Panel JavaScript
 * SBL Sistema Interno - Developer Portal
 */

class DeveloperPanel {
    constructor() {
        this.currentSection = 'dashboard';
        this.charts = {};
        this.realTimeInterval = null;
        this.terminalHistory = [];
        this.terminalHistoryIndex = -1;
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.initializeCharts();
        this.startRealTimeUpdates();
        this.setupKeyboardShortcuts();
        
        console.log('Developer Panel initialized');
    }

    setupEventListeners() {
        // Navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => this.handleNavigation(e));
        });

        // Mobile sidebar toggle
        const toggleButton = document.querySelector('.navbar-toggler');
        if (toggleButton) {
            toggleButton.addEventListener('click', () => this.toggleSidebar());
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => this.handleOutsideClick(e));

        // Window resize handler
        window.addEventListener('resize', () => this.handleResize());

        // Terminal input
        const terminalInput = document.getElementById('terminal-input');
        if (terminalInput) {
            terminalInput.addEventListener('keydown', (e) => this.handleTerminalKeydown(e));
        }

        // Refresh dashboard button
        const refreshBtn = document.querySelector('[onclick="refreshDashboard()"]');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.refreshDashboard());
        }
    }

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + R to refresh
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                this.refreshDashboard();
            }

            // Ctrl/Cmd + T to open terminal
            if ((e.ctrlKey || e.metaKey) && e.key === 't') {
                e.preventDefault();
                this.showSection('terminal');
            }

            // Ctrl/Cmd + D to go to dashboard
            if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
                e.preventDefault();
                this.showSection('dashboard');
            }

            // Escape to close mobile sidebar
            if (e.key === 'Escape') {
                this.closeSidebar();
            }
        });
    }

    handleNavigation(e) {
        e.preventDefault();
        const link = e.currentTarget;
        const href = link.getAttribute('href') || link.getAttribute('onclick');
        
        if (href && href.includes('showSection')) {
            const sectionMatch = href.match(/showSection\('([^']+)'\)/);
            if (sectionMatch) {
                this.showSection(sectionMatch[1]);
            }
        }

        // Update active state
        document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
        link.classList.add('active');

        // Close mobile sidebar
        this.closeSidebar();
    }

    showSection(sectionName) {
        this.currentSection = sectionName;
        
        const dashboard = document.getElementById('dashboard');
        const dynamicContent = document.getElementById('dynamic-content');

        if (sectionName === 'dashboard') {
            dashboard.classList.remove('d-none');
            dynamicContent.classList.add('d-none');
            this.refreshCharts();
            return;
        }

        dashboard.classList.add('d-none');
        dynamicContent.classList.remove('d-none');
        
        this.loadSectionContent(sectionName);
    }

    loadSectionContent(sectionName) {
        const content = document.getElementById('dynamic-content');
        
        // Show loading
        content.innerHTML = this.getLoadingHTML(sectionName);

        // Simulate API call delay
        setTimeout(() => {
            content.innerHTML = this.getSectionHTML(sectionName);
            this.initializeSectionFeatures(sectionName);
        }, Math.random() * 500 + 300);
    }

    getLoadingHTML(sectionName) {
        return `
            <div class="text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <h5>Cargando ${this.getSectionTitle(sectionName)}...</h5>
                <p class="text-muted">Obteniendo datos del servidor...</p>
            </div>
        `;
    }

    getSectionTitle(sectionName) {
        const titles = {
            'system-info': 'Información del Sistema',
            'database': 'Base de Datos',
            'api-docs': 'Documentación de APIs',
            'logs': 'Logs del Sistema',
            'code-editor': 'Editor de Código',
            'performance': 'Monitoreo de Performance',
            'security': 'Panel de Seguridad',
            'tools': 'Herramientas de Desarrollo',
            'settings': 'Configuración del Sistema',
            'terminal': 'Terminal'
        };
        return titles[sectionName] || sectionName;
    }

    getSectionHTML(sectionName) {
        const templates = {
            'system-info': this.getSystemInfoHTML(),
            'database': this.getDatabaseHTML(),
            'api-docs': this.getApiDocsHTML(),
            'logs': this.getLogsHTML(),
            'code-editor': this.getCodeEditorHTML(),
            'performance': this.getPerformanceHTML(),
            'security': this.getSecurityHTML(),
            'tools': this.getToolsHTML(),
            'settings': this.getSettingsHTML(),
            'terminal': this.getTerminalHTML()
        };

        return templates[sectionName] || this.getDefaultSectionHTML(sectionName);
    }

    getSystemInfoHTML() {
        return `
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom" style="border-color: var(--dark-border) !important;">
                <h1 class="h2"><i class="bi bi-info-square text-primary me-2"></i>Información del Sistema</h1>
                <button class="btn btn-outline-primary btn-sm" onclick="window.developerPanel.refreshSystemInfo()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Actualizar
                </button>
            </div>
            
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card card-dark">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-server me-2"></i>Servidor</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-dark table-borderless">
                                <tr><td class="fw-bold">Sistema Operativo:</td><td>Windows Server 2022</td></tr>
                                <tr><td class="fw-bold">Versión PHP:</td><td><span class="badge bg-success">8.2.0</span></td></tr>
                                <tr><td class="fw-bold">MySQL:</td><td><span class="badge bg-success">8.0.35</span></td></tr>
                                <tr><td class="fw-bold">Apache:</td><td><span class="badge bg-success">2.4.58</span></td></tr>
                                <tr><td class="fw-bold">Memoria Total:</td><td>16 GB</td></tr>
                                <tr><td class="fw-bold">Espacio en Disco:</td><td>500 GB SSD</td></tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6 mb-4">
                    <div class="card card-dark">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Aplicación</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-dark table-borderless">
                                <tr><td class="fw-bold">Versión:</td><td><span class="badge bg-primary">2.1.0</span></td></tr>
                                <tr><td class="fw-bold">Última actualización:</td><td>26 Sep 2025</td></tr>
                                <tr><td class="fw-bold">Modo Debug:</td><td><span class="badge bg-warning">Activado</span></td></tr>
                                <tr><td class="fw-bold">Ambiente:</td><td><span class="badge bg-info">Desarrollo</span></td></tr>
                                <tr><td class="fw-bold">Timezone:</td><td>America/Mexico_City</td></tr>
                                <tr><td class="fw-bold">Charset:</td><td>UTF-8</td></tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card card-dark">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Extensiones PHP Cargadas</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-2"><span class="badge bg-success me-1">✓</span> mysqli</div>
                                <div class="col-md-3 mb-2"><span class="badge bg-success me-1">✓</span> pdo_mysql</div>
                                <div class="col-md-3 mb-2"><span class="badge bg-success me-1">✓</span> curl</div>
                                <div class="col-md-3 mb-2"><span class="badge bg-success me-1">✓</span> json</div>
                                <div class="col-md-3 mb-2"><span class="badge bg-success me-1">✓</span> openssl</div>
                                <div class="col-md-3 mb-2"><span class="badge bg-success me-1">✓</span> mbstring</div>
                                <div class="col-md-3 mb-2"><span class="badge bg-success me-1">✓</span> gd</div>
                                <div class="col-md-3 mb-2"><span class="badge bg-success me-1">✓</span> zip</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    getTerminalHTML() {
        return `
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom" style="border-color: var(--dark-border) !important;">
                <h1 class="h2"><i class="bi bi-terminal text-primary me-2"></i>Terminal</h1>
                <div class="btn-group">
                    <button class="btn btn-outline-primary btn-sm" onclick="window.developerPanel.clearTerminal()">
                        <i class="bi bi-x-circle me-1"></i>Limpiar
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="window.developerPanel.toggleTerminalFullscreen()">
                        <i class="bi bi-fullscreen me-1"></i>Pantalla completa
                    </button>
                </div>
            </div>
            
            <div class="card card-dark">
                <div class="card-body p-0">
                    <div class="terminal-output" id="terminal-output">
                        <div style="color: #4a9eff;">███████╗██████╗ ██╗          ████████╗███████╗██████╗ ███╗   ███╗██╗███╗   ██╗ █████╗ ██╗     </div>
                        <div style="color: #4a9eff;">██╔════╝██╔══██╗██║          ╚══██╔══╝██╔════╝██╔══██╗████╗ ████║██║████╗  ██║██╔══██╗██║     </div>
                        <div style="color: #4a9eff;">███████╗██████╔╝██║             ██║   █████╗  ██████╔╝██╔████╔██║██║██╔██╗ ██║███████║██║     </div>
                        <div style="color: #4a9eff;">╚════██║██╔══██╗██║             ██║   ██╔══╝  ██╔══██╗██║╚██╔╝██║██║██║╚██╗██║██╔══██║██║     </div>
                        <div style="color: #4a9eff;">███████║██████╔╝███████╗        ██║   ███████╗██║  ██║██║ ╚═╝ ██║██║██║ ╚████║██║  ██║███████╗</div>
                        <div style="color: #4a9eff;">╚══════╝╚═════╝ ╚══════╝        ╚═╝   ╚══════╝╚═╝  ╚═╝╚═╝     ╚═╝╚═╝╚═╝  ╚═══╝╚═╝  ╚═╝╚══════╝</div>
                        <div style="margin: 20px 0; color: #00ff88;">SBL Developer Terminal v2.1.0</div>
                        <div style="color: #8b949e;">Conectado al servidor de desarrollo</div>
                        <div style="color: #8b949e;">Digite 'help' para ver los comandos disponibles</div>
                        <div style="margin-top: 20px;">
                            <span style="color: #00ff88;">developer@sbl-system</span>:<span style="color: #4a9eff;">~/public/apps/developer</span>$ <span class="cursor">|</span>
                        </div>
                    </div>
                    <div class="p-3" style="background: var(--dark-bg-tertiary); border-top: 1px solid var(--dark-border);">
                        <div class="input-group">
                            <span class="input-group-text" style="background: var(--dark-bg-primary); color: #00ff88; border-color: var(--dark-border); font-family: monospace;">$</span>
                            <input type="text" class="form-control" id="terminal-input" 
                                   style="background: var(--dark-bg-primary); color: var(--dark-text-primary); border-color: var(--dark-border); font-family: monospace;"
                                   placeholder="Ingresa un comando..." autocomplete="off">
                        </div>
                        <small class="text-muted mt-2 d-block">
                            <i class="bi bi-info-circle me-1"></i>
                            Shortcuts: Ctrl+L (limpiar), Ctrl+C (cancelar), ↑↓ (historial)
                        </small>
                    </div>
                </div>
            </div>
        `;
    }

    getDefaultSectionHTML(sectionName) {
        return `
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom" style="border-color: var(--dark-border) !important;">
                <h1 class="h2">${this.getSectionTitle(sectionName)}</h1>
            </div>
            <div class="card card-dark">
                <div class="card-body text-center py-5">
                    <i class="bi bi-tools display-1 text-muted mb-4"></i>
                    <h4>En Desarrollo</h4>
                    <p class="text-muted">Esta sección está siendo desarrollada. Estará disponible pronto.</p>
                    <div class="alert alert-info" role="alert">
                        <i class="bi bi-info-circle me-2"></i>
                        La funcionalidad de <strong>${this.getSectionTitle(sectionName)}</strong> será implementada en la próxima versión.
                    </div>
                    <button class="btn btn-outline-primary mt-3" onclick="window.developerPanel.showSection('dashboard')">
                        <i class="bi bi-arrow-left me-2"></i>Volver al Dashboard
                    </button>
                </div>
            </div>
        `;
    }

    initializeSectionFeatures(sectionName) {
        if (sectionName === 'terminal') {
            this.setupTerminal();
        }
        // Add other section-specific initializations here
    }

    setupTerminal() {
        const terminalInput = document.getElementById('terminal-input');
        if (terminalInput) {
            terminalInput.focus();
            terminalInput.addEventListener('keydown', (e) => this.handleTerminalKeydown(e));
        }
    }

    handleTerminalKeydown(e) {
        const input = e.target;
        
        if (e.key === 'Enter') {
            const command = input.value.trim();
            if (command) {
                this.terminalHistory.push(command);
                this.terminalHistoryIndex = this.terminalHistory.length;
                this.executeTerminalCommand(command);
            }
            input.value = '';
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (this.terminalHistoryIndex > 0) {
                this.terminalHistoryIndex--;
                input.value = this.terminalHistory[this.terminalHistoryIndex];
            }
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (this.terminalHistoryIndex < this.terminalHistory.length - 1) {
                this.terminalHistoryIndex++;
                input.value = this.terminalHistory[this.terminalHistoryIndex];
            } else {
                this.terminalHistoryIndex = this.terminalHistory.length;
                input.value = '';
            }
        } else if (e.ctrlKey && e.key === 'l') {
            e.preventDefault();
            this.clearTerminal();
        } else if (e.ctrlKey && e.key === 'c') {
            e.preventDefault();
            this.addTerminalOutput('^C', 'error');
            input.value = '';
        }
    }

    executeTerminalCommand(command) {
        const output = document.getElementById('terminal-output');
        
        // Add command to output
        this.addTerminalOutput(`<span style="color: #00ff88;">developer@sbl-system</span>:<span style="color: #4a9eff;">~/public/apps/developer</span>$ ${command}`);
        
        // Process command
        this.processCommand(command);
        
        // Add new prompt
        setTimeout(() => {
            this.addTerminalOutput(`<span style="color: #00ff88;">developer@sbl-system</span>:<span style="color: #4a9eff;">~/public/apps/developer</span>$ <span class="cursor">|</span>`);
        }, 100);
    }

    processCommand(command) {
        const args = command.split(' ');
        const cmd = args[0].toLowerCase();

        switch (cmd) {
            case 'help':
                this.addTerminalOutput(`
                    <div style="margin: 10px 0;">
                        <div style="color: #4a9eff; font-weight: bold;">Comandos disponibles:</div>
                        <div style="margin-left: 20px;">
                            <div><span style="color: #00ff88;">help</span> - Mostrar esta ayuda</div>
                            <div><span style="color: #00ff88;">status</span> - Estado del sistema</div>
                            <div><span style="color: #00ff88;">clear</span> - Limpiar terminal</div>
                            <div><span style="color: #00ff88;">php -v</span> - Versión de PHP</div>
                            <div><span style="color: #00ff88;">mysql --version</span> - Versión de MySQL</div>
                            <div><span style="color: #00ff88;">ls</span> - Listar archivos</div>
                            <div><span style="color: #00ff88;">pwd</span> - Mostrar directorio actual</div>
                            <div><span style="color: #00ff88;">whoami</span> - Usuario actual</div>
                            <div><span style="color: #00ff88;">date</span> - Fecha y hora actual</div>
                            <div><span style="color: #00ff88;">uptime</span> - Tiempo de actividad</div>
                        </div>
                    </div>
                `);
                break;

            case 'status':
                this.addTerminalOutput(`
                    <div style="margin: 10px 0; color: #00ff88;">
                        <div>🟢 Sistema: <span style="color: #2ea043;">Online</span></div>
                        <div>🔗 Base de datos: <span style="color: #2ea043;">Conectada</span></div>
                        <div>📡 APIs: <span style="color: #2ea043;">12 activas</span></div>
                        <div>⏱️ Uptime: <span style="color: #2ea043;">99.8%</span></div>
                        <div>👥 Usuarios online: <span style="color: #2ea043;">47</span></div>
                    </div>
                `);
                break;

            case 'clear':
                this.clearTerminal();
                return;

            case 'php':
                if (args[1] === '-v') {
                    this.addTerminalOutput(`<div style="margin: 10px 0;">PHP 8.2.0 (cli) (built: Dec  6 2022 15:31:23) ( NTS Visual C++ 2019 x64 )</div>`);
                } else {
                    this.addTerminalOutput(`<div style="margin: 10px 0; color: #f85149;">php: comando incompleto. Use 'php -v' para ver la versión</div>`);
                }
                break;

            case 'mysql':
                if (args.includes('--version')) {
                    this.addTerminalOutput(`<div style="margin: 10px 0;">mysql  Ver 8.0.35 for Win64 on x86_64 (MySQL Community Server - GPL)</div>`);
                } else {
                    this.addTerminalOutput(`<div style="margin: 10px 0; color: #f85149;">mysql: comando incompleto. Use 'mysql --version' para ver la versión</div>`);
                }
                break;

            case 'ls':
                this.addTerminalOutput(`
                    <div style="margin: 10px 0;">
                        <div><span style="color: #4a9eff;">drwxr-xr-x</span> assets/</div>
                        <div><span style="color: #4a9eff;">drwxr-xr-x</span> sections/</div>
                        <div><span style="color: #e6edf3;">-rw-r--r--</span> index.html</div>
                        <div><span style="color: #e6edf3;">-rw-r--r--</span> README.md</div>
                    </div>
                `);
                break;

            case 'pwd':
                this.addTerminalOutput(`<div style="margin: 10px 0;">/xampp/htdocs/SBL_SISTEMA_INTERNO/public/apps/developer</div>`);
                break;

            case 'whoami':
                this.addTerminalOutput(`<div style="margin: 10px 0;">developer</div>`);
                break;

            case 'date':
                this.addTerminalOutput(`<div style="margin: 10px 0;">${new Date().toLocaleString('es-MX')}</div>`);
                break;

            case 'uptime':
                this.addTerminalOutput(`<div style="margin: 10px 0;">Sistema activo por: 23 días, 14:32, carga promedio: 0.45, 0.52, 0.48</div>`);
                break;

            default:
                if (command) {
                    this.addTerminalOutput(`<div style="margin: 10px 0; color: #f85149;">Comando no encontrado: ${command}</div>`);
                    this.addTerminalOutput(`<div style="color: #8b949e;">Digite 'help' para ver los comandos disponibles</div>`);
                }
                break;
        }
    }

    addTerminalOutput(content, type = 'normal') {
        const output = document.getElementById('terminal-output');
        if (output) {
            const div = document.createElement('div');
            div.innerHTML = content;
            if (type === 'error') {
                div.style.color = '#f85149';
            }
            output.appendChild(div);
            output.scrollTop = output.scrollHeight;
        }
    }

    clearTerminal() {
        const output = document.getElementById('terminal-output');
        if (output) {
            output.innerHTML = `
                <div style="color: #4a9eff;">███████╗██████╗ ██╗          ████████╗███████╗██████╗ ███╗   ███╗██╗███╗   ██╗ █████╗ ██╗     </div>
                <div style="color: #4a9eff;">██╔════╝██╔══██╗██║          ╚══██╔══╝██╔════╝██╔══██╗████╗ ████║██║████╗  ██║██╔══██╗██║     </div>
                <div style="color: #4a9eff;">███████╗██████╔╝██║             ██║   █████╗  ██████╔╝██╔████╔██║██║██╔██╗ ██║███████║██║     </div>
                <div style="color: #4a9eff;">╚════██║██╔══██╗██║             ██║   ██╔══╝  ██╔══██╗██║╚██╔╝██║██║██║╚██╗██║██╔══██║██║     </div>
                <div style="color: #4a9eff;">███████║██████╔╝███████╗        ██║   ███████╗██║  ██║██║ ╚═╝ ██║██║██║ ╚████║██║  ██║███████╗</div>
                <div style="color: #4a9eff;">╚══════╝╚═════╝ ╚══════╝        ╚═╝   ╚══════╝╚═╝  ╚═╝╚═╝     ╚═╝╚═╝╚═╝  ╚═══╝╚═╝  ╚═╝╚══════╝</div>
                <div style="margin: 20px 0; color: #00ff88;">SBL Developer Terminal v2.1.0</div>
                <div style="color: #8b949e;">Terminal limpiado</div>
                <div style="margin-top: 20px;">
                    <span style="color: #00ff88;">developer@sbl-system</span>:<span style="color: #4a9eff;">~/public/apps/developer</span>$ <span class="cursor">|</span>
                </div>
            `;
        }
    }

    initializeCharts() {
        this.initUsageChart();
        this.initApiChart();
    }

    initUsageChart() {
        const ctx = document.getElementById('usageChart');
        if (!ctx) return;

        this.charts.usage = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
                datasets: [{
                    label: 'Requests por día',
                    data: [1200, 1500, 1100, 1800, 1400, 2200, 1900],
                    borderColor: 'var(--accent-blue)',
                    backgroundColor: 'rgba(31, 111, 235, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: 'var(--accent-blue)',
                    pointBorderColor: 'var(--accent-blue)',
                    pointHoverBackgroundColor: '#ffffff',
                    pointHoverBorderColor: 'var(--accent-blue)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        labels: { color: 'var(--dark-text-primary)' }
                    },
                    tooltip: {
                        backgroundColor: 'var(--dark-bg-secondary)',
                        titleColor: 'var(--dark-text-primary)',
                        bodyColor: 'var(--dark-text-primary)',
                        borderColor: 'var(--dark-border)',
                        borderWidth: 1
                    }
                },
                scales: {
                    x: { 
                        ticks: { color: 'var(--dark-text-secondary)' },
                        grid: { color: 'var(--dark-border)' }
                    },
                    y: { 
                        ticks: { color: 'var(--dark-text-secondary)' },
                        grid: { color: 'var(--dark-border)' }
                    }
                }
            }
        });
    }

    initApiChart() {
        const ctx = document.getElementById('apiChart');
        if (!ctx) return;

        this.charts.api = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Calibraciones', 'Usuarios', 'Instrumentos', 'Reportes'],
                datasets: [{
                    data: [35, 25, 20, 20],
                    backgroundColor: [
                        'var(--accent-blue)',
                        'var(--accent-green)',
                        'var(--accent-yellow)',
                        'var(--accent-red)'
                    ],
                    borderWidth: 2,
                    borderColor: 'var(--dark-bg-primary)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        labels: { color: 'var(--dark-text-primary)' },
                        position: 'bottom'
                    },
                    tooltip: {
                        backgroundColor: 'var(--dark-bg-secondary)',
                        titleColor: 'var(--dark-text-primary)',
                        bodyColor: 'var(--dark-text-primary)',
                        borderColor: 'var(--dark-border)',
                        borderWidth: 1
                    }
                }
            }
        });
    }

    startRealTimeUpdates() {
        this.realTimeInterval = setInterval(() => {
            this.updateMetrics();
        }, 5000);
    }

    updateMetrics() {
        // CPU Usage
        const cpuUsage = Math.floor(Math.random() * 30) + 30;
        const cpuElement = document.getElementById('cpu-usage');
        const cpuBar = document.getElementById('cpu-bar');
        if (cpuElement && cpuBar) {
            cpuElement.textContent = cpuUsage + '%';
            cpuBar.style.width = cpuUsage + '%';
        }

        // RAM Usage
        const ramUsage = Math.floor(Math.random() * 40) + 50;
        const ramElement = document.getElementById('ram-usage');
        const ramBar = document.getElementById('ram-bar');
        if (ramElement && ramBar) {
            ramElement.textContent = ramUsage + '%';
            ramBar.style.width = ramUsage + '%';
        }

        // Disk Usage
        const diskUsage = Math.floor(Math.random() * 10) + 20;
        const diskElement = document.getElementById('disk-usage');
        const diskBar = document.getElementById('disk-bar');
        if (diskElement && diskBar) {
            diskElement.textContent = diskUsage + '%';
            diskBar.style.width = diskUsage + '%';
        }

        // Online Users
        const onlineUsers = Math.floor(Math.random() * 20) + 40;
        const usersElement = document.getElementById('online-users');
        if (usersElement) {
            usersElement.textContent = onlineUsers;
        }

        // Update charts data occasionally
        if (Math.random() < 0.1) { // 10% chance every 5 seconds
            this.updateChartData();
        }
    }

    updateChartData() {
        if (this.charts.usage) {
            const newData = this.charts.usage.data.datasets[0].data.slice(1);
            newData.push(Math.floor(Math.random() * 1000) + 1000);
            this.charts.usage.data.datasets[0].data = newData;
            this.charts.usage.update('none');
        }
    }

    refreshCharts() {
        if (this.charts.usage) {
            this.charts.usage.resize();
        }
        if (this.charts.api) {
            this.charts.api.resize();
        }
    }

    refreshDashboard() {
        // Show loading state
        const refreshBtn = document.querySelector('[onclick="refreshDashboard()"], .btn-outline-primary');
        if (refreshBtn) {
            const originalContent = refreshBtn.innerHTML;
            refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Actualizando...';
            refreshBtn.disabled = true;

            setTimeout(() => {
                refreshBtn.innerHTML = originalContent;
                refreshBtn.disabled = false;
                this.updateMetrics();
                this.showNotification('Dashboard actualizado correctamente', 'success');
            }, 1500);
        }
    }

    showNotification(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.remove();
        }, 5000);
    }

    toggleSidebar() {
        const sidebar = document.getElementById('sidebarMenu');
        if (sidebar) {
            sidebar.classList.toggle('show');
        }
    }

    closeSidebar() {
        const sidebar = document.getElementById('sidebarMenu');
        if (sidebar) {
            sidebar.classList.remove('show');
        }
    }

    handleOutsideClick(e) {
        const sidebar = document.getElementById('sidebarMenu');
        const toggleButton = document.querySelector('.navbar-toggler');
        
        if (window.innerWidth <= 768 && 
            sidebar && !sidebar.contains(e.target) && 
            toggleButton && !toggleButton.contains(e.target)) {
            this.closeSidebar();
        }
    }

    handleResize() {
        if (window.innerWidth > 768) {
            this.closeSidebar();
        }
        this.refreshCharts();
    }

    // Public methods for external access
    refreshSystemInfo() {
        this.showNotification('Información del sistema actualizada', 'success');
    }

    toggleTerminalFullscreen() {
        const terminal = document.querySelector('.terminal-output');
        if (terminal) {
            if (terminal.style.maxHeight === '100vh') {
                terminal.style.maxHeight = '400px';
            } else {
                terminal.style.maxHeight = '100vh';
            }
        }
    }

    destroy() {
        if (this.realTimeInterval) {
            clearInterval(this.realTimeInterval);
        }
        
        // Destroy charts
        Object.values(this.charts).forEach(chart => {
            if (chart) {
                chart.destroy();
            }
        });
    }
}

// Initialize the Developer Panel when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.developerPanel = new DeveloperPanel();
});

// Handle page unload
window.addEventListener('beforeunload', function() {
    if (window.developerPanel) {
        window.developerPanel.destroy();
    }
});

// Add CSS for spinning animation
const style = document.createElement('style');
style.textContent = `
    .spin {
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);