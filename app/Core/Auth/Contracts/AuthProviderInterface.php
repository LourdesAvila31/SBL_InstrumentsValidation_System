<?php

namespace App\Core\Auth\Contracts;

/**
 * Interface para proveedores de autenticación
 * 
 * Define los métodos que debe implementar cualquier proveedor
 * de autenticación (base de datos, LDAP, OAuth, etc.)
 */
interface AuthProviderInterface
{
    /**
     * Autentica un usuario con credenciales
     *
     * @param array $credentials Credenciales (username, password, etc.)
     * @return array|null Datos del usuario autenticado o null si falla
     */
    public function authenticate(array $credentials): ?array;

    /**
     * Encuentra un usuario por ID
     *
     * @param mixed $identifier Identificador del usuario
     * @return array|null Datos del usuario o null si no existe
     */
    public function findUser($identifier): ?array;

    /**
     * Valida las credenciales del usuario
     *
     * @param array $user Datos del usuario
     * @param array $credentials Credenciales a validar
     * @return bool True si las credenciales son válidas
     */
    public function validateCredentials(array $user, array $credentials): bool;

    /**
     * Actualiza la última fecha de acceso del usuario
     *
     * @param mixed $identifier Identificador del usuario
     * @return bool True si se actualizó correctamente
     */
    public function updateLastLogin($identifier): bool;
}