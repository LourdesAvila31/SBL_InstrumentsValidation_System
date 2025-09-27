/**
 * Developer Portal JavaScript
 * SBL Sistema Interno - Developer Section
 */

const DeveloperPortal = {
    currentSection: 'overview',
    refreshInterval: null,
    charts: {},
    
    /**
     * Initialize the developer portal
     */
    init() {
        console.log('Initializing Developer Portal...');
        this.setupNavigation();
        this.initializeCharts();
        this.startRealTimeUpdates();
        this.setupEventListeners();
        console.log('Developer Portal initialized successfully');
    },
    
    /**
     * Setup navigation event listeners
     */
    setupNavigation() {
        const navLinks = document.querySelectorAll('.nav-link[data-section]');
        navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const section = link.getAttribute('data-section');
                this.showSection(section);
            });
        });
    },
    
    /**
     * Show a specific section
     */
    showSection(sectionName) {
        // Hide all sections
        document.querySelectorAll('.content-section').forEach(section => {
            section.classList.remove('active');
        });
        
        // Remove active class from all nav links
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.remove('active');
        });
        
        // Show selected section
        const targetSection = document.getElementById(sectionName + '-section');
        if (targetSection) {
            targetSection.classList.add('active');
        }
        
        // Add active class to selected nav link
        const activeLink = document.querySelector(`[data-section="${sectionName}"]`);
        if (activeLink) {
            activeLink.classList.add('active');
        }
        
        // Update page title
        const titles = {
            'overview': 'System Overview',
            'system-status': 'System Status',
            'api-docs': 'API Documentation',
            'database': 'Database Tools',
            'logs': 'System Logs',
            'gamp5': 'GAMP5 Validation',
            'testing': 'Testing Suite',
            'backup': 'Backup & Recovery',
            'configuration': 'Configuration',
            'security': 'Security Settings'
        };
        
        const pageTitle = document.getElementById('page-title');
        if (pageTitle) {
            pageTitle.textContent = titles[sectionName] || 'Developer Portal';
        }
        
        this.currentSection = sectionName;
        
        // Load section-specific data
        this.loadSectionData(sectionName);
    },
    
    /**
     * Load data for specific section
     */
    loadSectionData(sectionName) {
        switch(sectionName) {
            case 'overview':
                this.loadOverviewData();
                break;
            case 'system-status':
                this.loadSystemStatus();
                break;
            case 'api-docs':
                this.loadApiDocs();
                break;
            case 'logs':
                this.loadSystemLogs();
                break;
            default:
                break;
        }
    },
    
    /**
     * Initialize charts
     */
    initializeCharts() {
        this.initPerformanceChart();
    },
    
    /**
     * Initialize performance chart
     */
    initPerformanceChart() {
        const ctx = document.getElementById('performance-chart');
        if (!ctx) return;
        
        this.charts.performance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['12:00', '12:30', '13:00', '13:30', '14:00', '14:30'],
                datasets: [{
                    label: 'Response Time (ms)',
                    data: [120, 85, 95, 110, 90, 88],
                    borderColor: '#06b6d4',
                    backgroundColor: 'rgba(6, 182, 212, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'CPU Usage (%)',
                    data: [45, 52, 48, 65, 58, 55],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#e2e8f0'
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: '#94a3b8' },
                        grid: { color: '#374151' }
                    },
                    y: {
                        ticks: { color: '#94a3b8' },
                        grid: { color: '#374151' }
                    }
                }
            }
        });
    },
    
    /**
     * Start real-time updates
     */
    startRealTimeUpdates() {
        // Update every 30 seconds
        this.refreshInterval = setInterval(() => {
            if (this.currentSection === 'overview') {
                this.updateOverviewStats();
            }
        }, 30000);
    },
    
    /**
     * Stop real-time updates
     */
    stopRealTimeUpdates() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    },
    
    /**
     * Setup additional event listeners
     */
    setupEventListeners() {
        // Handle mobile menu
        document.addEventListener('click', (e) => {
            const sidebar = document.querySelector('.sidebar');
            const isMobile = window.innerWidth <= 768;
            
            if (isMobile && !sidebar.contains(e.target) && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', () => {
            if (this.charts.performance) {
                this.charts.performance.resize();
            }
        });
    },
    
    /**
     * Load overview data
     */
    async loadOverviewData() {
        try {
            // Simulate API call
            const data = await this.simulateApiCall('/api/developer/overview');
            if (data) {
                this.updateOverviewStats(data);
            }
        } catch (error) {
            console.error('Error loading overview data:', error);
        }
    },
    
    /**
     * Update overview statistics
     */
    updateOverviewStats(data = null) {
        // Update active users
        const activeUsers = document.getElementById('active-users');
        if (activeUsers) {
            if (data && data.activeUsers) {
                activeUsers.textContent = data.activeUsers;
            } else {
                // Simulate random update
                const currentValue = parseInt(activeUsers.textContent) || 127;
                const change = Math.floor(Math.random() * 10) - 5;
                activeUsers.textContent = Math.max(0, currentValue + change);
            }
        }
        
        // Update API requests
        const apiRequests = document.getElementById('api-requests');
        if (apiRequests) {
            if (data && data.apiRequests) {
                apiRequests.textContent = data.apiRequests;
            } else {
                // Simulate increase
                const currentText = apiRequests.textContent;
                const currentValue = parseFloat(currentText.replace('K', ''));
                const increment = (Math.random() * 0.2).toFixed(1);
                apiRequests.textContent = (currentValue + parseFloat(increment)).toFixed(1) + 'K';
            }
        }
        
        // Update uptime
        const uptime = document.getElementById('system-uptime');
        if (uptime && data && data.uptime) {
            uptime.textContent = data.uptime;
        }
        
        // Update database size
        const dbSize = document.getElementById('database-size');
        if (dbSize && data && data.databaseSize) {
            dbSize.textContent = data.databaseSize;
        }
    },
    
    /**
     * Load system status
     */
    async loadSystemStatus() {
        try {
            const data = await this.simulateApiCall('/api/developer/system-status');
            if (data) {
                this.updateSystemStatus(data);
            }
        } catch (error) {
            console.error('Error loading system status:', error);
        }
    },
    
    /**
     * Update system status display
     */
    updateSystemStatus(data) {
        // Update services status
        if (data.services) {
            this.updateServicesStatus(data.services);
        }
        
        // Update resource usage
        if (data.resources) {
            this.updateResourceUsage(data.resources);
        }
    },
    
    /**
     * Update services status
     */
    updateServicesStatus(services) {
        const container = document.getElementById('services-status');
        if (!container) return;
        
        container.innerHTML = services.map(service => `
            <div class="service-item">
                <div class="service-name">${service.name}</div>
                <div class="service-status">
                    <i class="fas fa-circle status-${service.status}"></i>
                    <span>${this.capitalizeFirst(service.status)}</span>
                </div>
            </div>
        `).join('');
    },
    
    /**
     * Update resource usage
     */
    updateResourceUsage(resources) {
        const container = document.getElementById('server-resources');
        if (!container) return;
        
        container.innerHTML = `
            <div style="margin-bottom: 1rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                    <span>CPU Usage</span>
                    <span>${resources.cpu}%</span>
                </div>
                <div class="resource-bar">
                    <div class="resource-fill resource-cpu" style="width: ${resources.cpu}%;"></div>
                </div>
            </div>
            <div style="margin-bottom: 1rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                    <span>Memory Usage</span>
                    <span>${resources.memory}%</span>
                </div>
                <div class="resource-bar">
                    <div class="resource-fill resource-memory" style="width: ${resources.memory}%;"></div>
                </div>
            </div>
            <div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                    <span>Disk Usage</span>
                    <span>${resources.disk}%</span>
                </div>
                <div class="resource-bar">
                    <div class="resource-fill resource-disk" style="width: ${resources.disk}%;"></div>
                </div>
            </div>
        `;
    },
    
    /**
     * Load API documentation
     */
    async loadApiDocs() {
        try {
            const data = await this.simulateApiCall('/api/developer/api-docs');
            if (data && data.endpoints) {
                this.updateApiDocs(data.endpoints);
            }
        } catch (error) {
            console.error('Error loading API docs:', error);
        }
    },
    
    /**
     * Update API documentation display
     */
    updateApiDocs(endpoints) {
        const container = document.getElementById('api-endpoints');
        if (!container) return;
        
        container.innerHTML = endpoints.map(endpoint => `
            <div class="api-endpoint">
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                    <span class="api-method method-${endpoint.method.toLowerCase()}">${endpoint.method}</span>
                    <span class="api-path">${endpoint.path}</span>
                    <span class="badge badge-${endpoint.status === 'active' ? 'success' : 'warning'}">${this.capitalizeFirst(endpoint.status)}</span>
                </div>
                <p style="margin: 0; color: var(--dev-text-secondary);">${endpoint.description}</p>
            </div>
        `).join('');
    },
    
    /**
     * Load system logs
     */
    async loadSystemLogs() {
        try {
            const data = await this.simulateApiCall('/api/developer/logs');
            if (data && data.logs) {
                this.updateSystemLogs(data.logs);
            }
        } catch (error) {
            console.error('Error loading system logs:', error);
        }
    },
    
    /**
     * Update system logs display
     */
    updateSystemLogs(logs) {
        const container = document.getElementById('system-logs');
        if (!container) return;
        
        container.innerHTML = logs.map(log => {
            let color = 'var(--dev-text-secondary)';
            switch(log.level.toLowerCase()) {
                case 'error': color = 'var(--dev-danger)'; break;
                case 'warn': case 'warning': color = 'var(--dev-warning)'; break;
                case 'info': color = 'var(--dev-info)'; break;
                case 'debug': color = 'var(--dev-success)'; break;
            }
            
            return `<div style="color: ${color};">[${log.timestamp}] ${log.level.toUpperCase()}: ${log.message}</div>`;
        }).join('');
    },
    
    /**
     * Simulate API call (replace with actual API calls)
     */
    async simulateApiCall(endpoint, options = {}) {
        return new Promise((resolve) => {
            setTimeout(() => {
                // Simulate different responses based on endpoint
                switch(endpoint) {
                    case '/api/developer/overview':
                        resolve({
                            activeUsers: Math.floor(Math.random() * 200) + 100,
                            apiRequests: (Math.random() * 10 + 5).toFixed(1) + 'K',
                            uptime: '99.' + Math.floor(Math.random() * 9) + '%',
                            databaseSize: (Math.random() * 2 + 2).toFixed(1) + 'GB'
                        });
                        break;
                    
                    case '/api/developer/system-status':
                        resolve({
                            services: [
                                { name: 'Web Server', status: 'online' },
                                { name: 'Database', status: 'online' },
                                { name: 'API Gateway', status: Math.random() > 0.7 ? 'warning' : 'online' },
                                { name: 'Cache Service', status: 'online' }
                            ],
                            resources: {
                                cpu: Math.floor(Math.random() * 30) + 40,
                                memory: Math.floor(Math.random() * 40) + 30,
                                disk: Math.floor(Math.random() * 20) + 20
                            }
                        });
                        break;
                    
                    case '/api/developer/api-docs':
                        resolve({
                            endpoints: [
                                { method: 'GET', path: '/api/v1/calibraciones', status: 'active', description: 'Obtener lista de calibraciones' },
                                { method: 'POST', path: '/api/v1/calibraciones', status: 'active', description: 'Crear nueva calibración' },
                                { method: 'GET', path: '/api/v1/instrumentos', status: 'active', description: 'Obtener instrumentos registrados' },
                                { method: 'PUT', path: '/api/v1/instrumentos/{id}', status: 'active', description: 'Actualizar información de instrumento' },
                                { method: 'DELETE', path: '/api/v1/instrumentos/{id}', status: 'deprecated', description: 'Eliminar instrumento' }
                            ]
                        });
                        break;
                    
                    case '/api/developer/logs':
                        const now = new Date();
                        resolve({
                            logs: [
                                { timestamp: this.formatTime(new Date(now - 60000)), level: 'INFO', message: 'System startup completed' },
                                { timestamp: this.formatTime(new Date(now - 120000)), level: 'DEBUG', message: 'Database connection established' },
                                { timestamp: this.formatTime(new Date(now - 180000)), level: 'WARN', message: 'High memory usage detected (85%)' },
                                { timestamp: this.formatTime(new Date(now - 240000)), level: 'INFO', message: 'API request processed successfully' },
                                { timestamp: this.formatTime(new Date(now - 300000)), level: 'ERROR', message: 'Failed to connect to external service' },
                                { timestamp: this.formatTime(new Date(now - 360000)), level: 'DEBUG', message: 'Cache cleared successfully' }
                            ]
                        });
                        break;
                    
                    default:
                        resolve(null);
                }
            }, Math.random() * 1000 + 500); // 500-1500ms delay
        });
    },
    
    /**
     * Utility functions
     */
    capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    },
    
    formatTime(date) {
        return date.toISOString().slice(0, 19).replace('T', ' ');
    },
    
    /**
     * Show notification
     */
    showNotification(message, type = 'info') {
        console.log(`[${type.toUpperCase()}] ${message}`);
        // TODO: Implement toast notifications
    },
    
    /**
     * Cleanup resources
     */
    destroy() {
        this.stopRealTimeUpdates();
        
        // Destroy charts
        Object.values(this.charts).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
        
        this.charts = {};
    }
};

// Mobile sidebar toggle
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.toggle('show');
    }
}

// Export for global access
window.DeveloperPortal = DeveloperPortal;