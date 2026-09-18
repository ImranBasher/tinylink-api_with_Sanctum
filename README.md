# TinyLink API

A Laravel 13 URL shortener API using MySQL and Sanctum bearer tokens.

## Setup

Requirements: PHP 8.3+, Composer, MySQL, and the PHP `pdo_mysql` extension.

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Create a MySQL database named `tinylink_api`, then set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env` for your server. The example uses `127.0.0.1:3306`, database `tinylink_api`, user `root`, and a blank password.

```powershell
php artisan migrate
php artisan serve
```

The API is available at `http://127.0.0.1:8000`. To add two sample users with three URLs each, run `php artisan db:seed`. The seeder is optional.

For tests, create a separate `tinylink_api_test` MySQL database. The test connection is set in `phpunit.xml`; update its credentials if needed, then run `php artisan test`.

## Authentication and response format

Call `POST /api/register` or `POST /api/login` to obtain `data.token`. Send that token on protected routes:

```http
Authorization: Bearer <token>
Accept: application/json
```

Successful API responses have `success`, `message`, and `data`. Errors have `success: false` and `message`; validation errors also include field-specific `errors`.

```json
{"success":false,"message":"Validation failed.","errors":{"email":["The email field must be a valid email address."]}}
```

Unauthenticated requests receive HTTP 401; access to another user's URL receives HTTP 403; invalid inputs receive HTTP 422; missing URLs receive HTTP 404.

## Endpoints and examples

Examples use `http://127.0.0.1:8000` as the base URL. JSON examples show the response body; IDs, tokens, and generated short codes vary.

### Register — `POST /api/register`

Request:

```json
{"name":"Ada","email":"ada@example.com","password":"secret123","password_confirmation":"secret123"}
```

HTTP 201:

```json
{"success":true,"message":"Registered successfully.","data":{"user":{"id":1,"name":"Ada","email":"ada@example.com"},"token":"1|example-token"}}
```

Name, valid unique email, and a password of at least eight characters with matching confirmation are required.

### Login — `POST /api/login`

Request:

```json
{"email":"ada@example.com","password":"secret123"}
```

HTTP 200:

```json
{"success":true,"message":"Logged in successfully.","data":{"user":{"id":1,"name":"Ada","email":"ada@example.com"},"token":"2|example-token"}}
```

Invalid credentials receive HTTP 401: `{"success":false,"message":"Invalid credentials."}`.

### Logout — `POST /api/logout` (authenticated)

No request body. This revokes the bearer token used for this request. HTTP 200:

```json
{"success":true,"message":"Logged out successfully.","data":null}
```

### Current user — `GET /api/me` (authenticated)

HTTP 200:

```json
{"success":true,"message":"User retrieved successfully.","data":{"user":{"id":1,"name":"Ada","email":"ada@example.com"}}}
```

### Create a short URL — `POST /api/urls` (authenticated)

Request; `custom_code` is optional:

```json
{"url":"https://example.com/page","custom_code":"my-link"}
```

HTTP 201:

```json
{"success":true,"message":"URL shortened successfully.","data":{"id":1,"original_url":"https://example.com/page","short_code":"my-link","click_count":0}}
```

`url` must be a valid URL. A supplied `custom_code` must be unique and contain only letters, digits, `_`, or `-`. Without it, the API generates a unique eight-character code.

### List your URLs — `GET /api/urls?page=1&per_page=10` (authenticated)

HTTP 200:

```json
{"success":true,"message":"URLs retrieved successfully.","data":{"urls":[{"id":1,"original_url":"https://example.com/page","short_code":"my-link","click_count":0}],"pagination":{"page":1,"per_page":10,"total":1,"last_page":1}}}
```

Only the authenticated user's URLs appear, newest first. `page` and `per_page` must be positive integers; defaults are 1 and 10.

### URL details — `GET /api/urls/1` (authenticated)

HTTP 200:

```json
{"success":true,"message":"URL retrieved successfully.","data":{"id":1,"original_url":"https://example.com/page","short_code":"my-link","click_count":0}}
```

Only the owner may view the URL.

### Delete a URL — `DELETE /api/urls/1` (authenticated)

HTTP 200:

```json
{"success":true,"message":"URL deleted successfully.","data":null}
```

Only the owner may delete the URL.

### URL statistics — `GET /api/urls/1/stats` (authenticated)

HTTP 200:

```json
{"success":true,"message":"URL statistics retrieved successfully.","data":{"url":"https://example.com/page","short_code":"my-link","click_count":25}}
```

Only the owner may view statistics.

### Follow a short URL — `GET /my-link` (public)

No token is needed. The response is HTTP 302 with `Location: https://example.com/page`; each visit increments `click_count`. A missing code returns HTTP 404:

```json
{"success":false,"message":"Not found."}
```

## Design notes

- Controllers delegate work to `AuthService` and `UrlService`; validation lives in dedicated Form Requests.
- `UrlPolicy` owns URL access decisions. The `UrlServiceInterface` is bound to `UrlService` in `AppServiceProvider`.
- `ApiResponse` and `ApiResponseFormatter` produce the shared JSON envelopes. `GeneratesShortCode` produces automatic codes.
- The `urls` table contains only the specified fields: `id`, `user_id`, `original_url`, `short_code`, `click_count`, `created_at`, and `updated_at`.
- Redirect success is an HTTP redirect, so it has no JSON body. Logout revokes the current token rather than all of a user's tokens.
