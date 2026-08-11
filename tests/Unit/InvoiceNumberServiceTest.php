<?php

namespace Tests\Unit;

use App\Services\InvoiceNumberService;
use PHPUnit\Framework\TestCase;

class InvoiceNumberServiceTest extends TestCase
{
    private InvoiceNumberService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new InvoiceNumberService;
    }

    public function test_generates_formatted_invoice_number(): void
    {
        $date = new \DateTimeImmutable('2026-08-11');

        $number = $this->service->generate('inv', 42, $date);

        $this->assertSame('INV-2026/08-000042', $number);
    }

    public function test_normalizes_lowercase_prefix(): void
    {
        $date = new \DateTimeImmutable('2026-08-11');

        $this->assertSame('INV-2026/08-000001', $this->service->generate('inv', 1, $date));
    }

    public function test_parses_valid_number(): void
    {
        $parsed = $this->service->parse('INV-2026/08-000042');

        $this->assertSame('INV', $parsed['prefix']);
        $this->assertSame(2026, $parsed['year']);
        $this->assertSame(8, $parsed['month']);
        $this->assertSame(42, $parsed['sequence']);
    }

    public function test_returns_null_for_invalid_number(): void
    {
        $this->assertNull($this->service->parse('not-an-invoice'));
    }

    public function test_rejects_invalid_sequence(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->generate('INV', 0);
    }
}
