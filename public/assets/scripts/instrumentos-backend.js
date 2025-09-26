/**
 * Script de Integración Backend para Instrumentos
 * 
 * Este script conecta el frontend HTML existente con los nuevos endpoints PHP backend
 * Reemplaza las llamadas de prueba por llamadas reales a la API
 */

// Configuración base
const BACKEND_BASE_URL = window.BASE_URL ? `${window.BASE_URL}/backend/instrumentos` : '/backend/instrumentos';

// Estado global de la aplicación
let instrumentosData = [];
let currentFilters = {};
let isLoading = false;

/**
 * Llamada a la API para obtener lista de instrumentos
 */
async function fetchInstrumentosFromBackend(filters = {}) {
    if (isLoading) return;
    
    isLoading = true;
    showLoadingState();
    
    try {
        // Construir query string con filtros
        const params = new URLSearchParams();
        
        if (filters.codigo) params.append('codigo', filters.codigo);
        if (filters.descripcion) params.append('descripcion', filters.descripcion);
        if (filters.estado) params.append('estado', filters.estado);
        if (filters.ubicacion) params.append('ubicacion', filters.ubicacion);
        
        const url = `${BACKEND_BASE_URL}/list_gages.php${params.toString() ? '?' + params.toString() : ''}`;
        
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Cache-Control': 'no-cache'
            }
        });
        
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Error desconocido');
        }
        
        // Actualizar datos globales
        instrumentosData = result.data || [];
        
        // Actualizar estadísticas si están disponibles
        if (result.estadisticas) {
            updateEstadisticas(result.estadisticas);
        }
        
        // Renderizar tabla con nuevos datos
        renderInstrumentosTable(instrumentosData);
        
        hideLoadingState();
        
        return result;
        
    } catch (error) {
        console.error('Error al obtener instrumentos:', error);
        showErrorMessage(`Error al cargar instrumentos: ${error.message}`);
        hideLoadingState();
        throw error;
    } finally {
        isLoading = false;
    }
}

/**
 * Obtener un instrumento específico por ID
 */
async function getInstrumentoById(id) {
    try {
        const response = await fetch(`${BACKEND_BASE_URL}/get_gage.php?id=${id}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Instrumento no encontrado');
        }
        
        return result.data;
        
    } catch (error) {
        console.error('Error al obtener instrumento:', error);
        throw error;
    }
}

/**
 * Crear nuevo instrumento
 */
async function createInstrumento(data) {
    try {
        const response = await fetch(`${BACKEND_BASE_URL}/add_gage.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'No se pudo crear el instrumento');
        }
        
        return result;
        
    } catch (error) {
        console.error('Error al crear instrumento:', error);
        throw error;
    }
}

/**
 * Actualizar instrumento existente
 */
async function updateInstrumento(id, data) {
    try {
        const response = await fetch(`${BACKEND_BASE_URL}/edit_gage.php?id=${id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'No se pudo actualizar el instrumento');
        }
        
        return result;
        
    } catch (error) {
        console.error('Error al actualizar instrumento:', error);
        throw error;
    }
}

/**
 * Eliminar instrumento
 */
async function deleteInstrumento(id) {
    try {
        const response = await fetch(`${BACKEND_BASE_URL}/delete_gage.php?id=${id}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'No se pudo eliminar el instrumento');
        }
        
        return result;
        
    } catch (error) {
        console.error('Error al eliminar instrumento:', error);
        throw error;
    }
}

/**
 * Renderizar tabla de instrumentos con datos del backend
 */
