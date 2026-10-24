<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    /**
     * Arabic reset email matching the rest of the interface.
     */
    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $expiresIn = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('إعادة تعيين كلمة المرور | Bio Page')
            ->greeting('مرحبًا '.$notifiable->name.'!')
            ->line('وصلنا طلب لإعادة تعيين كلمة مرور حسابك في Bio Page.')
            ->action('تعيين كلمة مرور جديدة', $url)
            ->line('ينتهي هذا الرابط خلال '.$expiresIn.' دقيقة.')
            ->line('إذا لم تطلبي ذلك، تجاهلي هذه الرسالة ولن يتغير شيء.');
    }
}
