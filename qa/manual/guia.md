# Ejecución manual de los casos de Marcos (Fase 3)

Guía generada con `qa/manual/generar_guia.py`. Los resultados esperados son los de QAtlas (versión 3).

**Esto no lo puede hacer la IA ni un script.** El profesor pidió que cada integrante ejecute sus casos a mano. La colección trae verificaciones de ayuda, pero el resultado lo decide quien mira la respuesta y la compara con el caso. Si ves algo distinto a lo que dice esta guía, se registra lo que viste.

## Antes de empezar (una sola vez)

1. Instalar Postman e importar los dos archivos de `qa/manual/postman/`. Elegir el entorno **Firefly local (QA)** arriba a la derecha.
2. En Postman, abrir **Settings** y apagar **Automatically follow redirects**. La colección ya lo pide petición por petición, pero así se evitan sorpresas.
3. Levantar Firefly y crear la ejecución en QAtlas:

```bash
sh qa/manual/qa.sh arrancar
python qa/manual/manual_qatlas.py crear
```

## Cómo se ejecuta cada caso

1. `sh qa/manual/qa.sh preparar TC-F-XX` borra y crea los datos del caso. Copiá cada valor que muestra a la variable del entorno con el mismo nombre (`correo`, `clave`, `secreto_totp`, etc.).
2. Abrí la carpeta del caso en Postman y enviá las peticiones **una por una, en orden**. Las que dicen `Preparar` no son pasos del caso: solo dejan lista la sesión o leen el token CSRF.
3. Después de cada paso, mirá el código, el cuerpo y la pestaña **Test Results**, y compará con el resultado esperado.
4. Sacá la captura y guardala como `qa/manual/capturas/TC-F-XX/paso-N.png`. Tiene que verse la petición y la respuesta.
5. Marcá el paso, en QAtlas o con el comando:

```bash
python qa/manual/manual_qatlas.py marcar TC-F-XX N aprobado "HTTP 204 sin cuerpo"
```

Al terminar todos los casos:

```bash
python qa/manual/manual_qatlas.py capturas
python qa/manual/manual_qatlas.py estado
python qa/manual/manual_qatlas.py cerrar
```

## Por qué hay peticiones de más

- **Token CSRF:** el servidor real responde 500 a un POST sin token, y PHPUnit lo desactiva. Laravel además lo cambia al iniciar sesión, por eso se vuelve a leer después del login.
- **Mensajes de sesión:** una respuesta 302 no muestra el mensaje. Se ve en la página siguiente, y por eso algunos pasos tienen una segunda petición.
- **TOTP:** la colección calcula el código con `secreto_totp`, como una app de autenticación. Queda en la variable `totp`.

## Lo que ya se sabe (ensayo del 2026-11-01)

La colección se ensayó completa con Newman contra el Firefly real para comprobar que la guía funciona. Ese ensayo **no es evidencia** ni se subió a QAtlas. Dos pasos no dieron lo esperado, y son los defectos que ya están registrados:

- **TC-F-04, paso 3:** responde HTTP 500 en vez de 429, tras esperar hasta 60 s (FF-BUG-1).
- **TC-F-19, paso 3:** responde HTTP 401 en vez de 404 (FF-BUG-2).

Si al ejecutar se repiten, el paso va como `fallido` y se indica el defecto.

## Casos

### TC-F-01 Login exitoso con credenciales válidas

```bash
sh qa/manual/qa.sh preparar TC-F-01
```

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Enviar POST /login con email y password correctos. | HTTP 204 sin cuerpo. |
| 2 | Verificar el usuario autenticado en la sesión. | La sesión queda autenticada con el id de qa-login@firefly.test. |

**Orden en Postman:**

1. Preparar: GET /login (lee el token CSRF)
2. Paso 1: POST /login
3. Paso 2: GET /profile con la misma sesión

### TC-F-02 Login con contraseña incorrecta

```bash
sh qa/manual/qa.sh preparar TC-F-02
```

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Enviar POST /login con el email correcto y la password incorrecta. | HTTP 422. |
| 2 | Revisar el campo errors.email del JSON de respuesta. | Contiene exactamente "These credentials do not match our records." |
| 3 | Verificar el estado de la sesión. | La sesión no tiene usuario autenticado. |

