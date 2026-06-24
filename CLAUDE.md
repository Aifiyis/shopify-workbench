# CLAUDE.md

Project-specific guidance for agents working in this repository.

This file is intentionally operational. Follow it before running commands, changing data, or declaring work complete.

## Project Overview

**Shopify Internal Management Workbench** is a Laravel 8 application for internal Shopify operations.

Primary responsibilities:
- Admin login and role-based access.
- Shopify store/order access and export.
- Order field transformation and Excel generation.
- SKU cleaning and product type lookup.
- Dynamic SKU option image scraping through a Laravel command plus a Node/Playwright worker.

## Core Stack

- Framework: Laravel 8
- PHP compatibility: PHP 7.3+ / PHP 8.x as allowed by `composer.json`
- Frontend: Blade templates with vanilla JavaScript and Tailwind CDN in layouts
- Database: SQLite in local `.env`; MySQL-compatible schema in production-oriented docs
- Excel: PHPExcel through `maatwebsite/excel ^1.1`
- HTTP/API: Guzzle and Shopify API library
- Dynamic page scraping: Laravel orchestration plus Node.js Playwright worker

## Non-Negotiable Database Safety

This project previously lost local admin accounts because `php artisan test` ran while PHPUnit was not isolated. A test used `migrate:fresh`, which rebuilt `database/database.sqlite`.

Before running any test, migration, seed, import, or destructive command:
- Check the active database target.
- Treat `database/database.sqlite` as user data.
- Treat `admins`, stores, orders, order line items, SKU lookup files, and scraper outputs as user data.
- Do not run `php artisan migrate:fresh`, `php artisan migrate:refresh`, raw `DROP`, raw `TRUNCATE`, or any equivalent destructive command unless the user explicitly asks to rebuild that exact database.
- If a destructive database command is genuinely needed, first state the target database, expected impact, backup or restore plan, and wait for explicit approval.

For tests, `phpunit.xml` must keep this isolation:

```xml
<server name="DB_CONNECTION" value="sqlite"/>
<server name="DB_DATABASE" value=":memory:"/>
```

If those lines are missing, commented out, or overridden by environment variables, stop and fix the test database configuration before running `php artisan test`.

## Common Commands

### Run the Application

```bash
php artisan serve
```

The local app is commonly accessed at:

```text
http://localhost:8001/login
```

Check the active server/port before assuming a URL.

### Run Tests

Before running tests, verify PHPUnit still uses an isolated database:

```bash
php -r "echo file_get_contents('phpunit.xml');"
```

Preferred narrow test runs:

```bash
php artisan test --filter=Sku
php artisan test tests/Unit/SkuOptionScrapeServiceTest.php
npm.cmd run test:sku-options-worker
```

Only run the full suite after confirming the database isolation above:

```bash
php artisan test
```

If a full suite has unrelated existing failures, report them separately and do not claim the whole suite passes.

### Database Operations

Safe schema-forward migration when requested:

```bash
php artisan migrate
```

Dangerous commands requiring explicit user approval:

```bash
php artisan migrate:fresh
php artisan migrate:refresh
php artisan db:seed
```

Do not use these as casual verification commands.

### Logs

Laravel application log:

```text
storage/logs/laravel.log
```

SKU option scraper batch run logs:

```text
storage/logs/sku-options-scrape-run.log
storage/logs/sku-options-scrape-run.err.log
```

## Important Data Paths

Private business data lives under:

```text
storage/app/private/
```

Important files and directories:
- `storage/app/private/sku-cleaned.json`
- `storage/app/private/sku-exclude-values.json`
- `storage/app/private/all-sku-to-product_type.json`
- `storage/app/private/sku-option-links.txt`
- `storage/app/private/sku-options-image.json`
- `storage/app/private/sku-options-image/`

Do not delete, overwrite, regenerate, or bulk-clean these files unless the user requested that exact operation.

### Editing JSON Files With Chinese Text

Several business JSON files contain Chinese keys and values, especially:
- `storage/app/private/sku-cleaned.json`
- `storage/app/private/sku-options-image.json`
- `storage/app/private/lookups/sku-placement-rules.json`

When scripting updates to these files on Windows/PowerShell:
- Do not rely on PowerShell pipes, here-strings, or inline command text to carry Chinese string literals.
- Prefer reading/writing JSON through PHP or another structured parser using UTF-8.
- When the script itself is passed through PowerShell, construct Chinese string constants from pure ASCII Unicode escapes, for example:

```php
$category = json_decode('"\\u6b3e\\u5f0f\\u56fe\\u70eb\\u753b"', true); // 款式图烫画
$position = json_decode('"\\u5de6\\u80f8\\u548c\\u540e\\u80cc"', true); // 左胸和后背
$chineseNameKey = json_decode('"\\u4e2d\\u6587\\u540d\\u79f0"', true); // 中文名称
```

After any scripted JSON update:
- Run `json_decode` checks on every modified JSON file.
- Re-read representative target records and confirm Chinese fields are correct.
- Search for accidental replacement fields or values such as `????`.
- If a bad key such as `????` was introduced, remove it from the affected records before reporting completion.

