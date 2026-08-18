# univapay/univapay-sdk-migrate

[English version here](README.md)

Rector をベースにした `require-dev` 専用のツールです。レガシーな手書き SDK である
[`univapay/php-sdk`](https://github.com/univapay/univapay-php-sdk) を利用している PHP コードベースを、
[`univapay/univapay-sdk-compat`](https://github.com/univapay/univapay-php-sdk-compat) を使う形へ機械的に移行します。

## このツールが行うこと

このツールは、コードベース内の `use` 文、完全修飾クラス名（FQCN）、`instanceof` 判定、`catch` 節の型、
`::class` 参照、文字列リテラルとして書かれた FQCN、そして一部の docblock タグを書き換え、旧来の
`univapay/php-sdk` ではなく `univapay/univapay-sdk-compat` を参照するようにします。compat パッケージは、
新しく APIMatic により生成された `univapay/client-sdk` を実行エンジンとして内部で利用しながら、旧 SDK の
公開 API 表面 —— 同じクラス名、同じメソッドシグネチャ、public プロパティ、enum の書き方、例外、ポーリング
挙動 —— をそのまま再実装したランタイム互換レイヤーです。つまり、このツールが変更するのは
「どこからコードを import するか」だけであり、その import の先にあるコードの挙動が新しいエンジン上でも
以前とまったく同じように動くことを compat が保証します。

## 必要要件

- **ツール自体の実行には PHP 7.4 以上が必要です。** 固定バージョンとして採用している `rector/rector`
  は `2.6.2` です。Rector の 2.x 系は実行に `^7.4 || ^8.0` を要求します（1.x 系は `^7.2 || ^8.0` に対応
  していますが、すでにメンテナンスが終了しているため本ツールでは採用していません）。これはあくまで
  「ツールを実行する環境」に関する要件であり、アプリケーション本体の PHP バージョンとは独立しています。
- **移行後のコードは PHP 7.2 互換のまま維持されます。** Rector の設定では
  `phpVersion(PhpVersion::PHP_72)` を固定しているため、本ツールが出力するコードには 7.4 以降でしか
  使えない構文（型付きプロパティ、アロー関数、`??=`）やネイティブ enum は一切含まれません。これは
  `univapay/client-sdk` 自体の `"php": "^7.2 || ^8.0"` という要件と一致しています。
- **利用可能な PHP バイナリが 7.4 未満しかないホスト**（レガシーな共有ホスティングや CI イメージなど）
  でも、プロジェクトディレクトリをマウントした使い捨てコンテナ上で、より新しい PHP を使ってこのツールを
  実行できます。

  ```bash
  docker run --rm -v "$PWD":/app -w /app composer:2 sh -c "
    composer require --dev univapay/univapay-sdk-migrate &&
    vendor/bin/univapay-migrate
  "
  ```

  これは「アプリ本体の PHP バージョンより新しい PHP でツールを実行する」ための汎用的なパターンであり、
  PHP 8 系を同梱した公式の `composer` イメージを利用しています。本パッケージ自体が独自にビルドした
  Docker イメージや PHAR を配布しているわけではない点に注意してください。

## たった一つのコマンド

```bash
composer require --dev univapay/univapay-sdk-migrate
vendor/bin/univapay-migrate
```

このコマンド一つで、以下の処理が固定された順序で実行されます。プリフライトチェック → `composer require
univapay/univapay-sdk-compat` の実行 → Rector によるコード書き換え（この時点ではまだ旧 SDK が
インストールされたままになっており、受け側の型解決を正確に保つために重要です）→ `composer remove
univapay/php-sdk` → 3 セクション構成のレポート出力。この順序には理由があります。Rector を旧 SDK
削除より先に実行する必要がある理由については、`bin/univapay-migrate` 冒頭の doc comment を
参照してください。

### オプション一覧

| オプション | 効果 |
|---|---|
| `--dry-run` | Composer への変更を伴う 2 つの呼び出しをスキップし、`rector process` にも `--dry-run` を渡します。ディスク上のファイルは一切変更されませんが、レポートは通常どおり出力されます。 |
| `--strict` | 受け側の型を静的に解決できなかった `(verify)` 付きの未サポート機能フラグを、終了コード `2` を返すハードエラーへ昇格させます。指定しない場合、終了コードが `2` になるのは「確定した」未サポート機能フラグが見つかった場合のみです。 |
| `--allow-unsupported` | 未サポート機能による終了コード `2` を `0` に格下げします。`--strict` と併用可能です。「確定」フラグによる終了と、`--strict` によって昇格された `(verify)` フラグによる終了の**両方**を格下げします —— 判定の順序としては最後に評価されるため、両方のフラグを同時に指定した場合、未サポート機能に起因する結果は常に終了コード `0` になります。標準出力への表示や JSON レポートへの記録はこのフラグの影響を受けず、変わるのは終了コードだけです。レポート内容をレビューした上で意図的に受け入れる CI パイプライン向けです。 |
| `--skip-composer` | `composer require univapay/univapay-sdk-compat` と `composer remove univapay/php-sdk` の両方の手順を完全にスキップします。モノレポや、依存関係の変更を自前で管理している CI パイプライン向けです。この場合でも、Rector の実行時には旧 SDK が autoload 可能である必要があります。 |
| `--paths=a,b,c` | スキャン対象ディレクトリをカンマ区切りで指定します。省略した場合は、`composer.json` の `autoload`/`autoload-dev` に定義された PSR-4（および classmap）のパスから自動的に導出され、それも無ければ `src/` にフォールバックします。 |
| `--no-report` | カレントディレクトリへの `univapay-migrate-report.json` の書き出しをスキップします。既定では書き出されます —— 詳細は後述の「終了コードとレポート」を参照してください。 |
| `--phase2` | 既定のセットの代わりに、2 つ目の独立した移行 —— `univapay/univapay-sdk-compat` からネイティブな `univapay/client-sdk` への移行 —— を実行します。`composer.json` には一切触れません。詳しくは後述の「ネイティブ SDK へのさらなる移行」を参照してください。 |
| `-h`, `--help` | 使い方を表示して終了します。 |

### 書き換え前後の実例

本パッケージ自身のエンドツーエンドテスト一式（`tests/E2e/`）にある、旧 SDK のサンプルをそのまま
コピーしたフィクスチャからの抜粋です。

```php
// 変更前
use Univapay\UnivapayClient;
use Univapay\Resources\Authentication\AppJWT;
use Money\Money;

$storeAppToken = AppJWT::createToken('token', 'secret');
$client = new UnivapayClient($storeAppToken);
$charge = $client->createToken($paymentMethod)->createCharge(Money::USD(1000))->awaitResult();
```

```php
// 変更後
use Univapay\Compat\UnivapayClient;
use Univapay\Compat\Resources\Authentication\AppJWT;
use Money\Money;

$storeAppToken = AppJWT::createToken('token', 'secret');
$client = new UnivapayClient($storeAppToken);
$charge = $client->createToken($paymentMethod)->createCharge(Money::USD(1000))->awaitResult();
```

変更されたのは `use` の行だけです。メソッドチェーン、`Money` オブジェクト、`awaitResult()` の呼び出しは
一切変わりません（意図的な仕様です。詳しくは「書き換えられる内容」を参照してください）。

## 書き換えられる内容

`src/ClassMap.php` が唯一の正となる情報源で、旧 FQCN → compat 側 FQCN への対応が 154 件登録されています。
これは Rector の `RenameClassRector`（コード上の参照）、組み込みの `RenameStringRector`（文字列リテラル
として書かれた FQCN。例: `new BasicRetryHandler('Univapay\Errors\UnivapayServerError', ...)`）、そして
`@expectedException`/`@covers`/`@uses` の docblock タグ用に用意した小さな独自ルール（Rector 標準の
構造化された docblock 型リネーム機能ではカバーされない、単なるテキストタグのため）によって適用されます。

| カテゴリ | 例 |
|---|---|
| クライアント / オプション | `UnivapayClient`、`UnivapayClientOptions` |
| Enum | `ChargeStatus`、`SubscriptionStatus`、`TypedEnum` 他 36 クラス |
| エラー | `UnivapayRequestError`、`UnivapayNotFoundError`、`UnivapaySDKError` 他 11 クラス |
| 認証 | `AppJWT`、`StoreAppJWT`、`MerchantAppJWT`、`InvalidJWTFormat` |
| リソース | `Charge`、`Refund`、`Cancel`、`Subscription`、`TransactionToken`、`Store`、`Merchant`、`Paginated`、`WebhookPayload` 他 |
| Configuration | `Resources\Configuration\*` 配下のクラス群一式（`CardConfiguration`、`ThemeConfiguration` など） |
| Mixin | `GetCharges`、`GetSubscriptions`、`GetStores`、`GetTransactions`、`GetBankAccounts` 他 |
| 決済データ / 決済手段 / トークン | `Address`、`PhoneNumber`、`CardPayment`、`ConvenienceStorePayment`、`OnlineToken` 他 |
| ハンドラー | `RequestHandler`、`BasicRetryHandler`、`RateLimitHandler`、`NetworkRetryHandler` |
| ユーティリティ | `DateUtils`、`FormatterUtils`、`FunctionalUtils`、`Json\*` パーサー群、`OptionsValidator`、`StringUtils`、`ValidationHelper` |

リネームされるクラスはすべて**クラス名（basename）はそのまま**で、名前空間のプレフィックスだけが
（`Univapay\` → `Univapay\Compat\`）変わります。対象は `use` 文、`new`、`instanceof`、`catch` 節の型、
型ヒント、`::class`、docblock まで一通り含まれます。

**リネームされないクラス** —— compat 側に対応するものが一切存在しない、内部専用かつ通信処理と密結合な
5 つのクラスです: `Univapay\Requests\Requester`、`Univapay\Requests\HttpRequester`、
`Univapay\Requests\RequestContext`、`Univapay\Utility\HttpUtils`、`Univapay\Utility\RequesterUtils`。
これらのいずれかを参照しているコードは「内部 API の利用」としてフラグが付与されます（後述）。
`univapay/php-sdk` を削除した時点で置き換え先が存在しないため、確実にコンパイルエラーになります。

### 変わらないもの

compat をまず用意するというこの設計の最大の目的は、日常的なアプリケーションコードに
**手動レビューを一切必要としない**ことです。具体的には、以下のものは何も変わりません。

- **`Money\Money` / `Money\Currency`（moneyphp）の値。** compat は旧 SDK と同様、あらゆる箇所で
  `moneyphp` のオブジェクトをそのまま受け取り、そのまま返します。新エンジン SDK が採用している
  `int` + `string` によるフラットな表現は、compat 内部の境界でのみ変換される実装詳細です。
- **Enum の使い方。** `ChargeStatus::SUCCESSFUL()`、`->getValue()`、`->getName()`、`::fromValue()`、
  `===` による同一性比較、`switch` 文などはすべて以前とまったく同じように動作します。compat の enum は
  同じ `TypedEnum` ベースのシングルトンクラスであり、名前空間だけがリネームされています。
- **プロパティへのアクセス方法。** 各リソースは以前と同じ public プロパティを保持します
  （`$charge->status`、`$charge->requestedAmount` など）。getter への置き換えやシグネチャ変更は
  ありません。
- **`awaitResult()`、`fetch()`、メソッドチェーン**
  （`createToken($pm)->createCharge(...)->awaitResult()` など）—— すべてそのまま維持されます。
- **`catch` ブロックや例外処理のロジック。** `use`/`catch` に書かれた例外クラス名がリネームされるだけで、
  compat が公開する例外階層や `$e->code`/`$e->status` の意味は旧 SDK と一致します。

## 終了コードとレポート

| 終了コード | 意味 |
|---|---|
| `0` | 正常終了、警告のみ（`--strict` を指定していない状態での未解決な受け側フラグ）、または `--allow-unsupported` によって本来 `2` になるはずだった終了コードが格下げされた場合。 |
| `1` | 使用方法のエラー、またはプリフライト／各ステップの失敗 —— 何も（あるいは一部しか）実行されていません。 |
| `2` | 「確定した」未サポート機能フラグが 1 件以上見つかった場合、または `--strict` を指定した状態で `(verify)` フラグが 1 件以上見つかった場合 —— ただし `--allow-unsupported` によって `0` に格下げされていない場合に限ります。 |

ステップ 6 では、常に 3 セクション構成のレポートが出力されます。

**(a) `@univapay-migrate:*` マーカー** —— Rector のルールがコード中に挿入した
`@univapay-migrate:unsupported`（確定分と `(verify)` 分）、`@univapay-migrate:internal-api`、
`@univapay-migrate:network-exception` の各マーカーコメントの件数です（詳細は後述の「未サポート機能」
「既知の注意点」を参照）。

**(b) 残存する `Univapay\` 参照のスキャン** —— `Compat\` や `Migrate\` が続かない `Univapay\` 参照を、
`.php`、`.yml`、`.yaml`、`.xml`、`.json`、`.neon`、`.env`、`.twig`、`.blade.php`、`.ini` といった
ファイル種別を問わずリポジトリ全体から grep します。DI の設定ファイル、シリアライザのマッピング、
PHPStan/Psalm のベースラインなどに隠れている文字列としての FQCN は、PHP コード中のものと同様に
現実的な問題であり、Rector は任意の非 PHP テキストを安全に書き換えることができません。このセクションは、
そうした残存箇所を手動レビューのために可視化するためのものです。誤検知を避けるため、そもそも
`Univapay\` への参照が存在するファイルのみを対象にレポートします。

**(c) 既存しない import（削除して問題ないもの）** —— 旧 README のサンプルコードには登場するものの、実際には
`univapay/php-sdk` に一度も存在したことのない FQCN の、あらかじめ決め打ちされた短いリスト
（`Univapay\Client`、`Univapay\RequestsHandlers`、`Univapay\PaymentMethod\CardPayment`）です。これらは
決してリネームの対象にはなりません —— リネームしてしまうと `use` 文が重複するか、実在しない対象を
指すことになり、いずれにせよコンパイルエラーになるためです。代わりに「実在しない import です。削除して
問題ありません」として報告されます。

### 機械可読なレポート（`univapay-migrate-report.json`）

`--no-report` を指定しない限り、ステップ 6 では上記と同じ 3 セクション構成のレポートを機械可読な形式で
カレントディレクトリの `univapay-migrate-report.json` にも書き出します。標準出力向けにすでに集計済みの
内容から生成しており、再スキャンは一切行いません。

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

- `"version"` —— レポートのスキーマバージョンです（現時点では常に `1`）。破壊的な形式変更があった
  場合にのみ上げます。
- `"exitCode"` —— この実行が実際に返した**最終的な**終了コードです。つまり `--allow-unsupported` に
  よる格下げがあれば、それを反映済みの値になります。このファイルだけを見ても、プロセス自体と同じ
  合否判定を確認できます。
- `"unsupported"` の各エントリは、「確定」フラグでは `"verified": true`、受け側を静的に解決できなかった
  `(verify)` フラグでは `"verified": false` になります（詳細は後述の「未サポート機能」を参照）。
- `"internalApi"`、`"networkException"`、`"residualReferences"`、`"deadImports"` は、それぞれ標準出力の
  レポートのセクション (a)〜(c) と一対一で対応します —— `"residualReferences"` がセクション (b)、
  `"deadImports"` がセクション (c) です。

ファイルの書き出し自体が不要な場合（CI が標準出力のみを見ている、カレントディレクトリが読み取り専用、
など）は `--no-report` を指定してください。

## 未サポート機能

旧 SDK の機能の一部は、新しいエンジンに相当するものが存在せず、今後も追加される予定はありません。
それらを参照するコードはコンパイル自体は通ります（compat 側の**スタブ**へリネームされるため）が、
実行時には `UnivapayUnsupportedFeatureError` がスローされます。本ツールは移行時点でこれらの参照を
すべてフラグ付けするため、実行時に初めて気づくのではなく、レビュー可能な形で目に見えるようになります。

| 機能 | 対象クラス / メソッド | 理由 |
|---|---|---|
| 譲渡（Transfer）、台帳（Ledger）、譲渡ステータス変更 | `Transfer`、`TransferStatusChange`、`Ledger`、Mixin の `GetTransfers`/`GetLedgers`/`GetStatusChanges`、メソッドの `getTransfer`、`listTransfers(ByOptions)`、`listLedgers(ByOptions)`、`listStatusChanges(ByOptions)` | 新エンジン SDK では提供されていません。 |
| 加盟店向け入金用銀行口座（Bank Account） | `BankAccount`、Mixin の `GetBankAccounts`、メソッドの `getBankAccount`、`listBankAccounts`、`listBankAccountContextsByOptions`（`BankAccount` インスタンスの `fetch()`/`update()` も実行時に例外を投げますが、上記の `Transfer::fetch()`/`update()` と同様、これらの汎用メソッド名はサポート対象の全リソースで再利用されているためフラグ付け対象には含まれていません） | 新エンジン SDK では提供されていません（一時的な未実装ではなく、恒久的な未サポート操作です）。 |
| Apple Pay トークンの作成 | `ApplePayPayment`（値オブジェクトの生成自体は可能ですが、そこからトークンを作成することはできません） | Apple Pay のトークン作成は新エンジン SDK に組み込まれていません。 |
| 課金の QR マーチャントトークン | `Charge::qrMerchantToken()`（このメソッドのみが対象で、`Charge` 自体は完全にサポートされています） | 対応するエンドポイント `/qr` はサーバー側ですでに非推奨となっており、MPM 用の QR データはトークンオブジェクト経由で取得する形に変わりました。 |

このフラグ付けルールは、メソッド呼び出しの受け側オブジェクトが本当に Univapay のオブジェクトかどうかを
静的に判定できない場合（型ヒントのない引数など）にも、`(verify)` 付きのバリアントを出力します。これらは
「確定」フラグではなくあくまで警告であり、そもそも `Univapay\` への参照が存在するファイルにしか
出現しません。そのため、無関係なクラスにたまたま同名のメソッドが存在するだけの場合にフラグが付くことは
ありません。

これとは別に、厳密には「未サポート」とは異なる 2 つのカテゴリにも、それぞれ専用のマーカー・レポート行が
用意されています。

- **内部 API の利用**（`@univapay-migrate:internal-api`）—— 前述の「書き換えられる内容」で挙げた、
  compat 側に対応するものが存在しない 5 つのクラス。
- **通信例外の利用**（`@univapay-migrate:network-exception`）—— `WpOrg\Requests\*` への参照
  （例: 独自のリトライハンドラーで `WpOrg\Requests\Exception` を catch している場合）。新しい通信層は
  この例外型を一切スローしません。通信エラーは代わりに `UnivapayNetworkError` としてスローされます。

## 既知の注意点

ツール自体の実際の制約がいくつかあります(技術的な詳細は [NOTES.md](NOTES.md) を参照してください)。

- **`importNames(true)` により、無関係かつ既存の完全修飾参照が意図せず短縮されることがあります。**
  このツールが触れたファイル内であれば、Univapay とは無関係な箇所でも
  `new \Some\Unrelated\Thing()` が `new Thing()` と `use Some\Unrelated\Thing;` の追加へ変わる、
  といった変化が起こり得ます。これは見た目上の変化であり、動作は変わりません（同じクラスを同じように
  解決するだけ）。コンパイルエラーになることもなく、Univapay 固有のコードに限った話でもありませんが、
  意図したリネーム範囲を超えた差分として実際に目にすることになります。
- **リネームされる `use` 文の直前にある、開発者が書いた既存のコメントは、移動ではなく削除されます。**
  これは `rector/rector` 自体の内部実装（import ブロックの再構築処理）による挙動であり、本パッケージ側で
  制御できるものではないため、回避策もありません。旧 SDK の `use` 行の直前に説明用のコメントを
  書いている場合、そのコメントは失われるものとして想定してください。
- **ダブルクォートで書かれた文字列 FQCN のリネーム結果は、シングルクォートになります。**
  `"Univapay\\Errors\\X"` と `'Univapay\Errors\X'` はどちらもマッチしてリネームされますが、書き換えを
  行う組み込みルールは常に新しいシングルクォート文字列ノードを生成するため、リネーム前後でクォートの
  スタイルは保持されません。
- **グループ形式の `use` インポートは分割されます。1 ファイルに複数のグループがある場合も対象です。**
  `use Univapay\Enums\{ChargeStatus, RefundStatus};` のような記法は、リネームが実行される前に
  クラスごとの `use` 文へ展開されるため、他の import と同様に問題なくリネームされます
  （この分割処理には、同一ファイル内に 2 つ以上のグループ形式 import がある場合の不具合が初期には
  ありましたが、現在は修正済みで、E2E テスト一式でも検証されています）。

## 移行後に行うこと

1. **テストスイートを実行してください。**
2. **旧 SDK のオブジェクトをシリアライズした状態で保持しているキュー・キャッシュをクリアしてください。**
   クラス名が変わっているため、`class_alias` によるセーフティネットなしに旧 SDK オブジェクトを
   unserialize しようとすると失敗します。
3. **PHPStan/Psalm のベースラインおよび IDE ヘルパーファイルを再生成してください。**
4. **レポートの各セクションをすべて確認してください。** 未サポート機能のフラグは呼び出し箇所ごとの
   判断が、内部 API・通信例外のフラグは手動での移植が、残存参照は手動での修正が、それぞれ必要です。
5. 移行ガイドはこちらです: `https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration`

## ネイティブ SDK へのさらなる移行

`univapay/univapay-sdk-compat` は恒久的な着地点として設計されているわけではありません。その
`UnivapayClient::native()` メソッドは、compat が内部で構築済みの `UnivaPay\UnivapayClientSdkClient`
インスタンスそのものを返します —— 認証情報、ベース URL、タイムアウトも含めて同一のインスタンスであり、
別途独立して構成された 2 つ目のクライアントではありません。これにより**混在モード**が可能になります。
呼び出し箇所を 1 つずつネイティブな型付き SDK へ移行しつつ、まだ移行していない箇所は引き続き compat の
API を呼び出す、という進め方です。両方の経路は同じエンジンを共有しているため、移行期間中に両者の間で
挙動がずれることはありません。混在モードの詳しい使い方と、コンストラクト単位での移行ノートについては
[`univapay-sdk-compat` の README](https://github.com/univapay/univapay-php-sdk-compat#migrating-off-the-compat-layer)
を参照してください。

### フェーズ2: `--phase2`

```bash
vendor/bin/univapay-migrate --phase2
```

既定のセットの代わりに `UnivapaySetList::COMPAT_TO_NATIVE` を実行します。**レビュー支援であり、
無修正で使えるものではありません。** 両方のツリー（`univapay/univapay-sdk-compat` の `src/` と、
ネイティブな `univapay/client-sdk` の `src/Models`）を監査した結果、機械的にリネームしても安全な
データクラス・例外クラスは 1 つも見つかりませんでした —— compat のリソースはすべて public
プロパティを使うのに対し、ネイティブのモデルはすべて getter の背後にある private プロパティを使います。
compat の enum はすべて `TypedEnum` シングルトンであるのに対し、ネイティブの enum は単なる文字列の
`const` です。そして compat の例外サブクラスはどれも多対一で
`UnivaPay\Exceptions\ApiException`/`ApiErrorException` に collapse してしまいます。そのため、この
セットにはリネームマップの代わりに 1 つのルール（`FlagCompatManualMigrationRector`）があり、人間の
判断が必要な各構文の直前に、カテゴリ名とネイティブ側の対応先を示す冪等な `//
@univapay-migrate:phase2-manual` マーカーコメントを挿入します。

| カテゴリ | compat 側の構文 | ネイティブ側の対応 |
|---|---|---|
| `typed-enum` | `ChargeStatus::SUCCESSFUL()`、`->getValue()`、`===`、`switch` | `UnivaPay\Models\ChargeStatus::SUCCESSFUL`（単なる文字列 `const`） |
| `money` | `Money\Money`/`Money\Currency`（moneyphp） | `int $amount` + `string $currency` のフラットな組 |
| `public-property` | `$charge->status` | `$charge->getStatus()` / `ApiResponse` 上の `->getResult()->getStatus()` |
| `poll` | `->awaitResult()` | `pollCharge()`/`pollRefund()`/`pollCancel()`/`pollSubscription()` |
| `pagination` | `Paginated`、`->getNext()`/`->getPrevious()`、`Mixins\Get*` トレイト群 | ネイティブの一覧エンドポイントに対するカーソルパラメータのループ |
| `webhook` | `->parseWebhookData()` | `UnivaPay\Events\Webhooks\*Handler` |
| `client-construction` | `UnivapayClient`/`UnivapayClientOptions`、`AppJWT`/`StoreAppJWT`/`MerchantAppJWT` | `UnivapayClientSdkClientBuilder` + `BearerAuthCredentialsBuilder` |
| `exception-handling` | `Univapay\Compat\Errors\*` の catch/throw/`instanceof` | `ApiException`/`ApiErrorException`。`getHttpResponse()->getStatusCode()`/`getCodeProperty()` で区別 |
| `internal-utility` | `Univapay\Compat\Utility\*` | 対応なし —— 自前でロジックを移植してください |

前述の混在モード用エスケープハッチである `->native()` は、決してフラグ対象になりません。

**入力:** 既定のセットと同じ `--paths`/`--dry-run`/`--strict`/`--allow-unsupported`/`--no-report`
フラグが使えます。対象が `@univapay-migrate:unsupported` マーカーではなく
`@univapay-migrate:phase2-manual` マーカーになる点だけが異なります。`--skip-composer` はここでは
効果を持ちません —— 理由は「出力」を参照してください。

**出力:** `--phase2` は **`composer.json` 自体を一切変更しません。** 手順 2 と 6（`composer
require`/`composer remove`）は常にスキップされ、代わりに次のステップとして表示されます。

```bash
composer require univapay/client-sdk
composer remove univapay/univapay-sdk-compat
```

プリフライトチェックは、旧 SDK のクライアントではなく `Univapay\Compat\UnivapayClient` が
autoload 可能かどうかを確認します（compat がインストールされていなければ、そもそも移行元が
存在しません）。レポートの 4 番目のセクションと `univapay-migrate-report.json` の
`"phase2Manual"` 配列は、未サポート機能セクションとまったく同じ形式です —— 確定したフラグは
`"verified": true`、受け側の型を解決できなかった `(verify)` フラグは `false` となり、終了コードの
優先順位も同じです（`--strict` は `(verify)` を失敗へ昇格させ、`--allow-unsupported` は
いずれの場合も終了コードを `0` に格下げします）。

## 移行完了後にこのパッケージを削除する

本パッケージは `require-dev` としてのみ利用するものであり、移行が完了し
`univapay/univapay-sdk-compat` を使ったコードベースの動作確認が済んだ後は不要になります。

```bash
composer remove --dev univapay/univapay-sdk-migrate
rm rector-univapay.php
```

## ライセンス

MIT。