**Orden en Postman:**

1. Preparar: GET /login (lee el token CSRF)
2. Paso 1: POST /login
3. Paso 3: GET /profile con la misma sesión

### TC-F-03 Login con correo inexistente

```bash
sh qa/manual/qa.sh preparar TC-F-03
```

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Enviar POST /login con noexiste@firefly.test y la contraseña. | HTTP 422. |
| 2 | Revisar el campo errors.email del JSON de respuesta. | Contiene exactamente "These credentials do not match our records.", el mismo texto genérico que recibe una contraseña incorrecta. |

**Orden en Postman:**

1. Preparar: GET /login (lee el token CSRF)
2. Paso 1: POST /login

### TC-F-04 Bloqueo tras intentos fallidos consecutivos

```bash
sh qa/manual/qa.sh preparar TC-F-04
```

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Enviar POST /login con la password incorrecta. | HTTP 422 con "These credentials do not match our records." |
| 2 | Repetir el POST /login con la password incorrecta. | HTTP 422 con "These credentials do not match our records." |
| 3 | Enviar un tercer POST /login, esta vez con la password correcta. | HTTP 429: el login queda bloqueado aunque la contraseña sea correcta. Firefly espera hasta 60 s antes de responder. |
| 4 | Cuando responde el paso 3, enviar POST /login con la password correcta. | HTTP 204: el bloqueo es temporal y ya se levantó. |

**Orden en Postman:**

1. Preparar: GET /login (lee el token CSRF)
2. Paso 1: POST /login
3. Paso 2: POST /login
4. Paso 3: POST /login
5. Paso 4: POST /login

### TC-F-05 Registro con correo ya registrado

```bash
sh qa/manual/qa.sh preparar TC-F-05
```

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Enviar POST /register con email qa-dup@firefly.test, password de 16 caracteres y password_confirmation igual. | HTTP 422 con un error en el campo email. |
| 2 | Contar en la tabla users los registros con ese correo. | Hay exactamente 1. |

**Orden en Postman:**

1. Preparar: GET /register (lee el token CSRF)
2. Paso 1: POST /register

**Fuera de Postman (paso 2):** Contar las filas con ese correo: tiene que dar 1.

```bash
sh qa/manual/qa.sh ver TC-F-05
```


### TC-F-06 Registro con contraseña de 15 caracteres es rechazado

```bash
sh qa/manual/qa.sh preparar TC-F-06
```

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Enviar POST /register con qa-15@firefly.test, la password de 15 caracteres y password_confirmation igual. | HTTP 422 con un error en el campo password. |
| 2 | Buscar qa-15@firefly.test en la tabla users. | No existe. |
| 3 | Enviar POST /register con qa-16@firefly.test, la password de 16 caracteres y password_confirmation igual. | HTTP 302 y qa-16@firefly.test existe en la tabla users. |

**Orden en Postman:**

Carpeta *TC-F-06 parte 1*:

1. Preparar: GET /register (lee el token CSRF)
2. Paso 1: POST /register

**Fuera de Postman (paso 2):** qa-15@firefly.test tiene que dar 0 filas.

```bash
sh qa/manual/qa.sh ver TC-F-06
```

Carpeta *TC-F-06 parte 2*:

3. Paso 3: POST /register

**Fuera de Postman (paso 3):** qa-16@firefly.test tiene que dar 1 fila.

```bash
sh qa/manual/qa.sh ver TC-F-06
```


### TC-F-07 Registro con correo sin formato válido

```bash
sh qa/manual/qa.sh preparar TC-F-07
```

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Enviar POST /register con el correo mal formado, la password y password_confirmation igual. | HTTP 422 con un error en el campo email. |
| 2 | Buscar el correo en la tabla users. | No existe. |

**Orden en Postman:**

1. Preparar: GET /register (lee el token CSRF)
2. Paso 1: POST /register

**Fuera de Postman (paso 2):** El correo mal formado tiene que dar 0 filas.

```bash
sh qa/manual/qa.sh ver TC-F-07
```


### TC-F-08 Registro exitoso permite iniciar sesión de inmediato

