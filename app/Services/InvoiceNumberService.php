<?php

namespace App\Services;

class InvoiceNumberService
{
    public const Prefix = 'INV';

    public const Separator = '-';

    public const YearSeparator = '/';

    public const SequenceLength = 6;

    public const MaxSequence = 999999;

    public function generate(string $prefix, int $sequence, ?\DateTimeInterface $date = null): string
    {
        $prefix = $this->normalizePrefix($prefix);
        $sequence = $this->validateSequence($sequence);

        $date = $date ?? now();

        return implode(self::Separator, [
            $prefix,
            $date->format('Y').self::YearSeparator.$date->format('m'),
            str_pad((string) $sequence, self::SequenceLength, '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * @return array{prefix:string, year:int, month:int, sequence:int}|null
     */
    public function parse(string $number): ?array
    {
        $pattern = '/^([A-Z0-9]+)-(\d{4})\/(\d{2})-(\d{6})$/';

        if (! preg_match($pattern, $number, $matches)) {
            return null;
        }

        return [
            'prefix' => $matches[1],
            'year' => (int) $matches[2],
            'month' => (int) $matches[3],
            'sequence' => (int) $matches[4],
        ];
    }

    private function normalizePrefix(string $prefix): string
    {
        $normalized = strtoupper(trim($prefix));

        if ($normalized === '' || ! preg_match('/^[A-Z0-9]+$/', $normalized)) {
            throw new \InvalidArgumentException('Prefix nomor invoice tidak valid.');
        }

        return $normalized;
    }

    private function validateSequence(int $sequence): int
    {
        if ($sequence < 1 || $sequence > self::MaxSequence) {
            throw new \InvalidArgumentException('Nomor urut harus antara 1 dan '.self::MaxSequence.'.');
        }

        return $sequence;
    }
}
