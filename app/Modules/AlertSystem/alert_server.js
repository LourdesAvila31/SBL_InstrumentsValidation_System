/**
 * Servidor de Alertas Automáticas
 * 
 * Sistema de notificaciones en tiempo real usando Node.js, Socket.io y Nodemailer
 */

const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const nodemailer = require('nodemailer');
const mysql = require('mysql2/promise');
const cron = require('cron');
const winston = require('winston');
const axios = require('axios');
require('dotenv').config();

const app = express();
const server = http.createServer(app);
const io = socketIo(server, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"]
    }
});

// Configuración de logging
const logger = winston.createLogger({
    level: 'info',
    format: winston.format.combine(
        winston.format.timestamp(),
        winston.format.json()
    ),
    transports: [
        new winston.transports.File({ filename: 'logs/error.log', level: 'error' }),
        new winston.transports.File({ filename: 'logs/combined.log' }),
        new winston.transports.Console()
    ]
});

// Configuración de la base de datos
const dbConfig = {
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASS || '',
    database: process.env.DB_NAME || 'iso17025'
};

// Configuración de email
const emailTransporter = nodemailer.createTransporter({
    service: process.env.EMAIL_SERVICE || 'gmail',
    auth: {
        user: process.env.EMAIL_USER,
        pass: process.env.EMAIL_PASS
    }
});

class AlertSystem {
    constructor() {
        this.activeAlerts = new Map();
        this.connectedClients = new Set();
        this.alertThresholds = {
            database_response_time: { warning: 1000, critical: 3000 },
            disk_usage: { warning: 80, critical: 90 },
            memory_usage: { warning: 80, critical: 90 },
            cpu_usage: { warning: 80, critical: 90 },
            active_incidents: { warning: 5, critical: 10 }
        };
        
        this.initializeSocketHandlers();
        this.startMonitoring();
    }

    /**
     * Inicializa los manejadores de Socket.io
     */
    initializeSocketHandlers() {
        io.on('connection', (socket) => {
            logger.info(`Cliente conectado: ${socket.id}`);
            this.connectedClients.add(socket.id);

            // Enviar alertas activas al cliente recién conectado
            this.sendActiveAlertsToClient(socket);

            socket.on('acknowledge_alert', (alertId) => {
                this.acknowledgeAlert(alertId, socket.id);
            });

            socket.on('dismiss_alert', (alertId) => {
                this.dismissAlert(alertId, socket.id);
            });

            socket.on('disconnect', () => {
                logger.info(`Cliente desconectado: ${socket.id}`);
                this.connectedClients.delete(socket.id);
            });
        });
    }

    /**
     * Inicia el monitoreo automático del sistema
     */
    startMonitoring() {
        // Monitoreo cada 30 segundos
        const monitoringJob = new cron.CronJob('*/30 * * * * *', () => {
            this.performSystemCheck();
        });

        // Verificación de KPIs cada 5 minutos
        const kpiJob = new cron.CronJob('0 */5 * * * *', () => {
            this.checkKPIs();
        });

        // Reporte diario a las 8:00 AM
        const dailyReportJob = new cron.CronJob('0 0 8 * * *', () => {
            this.generateDailyReport();
        });

        monitoringJob.start();
        kpiJob.start();
        dailyReportJob.start();

        logger.info('Sistema de monitoreo iniciado');
    }

    /**
     * Realiza verificación general del sistema
     */
    async performSystemCheck() {
        try {
            const systemMetrics = await this.gatherSystemMetrics();
            
            for (const [metric, value] of Object.entries(systemMetrics)) {
                await this.evaluateMetric(metric, value);
            }
            
        } catch (error) {
            logger.error('Error durante verificación del sistema:', error);
        }
    }

