<?php

namespace Webkul\Paymob\Payment;

use Webkul\Payment\Payment\Payment as BasePayment;

class Paymob extends BasePayment
{
    protected $code = 'paymob';

    /**
     * Required abstract method
     */
    public function getRedirectUrl()
    {
        return route('paymob.redirect');
    }

    /**
     * This method is called automatically by Admin Order View
     */
    public function getAdditionalDetails()
    {
        /**
         * Bagisto injects payment model into $this->payment
         */
        $payment = $this->payment ?? null;

        if (! $payment) {
            return [];
        }

        $additional = $payment->additional ?? [];

        // Normalize additional (JSON | array | object)
        if (is_string($additional)) {
            $decoded = json_decode($additional, true);
            $additional = is_array($decoded) ? $decoded : [];
        } elseif (is_object($additional)) {
            $additional = (array) $additional;
        } elseif (! is_array($additional)) {
            $additional = [];
        }

        $txId = $additional['paymob_transaction_id']
            ?? $additional['transaction_id']
            ?? null;

        if (! $txId) {
            return [];
        }

        return [
            'title' => __('Paymob Transaction ID'),
            'value' => $txId,
        ];
    }
}
