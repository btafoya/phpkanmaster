<?php

namespace App\Notifications\Messages;

class TwilioMessage
{
    public string $content;
    public ?string $from = null;
    public ?string $to = null;

    public static function create(string $content): self
    {
        return (new self)->content($content);
    }

    public function content(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function from(string $from): self
    {
        $this->from = $from;

        return $this;
    }

    public function to(string $to): self
    {
        $this->to = $to;

        return $this;
    }
}