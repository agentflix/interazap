<?php

declare(strict_types=1);

return [
    'models' => [
        'permission' => Domain\Auth\Models\AuthPermission::class,
        'role' => Domain\Auth\Models\AuthRole::class,
    ],

    'table_names' => [
        'roles' => 'auth_roles',
        'permissions' => 'auth_permissions',
        'model_has_permissions' => 'auth_model_has_permissions',
        'model_has_roles' => 'auth_model_has_roles',
        'role_has_permissions' => 'auth_role_has_permissions',
    ],

    'column_names' => [
        'model_morph_key' => 'model_id',
    ],

    'display_permission_in_exception' => false,

    'cache' => [
        'expiration_time' => \DateInterval::createFromDateString('24 hours'),
        'key' => 'spatie.permission.cache',
        'store' => 'default',
    ],
];
