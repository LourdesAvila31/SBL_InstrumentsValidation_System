<?php

namespace App\Core\Auth\Contracts;

/**
 * Interface para gestión de roles
 */
interface RoleManagerInterface
{
    /**
     * Obtiene los roles de un usuario
     *
     * @param mixed $userId ID del usuario
     * @return array Lista de roles del usuario
     */
    public function getUserRoles($userId): array;

    /**
     * Verifica si un usuario tiene un rol específico
     *
     * @param mixed $userId ID del usuario
     * @param string $role Nombre del rol
     * @return bool True si el usuario tiene el rol
     */
    public function hasRole($userId, string $role): bool;

    /**
     * Asigna un rol a un usuario
     *
     * @param mixed $userId ID del usuario
     * @param string $role Nombre del rol
     * @return bool True si se asignó correctamente
     */
    public function assignRole($userId, string $role): bool;

    /**
     * Revoca un rol de un usuario
     *
     * @param mixed $userId ID del usuario
     * @param string $role Nombre del rol
     * @return bool True si se revocó correctamente
     */
    public function revokeRole($userId, string $role): bool;

    /**
     * Obtiene todos los roles disponibles
     *
     * @return array Lista de todos los roles
     */
    public function getAllRoles(): array;
}