function renderInstrumentosTable(instrumentos) {
    const tbody = document.querySelector('#instrumentosTbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (!instrumentos || instrumentos.length === 0) {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td colspan="15" class="text-center py-4">
                <i class="fas fa-info-circle text-muted me-2"></i>
                No hay instrumentos registrados
            </td>
        `;
        tbody.appendChild(row);
        return;
    }
    
    instrumentos.forEach(instrumento => {
        const row = document.createElement('tr');
        
        // Determinar clases CSS según estado de calibración
        const estadoCalibracion = instrumento.estado_calibracion || 'SIN_PROGRAMAR';
        let calibrationClass = '';
        let calibrationBadge = '';
        
        switch (estadoCalibracion) {
            case 'VENCIDO':
                calibrationClass = 'calibration-overdue';
                calibrationBadge = '<span class="badge bg-danger">Vencido</span>';
                break;
            case 'PROXIMO_A_VENCER':
                calibrationBadge = '<span class="badge bg-warning text-dark">Próximo a vencer</span>';
                break;
            case 'VIGENTE':
                calibrationBadge = '<span class="badge bg-success">Vigente</span>';
                break;
            default:
                calibrationBadge = '<span class="badge bg-secondary">Sin programar</span>';
        }
        
        // Estado operativo
        const estadoOperativo = instrumento.estado || 'ACTIVO';
        let operationalBadge = '';
        
        switch (estadoOperativo.toUpperCase()) {
            case 'ACTIVO':
                operationalBadge = '<span class="badge bg-success">Activo</span>';
                break;
            case 'INACTIVO':
                operationalBadge = '<span class="badge bg-warning text-dark">Inactivo</span>';
                break;
            case 'MANTENIMIENTO':
                operationalBadge = '<span class="badge bg-info">Mantenimiento</span>';
                break;
            case 'BAJA':
                operationalBadge = '<span class="badge bg-secondary">Baja</span>';
                break;
            default:
                operationalBadge = '<span class="badge bg-secondary">Desconocido</span>';
        }
        
        if (calibrationClass) {
            row.classList.add(calibrationClass);
        }
        
        // Formatear fechas
        const formatDate = (dateStr) => {
            if (!dateStr) return 'NA';
            try {
                return new Date(dateStr).toLocaleDateString('es-ES');
            } catch {
                return 'NA';
            }
        };
        
        // Formatear próxima calibración con días restantes
        const formatProximaCalibracion = (fecha, dias) => {
            if (!fecha) return 'NA';
            const fechaFormateada = formatDate(fecha);
            if (dias !== null && dias !== undefined) {
                if (dias < 0) {
                    return `${fechaFormateada} (${Math.abs(dias)} días vencido)`;
                } else if (dias <= 30) {
                    return `${fechaFormateada} (${dias} días restantes)`;
                } else {
                    return fechaFormateada;
                }
            }
            return fechaFormateada;
        };
        
        row.innerHTML = `
            <td>
                <input type="checkbox" class="select-gage" data-id="${instrumento.id}" />
            </td>
            <td>
                <a href="#" class="instrument-id" data-id="${instrumento.id}">
                    ${instrumento.id}
                </a>
            </td>
            <td>
                <a href="#" class="instrument-name" data-id="${instrumento.id}">
                    ${escapeHtml(instrumento.descripcion || 'Sin descripción')}
                </a>
            </td>
            <td>${escapeHtml(instrumento.marca || 'NA')}</td>
            <td>${escapeHtml(instrumento.modelo || 'NA')}</td>
            <td>${escapeHtml(instrumento.numero_serie || 'NA')}</td>
            <td>${escapeHtml(instrumento.codigo_identificacion || 'NA')}</td>
            <td>Laboratorio</td>
            <td>${escapeHtml(instrumento.ubicacion || 'NA')}</td>
            <td class="text-center">
                <i class="fas fa-file-alt text-muted"></i>
                <span class="ms-1">0</span>
            </td>
            <td class="date-col">${formatDate(instrumento.fecha_registro)}</td>
            <td class="date-col">NA</td>
            <td class="status-col">${operationalBadge}</td>
            <td class="status-col">${calibrationBadge}</td>
            <td class="date-col">
                ${formatProximaCalibracion(instrumento.fecha_proxima_calibracion, instrumento.dias_hasta_calibracion)}
            </td>
        `;
        
        tbody.appendChild(row);
    });
    
    // Agregar event listeners para checkboxes y links
    addTableEventListeners();
}

/**
 * Agregar event listeners a elementos de la tabla
 */
function addTableEventListeners() {
    // Checkboxes de selección
    document.querySelectorAll('.select-gage').forEach(checkbox => {
        checkbox.addEventListener('change', handleInstrumentSelection);
    });
    
    // Links de ID de instrumento
    document.querySelectorAll('.instrument-id').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const id = link.dataset.id;
            showInstrumentDetails(id);
        });
    });
    
    // Links de nombre de instrumento  
    document.querySelectorAll('.instrument-name').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const id = link.dataset.id;
            showInstrumentHistory(id);
        });
    });
}

/**
 * Manejar selección de instrumentos
 */
function handleInstrumentSelection() {
    const selectedCheckboxes = document.querySelectorAll('.select-gage:checked');
    const selectedIds = Array.from(selectedCheckboxes).map(cb => cb.dataset.id);
    
    // Actualizar estado de botones
    updateButtonStates(selectedIds.length);
    
    // Guardar IDs seleccionados globalmente
    window.selectedInstrumentIds = selectedIds;
}

/**
 * Actualizar estado de botones según selección
 */
function updateButtonStates(selectedCount) {
    const btnEdit = document.getElementById('btnEdit');
    const btnDelete = document.getElementById('btnDelete');
    const btnPrintLabel = document.getElementById('btnPrintLabel');
    
    if (btnEdit) btnEdit.disabled = selectedCount !== 1;
    if (btnDelete) btnDelete.disabled = selectedCount === 0;
    if (btnPrintLabel) btnPrintLabel.disabled = selectedCount !== 1;
}

/**
 * Mostrar detalles de instrumento
 */
async function showInstrumentDetails(id) {
    try {
        const instrumento = await getInstrumentoById(id);
        
        // Aquí conectar con el modal existente de detalles
        const modalElement = document.getElementById('detallesModal');
        const modalContent = document.getElementById('detallesModalContenido');
        
        if (modalElement && modalContent) {
            modalContent.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>ID:</strong> ${instrumento.id}</p>
                        <p><strong>Código:</strong> ${escapeHtml(instrumento.codigo_identificacion || 'NA')}</p>
                        <p><strong>Descripción:</strong> ${escapeHtml(instrumento.descripcion || 'NA')}</p>
                        <p><strong>Marca:</strong> ${escapeHtml(instrumento.marca || 'NA')}</p>
                        <p><strong>Modelo:</strong> ${escapeHtml(instrumento.modelo || 'NA')}</p>
                        <p><strong>Serie:</strong> ${escapeHtml(instrumento.numero_serie || 'NA')}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Rango:</strong> ${escapeHtml(instrumento.rango_medicion || 'NA')}</p>
                        <p><strong>Resolución:</strong> ${escapeHtml(instrumento.resolucion || 'NA')}</p>
                        <p><strong>Incertidumbre:</strong> ${escapeHtml(instrumento.incertidumbre || 'NA')}</p>
                        <p><strong>Ubicación:</strong> ${escapeHtml(instrumento.ubicacion || 'NA')}</p>
                        <p><strong>Estado:</strong> ${escapeHtml(instrumento.estado || 'NA')}</p>
                        <p><strong>Última calibración:</strong> ${formatDate(instrumento.fecha_ultima_calibracion)}</p>
                        <p><strong>Próxima calibración:</strong> ${formatDate(instrumento.fecha_proxima_calibracion)}</p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <p><strong>Proveedor de calibración:</strong> ${escapeHtml(instrumento.proveedor_calibracion || 'NA')}</p>
                        <p><strong>Certificado:</strong> ${escapeHtml(instrumento.certificado_calibracion || 'NA')}</p>
                        <p><strong>Observaciones:</strong> ${escapeHtml(instrumento.observaciones || 'Sin observaciones')}</p>
                    </div>
                </div>
            `;
            
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        }
        
    } catch (error) {
        showErrorMessage(`Error al cargar detalles: ${error.message}`);
    }
}

