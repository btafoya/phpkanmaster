<?php

namespace App\Notifications\Messages;

class RocketChatMessage
{
    public string $text;
    public ?string $channel = null;

    public static function create(string $text): self
    {
        return (new self)->text($text);
    }

    public function text(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function channel(string $channel): self
    {
        $this->channel = $channel;

        return $this;
    }

    public function toArray(): array
    {
        return array_filter([
            'text'    => $this->text,
            'channel' => $this->channel,
        ], fn($value) => $value !== null);
    }
}