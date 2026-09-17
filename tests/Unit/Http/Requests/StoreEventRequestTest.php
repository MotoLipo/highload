<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StoreEventRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StoreEventRequestTest extends TestCase
{
    public function testAuthorizeAlwaysReturnsTrue(): void
    {
        $this->assertTrue((new StoreEventRequest)->authorize());
    }

    #[DataProvider('validPayloads')]
    public function testValidPayloadsPassValidation(array $data): void
    {
        $validator = Validator::make($data, (new StoreEventRequest)->rules());

        $this->assertTrue($validator->passes(), $validator->errors()->__toString());
    }

    public static function validPayloads(): array
    {
        return [
            'page_view with payload' => [['event_type' => 'page_view', 'user_id' => 1, 'payload' => ['url' => '/home']]],
            'purchase with payload' => [['event_type' => 'purchase', 'user_id' => 10, 'payload' => ['amount' => 9.99]]],
            'signup without a payload key' => [['event_type' => 'signup', 'user_id' => 3]],
            'null payload' => [['event_type' => 'signup', 'user_id' => 3, 'payload' => null]],
            'empty array payload' => [['event_type' => 'signup', 'user_id' => 3, 'payload' => []]],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function testInvalidPayloadsFailValidation(array $data, string $expectedInvalidField): void
    {
        $validator = Validator::make($data, (new StoreEventRequest)->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey($expectedInvalidField, $validator->errors()->toArray());
    }

    public static function invalidPayloads(): array
    {
        return [
            'missing event_type' => [['user_id' => 1], 'event_type'],
            'unknown event_type' => [['event_type' => 'unknown', 'user_id' => 1], 'event_type'],
            'non string event_type' => [['event_type' => 123, 'user_id' => 1], 'event_type'],
            'missing user_id' => [['event_type' => 'page_view'], 'user_id'],
            'non integer user_id' => [['event_type' => 'page_view', 'user_id' => 'abc'], 'user_id'],
            'zero user_id' => [['event_type' => 'page_view', 'user_id' => 0], 'user_id'],
            'negative user_id' => [['event_type' => 'page_view', 'user_id' => -5], 'user_id'],
            'payload not an array' => [['event_type' => 'page_view', 'user_id' => 1, 'payload' => 'nope'], 'payload'],
        ];
    }
}
