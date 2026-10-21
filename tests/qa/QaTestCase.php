<?php

/*
 * Proyecto de QA, Fase 3 (curso de Aseguramiento de la Calidad del Software).
 *
 * Base de todas las pruebas del proyecto. Cada clase de prueba corresponde a
 * un caso de QAtlas (FF-TC-n / TC-F-nn) y cada método verifica UN solo
 * resultado esperado de un paso del caso:
 *   - setUp() prepara las precondiciones y ejecuta los pasos del caso
 *     (Arrange + Act), guardando las respuestas en propiedades.
 *   - Cada método #[Test] hace un único assert sobre lo guardado (Assert).
 *   - El nombre del método empieza con paso_N_ para ligarlo al paso N del caso.
 *
 * Así cada método es independiente, se cumple "un assert por método" y los
 * casos no dependen unos de otros (cada uno crea sus propios datos en una
 * base SQLite en memoria que se rehace en cada prueba). Los correos no salen:
 * phpunit.qa.xml usa MAIL_MAILER=array.
 */

declare(strict_types=1);

namespace Tests\qa;

use Carbon\Carbon;
use FireflyIII\Enums\AccountTypeEnum;
use FireflyIII\Models\Account;
use FireflyIII\Models\AccountType;
use Laravel\Passport\ClientRepository;
use FireflyIII\Models\GroupMembership;
use FireflyIII\Models\UserGroup;
use FireflyIII\Models\UserRole;
use FireflyIII\Support\Facades\AppConfiguration;
use FireflyIII\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PragmaRX\Google2FALaravel\Facade as Google2FA;
use Tests\integration\CreatesApplication;

abstract class QaTestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    /** Contraseña válida para Firefly III: 16 caracteres o más. */
    protected const string CLAVE_VALIDA = 'QaFirefly-2026-Fase3!';

    /** Corre DatabaseSeeder (roles, monedas, tipos de cuenta, configuración). */
    protected $seed = true;

    /** Cabecera para que Laravel responda JSON (422 con errores en vez de redirigir). */
    protected const array JSON = ['Accept' => 'application/json'];

    protected function tearDown(): void
    {
        // Devuelve el reloj a la hora real por si el caso lo movió.
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Crea un usuario activo con su grupo y rol de dueño, igual que el
     * registro de Firefly III.
     */
    protected function crearUsuario(string $correo, string $clave = self::CLAVE_VALIDA): User
    {
        $grupo = UserGroup::create(['title' => $correo]);
        $rol   = UserRole::query()->where('title', 'owner')->first();
        $user  = User::create([
            'email'         => $correo,
            'password'      => bcrypt($clave),
            'user_group_id' => $grupo->id,
        ]);
        GroupMembership::create(['user_id' => $user->id, 'user_group_id' => $grupo->id, 'user_role_id' => $rol->id]);

        return $user;
    }

    /** Crea un usuario con 2FA activo y devuelve [usuario, secreto TOTP]. */
    protected function crearUsuarioCon2fa(string $correo): array
    {
        $user   = $this->crearUsuario($correo);
        $secreto = Google2FA::generateSecretKey();
        $user->mfa_secret = $secreto;
        $user->save();

        return [$user, $secreto];
    }

    /** Código TOTP vigente para un secreto, como lo calcula una app de autenticación. */
    protected function codigoTotp(string $secreto): string
    {
        return Google2FA::getCurrentOtp($secreto);
    }

    /** POST /login con JSON, como lo hace cada caso Web-HTTP. */
    protected function iniciarSesion(string $correo, string $clave = self::CLAVE_VALIDA)
    {
        return $this->postJson('/login', ['email' => $correo, 'password' => $clave]);
    }

    /**
     * Personal Access Token real de la API (Passport), como el que el usuario
     * crea en Opciones > OAuth. Devuelve [token en texto, id en oauth_access_tokens].
     * Requiere las llaves de Passport (php artisan firefly-iii:laravel-passport-keys).
     */
    protected function crearTokenPersonal(User $user): array
    {
        app(ClientRepository::class)->createPersonalAccessGrantClient('QA Fase 3', 'users');
        $resultado = $user->createToken('qa-fase3');

        return [$resultado->accessToken, $resultado->token->id];
    }

    /** Cuenta de activo (Asset account) del usuario, creada directo en la base. */
    protected function crearCuentaDeActivo(User $user, string $nombre): Account
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

    /** Cabeceras de una petición a /api/v1 con el token del usuario. */
    protected function cabecerasApi(string $token): array
    {
        return ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token];
    }

    /**
     * Habilita el registro: Firefly III trae single_user_mode activo y, con un
     * usuario ya creado, no deja registrar otro (precondición de TC-F-05 a 08).
     */
    protected function habilitarRegistro(): void
    {
        AppConfiguration::set('single_user_mode', false);
    }
}