/**
 * Mostrar historial de instrumento
 */
function showInstrumentHistory(id) {
    // Conectar con el modal de historial existente
    const modalElement = document.getElementById('historialModal');
    if (modalElement) {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
        
        // Cargar historial específico del instrumento
        loadInstrumentHistory(id);
    }
}

/**
 * Cargar historial de calibraciones
 */
async function loadInstrumentHistory(id) {
    // Implementación pendiente - por ahora mostrar mensaje
    const historialGridBody = document.getElementById('historialGridBody');
    if (historialGridBody) {
        historialGridBody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center text-muted py-3">
                    <i class="fas fa-clock me-2"></i>
                    Función de historial en desarrollo
                </td>
            </tr>
        `;
    }
}

/**
 * Actualizar estadísticas en la interfaz
 */
function updateEstadisticas(stats) {
    // Si hay elementos de estadísticas en el HTML, actualizarlos
    console.log('Estadísticas:', stats);
    
    // Ejemplo de actualización de badge counts si existen
    const badges = document.querySelectorAll('.quick-filter-btn');
    badges.forEach(badge => {
        const status = badge.dataset.status;
        if (status && stats[status] !== undefined) {
            const currentText = badge.textContent;
            badge.textContent = `${currentText} (${stats[status]})`;
        }
    });
}

/**
 * Mostrar estado de carga
 */
function showLoadingState() {
    const tbody = document.querySelector('#instrumentosTbody');
    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="15" class="text-center py-4">
                    <div class="spinner-border text-primary me-2" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    Cargando instrumentos...
                </td>
            </tr>
        `;
    }
}

/**
 * Ocultar estado de carga
 */
function hideLoadingState() {
    // El método renderInstrumentosTable se encarga de limpiar el contenido
}

/**
 * Mostrar mensaje de error
 */
