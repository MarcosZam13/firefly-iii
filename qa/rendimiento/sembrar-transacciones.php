<?php
// Siembra para las pruebas de rendimiento de transacciones (TC-P-02, 04, 05 y 08, Kevin).
// Por cada usuario qa-perf-NNN (los crea si faltan, con la clave de sembrar-usuarios.php)
// emite un token personal de API. Las cuentas las crea preparar_transacciones.py por la API,
// con moneda y saldo inicial (una cuenta creada directo en la base no tiene moneda y su
// current_balance queda en 0).
// Imprime JSON {"usuarios": [{correo, token}]}.
// La imagen de producción no trae tinker: el script arranca Laravel solo.
// No se llama directo: lo usa preparar-transacciones.sh.

use FireflyIII\Models\GroupMembership;
use FireflyIII\Models\UserGroup;
use FireflyIII\Models\UserRole;
use FireflyIII\User;
use Illuminate\Contracts\Console\Kernel;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$cantidad = (int) ($argv[1] ?? 50);
$clave    = 'QaRendimiento-2026!';
$rol      = UserRole::query()->where('title', 'owner')->first();

// Passport necesita un cliente de "personal access" para emitir tokens personales.
$hayCliente = Client::all()->contains(fn (Client $c) => in_array('personal_access', (array) $c->grant_types, true));
if (!$hayCliente) {
    app(ClientRepository::class)->createPersonalAccessGrantClient('QA rendimiento', 'users');
}

$salida = ['usuarios' => []];
for ($i = 1; $i <= $cantidad; ++$i) {
    $correo = sprintf('qa-perf-%03d@firefly.test', $i);
    $user   = User::query()->where('email', $correo)->first();
    if (null === $user) {
        $grupo = UserGroup::create(['title' => $correo]);
        $user  = User::create(['email' => $correo, 'password' => bcrypt($clave), 'user_group_id' => $grupo->id]);
        GroupMembership::create(['user_id' => $user->id, 'user_group_id' => $grupo->id, 'user_role_id' => $rol->id]);
    }
    $salida['usuarios'][] = [
        'correo' => $correo,
        'token'  => $user->createToken('qa-rendimiento')->accessToken,
    ];
}
echo json_encode($salida).PHP_EOL;
