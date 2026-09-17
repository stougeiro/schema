![PHP](https://img.shields.io/badge/PHP-%20^8.2-777BB4)
![PHPStan-Level](https://img.shields.io/badge/PHPStan-Level%209-224488)
![Pest-php](https://img.shields.io/badge/Tests-Passed-019733)
![License](https://img.shields.io/badge/License-MIT-777)

# Schema

A lightweight **structural gate** for PHP payloads.  
Validates the shape before business logic validates the content.


## ✨ Features

- **Structural gate pattern**:  
  Validates structure before business logic. A clean, intentional order.

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

## 🚦 What Schema Is

Schema validates the **structure** of your data — not the business rules.

It answers one question: **"Is this payload shaped correctly?"**
- Does the field exist?
- Is it the right type?
- Is the nesting correct?

It does NOT answer: **"Is this value meaningful?"**
- Is this email deliverable?
- Is this ID registered?
- Is this amount within limits?

That's a different concern, handled elsewhere.

---

## 🏗️ The Gate Pattern

```
Input → Structural Gate → Business Logic → Output
```

Structural validation is **cheap**. Business validation is **richer**.

Schema runs first — not because it's faster, but because it's
the right order. A malformed structure doesn't need business rules.
A valid structure is ready for them.

```php
$schema = new Schema([
    'email' => 'string',
    'amount' => 'float',
]);

// Structure first
if ( ! $schema->validate($data, $error)) {
    return response(422, $error);
}

// Business logic second
$email = EmailService::validate($data['email']);
```

Schema doesn't replace business validation — it **precedes** it.

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
/**
 * Using the global schema() helper (optional)
 * 
 * For convenience, you may use the global schema() helper
 * instead of instantiating Schema manually.
 * It behaves exactly the same, but makes nested definitions easier to read
 * */

$schema = schema([
  'id' => 'int',
  'name' => 'string',
  'email' => 'string',
  'status' => 'enum(active|inactive|pending)',
  'metadata' => schema([
    'ip' => 'string',
    'userAgent' => 'string:o',
  ])->optional(),
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

## ⚡ Performance

Schema is designed to be lightweight. Zero dependencies, minimal
overhead, static caching for repeated patterns.

For typical API payloads, validation runs almost instantly — fast
enough to be a transparent gate in any request lifecycle.

Schema doesn't aim to be the fastest validator. It aims to be
the right first step — structural validation before business
validation, at a negligible cost.

---

## 🧠 Why Schema?

Modern PHP applications pass arrays everywhere — API payloads,
configuration blocks, decoded JSON, event messages. PHP offers
no native way to validate these structures.

Schema fills that gap — not as a business validator, but as a
structural gatekeeper.

**Why a gate?**

Because structure should be confirmed before business rules apply.
A payload with the wrong shape doesn't need business evaluation.
It deserves a fast, clear rejection.

**What Schema guarantees:**

- The payload has the expected fields
- Each field has the expected type
- Nested structures match the expected shape
- Errors point exactly to where the structure breaks

**What Schema leaves to you:**

- Whether the email is deliverable
- Whether the amount is within limits
- Whether the ID exists in your database

Schema is a gate — it opens for well-shaped data and closes for
malformed payloads. What happens after the gate is your domain's
responsibility.

---

## 🤝 Contributions

Contributions are welcome.
Feel free to open issues or submit pull requests.

<br>

[<img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" width="170"/>](https://www.buymeacoffee.com/stougeiro)