```bash
sh qa/manual/qa.sh preparar TC-F-08
```

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Enviar POST /register con qa-nuevo@firefly.test, la password y password_confirmation igual. | HTTP 302 y qa-nuevo@firefly.test existe en la tabla users. |
| 2 | Enviar POST /logout con la misma sesión. | HTTP 204. |
| 3 | Enviar POST /login con las mismas credenciales. | HTTP 204. |

**Orden en Postman:**

Carpeta *TC-F-08 parte 1*:

1. Preparar: GET /register (lee el token CSRF)
2. Paso 1: POST /register

**Fuera de Postman (paso 1):** qa-nuevo@firefly.test tiene que dar 1 fila.

```bash
sh qa/manual/qa.sh ver TC-F-08
```

Carpeta *TC-F-08 parte 2*:

3. Preparar: GET /profile (lee el token CSRF)
4. Paso 2: POST /logout
5. Preparar: GET /login (lee el token CSRF)
6. Paso 3: POST /login

### TC-F-09 Solicitar reseteo de contraseña con correo registrado

```bash
sh qa/manual/qa.sh preparar TC-F-09
```

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Enviar POST /password/email con email qa-reset@firefly.test. | HTTP 302. |
| 2 | Revisar el mensaje status de la sesión. | Es exactamente "Thank you. If an account exists with this email address, you will find instructions in your inbox." |
| 3 | Buscar el correo en la tabla password_resets. | Hay 1 token para qa-reset@firefly.test. |

**Orden en Postman:**

1. Preparar: GET /password/reset (lee el token CSRF)
2. Paso 1: POST /password/email
3. Paso 2: GET /password/reset (mensaje de la sesión)

**Fuera de Postman (paso 3):** Tiene que haber 1 token para qa-reset@firefly.test.

```bash
sh qa/manual/qa.sh ver TC-F-09
```


### TC-F-10 Solicitar reseteo con correo no registrado

```bash
sh qa/manual/qa.sh preparar TC-F-10
```

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Enviar POST /password/email con email noexiste@firefly.test. | HTTP 302. |
| 2 | Revisar el mensaje status de la sesión. | Es exactamente "Thank you. If an account exists with this email address, you will find instructions in your inbox.", el mismo texto que recibe un correo registrado. |
| 3 | Buscar el correo en la tabla password_resets. | No hay ningún token para ese correo. |

**Orden en Postman:**

1. Preparar: GET /password/reset (lee el token CSRF)
2. Paso 1: POST /password/email
3. Paso 2: GET /password/reset (mensaje de la sesión)

**Fuera de Postman (paso 3):** Tiene que haber 0 tokens para noexiste@firefly.test.

```bash
sh qa/manual/qa.sh ver TC-F-10
```


### TC-F-11 Token de reseteo ya usado y token vencido son rechazados

```bash
sh qa/manual/qa.sh preparar TC-F-11
```

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Enviar POST /password/reset con T1, el email, password = P1 y password_confirmation = P1. | HTTP 200 y la contraseña del usuario pasa a ser P1. |
| 2 | Repetir el POST /password/reset con T1, ahora con P2. | HTTP 422 con "This password reset token is invalid." en errors.email. |
| 3 | Generar un token nuevo T2 para el usuario y cambiar su created_at en password_resets a hace 61 minutos. | La fila de T2 queda con esa fecha. |
| 4 | Enviar POST /password/reset con T2 y P2. | HTTP 422 con "This password reset token is invalid." en errors.email. |
| 5 | Enviar POST /login con el email y P2. | HTTP 422: P2 nunca quedó como contraseña. |

**Orden en Postman:**

Carpeta *TC-F-11 parte 1*:

1. Preparar: GET /login (lee el token CSRF)
2. Paso 1: POST /password/reset
3. Preparar: GET /profile (lee el token CSRF)
4. Preparar: POST /logout
5. Preparar: GET /login (lee el token CSRF)
6. Paso 2: POST /password/reset

**Fuera de Postman (paso 3):** Generar T2 con created_at de hace 61 minutos. Copiar token_vencido a la variable del entorno.

```bash
sh qa/manual/qa.sh token-vencido
```

Carpeta *TC-F-11 parte 2*:

