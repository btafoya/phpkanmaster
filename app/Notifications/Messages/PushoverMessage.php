<?php

namespace App\Notifications\Messages;

class PushoverMessage
{
    public string $message;
    public int $priority = 0;
    public ?int $retry = null;
    public ?int $expire = null;
    public ?string $title = null;
    public ?string $url = null;
    public ?string $urlTitle = null;
    public ?string $sound = null;

    public static function create(string $message): self
    {
        return (new self)->message($message);
    }

    public function message(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function retry(int $seconds): self
    {
        $this->retry = $seconds;

        return $this;
    }

    public function expire(int $seconds): self
    {
        $this->expire = $seconds;

        return $this;
    }

    public function url(string $url, ?string $title = null): self
    {
        $this->url = $url;
        $this->urlTitle = $title;

        return $this;
    }

    public function sound(string $sound): self
    {
        $this->sound = $sound;

        return $this;
    }

    public function toArray(): array
    {
        return array_filter([
            'message'  => $this->message,
            'title'    => $this->title,
            'priority' => $this->priority,
            'retry'    => $this->retry,
            'expire'   => $this->expire,
            'url'      => $this->url,
            'url_title' => $this->urlTitle,
            'sound'    => $this->sound,
        ], fn($value) => $value !== null);
    }
}