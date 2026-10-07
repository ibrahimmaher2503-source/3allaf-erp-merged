<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class PureCalculatorTestCase extends PHPUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        qa_reset_pure_calculator_container();
    }
}
