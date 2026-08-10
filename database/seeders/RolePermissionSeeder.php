<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Convenção de três papéis validada em produção (cgov-agreements): super_admin
 * enxerga tudo, admin gerencia usuários, user é o papel padrão pós-cadastro.
 * As permissions aqui são só as genéricas de gestão de usuário — cada projeto
 * gerado a partir do Aquiles adiciona as suas por cima (não apaga estas).
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        Permission::firstOrCreate(['name' => 'manage-users', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view-users', 'guard_name' => 'web']);

        $superAdmin->syncPermissions(Permission::all());
        $admin->syncPermissions(['manage-users', 'view-users']);
    }
}