7. Paso 4: POST /password/reset
8. Paso 5: POST /login

### TC-F-12 Cerrar sesión invalida la sesión

```bash
sh qa/manual/qa.sh preparar TC-F-12
```

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Enviar POST /logout con la cookie de sesión. | HTTP 204. |
| 2 | Enviar GET / con la misma cookie. | HTTP 401: la sesión ya no sirve. |

**Orden en Postman:**

1. Preparar: GET /login (lee el token CSRF)
2. Preparar: POST /login
3. Preparar: GET /profile (lee el token CSRF)
4. Paso 1: POST /logout
5. Paso 2: GET /

### TC-F-13 Operación de la API con token vencido es rechazada

```bash
sh qa/manual/qa.sh preparar TC-F-13
```

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Enviar GET /api/v1/accounts con el token vencido. | HTTP 401. |
| 2 | Revisar el cuerpo de la respuesta. | Trae el campo message y no trae el arreglo data con cuentas. |

**Orden en Postman:**

1. Paso 1: GET /api/v1/accounts con el token vencido

### TC-F-14 Login con 2FA activo y código correcto

```bash
sh qa/manual/qa.sh preparar TC-F-14
```

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Enviar POST /login con email y password correctos. | HTTP 204. |
| 2 | Generar el código TOTP vigente con el secreto y enviarlo en POST /two-factor/submit como one_time_password. | HTTP 302 con Location /home. |
| 3 | Leer las preferencias mfa_history y mfa_failure_count del usuario. | mfa_history contiene el código enviado y mfa_failure_count vale 0. |

**Orden en Postman:**

1. Preparar: GET /login (lee el token CSRF)
2. Paso 1: POST /login
3. Preparar: GET /home (lee el token CSRF)
4. Paso 2: POST /two-factor/submit con el código vigente

**Fuera de Postman (paso 3):** mfa_history contiene el código enviado y mfa_failure_count vale 0.

```bash
sh qa/manual/qa.sh ver TC-F-14
```


### TC-F-15 Login con 2FA: código incorrecto o reutilizado es rechazado

```bash
sh qa/manual/qa.sh preparar TC-F-15
```

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Enviar POST /login con email y password correctos. | HTTP 204. |
| 2 | Enviar POST /two-factor/submit con one_time_password = 000000 (código incorrecto). | HTTP 302, la sesión trae el error "This MFA code is not valid." y mfa_failure_count pasa a 1. |
| 3 | Enviar POST /two-factor/submit con el código TOTP vigente C. | HTTP 302, mfa_history contiene C y mfa_failure_count vuelve a 0. |
| 4 | Enviar POST /logout y luego POST /login con email y password. | HTTP 204 en los dos. |
| 5 | Antes de 300 s desde el paso 3, enviar POST /two-factor/submit con el mismo código C. | HTTP 302 y la sesión trae el error "This MFA code is not valid.": el código ya usado no sirve. |

**Orden en Postman:**

Carpeta *TC-F-15 parte 1*:

1. Preparar: GET /login (lee el token CSRF)
2. Paso 1: POST /login
3. Preparar: GET /home (lee el token CSRF)
4. Paso 2: POST /two-factor/submit con 000000
5. Paso 2: GET /home (mensaje de la sesión)

**Fuera de Postman (paso 2):** mfa_failure_count tiene que valer 1.

```bash
sh qa/manual/qa.sh ver TC-F-15
```

Carpeta *TC-F-15 parte 2*:

6. Paso 3: POST /two-factor/submit con el código vigente C

**Fuera de Postman (paso 3):** mfa_history contiene C y mfa_failure_count vuelve a 0.

```bash
sh qa/manual/qa.sh ver TC-F-15
```

Carpeta *TC-F-15 parte 3*:

7. Preparar: GET /profile (lee el token CSRF)
8. Paso 4: POST /logout
9. Preparar: GET /login (lee el token CSRF)
10. Paso 4: POST /login
11. Preparar: GET /home (lee el token CSRF)
12. Paso 5: POST /two-factor/submit con el mismo C
13. Paso 5: GET /home (mensaje de la sesión)

### TC-F-16 Desactivar 2FA requiere contraseña y código

