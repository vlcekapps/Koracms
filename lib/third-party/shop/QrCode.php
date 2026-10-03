<?php

declare(strict_types=1);

namespace Kora\Shop\Qr;

/*
 * QR Code generator: PHP translation of official Nayuki v1.8.0 Python source.
 * Copyright (c) Project Nayuki. MIT license: LICENSE-Nayuki.txt.
 * https://www.nayuki.io/page/qr-code-generator-library
 * Local translation; unchanged upstream reference is qrcodegen.py.
 */
final class Segment
{
    public const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';
    private const COUNTS = [1 => [10,12,14], 2 => [9,11,13], 4 => [8,16,16], 8 => [8,10,12], 7 => [0,0,0]];
    public int $mode;
    public int $count;
    /** @var list<int> */
    public array $bits;

    /** @param array<int,int> $bits */
    public function __construct(int $mode, int $count, array $bits)
    {
        if (!isset(self::COUNTS[$mode]) || $count < 0 || array_diff($bits, [0,1]) !== []) {
            throw new \InvalidArgumentException('Invalid QR segment.');
        }
        $this->mode = $mode;
        $this->count = $count;
        $this->bits = array_values($bits);
    }

    public function countBits(int $version): int
    {
        return self::COUNTS[$this->mode][intdiv($version + 7, 17)];
    }

    /** @param list<int> $bits */
    public static function append(array &$bits, int $value, int $length): void
    {
        if ($length < 0 || $length > 31 || $value < 0 || ($value >> $length) !== 0) {
            throw new \InvalidArgumentException('Invalid QR bit value.');
        }
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    /** @return list<self> */
    public static function text(string $text): array
    {
        if ($text === '') {
            return [];
        }
        $bits = [];
        $length = strlen($text);
        if (preg_match('/\A[0-9]+\z/D', $text)) {
            for ($i = 0; $i < $length; $i += 3) {
                $n = min(3, $length - $i);
                self::append($bits, (int) substr($text, $i, $n), $n * 3 + 1);
            }
            return [new self(1, $length, $bits)];
        }
        if (strspn($text, self::ALPHABET) === $length) {
            for ($i = 0; $i < $length; $i += 2) {
                $value = strpos(self::ALPHABET, $text[$i]);
                if ($i + 1 < $length) {
                    self::append($bits, $value * 45 + strpos(self::ALPHABET, $text[$i + 1]), 11);
                } else {
                    self::append($bits, $value, 6);
                }
            }
            return [new self(2, $length, $bits)];
        }
        return [self::bytes($text)];
    }

    public static function bytes(string $data): self
    {
        $bits = [];
        for ($i = 0; $i < strlen($data); $i++) {
            self::append($bits, ord($data[$i]), 8);
        }
        return new self(4, strlen($data), $bits);
    }

    public static function eci(int $value): self
    {
        $bits = [];
        if ($value < 0 || $value >= 1000000) {
            throw new \InvalidArgumentException('ECI out of range.');
        }
        if ($value < 128) {
            self::append($bits, $value, 8);
        } elseif ($value < 16384) {
            self::append($bits, 2, 2);
            self::append($bits, $value, 14);
        } else {
            self::append($bits, 6, 3);
            self::append($bits, $value, 21);
        }
        return new self(7, 0, $bits);
    }
}

final class QrCode
{
    public const LOW = 0;
    public const MEDIUM = 1;
    public const QUARTILE = 2;
    public const HIGH = 3;
    private const ECC = [
        [-1,7,10,15,20,26,18,20,24,30,18,20,24,26,30,22,24,28,30,28,28,28,28,30,30,26,28,30,30,30,30,30,30,30,30,30,30,30,30,30,30],
        [-1,10,16,26,18,24,16,18,22,22,26,30,22,22,24,24,28,28,26,26,26,26,28,28,28,28,28,28,28,28,28,28,28,28,28,28,28,28,28,28,28],
        [-1,13,22,18,26,18,24,18,22,20,24,28,26,24,20,30,24,28,28,26,30,28,30,30,30,30,28,30,30,30,30,30,30,30,30,30,30,30,30,30,30],
        [-1,17,28,22,16,22,28,26,26,24,28,24,28,22,24,24,30,28,28,26,28,30,24,30,30,30,30,30,30,30,30,30,30,30,30,30,30,30,30,30,30],
    ];
    private const BLOCKS = [
        [-1,1,1,1,1,1,2,2,2,2,4,4,4,4,4,6,6,6,6,7,8,8,9,9,10,12,12,12,13,14,15,16,17,18,19,19,20,21,22,24,25],
        [-1,1,1,1,2,2,4,4,4,5,5,5,8,9,9,10,10,11,13,14,16,17,17,18,20,21,23,25,26,28,29,31,33,35,37,38,40,43,45,47,49],
        [-1,1,1,2,2,4,4,6,6,8,8,8,10,12,16,12,17,16,18,21,20,23,23,25,27,29,34,34,35,38,40,43,45,48,51,53,56,59,62,65,68],
        [-1,1,1,2,4,4,4,5,6,8,8,11,11,16,16,18,16,19,21,25,25,25,34,30,32,35,37,40,42,45,48,51,54,57,60,63,66,70,74,77,81],
    ];
    public int $version;
    public int $size;
    public int $ecc;
    public int $mask;
    /** @var list<list<bool>> */
    private array $modules;
    /** @var list<list<bool>> */
    private array $functions;

