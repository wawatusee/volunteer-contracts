<?php
/**
 * Worker
 *
 * Value-object + factory for a "potential worker" entity.
 * Responsible for:
 *  - Defining which fields are required / optional
 *  - Validating incoming data (returns a list of error messages)
 *  - Sanitising values before they leave this layer
 *
 * This class is intentionally free of storage concerns.
 *
 * @package VolunteerContracts\Model
 */
class Worker
{
    // Collection name used in StorageInterface calls
    public const COLLECTION = 'workers';

    // Field definitions: name → [required, sanitiser hint]
    public const FIELDS = [
        'first_name'  => ['required' => true,  'type' => 'string'],
        'last_name'   => ['required' => true,  'type' => 'string'],
        'birth_date'  => ['required' => true,  'type' => 'date'],
        'address'     => ['required' => true,  'type' => 'string'],
        'city'        => ['required' => true,  'type' => 'string'],
        'postal_code' => ['required' => true,  'type' => 'string'],
        'country'     => ['required' => true,  'type' => 'string'],
        'email'       => ['required' => true,  'type' => 'email'],
        'iban'        => ['required' => true,  'type' => 'iban'],
        'phone'       => ['required' => false, 'type' => 'string'],
        'notes'       => ['required' => false, 'type' => 'string'],
    ];

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    /**
     * Create a sanitised worker array from raw input.
     * Does NOT persist anything.
     *
     * @param  array $raw   Raw POST / JSON input
     * @return array        ['data' => array, 'errors' => string[]]
     */
    public static function fromInput(array $raw): array
    {
        $data   = [];
        $errors = [];

        foreach (self::FIELDS as $field => $meta) {
            $value = trim($raw[$field] ?? '');

            if ($meta['required'] && $value === '') {
                $errors[] = "Field '{$field}' is required.";
                continue;
            }

            // Type-specific validation
            switch ($meta['type']) {
                case 'email':
                    if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Field '{$field}' must be a valid email address.";
                    }
                    break;

                case 'date':
                    if ($value !== '' && !\DateTime::createFromFormat('Y-m-d', $value)) {
                        $errors[] = "Field '{$field}' must be a valid date (YYYY-MM-DD).";
                    }
                    break;

                case 'iban':
                    if ($value !== '') {
                        $ibanError = self::validateIban($value);
                        if ($ibanError) {
                            $errors[] = "Field '{$field}': {$ibanError}";
                        }
                        $value = strtoupper(preg_replace('/\s+/', '', $value));
                    }
                    break;
            }

            $data[$field] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }

        // Preserve existing id if updating
        if (!empty($raw['id'])) {
            $data['id'] = $raw['id'];
        }

        return ['data' => $data, 'errors' => $errors];
    }

    /**
     * Build a display-friendly full name from a worker record.
     */
    public static function fullName(array $worker): string
    {
        return trim(($worker['first_name'] ?? '') . ' ' . ($worker['last_name'] ?? ''));
    }

    /**
     * Build a full address string.
     */
    public static function fullAddress(array $worker): string
    {
        return implode(', ', array_filter([
            $worker['address']     ?? '',
            $worker['postal_code'] ?? '',
            $worker['city']        ?? '',
            $worker['country']     ?? '',
        ]));
    }

    // -------------------------------------------------------------------------
    // IBAN validation (basic structural check, not bank-specific)
    // -------------------------------------------------------------------------

    /**
     * Validate an IBAN string.
     *
     * @return string  Error message, or empty string on success
     */
    public static function validateIban(string $raw): string
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $raw));

        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/', $iban)) {
            return 'IBAN format is invalid.';
        }

        // MOD-97 check
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $numeric    = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (ord($char) - 55) : $char;
        }

        $remainder = '0';
        foreach (str_split($numeric, 9) as $chunk) {
            $remainder = (string)(((int)$remainder . $chunk) % 97);
        }

        if ((int)$remainder !== 1) {
            return 'IBAN checksum is invalid.';
        }

        return '';
    }
}
