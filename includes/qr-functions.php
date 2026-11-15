<?php
/**
 * SafeMatch - QR code generation & payload helpers
 *
 * Contains a dependency-free QR Code encoder (ISO/IEC 18004, byte mode,
 * versions 1-40, Reed-Solomon error correction, automatic mask selection)
 * that renders PNG images with GD. The algorithm follows the well-known
 * reference design by Project Nayuki.
 *
 * Payloads are JSON and carry a short HMAC signature ("sig") so a forged or
 * hand-edited QR band is rejected during verification.
 */

require_once __DIR__ . '/functions.php';

/* =========================================================================
 * QR encoder
 * ====================================================================== */

final class QrEncoder
{
    // Error-correction levels and their 2-bit format indicators
    public const ECC_LOW = 0;
    public const ECC_MEDIUM = 1;
    public const ECC_QUARTILE = 2;
    public const ECC_HIGH = 3;
    private const FORMAT_BITS = [1, 0, 3, 2];

    // Indexed [ecc][version]
    private const ECC_CODEWORDS_PER_BLOCK = [
        [-1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28],
        [-1, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        [-1, 17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
    ];
    private const NUM_ERROR_CORRECTION_BLOCKS = [
        [-1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25],
        [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49],
        [-1, 1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68],
        [-1, 1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81],
    ];

    private int $version;
    private int $size;
    private int $ecc;
    /** @var bool[][] [y][x] true = dark */
    private array $modules = [];
    /** @var bool[][] [y][x] true = function pattern (not data) */
    private array $isFunction = [];

    /**
     * Encodes $text (UTF-8 bytes) and returns the module matrix [y][x].
     *
     * @return bool[][]
     */
    public static function encode(string $text, int $ecc = self::ECC_MEDIUM): array
    {
        $bytes = array_values(unpack('C*', $text) ?: []);

        // Pick the smallest version that fits.
        for ($ver = 1; $ver <= 40; $ver++) {
            $countBits = $ver <= 9 ? 8 : 16;
            $needed = 4 + $countBits + count($bytes) * 8;
            if ($needed <= self::numDataCodewords($ver, $ecc) * 8) {
                break;
            }
        }
        if ($ver > 40) {
            throw new LengthException('Data too long for a QR code.');
        }

        // Build the bit stream: mode (0100 = byte), length, data.
        $bits = [];
        self::appendBits($bits, 0x4, 4);
        self::appendBits($bits, count($bytes), $countBits);
        foreach ($bytes as $b) {
            self::appendBits($bits, $b, 8);
        }

        // Terminator, byte alignment and pad bytes.
        $capacity = self::numDataCodewords($ver, $ecc) * 8;
        self::appendBits($bits, 0, min(4, $capacity - count($bits)));
        self::appendBits($bits, 0, (8 - count($bits) % 8) % 8);
        for ($pad = 0xEC; count($bits) < $capacity; $pad ^= 0xEC ^ 0x11) {
            self::appendBits($bits, $pad, 8);
        }

        $codewords = array_fill(0, intdiv(count($bits), 8), 0);
        foreach ($bits as $i => $bit) {
            $codewords[$i >> 3] |= $bit << (7 - ($i & 7));
        }

        $qr = new self($ver, $ecc);
        return $qr->build($codewords);
    }

    private function __construct(int $version, int $ecc)
    {
        $this->version = $version;
        $this->ecc = $ecc;
        $this->size = $version * 4 + 17;
        $row = array_fill(0, $this->size, false);
        $this->modules = array_fill(0, $this->size, $row);
        $this->isFunction = array_fill(0, $this->size, $row);
    }

    private function build(array $dataCodewords): array
    {
        $this->drawFunctionPatterns();
        $all = $this->addEccAndInterleave($dataCodewords);
        $this->drawCodewords($all);

        // Try every mask and keep the one with the lowest penalty.
        $best = 0;
        $minPenalty = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $this->applyMask($mask);
            $this->drawFormatBits($mask);
            $penalty = $this->penaltyScore();
            if ($penalty < $minPenalty) {
                $best = $mask;
                $minPenalty = $penalty;
            }
            $this->applyMask($mask); // XOR again = undo
        }
        $this->applyMask($best);
        $this->drawFormatBits($best);

        return $this->modules;
    }

    /* ---------- Function patterns ---------- */

    private function drawFunctionPatterns(): void
    {
        for ($i = 0; $i < $this->size; $i++) {
            $this->setFunction(6, $i, $i % 2 === 0);
            $this->setFunction($i, 6, $i % 2 === 0);
        }

        $this->drawFinder(3, 3);
        $this->drawFinder($this->size - 4, 3);
        $this->drawFinder(3, $this->size - 4);

        $pos = $this->alignmentPositions();
        $n = count($pos);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $n - 1) || ($i === $n - 1 && $j === 0)) {
                    continue; // overlaps a finder pattern
                }
                $this->drawAlignment($pos[$i], $pos[$j]);
            }
        }

        $this->drawFormatBits(0); // placeholder, overwritten after masking
        $this->drawVersion();
    }

    private function drawFinder(int $x, int $y): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $dist = max(abs($dx), abs($dy));
                $xx = $x + $dx;
                $yy = $y + $dy;
                if ($xx >= 0 && $xx < $this->size && $yy >= 0 && $yy < $this->size) {
                    $this->setFunction($xx, $yy, $dist !== 2 && $dist !== 4);
                }
            }
        }
    }

    private function drawAlignment(int $x, int $y): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $this->setFunction($x + $dx, $y + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
    }

    private function alignmentPositions(): array
    {
        if ($this->version === 1) {
            return [];
        }
        $numAlign = intdiv($this->version, 7) + 2;
        $step = intdiv($this->version * 8 + $numAlign * 3 + 5, $numAlign * 4 - 4) * 2;
        $result = [];
        for ($i = 0, $pos = $this->size - 7; $i < $numAlign - 1; $i++, $pos -= $step) {
            array_unshift($result, $pos);
        }
        array_unshift($result, 6);
        return $result;
    }

    private function drawFormatBits(int $mask): void
    {
        $data = (self::FORMAT_BITS[$this->ecc] << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;

        // First copy (around the top-left finder)
        for ($i = 0; $i <= 5; $i++) {
            $this->setFunction(8, $i, self::bit($bits, $i));
        }
        $this->setFunction(8, 7, self::bit($bits, 6));
        $this->setFunction(8, 8, self::bit($bits, 7));
        $this->setFunction(7, 8, self::bit($bits, 8));
        for ($i = 9; $i < 15; $i++) {
            $this->setFunction(14 - $i, 8, self::bit($bits, $i));
        }

        // Second copy (split between top-right and bottom-left)
        for ($i = 0; $i < 8; $i++) {
            $this->setFunction($this->size - 1 - $i, 8, self::bit($bits, $i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFunction(8, $this->size - 15 + $i, self::bit($bits, $i));
        }
        $this->setFunction(8, $this->size - 8, true); // always-dark module
    }

    private function drawVersion(): void
    {
        if ($this->version < 7) {
            return;
        }
        $rem = $this->version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        $bits = ($this->version << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $bit = self::bit($bits, $i);
            $a = $this->size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $this->setFunction($a, $b, $bit);
            $this->setFunction($b, $a, $bit);
        }
    }

    private function setFunction(int $x, int $y, bool $dark): void
    {
        $this->modules[$y][$x] = $dark;
        $this->isFunction[$y][$x] = true;
    }

    /* ---------- Data & error correction ---------- */

    private function addEccAndInterleave(array $data): array
    {
        $numBlocks = self::NUM_ERROR_CORRECTION_BLOCKS[$this->ecc][$this->version];
        $blockEccLen = self::ECC_CODEWORDS_PER_BLOCK[$this->ecc][$this->version];
        $rawCodewords = intdiv(self::numRawDataModules($this->version), 8);
        $numShortBlocks = $numBlocks - $rawCodewords % $numBlocks;
        $shortBlockLen = intdiv($rawCodewords, $numBlocks);

        $divisor = self::rsDivisor($blockEccLen);
        $blocks = [];
        for ($i = 0, $k = 0; $i < $numBlocks; $i++) {
            $len = $shortBlockLen - $blockEccLen + ($i < $numShortBlocks ? 0 : 1);
            $dat = array_slice($data, $k, $len);
            $k += $len;
            $ecc = self::rsRemainder($dat, $divisor);
            if ($i < $numShortBlocks) {
                $dat[] = 0; // placeholder so all blocks have equal length
            }
            $blocks[] = array_merge($dat, $ecc);
        }

        $result = [];
        $blockLen = count($blocks[0]);
        for ($i = 0; $i < $blockLen; $i++) {
            foreach ($blocks as $j => $block) {
                if ($i !== $shortBlockLen - $blockEccLen || $j >= $numShortBlocks) {
                    $result[] = $block[$i];
                }
            }
        }
        return $result;
    }

    private function drawCodewords(array $data): void
    {
        $totalBits = count($data) * 8;
        $i = 0;
        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5; // skip the vertical timing column
            }
            for ($vert = 0; $vert < $this->size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $this->size - 1 - $vert : $vert;
                    if (!$this->isFunction[$y][$x] && $i < $totalBits) {
                        $this->modules[$y][$x] = self::bit($data[$i >> 3], 7 - ($i & 7));
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->isFunction[$y][$x]) {
                    continue;
                }
                switch ($mask) {
                    case 0: $invert = ($x + $y) % 2 === 0; break;
                    case 1: $invert = $y % 2 === 0; break;
                    case 2: $invert = $x % 3 === 0; break;
                    case 3: $invert = ($x + $y) % 3 === 0; break;
                    case 4: $invert = (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0; break;
                    case 5: $invert = ($x * $y % 2 + $x * $y % 3) === 0; break;
                    case 6: $invert = (($x * $y % 2 + $x * $y % 3) % 2) === 0; break;
                    default: $invert = ((($x + $y) % 2 + $x * $y % 3) % 2) === 0; break;
                }
                if ($invert) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    /** Penalty score used to choose the most scanner-friendly mask. */
    private function penaltyScore(): int
    {
        $n = $this->size;
        $m = $this->modules;
        $penalty = 0;

        // Rule 1: runs of 5+ same-colour modules in rows and columns.
        for ($a = 0; $a < $n; $a++) {
            foreach ([true, false] as $horizontal) {
                $run = 1;
                for ($b = 1; $b < $n; $b++) {
                    $cur  = $horizontal ? $m[$a][$b] : $m[$b][$a];
                    $prev = $horizontal ? $m[$a][$b - 1] : $m[$b - 1][$a];
                    if ($cur === $prev) {
                        $run++;
                        if ($run === 5) {
                            $penalty += 3;
                        } elseif ($run > 5) {
                            $penalty++;
                        }
                    } else {
                        $run = 1;
                    }
                }
            }
        }

        // Rule 2: 2x2 blocks of the same colour.
        for ($y = 0; $y < $n - 1; $y++) {
            for ($x = 0; $x < $n - 1; $x++) {
                $c = $m[$y][$x];
                if ($c === $m[$y][$x + 1] && $c === $m[$y + 1][$x] && $c === $m[$y + 1][$x + 1]) {
                    $penalty += 3;
                }
            }
        }

        // Rule 3: finder-like patterns 1011101 with 4 light modules on a side.
        $p1 = [true, false, true, true, true, false, true, false, false, false, false];
        $p2 = array_reverse($p1);
        for ($a = 0; $a < $n; $a++) {
            for ($b = 0; $b <= $n - 11; $b++) {
                foreach ([true, false] as $horizontal) {
                    $match1 = $match2 = true;
                    for ($k = 0; $k < 11 && ($match1 || $match2); $k++) {
                        $v = $horizontal ? $m[$a][$b + $k] : $m[$b + $k][$a];
                        if ($v !== $p1[$k]) {
                            $match1 = false;
                        }
                        if ($v !== $p2[$k]) {
                            $match2 = false;
                        }
                    }
                    if ($match1) {
                        $penalty += 40;
                    }
                    if ($match2) {
                        $penalty += 40;
                    }
                }
            }
        }

        // Rule 4: balance of dark and light modules.
        $dark = 0;
        foreach ($m as $row) {
            foreach ($row as $v) {
                if ($v) {
                    $dark++;
                }
            }
        }
        $total = $n * $n;
        $k = (int) ceil(abs($dark * 20 - $total * 10) / $total) - 1;
        $penalty += max(0, $k) * 10;

        return $penalty;
    }

    /* ---------- Static math helpers ---------- */

    private static function numRawDataModules(int $ver): int
    {
        $result = (16 * $ver + 128) * $ver + 64;
        if ($ver >= 2) {
            $numAlign = intdiv($ver, 7) + 2;
            $result -= (25 * $numAlign - 10) * $numAlign - 55;
            if ($ver >= 7) {
                $result -= 36;
            }
        }
        return $result;
    }

    private static function numDataCodewords(int $ver, int $ecc): int
    {
        return intdiv(self::numRawDataModules($ver), 8)
            - self::ECC_CODEWORDS_PER_BLOCK[$ecc][$ver] * self::NUM_ERROR_CORRECTION_BLOCKS[$ecc][$ver];
    }

    private static function rsDivisor(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::gfMultiply($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = self::gfMultiply($root, 0x02);
        }
        return $result;
    }

    private static function rsRemainder(array $data, array $divisor): array
    {
        $result = array_fill(0, count($divisor), 0);
        foreach ($data as $b) {
            $factor = $b ^ array_shift($result);
            $result[] = 0;
            foreach ($divisor as $i => $coef) {
                $result[$i] ^= self::gfMultiply($coef, $factor);
            }
        }
        return $result;
    }

    /** Multiplication in GF(2^8) modulo x^8 + x^4 + x^3 + x^2 + 1. */
    private static function gfMultiply(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = (($z << 1) ^ (($z >> 7) * 0x11D)) & 0xFF;
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z;
    }

    private static function appendBits(array &$bits, int $value, int $length): void
    {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    private static function bit(int $x, int $i): bool
    {
        return (($x >> $i) & 1) !== 0;
    }
}

/**
 * Renders a QR code for $text as a PNG data URI ("data:image/png;base64,...").
 *
 * @param int $scale  pixels per module
 * @param int $border quiet-zone width in modules (spec minimum is 4)
 */
function qr_png_data_uri(string $text, int $scale = 8, int $border = 4): string
{
    $matrix = QrEncoder::encode($text, QrEncoder::ECC_MEDIUM);
    $size = count($matrix);
    $px = ($size + $border * 2) * $scale;

    $img = imagecreate($px, $px); // palette image keeps the PNG tiny
    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 0, 0, 0);
    imagefill($img, 0, 0, $white);

    foreach ($matrix as $y => $row) {
        foreach ($row as $x => $dark) {
            if ($dark) {
                $x0 = ($x + $border) * $scale;
                $y0 = ($y + $border) * $scale;
                imagefilledrectangle($img, $x0, $y0, $x0 + $scale - 1, $y0 + $scale - 1, $black);
            }
        }
    }

    ob_start();
    imagepng($img, null, 9);
    $png = ob_get_clean();
    imagedestroy($img);

    return 'data:image/png;base64,' . base64_encode($png);
}

/* =========================================================================
 * SafeMatch payloads
 * ====================================================================== */

/** Short HMAC over the payload (without "sig"), keyed with APP_SECRET. */
function qr_signature(array $payload): string
{
    unset($payload['sig']);
    return substr(hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), APP_SECRET), 0, 16);
}

/** Payload for a mother's wristband. Key order is fixed (it affects the signature). */
function mother_qr_payload(array $mother): array
{
    $payload = [
        'type'      => 'mother',
        'id'        => $mother['mother_id'],
        'name'      => full_name($mother),
        'dob'       => substr((string) $mother['date_of_birth'], 0, 10),
        'admission' => substr((string) $mother['admission_date'], 0, 10),
    ];
    $payload['sig'] = qr_signature($payload);
    return $payload;
}

/** Payload for a newborn's band - includes the mother's ID it belongs to. */
function newborn_qr_payload(array $newborn, array $mother): array
{
    $payload = [
        'type'      => 'newborn',
        'id'        => $newborn['newborn_id'],
        'mother_id' => $mother['mother_id'],
        'name'      => newborn_display_name($newborn),
        'dob'       => substr((string) $newborn['date_of_birth'], 0, 10),
        'tob'       => substr((string) $newborn['time_of_birth'], 0, 5),
        'sex'       => $newborn['sex'],
    ];
    $payload['sig'] = qr_signature($payload);
    return $payload;
}

function qr_json(array $payload): string
{
    return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Generates (or regenerates) the QR pair for a newborn and its mother and
 * stores both images + payloads in qr_codes. Returns the stored row.
 */
function generate_qr_pair(int $newbornPk, ?int $userId = null): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM newborns WHERE id = ?');
    $stmt->execute([$newbornPk]);
    $newborn = $stmt->fetch();
    if (!$newborn) {
        throw new InvalidArgumentException('Newborn not found.');
    }
    $stmt = $pdo->prepare('SELECT * FROM mothers WHERE id = ?');
    $stmt->execute([$newborn['mother_id']]);
    $mother = $stmt->fetch();
    if (!$mother) {
        throw new InvalidArgumentException('Mother not found.');
    }

    $motherData  = qr_json(mother_qr_payload($mother));
    $newbornData = qr_json(newborn_qr_payload($newborn, $mother));
    $motherImg   = qr_png_data_uri($motherData);
    $newbornImg  = qr_png_data_uri($newbornData);

    // One active QR pair per newborn: replace any previous one.
    $pdo->prepare('DELETE FROM qr_codes WHERE newborn_id = ?')->execute([$newbornPk]);
    $pdo->prepare(
        'INSERT INTO qr_codes (mother_id, newborn_id, mother_qr_code, newborn_qr_code, mother_qr_data, newborn_qr_data, generated_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([$mother['id'], $newbornPk, $motherImg, $newbornImg, $motherData, $newbornData, $userId]);

    $stmt = $pdo->prepare('SELECT * FROM qr_codes WHERE id = ?');
    $stmt->execute([$pdo->lastInsertId()]);
    return $stmt->fetch();
}

/** Regenerates QR pairs for every newborn of a mother (after her details change). */
function regenerate_mother_qrs(int $motherPk, ?int $userId = null): void
{
    $stmt = db()->prepare('SELECT id FROM newborns WHERE mother_id = ?');
    $stmt->execute([$motherPk]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $newbornId) {
        generate_qr_pair((int) $newbornId, $userId);
    }
}

/**
 * Parses scanned QR text. Accepts the JSON payload, or a bare ID typed
 * manually (e.g. "M2026001") as a fallback when no camera is available.
 *
 * @return array{payload: ?array, id: ?string, manual: bool, signature_ok: bool}
 */
function parse_scanned_qr(string $raw): array
{
    $raw = trim($raw);
    $json = json_decode($raw, true);
    if (is_array($json) && isset($json['type'], $json['id'])) {
        $sigOk = isset($json['sig']) && is_string($json['sig'])
            && hash_equals(qr_signature($json), $json['sig']);
        return ['payload' => $json, 'id' => (string) $json['id'], 'manual' => false, 'signature_ok' => $sigOk];
    }
    if (preg_match('/^[MN]\d{7,}$/i', $raw)) {
        return ['payload' => null, 'id' => strtoupper($raw), 'manual' => true, 'signature_ok' => false];
    }
    return ['payload' => null, 'id' => null, 'manual' => false, 'signature_ok' => false];
}
