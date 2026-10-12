# Translation Management Service

API for storing UI translations across locales and context tags, with search and a JSON export aimed at frontend apps such as Vue.

Management routes use bearer tokens. The export route is public by default so a browser or CDN can fetch it, and it can be locked with `EXPORT_REQUIRES_AUTH=true`.

## Requirements

- PHP 8.3+ with `pdo_sqlite` (and `pdo_mysql` if you use MySQL)
- Composer
- Optional: Docker, Redis, MySQL 8

## Local setup (Laragon / SQLite)

```bash
cd C:\laragon\www\translation-service
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Seeded admin:

- email: `admin@example.com`
- password: `password` (local only; set `SEED_ADMIN_EMAIL` / `SEED_ADMIN_PASSWORD` to change it. With `APP_ENV=production` the seeder skips the admin unless `SEED_ADMIN_PASSWORD` is set, and it never resets an existing admin's password.)

Interactive docs: `http://127.0.0.1:8000/api/docs`

OpenAPI file: `http://127.0.0.1:8000/api/openapi.yaml`

Laragon can also serve the folder as `http://translation-service.test` when Apache is running.

### Issue a token

```bash
curl -X POST http://127.0.0.1:8000/api/auth/token ^
  -H "Content-Type: application/json" ^
  -H "Accept: application/json" ^
  -d "{\"email\":\"admin@example.com\",\"password\":\"password\"}"
```

Send the token as `Authorization: Bearer <access_token>` on language, tag, and translation routes.

## MySQL

Laragon's MySQL client is installed, but the server may be stopped. Start MySQL from Laragon, create a database, then point `.env` at it:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=translations
DB_USERNAME=root
DB_PASSWORD=
```

MySQL is what enables the `FULLTEXT` index on translation content. On SQLite, content search falls back to `LIKE`. Tokens shorter than `FULLTEXT_MIN_LENGTH` (default 3, matching InnoDB) also use `LIKE`.

## Docker

```bash
docker compose up --build
```

The API is published on `http://localhost:8080`. Compose starts PHP-FPM, nginx, MySQL 8.4, and Redis. The app image contains the code, so PHP does not read the project from the Windows folder on each request. Rebuild after code changes (`docker compose up --build`). The app container runs migrations on boot, caches config, and runs the seeder only when `SEED_DATABASE=true` (set in `docker-compose.yml` for local use; leave it unset in production).

Load a large dataset from inside the app container:

```bash
docker compose exec app php artisan translations:populate --count=100000
docker compose exec app php artisan translations:benchmark --assert
```

## Large dataset

```bash
php artisan translations:populate --count=100000
php artisan translations:benchmark --assert
```

`--fresh` deletes existing translation keys and rows, then loads the set again. The command always creates or reactivates `en`, `fr`, and `es`, and the tags `mobile`, `desktop`, and `web`. Inserts are chunked so SQLite's placeholder limit is not exceeded. Factories under `database/factories` cover the same models for tests.

`translations:benchmark --assert` exits with a failure when an export takes longer than `EXPORT_BUDGET_MS` (default 500).

## API surface

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| POST | `/api/auth/register` | No | Create a **viewer** and token |
| POST | `/api/auth/token` | No | Login |
| DELETE | `/api/auth/token` | Yes | Revoke the current token |
| GET | `/api/auth/user` | Yes | Current user |
| GET / POST | `/api/languages` | Yes / editor | List or add a locale |
| GET / PATCH | `/api/languages/{code}` | Yes / editor | View or update a locale |
| GET / POST | `/api/tags` | Yes / editor | List or add a context tag |
| GET / POST | `/api/translations` | Yes / editor | Search or create |
| GET / PUT, DELETE | `/api/translations/{id}` | Yes / editor | View, update, delete |
| GET | `/api/export/{locale}` | Optional | JSON map for the frontend |

Search query parameters: `key`, `key_mode` (`prefix`, `contains`, `exact`), `content`, `locale`, `tags`, `tag_mode` (`any`, `all`), `group`, `per_page` (max 100), `cursor`. Results are cursor-paginated, newest first, with no total count: follow `meta.next_cursor` or `links.next` (which keeps the filters).

**Roles.** `editor` can read and write; `viewer` is read-only and gets `403` on every POST/PUT/PATCH/DELETE. Registration accepts an optional `role` of `viewer` or `editor`. Omitting it creates a viewer. `role` is not mass-assignable on the user model. The seeded admin is an editor; the migration that added roles made every existing account an editor.

Export query parameters: `tags`, `tag_mode`, `structure` (`flat` or `nested`). Send `If-None-Match` to receive `304` when the revision has not changed.

Tags on a translation refer to tag slugs. Tags belong to the translation key, so every locale of that key shares them. A later create merges tags. An update replaces them.

## Tests

```bash
php artisan test
vendor\bin\pint --test
```

Suites: `Unit`, `Feature` and `Performance` (all run by default).

### Performance (100k+ rows)

`tests/Performance/LargeDatasetPerformanceTest.php` seeds **100,000 translations** with the real `translations:populate` command, then times every endpoint in-process (median of 5 runs after a warm-up; cold exports measured once on an empty cache) and fails if any read/write exceeds **200ms** or any export exceeds **500ms**.

