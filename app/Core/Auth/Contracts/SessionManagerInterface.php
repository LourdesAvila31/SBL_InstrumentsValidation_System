<?php

namespace App\Core\Auth\Contracts;

/**
 * Interface para gestión de sesiones
 */
interface SessionManagerInterface
{
    /**
     * Inicia una nueva sesión para el usuario
     *
     * @param array $user Datos del usuario
     * @param array $options Opciones adicionales
     * @return string|null ID de sesión o null si falla
     */
    public function start(array $user, array $options = []): ?string;

    /**
     * Obtiene los datos de una sesión
     *
     * @param string $sessionId ID de la sesión
     * @return array|null Datos de la sesión o null si no existe
     */
    public function get(string $sessionId): ?array;

    /**
     * Actualiza los datos de una sesión
     *
     * @param string $sessionId ID de la sesión
     * @param array $data Datos a actualizar
     * @return bool True si se actualizó correctamente
     */
    public function update(string $sessionId, array $data): bool;

    /**
     * Destruye una sesión específica
     *
     * @param string $sessionId ID de la sesión
     * @return bool True si se destruyó correctamente
     */
    public function destroy(string $sessionId): bool;

    /**
     * Destruye todas las sesiones de un usuario
     *
     * @param mixed $userId ID del usuario
     * @return bool True si se destruyeron correctamente
     */
    public function destroyUserSessions($userId): bool;

    /**
     * Verifica si una sesión es válida
     *
     * @param string $sessionId ID de la sesión
     * @return bool True si la sesión es válida
     */
    public function isValid(string $sessionId): bool;

    /**
     * Limpia sesiones expiradas
     *
     * @return int Número de sesiones limpiadas
     */
    public function cleanup(): int;

    /**
     * Renueva una sesión (cambia el ID por seguridad)
     *
     * @param string $sessionId ID de la sesión actual
     * @return string|null Nuevo ID de sesión o null si falla
     */
    public function renew(string $sessionId): ?string;
}