```bash
sh qa/manual/qa.sh preparar TC-F-16
```

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Enviar POST /mfa/disableMFA solo con code (sin password). | HTTP 422 con un error en el campo password. |
| 2 | Enviar POST /mfa/disableMFA solo con password (sin code). | HTTP 422 con un error en el campo code. |
| 3 | Revisar users.mfa_secret del usuario. | Sigue con valor: 2FA sigue activo. |
| 4 | Enviar POST /mfa/disableMFA con password correcta y un código vigente nuevo. | HTTP 302 y users.mfa_secret queda en NULL. |

**Orden en Postman:**

Carpeta *TC-F-16 parte 1*:

1. Preparar: GET /login (lee el token CSRF)
2. Preparar: POST /login
3. Preparar: GET /home (lee el token CSRF)
4. Preparar: POST /two-factor/submit
5. Preparar: GET /profile (lee el token CSRF)
6. Paso 1: POST /mfa/disableMFA solo con code
7. Paso 2: POST /mfa/disableMFA solo con password

**Fuera de Postman (paso 3):** Revisar que 2FA sigue activo (users.mfa_secret con valor). Esperar 31 s para que el código cambie: el paso 4 pide un código nuevo.

```bash
sh qa/manual/qa.sh ver TC-F-16
```

Carpeta *TC-F-16 parte 2*:

8. Paso 4: POST /mfa/disableMFA con password y código nuevo

**Fuera de Postman (paso 4):** users.mfa_secret tiene que quedar en NULL.

```bash
sh qa/manual/qa.sh ver TC-F-16
```


### TC-F-17 Eliminar cuenta de usuario con contraseña incorrecta no la borra

```bash
sh qa/manual/qa.sh preparar TC-F-17
```

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Enviar POST /profile/delete-account sin el campo password. | HTTP 422 con un error en el campo password. |
| 2 | Enviar POST /profile/delete-account con una password incorrecta. | HTTP 302 con Location /profile/delete-account y la sesión trae el error "Invalid password!". |
| 3 | Buscar qa-borrar@firefly.test en la tabla users. | Sigue existiendo. |

**Orden en Postman:**

1. Preparar: GET /login (lee el token CSRF)
2. Preparar: POST /login
3. Preparar: GET /profile (lee el token CSRF)
4. Paso 1: POST /profile/delete-account sin password
5. Paso 2: POST /profile/delete-account con password incorrecta
6. Paso 2: GET /profile/delete-account (mensaje de la sesión)

**Fuera de Postman (paso 3):** qa-borrar@firefly.test tiene que seguir existiendo: 1 fila.

```bash
sh qa/manual/qa.sh ver TC-F-17
```


### TC-F-18 Login con credenciales de una cuenta eliminada falla

```bash
sh qa/manual/qa.sh preparar TC-F-18
```

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Enviar POST /profile/delete-account con la password correcta. | HTTP 302 y qa-eliminado@firefly.test ya no existe en la tabla users. |
| 2 | Enviar POST /login con el correo y la contraseña del usuario eliminado. | HTTP 422 con "These credentials do not match our records." en errors.email. |

**Orden en Postman:**

Carpeta *TC-F-18 parte 1*:

1. Preparar: GET /login (lee el token CSRF)
2. Preparar: POST /login
3. Preparar: GET /profile (lee el token CSRF)
4. Paso 1: POST /profile/delete-account con la password correcta

**Fuera de Postman (paso 1):** qa-eliminado@firefly.test tiene que dar 0 filas.

```bash
sh qa/manual/qa.sh ver TC-F-18
```

Carpeta *TC-F-18 parte 2*:

5. Preparar: GET /login (lee el token CSRF)
6. Paso 2: POST /login

### TC-F-19 Datos financieros tras eliminar el usuario

```bash
sh qa/manual/qa.sh preparar TC-F-19
```

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Con la sesión de A, enviar POST /profile/delete-account con su password. | HTTP 302 y A ya no existe en la tabla users. |
| 2 | Contar en la tabla accounts las filas con el user_id de A. | 0 filas. |
| 3 | Con el token de B, enviar GET /api/v1/accounts/{id de la cuenta de A}. | HTTP 404. |

**Orden en Postman:**

