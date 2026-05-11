# Lab 10 — Internationalisation (i18n) and OTP Authentication

Vanier College - eCommerce - Tiago Bortoletto Vaz - Winter 2026

---

> **Important — read before you start**
>
> The two features you build in this lab — language switching and OTP authentication — will appear in your **final project** and on your **final exam**. Do not rush through them. The goal is not just to complete the code from my instructions, it is to understand *why* each piece works the way it does. Pay attention to the questions and do your best to answer them in your own words. 
>
> For your final project you are free to implement i18n and OTP using any library or approach you like. In this lab I chose **Symfony Translation** for i18n and **robthree/twofactorauth** (TOTP) for authentication because they are among the easiest to integrate with Slim. Other valid choices exist (e.g. `gettext`, database-stored tokens, email-based random codes), and you are encouraged to explore them for your project.

---

## Overview

In this lab you will add **two features** to the same Todo app from Lab 8.

| Part | Feature | Concepts |
|------|---------|---------|
| 1 | **Language switcher (EN / FR)** | Symfony Translation, PHP sessions, Twig functions |
| 2 | **OTP service** | TOTP (RFC 6238), `robthree/twofactorauth`, authenticator apps |
| 3 | **Auth flow + protected routes** | Controller, invokable middleware, Slim route groups |

Each part builds on the previous one. Parts 2 and 3 only work correctly once Part 1 (sessions) is enabled.

---

## Lab 8 Middleware — Quick Recap

This project ships with the three middleware classes you built in Lab 8, already registered in `index.php`:

```
Request  →  SecurityHeadersMiddleware  →  MaintenanceMiddleware  →  LoggerMiddleware  →  Route
Response ←  SecurityHeadersMiddleware  ←  MaintenanceMiddleware  ←  LoggerMiddleware  ←  Route
```

| Middleware | What it does | Pattern used |
|-----------|-------------|-------------|
| `LoggerMiddleware` | Writes one line per request to `var/app.log` | Closure |
| `MaintenanceMiddleware` | Returns 503 when `var/maintenance.flag` exists | Invokable class (`__invoke`) |
| `SecurityHeadersMiddleware` | Adds security headers to every response | PSR-15 (`implements MiddlewareInterface`) |

In **Part 3** of this lab you will add a fourth middleware — `AuthMiddleware` — using the same invokable class pattern as `MaintenanceMiddleware`. Its job is to protect the `/todos` routes: any unauthenticated request gets redirected to `/auth` before the controller ever runs.

---

## Project Structure

```
todo-app-lab10/
│
├── index.php                        ← Bootstrap: DB, Twig, i18n, DI, middleware, routes
│
├── src/
│   ├── Controllers/
│   │   ├── TodoController.php       ← Pre-filled — do not modify
│   │   └── AuthController.php       ← Parts 2 & 3: requestOtp(), verifyOtp()
│   ├── Middleware/
│   │   ├── MaintenanceMiddleware.php ← From Lab 8 — already wired
│   │   ├── SecurityHeadersMiddleware.php ← From Lab 8 — already wired
│   │   └── AuthMiddleware.php       ← Part 3: implement __invoke()
│   ├── Models/
│   │   └── TodoModel.php            ← Pre-filled — do not modify
│   └── Services/
│       └── OtpService.php           ← Part 2: implement generate() and verify()
│
├── templates/
│   ├── layout.html.twig             ← Base layout with language switcher
│   ├── todos.html.twig              ← Todo list page
│   └── auth.html.twig               ← Login / OTP display / OTP entry
│
├── translations/
│   ├── messages.en.php              ← English catalog (reference — do not modify)
│   └── messages.fr.php              ← Part 1: fill in the French translations
│
├── var/
│   ├── todos.db                     ← SQLite database (auto-created on first run)
│   └── app.log                      ← Request log (from Lab 8 middleware)
│
├── composer.json
└── README.md
```

---

## Setup

### 1. Inspect `composer.json` and install missing requirements

Open `composer.json`. You will see the project's dependencies listed under `"require"`. Notice that `robthree/twofactorauth` and other libraries for this Lab are not declared there — but they need to be present in your `composer.json` file.

> Run these composer commands from your `htdocs/todo-app-lab10` directory.

Use the following command to add them yourself:

```bash
..\composer.bat require robthree/twofactorauth bacon/bacon-qr-code symfony/translation
```

