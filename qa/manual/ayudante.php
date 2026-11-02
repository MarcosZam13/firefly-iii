<?php

// Ayudante de la ejecución manual (Fase 3): prepara los datos de cada caso en el Firefly
// local y muestra lo que el caso pide revisar en la base. No marca nada como aprobado:
// quien ejecuta compara lo que ve con el resultado esperado del caso.
// La imagen de producción no trae tinker: el script arranca Laravel solo.
// Se usa a través de qa/manual/qa.sh.

declare(strict_types=1);

use Carbon\Carbon;
use Carbon\CarbonInterval;
use FireflyIII\Enums\AccountTypeEnum;
use FireflyIII\Models\Account;
use FireflyIII\Models\AccountType;
use FireflyIII\Models\GroupMembership;
use FireflyIII\Models\UserGroup;
use FireflyIII\Models\UserRole;
use FireflyIII\Support\Facades\AppConfiguration;
use FireflyIII\Support\Facades\Preferences;
use FireflyIII\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use PragmaRX\Google2FALaravel\Facade as Google2FA;

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

const CLAVE = 'QaFirefly-2026-Fase3!';

/** Correos que usa cada caso. preparar los borra y los crea de nuevo: cada caso arranca limpio. */
const USUARIOS = [
    'TC-F-01' => ['qa-login@firefly.test'],
    'TC-F-02' => ['qa-clave@firefly.test'],
    'TC-F-03' => [],
    'TC-F-04' => ['qa-bloqueo@firefly.test'],
    'TC-F-05' => ['qa-dup@firefly.test'],
    'TC-F-06' => [],
    'TC-F-07' => [],
    'TC-F-08' => [],
    'TC-F-09' => ['qa-reset@firefly.test'],
    'TC-F-10' => [],
    'TC-F-11' => ['qa-token@firefly.test'],
    'TC-F-12' => ['qa-logout@firefly.test'],
    'TC-F-13' => ['qa-api@firefly.test'],
    'TC-F-14' => ['qa-2fa-ok@firefly.test'],
    'TC-F-15' => ['qa-2fa-mal@firefly.test'],
    'TC-F-16' => ['qa-2fa-off@firefly.test'],
    'TC-F-17' => ['qa-borrar@firefly.test'],
    'TC-F-18' => ['qa-eliminado@firefly.test'],
    'TC-F-19' => ['qa-duena-a@firefly.test', 'qa-ajeno-b@firefly.test'],
    'TC-F-20' => ['qa-2fa-nuevo@firefly.test'],
    'TC-F-59' => ['qa-respaldo@firefly.test'],
];
/** Correos que el caso registra por su cuenta: solo se borran. */
const A_REGISTRAR = [
    'TC-F-06' => ['qa-15@firefly.test', 'qa-16@firefly.test'],
    'TC-F-07' => ['qatestfirefly.test'],
    'TC-F-08' => ['qa-nuevo@firefly.test'],
];

function salida(string $clave, string|int $valor): void
{
    echo $clave.'='.$valor.PHP_EOL;
}

function borrarUsuario(string $correo): void
{
    User::query()->where('email', $correo)->get()->each(static fn (User $u) => $u->delete());
    UserGroup::query()->where('title', $correo)->delete();
    DB::table('password_resets')->where('email', $correo)->delete();
}

function crearUsuario(string $correo): User
{
    $grupo = UserGroup::create(['title' => $correo]);
    $rol   = UserRole::query()->where('title', 'owner')->first();
    $user  = User::create(['email' => $correo, 'password' => bcrypt(CLAVE), 'user_group_id' => $grupo->id]);
    GroupMembership::create(['user_id' => $user->id, 'user_group_id' => $grupo->id, 'user_role_id' => $rol->id]);

    return $user;
}

function crearCuentaDeActivo(User $user, string $nombre): Account
{
    $tipo = AccountType::query()->where('type', AccountTypeEnum::ASSET->value)->first();

    return Account::create([
        'user_id'         => $user->id,
        'user_group_id'   => $user->user_group_id,
        'account_type_id' => $tipo->id,
        'name'            => $nombre,
        'active'          => true,
    ]);
}

function activar2fa(User $user): string
{
    $secreto          = Google2FA::generateSecretKey();
    $user->mfa_secret = $secreto;
    $user->save();

    return $secreto;
}

function usuario(string $correo): ?User
{
    return User::query()->where('email', $correo)->first();
}

function preferencia(User $user, string $nombre): mixed
{
    return DB::table('preferences')->where('user_id', $user->id)->where('name', $nombre)->value('data');
}

function tokenPersonal(User $user, string $nombre): string
{
    app(ClientRepository::class)->createPersonalAccessGrantClient('QA manual', 'users');

    return $user->createToken($nombre)->accessToken;
}