Generated temporary files belong under:

```text
storage/app/temp/
```

Processed downloadable files commonly live under:

```text
storage/app/public/processed_files/
```

## Architecture Notes

### Authentication

- Model: `App\Models\Admin`
- Guard: `admin`
- Login controller: `app/Http/Controllers/Auth/AdminLoginController.php`
- Admin table is `admins`, not Laravel's default `users`.

Default restored local login, when needed:

```text
email: admin@example.com
password: password123
role: super
```

Do not reset or recreate admin accounts unless the user asks or login recovery is explicitly needed.

### Order and Export Flow

Core services:
- `ShopifyService` fetches Shopify orders.
- `OrderCacheService` caches store orders locally.
- `OrderFieldTransformer` coordinates field transformers.
- `ExcelExportService` writes XLSX exports.
- `DataProcessingService` handles uploaded order files and generated archives.

Transformer classes live in:

```text
app/Services/Transformers/
```

Keep transformation changes narrow and covered by focused tests.

### SKU Cleaning

SKU cleaning uses:
- `SkuCleaningService`
- `storage/app/private/sku-cleaned.json`
- `storage/app/private/sku-exclude-values.json`

Resolution order:
- Match `original_sku` in `sku-cleaned.json`.
- If not found, clean using exclude values.
- Match cleaned SKU back to `sku-cleaned.json` for `中文名称`.

`excel_category` and `type` both use the matched `中文名称`.

### SKU Option Image Scraping

Command:

```bash
php artisan sku-options:scrape storage/app/private/sku-option-links.txt --timeout=120
```

Responsibilities:
- Laravel command reads URLs, invokes the worker, downloads images, writes JSON, and logs errors.
- Node/Playwright worker opens dynamic product pages and extracts plugin-specific option data.
- YMQ is implemented.
- Customily is detected and reported as unsupported until a specific extractor is added.

Output:

```text
storage/app/private/sku-options-image.json
storage/app/private/sku-options-image/
```

Current image naming rule:

```text
{cleaned_sku}_{option_value}.{extension}
```

Do not include option name in downloaded image filenames. If the target filename already exists, skip downloading that image again.

`options.sort_order` preserves the original scrape order from the product page.

When running large batches:
- Use a background process for long runs.
- Redirect stdout/stderr to named log files.
- Errors from failed products, unsupported plugins, unknown pages, and image downloads should go to `storage/logs/laravel.log`.
- Report PID, log paths, JSON path, and image directory.

If Playwright cannot find its bundled browser, the worker supports:

```powershell
$env:PLAYWRIGHT_CHROMIUM_EXECUTABLE='C:\Program Files\Google\Chrome\Application\chrome.exe'
```

In this environment, `NODE_PATH` may be needed for the bundled runtime:

```powershell
$env:NODE_PATH='C:\Users\20111\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\node_modules\.pnpm\node_modules'
```

## Coding Guidelines

- Keep edits closely scoped to the user's request.
- Prefer existing Laravel service/controller patterns.
- Use dependency injection for services when practical.
- Keep controller logic thin; put business logic in services.
- Use `\Log` or `Illuminate\Support\Facades\Log` for operational failures.
- Avoid broad refactors while feature work is in progress.
- Do not touch dirty files unrelated to the task.

## Verification Guidelines

Before claiming completion:
- Run the narrowest relevant tests.
- Verify generated files exist when the task creates files.
- Verify counts and representative records for JSON/image outputs.
- If full verification is blocked by network, permissions, or existing unrelated failures, state that plainly.

Do not say "tests pass" unless the exact test command passed in the current run.

For frontend-visible changes, use the browser or HTTP checks when a local server is available.

For scraper work, useful checks include:

```bash
php artisan test --filter=Sku
npm.cmd run test:sku-options-worker
node --check scripts/sku-options-scraper.js
```

For database-sensitive work, never use a broad test command until PHPUnit isolation is confirmed.

## Dependency Notes

- Do not upgrade `maatwebsite/excel` to v3 without planning a PHP upgrade; this project uses the PHPExcel-era package.
- `node_modules` may be a junction in Codex environments. Do not assume it is a normal project-local dependency directory.
- If npm writes a project-local cache, keep `.npm-cache` ignored.

## Troubleshooting

Login fails:
- Confirm `admins` contains an active admin.
- Confirm password hash with a non-mutating read or a targeted recovery step.
- Do not run migrations or seeders as a login fix unless explicitly approved.

Scraper produces no options:
- Check `sku-options-image.json` product `plugin`, `status`, and `error`.
- `unknown` usually means the page failed to load or no supported plugin was detected.
- Network sandbox failures may appear as `ERR_NETWORK_ACCESS_DENIED`.
- Check `storage/logs/laravel.log` for product and image-level warnings.

Excel export fails:
- Check writable output directories.
- Check source workbook formulas/images.
- Keep image temp files under `storage/app/temp/`.

Orders not showing:
- Check Shopify token and store access.
- Check cache TTL and `OrderCacheService`.
- Check `storage/logs/laravel.log`.
