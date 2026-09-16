<?php declare(strict_types=1);

    namespace STDW\Schema;

    use LogicException;


    class Schema
    {
        /** @var array<int, mixed>
         */
        protected const BOOL_COERCION = [0, 1, '0', '1', 'true', 'false'];


        /** @var array<string, mixed>
         */
        protected static array $cache = [];

        /** @var bool
         */
        protected bool $optional = false;


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
            return $this->doValidate($data, $error, '');
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
         * @param array<int|string, mixed> $data
         * @param null|string &$error 
         * @param string $context 
         * @return bool 
         */
        protected function doValidate(array $data, ?string &$error, string $context): bool
        {
            $optional = [];
            $required = [];
            $collection = [];

            /**
             * Classification
             * 
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

            /**
             * Validation: Unexpected fields
             */
            if ($diff = array_diff_key($data, $collection)) {
                $error = "Unexpected fields: [" . implode(', ', array_keys($diff)) . "]";

                return false;
            }

            /**
             * Validation
             */
            try {
                /** Required fields
                 */
                foreach ($required as $key => $type) {
                    if ( ! array_key_exists($key, $data)) {
                        $error = "Missing required: {$key}";

                        return false;
                    }

                    $isSchema = is_object($type);

                    if ($isSchema) {
                        /** @var array<int|string, mixed> $childData */
                        $childData = $data[$key];
                        $childContext = ($context === '') ? $key : "{$context}.{$key}";

                        $valid = $type->doValidate($childData, $error, $childContext);
                    } else {
                        $valid = $this->match($type, $data[$key]);
                    }

                    if ( ! $valid) {
                        if ( ! $isSchema) {
                            $fullKey = ($context === '') ? $key : "{$context}.{$key}";
                            $received = $this->getType($data[$key]);
                            $error = "Invalid required [{$fullKey}]: expected [{$type}], got [{$received}]";
                        }

                        return false;
                    }
                }

                /** Optional fields
                 */
                foreach ($optional as $key => $type) {
                    if ( ! array_key_exists($key, $data)) {
                        continue;
                    }

                    $isSchema = is_object($type);

                    if ($isSchema) {
                        /** @var array<int|string, mixed> $childData */
                        $childData = $data[$key];
                        $childContext = ($context === '') ? $key : "{$context}.{$key}";

                        $valid = $type->doValidate($childData, $error, $childContext);
                    } else {
                        $valid = $this->match($type, $data[$key]);
                    }

                    if ( ! $valid) {
                        if ( ! $isSchema) {
                            $fullKey = ($context === '') ? $key : "{$context}.{$key}";
                            $received = $this->getType($data[$key]);
                            $error = "Invalid optional [{$fullKey}]: expected [{$type}], got [{$received}]";
                        }

                        return false;
                    }
                }
            } catch (LogicException $e) {
                $error = $e->getMessage();

                return false;
            }

            return true;
        }

        /**
         * Match the given value against the type.
         *
         * @param string $type
         * @param mixed $value
         * @return bool
         * @throws LogicException
         */
        protected function match(string $type, mixed $value): bool
        {
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
                    $content = trim( substr($type, 5, -1));

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

                /** @var array<string> $options */
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
        protected function getType(mixed $value): string
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