    public static function encodeText(string $text, int $ecc = self::MEDIUM): self
    {
        return self::encodeSegments(Segment::text($text), $ecc);
    }

    /** @param list<Segment> $segments */
    public static function encodeSegments(array $segments, int $ecc, int $min = 1, int $max = 40, int $mask = -1, bool $boost = true): self
    {
        if ($min < 1 || $min > $max || $max > 40 || $ecc < 0 || $ecc > 3 || $mask < -1 || $mask > 7) {
            throw new \InvalidArgumentException('Invalid QR parameters.');
        }
        for ($version = $min; ; $version++) {
            $used = 0;
            foreach ($segments as $segment) {
                $countBits = $segment->countBits($version);
                if ($segment->count >= (1 << $countBits)) {
                    $used = PHP_INT_MAX;
                    break;
                }
                $used += 4 + $countBits + count($segment->bits);
            }
            if ($used <= self::capacity($version, $ecc) * 8) {
                break;
            }
            if ($version >= $max) {
                throw new \LengthException('Data too long for QR Code.');
            }
        }
        for ($level = 1; $level <= 3; $level++) {
            if ($boost && $used <= self::capacity($version, $level) * 8) {
                $ecc = $level;
            }
        }
        $bits = [];
        foreach ($segments as $segment) {
            Segment::append($bits, $segment->mode, 4);
            Segment::append($bits, $segment->count, $segment->countBits($version));
            foreach ($segment->bits as $bit) {
                $bits[] = $bit;
            }
        }
        $capacity = self::capacity($version, $ecc) * 8;
        Segment::append($bits, 0, min(4, $capacity - count($bits)));
        Segment::append($bits, 0, (8 - count($bits) % 8) % 8);
        for ($pad = 0xEC; count($bits) < $capacity; $pad ^= 0xEC ^ 0x11) {
            Segment::append($bits, $pad, 8);
        }
        $data = array_fill(0, intdiv(count($bits), 8), 0);
        foreach ($bits as $i => $bit) {
            $data[$i >> 3] |= $bit << (7 - ($i & 7));
        }
        return new self($version, $ecc, $data, $mask);
    }

