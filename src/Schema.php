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
                goto nonstrictschema;
            }


            $diff = array_merge(
                array_diff_key($this->schema, $data),
                array_diff_key($data, $this->schema)
            );

            if (count($diff)) {
                return false;
            }


            nonstrictschema:

            foreach ($this->schema as $key => $type) {
                if ( ! array_key_exists($key, $data) || ! $this->match($type, $data[$key], $strict)) {
                    return false;
                }
            }

            return true;
        }


        private function match(Schema|string $type, mixed $value, bool $strict): bool
        {
            if (is_object($type) && $type instanceof Schema && is_array($value)) {
                return $type->validate($value, $strict);
            }

            return match($type) {
                'null'     => is_null($value),
                'bool'     => is_bool($value),
                'string'   => is_string($value),
                'int'      => is_int($value),
                'float'    => is_float($value),
                'array'    => is_array($value),
                'object'   => is_object($value),
                'resource' => is_resource($value),
                'callable' => is_callable($value),

                'list'     => $this->is_list($value),

                '?string'  => is_null($value) || is_string($value),
                '?int'     => is_null($value) || is_int($value),
                '?float'   => is_null($value) || is_float($value),
                '?array'   => is_null($value) || is_array($value),
                '?object'  => is_null($value) || is_object($value),

                '?list'    => is_null($value) || $this->is_list($value),

                default => false
            };
        }

        private function is_list(array $array): bool
        {
            return is_array($array) && array_is_list($array);
        }
    }