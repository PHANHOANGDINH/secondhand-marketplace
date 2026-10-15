<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The mail of "Quên mật khẩu": a one-time temporary password. The value only exists in this mail. */
class TemporaryPasswordNotification extends Notification
{
    public function __construct(private string $password, private int $minutes) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = config('app.brand_name', 'pass lại');

        return (new MailMessage)
            ->subject("Mật khẩu tạm thời cho tài khoản {$brand}")
            ->greeting('Xin chào '.$notifiable->name.',')
            ->line("Bạn (hoặc ai đó) vừa yêu cầu lấy lại mật khẩu tài khoản {$brand}. Đây là mật khẩu tạm thời của bạn:")
            ->line('**'.$this->password.'**')
            ->line("Mật khẩu này chỉ dùng được một lần và hết hạn sau {$this->minutes} phút.")
            ->line('Sau khi đăng nhập, hệ thống sẽ yêu cầu bạn đặt mật khẩu mới ngay.')
            ->action('Đăng nhập', rtrim((string) config('auth.frontend_login_url'), '/'))
            ->line('Nếu không phải bạn yêu cầu, hãy bỏ qua thư này: mật khẩu hiện tại của bạn vẫn dùng được bình thường.')
            ->salutation("Trân trọng,\n{$brand}");
    }
}
