<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;

class AssetAlert extends Notification {
    public function __construct(public string $message) {}
    public function via($n): array { return ['database']; }
    public function toArray($n): array { return ['message' => $this->message]; }
}
