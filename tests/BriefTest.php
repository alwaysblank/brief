<?php
declare(strict_types=1);

use AlwaysBlank\Brief\Brief;
use AlwaysBlank\Brief\Exceptions\CannotSetProtectedKeyException;
use PHPUnit\Framework\TestCase;

final class BriefTest extends TestCase
{
    public function testCanBeCorrectlyInstantiated(): void
    {
        $this->assertInstanceOf(
            Brief::class,
            Brief::make([])
        );
    }

    public function testReturnsSameBriefIfGivenBriefAsArgument(): void
    {
        $Tester = Brief::make(['key' => 'value']);
        $this->assertEquals(
            $Tester,
            Brief::make($Tester)
        );
    }

    public function testAttemptingToUseProtectedKeysThrowsCorrectException(): void
    {
        $this->expectException(CannotSetProtectedKeyException::class);
        Brief::make(['protected' => 'value']);
    }
}