function preparar(string $caso): void
{
    if (!array_key_exists($caso, USUARIOS)) {
        fwrite(STDERR, "Caso desconocido: {$caso}\n");
        exit(2);
    }
    foreach ([...USUARIOS[$caso], ...(A_REGISTRAR[$caso] ?? [])] as $correo) {
        borrarUsuario($correo);
    }
    // Sin sesiones viejas ni contadores de intentos: la prueba arranca sin cookie válida.
    array_map('unlink', glob('/var/www/html/storage/framework/sessions/*') ?: []);
    Artisan::call('cache:clear');
    // single_user_mode bloquea el registro: precondición de TC-F-05 a TC-F-08.
    AppConfiguration::set('single_user_mode', false);

    $usuarios = [];
    foreach (USUARIOS[$caso] as $correo) {
        $usuarios[$correo] = crearUsuario($correo);
    }
    $primero = array_key_first($usuarios);
    if (null !== $primero) {
        salida('correo', $primero);
    }
    salida('clave', CLAVE);

    switch ($caso) {
        case 'TC-F-11':
            salida('token_reseteo', Password::broker()->createToken($usuarios[$primero]));
            break;
        case 'TC-F-13':
            crearCuentaDeActivo($usuarios[$primero], 'Cuenta QA token vencido');
            Passport::personalAccessTokensExpireIn(CarbonInterval::seconds(1));
            $token = tokenPersonal($usuarios[$primero], 'qa-vencido');
            // Control del caso: con el token aún vigente, la API responde 200.
            $contexto = stream_context_create(['http' => [
                'header'        => "Accept: application/json\r\nAuthorization: Bearer {$token}\r\n",
                'ignore_errors' => true,
            ]]);
            file_get_contents('http://localhost:8080/api/v1/accounts', false, $contexto);
            salida('control_token_vigente', $http_response_header[0] ?? 'sin respuesta');
            sleep(2);
            salida('token_vencido', $token);
            break;
        case 'TC-F-14':
        case 'TC-F-15':
        case 'TC-F-16':
            salida('secreto_totp', activar2fa($usuarios[$primero]));
            break;
        case 'TC-F-19':
            $a = $usuarios['qa-duena-a@firefly.test'];
            $b = $usuarios['qa-ajeno-b@firefly.test'];
            salida('id_usuario_a', $a->id);
            salida('id_cuenta_a', crearCuentaDeActivo($a, 'Cuenta QA de A')->id);
            salida('token_b', tokenPersonal($b, 'qa-b'));
            break;
        case 'TC-F-59':
            $secreto = activar2fa($usuarios[$primero]);
            $codigos = [];
            for ($i = 0; $i < 8; ++$i) {
                $codigos[] = sprintf('%08d', random_int(0, 99999999));
            }
            Preferences::setForUser($usuarios[$primero], 'mfa_recovery', $codigos);
            salida('secreto_totp', $secreto);
            salida('codigo_respaldo', $codigos[0]);
            break;
    }
}

function ver(string $caso): void
{
    $correos = USUARIOS[$caso] ?? [];
    $correo  = $correos[0] ?? null;
    $user    = null !== $correo ? usuario($correo) : null;
    switch ($caso) {
        case 'TC-F-05':
            salida('filas en users con qa-dup@firefly.test', User::query()->where('email', 'qa-dup@firefly.test')->count());
            break;
        case 'TC-F-06':
            salida('filas en users con qa-15@firefly.test', User::query()->where('email', 'qa-15@firefly.test')->count());
            salida('filas en users con qa-16@firefly.test', User::query()->where('email', 'qa-16@firefly.test')->count());
            break;
        case 'TC-F-07':
            salida('filas en users con qatestfirefly.test', User::query()->where('email', 'qatestfirefly.test')->count());
            break;
        case 'TC-F-08':
            salida('filas en users con qa-nuevo@firefly.test', User::query()->where('email', 'qa-nuevo@firefly.test')->count());
            break;
        case 'TC-F-09':
        case 'TC-F-10':
            $buscado = $correo ?? 'noexiste@firefly.test';
            salida("tokens en password_resets para {$buscado}", DB::table('password_resets')->where('email', $buscado)->count());
            break;
        case 'TC-F-11':
            salida('created_at del token en password_resets', (string) DB::table('password_resets')->where('email', $correo)->value('created_at'));
            break;
        case 'TC-F-14':
        case 'TC-F-15':
            salida('mfa_history', (string) preferencia($user, 'mfa_history'));
            salida('mfa_failure_count', (string) preferencia($user, 'mfa_failure_count'));
            break;
        case 'TC-F-16':
            salida('users.mfa_secret', null === $user->mfa_secret ? 'NULL' : 'con valor');
            break;
        case 'TC-F-17':
        case 'TC-F-18':
            salida("filas en users con {$correo}", User::query()->where('email', $correo)->count());
            break;
        case 'TC-F-19':
            salida('filas en users con qa-duena-a@firefly.test', User::query()->where('email', 'qa-duena-a@firefly.test')->count());
            salida('filas en accounts con el user_id de A', Account::query()->withTrashed()->where('user_id', (int) ($GLOBALS['argv'][3] ?? 0))->count());
            break;
        case 'TC-F-20':
            $secreto = json_decode((string) preferencia($user, 'temp-mfa-secret'), true) ?? preferencia($user, 'temp-mfa-secret');
            salida('temp-mfa-secret', (string) $secreto);
            salida('largo', strlen((string) $secreto));
            salida('users.mfa_secret', (string) ($user->mfa_secret ?? 'NULL'));
            break;
        case 'TC-F-59':
            $codigos = json_decode((string) preferencia($user, 'mfa_recovery'), true) ?? [];
            salida('códigos en mfa_recovery', count($codigos));
            salida('el código R sigue en la lista', in_array($GLOBALS['argv'][3] ?? '', $codigos, true) ? 'sí' : 'no');
            break;
        default:
            echo "Este caso no pide revisar la base.\n";
    }
}

/** TC-F-11, paso 3: token T2 con created_at de hace 61 minutos. */
function tokenVencido(): void
{
    $correo = 'qa-token@firefly.test';
    salida('token_vencido', Password::broker()->createToken(usuario($correo)));
    DB::table('password_resets')->where('email', $correo)->update(['created_at' => Carbon::now()->subMinutes(61)]);
    salida('created_at', (string) DB::table('password_resets')->where('email', $correo)->value('created_at'));
}

$comando = $argv[1] ?? '';
$caso    = $argv[2] ?? '';
match ($comando) {
    'preparar'      => preparar($caso),
    'ver'           => ver($caso),
    'token-vencido' => tokenVencido(),
    default         => print("Uso: preparar|ver CASO, token-vencido\n"),
};