Carpeta *TC-F-19 parte 1*:

1. Preparar: GET /login (lee el token CSRF)
2. Preparar: POST /login
3. Preparar: GET /profile (lee el token CSRF)
4. Paso 1: POST /profile/delete-account de A

**Fuera de Postman (paso 1, 2):** A tiene que dar 0 filas en users y 0 filas en accounts.

```bash
sh qa/manual/qa.sh ver TC-F-19 {{id_usuario_a}}
```

Carpeta *TC-F-19 parte 2*:

5. Paso 3: GET /api/v1/accounts/{id de A} con el token de B

### TC-F-20 Generación de secreto de 2FA con formato TOTP válido

```bash
sh qa/manual/qa.sh preparar TC-F-20
```

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Enviar GET /mfa/enableMFA. | HTTP 200. |
| 2 | Leer la preferencia temp-mfa-secret del usuario. | 32 caracteres, todos del alfabeto Base32 (A-Z y 2-7). |
| 3 | Enviar POST /mfa/enableMFA con la password y un código TOTP generado con ese secreto. | HTTP 302 con Location /mfa/backup-codes y users.mfa_secret queda igual al secreto del paso 2. |

**Orden en Postman:**

Carpeta *TC-F-20 parte 1*:

1. Preparar: GET /login (lee el token CSRF)
2. Preparar: POST /login
3. Paso 1: GET /mfa/enableMFA

**Fuera de Postman (paso 2):** temp-mfa-secret: 32 caracteres, todos de A-Z y 2-7.

```bash
sh qa/manual/qa.sh ver TC-F-20
```

Carpeta *TC-F-20 parte 2*:

4. Paso 3: POST /mfa/enableMFA con password y código

**Fuera de Postman (paso 3):** users.mfa_secret tiene que ser igual al secreto del paso 2.

```bash
sh qa/manual/qa.sh ver TC-F-20
```


### TC-F-59 Login con código de respaldo de 2FA, que sirve una sola vez

```bash
sh qa/manual/qa.sh preparar TC-F-59
```

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Enviar POST /login con email y password correctos. | HTTP 204. |
| 2 | Enviar POST /two-factor/submit con one_time_password = R. | HTTP 302 y la sesión trae el aviso "You have used a backup code to login to Firefly III. It can't be used again, so cross it from your list." |
| 3 | Leer la preferencia mfa_recovery. | Tiene 7 códigos y R no está entre ellos. |
| 4 | Enviar POST /logout y luego POST /login con email y password. | HTTP 204 en los dos. |
| 5 | Enviar POST /two-factor/submit con el mismo R. | HTTP 302 y la sesión trae el error "This MFA code is not valid." |

**Orden en Postman:**

Carpeta *TC-F-59 parte 1*:

1. Preparar: GET /login (lee el token CSRF)
2. Paso 1: POST /login
3. Preparar: GET /home (lee el token CSRF)
4. Paso 2: POST /two-factor/submit con el código de respaldo R
5. Paso 2: GET /profile (aviso de la sesión)

**Fuera de Postman (paso 3):** mfa_recovery tiene 7 códigos y R ya no está.

```bash
sh qa/manual/qa.sh ver TC-F-59 {{codigo_respaldo}}
```

Carpeta *TC-F-59 parte 2*:

6. Preparar: GET /profile (lee el token CSRF)
7. Paso 4: POST /logout
8. Preparar: GET /login (lee el token CSRF)
9. Paso 4: POST /login
10. Preparar: GET /home (lee el token CSRF)
11. Paso 5: POST /two-factor/submit con el mismo R
12. Paso 5: GET /home (mensaje de la sesión)

### TC-U-01 Ventana de 300 s contra reuso de códigos 2FA

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Fijar el reloj en T + 299 s e invocar inMFAHistory con 123456. | Devuelve true. |
| 2 | Fijar el reloj en T + 300 s e invocar inMFAHistory con 123456. | Devuelve true: el borde es inclusivo. |
| 3 | Fijar el reloj en T + 301 s e invocar inMFAHistory con 123456. | Devuelve false. |

Se ejecuta por comando porque prueba un método interno, sin pantalla ni API. Queda pendiente confirmar con el profesor si esta forma le sirve.

