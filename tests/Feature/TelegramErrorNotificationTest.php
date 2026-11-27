<?php

namespace Tests\Feature;

use App\Exceptions\DomainRuleException;
use App\Notifications\ErrorNotification;
use App\Notifications\TelegramNotifiable;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use RuntimeException;
use Tests\TestCase;

class TelegramErrorNotificationTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->app['env'] = 'testing';
        parent::tearDown();
    }

    public function test_services_config_contains_telegram_bot_api_keys(): void
    {
        $config = config('services.telegram-bot-api');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('token', $config);
        $this->assertArrayHasKey('chat_id', $config);
    }

    public function test_telegram_notifiable_routes_to_configured_chat_id(): void
    {
        config(['services.telegram-bot-api.chat_id' => '123456789']);

        $notifiable = new TelegramNotifiable();

        $this->assertSame('123456789', $notifiable->routeNotificationForTelegram());
    }

    public function test_error_notification_formats_telegram_message(): void
    {
        config(['services.telegram-bot-api.chat_id' => '987654321']);

        $request = \Illuminate\Http\Request::create(
            'https://api.xvnatalia.com/api/memories',
            'POST',
            [],
            [],
            [],
            [
                'REMOTE_ADDR' => '190.120.10.5',
                'HTTP_USER_AGENT' => 'Mozilla/5.0 TestAgent',
                'HTTP_X_DEVICE_ID' => 'dev-abc-123',
                'HTTP_X_ACTOR_KEY' => 'actor-xyz',
                'HTTP_X_EVENT_CODE' => 'XV-NATALIA',
            ]
        );
        $this->app->instance('request', $request);

        $longMessage = str_repeat('a', 450);
        $exception = $this->createTestException($longMessage);
        $notification = new ErrorNotification($exception);

        $this->assertSame([TelegramChannel::class], $notification->via(new TelegramNotifiable()));

        $telegramMessage = $notification->toTelegram(new TelegramNotifiable());
        $payload = $telegramMessage->toArray();

        $this->assertSame('987654321', $payload['chat_id']);
        $this->assertStringContainsString('Error en producción — xv-natalia', $payload['text']);
        $this->assertStringContainsString(RuntimeException::class, $payload['text']);
        $this->assertStringContainsString("```\n" . str_repeat('a', 400) . "\n```", $payload['text']);
        $this->assertStringNotContainsString(str_repeat('a', 401), $payload['text']);
        $this->assertStringContainsString('tests/Feature/TelegramErrorNotificationTest.php:', $payload['text']);
        $this->assertStringContainsString('createTestException()', $payload['text']);
        $this->assertStringContainsString('POST https://api.xvnatalia.com/api/memories', $payload['text']);
        $this->assertStringContainsString('IP: `190.120.10.5`', $payload['text']);
        $this->assertStringContainsString('Device: `dev-abc-123`', $payload['text']);
        $this->assertStringContainsString('Actor: `actor-xyz`', $payload['text']);
        $this->assertStringContainsString('Evento: `XV-NATALIA`', $payload['text']);
        $this->assertStringContainsString('UA: `Mozilla/5.0 TestAgent`', $payload['text']);
        $this->assertStringContainsString("🔍 *Stack trace (app):*\n```\n", $payload['text']);
    }

    public function test_error_notification_reads_resolved_request_attributes_and_handles_console(): void
    {
        config(['services.telegram-bot-api.chat_id' => '987654321']);

        $requestWithAttributes = \Illuminate\Http\Request::create('https://api.xvnatalia.com/api/feed', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'MobileApp/1.0',
        ]);
        $requestWithAttributes->attributes->set('deviceId', 'attr-dev-99');
        $requestWithAttributes->attributes->set('actorKey', 'attr-actor-77');
        $requestWithAttributes->attributes->set('eventCode', 'XV-ATTR');
        $this->app->instance('request', $requestWithAttributes);

        $payload = (new ErrorNotification($this->createTestException('Attr failure')))
            ->toTelegram(new TelegramNotifiable())
            ->toArray();

        $this->assertStringContainsString('Device: `attr-dev-99`', $payload['text']);
        $this->assertStringContainsString('Actor: `attr-actor-77`', $payload['text']);
        $this->assertStringContainsString('Evento: `XV-ATTR`', $payload['text']);

        // Synthetic console request (no device attributes/headers, Symfony/null UA)
        $consoleRequest = \Illuminate\Http\Request::create('http://localhost', 'GET');
        $this->app->instance('request', $consoleRequest);

        $consolePayload = (new ErrorNotification($this->createTestException('Job failure')))
            ->toTelegram(new TelegramNotifiable())
            ->toArray();

        $this->assertStringContainsString('⚙️ *Ejecución:*', $consolePayload['text']);
        $this->assertStringNotContainsString('🌐 *Request:*', $consolePayload['text']);
    }

    public function test_unhandled_exception_does_not_notify_outside_production(): void
    {
        Notification::fake();

        $this->app->make(ExceptionHandler::class)->report(new RuntimeException('Local failure'));

        Notification::assertNothingSent();
    }

    public function test_unhandled_exception_notifies_in_production(): void
    {
        Notification::fake();
        config([
            'services.telegram-bot-api.token' => 'test-token',
            'services.telegram-bot-api.chat_id' => '123456',
        ]);
        $this->app['env'] = 'production';

        $this->app->make(ExceptionHandler::class)->report(new RuntimeException('Production boom'));

        Notification::assertSentTimes(ErrorNotification::class, 1);
    }

    public function test_notifier_failure_is_silenced_in_production(): void
    {
        $this->app->instance(\NotificationChannels\Telegram\TelegramChannel::class, new class {
            public function send(): void
            {
                throw new RuntimeException('Telegram API unreachable');
            }
        });
        $this->app['env'] = 'production';

        $this->app->make(ExceptionHandler::class)->report(new RuntimeException('Production boom'));

        $this->assertTrue(true);
    }

    public function test_domain_rule_exception_is_not_reported_in_production(): void
    {
        Notification::fake();
        $this->app['env'] = 'production';

        $this->app->make(ExceptionHandler::class)->report(
            new DomainRuleException('RULE_VIOLATION', 'Domain rule failed')
        );

        Notification::assertNothingSent();
    }

    private function createTestException(string $message): RuntimeException
    {
        return new RuntimeException($message);
    }
}
