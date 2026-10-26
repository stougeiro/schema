<?php declare(strict_types=1);

    use STDW\Schema\Schema;

    //
    // 1. Constructor
    //

    it('accepts any schema array', function () {
        $schema = new Schema([
            'name' => 'string',
            'age'  => 'int',
            'obj'  => new Schema([]),
            'mix'  => 123, // invalid but accepted by constructor
        ]);

        expect($schema)->toBeInstanceOf(Schema::class);
    });


    //
    // 2. optional()
    //

    it('marks schema as optional', function () {
        $schema = (new Schema([]))->optional();

        $reflection = new ReflectionClass($schema);
        $prop = $reflection->getProperty('optional');

        expect($prop->getValue($schema))->toBeTrue();
    });

    it('optional returns itself (fluent)', function () {
        $schema = new Schema([]);
        expect($schema->optional())->toBe($schema);
    });


    //
    // 3. Primitive type validation (via validate)
    //

    it('validates string type', function () {
        $schema = new Schema(['name' => 'string']);

        expect($schema->validate(['name' => 'Sidney']))->toBeTrue();
        expect($schema->validate(['name' => 123]))->toBeFalse();
    });

    it('validates int type', function () {
        $schema = new Schema(['age' => 'int']);

        expect($schema->validate(['age' => 10]))->toBeTrue();
        expect($schema->validate(['age' => '10']))->toBeFalse();
    });

    it('validates float type', function () {
        $schema = new Schema(['price' => 'float']);

        expect($schema->validate(['price' => 1.5]))->toBeTrue();
        expect($schema->validate(['price' => 10]))->toBeFalse();
    });

    it('validates bool type', function () {
        $schema = new Schema(['flag' => 'bool']);

        expect($schema->validate(['flag' => true]))->toBeTrue();
        expect($schema->validate(['flag' => false]))->toBeTrue();
        expect($schema->validate(['flag' => 'true']))->toBeTrue();
        expect($schema->validate(['flag' => 'false']))->toBeTrue();
        expect($schema->validate(['flag' => 1]))->toBeTrue();
        expect($schema->validate(['flag' => 0]))->toBeTrue();

        expect($schema->validate(['flag' => 'yes']))->toBeFalse();
    });

    it('validates null type', function () {
        $schema = new Schema(['x' => 'null']);

        expect($schema->validate(['x' => null]))->toBeTrue();
        expect($schema->validate(['x' => 'null']))->toBeFalse();
    });

    it('validates array type', function () {
        $schema = new Schema(['items' => 'array']);

        expect($schema->validate(['items' => []]))->toBeTrue();
        expect($schema->validate(['items' => 'not-array']))->toBeFalse();
    });

    it('validates list type', function () {
        $schema = new Schema(['items' => 'list']);

        expect($schema->validate(['items' => [1,2,3]]))->toBeTrue();
        expect($schema->validate(['items' => ['a' => 1]]))->toBeFalse();
    });

    it('validates object type', function () {
        $schema = new Schema(['obj' => 'object']);

        expect($schema->validate(['obj' => new stdClass]))->toBeTrue();
        expect($schema->validate(['obj' => []]))->toBeFalse();
    });

    it('validates callable type', function () {
        $schema = new Schema(['fn' => 'callable']);

        expect($schema->validate(['fn' => fn() => 1]))->toBeTrue();
        expect($schema->validate(['fn' => 'strtoupper']))->toBeTrue();
        expect($schema->validate(['fn' => 123]))->toBeFalse();
    });


    //
    // 4. Nullable types
    //

    it('validates nullable string', function () {
        $schema = new Schema(['name' => '?string']);

        expect($schema->validate(['name' => null]))->toBeTrue();
        expect($schema->validate(['name' => 'Sidney']))->toBeTrue();
        expect($schema->validate(['name' => 123]))->toBeFalse();
    });


    //
    // 5. const(x)
    //

    it('validates const rule', function () {
        $schema = new Schema(['role' => 'const(admin)']);

        expect($schema->validate(['role' => 'admin']))->toBeTrue();
        expect($schema->validate(['role' => 'user']))->toBeFalse();
    });

    it('rejects empty const()', function () {
        $schema = new Schema(['role' => 'const()']);

        expect($schema->validate(['role' => 'anything']))->toBeFalse();
    });


    //
    // 6. enum()
    //

    it('validates enum rule', function () {
        $schema = new Schema(['status' => 'enum(active|inactive)']);

        expect($schema->validate(['status' => 'active']))->toBeTrue();
        expect($schema->validate(['status' => 'inactive']))->toBeTrue();
        expect($schema->validate(['status' => 'invalid']))->toBeFalse();
    });

    it('validates enum(,) rule', function () {
        $schema = new Schema(['status' => 'enum(active,inactive)']);

        expect($schema->validate(['status' => 'active']))->toBeTrue();
        expect($schema->validate(['status' => 'inactive']))->toBeTrue();
        expect($schema->validate(['status' => 'invalid']))->toBeFalse();
    });

    it('rejects empty enum()', function () {
        $schema = new Schema(['status' => 'enum()']);

        expect($schema->validate(['status' => 'anything']))->toBeFalse();
    });

    it('rejects boolean in enum', function () {
        $schema = new Schema(['status' => 'enum(1|2|3)']);

        expect($schema->validate(['status' => true]))->toBeFalse();
    });


    //
    // 7. Nested schema
    //

    it('validates nested schema', function () {
        $schema = new Schema([
            'user' => new Schema([
                'id'   => 'int',
                'name' => 'string',
            ]),
        ]);

        expect($schema->validate([
            'user' => ['id' => 1, 'name' => 'Sidney']
        ]))->toBeTrue();
    });

    it('rejects invalid nested schema', function () {
        $schema = new Schema([
            'user' => new Schema([
                'id'   => 'int',
                'name' => 'string',
            ]),
        ]);

        expect($schema->validate([
            'user' => ['id' => 'not-int', 'name' => 'Sidney']
        ]))->toBeFalse();
    });


    //
    // 8. Required fields
    //

    it('fails when required field is missing', function () {
        $schema = new Schema(['name' => 'string']);

        $schema->validate([], $error);

        expect($error)->toBe('Missing required: name');
    });


    //
    // 9. Optional fields
    //

    it('accepts missing optional field', function () {
        $schema = new Schema(['nickname' => 'string:o']);

        expect($schema->validate([]))->toBeTrue();
    });

    it('rejects invalid optional field', function () {
        $schema = new Schema(['nickname' => 'string:o']);

        expect($schema->validate(['nickname' => 123]))->toBeFalse();
    });


    //
    // 10. Optional via ->optional()
    //

    it('accepts missing optional nested schema', function () {
        $schema = new Schema([
            'profile' => (new Schema(['bio' => 'string']))->optional(),
        ]);

        expect($schema->validate([]))->toBeTrue();
    });

    it('rejects invalid optional nested schema', function () {
        $schema = new Schema([
            'profile' => (new Schema(['bio' => 'string']))->optional(),
        ]);

        expect($schema->validate(['profile' => ['bio' => 123]]))->toBeFalse();
    });


    //
    // 11. Unexpected fields
    //

    it('rejects unexpected fields', function () {
        $schema = new Schema(['name' => 'string']);

        $schema->validate(['name' => 'Sidney', 'extra' => 123], $error);

        expect($error)->toBe('Unexpected fields: [extra]');
    });


    //
    // 12. Invalid schema definition
    //

    it('rejects invalid schema types', function () {
        $schema = new Schema(['x' => 123]); // invalid type

        $schema->validate(['x' => 'anything'], $error);

        expect($error)->toBe('Invalid type [x]: expected [string|Schema], got [int]');
    });


    //
    // 13. Error messages (getType)
    //

    it('produces correct error message for wrong type', function () {
        $schema = new Schema(['age' => 'int']);

        $schema->validate(['age' => 'not-int'], $error);

        expect($error)->toBe("Invalid required [age]: expected [int], got ['not-int' (string)]");
    });


    //
    // 14. Invalid nullable syntax
    //

    it('rejects nullable syntax with no type', function () {
        $schema = new Schema(['x' => '?']);

        $schema->validate(['x' => null], $error);

        expect($error)->toBe("Unknown validation type: ''");
    });

    it('rejects double nullable prefix', function () {
        $schema = new Schema(['x' => '??string']);

        $schema->validate(['x' => 'abc'], $error);

        expect($error)->toBe("Unknown validation type: '?string'");
    });


    //
    // 15. Invalid optional suffix
    //

    it('rejects optional suffix in wrong position', function () {
        $schema = new Schema(['x' => 'string:o:o']);

        $schema->validate(['x' => 'abc'], $error);

        expect($error)->toBe("Unknown validation type: 'string:o'");
    });

    it('rejects unknown suffix :a', function () {
        $schema = new Schema(['x' => 'string:a']);

        $schema->validate(['x' => 'abc'], $error);

        expect($error)->toBe("Unknown validation type: 'string:a'");
    });

    it('rejects unknown suffix !', function () {
        $schema = new Schema(['x' => 'string!']);

        $schema->validate(['x' => 'abc'], $error);

        expect($error)->toBe("Unknown validation type: 'string!'");
    });


    //
    // 16. Invalid const() syntax
    //

    it('rejects const with missing parentheses', function () {
        $schema = new Schema(['x' => 'const']);

        $schema->validate(['x' => 'abc'], $error);

        expect($error)->toBe("Unknown validation type: 'const'");
    });

    it('rejects const with no closing parenthesis', function () {
        $schema = new Schema(['x' => 'const(abc']);

        $schema->validate(['x' => 'abc'], $error);

        expect($error)->toBe("Unknown validation type: 'const(abc'");
    });

    it('rejects const with no opening parenthesis', function () {
        $schema = new Schema(['x' => 'constabc)']);

        $schema->validate(['x' => 'abc'], $error);

        expect($error)->toBe("Unknown validation type: 'constabc)'");
    });


    //
    // 17. Invalid enum() syntax
    //

    it('rejects enum with missing parentheses', function () {
        $schema = new Schema(['x' => 'enum']);

        $schema->validate(['x' => 'abc'], $error);

        expect($error)->toBe("Unknown validation type: 'enum'");
    });

    it('rejects enum with no closing parenthesis', function () {
        $schema = new Schema(['x' => 'enum(a|b']);

        $schema->validate(['x' => 'a'], $error);

        expect($error)->toBe("Unknown validation type: 'enum(a|b'");
    });

    it('rejects enum with no opening parenthesis', function () {
        $schema = new Schema(['x' => 'enuma|b)']);

        $schema->validate(['x' => 'a'], $error);

        expect($error)->toBe("Unknown validation type: 'enuma|b)'");
    });

    it('rejects enum with empty value inside (1/3)', function () {
        $schema = new Schema(['x' => 'enum(a|)']);

        $schema->validate(['x' => 'a'], $error);

        expect($error)->toBe("Invalid enum definition: 'enum(a|)' contains empty values");
    });

    it('rejects enum with empty value inside (2/3)', function () {
        $schema = new Schema(['x' => 'enum(a||b)']);

        $schema->validate(['x' => 'a'], $error);

        expect($error)->toBe("Invalid enum definition: 'enum(a||b)' contains empty values");
    });

    it('rejects enum with empty value inside (3/3)', function () {
        $schema = new Schema(['x' => 'enum(|a)']);

        $schema->validate(['x' => 'a'], $error);

        expect($error)->toBe("Invalid enum definition: 'enum(|a)' contains empty values");
    });


    //
    // 18. Completely unknown types
    //

    it('rejects unknown primitive type', function () {
        $schema = new Schema(['x' => 'unknown']);

        $schema->validate(['x' => 'abc'], $error);

        expect($error)->toBe("Unknown validation type: 'unknown'");
    });

    it('rejects garbage type', function () {
        $schema = new Schema(['x' => '@#$%']);

        $schema->validate(['x' => 'abc'], $error);

        expect($error)->toBe("Unknown validation type: '@#$%'");
    });


    //
    // 19. Invalid schema definition (non-string, non-Schema)
    //

    it('rejects invalid schema definition types', function () {
        $schema = new Schema(['x' => 123]); // invalid type

        $schema->validate(['x' => 'anything'], $error);

        expect($error)->toBe('Invalid type [x]: expected [string|Schema], got [int]');
    });


    //
    // 20. boolean (strict)
    //

    it('validates boolean type (strict)', function () {
        $schema = new Schema(['active' => 'boolean']);

        expect($schema->validate(['active' => true]))->toBeTrue();
        expect($schema->validate(['active' => false]))->toBeTrue();
        expect($schema->validate(['active' => 1]))->toBeFalse();
        expect($schema->validate(['active' => 0]))->toBeFalse();
        expect($schema->validate(['active' => 'true']))->toBeFalse();
        expect($schema->validate(['active' => 'false']))->toBeFalse();
        expect($schema->validate(['active' => '1']))->toBeFalse();
    });


    //
    // 21. number
    //

    it('validates number type', function () {
        $schema = new Schema(['value' => 'number']);

        expect($schema->validate(['value' => 10]))->toBeTrue();
        expect($schema->validate(['value' => 3.14]))->toBeTrue();
        expect($schema->validate(['value' => 0]))->toBeTrue();
        expect($schema->validate(['value' => -5]))->toBeTrue();
        expect($schema->validate(['value' => '10']))->toBeFalse();
        expect($schema->validate(['value' => '3.14']))->toBeFalse();
        expect($schema->validate(['value' => true]))->toBeFalse();
    });


    //
    // 22. numeric
    //

    it('validates numeric type', function () {
        $schema = new Schema(['value' => 'numeric']);

        expect($schema->validate(['value' => 10]))->toBeTrue();
        expect($schema->validate(['value' => 3.14]))->toBeTrue();
        expect($schema->validate(['value' => 0]))->toBeTrue();
        expect($schema->validate(['value' => '10']))->toBeTrue();
        expect($schema->validate(['value' => '3.14']))->toBeTrue();
        expect($schema->validate(['value' => '1e2']))->toBeTrue();
        expect($schema->validate(['value' => '-5.5']))->toBeTrue();
        expect($schema->validate(['value' => 'abc']))->toBeFalse();
        expect($schema->validate(['value' => true]))->toBeFalse();
        expect($schema->validate(['value' => []]))->toBeFalse();
    });


    //
    // 23. Nullable const
    //

    it('validates nullable const', function () {
        $schema = new Schema(['role' => '?const(admin)']);

        expect($schema->validate(['role' => null]))->toBeTrue();
        expect($schema->validate(['role' => 'admin']))->toBeTrue();
        expect($schema->validate(['role' => 'user']))->toBeFalse();
    });


    //
    // 24. Nullable enum
    //

    it('validates nullable enum', function () {
        $schema = new Schema(['status' => '?enum(active|inactive)']);

        expect($schema->validate(['status' => null]))->toBeTrue();
        expect($schema->validate(['status' => 'active']))->toBeTrue();
        expect($schema->validate(['status' => 'inactive']))->toBeTrue();
        expect($schema->validate(['status' => 'invalid']))->toBeFalse();
    });


    //
    // 25. Multiple unexpected fields
    //

    it('rejects multiple unexpected fields', function () {
        $schema = new Schema(['name' => 'string']);

        $schema->validate(['name' => 'Sid', 'extra1' => 1, 'extra2' => 2], $error);

        expect($error)->toBe('Unexpected fields: [extra1, extra2]');
    });


    //
    // 26. Deep nested error path
    //

    it('produces deep nested error path', function () {
        $schema = new Schema([
            'a' => new Schema([
                'b' => new Schema([
                    'c' => 'int',
                ]),
            ]),
        ]);

        $schema->validate(['a' => ['b' => ['c' => 'not-int']]], $error);

        expect($error)->toBe("Invalid required [a.b.c]: expected [int], got ['not-int' (string)]");
    });


