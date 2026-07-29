<?php declare(strict_types=1);

    namespace STDW\Schema;

    use LogicException;


    final class Schema
    {
        private bool $optional = false;


        /**
         * Create a new schema instance.
         * 
         * @param array<string, mixed> $schema
         */
        public function __construct(
            private array $schema )
        { }


        /**
         * Validate the given data against the schema.
         * 
         * @param array<int|string, mixed> $data
         * @param string|null $error
         * @return bool
         */
        public function validate(array $data, ?string &$error = null): bool
        {
            $optional = [];
            $required = [];
            $collection = [];

            /**
             * @var string $key
             * @var mixed $type
             */
            foreach ($this->schema as $key => $type) {
                if (is_object($type)) {
                    if ( ! $type instanceof Schema) {
                        $error = "Invalid object [{$key}]: expected [Schema], got [" . get_class($type) . "]";

                        return false;
                    }

                    ($type->optional === true)
                        ? $optional[$key] = $type
                        : $required[$key] = $type;

                    $collection[] = $key;

                    continue;
                }

                if (is_string($type)) {
                    (str_ends_with($type, ':o'))
                        ? $optional[$key] = substr($type, 0, -2)
                        : $required[$key] = $type;

                    $collection[] = $key;

                    continue;
                }


                $error = "Invalid type [{$key}]: expected [string|Schema], got [" . get_debug_type($type) . "]";

                return false;
            }

            if ($diff = array_diff( array_keys($data), $collection)) {
                $list = implode(', ', $diff);
                $error = "Unexpected fields: [{$list}]";

                return false;
            }

            try {
                foreach ($required as $key => $type) {
                    if ( ! array_key_exists($key, $data)) {
                        $error = "Missing required: {$key}";

                        return false;
                    }

                    if ( ! $this->match($type, $data[$key])) {
                        $expected = is_object($type) ? 'Schema' : $type;
                        $received = $this->getType($data[$key]);
                        $error = "Invalid required [{$key}]: expected [{$expected}], got [{$received}]";

                        return false;
                    }
                }

                foreach ($optional as $key => $type) {
                    if (array_key_exists($key, $data) && ! $this->match($type, $data[$key])) {
                        $expected = is_object($type) ? 'Schema' : $type;
                        $received = $this->getType($data[$key]);
                        $error = "Invalid optional [{$key}]: expected [{$expected}], got [{$received}]";

                        return false;
                    }
                }
            } catch(LogicException $e) {
                $error = $e->getMessage();

                return false;
            }

            return true;
        }

        /**
         * Mark this schema as optional.
         *
         * @return Schema
         */
        public function optional(): Schema
        {
            $this->optional = true;

            return $this;
        }


        /**
         * Match the given value against the type.
         *
         * @param Schema|string $type
         * @param mixed $value
         * @return bool
         */
        private function match(Schema|string $type, mixed $value): bool
        {
            /** @var Schema|string $type */
            if ($type instanceof Schema) {
                if ( ! is_array($value)) {
                    return false;
                }

                /** @var array<int|string, mixed> $value */
                return $type->validate($value);
            }

            /** @var string $type */
            if (str_starts_with($type, '?')) {
                /** @var mixed $value */
                if (is_null($value)) {
                    return true;
                }

                $type = substr($type, 1);
            }

            if (str_starts_with($type, 'const(') && str_ends_with($type, ')')) {
                $content = substr($type, 6, -1);

                return $content !== '' && $value == $content;
            }

            if (str_starts_with($type, 'enum(') && str_ends_with($type, ')')) {
                $content = substr($type, 5, -1);

                if ($content === '' || is_bool($value)) {
                    return false;
                }

                $separator = str_contains($content, '|') ? '|' : ',';
                $options = array_map('trim', explode($separator, $content));

                $normalizedValue = is_numeric($value) ? (string) $value : $value;
                $normalizedOptions = array_map(
                    fn($option) => is_numeric($option) ? (string) $option : $option,
                    $options
                );

                return in_array($normalizedValue, $normalizedOptions);
            }

            return match($type) {
                'null'     => is_null($value),
                'bool'     => is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true),
                'string'   => is_string($value),
                'int'      => is_int($value),
                'float'    => is_float($value),
                'array'    => is_array($value),
                'object'   => is_object($value),
                'resource' => is_resource($value),
                'callable' => is_callable($value),
                'list'     => is_array($value) && array_is_list($value),

                default => throw new LogicException("Unknown validation type: '{$type}'")
            };
        }

        /**
         * Get the type of the given value.
         *
         * @param mixed $value
         * @return string
         */
        private function getType(mixed $value): string
        {
            if (is_null($value)) return 'null';
            if (is_bool($value)) return $value ? 'true (bool)' : 'false (bool)';
            if (is_string($value)) return "'{$value}' (string)";
            if (is_numeric($value)) return "{$value} (number)";
            if (is_array($value)) return 'Array';
            if (is_object($value)) return 'Object('. get_class($value) .')';

            return gettype($value);
        }
    }