This command updates `composer.json` and `composer.lock` and downloads the packages in one step.

### 2. Install all dependencies

```bash
..\composer.bat install
```

This reads `composer.lock` and installs every package the project needs into the `vendor/` folder: Slim, Twig, RedBeanPHP, Symfony Translation, and the TOTP library.

Make sure you understand these composer commands, they'll be required for your final exam!

### 3. Open the app

Visit [http://localhost/todo-app-lab10/todos](http://localhost/todo-app-lab10/todos)

---

## Reading the Bootstrap `index.php`

Before making any changes, read through `index.php`. It is divided into numbered sections:

```
1. DATABASE         — RedBeanPHP + SQLite (unchanged from Lab 8)
2. TEMPLATE ENGINE  — Twig setup
3. I18N             — Symfony Translator wiring (your work: Part 1)
4. DI CONTAINER     — PHP-DI wires controllers (your work: Part 3)
5. APPLICATION      — Slim app creation
6. MIDDLEWARE       — Logger, Maintenance, SecurityHeaders (already done)
7. HTML ROUTES      — /todos routes (your work: Part 3 — route group)
8. LANGUAGE ROUTE   — /lang/{locale} (your work: Part 1)
9. AUTH ROUTES      — /auth/* routes (pre-filled)
10. DEBUG ROUTE     — /otp-test (remove when you reach Part 3)
11. RUN             — $app->run()
```

> **Do not modify sections 1, 2, 5, 6, 9, 11.**

---

---

# Part 1 — Language Switcher *(i18n)*

## What is i18n?

**Internationalisation** (i18n, because there are 18 letters between i and n) means writing your app so that all user-visible strings can be swapped out for a different language at runtime — without changing the logic.

The standard pattern:

1. Replace hardcoded strings in templates with **translation keys** (`'app.title'`, `'todo.add'`, …).
2. Provide one **catalog file** per language that maps every key to its translation.
3. At runtime, read the user's preferred language from the session and look up each key in the right catalog.

The app uses [Symfony Translation](https://symfony.com/doc/current/translation.html) for steps 2 and 3. Every template call that looks like `{{ trans('some.key') }}` goes through the `Translator` object you will wire in this part.

## PHP Sessions — Quick Reminder

We covered PHP sessions in Lab 4. In this lab sessions carry three pieces of state across requests:

- `$_SESSION['lang']` — the active UI language (`'en'` or `'fr'`)
- `$_SESSION['totp_secret']` — the TOTP secret generated during login setup (Parts 2 & 3)
- `$_SESSION['authenticated']` — whether the user has verified their TOTP code (Part 3)

Remember: `session_start()` must be called before any output is sent and before `$_SESSION` is accessed anywhere. That is why it lives at the very top of `index.php`.

## Step 1 — Enable sessions

Open `index.php`. Near the top, find:

```php
// TODO (Part 1, Step 1): Uncomment the line below to enable sessions.
// session_start();
```

Uncomment `session_start()`.

## Step 2 — Wire the Symfony Translator

In `index.php`, section 3 (I18N), you will find four commented-out blocks. Uncomment and complete them one at a time:

```php
// Step 2a — uncomment and replace __ with the default locale string
// $translator = new Translator('__');

// Step 2b — uncomment and replace __ with the correct ArrayLoader instance
// $translator->addLoader('array', __);

// Step 2c — uncomment and replace __ on the the correct locale string
// $translator->addResource('array', require __DIR__ . '/translations/messages.__.php', '__');
// $translator->addResource('array', require __DIR__ . '/translations/messages.__.php', '__');

// Step 2d — uncomment and replace __ with the variable that holds the active locale
// $twig->addFunction(new TwigFunction('trans', function (string $key, array $params = []) use ($translator) {
//     $locale = $_SESSION['lang'] ?? 'en';
//     return $translator->trans($key, $params, null, __);
// }));
```

Once all four blocks are uncommented and filled in, remove the fallback `trans()` line below them.

## Step 3 — Implement the language route

Find the `/lang/{locale}` route in section 8 of `index.php`:

```php
$app->get('/lang/{locale}', function (Request $request, Response $response, array $args) use ($basePath) {
    $allowed = ['en', 'fr'];

    // TODO (Part 1, Step 3): If $args['locale'] is in $allowed, store it in $_SESSION['lang'].
    //   Then redirect to $basePath . '/todos' with status 302.

    // your code here
});
```

Fill in the body. Use `in_array($args['locale'], $allowed)` to validate the locale before storing it.

## Step 4 — Translate the French catalog

Open `translations/messages.fr.php`. Replace every `'___'` value with the French translation shown in the comment.

**Rules:**
- Keep all keys exactly as they are.
- `'app.lang.en'`, `'app.lang.fr'`, and `'app.footer'` are already filled — do not change them.
- In `'todo.task_count'` your translation **must contain `%count%`** — it is replaced at runtime. If you rename the placeholder the number will not appear.

This is exactly the kind of task you could ask your AI agent for help. Do not forget to review the provided translations, though.

## Verify Part 1

1. Visit the app — all text should appear in English.
2. Click the **FR** link in the top-right corner.
3. The page reloads in French.
4. Click **EN** to switch back.
5. Add a todo — verify the task count string (`%count% tasks total`) updates correctly in both languages.

---

---

# Part 2 — OTP Service *(TOTP via robthree/twofactorauth)*

## What is TOTP?

**TOTP** (Time-based One-Time Password, RFC 6238) is the algorithm behind authenticator apps like Google Authenticator and Authy. Instead of the server generating and sending a code, both the server and the app independently compute the same 6-digit code from two shared inputs:

1. A **secret** — a random Base32 string exchanged once during setup.
2. The **current time** — rounded to a 30-second window.

Because both sides use the same inputs and the same algorithm, they always arrive at the same result without any network exchange. When the user submits the code shown in their app, the server recomputes it and compares.

The setup flow:

```
Server generates secret → renders QR code → user scans with authenticator app
         ↓                                                  ↓
   stores secret in session                    app shows a new 6-digit code every 30s
         ↓
   user submits code → server verifies → authenticated
```

The QR code is rendered server-side using `bacon/bacon-qr-code` as an inline SVG data URI — no external service, no network call, no extra extensions required.

## Step 1 — Implement `OtpService::generate()`

Open `src/Services/OtpService.php`. The `TwoFactorAuth` object is pre-created in the constructor. In `generate()`:

```php
$secret = $this->tfa->____();                              // generate a new Base32 secret
$_SESSION['totp_secret'] = ____;                           // store it in the session

return $this->tfa->getQRCodeImageAsDataUri(____, ____);    // return QR code data URI
```

**Hints:**
- `createSecret()` generates a new random Base32 secret string.
- `getQRCodeImageAsDataUri($label, $secret)` renders the QR code and returns a `data:image/svg+xml;base64,...` string you can put directly in an `<img>` tag.

## Step 2 — Implement `OtpService::verify()`

```php
$secret = $_SESSION['totp_secret'] ?? null;

if (!____) { return ____; }                       // guard: no secret in session

return $this->tfa->verifyCode(____, ____);         // verify the submitted code
```

**Hints:**
- First blank: check that `$secret` is not null. Check the possible return from the function signature.
- `verifyCode(__, __)` returns `true` if the code matches the current 30-second window.

## Verify Part 2

Visit [http://localhost/todo-app-lab10/otp-test](http://localhost/todo-app-lab10/otp-test)

You should see something like:

```
Secret           : JBSWY3DPEHPK3PXP
Current code     : 483921
verify(correct)  : true ✓
verify('000000') : false ✗
```

The secret and code will be different each run, and the code changes every 30 seconds. This route bypasses `OtpService` and calls the library directly — it is only here to confirm the library works before you implement `OtpService`. Remove it from `index.php` before starting Part 3.

**Note on Implementation Limitations:**
This implementation uses a basic "register every time" approach where users must scan the QR code on each login. For your final project, you'll need to implement a more robust "register once, enter code forever" workflow. For that, you can follow the steps (not required for this Lab, but you're welcome to implement it here if you wish!):

- Create a `users` database table with columns: `id`, `username`, `totp_secret`, `created_at`
- Store the TOTP secret in the database instead of session
- On login: check if user exists, if no secret generate and store one, if secret exists skip QR code
- Add user management features: registration, password reset, account deletion
- Consider secret rotation and revocation mechanisms
- Implement proper error handling for duplicate usernames

---

---

# Part 3 — Auth Flow

## Overview

With the OTP service ready you can now wire up the full authentication flow:

```
GET  /auth          → show username form
POST /auth/request  → generate OTP, display it on screen
GET  /auth/verify   → show OTP entry form
POST /auth/verify   → check OTP → redirect to /todos or show error
POST /auth/logout   → destroy session → redirect to /auth
```

`/todos` and its sub-routes are protected: `AuthMiddleware` checks `$_SESSION['authenticated']`
before every request and redirects to `/auth` if the user is not logged in.

## Step 1 — Implement `AuthMiddleware::__invoke()`

Open `src/Middleware/AuthMiddleware.php`.

```php
public function __invoke(Request $request, RequestHandler $handler): Response
{
    // 1. Check $_SESSION['authenticated'] === true
    // 2. If NOT: redirect to $this->basePath . '/auth' with status 302
    // 3. If yes: call $handler->handle($request) and return the result
}
```

Use `$this->responseFactory->createResponse(302)->withHeader('Location', ...)` for the redirect.

## Step 2 — Implement `AuthController::requestOtp()`

Open `src/Controllers/AuthController.php`. Read the docblock, then implement the method:

1. Read `username` from `$request->getParsedBody()`. Trim it. If empty, redirect to `GET /auth`.
2. Store username in `$_SESSION['username']`.
3. Call `$this->otpService->generate($username)` — it stores the secret in the session and returns the QR code as a data URI.
4. Render `auth.html.twig` with `step = 'otp_display'` and `qr_code = $uri`.

## Step 3 — Implement `AuthController::verifyOtp()`

1. Read `code` from the POST body. Trim it.
2. Call `$this->otpService->verify($code)`.
3. If **true**: call `invalidate()`, set `$_SESSION['authenticated'] = true`, redirect to `/todos`.
4. If **false**: render `auth.html.twig` with `step = 'verify'` and `error = 'auth.error_invalid'`.

## Step 4 — Wire `AuthController` and the protected route group

### Step 4a — Register `AuthController` in the DI container

In `index.php`, section 4, fill in the three blanks:

```php
$container->set(AuthController::class, fn() => new AuthController(
    ____,   // the Twig environment
    ____,   // a new OtpService instance
    ____    // the $basePath string
));
```

### Step 4b — Replace flat routes with a route group

In section 7 of `index.php`, replace the four flat `/todos` routes with the group shown in the comment block:

```php
$app->group('/todos', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('',              [TodoController::class, 'index']);
    $group->post('',             [TodoController::class, 'store']);
    $group->post('/{id}/toggle', [TodoController::class, 'toggle']);
    $group->post('/{id}/delete', [TodoController::class, 'destroy']);
})->add(new AuthMiddleware(
    responseFactory: $app->getResponseFactory(),
    basePath:        $basePath,
));
```

## Verify Part 3

1. Visit [http://localhost/todo-app-lab10/todos](http://localhost/todo-app-lab10/todos) — you should be redirected to `/auth`.
2. Enter any username and click **Send Code**.
3. A QR code appears — scan it with Google Authenticator, Authy, or any TOTP app.
4. Click **Continue to verification**, enter the 6-digit code shown in your app.
5. You should be redirected to the todo list.
6. Click **Log out** — you should be back at the login screen.
7. Try entering a wrong code — you should see the error message in the current language.

---

---

# Reflection

At the end of this lab the full request path for an authenticated `/todos` request looks like this:

```
Request
  → SecurityHeadersMiddleware   (add response headers on the way back)
  → MaintenanceMiddleware        (503 if flag file present)
  → LoggerMiddleware             (record timing on the way back)
  → AuthMiddleware               (redirect to /auth if not authenticated)
  → TodoController::index()      (build the HTML response)
Response ←──────────────────────────────────────────────────────────
```

And for an unauthenticated request to `/todos`:

```
Request
  → SecurityHeadersMiddleware
  → MaintenanceMiddleware
  → LoggerMiddleware
  → AuthMiddleware               (detects $_SESSION['authenticated'] is not set)
    ↳ returns 302 → /auth        (short-circuits; TodoController never runs)
Response ←──────────────────────────────────────────────────────────
```

The `/auth/*` routes are **outside** the group, so `AuthMiddleware` never runs for them — unauthenticated users can always reach the login page.

---

---

# Submission

Submit a **PDF only** containing your written answers to the **7 reflection questions** (Q1 through Q7). One or two paragraphs per question is enough — focus on explaining the concept, not copying code.

**Do not submit your code.** Keep the project in your Wampoon `htdocs` folder exactly as it is. The teacher may ask you to do a **live demo** to validate your grade, so the app must run on your machine.
