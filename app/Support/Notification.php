<?php

namespace App\Support;

use Joli\JoliNotif\DefaultNotifier;
use Joli\JoliNotif\Notification as JoliNotifNotification;

class Notification
{
    public static function send(string $title, string $body): void
    {
        $notifier = new DefaultNotifier;

        $notification = (new JoliNotifNotification)
            ->setTitle($title)
            ->setBody($body)
            ->addOption('sound', 'Glass');

        $notifier->send($notification);
    }
}
