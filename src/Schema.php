<?php declare(strict_types=1);

    namespace STDW\Schema;

    use LogicException;


    class Schema
    {
        /** @var array<int, mixed>
         */
        protected const BOOL_COERCION = [0, 1, '0', '1', 'true', 'false'];


        /** @var bool
         */
        protected static bool $production = true;

        /** @var bool
         */
        protected bool $optional = false;

        /** @var bool
         */
        protected bool $many = false;

        /**
         * @var null|array{
         *   required: array<string, string|Schema>,
         *   optional: array<string, string|Schema>,
         *   collection: array<string, true>
         * }
         */
        protected ?array $compiled = null;

        /** @var array<string, mixed>
         */
        protected static array $cache = [];


        /**
         * Create a new schema instance.
         * 
         * @param array<string, mixed> $schema
         */
        public function __construct(
            private array $schema )
        { }


        /**
         * Redact values from validation errors.
         *
         * @return void
         */
        public static function production(): void
        {
            self::$production = true;
        }

        /**
         * Include values in validation errors for development and testing.
         *
         * @return void
         */
        public static function development(): void
        {
            self::$production = false;
        }


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
         * Mark this schema as a list of schemas.
         *
         * @return Schema
         */
        public function many(): Schema
        {
            $this->many = true;

            return $this;
        }


        /**
         * @param mixed $data
         * @param null|string &$error 
         * @param string $context 
         * @return bool 
         */
        protected function doValidate(mixed $data, ?string &$error, string $context): bool
        {
            if ( ! is_array($data)) {
                $label = $this->optional ? 'optional' : 'required';

                $error = ($context === '')
                    ? "Invalid payload: expected [array], got [{$this->getType($data)}]"
                    : "Invalid {$label} [{$context}]: expected [array], got [{$this->getType($data)}]";

                return false;
            }

            if ($this->compiled === null) {
                if (($this->compiled = $this->compile($error)) === null) {
                    return false;
                }
            }

            $required = $this->compiled['required'];
            $optional = $this->compiled['optional'];

            /**
             * Validation: Unexpected fields
             */
            if ($diff = array_diff_key($data, $this->compiled['collection'])) {
                $error = self::$production
                    ? 'Unexpected fields present: ' . count($diff)
                    : "Unexpected fields: [" . implode(', ', array_keys($diff)) . "]";

                return false;
            }

            /**
             * Validation
             */
            try {
                /** Required fields
                 */
                foreach ($required as $key => $type) {
                    if ( ! is_string($type) && $type->optional) {
                        continue;
                    }

                    if ( ! array_key_exists($key, $data)) {
                        $error = "Missing required: {$key}";

                        return false;
                    }

                    $value = $data[$key];

                    if ( ! $this->validateField($key, $type, $value, $error, $context, false)) {
                        return false;
                    }
                }

                /** Optional fields
                 */
                foreach ($optional as $key => $type) {
                    if ( ! is_string($type) && ! $type->optional) {
                        continue;
                    }

                    if ( ! array_key_exists($key, $data)) {
                        continue;
                    }

                    $value = $data[$key];

                    if ( ! $this->validateField($key, $type, $value, $error, $context, true)) {
                        return false;
                    }
                }
            } catch (LogicException $e) {
                $error = self::$production
                    ? 'Invalid schema definition'
                    : $e->getMessage();

                return false;
            }

            return true;
        }

        /**
         * @param null|string &$error
         * @return null|array{required: array<string, string|Schema>, optional: array<string, string|Schema>, collection: array<string, true>}
         */
        protected function compile(?string &$error): ?array
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
                        $error = self::$production
                            ? 'Invalid schema definition'
                            : "Invalid object [{$key}]: expected [Schema], got [" . get_class($type) . "]";

                        return null;
                    }

                    $required[$key] = $type;
                    $optional[$key] = $type;
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

                $error = self::$production
                    ? 'Invalid schema definition'
                    : "Invalid type [{$key}]: expected [string|Schema], got [" . get_debug_type($type) . "]";

                return null;
            }

            return [
                'required' => $required,
                'optional' => $optional,
                'collection' => $collection,
            ];
        }

        /**
         * @param string $key
         * @param string|Schema $type
         * @param mixed $value
         * @param null|string &$error
         * @param string $context
         * @param bool $isOptional
         * @return bool
         */
        protected function validateField(
            string $key,
            string|Schema $type,
            mixed $value,
            ?string &$error,
            string $context,
            bool $isOptional ): bool
        {
            if ($isOptional && ! is_string($type) && ($value === null || $value === [])) {
                return true;
            }

            if (is_string($type)) {
                if ($this->match($type, $value)) {
                    return true;
                }

                $path = ($context === '') ? $key : "{$context}.{$key}";
                $label = $isOptional ? 'optional' : 'required';
                $error = "Invalid {$label} [{$path}]: expected [{$type}], got [{$this->getType($value)}]";

                return false;
            }

            $path = ($context === '') ? $key : "{$context}.{$key}";

            if ($type->many) {
                if ( ! is_array($value) || ! array_is_list($value)) {
                    $label = $isOptional ? 'optional' : 'required';
                    $error = "Invalid {$label} [{$path}]: expected [list], got [{$this->getType($value)}]";

                    return false;
                }

                foreach ($value as $index => $item) {
                    if ( ! $type->doValidate($item, $error, "{$path}.{$index}")) {
                        return false;
                    }
                }

                return true;
            }

            return $type->doValidate($value, $error, $path);
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
                'list'     => is_array($value) && array_is_list($value),
                'object'   => is_object($value),
                'resource' => is_resource($value),
                'callable' => is_callable($value),

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
            if (self::$production) {
                if (is_null($value)) return 'null';
                if (is_bool($value)) return 'bool';
                if (is_string($value)) return 'string';
                if (is_int($value)) return 'int';
                if (is_float($value)) return 'float';
                if (is_array($value)) return 'array';
                if (is_object($value)) return 'object';
                if (is_resource($value)) return 'resource';

                return gettype($value);
            }

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