//
// 27. Non-array sub-schema
//

it('rejects non-array for required nested schema', function () {
    $schema = new Schema([
        'user' => new Schema(['id' => 'int']),
    ]);

    $valid = $schema->validate(['user' => 10], $error);

    expect($valid)->toBeFalse();
    expect($error)->toBe('Invalid required [user]: expected [array], got [10 (int)]');
});

it('rejects non-array for optional nested schema', function () {
    $schema = new Schema([
        'snippet' => (new Schema(['log' => 'int:o']))->optional(),
    ]);

    $valid = $schema->validate(['snippet' => 10], $error);

    expect($valid)->toBeFalse();
    expect($error)->toBe('Invalid optional [snippet]: expected [array], got [10 (int)]');
});

it('reports deep path when non-array appears mid-nesting', function () {
    $schema = new Schema([
        'company' => new Schema([
            'address' => new Schema(['zip' => 'string']),
        ]),
    ]);

    $valid = $schema->validate(['company' => ['address' => 'nowhere']], $error);

    expect($valid)->toBeFalse();
    expect($error)->toBe("Invalid required [company.address]: expected [array], got ['nowhere' (string)]");
});


//
// 28. List of nested schemas
//

it('validates a list of nested schemas', function () {
    $schema = new Schema([
        'users' => (new Schema([
            'id'   => 'int',
            'name' => 'string',
        ]))->many(),
    ]);

    expect($schema->validate([
        'users' => [
            ['id' => 1, 'name' => 'Ana'],
            ['id' => 2, 'name' => 'Bia'],
        ],
    ]))->toBeTrue();
});

