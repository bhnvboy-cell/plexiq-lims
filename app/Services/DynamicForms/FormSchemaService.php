<?php

namespace App\Services\DynamicForms;

use App\Helpers\Database;
use App\Helpers\Audit;

class FormSchemaService
{
    /**
     * Create or update a dynamic form schema
     *
     * @param array<string, mixed> $data
     * @param int|null $userId
     * @return array<string, mixed>
     */
    public function saveSchema(array $data, ?int $userId = null): array
    {
        $db = Database::connect();
        $code = trim($data['schema_code'] ?? '');
        $name = trim($data['name'] ?? '');
        $entityType = trim($data['entity_type'] ?? 'sample');
        $fields = is_array($data['fields_schema'] ?? null) ? $data['fields_schema'] : [];
        $layout = is_array($data['layout_schema'] ?? null) ? $data['layout_schema'] : ['columns' => 2];
        $rules = is_array($data['validation_rules'] ?? null) ? $data['validation_rules'] : [];
        $status = $data['status'] ?? 'draft';

        if (empty($code) || empty($name)) {
            throw new \InvalidArgumentException('Schema code and name are required.');
        }

        // Validate field definitions
        $this->validateFields($fields);

        // Check if exists
        $stmt = $db->prepare("SELECT id, version FROM dynamic_form_schemas WHERE schema_code = ?");
        $stmt->execute([$code]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($existing) {
            $id = (int)$existing['id'];
            $newVersion = ((int)$existing['version']) + 1;

            $update = $db->prepare("
                UPDATE dynamic_form_schemas
                SET name = ?, entity_type = ?, version = ?, status = ?,
                    fields_schema = ?, layout_schema = ?, validation_rules = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
                RETURNING id, version
            ");
            $update->execute([
                $name, $entityType, $newVersion, $status,
                json_encode($fields, JSON_UNESCAPED_SLASHES),
                json_encode($layout, JSON_UNESCAPED_SLASHES),
                json_encode($rules, JSON_UNESCAPED_SLASHES),
                $id
            ]);

            Audit::log('Dynamic Form Schema Updated', 'dynamic_form_schemas', $id, ['version' => $existing['version']], ['version' => $newVersion, 'status' => $status]);
            return ['id' => $id, 'schema_code' => $code, 'version' => $newVersion, 'status' => $status];
        }

        // Insert new schema
        $insert = $db->prepare("
            INSERT INTO dynamic_form_schemas (schema_code, name, entity_type, version, status, fields_schema, layout_schema, validation_rules, created_by)
            VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?)
            RETURNING id, version
        ");
        $insert->execute([
            $code, $name, $entityType, $status,
            json_encode($fields, JSON_UNESCAPED_SLASHES),
            json_encode($layout, JSON_UNESCAPED_SLASHES),
            json_encode($rules, JSON_UNESCAPED_SLASHES),
            $userId
        ]);
        $row = $insert->fetch(\PDO::FETCH_ASSOC);

        Audit::log('Dynamic Form Schema Created', 'dynamic_form_schemas', (int)$row['id'], null, ['schema_code' => $code]);
        return ['id' => (int)$row['id'], 'schema_code' => $code, 'version' => (int)$row['version'], 'status' => $status];
    }

    /**
     * Validate submission payload against schema field definitions and validation rules
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $payload
     * @return array{is_valid: bool, errors: array<string, string>, sanitized_data: array<string, mixed>}
     */
    public function validateSubmission(array $schema, array $payload): array
    {
        $fields = is_string($schema['fields_schema']) ? json_decode($schema['fields_schema'], true) : ($schema['fields_schema'] ?? []);
        $errors = [];
        $sanitized = [];

        foreach ($fields as $field) {
            $name = $field['name'] ?? '';
            $label = $field['label'] ?? $name;
            $type = $field['type'] ?? 'text';
            $required = !empty($field['required']);
            $val = $payload[$name] ?? null;

            // Required check
            if ($required && ($val === null || $val === '')) {
                $errors[$name] = "{$label} is required.";
                continue;
            }

            if ($val !== null && $val !== '') {
                // Type checks
                if ($type === 'number') {
                    if (!is_numeric($val)) {
                        $errors[$name] = "{$label} must be numeric.";
                    } else {
                        $num = (float)$val;
                        if (isset($field['min']) && $num < $field['min']) {
                            $errors[$name] = "{$label} must be at least {$field['min']}.";
                        }
                        if (isset($field['max']) && $num > $field['max']) {
                            $errors[$name] = "{$label} cannot exceed {$field['max']}.";
                        }
                        $sanitized[$name] = $num;
                    }
                } elseif ($type === 'select') {
                    $options = $field['options'] ?? [];
                    if (!empty($options) && !in_array($val, $options)) {
                        $errors[$name] = "{$label} must be one of the permitted options.";
                    } else {
                        $sanitized[$name] = (string)$val;
                    }
                } elseif ($type === 'date') {
                    if (strtotime((string)$val) === false) {
                        $errors[$name] = "{$label} must be a valid date.";
                    } else {
                        $sanitized[$name] = date('Y-m-d', strtotime((string)$val));
                    }
                } else {
                    $sanitized[$name] = is_string($val) ? htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8') : $val;
                }
            } else {
                $sanitized[$name] = null;
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'sanitized_data' => $sanitized
        ];
    }

    /**
     * Validate structural validity of field definitions
     *
     * @param array<int, array<string, mixed>> $fields
     */
    private function validateFields(array $fields): void
    {
        $allowedTypes = ['text', 'textarea', 'number', 'select', 'date', 'checkbox', 'calculation', 'file'];
        foreach ($fields as $idx => $f) {
            if (empty($f['name']) || !preg_match('/^[a-zA-Z0-9_]+$/', $f['name'])) {
                throw new \InvalidArgumentException("Field index {$idx} has an invalid or missing name identifier.");
            }
            $type = $f['type'] ?? 'text';
            if (!in_array($type, $allowedTypes)) {
                throw new \InvalidArgumentException("Field '{$f['name']}' uses unsupported field type: {$type}");
            }
        }
    }
}
