![phpstan-level](https://img.shields.io/badge/PHPStan-Level%209-brightgreen)

# Schema

A lightweight and expressive **payload validation library** for PHP.  
Designed to be minimalistic, predictable and framework‑agnostic, it provides a clean way to validate arrays and nested structures — with **clear error messages**, **type‑safe rules**, and **full path context** for deep schemas.


## ✨ Features

- **Simple, explicit schemas**:  
  Define validation rules using native PHP arrays and nested `Schema` objects.

- **Full path error messages**:  
  Errors show exactly *where* the validation failed:  
  `Invalid required [user.address.zip]: expected [int], got ['ABC' (string)]`

- **Nested schema support**:  
  Validate complex payloads with unlimited depth.

- **Optional fields**:  
  Use `string:o` or `$schema->optional()` for optional nested schemas.

- **Nullable types**:  
  Prefix any type with `?` (e.g., `?string`).

- **Enum & Const validation**  
  `enum(a|b|c)` and `const(value)`.

- **Strict type matching**  
  Supports: `string`, `int`, `float`, `bool`, `array`, `object`, `list`, `null`.

- **Zero dependencies**  
  Pure PHP. No magic. No framework coupling.

---

## 📦 Installation

Install via Composer:

```bash
composer require stougeiro/schema
```


## 🚀 Usage Example

### Simple validation

```php
use STDW\Schema\Schema;

$schema = new Schema([
  'name' => 'string',
  'age' => 'int',
]);

$payload = [
  'name' => 'Sidney',
  'age' => 42,
];

$schema->validate($payload); // true
```

### Complex validation

```php
use STDW\Schema\Schema;

$schema = new Schema([
  'id' => 'int',
  'name' => 'string',
  'email' => 'string',
  'status' => 'enum(active|inactive|pending)',
  'metadata' => (new Schema([
    'ip' => 'string',
    'userAgent' => 'string:o',
  ]))->optional(),
]);

$payload = [
  'id'     => 1,
  'name'   => 'Sidney',
  'email'  => 'sidney@example.com',
  'status' => 'active',
  'metadata' => [
    'ip' => '127.0.0.1',
  ],
];

$schema->validate($payload); // true
```


## 🔥 Error Handling

`Schema::validate()` accepts an optional second parameter by reference that will contain the first validation error message when the payload is invalid:

```php
use STDW\Schema\Schema;

$schema = new Schema([
    'name' => 'string',
    'age'  => 'int',
]);

$payload = [
    'name' => 'Sidney',
    'age'  => '12',
];

if ( ! $schema->validate($payload, $error)) {
    echo $error; // Invalid required [age]: expected [int], got ['12' (string)]
}
```

When using nested schemas, the error message includes the full path to the failing field:

```php
$schema = new Schema([
  'template' => 'enum(user|product|payment)',
  'user' => new Schema([
    'id'    => 'int',
    'name'  => 'string',
    'email' => 'string',
  ]),
]);

$payload = [
  'template' => 'user',
  'user' => [
    'id'    => '12',
    'name'  => 'Sidney',
    'email' => 'sidney@example.com',
  ],
];

if ( ! $schema->validate($payload, $error)) {
    echo $error; // Invalid required [user.id]: expected [int], got ['12' (string)]
}
```

---

## 🧠 Why Schema?

Modern PHP applications frequently rely on arrays as data carriers — API payloads, DTOs, configuration blocks, decoded JSON, request bodies, and more. Yet PHP offers no native, structured way to validate these arrays. Most solutions introduce heavy abstractions, framework‑specific validators, annotations, attributes, or magic behavior that obscures what is actually happening.

Schema takes the opposite approach.

It embraces the simplicity of plain PHP arrays while providing a predictable, explicit and type‑safe validation layer. No hidden conventions. No reflection tricks. No framework dependencies. Just clear rules and clear errors.

Schema is built for developers who value:

- Explicitness over magic  
  Every rule is visible and intentional. No guessing.

- Predictable behavior  
 Validation is deterministic and easy to reason about.

- Readable error messages  
  Full path context (user.address.zip) makes debugging payloads effortless.

- Nested structure support  
  Deeply nested schemas behave exactly like shallow ones.

- Zero dependencies  
  Works anywhere — CLI scripts, microservices, APIs, legacy systems, modern frameworks.

- Type safety and clarity  
  Native types, nullable types, optional fields, enums, const values — all expressed simply.

Schema aims to be a small, expressive and reliable tool that solves one problem extremely well:
validating structured data in PHP without unnecessary complexity.