it('validates an empty list of nested schemas', function () {
    $schema = new Schema([
        'users' => (new Schema(['id' => 'int']))->many(),
    ]);

    expect($schema->validate(['users' => []]))->toBeTrue();
});

it('rejects non-list for a many schema', function () {
    $schema = new Schema([
        'users' => (new Schema(['id' => 'int']))->many(),
    ]);

    $valid = $schema->validate(['users' => ['id' => 1]], $error);

    expect($valid)->toBeFalse();
    expect($error)->toBe('Invalid required [users]: expected [list], got [Array]');
});

it('reports item error with indexed path', function () {
    $schema = new Schema([
        'users' => (new Schema(['id' => 'int']))->many(),
    ]);

    $valid = $schema->validate([
        'users' => [
            ['id' => 1],
            ['id' => 'x'],
        ],
    ], $error);

    expect($valid)->toBeFalse();
    expect($error)->toBe("Invalid required [users.1.id]: expected [int], got ['x' (string)]");
});

it('rejects non-array item in a list', function () {
    $schema = new Schema([
        'users' => (new Schema(['id' => 'int']))->many(),
    ]);

    $valid = $schema->validate(['users' => [10]], $error);

    expect($valid)->toBeFalse();
    expect($error)->toBe('Invalid required [users.0]: expected [array], got [10 (int)]');
});

