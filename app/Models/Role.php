<?php

namespace App\Models;

use Spatie\Permission\Models\Role as PermissionRole;

/**
 * The application's roles table uses integer keys.
 *
 * @property int $id
 * @property string|null $description
 * @property bool|int $is_system
 */
class Role extends PermissionRole {}
