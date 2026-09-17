<?php declare(strict_types=1);

use STDW\Schema\Schema;

/*
|--------------------------------------------------------------------------
| Payment Response Helper
|--------------------------------------------------------------------------
*/

function createPaymentSchema(): Schema
{
    return new Schema([
        'id'            => 'string',
        'status'        => 'enum(pending|approved|declined|refunded|cancelled)',
        'amount'        => 'float',
        'currency'      => 'string',
        'created_at'    => 'string',
        'updated_at'    => 'string',

        'payment_method' => new Schema([
            'type'         => 'enum(credit_card|debit_card|pix|boleto)',
            'installments' => 'int',
            'card' => (new Schema([
                'brand'       => 'enum(visa|mastercard|amex|elo)',
                'last_four'   => 'string',
                'exp_month'   => 'int',
                'exp_year'    => 'int',
                'holder_name' => 'string',
            ]))->optional(),
        ]),

        'customer' => new Schema([
            'id'        => 'string',
            'name'      => 'string',
            'email'     => 'string',
            'document'  => 'string',
            'phone'     => 'string:o',
            'address' => (new Schema([
                'street'       => 'string',
                'number'       => 'string',
                'complement'   => 'string:o',
                'neighborhood' => 'string',
                'city'         => 'string',
                'state'        => 'string',
                'zip'          => 'string',
                'country'      => 'string',
            ]))->optional(),
        ]),

        'items' => new Schema([
            'id'          => 'string',
            'description' => 'string',
            'quantity'    => 'int',
            'unit_price'  => 'float',
            'total'       => 'float',
        ]),

        'metadata' => (new Schema([
            'key1' => 'string:o',
            'key2' => 'string:o',
        ]))->optional(),

        'fees' => (new Schema([
            'gateway'    => 'float',
            'antifraud'  => 'float',
            'processing' => 'float',
        ]))->optional(),

        'history' => new Schema([
            'status'    => 'enum(pending|approved|declined|refunded|cancelled)',
            'timestamp' => 'string',
            'reason'    => 'string:o',
        ]),
    ]);
}

function createFullPaymentPayload(): array
{
    return [
        'id'         => 'txn_1234567890',
        'status'     => 'approved',
        'amount'     => 199.90,
        'currency'   => 'BRL',
        'created_at' => '2025-01-15T10:30:00Z',
        'updated_at' => '2025-01-15T10:30:05Z',

        'payment_method' => [
            'type'         => 'credit_card',
            'installments' => 3,
            'card' => [
                'brand'       => 'visa',
                'last_four'   => '4242',
                'exp_month'   => 12,
                'exp_year'    => 2027,
                'holder_name' => 'Sidney Tougeiro',
            ],
        ],

        'customer' => [
            'id'        => 'cust_9876543210',
            'name'      => 'Sidney Tougeiro',
            'email'     => 'sidney@example.com',
            'document'  => '123.456.789-00',
            'phone'     => '+5511999999999',
            'address' => [
                'street'       => 'Rua Example',
                'number'       => '123',
                'complement'   => 'Apto 42',
                'neighborhood' => 'Centro',
                'city'         => 'São Paulo',
                'state'        => 'SP',
                'zip'          => '01234-567',
                'country'      => 'BR',
            ],
        ],

        'items' => [
            [
                'id'          => 'item_001',
                'description' => 'Product A',
                'quantity'    => 2,
                'unit_price'  => 99.95,
                'total'       => 199.90,
            ],
        ],

        'metadata' => [
            'key1' => 'value1',
            'key2' => 'value2',
        ],

        'fees' => [
            'gateway'    => 4.99,
            'antifraud'  => 1.50,
            'processing' => 2.30,
        ],

        'history' => [
            [
                'status'    => 'pending',
                'timestamp' => '2025-01-15T10:30:00Z',
                'reason'    => null,
            ],
            [
                'status'    => 'approved',
                'timestamp' => '2025-01-15T10:30:05Z',
                'reason'    => null,
            ],
        ],
    ];
}

function createMinimalPaymentPayload(): array
{
    return [
        'id'         => 'txn_1234567890',
        'status'     => 'approved',
        'amount'     => 199.90,
        'currency'   => 'BRL',
        'created_at' => '2025-01-15T10:30:00Z',
        'updated_at' => '2025-01-15T10:30:05Z',

        'payment_method' => [
            'type'         => 'pix',
            'installments' => 1,
        ],

        'customer' => [
            'id'        => 'cust_9876543210',
            'name'      => 'Sidney Tougeiro',
            'email'     => 'sidney@example.com',
            'document'  => '123.456.789-00',
        ],

        'items' => [
            [
                'id'          => 'item_001',
                'description' => 'Product A',
                'quantity'    => 2,
                'unit_price'  => 99.95,
                'total'       => 199.90,
            ],
        ],

        'history' => [
            [
                'status'    => 'approved',
                'timestamp' => '2025-01-15T10:30:05Z',
                'reason'    => null,
            ],
        ],
    ];
}


