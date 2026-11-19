<?php
/** Ejecutar solo en el contenedor local de QA con QAP_DIANA_SEED=1. */
declare(strict_types=1);

use Carbon\CarbonInterval;
use FireflyIII\Models\GroupMembership;
use FireflyIII\Models\UserGroup;
use FireflyIII\Models\UserRole;
use FireflyIII\User;
use GuzzleHttp\Client;
use Illuminate\Contracts\Console\Kernel;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

if ('1' !== getenv('QAP_DIANA_SEED')) { exit("Falta QAP_DIANA_SEED=1 para habilitar la siembra local.\n"); }
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Passport::personalAccessTokensExpireIn(CarbonInterval::hours(2));
app(ClientRepository::class)->createPersonalAccessGrantClient('QA Diana rendimiento', 'users');
$rol = UserRole::query()->where('title', 'owner')->firstOrFail();
$hash = bcrypt('QaDiana-Rendimiento-2026!');
$csv = fopen('/tmp/presupuestos-diana.csv', 'w');
fputcsv($csv, ['token', 'budget_id', 'limit_id'], escape: '');

for ($i = 1; $i <= 50; ++$i) {
    $correo = sprintf('qa-diana-perf-%03d@firefly.test', $i);
    $user = User::query()->where('email', $correo)->first();
    if (null === $user) {
        $grupo = UserGroup::create(['title' => $correo]);
        $user = User::create(['email' => $correo, 'password' => $hash, 'user_group_id' => $grupo->id]);
        GroupMembership::create(['user_id' => $user->id, 'user_group_id' => $grupo->id, 'user_role_id' => $rol->id]);
    }
    $token = $user->createToken('QA Diana rendimiento')->accessToken;
    $http = new Client(['base_uri' => 'http://127.0.0.1:8080/api/v1/', 'http_errors' => false,
        'headers' => ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json']]);
    $pedir = static function (string $metodo, string $ruta, array $datos = []) use ($http): array {
        $opciones = [] === $datos ? [] : ['json' => $datos];
        $respuesta = $http->request($metodo, $ruta, $opciones);
        if (!in_array($respuesta->getStatusCode(), [200, 201], true)) {
            throw new RuntimeException('Fallo en la preparacion de '.$ruta.': HTTP '.$respuesta->getStatusCode());
        }
        return json_decode((string) $respuesta->getBody(), true, flags: JSON_THROW_ON_ERROR);
    };
    $cuentas = $pedir('GET', 'accounts?type=asset')['data'];
    $cuenta = $cuentas[0] ?? $pedir('POST', 'accounts', ['name' => 'Cuenta QA rendimiento Diana',
        'type' => 'asset', 'account_role' => 'defaultAsset', 'currency_code' => 'EUR'])['data'];
    $presupuestos = $pedir('GET', 'budgets')['data'];
    $presupuesto = $presupuestos[0] ?? $pedir('POST', 'budgets', ['name' => 'Presupuesto QA rendimiento Diana', 'active' => true])['data'];
    $limites = $pedir('GET', 'budgets/'.$presupuesto['id'].'/limits')['data'];
    $limite = $limites[0] ?? $pedir('POST', 'budgets/'.$presupuesto['id'].'/limits',
        ['amount' => '500.00', 'start' => '2026-11-01', 'end' => '2026-11-30', 'currency_code' => 'EUR', 'fire_webhooks' => false])['data'];
    $historial = $pedir('GET', 'accounts/'.$cuenta['id'].'/transactions')['data'];
    if ([] === $historial) {
        $pedir('POST', 'transactions', ['apply_rules' => false, 'fire_webhooks' => false, 'transactions' => [[
            'type' => 'withdrawal', 'date' => '2026-11-09', 'amount' => '25.00', 'currency_code' => 'EUR',
            'description' => 'Gasto QA rendimiento Diana', 'source_id' => $cuenta['id'],
            'destination_name' => 'Comercio QA rendimiento', 'budget_id' => $presupuesto['id'],
        ]]]);
    }
    fputcsv($csv, [$token, $presupuesto['id'], $limite['id']], escape: '');
}
fclose($csv);
chmod('/tmp/presupuestos-diana.csv', 0600);
echo "Preparados 50 usuarios con cuentas, presupuestos y gastos. CSV local generado sin imprimir tokens.\n";
