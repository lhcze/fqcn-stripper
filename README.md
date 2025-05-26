# FQCN Stripper

FQCN Stripper is a small and flexible PHP 8.3+ utility for extracting and formatting the base class name from a Fully-Qualified Class Name (FQCN).

It supports string transformations like `lowercase`, `UPPERCASE`, and `Ucfirst` — with optional multibyte-safe handling (via `mbstring`).

---

## 🚀 Features

- Extracts base class name from a fully-qualified name
- Bitmask modifiers for string formatting:
    - `LOWER` — lowercased
    - `UC` — ucfirst
    - `UPPER` — fully uppercased
    - `LOW_UC` — lower + ucfirst
    - `MULTIBYTE` — applies transformations using `mb_` functions
- Supports both strings and objects
- Caches internally for performance
- Simple API for batch processing

---

## 🛠️ Requirements

- **PHP 8.3+**
- Optional: `mbstring` extension (for `MULTIBYTE`)

---

## 📦 Installation

```bash
composer require lhcze/fqcn-stripper
```

## ✅ Usage

```php
use Lhcze\FqcnStripper\FqcnStripper;

FqcnStripper::strip('App\\Entity\\User'); // "User"
FqcnStripper::strip('App\\Entity\\User', FqcnStripper::LOWER); // "user"
FqcnStripper::strip('App\\Entity\\User', FqcnStripper::LOW_UC); // "User"
FqcnStripper::strip('App\\Entity\\User', FqcnStripper::UPPER); // "USER"
```

```php
FqcnStripper::strip('App\\Entity\\Üser', FqcnStripper::LOW_UC | FqcnStripper::MULTIBYTE); // "Üser"
```

```php
$object = new \App\Entity\User();
FqcnStripper::strip($object, FqcnStripper::LOWER); // "user"
```

```php
FqcnStripper::strip('App\\WeirdObjects\\UserHandlerDtoEvent', FqcnStripper::TRIM_POSTFIX) // User
```

### 🔁 Batch usage

```php
$list = [
    'App\\Model\\CustomerModel',
    'App\\Entity\\OrderEntity',
    'App\\Controller\\Admin\\DashboardController',
];

FqcnStripper::stripThemAll($list, FqcnStripper::LOW_UC | FqcnStripper::TRIM_POSTFIX);
// ["Customer", "Order", "Dashboard"]
```

## 🧪 Code Quality & Local Development

### Tools & Standards
| Command            | Standards | Description                   |
|--------------------|-----------|-------------------------------|
| `composer phpunit` | N/A       | Run PHPUnit tests             |
| `composer cs`      | PSR12     | Check code style (dry-run)    |
| `composer cs-fix`  | PSR12     | Auto-fix coding standards     |
| `composer phpstan` | Level 9   | Run static analysis (PHPStan) |
| `composer check`   | N/A       | Runs all checks at once       |


Install dependencies and run QA tools:

```bash
composer install
composer check
```