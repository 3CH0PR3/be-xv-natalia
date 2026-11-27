<?php

namespace App\Notifications;

use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;
use Throwable;

class ErrorNotification extends Notification
{
    public function __construct(
        private readonly Throwable $exception,
        private readonly string $context = 'xv-natalia'
    ) {}

    public function via(mixed $notifiable): array
    {
        return [TelegramChannel::class];
    }

    public function toTelegram(mixed $notifiable): TelegramMessage
    {
        $e       = $this->exception;
        $request = request();
        $time    = now()->setTimezone('America/Caracas')->format('d/m/Y H:i:s');

        $basePath = str_replace('\\', '/', base_path()) . '/';
        $isVendor = static fn (string $path): bool => str_contains(str_replace('\\', '/', $path), '/vendor/');
        $shorten  = static fn (string $path): string => str_replace($basePath, '', str_replace('\\', '/', $path));

        // Ubicación real — priorizar el archivo donde se lanzó si no es vendor; si es vendor, buscar primer frame de la app
        $trace = $e->getTrace();

        if (! $isVendor($e->getFile())) {
            $file     = $e->getFile();
            $line     = $e->getLine();
            $topFrame = $trace[0] ?? [];
            $function = isset($topFrame['class'])
                ? "{$topFrame['class']}::{$topFrame['function']}()"
                : ($topFrame['function'] ?? '?');
        } else {
            $origin = collect($trace)
                ->first(fn (array $f): bool => isset($f['file']) && ! $isVendor($f['file']));

            $file     = $origin ? $origin['file'] : $e->getFile();
            $line     = $origin ? $origin['line'] : $e->getLine();
            $function = isset($origin['class'])
                ? "{$origin['class']}::{$origin['function']}()"
                : ($origin['function'] ?? '?');
        }

        $shortFile = $shorten($file);

        // Stack trace — solo frames del app (no vendor)
        $appTrace = collect($trace)
            ->filter(fn (array $f): bool => isset($f['file']) && ! $isVendor($f['file']))
            ->take(5)
            ->map(fn (array $f): string => '  ' . $shorten($f['file']) . ':' . ($f['line'] ?? '?'))
            ->implode("\n");

        // Mensaje de error recortado (salto de línea en ``` evita que Telegram tome la 1ra palabra como lenguaje)
        $message  = str_replace('```', "'''", mb_substr($e->getMessage(), 0, 400));
        $appTrace = str_replace('```', "'''", $appTrace);

        $lines = [
            "🚨 *Error en producción — {$this->context}*",
            '',
            "🕐 *Hora:* `{$time}`",
            '❌ *Tipo:* `' . get_class($e) . '`',
            '💬 *Mensaje:*',
            "```\n{$message}\n```",
            '',
            '📍 *Origen:*',
            "`{$shortFile}:{$line}`",
            "↳ `{$function}`",
        ];

        $isSyntheticConsoleRequest = app()->runningInConsole()
            && in_array($request->userAgent(), [null, 'Symfony'], true)
            && $request->header('X-Device-Id') === null
            && $request->attributes->get('deviceId') === null;

        if ($isSyntheticConsoleRequest) {
            $command = implode(' ', $_SERVER['argv'] ?? []);
            $lines[] = '';
            $lines[] = '⚙️ *Ejecución:*';
            $lines[] = 'Consola / Cola (`' . ($command !== '' ? mb_substr($command, 0, 150) : 'artisan') . '`)';
        } else {
            $method    = $request->method();
            $url       = $request->fullUrl();
            $ip        = $request->ip() ?? '—';
            $deviceId  = $this->resolveContextValue($request, 'deviceId', 'X-Device-Id');
            $actorKey  = $this->resolveContextValue($request, 'actorKey', 'X-Actor-Key');
            $eventCode = $this->resolveContextValue($request, 'eventCode', 'X-Event-Code');
            $userAgent = mb_substr($request->userAgent() ?? '—', 0, 80);

            $lines[] = '';
            $lines[] = '🌐 *Request:*';
            $lines[] = "`{$method} {$url}`";
            $lines[] = "IP: `{$ip}`";
            $lines[] = '';
            $lines[] = '👤 *Contexto del usuario:*';
            $lines[] = "Device: `{$deviceId}`";
            $lines[] = "Actor: `{$actorKey}`";
            $lines[] = "Evento: `{$eventCode}`";
            $lines[] = "UA: `{$userAgent}`";
        }

        if ($appTrace !== '') {
            $lines[] = '';
            $lines[] = '🔍 *Stack trace (app):*';
            $lines[] = "```\n{$appTrace}\n```";
        }

        return TelegramMessage::create()
            ->to(config('services.telegram-bot-api.chat_id'))
            ->content(implode("\n", $lines));
    }

    private function resolveContextValue(Request $request, string $attribute, string $header): string
    {
        $attr = $request->attributes->get($attribute);
        if (is_string($attr) && $attr !== '') {
            return $attr;
        }

        $headerValue = $request->header($header);
        if (is_string($headerValue) && $headerValue !== '') {
            return $headerValue;
        }

        return '—';
    }
}
