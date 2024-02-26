<?php declare(strict_types=1);

    namespace STDW\Schema;


    final class Schema
    {
        public function __construct(
            protected array $schema)
        { }


        public function validate(array $data, bool $strict = true): bool
        {
            if ( ! $strict) {
                goto nonstrictvalidate;
            }

            $diff = array_merge(
                array_diff_key($this->schema, $data),
                array_diff_key($data, $this->schema)
            );

            if (count($diff)) {
                return false;
            }


            nonstrictvalidate:

            foreach ($this->schema as $key => $type) {
                if ( ! isset($data[$key]) || ! $this->verify($type, $data[$key], $strict)) {
                    return false;
                }
            }

            return true;
        }


        protected function verify(Schema|string $type, mixed $value, bool $strict): bool
        {
            if (is_object($type) && $type instanceof Schema && is_array($value)) {
                return $type->validate($value, $strict);
            }

            return match($type) {
                'null' => is_null($value),
                'bool' => is_bool($value),
                'string' => is_string($value),
                'int' => is_int($value),
                'float' => is_float($value),
                'array' => is_array($value),
                'object' => is_object($value),
                'resource' => is_resource($value),
                'callable' => is_callable($value),

                default => false
            };
        }
    }