//
// 1. Schema classification performance
//

it('classifies flat schema (5 fields) in under 25ms for 10000 calls', function () {
    $schema = new Schema([
        'id'     => 'int',
        'name'   => 'string',
        'email'  => 'string',
        'age'    => 'int',
        'active' => 'boolean',
    ]);

    $start = hrtime(true);

    for ($i = 0; $i < 10000; $i++) {
        $schema->validate([
            'id'     => 1,
            'name'   => 'Test',
            'email'  => 'test@example.com',
            'age'    => 25,
            'active' => true,
        ]);
    }

    $elapsed = (hrtime(true) - $start) / 1e6;

    expect($elapsed)->toBeLessThan(25);
});


//
// 2. Enum validation with cache
//

it('validates enum (5 options) in under 10ms for 10000 calls', function () {
    $schema = new Schema(['status' => 'enum(a|b|c|d|e)']);

    // Warmup cache
    $schema->validate(['status' => 'a']);

    $start = hrtime(true);

    for ($i = 0; $i < 10000; $i++) {
        $schema->validate(['status' => 'c']);
    }

    $elapsed = (hrtime(true) - $start) / 1e6;

    expect($elapsed)->toBeLessThan(10);
});


//
// 3. Const validation with cache
//

it('validates const in under 10ms for 10000 calls', function () {
    $schema = new Schema(['role' => 'const(admin)']);

    // Warmup cache
    $schema->validate(['role' => 'admin']);

    $start = hrtime(true);

    for ($i = 0; $i < 10000; $i++) {
        $schema->validate(['role' => 'admin']);
    }

    $elapsed = (hrtime(true) - $start) / 1e6;

    expect($elapsed)->toBeLessThan(10);
});


//
// 4. Nested schema (3 levels) performance
//

it('validates nested schema (3 levels) in under 25ms for 10000 calls', function () {
    $schema = new Schema([
        'a' => new Schema([
            'b' => new Schema([
                'c' => 'int',
            ]),
        ]),
    ]);

    $start = hrtime(true);

    for ($i = 0; $i < 10000; $i++) {
        $schema->validate(['a' => ['b' => ['c' => 1]]]);
    }

    $elapsed = (hrtime(true) - $start) / 1e6;

    expect($elapsed)->toBeLessThan(25);
});


//
// 5. Large payload (20 fields) performance
//

it('validates large payload (20 fields) in under 85ms for 10000 calls', function () {
    $schema = new Schema([
        'f1'  => 'string',
        'f2'  => 'string',
        'f3'  => 'string',
        'f4'  => 'string',
        'f5'  => 'string',
        'f6'  => 'int',
        'f7'  => 'int',
        'f8'  => 'int',
        'f9'  => 'int',
        'f10' => 'int',
        'f11' => 'float',
        'f12' => 'float',
        'f13' => 'bool',
        'f14' => 'boolean',
        'f15' => 'array',
        'f16' => 'list',
        'f17' => 'enum(a|b|c)',
        'f18' => 'const(x)',
        'f19' => '?string',
        'f20' => 'string:o',
    ]);

    $payload = [
        'f1'  => 'a', 'f2'  => 'b', 'f3'  => 'c', 'f4'  => 'd', 'f5'  => 'e',
        'f6'  => 1, 'f7'  => 2, 'f8'  => 3, 'f9'  => 4, 'f10' => 5,
        'f11' => 1.1, 'f12' => 2.2,
        'f13' => true, 'f14' => false,
        'f15' => [], 'f16' => [1, 2, 3],
        'f17' => 'a', 'f18' => 'x',
    ];

    $start = hrtime(true);

    for ($i = 0; $i < 10000; $i++) {
        $schema->validate($payload);
    }

    $elapsed = (hrtime(true) - $start) / 1e6;

    expect($elapsed)->toBeLessThan(85);
});


//
// 6. Error path generation performance
//

it('generates error path in under 40ms for 10000 calls', function () {
    $schema = new Schema([
        'a' => new Schema([
            'b' => new Schema([
                'c' => new Schema([
                    'd' => new Schema([
                        'e' => 'int',
                    ]),
                ]),
            ]),
        ]),
    ]);

    $start = hrtime(true);

    for ($i = 0; $i < 10000; $i++) {
        $schema->validate(['a' => ['b' => ['c' => ['d' => ['e' => 'not-int']]]]]);
    }

    $elapsed = (hrtime(true) - $start) / 1e6;

    expect($elapsed)->toBeLessThan(40);
});


