<?php
/**
 * SafeMatch - Validation rules and field mapping for mothers and newborns.
 * Shared by the create and update endpoints so both enforce the same rules.
 */

require_once __DIR__ . '/functions.php';

const MOTHER_FIELDS = [
    'first_name', 'middle_name', 'last_name', 'date_of_birth', 'address', 'contact_number',
    'emergency_contact', 'emergency_contact_number', 'blood_type', 'admission_date',
    'room_number', 'attending_physician', 'medical_history', 'allergies',
];

const NEWBORN_FIELDS = [
    'mother_id', 'first_name', 'middle_name', 'last_name', 'date_of_birth', 'time_of_birth', 'sex',
    'weight', 'length', 'head_circumference', 'apgar_score_1min', 'apgar_score_5min',
    'delivery_type', 'birth_complications', 'pediatrician',
];

/** Validates mother input. Returns [errors, cleanValues]. */
function validate_mother(array $data): array
{
    $errors = validate($data, [
        'first_name'               => 'required|maxlen:255',
        'middle_name'              => 'maxlen:255',
        'last_name'                => 'required|maxlen:255',
        'date_of_birth'            => 'required|date',
        'address'                  => 'required|maxlen:1000',
        'contact_number'           => 'required|phone',
        'emergency_contact'        => 'maxlen:255',
        'emergency_contact_number' => 'phone',
        'blood_type'               => 'in:' . implode(',', BLOOD_TYPES),
        'admission_date'           => 'required|datetime',
        'room_number'              => 'maxlen:50',
        'attending_physician'      => 'maxlen:255',
        'medical_history'          => 'maxlen:5000',
        'allergies'                => 'maxlen:2000',
    ], [
        'date_of_birth'  => 'Date of birth',
        'admission_date' => 'Admission date',
    ]);

    // Plausibility checks on dates
    if (empty($errors['date_of_birth'])) {
        $age = (new DateTime($data['date_of_birth']))->diff(new DateTime('today'))->y;
        if (new DateTime($data['date_of_birth']) > new DateTime('today')) {
            $errors['date_of_birth'][] = 'Date of birth cannot be in the future.';
        } elseif ($age < 10 || $age > 65) {
            $errors['date_of_birth'][] = 'Please check the date of birth (age must be between 10 and 65).';
        }
    }
    if (empty($errors['admission_date'])) {
        $admission = new DateTime(parse_datetime($data['admission_date']));
        if ($admission > new DateTime('+1 day')) {
            $errors['admission_date'][] = 'Admission date cannot be in the future.';
        }
    }

    $values = [];
    foreach (MOTHER_FIELDS as $field) {
        $v = $data[$field] ?? null;
        $values[$field] = nullable(is_string($v) ? trim($v) : $v);
    }
    if (empty($errors)) {
        $values['admission_date'] = parse_datetime($data['admission_date']);
    }
    return [$errors, $values];
}

/** Validates newborn input. Returns [errors, cleanValues]. */
function validate_newborn(array $data): array
{
    $errors = validate($data, [
        'mother_id'           => 'required|integer',
        'first_name'          => 'maxlen:255',
        'middle_name'         => 'maxlen:255',
        'last_name'           => 'required|maxlen:255',
        'date_of_birth'       => 'required|date',
        'time_of_birth'       => 'required|time',
        'sex'                 => 'required|in:Male,Female',
        'weight'              => 'required|numeric|min:0.3|max:7',
        'length'              => 'required|numeric|min:20|max:70',
        'head_circumference'  => 'numeric|min:20|max:50',
        'apgar_score_1min'    => 'integer|min:0|max:10',
        'apgar_score_5min'    => 'integer|min:0|max:10',
        'delivery_type'       => 'required|in:Normal,Cesarean',
        'birth_complications' => 'maxlen:5000',
        'pediatrician'        => 'maxlen:255',
    ], [
        'mother_id'          => 'Mother',
        'date_of_birth'      => 'Date of birth',
        'time_of_birth'      => 'Time of birth',
        'weight'             => 'Weight (kg)',
        'length'             => 'Length (cm)',
        'head_circumference' => 'Head circumference (cm)',
        'apgar_score_1min'   => 'APGAR (1 min)',
        'apgar_score_5min'   => 'APGAR (5 min)',
    ]);

    $values = [];
    foreach (NEWBORN_FIELDS as $field) {
        $v = $data[$field] ?? null;
        $values[$field] = nullable(is_string($v) ? trim($v) : $v);
    }

    if (empty($errors['date_of_birth']) && empty($errors['time_of_birth'])) {
        $time = strlen($values['time_of_birth']) === 5 ? $values['time_of_birth'] . ':00' : $values['time_of_birth'];
        $birth = new DateTime($values['date_of_birth'] . ' ' . $time);
        if ($birth > new DateTime('+5 minutes')) {
            $errors['date_of_birth'][] = 'Birth date/time cannot be in the future.';
        }
        $values['time_of_birth'] = $time;
        $values['date_of_birth'] = $birth->format('Y-m-d H:i:s');
    }

    // The baby must belong to an existing mother.
    if (empty($errors['mother_id'])) {
        $stmt = db()->prepare('SELECT id, admission_date FROM mothers WHERE id = ?');
        $stmt->execute([(int) $values['mother_id']]);
        if (!$stmt->fetch()) {
            $errors['mother_id'][] = 'Selected mother does not exist.';
        }
    }

    return [$errors, $values];
}
