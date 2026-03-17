<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Payment;

class PaymentPolicy
{
    /**
     * Incoming payment → only the recipient (remote_id) can pay/deny.
     */
    public function pay(User $user, Payment $payment): bool
    {   
        // dd($payment);
        
        return $payment->direction === 'incoming'
            && $payment->status === 'pending'
            && $payment->remote?->user_id !== $user->id;
    }

    public function deny(User $user, Payment $payment): bool
    {
        return $payment->direction === 'incoming'
            && $payment->status === 'pending'
            && $payment->remote?->user_id !== $user->id;        
    }

    /**
     * Outgoing payment → only the sender (user_id) can update/delete.
     */
    public function update(User $user, Payment $payment): bool
    {
        return $payment->direction === 'outgoing'
            && $user->id === $payment->user_id 
            && ($payment->status === 'pending' || $payment->status === 'denied');
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $payment->direction === 'outgoing'
            && $user->id === $payment->user_id 
            && ($payment->status === 'pending' || $payment->status === 'denied');
    }
    
}