```bash
composer test:performance
```

Sample run (SQLite in-memory, PHP 8.3, Windows laptop):

| Endpoint | ms |
|---|---:|
| list, first page / deep cursor page | 18 / 17 |
| search: key prefix / exact | 15 / 7 |
| search: content common / rare | 11 / 23 |
| search: tag / tags=all + group | 15 / 11 |
| show | 9 |
| create / update | 23 / 14 |
| export cold, flat (~33k keys) | 181 |
| export cold, nested | 276 |
| export warm (cached body) | 4 |
| export 304 (If-None-Match) | 5 |
| export rebuild after a write | 160 |

These are in-process timings: they exclude PHP start-up and HTTP. For real HTTP numbers enable OPcache, set `APP_DEBUG=false`, run `php artisan config:cache route:cache`, then use `translations:benchmark --assert` and `curl -w "%{time_total}"` against MySQL/Redis.

### Coverage

```bash
composer test:coverage
```

Runs the suite with a coverage driver and fails below **95%** line coverage of `app/` (last measured: **99.5%**, 41 tests). The Docker image ships PCOV:

```bash
docker compose run --rm --no-deps -e PCOV=1 app composer test:coverage
```

Locally you need the PCOV or Xdebug extension (`xdebug.mode=coverage`).

## Design choices

**Schema.** Locales, tags, translation keys, and per-locale values are separate tables. New languages are rows, not columns. The message key lives in `message_key` so the column name is not the reserved word `key`. A unique index on `(translation_key_id, language_id)` supports the export join. `language_id` is indexed for filtered lists. Tag filters use the pivot primary key plus an index on `tag_id`. MySQL adds a fulltext index on `content`.

**Writes.** Creating, updating, and deleting a translation runs in one transaction and increments a single `translation_revisions` row. Each cached export stores the revision it was built from, so a committed write cannot be served from the previous entry. The single revision row is a deliberate trade-off: it makes freshness trivial, but every write briefly locks it, so write throughput is serialized (fine for a translation admin; shard it per locale if writes ever become hot). Reads still hit the database for the revision number, which is a primary-key lookup.

**Export.** The export query reads `translations` for one `language_id`, joins `translation_keys` for the message key, and selects only those two columns. A miss streams rows with a cursor, then encodes the JSON once. That body is cached in one slot per locale, tag fingerprint, and structure (`flat` or `nested`), so a repeat read returns the string and does not rebuild the map. A write changes the revision; the next request sees the slot's older revision, rebuilds, and overwrites the same slot, so stale versions never pile up. `If-None-Match` is checked against an ETag computed from the revision alone, so a `304` never loads translation rows. An empty map is encoded as `{}`. Nested structure is the vue-i18n tree, cached separately from the flat map of dotted keys.

**Search.** Prefix key search is the default because `LIKE 'term%'` can use the unique key index. Contains and content search escape `%`, `_`, and `\` and pass an explicit `ESCAPE` clause so SQLite and MySQL treat them the same. Page size is capped at 100. Unknown locales or tags return an empty page instead of the full table.

**Auth.** Tokens are 32 random bytes, shown once, and stored only as SHA-256. Expired tokens are rejected. `last_used_at` is written at most every five minutes so read traffic does not update the token row on every call. Login always runs one hash check, even for unknown emails, so timing does not reveal which accounts exist. Registration can create a viewer or an editor. Writes need the `editor` role (see Roles above).

**CDN.** Export responses send `Cache-Control: public, max-age=0, must-revalidate` unless `CDN_TTL` is set, plus `ETag`, `CDN-Cache-Control`, `Cache-Tag`, and `Surrogate-Key`. A CDN may store the body, but it must revalidate. The ETag changes when the revision changes, so clients do not keep a stale catalog. Set `CDN_URL` to include a `cdn_url` in the JSON. Set `CDN_ENABLED=true` with `CLOUDFLARE_ZONE_ID` and `CLOUDFLARE_API_TOKEN` to purge the `translations` cache tag after a write. Other management responses use `Cache-Control: no-store`. With `EXPORT_REQUIRES_AUTH=true` the export switches to `Cache-Control: private, max-age=0, must-revalidate`, `CDN-Cache-Control: no-store` and `Vary: Authorization` (no cache tags, no `cdn_url`), so a shared cache never stores a token-protected catalog and serves it to anonymous callers.

**SOLID and PSR-12.** Controllers depend on interfaces (`ManagesTranslations`, `SearchesTranslations`, `ExportsTranslations`, repositories, `TokenManager`, `CdnCachePolicy`). Validation stays in form requests. SQL stays in repositories. Pint enforces PSR-12 via `pint.json` (`psr12` preset plus `declare(strict_types=1)` in every file). No third-party CRUD or translation package is used.

## Project layout

- `app/Http` controllers, form requests, resources, middleware
- `app/Services` token, write, search, export, CDN, content matching
- `app/Repositories` Eloquent reads/writes and the export query
- `app/Console/Commands` volume load and export benchmark
- `docs/openapi.yaml` API description
- `docker-compose.yml` PHP, nginx, MySQL, Redis
