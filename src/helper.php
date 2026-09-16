<?php declare(strict_types=1);

    use STDW\Schema\Schema;


    /**
     * Create a new schema instance.
     * 
     * @param array<string, mixed> $schema
     * @return Schema
     */
    function schema(array $schema): Schema
    {
        return new Schema($schema);
    }