```bash
MSYS_NO_PATHCONV=1 docker run --rm -v "$(pwd -W):/app" -v firefly-qa-vendor:/app/vendor firefly-qa:php8.5 \
  vendor/bin/phpunit -c phpunit.qa.xml --filter TcU01VentanaHistorialMfa --testdox
```

Captura: la terminal con el comando y el resultado, en `qa/manual/capturas/TC-U-01/`.


### TC-U-02 Generación de secreto TOTP con formato válido

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Invocar generateSecretKey(). | Devuelve una cadena de 32 caracteres. |
| 2 | Validar la cadena contra la expresión ^[A-Z2-7]{32}$. | Coincide. |
| 3 | Invocar getQRCodeUrl("Firefly III", "qa-2fa@firefly.test", secreto). | Devuelve un URI que empieza con otpauth://totp/ y contiene issuer=Firefly%20III y secret=<secreto>. |

Se ejecuta por comando porque prueba un método interno, sin pantalla ni API. Queda pendiente confirmar con el profesor si esta forma le sirve.

```bash
MSYS_NO_PATHCONV=1 docker run --rm -v "$(pwd -W):/app" -v firefly-qa-vendor:/app/vendor firefly-qa:php8.5 \
  vendor/bin/phpunit -c phpunit.qa.xml --filter TcU02SecretoTotp --testdox
```

Captura: la terminal con el comando y el resultado, en `qa/manual/capturas/TC-U-02/`.


### TC-P-01 Carga normal en login

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Configurar 50 hilos, rampa de 20 s y duración de 120 s: cada iteración pide GET /login, extrae _token y envía POST /login con email, password y _token. | El plan valida con una aserción de código 204 en POST /login. |
| 2 | Ejecutar el plan con jmeter -n. | La corrida termina y genera el archivo de resultados .jtl. |
| 3 | Revisar el Summary Report de POST /login. | Promedio ≤ 500 ms y tasa de error < 1%. |

Se ejecuta con JMeter en modo sin interfaz. **Antes de medir:** cerrá lo que esté consumiendo la compu y corré una vez el plan sin registrar el resultado, para que Firefly no arranque en frío. El ensayo en frío y con la compu ocupada dio tiempos varias veces peores.

```bash
sh qa/manual/qa.sh arrancar
cd qa/rendimiento
"$HOME/tools/apache-jmeter-5.6.3/bin/jmeter.bat" -n -t jmeter/login-carga.jmx -l resultados/manual-tc-p-01.jtl
python analizar.py resultados/manual-tc-p-01.jtl 
```

Captura: la terminal con el resumen de `analizar.py`, en `qa/manual/capturas/TC-P-01/`.


### TC-P-06 Pico repentino en login

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Configurar un pico de 0 a 300 hilos en 5 s, sostenerlo 10 s y bajar a 50 hilos hasta completar 30 s: cada iteración pide GET /login, extrae _token y envía POST /login. | El plan valida con una aserción de código 204 en POST /login. |
| 2 | Ejecutar el plan con jmeter -n. | La corrida termina y genera el archivo .jtl. |
| 3 | Calcular la tasa de error de los primeros 10 s. | Menor a 5%. |
| 4 | Calcular el promedio de respuesta de POST /login en los últimos 10 s (carga de vuelta en 50 hilos). | ≤ 500 ms: el login se recuperó del pico. |

Se ejecuta con JMeter en modo sin interfaz. **Antes de medir:** cerrá lo que esté consumiendo la compu y corré una vez el plan sin registrar el resultado, para que Firefly no arranque en frío. El ensayo en frío y con la compu ocupada dio tiempos varias veces peores.

```bash
sh qa/manual/qa.sh arrancar
cd qa/rendimiento
"$HOME/tools/apache-jmeter-5.6.3/bin/jmeter.bat" -n -t jmeter/login-spike.jmx -l resultados/manual-tc-p-06.jtl
python analizar.py resultados/manual-tc-p-06.jtl 0 10
```

Captura: la terminal con el resumen de `analizar.py`, en `qa/manual/capturas/TC-P-06/`.

Para el paso 4, repetir `analizar.py` con la ventana `20 30`.

