<?php

namespace App\Notifications;

use Illuminate\Notifications\Notifiable;

class TelegramNotifiable
{
    use Notifiable;

    public function routeNotificationForTelegram(): int|string|null
    {
        return config('services.telegram-bot-api.chat_id');
    }

    public function getKey(): string
    {
        return 'telegram';
    }
}