    /** @param list<int> $data */
    public function __construct(int $version, int $ecc, array $data, int $mask)
    {
        if ($version < 1 || $version > 40 || $ecc < 0 || $ecc > 3 || $mask < -1 || $mask > 7
            || count($data) !== self::capacity($version, $ecc) || array_diff($data, range(0, 255)) !== []) {
            throw new \InvalidArgumentException('Invalid QR codewords.');
        }
        $this->version = $version;
        $this->size = $version * 4 + 17;
        $this->ecc = $ecc;
        $this->modules = $this->functions = array_fill(0, $this->size, array_fill(0, $this->size, false));
        $this->drawFunctions();
        $words = $this->interleave($data);
        $i = 0;
        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $this->size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $y = (($right + 1) & 2) === 0 ? $this->size - 1 - $vert : $vert;
                    if (!$this->functions[$y][$x] && $i < count($words) * 8) {
                        $this->modules[$y][$x] = (($words[$i >> 3] >> (7 - ($i & 7))) & 1) !== 0;
                        $i++;
                    }
                }
            }
        }
        if ($mask === -1) {
            $best = PHP_INT_MAX;
            for ($m = 0; $m < 8; $m++) {
                $this->applyMask($m);
                $this->format($m);
                $score = $this->penalty();
                if ($score < $best) {
                    $best = $score;
                    $mask = $m;
                }
                $this->applyMask($m);
            }
        }
        $this->mask = $mask;
        $this->applyMask($mask);
        $this->format($mask);
        $this->functions = [];
    }

    public function getModule(int $x, int $y): bool
    {
        return $x >= 0 && $y >= 0 && $x < $this->size && $y < $this->size && $this->modules[$y][$x];
    }

    private static function rawModules(int $version): int
    {
        $result = (16 * $version + 128) * $version + 64;
        if ($version >= 2) {
            $align = intdiv($version, 7) + 2;
            $result -= (25 * $align - 10) * $align - 55;
            if ($version >= 7) {
                $result -= 36;
            }
        }
        return $result;
    }

    private static function capacity(int $version, int $ecc): int
    {
        return intdiv(self::rawModules($version), 8) - self::ECC[$ecc][$version] * self::BLOCKS[$ecc][$version];
    }

    private function set(int $x, int $y, bool $dark): void
    {
        $this->modules[$y][$x] = $dark;
        $this->functions[$y][$x] = true;
    }

    private function drawFunctions(): void
    {
        for ($i = 0; $i < $this->size; $i++) {
            $this->set(6, $i, $i % 2 === 0);
            $this->set($i, 6, $i % 2 === 0);
        }
        foreach ([[3,3],[$this->size - 4,3],[3,$this->size - 4]] as [$x,$y]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $xx = $x + $dx;
                    $yy = $y + $dy;
                    if ($xx >= 0 && $yy >= 0 && $xx < $this->size && $yy < $this->size) {
                        $this->set($xx, $yy, !in_array(max(abs($dx), abs($dy)), [2,4], true));
                    }
                }
            }
        }
        $positions = [];
        if ($this->version > 1) {
            $n = intdiv($this->version, 7) + 2;
            $step = $this->version === 32 ? 26 : intdiv($this->version * 4 + $n * 2 + 1, $n * 2 - 2) * 2;
            $positions = [6];
            for ($p = $this->size - 7; count($positions) < $n; $p -= $step) {
                array_splice($positions, 1, 0, [$p]);
            }
        }
        $last = count($positions) - 1;
        foreach ($positions as $i => $x) {
            foreach ($positions as $j => $y) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $last) || ($i === $last && $j === 0)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $this->set($x + $dx, $y + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }
        $this->format(0);
        if ($this->version >= 7) {
            $rem = $this->version;
            for ($i = 0; $i < 12; $i++) {
                $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
            }
            $bits = ($this->version << 12) | $rem;
            for ($i = 0; $i < 18; $i++) {
                $a = $this->size - 11 + $i % 3;
                $b = intdiv($i, 3);
                $this->set($a, $b, (($bits >> $i) & 1) !== 0);
                $this->set($b, $a, (($bits >> $i) & 1) !== 0);
            }
        }
    }

    private function format(int $mask): void
    {
        $data = ([1,0,3,2][$this->ecc] << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;
        for ($i = 0; $i < 6; $i++) {
            $this->set(8, $i, (($bits >> $i) & 1) !== 0);
        }
        $this->set(8, 7, (($bits >> 6) & 1) !== 0);
        $this->set(8, 8, (($bits >> 7) & 1) !== 0);
        $this->set(7, 8, (($bits >> 8) & 1) !== 0);
        for ($i = 9; $i < 15; $i++) {
            $this->set(14 - $i, 8, (($bits >> $i) & 1) !== 0);
        }
        for ($i = 0; $i < 8; $i++) {
            $this->set($this->size - 1 - $i, 8, (($bits >> $i) & 1) !== 0);
        }
        for ($i = 8; $i < 15; $i++) {
            $this->set(8, $this->size - 15 + $i, (($bits >> $i) & 1) !== 0);
        }
        $this->set(8, $this->size - 8, true);
    }

    private static function multiply(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z;
    }

    /**
     * @param list<int> $data
     * @return list<int>
     */
    private function interleave(array $data): array
    {
        $n = self::BLOCKS[$this->ecc][$this->version];
        $degree = self::ECC[$this->ecc][$this->version];
        $raw = intdiv(self::rawModules($this->version), 8);
        $short = $n - $raw % $n;
        $length = intdiv($raw, $n);
        $divisor = array_fill(0, $degree, 0);
        $divisor[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $divisor[$j] = self::multiply($divisor[$j], $root);
                if ($j + 1 < $degree) {
                    $divisor[$j] ^= $divisor[$j + 1];
                }
            }
            $root = self::multiply($root, 2);
        }
        $blocks = [];
        $k = 0;
        for ($i = 0; $i < $n; $i++) {
            $block = array_slice($data, $k, $length - $degree + ($i < $short ? 0 : 1));
            $k += count($block);
            $remainder = array_fill(0, $degree, 0);
            foreach ($block as $byte) {
                $factor = $byte ^ array_shift($remainder);
                $remainder[] = 0;
                foreach ($divisor as $j => $coef) {
                    $remainder[$j] ^= self::multiply($coef, $factor);
                }
            }
            if ($i < $short) {
                $block[] = 0;
            }
            $blocks[] = array_merge($block, $remainder);
        }
        $result = [];
        for ($i = 0; $i < count($blocks[0]); $i++) {
            foreach ($blocks as $j => $block) {
                if ($i !== $length - $degree || $j >= $short) {
                    $result[] = $block[$i];
                }
            }
        }
        return $result;
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                $value = [($x + $y) % 2, $y % 2, $x % 3, ($x + $y) % 3,
                    (intdiv($x, 3) + intdiv($y, 2)) % 2, $x * $y % 2 + $x * $y % 3,
                    ($x * $y % 2 + $x * $y % 3) % 2, (($x + $y) % 2 + $x * $y % 3) % 2][$mask];
                if ($value === 0 && !$this->functions[$y][$x]) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    /** @param list<int> $history */
    private function history(int $length, array &$history): void
    {
        if ($history[0] === 0) {
            $length += $this->size;
        }
        array_pop($history);
        array_unshift($history, $length);
    }

    /** @param list<int> $h */
    private function patterns(array $h): int
    {
        $n = $h[1];
        $core = $n > 0 && $h[2] === $n && $h[3] === $n * 3 && $h[4] === $n && $h[5] === $n;
        return (int) ($core && $h[0] >= $n * 4 && $h[6] >= $n)
            + (int) ($core && $h[6] >= $n * 4 && $h[0] >= $n);
    }

    private function penalty(): int
    {
        $result = 0;
        for ($axis = 0; $axis < 2; $axis++) {
            for ($a = 0; $a < $this->size; $a++) {
                $color = false;
                $run = 0;
                $history = array_fill(0, 7, 0);
                for ($b = 0; $b < $this->size; $b++) {
                    $next = $axis === 0 ? $this->modules[$a][$b] : $this->modules[$b][$a];
                    if ($next === $color) {
                        $run++;
                        if ($run === 5) {
                            $result += 3;
                        } elseif ($run > 5) {
                            $result++;
                        }
                    } else {
                        $this->history($run, $history);
                        if (!$color) {
                            $result += $this->patterns($history) * 40;
                        }
                        $color = $next;
                        $run = 1;
                    }
                }
                if ($color) {
                    $this->history($run, $history);
                    $run = 0;
                }
                $this->history($run + $this->size, $history);
                $result += $this->patterns($history) * 40;
            }
        }
        $dark = 0;
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                $dark += (int) $this->modules[$y][$x];
                if ($x + 1 < $this->size && $y + 1 < $this->size
                    && $this->modules[$y][$x] === $this->modules[$y][$x + 1]
                    && $this->modules[$y][$x] === $this->modules[$y + 1][$x]
                    && $this->modules[$y][$x] === $this->modules[$y + 1][$x + 1]) {
                    $result += 3;
                }
            }
        }
        $total = $this->size * $this->size;
        $result += (intdiv(abs($dark * 20 - $total * 10) + $total - 1, $total) - 1) * 10;
        return $result;
    }
}
