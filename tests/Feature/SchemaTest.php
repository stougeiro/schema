<?php declare(strict_types=1);

use STDW\Schema\Schema;

//
// 1. Simple Payload Validation
//

it('validates a simple user payload', function () {
    $schema = new Schema([
        'name' => 'string',
        'age'  => 'int',
    ]);

    $payload = [
        'name' => 'Sidney',
        'age'  => 42,
    ];

    expect($schema->validate($payload))->toBeTrue();
});


//
// 2. Missing Required Field
//

it('fails when a required field is missing', function () {
    $schema = new Schema([
        'name' => 'string',
        'age'  => 'int',
    ]);

    $payload = [
        'name' => 'Sidney',
    ];

    $schema->validate($payload, $error);

    expect($error)->toBe('Missing required: age');
});


//
// 3. Real-World Enum Validation
//

it('validates a real-world enum', function () {
    $schema = new Schema([
        'status' => 'enum(active|inactive|pending)',
    ]);

    $payload = ['status' => 'active'];
    expect($schema->validate($payload))->toBeTrue();

    $payload = ['status' => 'pending'];
    expect($schema->validate($payload))->toBeTrue();

    $payload = ['status' => 'invalid'];
    expect($schema->validate($payload))->toBeFalse();
});


//
// 4. Const Rule Validation
//

it('validates const rule for fixed type', function () {
    $schema = new Schema([
        'role' => 'const(admin)',
    ]);

    $payload = ['role' => 'admin'];
    expect($schema->validate($payload))->toBeTrue();

    $payload = ['role' => 'user'];
    expect($schema->validate($payload))->toBeFalse();
});


//
// 5. Nullable Field Validation
//

it('validates nullable fields', function () {
    $schema = new Schema([
        'nickname' => '?string',
    ]);

    $payload = ['nickname' => null];
    expect($schema->validate($payload))->toBeTrue();

    $payload = ['nickname' => 'Sid'];
    expect($schema->validate($payload))->toBeTrue();

    $payload = ['nickname' => 123];
    expect($schema->validate($payload))->toBeFalse();
});


//
// 6. Optional Field Validation
//

it('validates optional fields using :o suffix', function () {
    $schema = new Schema([
        'bio' => 'string:o',
    ]);

    $payload = [];
    expect($schema->validate($payload))->toBeTrue();

    $payload = ['bio' => 'Hello world'];
    expect($schema->validate($payload))->toBeTrue();

    $payload = ['bio' => 123];
    expect($schema->validate($payload))->toBeFalse();
});


//
// 7. Nested Schema Validation
//

it('validates nested user profile', function () {
    $schema = new Schema([
        'user' => new Schema([
            'id'    => 'int',
            'name'  => 'string',
            'email' => 'string',
        ]),
    ]);

    $payload = [
        'user' => [
            'id'    => 10,
            'name'  => 'Sidney',
            'email' => 'sidney@example.com',
        ],
    ];

    expect($schema->validate($payload))->toBeTrue();
});


//
// 8. Nested Schema Failure
//

it('fails nested schema validation', function () {
    $schema = new Schema([
        'template' => 'string',
        'user' => new Schema([
            'id'    => 'int',
            'name'  => 'string',
            'email' => 'string',
        ]),
    ]);

    $payload = [
        'template' => 'user_model',
        'user' => [
            'id'    => 'not-int',
            'name'  => 'Sidney',
            'email' => 'sidney@example.com',
        ],
    ];

    $schema->validate($payload, $error);

    expect($error)->toBe("Invalid required [user.id]: expected [int], got ['not-int' (string)]");
});


//
// 9. Optional Nested Schema
//

it('validates optional nested schema', function () {
    $schema = new Schema([
        'profile' => (new Schema([
            'bio' => 'string',
            'age' => 'int',
        ]))->optional(),
    ]);

    $payload = [];
    expect($schema->validate($payload))->toBeTrue();

    $payload = [
        'profile' => [
            'bio' => 'Hello',
            'age' => 30,
        ]
    ];
    expect($schema->validate($payload))->toBeTrue();

    $payload = [
        'profile' => [
            'bio' => 'Hello',
            'age' => 'wrong',
        ]
    ];
    expect($schema->validate($payload))->toBeFalse();
});


//
// 10. Unexpected Fields
//

it('fails when unexpected fields are present', function () {
    $schema = new Schema([
        'name' => 'string',
    ]);

    $payload = [
        'name' => 'Sidney',
        'extra' => 'not allowed',
    ];

    $schema->validate($payload, $error);

    expect($error)->toBe('Unexpected fields: [extra]');
});


//
// 11. Realistic API Payload Validation
//

it('validates a realistic API payload', function () {
    $schema = new Schema([
        'id'       => 'int',
        'name'     => 'string',
        'email'    => 'string',
        'status'   => 'enum(active|inactive|pending)',
        'metadata' => (new Schema([
            'ip'        => 'string',
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

    expect($schema->validate($payload))->toBeTrue();
});


//
// 12. API Payload with boolean
//

it('validates API payload with boolean field', function () {
    $schema = new Schema([
        'id'     => 'int',
        'name'   => 'string',
        'active' => 'boolean',
    ]);

    $payload = ['id' => 1, 'name' => 'Test', 'active' => true];
    expect($schema->validate($payload))->toBeTrue();

    $payload = ['id' => 1, 'name' => 'Test', 'active' => false];
    expect($schema->validate($payload))->toBeTrue();

    $payload = ['id' => 1, 'name' => 'Test', 'active' => 1];
    expect($schema->validate($payload))->toBeFalse();
});


//
// 13. API Payload with numeric fields
//

it('validates API payload with numeric fields', function () {
    $schema = new Schema([
        'price'    => 'number',
        'quantity' => 'numeric',
    ]);

    $payload = ['price' => 10, 'quantity' => 5];
    expect($schema->validate($payload))->toBeTrue();

    $payload = ['price' => 10, 'quantity' => '5'];
    expect($schema->validate($payload))->toBeTrue();

    $payload = ['price' => '10', 'quantity' => 5];
    expect($schema->validate($payload))->toBeFalse();
});


//
// 14. Deeply nested error path
//

it('validates deeply nested payload with error path', function () {
    $schema = new Schema([
        'company' => new Schema([
            'address' => new Schema([
                'zip' => 'string',
            ]),
        ]),
    ]);

    $payload = ['company' => ['address' => ['zip' => 123]]];

    $schema->validate($payload, $error);

    expect($error)->toBe("Invalid required [company.address.zip]: expected [string], got [123 (int)]");
});


//
// 15. Multiple unexpected fields in real payload
//

it('shows all unexpected fields in error', function () {
    $schema = new Schema([
        'id'   => 'int',
        'name' => 'string',
    ]);

    $payload = ['id' => 1, 'name' => 'Test', 'extra1' => 'x', 'extra2' => 'y'];

    $schema->validate($payload, $error);

    expect($error)->toBe('Unexpected fields: [extra1, extra2]');
});