/*
|--------------------------------------------------------------------------
| Payment Response Performance Tests
|--------------------------------------------------------------------------
*/


//
// P1 — Payment schema classification (full schema, ~40 fields, 4 levels)
//

it('classifies payment response schema in under 175ms for 10000 calls', function () {
    $schema = createPaymentSchema();
    $payload = createFullPaymentPayload();

    $start = hrtime(true);

    for ($i = 0; $i < 10000; $i++) {
        $schema->validate($payload);
    }

    $elapsed = (hrtime(true) - $start) / 1e6;

    expect($elapsed)->toBeLessThan(175);
});


//
// P2 — All optional fields present
//

it('validates payment response with all optional fields in under 175ms for 10000 calls', function () {
    $schema = createPaymentSchema();
    $payload = createFullPaymentPayload();

    $start = hrtime(true);

    for ($i = 0; $i < 10000; $i++) {
        $schema->validate($payload);
    }

    $elapsed = (hrtime(true) - $start) / 1e6;

    expect($elapsed)->toBeLessThan(175);
});


//
// P3 — Optional fields absent (minimal payment)
//

it('validates payment response without optional fields in under 100ms for 10000 calls', function () {
    $schema = createPaymentSchema();
    $payload = createMinimalPaymentPayload();

    $start = hrtime(true);

    for ($i = 0; $i < 10000; $i++) {
        $schema->validate($payload);
    }

    $elapsed = (hrtime(true) - $start) / 1e6;

    expect($elapsed)->toBeLessThan(100);
});


//
// P4 — 10 items
//

it('validates payment response with 10 items in under 175ms for 10000 calls', function () {
    $schema = createPaymentSchema();
    $payload = createFullPaymentPayload();

    $items = [];
    for ($n = 0; $n < 10; $n++) {
        $items[] = [
            'id'          => "item_{$n}",
            'description' => "Product {$n}",
            'quantity'    => $n + 1,
            'unit_price'  => 10.0 * ($n + 1),
            'total'       => 10.0 * ($n + 1) * ($n + 1),
        ];
    }
    $payload['items'] = $items;

    $start = hrtime(true);

    for ($i = 0; $i < 10000; $i++) {
        $schema->validate($payload);
    }

    $elapsed = (hrtime(true) - $start) / 1e6;

    expect($elapsed)->toBeLessThan(175);
});


//
// P5 — 20 history entries
//

it('validates payment response with 20 history entries in under 180ms for 10000 calls', function () {
    $schema = createPaymentSchema();
    $payload = createFullPaymentPayload();

    $statuses = ['pending', 'approved', 'declined', 'refunded', 'cancelled'];
    $history = [];
    for ($n = 0; $n < 20; $n++) {
        $history[] = [
            'status'    => $statuses[$n % 5],
            'timestamp' => "2025-01-15T10:30:{$n}Z",
            'reason'    => null,
        ];
    }
    $payload['history'] = $history;

    $start = hrtime(true);

    for ($i = 0; $i < 10000; $i++) {
        $schema->validate($payload);
    }

    $elapsed = (hrtime(true) - $start) / 1e6;

    expect($elapsed)->toBeLessThan(180);
});


//
// P6 — Deep error path (customer.address.zip)
//

it('generates deep error path in payment schema in under 160ms for 10000 calls', function () {
    $schema = createPaymentSchema();
    $payload = createFullPaymentPayload();
    $payload['customer']['address']['zip'] = 123;

    $start = hrtime(true);

    for ($i = 0; $i < 10000; $i++) {
        $schema->validate($payload);
    }

    $elapsed = (hrtime(true) - $start) / 1e6;

    expect($elapsed)->toBeLessThan(160);
});


//
// P7 — Simple schema vs payment schema ratio
//

it('payment schema is within 9x of simple schema performance', function () {
    $simpleSchema = new Schema([
        'id'     => 'int',
        'name'   => 'string',
        'email'  => 'string',
        'age'    => 'int',
        'active' => 'boolean',
    ]);
    $simplePayload = [
        'id' => 1, 'name' => 'Test', 'email' => 'test@example.com',
        'age' => 25, 'active' => true,
    ];

    $paymentSchema = createPaymentSchema();
    $paymentPayload = createFullPaymentPayload();

    $iterations = 10000;

    $start = hrtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $simpleSchema->validate($simplePayload);
    }
    $simpleTime = (hrtime(true) - $start) / 1e6;

    $start = hrtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $paymentSchema->validate($paymentPayload);
    }
    $paymentTime = (hrtime(true) - $start) / 1e6;

    $ratio = $paymentTime / max($simpleTime, 0.001);

    expect($ratio)->toBeLessThan(9);
});