    /**
     * Recopila métricas del sistema
     */
    async gatherSystemMetrics() {
        const metrics = {};

        try {
            // Tiempo de respuesta de la base de datos
            const dbStart = Date.now();
            const connection = await mysql.createConnection(dbConfig);
            await connection.execute('SELECT 1');
            await connection.end();
            metrics.database_response_time = Date.now() - dbStart;

            // Uso de memoria del proceso Node.js
            const memUsage = process.memoryUsage();
            metrics.memory_usage = (memUsage.heapUsed / memUsage.heapTotal) * 100;

            // CPU usage (simplificado)
            metrics.cpu_usage = process.cpuUsage().user / 1000000; // Convertir a segundos

            // Incidentes activos
            const connection2 = await mysql.createConnection(dbConfig);
            const [rows] = await connection2.execute(
                "SELECT COUNT(*) as count FROM incidents WHERE status = 'open'"
            );
            metrics.active_incidents = rows[0].count;
            await connection2.end();

        } catch (error) {
            logger.error('Error recopilando métricas:', error);
        }

        return metrics;
    }

    /**
     * Evalúa una métrica contra sus umbrales
     */
    async evaluateMetric(metric, value) {
        const thresholds = this.alertThresholds[metric];
        if (!thresholds) return;

        let alertLevel = null;
        
        if (value >= thresholds.critical) {
            alertLevel = 'critical';
        } else if (value >= thresholds.warning) {
            alertLevel = 'warning';
        }

        if (alertLevel) {
            await this.createAlert({
                type: 'system_metric',
                level: alertLevel,
                metric: metric,
                value: value,
                threshold: thresholds[alertLevel],
                message: `${metric} está en ${value}, excediendo el umbral ${alertLevel} de ${thresholds[alertLevel]}`
            });
        }
    }

