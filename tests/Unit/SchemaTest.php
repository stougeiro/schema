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
