<?php declare(strict_types=1);

    namespace STDW\Schema;

    use LogicException;


    final class Schema
    {
        /** @var array<int, mixed>
         */
        private const BOOL_COERCION = [0, 1, '0', '1', 'true', 'false'];


        /** @var bool
         */
        private bool $optional = false;

        /** @var list<string>
         */
        private static array $context = [];

        /** @var array<string, mixed>
         */
        private static array $cache = [];


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
         * @param string|null &$error
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

                    $collection[$key] = true;

                    continue;
                }

                if (is_string($type)) {
                    (str_ends_with($type, ':o'))
                        ? $optional[$key] = substr($type, 0, -2)
                        : $required[$key] = $type;

                    $collection[$key] = true;

                    continue;
                }

                $error = "Invalid type [{$key}]: expected [string|Schema], got [" . get_debug_type($type) . "]";

                return false;
            }

            if ($diff = array_diff_key($data, $collection)) {
                $error = "Unexpected fields: [" . implode(', ', array_keys($diff)) . "]";

                return false;
            }

            try {
                foreach ($required as $key => $type) {
                    if ( ! array_key_exists($key, $data)) {
                        $error = "Missing required: {$key}";

                        return false;
                    }

                    $isSchema = is_object($type);

                    if ($isSchema) {
                        self::$context[] = $key;
                    }

                    $valid = $this->match($type, $data[$key], $required_error);

                    if ( ! $valid) {
                        $context = implode('.', self::$context);
                        $fullKey = empty($context) ? $key : "{$context}.{$key}";

                        $expected = $isSchema ? 'Schema' : $type;
                        $received = $this->getType($data[$key]);
                        $error = $required_error ?? "Invalid required [{$fullKey}]: expected [{$expected}], got [{$received}]";

                        return false;
                    }

                    if ($isSchema) {
                        array_pop(self::$context);
                    }
                }

                foreach ($optional as $key => $type) {
                    if ( ! array_key_exists($key, $data)) {
                        continue;
                    }

                    $isSchema = is_object($type);

                    if ($isSchema) {
                        self::$context[] = $key;
                    }

                    $valid = $this->match($type, $data[$key], $optional_error);

                    if ( ! $valid) {
                        $context = implode('.', self::$context);
                        $fullKey = empty($context) ? $key : "{$context}.{$key}";

                        $expected = $isSchema ? 'Schema' : $type;
                        $received = $this->getType($data[$key]);
                        $error = $optional_error ?? "Invalid optional [{$fullKey}]: expected [{$expected}], got [{$received}]";

                        return false;
                    }

                    if ($isSchema) {
                        array_pop(self::$context);
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
         * @param string|null &$error
         * @return bool
         * @throws LogicException
         */
        private function match(Schema|string $type, mixed $value, ?string &$error = null): bool
        {
            /** @var Schema|string $type
             */

            if ($type instanceof Schema) {
                if ( ! is_array($value)) {
                    return false;
                }

                /** @var array<int|string, mixed> $value
                 */
                return $type->validate($value, $error);
            }

            /** @var string $type
             */

            $isNullable = str_starts_with($type, '?');

            if ($isNullable) {
                $type = substr($type, 1);
            }


            if (str_starts_with($type, 'const(') && str_ends_with($type, ')')) {
                if ( ! isset(self::$cache[$type])) {
                    self::$cache[$type] = trim( substr($type, 6, -1));
                }

                $content = self::$cache[$type];
                $result = ($content !== '' && $value === $content);

                return $isNullable ? (is_null($value) || $result) : $result;
            }

            if (str_starts_with($type, 'enum(') && str_ends_with($type, ')')) {
                if ( ! isset(self::$cache[$type])) {
                    $content = trim( substr($type, 6, -1));

                    if ($content === '') {
                        self::$cache[$type] = [];
                    } else {
                        $separator = str_contains($content, '|') ? '|' : ',';
                        $options = array_map('trim', explode($separator, $content));

                        if (in_array('', $options, true)) {
                            throw new LogicException("Invalid enum definition: '{$type}' contains empty values");
                        }

                        self::$cache[$type] = array_map(
                            fn($o) => is_numeric($o) ? (string) $o : $o,
                            $options
                        );
                    }
                }

                /** @var array<string> $options
                 */
                $options = self::$cache[$type];

                if ($options === [] || is_bool($value)) {
                    return false;
                }

                $normalizedValue = is_numeric($value) ? (string) $value : $value;
                $result = in_array($normalizedValue, $options);

                return $isNullable ? (is_null($value) || $result) : $result;
            }

            $result = match($type) {
                'null'     => is_null($value),
                'bool'     => is_bool($value) || in_array($value, self::BOOL_COERCION, true),
                'boolean'  => is_bool($value),
                'string'   => is_string($value),
                'int'      => is_int($value),
                'float'    => is_float($value),
                'number'   => is_int($value) || is_float($value),
                'numeric'  => is_numeric($value),
                'array'    => is_array($value),
                'object'   => is_object($value),
                'resource' => is_resource($value),
                'callable' => is_callable($value),
                'list'     => is_array($value) && array_is_list($value),

                default => throw new LogicException("Unknown validation type: '{$type}'")
            };

            return $isNullable ? (is_null($value) || $result) : $result;
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
            if (is_int($value)) return "{$value} (int)";
            if (is_float($value)) return "{$value} (float)";
            if (is_array($value)) return 'Array';
            if (is_object($value)) return 'Object('. get_class($value) .')';

            return gettype($value);
        }
    }