it('validates optional list of nested schemas', function () {
    $schema = new Schema([
        'users' => (new Schema(['id' => 'int']))->many()->optional(),
    ]);

    expect($schema->validate([]))->toBeTrue();
    expect($schema->validate(['users' => [['id' => 1]]]))->toBeTrue();
    expect($schema->validate(['users' => [['id' => 'x']]]))->toBeFalse();
});

it('validates nested lists of nested schemas', function () {
    $schema = new Schema([
        'users' => (new Schema([
            'id'    => 'int',
            'roles' => (new Schema(['name' => 'string']))->many(),
        ]))->many(),
    ]);

    expect($schema->validate([
        'users' => [
            ['id' => 1, 'roles' => [['name' => 'admin']]],
            ['id' => 2, 'roles' => []],
        ],
    ]))->toBeTrue();

    $valid = $schema->validate([
        'users' => [
            ['id' => 1, 'roles' => [['name' => 'admin'], ['name' => 5]]],
        ],
    ], $error);

    expect($valid)->toBeFalse();
    expect($error)->toBe('Invalid required [users.0.roles.1.name]: expected [string], got [5 (int)]');
});


//
// 29. Optional nested schema: absent, null and empty array
//

it('treats absent, null and empty array as empty for optional nested schema', function () {
    $schema = new Schema([
        'profile' => (new Schema(['bio' => 'string']))->optional(),
    ]);

    expect($schema->validate([]))->toBeTrue();
    expect($schema->validate(['profile' => null]))->toBeTrue();
    expect($schema->validate(['profile' => []]))->toBeTrue();
    expect($schema->validate(['profile' => ['bio' => 'Sidney']]))->toBeTrue();
    expect($schema->validate(['profile' => ['bio' => 123]]))->toBeFalse();
});

