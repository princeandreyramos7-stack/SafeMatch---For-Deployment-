<?php
/**
 * SafeMatch - Discharge readiness logic
 *
 * Each newborn (with its mother) has one discharge record. The record holds a
 * decision row for every active discharge requirement. Readiness is:
 *   percentage = requirements met / active requirements
 *   green      = every mandatory requirement met
 *   yellow     = at least READINESS_YELLOW_THRESHOLD % met
 *   red        = anything less
 * Final discharge additionally requires that the most recent QR verification
 * of the pair is a "match".
 */

require_once __DIR__ . '/functions.php';

/**
 * Returns the discharge record for a newborn, creating it (and syncing one
 * decision row per active requirement) when needed.
 */
function ensure_discharge_record(int $newbornPk): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, mother_id FROM newborns WHERE id = ?');
    $stmt->execute([$newbornPk]);
    $newborn = $stmt->fetch();
    if (!$newborn) {
        throw new InvalidArgumentException('Newborn not found.');
    }

    $stmt = $pdo->prepare('SELECT * FROM discharge_records WHERE newborn_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$newbornPk]);
    $record = $stmt->fetch();

    if (!$record) {
        $pdo->prepare('INSERT INTO discharge_records (mother_id, newborn_id, discharge_status) VALUES (?, ?, ?)')
            ->execute([$newborn['mother_id'], $newbornPk, 'not_ready']);
        $stmt->execute([$newbornPk]);
        $record = $stmt->fetch();
    }

    // Requirements added after the record was created get a row too
    // (but a completed discharge is frozen).
    if ($record['discharge_status'] !== 'discharged') {
        $pdo->prepare(
            'INSERT IGNORE INTO discharge_decisions (discharge_record_id, requirement_id, is_met)
             SELECT ?, id, 0 FROM discharge_requirements WHERE is_active = 1'
        )->execute([$record['id']]);
    }

    return $record;
}

/** Latest QR verification for a mother/newborn pair, or null. */
function latest_verification(int $motherPk, int $newbornPk): ?array
{
    $stmt = db()->prepare(
        'SELECT v.id, v.verification_result, v.verification_notes, v.verified_at, u.name AS verified_by_name
         FROM qr_verifications v
         LEFT JOIN users u ON u.id = v.verified_by
         WHERE v.mother_id = ? AND v.newborn_id = ?
         ORDER BY v.verified_at DESC, v.id DESC LIMIT 1'
    );
    $stmt->execute([$motherPk, $newbornPk]);
    return $stmt->fetch() ?: null;
}

/** Traffic-light colour for a readiness state. */
function readiness_light(string $status, float $percent, int $mandatoryPending): string
{
    if ($status === 'discharged') {
        return 'discharged';
    }
    if ($mandatoryPending === 0) {
        return 'green';
    }
    return $percent >= READINESS_YELLOW_THRESHOLD ? 'yellow' : 'red';
}

/**
 * Full readiness breakdown for a discharge record.
 */
function discharge_readiness(int $recordId): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM discharge_records WHERE id = ?');
    $stmt->execute([$recordId]);
    $record = $stmt->fetch();
    if (!$record) {
        throw new InvalidArgumentException('Discharge record not found.');
    }

    // Open records: every active requirement. Discharged records are frozen:
    // show exactly the decisions that existed at discharge time.
    $discharged = $record['discharge_status'] === 'discharged' ? 1 : 0;
    $stmt = $pdo->prepare(
        'SELECT r.id AS requirement_id, r.name, r.description, r.category, r.is_mandatory,
                d.id AS decision_id, COALESCE(d.is_met, 0) AS is_met, d.checked_at, d.notes,
                u.name AS checked_by_name
         FROM discharge_requirements r
         LEFT JOIN discharge_decisions d ON d.requirement_id = r.id AND d.discharge_record_id = ?
         LEFT JOIN users u ON u.id = d.checked_by
         WHERE (? = 0 AND r.is_active = 1) OR (? = 1 AND d.id IS NOT NULL)
         ORDER BY FIELD(r.category, \'mother\', \'newborn\', \'both\'), r.id'
    );
    $stmt->execute([$recordId, $discharged, $discharged]);
    $requirements = $stmt->fetchAll();

    $total = count($requirements);
    $met = 0;
    $mandatoryPending = 0;
    $pending = [];
    foreach ($requirements as &$req) {
        $req['is_met'] = (bool) $req['is_met'];
        $req['is_mandatory'] = (bool) $req['is_mandatory'];
        if ($req['is_met']) {
            $met++;
        } else {
            $pending[] = $req['name'];
            if ($req['is_mandatory']) {
                $mandatoryPending++;
            }
        }
    }
    unset($req);

    $percent = $total > 0 ? round($met / $total * 100, 1) : 100.0;
    $verification = latest_verification((int) $record['mother_id'], (int) $record['newborn_id']);
    $verified = $verification && $verification['verification_result'] === 'match';

    $blockers = [];
    if ($mandatoryPending > 0) {
        $blockers[] = "$mandatoryPending mandatory requirement(s) still pending.";
    }
    if (!$verified) {
        $blockers[] = $verification
            ? 'The latest QR verification was a MISMATCH. Re-verify the mother and baby bands.'
            : 'Mother and baby QR bands have not been verified yet.';
    }

    return [
        'record'            => $record,
        'requirements'      => $requirements,
        'total'             => $total,
        'met'               => $met,
        'percent'           => $percent,
        'mandatory_pending' => $mandatoryPending,
        'pending'           => $pending,
        'light'             => readiness_light($record['discharge_status'], $percent, $mandatoryPending),
        'verification'      => $verification,
        'verified'          => $verified,
        'can_discharge'     => $record['discharge_status'] !== 'discharged' && !$blockers,
        'blockers'          => $record['discharge_status'] === 'discharged' ? [] : $blockers,
    ];
}