    /**
     * Crea una nueva alerta
     */
    async createAlert(alertData) {
        const alertId = `alert_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
        
        const alert = {
            id: alertId,
            ...alertData,
            timestamp: new Date(),
            acknowledged: false,
            dismissed: false
        };

        // Evitar duplicados para el mismo tipo de alerta
        const existingAlert = Array.from(this.activeAlerts.values())
            .find(a => a.type === alert.type && a.metric === alert.metric && !a.dismissed);
        
        if (existingAlert) {
            // Actualizar alerta existente
            existingAlert.value = alert.value;
            existingAlert.timestamp = alert.timestamp;
            existingAlert.acknowledged = false;
        } else {
            // Nueva alerta
            this.activeAlerts.set(alertId, alert);
        }

        // Enviar a todos los clientes conectados
        io.emit('new_alert', alert);

        // Enviar notificación por email si es crítica
        if (alert.level === 'critical') {
            await this.sendEmailNotification(alert);
        }

        // Registrar en logs
        logger.warn(`Nueva alerta ${alert.level}:`, alert);

        // Guardar en base de datos
        await this.saveAlertToDatabase(alert);
    }

    /**
     * Envía notificación por email
     */
    async sendEmailNotification(alert) {
        try {
            const recipients = await this.getAlertRecipients(alert.level);
            
            const mailOptions = {
                from: process.env.EMAIL_USER,
                to: recipients.join(','),
                subject: `🚨 Alerta ${alert.level.toUpperCase()}: ${alert.metric}`,
                html: `
                    <h2>Alerta del Sistema ISO 17025</h2>
                    <p><strong>Nivel:</strong> ${alert.level.toUpperCase()}</p>
                    <p><strong>Métrica:</strong> ${alert.metric}</p>
                    <p><strong>Valor actual:</strong> ${alert.value}</p>
                    <p><strong>Umbral:</strong> ${alert.threshold}</p>
                    <p><strong>Mensaje:</strong> ${alert.message}</p>
                    <p><strong>Timestamp:</strong> ${alert.timestamp}</p>
                    <hr>
                    <p>Este es un mensaje automático del sistema de monitoreo.</p>
                `
            };

            await emailTransporter.sendMail(mailOptions);
            logger.info(`Email de alerta enviado a: ${recipients.join(', ')}`);
            
        } catch (error) {
            logger.error('Error enviando email de alerta:', error);
        }
    }

    /**
     * Obtiene destinatarios de alertas según el nivel
     */
    async getAlertRecipients(level) {
        try {
            const connection = await mysql.createConnection(dbConfig);
            let query = '';
            
            if (level === 'critical') {
                query = "SELECT email FROM usuarios WHERE rol IN ('developer', 'admin', 'superadmin') AND email IS NOT NULL";
            } else {
                query = "SELECT email FROM usuarios WHERE rol IN ('developer', 'admin') AND email IS NOT NULL";
            }
            
            const [rows] = await connection.execute(query);
            await connection.end();
            
            return rows.map(row => row.email);
            
        } catch (error) {
            logger.error('Error obteniendo destinatarios:', error);
            return [process.env.FALLBACK_EMAIL || 'admin@sistema.com'];
        }
    }

    /**
     * Reconoce una alerta
     */
    acknowledgeAlert(alertId, clientId) {
        const alert = this.activeAlerts.get(alertId);
        if (alert) {
            alert.acknowledged = true;
            alert.acknowledgedBy = clientId;
            alert.acknowledgedAt = new Date();
            
            io.emit('alert_acknowledged', { alertId, clientId });
            logger.info(`Alerta ${alertId} reconocida por ${clientId}`);
        }
    }

    /**
     * Descarta una alerta
     */
    dismissAlert(alertId, clientId) {
        const alert = this.activeAlerts.get(alertId);
        if (alert) {
            alert.dismissed = true;
            alert.dismissedBy = clientId;
            alert.dismissedAt = new Date();
            
            io.emit('alert_dismissed', { alertId, clientId });
            logger.info(`Alerta ${alertId} descartada por ${clientId}`);
        }
    }

    /**
     * Envía alertas activas a un cliente específico
     */
    sendActiveAlertsToClient(socket) {
        const activeAlerts = Array.from(this.activeAlerts.values())
            .filter(alert => !alert.dismissed);
        
        socket.emit('active_alerts', activeAlerts);
    }

    /**
     * Guarda alerta en la base de datos
     */
    async saveAlertToDatabase(alert) {
        try {
            const connection = await mysql.createConnection(dbConfig);
            
            await connection.execute(`
                INSERT INTO system_alerts (
                    alert_id, type, level, metric, value, threshold, 
                    message, created_at, acknowledged, dismissed
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            `, [
                alert.id, alert.type, alert.level, alert.metric || null,
                alert.value || null, alert.threshold || null, alert.message,
                alert.timestamp, alert.acknowledged, alert.dismissed
            ]);
            
            await connection.end();
            
        } catch (error) {
            logger.error('Error guardando alerta en BD:', error);
        }
    }

    /**
     * Verifica KPIs específicos del negocio
     */
    async checkKPIs() {
        try {
            const connection = await mysql.createConnection(dbConfig);
            
            // KPI: Calibraciones vencidas
            const [expiredCals] = await connection.execute(`
                SELECT COUNT(*) as count 
                FROM calibraciones 
                WHERE fecha_vencimiento < CURDATE() AND estado = 'activa'
            `);
            
            if (expiredCals[0].count > 0) {
                await this.createAlert({
                    type: 'business_kpi',
                    level: 'warning',
                    metric: 'calibraciones_vencidas',
                    value: expiredCals[0].count,
                    message: `Hay ${expiredCals[0].count} calibraciones vencidas que requieren atención`
                });
            }

            // KPI: Usuarios inactivos por mucho tiempo
            const [inactiveUsers] = await connection.execute(`
                SELECT COUNT(*) as count 
                FROM usuarios 
                WHERE ultimo_acceso < DATE_SUB(NOW(), INTERVAL 30 DAY) 
                AND estado = 'activo'
            `);
            
            if (inactiveUsers[0].count > 5) {
                await this.createAlert({
                    type: 'business_kpi',
                    level: 'info',
                    metric: 'usuarios_inactivos',
                    value: inactiveUsers[0].count,
                    message: `${inactiveUsers[0].count} usuarios no han accedido en los últimos 30 días`
                });
            }
            
            await connection.end();
            
        } catch (error) {
            logger.error('Error verificando KPIs:', error);
        }
    }

    /**
     * Genera reporte diario
     */
    async generateDailyReport() {
        try {
            const connection = await mysql.createConnection(dbConfig);
            
            // Recopilar estadísticas del día
            const [alertsToday] = await connection.execute(`
                SELECT level, COUNT(*) as count 
                FROM system_alerts 
                WHERE DATE(created_at) = CURDATE() 
                GROUP BY level
            `);
            
            const [incidentsToday] = await connection.execute(`
                SELECT status, COUNT(*) as count 
                FROM incidents 
                WHERE DATE(created_at) = CURDATE() 
                GROUP BY status
            `);
            
            const reportData = {
                date: new Date().toISOString().split('T')[0],
                alerts: alertsToday,
                incidents: incidentsToday,
                system_status: 'operational'
            };
            
            // Enviar reporte por email
            await this.sendDailyReport(reportData);
            
            await connection.end();
            
        } catch (error) {
            logger.error('Error generando reporte diario:', error);
        }
    }

    /**
     * Envía reporte diario por email
     */
    async sendDailyReport(reportData) {
        try {
            const recipients = await this.getAlertRecipients('info');
            
            const alertsSummary = reportData.alerts.length > 0 ? 
                reportData.alerts.map(a => `${a.level}: ${a.count}`).join(', ') : 
                'No hay alertas';
                
            const incidentsSummary = reportData.incidents.length > 0 ?
                reportData.incidents.map(i => `${i.status}: ${i.count}`).join(', ') :
                'No hay incidentes';
            
            const mailOptions = {
                from: process.env.EMAIL_USER,
                to: recipients.slice(0, 3).join(','), // Limitar a 3 destinatarios para reportes diarios
                subject: `📊 Reporte Diario del Sistema - ${reportData.date}`,
                html: `
                    <h2>Reporte Diario del Sistema ISO 17025</h2>
                    <p><strong>Fecha:</strong> ${reportData.date}</p>
                    <p><strong>Estado del Sistema:</strong> ${reportData.system_status}</p>
                    
                    <h3>Resumen de Alertas</h3>
                    <p>${alertsSummary}</p>
                    
                    <h3>Resumen de Incidentes</h3>
                    <p>${incidentsSummary}</p>
                    
                    <hr>
                    <p>Este es un reporte automático generado diariamente.</p>
                `
            };

            await emailTransporter.sendMail(mailOptions);
            logger.info('Reporte diario enviado exitosamente');
            
        } catch (error) {
            logger.error('Error enviando reporte diario:', error);
        }
    }
}

// Middleware
app.use(express.json());
app.use(express.static('public'));

// Rutas de API
app.get('/api/alerts', (req, res) => {
    const activeAlerts = Array.from(alertSystem.activeAlerts.values());
    res.json(activeAlerts);
});

app.post('/api/alerts/:id/acknowledge', (req, res) => {
    const alertId = req.params.id;
    alertSystem.acknowledgeAlert(alertId, 'api_user');
    res.json({ success: true });
});

app.post('/api/alerts/:id/dismiss', (req, res) => {
    const alertId = req.params.id;
    alertSystem.dismissAlert(alertId, 'api_user');
    res.json({ success: true });
});

app.get('/api/system/status', async (req, res) => {
    try {
        const metrics = await alertSystem.gatherSystemMetrics();
        res.json({
            status: 'operational',
            metrics: metrics,
            timestamp: new Date()
        });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Inicializar sistema de alertas
const alertSystem = new AlertSystem();

// Iniciar servidor
const PORT = process.env.PORT || 3001;
server.listen(PORT, () => {
    logger.info(`Servidor de alertas iniciado en puerto ${PORT}`);
});

// Manejo de errores no capturados
process.on('uncaughtException', (error) => {
    logger.error('Excepción no capturada:', error);
});

process.on('unhandledRejection', (reason, promise) => {
    logger.error('Rechazo no manejado en:', promise, 'razón:', reason);
});

module.exports = { AlertSystem, app };