it('does not treat empty array as absent for required nested schema', function () {
    $schema = new Schema([
        'profile' => new Schema(['bio' => 'string']),
    ]);

    expect($schema->validate(['profile' => null]))->toBeFalse();
    expect($schema->validate(['profile' => []]))->toBeFalse();
});

it('does not treat null as absent for optional scalar', function () {
    $schema = new Schema(['nickname' => 'string:o']);

    expect($schema->validate([]))->toBeTrue();
    expect($schema->validate(['nickname' => null]))->toBeFalse();
    expect($schema->validate(['nickname' => []]))->toBeFalse();
});

it('allows null for nullable optional scalar', function () {
    $schema = new Schema(['nickname' => '?string:o']);

    expect($schema->validate([]))->toBeTrue();
    expect($schema->validate(['nickname' => null]))->toBeTrue();
    expect($schema->validate(['nickname' => 'Sidney']))->toBeTrue();
});

it('treats absent, null and empty array as empty for optional many schema', function () {
    $schema = new Schema([
        'users' => (new Schema(['id' => 'int']))->many()->optional(),
    ]);

    expect($schema->validate([]))->toBeTrue();
    expect($schema->validate(['users' => null]))->toBeTrue();
    expect($schema->validate(['users' => []]))->toBeTrue();
    expect($schema->validate(['users' => [['id' => 1]]]))->toBeTrue();
});

