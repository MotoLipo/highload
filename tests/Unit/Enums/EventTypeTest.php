<?php

namespace Tests\Unit\Enums;

use App\Enums\EventType;
use PHPUnit\Framework\TestCase;
use ValueError;

class EventTypeTest extends TestCase
{
    public function testItHasTheExpectedCasesAndValues(): void
    {
        $this->assertSame('page_view', EventType::PageView->value);
        $this->assertSame('purchase', EventType::Purchase->value);
        $this->assertSame('signup', EventType::SignUp->value);
    }

    public function testItExposesExactlyThreeCases(): void
    {
        $this->assertCount(3, EventType::cases());
    }

    public function testFromResolvesACaseByItsValue(): void
    {
        $this->assertSame(EventType::PageView, EventType::from('page_view'));
        $this->assertSame(EventType::Purchase, EventType::from('purchase'));
        $this->assertSame(EventType::SignUp, EventType::from('signup'));
    }

    public function testTryFromReturnsNullForAnUnknownValue(): void
    {
        $this->assertNull(EventType::tryFrom('unknown'));
    }

    public function testFromThrowsForAnUnknownValue(): void
    {
        $this->expectException(ValueError::class);

        EventType::from('unknown');
    }
}
