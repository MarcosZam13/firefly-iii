<?php
// Siembra los usuarios de las pruebas de rendimiento (TC-P-01: 50, TC-P-06: 300).
// La imagen de producción no trae tinker: el script arranca Laravel solo.
// Uso (desde qa/rendimiento):
//   docker compose cp sembrar-usuarios.php app:/tmp/sembrar-usuarios.php
//   MSYS_NO_PATHCONV=1 docker compose exec app php /tmp/sembrar-usuarios.php

use FireflyIII\Models\GroupMembership;
use FireflyIII\Models\UserGroup;
use FireflyIII\Models\UserRole;
use FireflyIII\User;
use Illuminate\Contracts\Console\Kernel;

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$clave = 'QaRendimiento-2026!';
$hash  = bcrypt($clave);
$rol   = UserRole::query()->where('title', 'owner')->first();
for ($i = 1; $i <= 300; ++$i) {
    $correo = sprintf('qa-perf-%03d@firefly.test', $i);
    if (User::query()->where('email', $correo)->exists()) {
        continue;
    }
    $grupo = UserGroup::create(['title' => $correo]);
    $user  = User::create(['email' => $correo, 'password' => $hash, 'user_group_id' => $grupo->id]);
    GroupMembership::create(['user_id' => $user->id, 'user_group_id' => $grupo->id, 'user_role_id' => $rol->id]);
}
echo 'usuarios qa-perf: '.User::query()->where('email', 'like', 'qa-perf-%')->count().PHP_EOL;
