<?php

namespace App\Core\Auth\Contracts;

/**
 * Interface para gestión de permisos
 */
interface PermissionManagerInterface
{
    /**
     * Verifica si un usuario tiene un permiso específico
     *
     * @param mixed $userId ID del usuario
     * @param string $permission Nombre del permiso
     * @param array $context Contexto adicional (empresa, módulo, etc.)
     * @return bool True si el usuario tiene el permiso
     */
    public function hasPermission($userId, string $permission, array $context = []): bool;

    /**
     * Obtiene todos los permisos de un usuario
     *
     * @param mixed $userId ID del usuario  
     * @param array $context Contexto adicional
     * @return array Lista de permisos del usuario
     */
    public function getUserPermissions($userId, array $context = []): array;

    /**
     * Obtiene los permisos de un rol específico
     *
     * @param string $role Nombre del rol
     * @return array Lista de permisos del rol
     */
    public function getRolePermissions(string $role): array;

    /**
     * Asigna un permiso a un usuario
     *
     * @param mixed $userId ID del usuario
     * @param string $permission Nombre del permiso
     * @param array $context Contexto adicional
     * @return bool True si se asignó correctamente
     */
    public function grantPermission($userId, string $permission, array $context = []): bool;

    /**
     * Revoca un permiso de un usuario
     *
     * @param mixed $userId ID del usuario
     * @param string $permission Nombre del permiso
     * @param array $context Contexto adicional
     * @return bool True si se revocó correctamente
     */
    public function revokePermission($userId, string $permission, array $context = []): bool;

    /**
     * Obtiene todos los permisos disponibles
     *
     * @return array Lista de todos los permisos
     */
    public function getAllPermissions(): array;
}