<?php

namespace Jibiat\Socle\Contract;

/**
 * Validateur JSON Schema réduit au besoin du contrat (sans dépendance) : type (dont « null »), required, properties,
 * items, enum, format date / date-time, $ref vers un autre schéma du contrat. Les champs en plus sont permis :
 * ajouter un champ ne casse jamais le contrat.
 */
final class SchemaValidator
{
    /** @var callable(string): array<string, mixed> */
    private $resolve;

    /**
     * @param  callable(string): array<string, mixed>  $resolve  schéma du contrat par son nom (« order »)
     */
    public function __construct(callable $resolve)
    {
        $this->resolve = $resolve;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<string> erreurs (« $.data.order.status : valeur « perdue » hors de la liste »), vide si conforme
     */
    public function validate(mixed $value, array $schema, string $path = '$'): array
    {
        if (isset($schema['$ref'])) {
            return $this->validate($value, ($this->resolve)(basename((string) $schema['$ref'], '.json')), $path);
        }

        $types = (array) ($schema['type'] ?? []);
        if ($types !== [] && ! $this->matchesType($value, $types)) {
            return ["{$path} : ".$this->describe($value).' au lieu de '.implode(' ou ', $types)];
        }
        if ($value === null) {
            return [];
        }

        $errors = [];
        if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            $errors[] = "{$path} : valeur « ".(is_scalar($value) ? $value : json_encode($value)).' » hors de la liste ('.implode(', ', $schema['enum']).')';
        }
        if (($schema['format'] ?? null) === 'date' && is_string($value) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $errors[] = "{$path} : date attendue (AAAA-MM-JJ), reçu « {$value} »";
        }
        if (($schema['format'] ?? null) === 'date-time' && is_string($value) && ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value)) {
            $errors[] = "{$path} : date et heure ISO 8601 attendues, reçu « {$value} »";
        }

        if (is_array($value) && ! array_is_list($value) || (is_array($value) && $value === [] && isset($schema['properties']))) {
            foreach ($schema['required'] ?? [] as $key) {
                if (! array_key_exists($key, $value)) {
                    $errors[] = "{$path}.{$key} : champ obligatoire absent";
                }
            }
            foreach ($schema['properties'] ?? [] as $key => $sub) {
                if (array_key_exists($key, $value)) {
                    array_push($errors, ...$this->validate($value[$key], $sub, "{$path}.{$key}"));
                }
            }
        }
        if (is_array($value) && array_is_list($value) && isset($schema['items'])) {
            foreach ($value as $i => $item) {
                array_push($errors, ...$this->validate($item, $schema['items'], "{$path}[{$i}]"));
            }
        }

        return $errors;
    }

    /**
     * @param  list<string>  $types
     */
    private function matchesType(mixed $value, array $types): bool
    {
        foreach ($types as $type) {
            $ok = match ($type) {
                'null' => $value === null,
                'string' => is_string($value),
                'integer' => is_int($value),
                'number' => is_int($value) || is_float($value),
                'boolean' => is_bool($value),
                // Objet JSON vide décodé en tableau : accepté comme objet
                'object' => is_array($value) && ($value === [] || ! array_is_list($value)),
                'array' => is_array($value) && array_is_list($value),
                default => false,
            };
            if ($ok) {
                return true;
            }
        }

        return false;
    }

    private function describe(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => 'booléen',
            is_int($value) => 'entier',
            is_float($value) => 'nombre',
            is_string($value) => 'texte',
            is_array($value) && array_is_list($value) => 'liste',
            default => 'objet',
        };
    }
}