/** Re-computes ready/not_ready after a requirement changes (never touches discharged). */
function refresh_discharge_status(int $recordId): string
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM discharge_requirements r
         LEFT JOIN discharge_decisions d ON d.requirement_id = r.id AND d.discharge_record_id = ?
         WHERE r.is_active = 1 AND r.is_mandatory = 1 AND COALESCE(d.is_met, 0) = 0"
    );
    $stmt->execute([$recordId]);
    $status = ((int) $stmt->fetchColumn()) === 0 ? 'ready' : 'not_ready';

    db()->prepare("UPDATE discharge_records SET discharge_status = ? WHERE id = ? AND discharge_status <> 'discharged'")
        ->execute([$status, $recordId]);
    return $status;
}

/** Recomputes status for every open record (used after requirements are reconfigured). */
function refresh_all_discharge_statuses(): void
{
    $ids = db()->query("SELECT id FROM discharge_records WHERE discharge_status <> 'discharged'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        refresh_discharge_status((int) $id);
    }
}

/**
 * SQL (without ORDER/LIMIT) listing every mother-newborn pair with its
 * readiness numbers. Used by the discharge list and the dashboard.
 */
function discharge_overview_sql(): string
{
    return "
        SELECT n.id AS newborn_pk, n.newborn_id, n.first_name AS nb_first_name, n.last_name AS nb_last_name,
               n.sex, n.date_of_birth AS nb_dob,
               m.id AS mother_pk, m.mother_id, m.first_name AS m_first_name, m.last_name AS m_last_name,
               m.room_number,
               dr.id AS record_id, COALESCE(dr.discharge_status, 'not_ready') AS discharge_status,
               dr.discharge_date,
               (SELECT COUNT(*) FROM discharge_requirements WHERE is_active = 1) AS total_requirements,
               (SELECT COUNT(*) FROM discharge_decisions dd
                  JOIN discharge_requirements r ON r.id = dd.requirement_id AND r.is_active = 1
                  WHERE dd.discharge_record_id = dr.id AND dd.is_met = 1) AS met_requirements,
               (SELECT COUNT(*) FROM discharge_requirements r
                  LEFT JOIN discharge_decisions dd ON dd.requirement_id = r.id AND dd.discharge_record_id = dr.id
                  WHERE r.is_active = 1 AND r.is_mandatory = 1 AND COALESCE(dd.is_met, 0) = 0) AS mandatory_pending,
               (SELECT v.verification_result FROM qr_verifications v
                  WHERE v.mother_id = m.id AND v.newborn_id = n.id
                  ORDER BY v.verified_at DESC, v.id DESC LIMIT 1) AS last_verification
        FROM newborns n
        JOIN mothers m ON m.id = n.mother_id
        LEFT JOIN discharge_records dr ON dr.id = (
            SELECT MAX(id) FROM discharge_records WHERE newborn_id = n.id
        )";
}

/** Adds percent + light to a row from discharge_overview_sql(). */
function decorate_overview_row(array $row): array
{
    $total = (int) $row['total_requirements'];
    $met = (int) $row['met_requirements'];
    $row['percent'] = $total > 0 ? round($met / $total * 100, 1) : 100.0;
    $row['light'] = readiness_light($row['discharge_status'], $row['percent'], (int) $row['mandatory_pending']);
    $row['mother_name'] = trim($row['m_first_name'] . ' ' . $row['m_last_name']);
    $row['newborn_name'] = newborn_display_name([
        'first_name' => $row['nb_first_name'], 'last_name' => $row['nb_last_name'], 'sex' => $row['sex'],
    ]);
    return $row;
}
