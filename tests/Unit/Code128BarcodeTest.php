<?php

namespace Tests\Unit;

use App\Support\Code128Barcode;
use PHPUnit\Framework\TestCase;

class Code128BarcodeTest extends TestCase
{
    public function test_it_renders_svg_bars_for_printing(): void
    {
        $svg = Code128Barcode::svg('COU000123');

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('<rect ', $svg);
        $this->assertStringContainsString('fill="#000"', $svg);
        $this->assertStringContainsString('Barcode COU000123', $svg);
        $this->assertStringNotContainsString('background', $svg);
    }

    public function test_it_removes_non_printable_characters(): void
    {
        $svg = Code128Barcode::svg("COU\n000123");

        $this->assertStringContainsString('Barcode COU000123', $svg);
    }
}
