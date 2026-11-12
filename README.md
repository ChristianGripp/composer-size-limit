<div align="center">

# 📦 composer-size-limit

**Keep your PHP project lean.**<br>
Set size budgets for files, `vendor/` and build artifacts, and fail the build when they grow.

![PHP](https://img.shields.io/badge/PHP-%5E8.2-777BB4?logo=php&logoColor=white)
![Composer](https://img.shields.io/badge/Composer-2.x-885630?logo=composer&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)
![PHPStan](https://img.shields.io/badge/PHPStan-level%208-4F5B93)

*Inspired by npm's [size-limit](https://github.com/ai/size-limit).*

</div>

---

## ✨ Features

- 🎯 **Budgets per entry**: files, directories, globs or your whole `vendor/`
- 🗜️ **Real-world sizes**: raw bytes, `gzip` or `brotli`
- 🧹 **Production-only `vendor/`**: skip `require-dev` packages with `no-dev`
- 🤖 **CI-ready**: exit code `1` on overflow, machine-readable `--json`
- 🪶 **Zero config files**: everything lives in your `composer.json`
- 🛡️ **Strict validation**: typos in keys, bad units or empty matches fail loudly

## 🚀 Quick start

```bash
composer require --dev core23/composer-size-limit
```

Allow the plugin when Composer asks, then add a budget to your `composer.json`:

```json
{
    "extra": {
        "size-limit": [
            { "name": "Assets", "path": "public/build/**/*.js", "limit": "500 kB", "compression": "gzip" },
            { "name": "Vendor", "path": "vendor", "limit": "20 MB", "no-dev": true }
        ]
    }
}
```

Run it:

```bash
composer size-limit
```

```text
+--------+----------+-------+-------------+----------+
| Name   | Size     | Limit | Compression | Status   |
+--------+----------+-------+-------------+----------+
| Assets | 312.4 kB | 500 kB| gzip        | OK       |
| Vendor | 24.1 MB  | 20 MB | none        | EXCEEDED |
+--------+----------+-------+-------------+----------+
```

## ⚙️ Configuration

Each entry in `extra.size-limit` supports:

| Key           | Required | Default | Description                                                    |
|---------------|:--------:|:-------:|----------------------------------------------------------------|
| `name`        |    ✅    |    –    | Label in the report                                            |
| `path`        |    ✅    |    –    | File, directory (recursive) or glob, or a list of them         |
| `limit`       |    ✅    |    –    | Maximum size, e.g. `"500 kB"`, `"2 MiB"` or plain bytes       |
| `compression` |          | `none`  | `none`, `gzip` or `brotli` (needs `ext-brotli`)                |
| `no-dev`      |          | `false` | Ignore files of packages that are only in `require-dev`        |

### 🔍 Paths and globs

| Pattern            | Matches                                    |
|--------------------|--------------------------------------------|
| `build/app.phar`   | a single file                              |
| `vendor`           | every file below the directory             |
| `dist/*.js`        | `.js` files directly in `dist/`            |
| `dist/**/*.js`     | `.js` files at any depth                   |
| `dist/*.{js,css}`  | alternatives                               |

Paths are relative to the directory of `composer.json`. Symlinks are ignored, overlapping paths are counted once, and an entry without any match is an error.

### 📏 Units

| Decimal (×1000)  | Binary (×1024)     |
|------------------|--------------------|
| `kB`, `MB`, `GB` | `KiB`, `MiB`, `GiB` |

Units are case-insensitive. With `gzip` or `brotli`, every file is compressed on its own and the results are summed, just like they would be served over HTTP.

## 🖥️ Usage

```bash
composer size-limit            # table output, exit code 1 if a limit is exceeded
composer size-limit --json     # machine-readable output for CI
composer size-limit --no-dev   # apply "no-dev" to every entry
```

<details>
<summary>📄 JSON output</summary>

```json
{
    "passed": false,
    "results": [
        { "name": "Assets", "size": 312400, "limit": 500000, "compression": "gzip", "passed": true },
        { "name": "Vendor", "size": 24100000, "limit": 20000000, "compression": "none", "passed": false }
    ]
}
```

</details>

## 🔄 Continuous integration

```yaml
# .github/workflows/size.yml
name: Size limit
on: [pull_request]

jobs:
  size:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      # the plugin is a dev dependency, so install with dev packages
      # and use "no-dev": true on entries that must only count production code
      - run: composer install --prefer-dist
      - run: composer size-limit
```

## 🧪 Development

```bash
composer install
composer test   # PHPUnit
composer stan   # PHPStan level 8
```

## 📄 License

MIT