function showErrorMessage(message) {
    const tbody = document.querySelector('#instrumentosTbody');
    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="15" class="text-center py-4 text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${escapeHtml(message)}
                    <br>
                    <button class="btn btn-outline-primary btn-sm mt-2" onclick="reloadInstrumentos()">
                        <i class="fas fa-redo me-1"></i> Reintentar
                    </button>
                </td>
            </tr>
        `;
    }
}

/**
 * Recargar instrumentos
 */
function reloadInstrumentos() {
    fetchInstrumentosFromBackend(currentFilters);
}

/**
 * Utilidad para escapar HTML
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Formatear fecha
 */
function formatDate(dateStr) {
    if (!dateStr) return 'NA';
    try {
        return new Date(dateStr).toLocaleDateString('es-ES');
    } catch {
        return 'NA';
    }
}

/**
 * Conectar con eventos existentes del frontend
 */
function connectWithExistingFrontend() {
    // Reemplazar la función fetchGages existente
    if (typeof window.fetchGages === 'function') {
        window.originalFetchGages = window.fetchGages;
    }
    
    window.fetchGages = () => {
        return fetchInstrumentosFromBackend(currentFilters);
    };
    
    // Conectar filtros
    const applyFilterBtn = document.getElementById('applyFilter');
    if (applyFilterBtn) {
        applyFilterBtn.addEventListener('click', handleFilterApplication);
    }
    
    // Conectar botones de acción
    const btnDelete = document.getElementById('btnDelete');
    if (btnDelete) {
        btnDelete.addEventListener('click', handleDeleteInstruments);
    }
    
    const btnEdit = document.getElementById('btnEdit');
    if (btnEdit) {
        btnEdit.addEventListener('click', handleEditInstrument);
    }
}

/**
 * Manejar aplicación de filtros
 */
function handleFilterApplication() {
    const filterPeriod = document.getElementById('filterPeriod')?.value;
    const filterStatus = document.getElementById('filterStatus')?.value;
    const filterDept = document.getElementById('filterDept')?.value;
    
    currentFilters = {};
    
    if (filterStatus && filterStatus !== 'all') {
        currentFilters.estado = filterStatus;
    }
    
    if (filterDept && filterDept !== 'all') {
        currentFilters.departamento = filterDept;
    }
    
    fetchInstrumentosFromBackend(currentFilters);
}

/**
 * Manejar eliminación de instrumentos
 */
async function handleDeleteInstruments() {
    const selectedIds = window.selectedInstrumentIds || [];
    
    if (selectedIds.length === 0) {
        alert('Selecciona al menos un instrumento para eliminar');
        return;
    }
    
    const confirmMessage = selectedIds.length === 1 
        ? '¿Estás seguro de eliminar este instrumento?'
        : `¿Estás seguro de eliminar ${selectedIds.length} instrumentos?`;
    
    if (!confirm(confirmMessage)) return;
    
    try {
        // Eliminar cada instrumento seleccionado
        const deletePromises = selectedIds.map(id => deleteInstrumento(id));
        await Promise.all(deletePromises);
        
        alert('Instrumentos eliminados correctamente');
        
        // Recargar lista
        await fetchInstrumentosFromBackend(currentFilters);
        
        // Limpiar selección
        window.selectedInstrumentIds = [];
        updateButtonStates(0);
        
    } catch (error) {
        alert(`Error al eliminar instrumentos: ${error.message}`);
    }
}

/**
 * Manejar edición de instrumento
 */
async function handleEditInstrument() {
    const selectedIds = window.selectedInstrumentIds || [];
    
    if (selectedIds.length !== 1) {
        alert('Selecciona exactamente un instrumento para editar');
        return;
    }
    
    const id = selectedIds[0];
    
    try {
        const instrumento = await getInstrumentoById(id);
        
        // Rellenar formulario de edición existente
        fillEditForm(instrumento);
        
        // Mostrar modal de edición
        const editModal = document.getElementById('editModal');
        if (editModal) {
            const modal = new bootstrap.Modal(editModal);
            modal.show();
        }
        
    } catch (error) {
        alert(`Error al cargar datos del instrumento: ${error.message}`);
    }
}

/**
 * Rellenar formulario de edición
 */
function fillEditForm(instrumento) {
    const fields = {
        'editNombre': instrumento.descripcion,
        'editMarca': instrumento.marca,
        'editModelo': instrumento.modelo,
        'editSerie': instrumento.numero_serie,
        'editCodigo': instrumento.codigo_identificacion,
        'editUbicacion': instrumento.ubicacion,
        'editFechaAlta': instrumento.fecha_registro?.split(' ')[0], // Solo fecha, sin hora
        'editEstado': instrumento.estado
    };
    
    Object.entries(fields).forEach(([fieldId, value]) => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.value = value || '';
        }
    });
}

/**
 * Inicialización cuando el DOM esté listo
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔧 Inicializando integración backend de instrumentos...');
    
    // Conectar con frontend existente
    connectWithExistingFrontend();
    
    // Cargar instrumentos inicial
    fetchInstrumentosFromBackend();
    
    console.log('✅ Integración backend de instrumentos inicializada');
});

// Exportar funciones principales para uso global
window.InstrumentosBackend = {
    fetchInstrumentos: fetchInstrumentosFromBackend,
    getInstrumento: getInstrumentoById,
    createInstrumento: createInstrumento,
    updateInstrumento: updateInstrumento,
    deleteInstrumento: deleteInstrumento,
    reloadInstrumentos: reloadInstrumentos
};