it('does not treat null or empty array items as absent inside a list', function () {
    $schema = new Schema([
        'users' => (new Schema(['id' => 'int']))->many()->optional(),
    ]);

    expect($schema->validate(['users' => [null]]))->toBeFalse();
    expect($schema->validate(['users' => [[]]]))->toBeFalse();
});


//
// 30. Runtime optionality
//

it('reflects optional() called after a previous validation', function () {
    $profile = new Schema(['bio' => 'string']);
    $schema = new Schema(['profile' => $profile]);

    expect($schema->validate(['profile' => ['bio' => 'Sidney']]))->toBeTrue();
    expect($schema->validate([]))->toBeFalse();

    $profile->optional();

    expect($schema->validate([]))->toBeTrue();
});

it('reflects many() called after a previous validation', function () {
    $user = new Schema(['id' => 'int']);
    $schema = new Schema(['users' => $user]);

    expect($schema->validate(['users' => ['id' => 1]]))->toBeTrue();
    expect($schema->validate(['users' => [['id' => 1]]]))->toBeFalse();

    $user->many();

    expect($schema->validate(['users' => [['id' => 1]]]))->toBeTrue();
});

it('keeps required fields ahead of optional fields after compilation', function () {
    $schema = new Schema([
        'nickname' => 'int:o',
        'name' => 'string',
    ]);

    expect($schema->validate(['nickname' => 'invalid']))->toBeFalse();

    $schema->validate(['nickname' => 'invalid'], $error);

    expect($error)->toBe('Missing required: name');
});

it('reuses the same schema across repeated validations', function () {
    $schema = new Schema([
        'id' => 'int',
        'items' => (new Schema(['name' => 'string']))->many()->optional(),
    ]);

    for ($i = 0; $i < 25; $i++) {
        expect($schema->validate(['id' => $i, 'items' => [['name' => 'a']]]))->toBeTrue();
        expect($schema->validate(['id' => 'invalid']))->toBeFalse();
    }
});


