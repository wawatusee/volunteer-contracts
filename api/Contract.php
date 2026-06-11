<?php
/**
 * Contract
 *
 * Value-object + factory for a volunteer contract entity.
 *
 * A contract links to a worker (via worker_id) OR stores volunteer data inline
 * (when the volunteer is not in the workers database).
 *
 * Template variables exported by toTemplateVars() map 1-to-1 onto the
 * {{placeholders}} used in the bilingual contract template.
 *
 * @package VolunteerContracts\Model
 */
class Contract
{
    public const COLLECTION = 'contracts';

    /**
     * All editable contract fields with their validation rules.
     * volunteer_* fields are required only when worker_id is empty.
     */
    public const FIELDS = [
        // Volunteer identity (pre-filled from worker or entered manually)
        'worker_id'         => ['required' => false, 'type' => 'string'],
        'volunteer_first'   => ['required' => false, 'type' => 'string'],
        'volunteer_last'    => ['required' => false, 'type' => 'string'],
        'volunteer_birth'   => ['required' => false, 'type' => 'date'],
        'volunteer_address' => ['required' => false, 'type' => 'string'],
        'volunteer_email'   => ['required' => false, 'type' => 'email'],
        'volunteer_iban'    => ['required' => false, 'type' => 'iban'],

        // Contract specifics
        'role'              => ['required' => true,  'type' => 'string'],
        'event_name'        => ['required' => true,  'type' => 'string'],
        'start_date'        => ['required' => true,  'type' => 'date'],
        'end_date'          => ['required' => true,  'type' => 'date'],
        'total_amount'      => ['required' => true,  'type' => 'positive_number'],
        'signature_date'    => ['required' => true,  'type' => 'date'],
        'organisation_name' => ['required' => true,  'type' => 'string'],
        'organisation_rep'  => ['required' => true,  'type' => 'string'],

        // Metadata
        'status'            => ['required' => false, 'type' => 'string'], // draft|signed|archived
        'language'          => ['required' => false, 'type' => 'string'], // nl|en|both
    ];

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    /**
     * Build and validate a contract from raw input.
     * Does NOT persist or load worker data.
     *
     * @param  array $raw
     * @return array ['data' => array, 'errors' => string[]]
     */
    public static function fromInput(array $raw): array
    {
        $data   = [];
        $errors = [];

        foreach (self::FIELDS as $field => $meta) {
            $value = trim($raw[$field] ?? '');

            // Volunteer fields are required when no worker_id is given
            $isVolunteerField = str_starts_with($field, 'volunteer_');
            $hasWorker        = !empty($raw['worker_id']);
            $isRequired       = $meta['required'] || ($isVolunteerField && !$hasWorker);

            if ($isRequired && $value === '') {
                $errors[] = "Field '{$field}' is required.";
                continue;
            }

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
                        // Reuse Worker's IBAN validator
                        require_once __DIR__ . '/Worker.php';
                        $ibanError = Worker::validateIban($value);
                        if ($ibanError) {
                            $errors[] = "Field '{$field}': {$ibanError}";
                        }
                        $value = strtoupper(preg_replace('/\s+/', '', $value));
                    }
                    break;

                case 'positive_number':
                    if ($value !== '') {
                        $num = filter_var($value, FILTER_VALIDATE_FLOAT);
                        if ($num === false || $num < 0) {
                            $errors[] = "Field '{$field}' must be a positive number.";
                        }
                    }
                    break;
            }

            $data[$field] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }

        // Cross-field validation: start_date ≤ end_date
        if (!empty($data['start_date']) && !empty($data['end_date'])) {
            $start = \DateTime::createFromFormat('Y-m-d', $data['start_date']);
            $end   = \DateTime::createFromFormat('Y-m-d', $data['end_date']);
            if ($start && $end && $start > $end) {
                $errors[] = "start_date must be before or equal to end_date.";
            }
        }

        // Defaults
        $data['status']   = $data['status']   ?: 'draft';
        $data['language'] = $data['language'] ?: 'both';

        if (!empty($raw['id'])) {
            $data['id'] = $raw['id'];
        }

        return ['data' => $data, 'errors' => $errors];
    }

    // -------------------------------------------------------------------------
    // Template variables
    // -------------------------------------------------------------------------

    /**
     * Produce the flat key→value map consumed by ContractTemplate::render().
     *
     * When a worker record is supplied, its fields take precedence over any
     * inline volunteer_* fields stored on the contract.
     *
     * @param  array      $contract  Contract record from storage
     * @param  array|null $worker    Worker record from storage (optional)
     * @return array<string, string>
     */
    public static function toTemplateVars(array $contract, ?array $worker = null): array
    {
        require_once __DIR__ . '/Worker.php';

        // Helper to pick worker value first, then contract inline value
        $pick = function (string $workerField, string $contractField) use ($worker, $contract): string {
            if ($worker && !empty($worker[$workerField])) {
                return $worker[$workerField];
            }
            return $contract[$contractField] ?? '';
        };

        $firstName = $pick('first_name', 'volunteer_first');
        $lastName  = $pick('last_name',  'volunteer_last');
        $address   = $worker
            ? Worker::fullAddress($worker)
            : ($contract['volunteer_address'] ?? '');

        return [
            'volunteer_name'    => trim("{$firstName} {$lastName}"),
            'birth_date'        => self::formatDate($pick('birth_date', 'volunteer_birth')),
            'address'           => $address,
            'email'             => $pick('email',      'volunteer_email'),
            'iban'              => $pick('iban',        'volunteer_iban'),
            'role'              => $contract['role']              ?? '',
            'event_name'        => $contract['event_name']        ?? '',
            'start_date'        => self::formatDate($contract['start_date']  ?? ''),
            'end_date'          => self::formatDate($contract['end_date']    ?? ''),
            'total_amount'      => number_format((float)($contract['total_amount'] ?? 0), 2, '.', ' '),
            'signature_date'    => self::formatDate($contract['signature_date'] ?? ''),
            'organisation_name' => $contract['organisation_name'] ?? '',
            'organisation_rep'  => $contract['organisation_rep']  ?? '',
        ];
    }

    /**
     * Format a Y-m-d date string to DD/MM/YYYY for display.
     */
    public static function formatDate(string $ymd): string
    {
        if ($ymd === '') return '';
        $dt = \DateTime::createFromFormat('Y-m-d', $ymd);
        return $dt ? $dt->format('d/m/Y') : $ymd;
    }
}
