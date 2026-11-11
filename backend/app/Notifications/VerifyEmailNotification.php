<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The mail sent after registering: a signed link and a code, either one confirms the address. */
class VerifyEmailNotification extends Notification
{
    public function __construct(private string $url, private int $minutes, private string $code, private int $codeMinutes) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = config('app.brand_name', 'Passdi');

        return (new MailMessage)
            ->subject("Xác minh thư điện tử cho tài khoản {$brand}")
            ->greeting('Xin chào '.$notifiable->name.',')
            ->line("Bạn vừa tạo tài khoản {$brand} bằng địa chỉ thư điện tử này. Hãy bấm nút bên dưới để xác minh, sau đó bạn có thể đăng nhập.")
            ->action('Xác minh thư điện tử', $this->url)
            ->line("Liên kết này hết hạn sau {$this->minutes} phút.")
            ->line('Hoặc nhập mã này ở trang xác minh:')
            ->line('**'.$this->code.'**')
            ->line("Mã hết hạn sau {$this->codeMinutes} phút. Đừng đưa mã này cho ai. Khi hết hạn, bạn có thể yêu cầu gửi lại ở trang xác minh.")
            ->line('Nếu không phải bạn tạo tài khoản, hãy bỏ qua thư này.')
            ->salutation("Trân trọng,\n{$brand}");
    }
}