//
// 31. Production error redaction
//

it('defaults to production error mode', function () {
    $property = (new ReflectionClass(Schema::class))->getProperty('production');

    expect($property->getDefaultValue())->toBeTrue();
});

it('redacts payload values from scalar errors in production', function () {
    Schema::production();

    $schema = new Schema([
        'name' => 'string',
        'age' => 'int',
        'amount' => 'float',
        'active' => 'bool',
    ]);

    $schema->validate([
        'name' => 'Sidney',
        'age' => 'SECRET_AGE',
        'amount' => 1.5,
        'active' => true,
    ], $error);

    expect($error)->toBe('Invalid required [age]: expected [int], got [string]');
    expect($error)->not->toContain('SECRET_AGE');

    $schema->validate([
        'name' => 'Sidney',
        'age' => 42,
        'amount' => 'SECRET_AMOUNT',
        'active' => true,
    ], $error);

    expect($error)->toBe('Invalid required [amount]: expected [float], got [string]');
    expect($error)->not->toContain('SECRET_AMOUNT');
});

it('redacts payload values from nested and indexed paths in production', function () {
    Schema::production();

    $schema = new Schema([
        'user' => new Schema([
            'document' => 'int',
        ]),
        'users' => (new Schema([
            'id' => 'int',
        ]))->many(),
    ]);

    $schema->validate([
        'user' => ['document' => 'SECRET_DOCUMENT'],
        'users' => [['id' => 1]],
    ], $error);

    expect($error)->toBe('Invalid required [user.document]: expected [int], got [string]');
    expect($error)->not->toContain('SECRET_DOCUMENT');

    $schema->validate([
        'user' => ['document' => 123],
        'users' => [['id' => 1], ['id' => 'SECRET_ID']],
    ], $error);

    expect($error)->toBe('Invalid required [users.1.id]: expected [int], got [string]');
    expect($error)->not->toContain('SECRET_ID');
});

it('hides unexpected field names in production', function () {
    Schema::production();

    $schema = new Schema(['name' => 'string']);

    $schema->validate([
        'name' => 'Sidney',
        'pan' => '4111111111111111',
        'cvv' => '123',
    ], $error);

    expect($error)->toBe('Unexpected fields present: 2');
    expect($error)->not->toContain('pan');
    expect($error)->not->toContain('cvv');
    expect($error)->not->toContain('4111111111111111');
});

it('hides schema definition details in production', function () {
    Schema::production();

    $schema = new Schema(['field' => 'unknown']);

    expect($schema->validate(['field' => 'value'], $error))->toBeFalse();
    expect($error)->toBe('Invalid schema definition');

    $enum = new Schema(['field' => 'enum(a|)']);

    expect($enum->validate(['field' => 'a'], $error))->toBeFalse();
    expect($error)->toBe('Invalid schema definition');

    $definition = new Schema(['field' => 123]);

    expect($definition->validate(['field' => 'value'], $error))->toBeFalse();
    expect($error)->toBe('Invalid schema definition');
});

it('does not expose object class names in production', function () {
    Schema::production();

    $schema = new Schema(['value' => 'string']);

    $schema->validate(['value' => new stdClass()], $error);

    expect($error)->toBe('Invalid required [value]: expected [string], got [object]');
    expect($error)->not->toContain(stdClass::class);
});

it('changes only error detail between production and development', function () {
    $schema = new Schema(['age' => 'int']);
    $payload = ['age' => 'SECRET_AGE'];

    Schema::production();
    expect($schema->validate($payload, $error))->toBeFalse();
    expect($error)->toBe('Invalid required [age]: expected [int], got [string]');

    Schema::development();
    expect($schema->validate($payload, $error))->toBeFalse();
    expect($error)->toBe("Invalid required [age]: expected [int], got ['SECRET_AGE' (string)]");

    Schema::production();
    expect($schema->validate(['age' => 42]))->toBeTrue();
});
