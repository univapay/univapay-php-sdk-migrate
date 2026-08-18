# univapay/univapay-sdk-migrate

[日本語版はこちら](README.ja.md)

A Rector-based `require-dev` tool that mechanically migrates a PHP codebase from the legacy,
hand-written [`univapay/php-sdk`](https://github.com/univapay/univapay-php-sdk) to
[`univapay/univapay-sdk-compat`](https://github.com/univapay/univapay-php-sdk-compat).

## What it does

This package rewrites a consumer codebase's `use` statements, fully-qualified class names,
`instanceof` checks, catch types, `::class` references, string-literal FQCNs, and a handful of
docblock tags so they point at `univapay/univapay-sdk-compat` instead of the old
`univapay/php-sdk`. Compat is a runtime compatibility layer that reimplements the old SDK's public
surface — the same class names, method signatures, public properties, enum style, exceptions, and
polling behavior — on top of the new APIMatic-generated `univapay/client-sdk` as its transport
engine. In other words: this tool changes *where your code imports from*; compat makes sure the
code behind those imports keeps behaving exactly as it did before, just running on the new engine.

## Requirements

- **PHP 7.4+ to run the tool itself.** The pinned `rector/rector` version is `2.6.2` — Rector's 2.x
  line requires `^7.4 || ^8.0` to run (its 1.x line matches `^7.2 || ^8.0` but is unmaintained, so
  it isn't used here). This is a *tooling* requirement, independent of your application's own PHP
  floor.
- **Your migrated code stays PHP 7.2-compatible.** The Rector config pins
  `phpVersion(PhpVersion::PHP_72)`, so nothing this tool prints uses 7.4+-only syntax (typed
  properties, arrow functions, `??=`) or native enums — matching `univapay/client-sdk`'s own
  `"php": "^7.2 || ^8.0"` floor.
- **A host whose only PHP binary is older than 7.4** (some legacy shared-hosting or CI images) can
  still run this tool via a disposable container with a newer PHP binary, mounting the project
  directory:

  ```bash
  docker run --rm -v "$PWD":/app -w /app composer:2 sh -c "
    composer require --dev univapay/univapay-sdk-migrate &&
    vendor/bin/univapay-migrate
  "
  ```

  This is a generic "run the tool under a newer PHP than your app's floor" pattern using the
  official `composer` image (which ships PHP 8+) — this package does not ship its own prebuilt
  Docker image or PHAR.

## The one command

```bash
composer require --dev univapay/univapay-sdk-migrate
vendor/bin/univapay-migrate
```

That single command runs, in this fixed order: a preflight check, `composer require
univapay/univapay-sdk-compat`, the Rector rewrite (with the old SDK still installed, so its
receiver-type resolution stays accurate), `composer remove univapay/php-sdk`, and a three-section
report. The order matters — see the doc comment at the top of `bin/univapay-migrate` for why
Rector must run before the old SDK is removed.

### Flags

| Flag | Effect |
|---|---|
| `--dry-run` | Skips the two mutating Composer calls and passes `--dry-run` through to `rector process`, so nothing on disk changes; still prints the full report. |
| `--strict` | Promotes unresolved-receiver `(verify)` unsupported-feature flags to a hard failure (exit `2`). Without it, only *confirmed* unsupported-feature flags do. |
| `--allow-unsupported` | Downgrades an unsupported-feature exit (`2`) to `0`. Composes with `--strict`: it downgrades *both* a confirmed unsupported-feature exit and a `--strict`-promoted `(verify)` exit — evaluated last, so passing both flags together always nets out to exit `0` for unsupported-feature-only findings. Findings are still printed and still written to the JSON report; only the exit code changes. For CI pipelines that have reviewed the findings and consciously accept them. |
| `--skip-composer` | Skips the `composer require univapay/univapay-sdk-compat` and `composer remove univapay/php-sdk` steps entirely — for monorepos or CI pipelines that manage those two dependency changes themselves. Rector still requires the old SDK to be autoloadable at run time regardless. |
| `--paths=a,b,c` | Comma-separated directories to scan. If omitted, derived from your `composer.json`'s `autoload`/`autoload-dev` PSR-4 (and classmap) entries, falling back to `src/` if none are declared. |
| `--no-report` | Skips writing `univapay-migrate-report.json` to the current working directory. Written by default — see "Exit codes + report" below. |
| `--phase2` | Runs the SECOND, independent migration — `univapay/univapay-sdk-compat` onto the native `univapay/client-sdk` — instead of this default set. Never touches `composer.json`. See "Migrating further to the native SDK" below. |
| `-h`, `--help` | Prints usage and exits. |

### Before / after

A real excerpt from this package's own end-to-end test corpus (`tests/E2e/`), taken from a
verbatim copy of an old-SDK example:

```php
// before
use Univapay\UnivapayClient;
use Univapay\Resources\Authentication\AppJWT;
use Money\Money;

$storeAppToken = AppJWT::createToken('token', 'secret');
$client = new UnivapayClient($storeAppToken);
$charge = $client->createToken($paymentMethod)->createCharge(Money::USD(1000))->awaitResult();
```

```php
// after
use Univapay\Compat\UnivapayClient;
use Univapay\Compat\Resources\Authentication\AppJWT;
use Money\Money;

$storeAppToken = AppJWT::createToken('token', 'secret');
$client = new UnivapayClient($storeAppToken);
$charge = $client->createToken($paymentMethod)->createCharge(Money::USD(1000))->awaitResult();
```

Only the `use` lines changed. The call chain, the `Money` object, and `awaitResult()` are untouched
— that is deliberate (see "What gets rewritten" below).

## What gets rewritten

`src/ClassMap.php` is the single source of truth: 154 old-FQCN → compat-FQCN entries, applied via
Rector's `RenameClassRector` (code references), the built-in `RenameStringRector` (string-literal
FQCNs, e.g. `new BasicRetryHandler('Univapay\Errors\UnivapayServerError', ...)`), and a small custom
rule for `@expectedException`/`@covers`/`@uses` docblock tags (generic text tags Rector's own
structured docblock-type renamer doesn't reach).

| Category | Examples |
|---|---|
| Client + options | `UnivapayClient`, `UnivapayClientOptions` |
| Enums | `ChargeStatus`, `SubscriptionStatus`, `TypedEnum`, and 36 more |
| Errors | `UnivapayRequestError`, `UnivapayNotFoundError`, `UnivapaySDKError`, and 11 more |
| Authentication | `AppJWT`, `StoreAppJWT`, `MerchantAppJWT`, `InvalidJWTFormat` |
| Resources | `Charge`, `Refund`, `Cancel`, `Subscription`, `TransactionToken`, `Store`, `Merchant`, `Paginated`, `WebhookPayload`, and more |
| Configuration | The full `Resources\Configuration\*` tree (`CardConfiguration`, `ThemeConfiguration`, …) |
| Mixins | `GetCharges`, `GetSubscriptions`, `GetStores`, `GetTransactions`, `GetBankAccounts`, and more |
| Payment data / methods / tokens | `Address`, `PhoneNumber`, `CardPayment`, `ConvenienceStorePayment`, `OnlineToken`, and more |
| Handlers | `RequestHandler`, `BasicRetryHandler`, `RateLimitHandler`, `NetworkRetryHandler` |
| Utility | `DateUtils`, `FormatterUtils`, `FunctionalUtils`, the `Json\*` parsers, `OptionsValidator`, `StringUtils`, `ValidationHelper` |

Everything renamed keeps its **basename** — only the namespace prefix changes
(`Univapay\` → `Univapay\Compat\`) — and covers `use` statements, `new`, `instanceof`, catch types,
type hints, `::class`, and docblocks.

**Classes that are *not* renamed** — five internal, transport-coupled classes with no compat
equivalent at all: `Univapay\Requests\Requester`, `Univapay\Requests\HttpRequester`,
`Univapay\Requests\RequestContext`, `Univapay\Utility\HttpUtils`, and
`Univapay\Utility\RequesterUtils`. Referencing any of these is flagged as internal-API usage (see
below) — it's a hard compile error once `univapay/php-sdk` is removed, since nothing replaces them.

### What does NOT change

The entire point of the compat-first design is that everyday application code needs **no manual
review**. Concretely, none of the following change:

- **`Money\Money` / `Money\Currency` (moneyphp) values.** Compat still accepts and returns
  `moneyphp` objects everywhere the old SDK did; the new engine SDK's flat `int`+`string`
  representation is an internal detail compat converts at its own boundary.
- **Enum style.** `ChargeStatus::SUCCESSFUL()`, `->getValue()`, `->getName()`, `::fromValue()`,
  `===` identity comparisons, and `switch` all keep working exactly as before — compat's enums are
  the same `TypedEnum`-based singleton classes, just renamed.
- **Property access.** Resources keep their old public properties (`$charge->status`,
  `$charge->requestedAmount`, …) — no getters, no signature changes.
- **`awaitResult()`, `fetch()`, chained calls** (`createToken($pm)->createCharge(...)->awaitResult()`)
  — all preserved verbatim.
- **Catch bodies and exception handling logic** — only the exception class names in `use`/`catch`
  are renamed; the hierarchy and `$e->code`/`$e->status` semantics compat exposes match the old
  SDK's.

## Exit codes + report

| Exit code | Meaning |
|---|---|
| `0` | Clean run, only warnings (unresolved-receiver flags without `--strict`), or `--allow-unsupported` downgraded what would otherwise have been exit `2`. |
| `1` | Usage error, or a preflight/step failure — nothing (or only partial work) was done. |
| `2` | At least one *confirmed* unsupported-feature flag was found, or `--strict` was passed and at least one `(verify)` flag was found — unless `--allow-unsupported` downgraded it to `0`. |

Step 6 always prints a three-section report:

**(a) `@univapay-migrate:*` markers** — counts of `@univapay-migrate:unsupported` (confirmed and
`(verify)`), `@univapay-migrate:internal-api`, and `@univapay-migrate:network-exception` marker
comments the Rector rules inserted in your code (see "Unsupported features" and "Known caveats"
below).

**(b) Residual `Univapay\` scan** — a repo-wide, file-type-agnostic grep for any `Univapay\`
reference *not* followed by `Compat\` or `Migrate\`, across `.php`, `.yml`, `.yaml`, `.xml`,
`.json`, `.neon`, `.env`, `.twig`, `.blade.php`, and `.ini` files. String FQCNs hiding in DI
configs, serializer mappings, and PHPStan/Psalm baselines are just as real a problem as ones in PHP
code, and Rector cannot safely rewrite arbitrary non-PHP text — this section is how those surface
for manual review. Only reported in files that reference `Univapay\` at all, to avoid false
positives on unrelated text.

**(c) Known dead imports** — a small, hard-coded list of FQCNs that look plausible from old README
snippets but never actually existed in `univapay/php-sdk` (`Univapay\Client`,
`Univapay\RequestsHandlers`, `Univapay\PaymentMethod\CardPayment`). These are never mapped — doing
so would either emit a duplicate `use` line or point at a target that was never real, both compile
errors — they're reported as "dead import, safe to delete" instead.

### Machine-readable report (`univapay-migrate-report.json`)

Unless `--no-report` is passed, step 6 also writes `univapay-migrate-report.json` to the current
working directory — a machine-readable mirror of the same three-section report above, built from
the exact findings already collected for stdout (never a second scan):

```json
{
  "version": 1,
  "exitCode": 0,
  "unsupported": [
    { "file": "src/Billing/TransferSync.php", "line": 42, "feature": "getTransfer", "verified": true }
  ],
  "internalApi": [
    { "file": "src/Http/RetryHandler.php", "line": 17, "feature": "Requests\\HttpRequester" }
  ],
  "networkException": [
    { "file": "src/Http/RetryHandler.php", "line": 29, "feature": "WpOrg\\Requests\\Exception" }
  ],
  "residualReferences": [
    { "file": "config/services.yaml", "line": 8, "text": "class: Univapay\\Resources\\Charge" }
  ],
  "deadImports": [
    { "file": "src/Legacy/Facade.php", "line": 3, "import": "Univapay\\Client" }
  ]
}
```

- `"version"` — the report schema version (currently always `1`); bump only on a breaking shape
  change.
- `"exitCode"` — the *final* exit code this invocation returned, i.e. already reflecting any
  `--allow-unsupported` downgrade. A consumer reading only this file sees the same pass/fail
  verdict the process itself did.
- `"unsupported"` entries carry `"verified": true` for a confirmed flag and `"verified": false`
  for an unresolved-receiver `(verify)` flag (see "Unsupported features" below).
- `"internalApi"`, `"networkException"`, `"residualReferences"`, and `"deadImports"` mirror
  report sections (a)–(c) one to one — `"residualReferences"` is section (b), `"deadImports"` is
  section (c).

Pass `--no-report` to skip writing the file entirely (e.g. if your CI only inspects stdout, or the
working directory is read-only).

## Unsupported features

Some of the old SDK's surface has no equivalent in the new engine and is not planned to gain one.
Referencing it still compiles (it's renamed to a compat *stub*), but the stub throws
`UnivapayUnsupportedFeatureError` at runtime. This tool flags every such reference at migrate time
so it's a visible, reviewable line instead of a silent runtime surprise.

| Feature | Classes / methods | Why |
|---|---|---|
| Transfers, Ledgers, Transfer status changes | `Transfer`, `TransferStatusChange`, `Ledger`, mixins `GetTransfers`/`GetLedgers`/`GetStatusChanges`, methods `getTransfer`, `listTransfers(ByOptions)`, `listLedgers(ByOptions)`, `listStatusChanges(ByOptions)` | Not exposed by the new engine SDK. |
| Merchant payout bank accounts | `BankAccount`, mixin `GetBankAccounts`, methods `getBankAccount`, `listBankAccounts`, `listBankAccountContextsByOptions` (`fetch()`/`update()` on a `BankAccount` instance also throw at runtime, but — like `Transfer::fetch()`/`update()` above — are not flagged by name, since those generic method names are reused by every supported resource) | Not exposed by the new engine SDK (unsupported operation, not a temporary gap). |
| Apple Pay token creation | `ApplePayPayment` (constructing the value object still works; creating a token from it does not) | Apple Pay token creation isn't wired into the new engine SDK. |
| Charge QR merchant token | `Charge::qrMerchantToken()` (only this one method — `Charge` itself is fully supported) | The underlying `/qr` endpoint is deprecated upstream; MPM QR data is available from the token object instead. |

The flag rule also emits an unresolved-receiver **`(verify)`** variant when it can't statically
determine whether a method call's receiver is actually a Univapay object (e.g. an untyped
parameter) — those are warnings, not confirmed flags, and only appear in files that reference
`Univapay\` somewhere at all, so a same-named method on an unrelated class is never flagged.

Separately, two more categories get their own marker/report line, though they aren't "unsupported"
in the same sense:

- **Internal-API usage** (`@univapay-migrate:internal-api`) — the five classes with no compat
  target listed above under "What gets rewritten".
- **Network-exception usage** (`@univapay-migrate:network-exception`) — any `WpOrg\Requests\*`
  reference (e.g. a custom retry handler catching `WpOrg\Requests\Exception`). The new transport
  never throws that type; connection failures now throw `UnivapayNetworkError`.

## Known caveats

A few real, tooling-level limitations (see [NOTES.md](NOTES.md) for the technical detail behind
each):

- **`importNames(true)` may shorten unrelated, pre-existing fully-qualified references** elsewhere
  in any file this tool touches at all — e.g. `new \Some\Unrelated\Thing()` may become `new
  Thing()` plus a new `use Some\Unrelated\Thing;` line. This is cosmetic and behavior-preserving
  (same class, same resolution), never a compile error, and not specific to Univapay code — but it
  is a real diff you'll see in touched files beyond the intended rename.
- **A pre-existing, human-authored comment directly above a renamed `use` statement is silently
  deleted**, not just moved. This is a `rector/rector` internal (its import-block rebuild pass),
  not something this package controls, and there is no workaround. If you have explanatory comments
  above old-SDK `use` lines, expect to lose them.
- **Double-quoted string FQCN renames come out single-quoted.** `"Univapay\\Errors\\X"` and
  `'Univapay\Errors\X'` both match and both get renamed, but the built-in rule that performs the
  rewrite always prints a fresh single-quoted string node — quote style is not preserved across a
  rename.
- **Grouped `use` imports are split, including files with more than one group.**
  `use Univapay\Enums\{ChargeStatus, RefundStatus};` is expanded into one `use` statement per class
  before the rename runs, so it ends up fully renamed like any other import. (An early version of
  this splitting had a bug with two-or-more group-use statements in the same file; it's fixed and
  covered by the E2E corpus.)

## After migrating

1. **Run your test suite.**
2. **Drain queues and flush caches that hold serialized old-SDK objects.** Class names changed;
   unserializing an old-SDK object without a `class_alias` safety net in place will fail.
3. **Regenerate your PHPStan/Psalm baselines and IDE helper files.**
4. **Review every report section** — unsupported-feature flags need a call-site decision,
   internal-API and network-exception flags need a manual port, and residual references need a
   manual fix.
5. See the migration guide: `https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration`

## Migrating further to the native SDK

`univapay/univapay-sdk-compat` is not meant to be a permanent destination. Its
`UnivapayClient::native()` method returns the exact `UnivaPay\UnivapayClientSdkClient` instance
compat already built internally — same auth, base URL, and timeout, never a second,
independently-configured client. That enables **mixed mode**: migrate call sites to the native,
typed SDK file by file, while everything not yet migrated keeps calling the compat facade. Both
paths share one engine, so there is no drift between them during the migration window. See the
[`univapay-sdk-compat` README](https://github.com/univapay/univapay-php-sdk-compat#migrating-off-the-compat-layer)
for the full mixed-mode pattern and its own construct-by-construct migration notes.

### Phase 2: `--phase2`

```bash
vendor/bin/univapay-migrate --phase2
```

Runs `UnivapaySetList::COMPAT_TO_NATIVE` instead of the default set. **Review-assisted, not
drop-in.** An audit of both trees (`univapay/univapay-sdk-compat`'s `src/` against the native
`univapay/client-sdk`'s `src/Models`) found zero data classes and zero exception classes safe to
rename mechanically — every compat resource uses public properties where native models use
private properties behind getters, every compat enum is a `TypedEnum` singleton where native enums
are plain string `const`s, and every compat exception subclass would collapse many-to-one onto
`UnivaPay\Exceptions\ApiException`/`ApiErrorException`. So instead of a rename map, this set's one
rule (`FlagCompatManualMigrationRector`) inserts an idempotent `//
@univapay-migrate:phase2-manual` marker comment above every construct that needs a human decision,
naming its category and the native equivalent:

| Category | Compat construct | Native equivalent |
|---|---|---|
| `typed-enum` | `ChargeStatus::SUCCESSFUL()`, `->getValue()`, `===`, `switch` | `UnivaPay\Models\ChargeStatus::SUCCESSFUL` (plain string `const`) |
| `money` | `Money\Money`/`Money\Currency` (moneyphp) | flat `int $amount` + `string $currency` |
| `public-property` | `$charge->status` | `$charge->getStatus()` / `->getResult()->getStatus()` on an `ApiResponse` |
| `poll` | `->awaitResult()` | `pollCharge()`/`pollRefund()`/`pollCancel()`/`pollSubscription()` |
| `pagination` | `Paginated`, `->getNext()`/`->getPrevious()`, the `Mixins\Get*` traits | a cursor-param loop against the native list endpoint |
| `webhook` | `->parseWebhookData()` | `UnivaPay\Events\Webhooks\*Handler` |
| `client-construction` | `UnivapayClient`/`UnivapayClientOptions`, `AppJWT`/`StoreAppJWT`/`MerchantAppJWT` | `UnivapayClientSdkClientBuilder` + `BearerAuthCredentialsBuilder` |
| `exception-handling` | any `Univapay\Compat\Errors\*` catch/throw/`instanceof` | `ApiException`/`ApiErrorException`, distinguished via `getHttpResponse()->getStatusCode()`/`getCodeProperty()` |
| `internal-utility` | `Univapay\Compat\Utility\*` | none — port the logic yourself |

`->native()` (the documented mixed-mode escape hatch above) is never flagged.

**Inputs:** the same `--paths`/`--dry-run`/`--strict`/`--allow-unsupported`/`--no-report` flags as
the default set, applied to the same `@univapay-migrate:phase2-manual` markers instead of
`@univapay-migrate:unsupported` ones. `--skip-composer` has no effect here — see Outputs.

**Outputs:** `--phase2` **never modifies `composer.json` itself.** Steps 2 and 6 (`composer
require`/`composer remove`) are always skipped and printed as next steps instead:

```bash
composer require univapay/client-sdk
composer remove univapay/univapay-sdk-compat
```

Preflight checks `Univapay\Compat\UnivapayClient` is autoloadable (compat must be installed —
there is nothing to migrate from otherwise), not the old SDK's client. The report's fourth section
and `univapay-migrate-report.json`'s `"phase2Manual"` array work exactly like the
unsupported-feature section: `"verified": true` for a confirmed flag, `false` for an
unresolved-receiver `(verify)` flag, and the same exit-code precedence (`--strict` promotes
`(verify)` to a failure, `--allow-unsupported` downgrades either back to `0`).

## Removing this package afterwards

This package is `require-dev`-only and is not needed once your migration is complete and your
codebase is verified against `univapay/univapay-sdk-compat`:

```bash
composer remove --dev univapay/univapay-sdk-migrate
rm rector-univapay.php
```

## License

MIT.
