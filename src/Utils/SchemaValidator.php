<?php

namespace ElliottLawson\McpPhpSdk\Utils;

/**
 * Utility class for validating data against JSON schemas.
 */
class SchemaValidator
{
    /**
     * Validate data against a JSON schema.
     *
     * @param array $data The data to validate
     * @param string|array $schema The JSON schema as a string or array
     * @return bool Whether the data is valid
     */
    public static function validate(array $data, string|array $schema): bool
    {
        $errors = self::validateWithErrors($data, $schema);
        return empty($errors);
    }
    
    /**
     * Validate data against a JSON schema and return any errors.
     *
     * @param array $data The data to validate
     * @param string|array $schema The JSON schema as a string or array
     * @return array An array of validation errors
     */
    public static function validateWithErrors(array $data, string|array $schema): array
    {
        // If the schema is a string, decode it
        if (is_string($schema)) {
            $schema = json_decode($schema, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [['message' => 'Invalid schema JSON: ' . json_last_error_msg()]];
            }
        }
        
        // Convert the schema to an object
        $schemaObj = self::arrayToObject($schema);
        $dataObj = self::arrayToObject($data);
        
        // Initialize the validator
        if (!class_exists('\JsonSchema\Validator')) {
            // Simple validation for the basic types
            return self::performBasicValidation($data, $schema);
        }
        
        try {
            // Use JsonSchema\Validator if available
            $validator = new \JsonSchema\Validator();
            $validator->validate($dataObj, $schemaObj);
            
            if ($validator->isValid()) {
                return [];
            }
            
            $errors = [];
            foreach ($validator->getErrors() as $error) {
                $errors[] = [
                    'property' => $error['property'],
                    'message' => $error['message'],
                    'constraint' => $error['constraint'] ?? null
                ];
            }
            
            return $errors;
        } catch (\Throwable $e) {
            return [['message' => 'Validation error: ' . $e->getMessage()]];
        }
    }
    
    /**
     * Convert an array to an object for schema validation.
     *
     * @param array $array The array to convert
     * @return object The converted object
     */
    private static function arrayToObject(array $array): object
    {
        return json_decode(json_encode($array));
    }
    
    /**
     * Perform basic validation for common schema types.
     *
     * @param array $data The data to validate
     * @param array $schema The schema to validate against
     * @return array An array of validation errors
     */
    private static function performBasicValidation(array $data, array $schema): array
    {
        $errors = [];
        
        // Check required properties
        if (isset($schema['required']) && is_array($schema['required'])) {
            foreach ($schema['required'] as $required) {
                if (!isset($data[$required])) {
                    $errors[] = [
                        'property' => $required,
                        'message' => "The property {$required} is required"
                    ];
                }
            }
        }
        
        // Check property types
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $propName => $propSchema) {
                if (!isset($data[$propName])) {
                    continue; // Skip if property doesn't exist (handled by required check)
                }
                
                $value = $data[$propName];
                
                // Check type
                if (isset($propSchema['type'])) {
                    $valid = self::validateType($value, $propSchema['type']);
                    
                    if (!$valid) {
                        $errors[] = [
                            'property' => $propName,
                            'message' => "The property {$propName} must be of type {$propSchema['type']}"
                        ];
                    }
                }
                
                // Check enum
                if (isset($propSchema['enum']) && is_array($propSchema['enum'])) {
                    if (!in_array($value, $propSchema['enum'], true)) {
                        $errors[] = [
                            'property' => $propName,
                            'message' => "The property {$propName} must be one of: " . implode(', ', $propSchema['enum'])
                        ];
                    }
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate a value against a specific type.
     *
     * @param mixed $value The value to check
     * @param string $type The expected type
     * @return bool Whether the value matches the type
     */
    private static function validateType($value, string $type): bool
    {
        switch ($type) {
            case 'string':
                return is_string($value);
            case 'number':
                return is_numeric($value);
            case 'integer':
                return is_int($value) || (is_string($value) && ctype_digit($value));
            case 'boolean':
                return is_bool($value);
            case 'array':
                return is_array($value) && array_is_list($value);
            case 'object':
                return is_array($value) && !array_is_list($value);
            case 'null':
                return $value === null;
            default:
                return false;
        }
    }
}
