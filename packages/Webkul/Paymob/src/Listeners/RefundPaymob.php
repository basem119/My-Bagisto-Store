<?php

namespace Webkul\Paymob\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class RefundPaymob
{
    /**
     * يتم استدعاؤه تلقائيًا بعد إنشاء Refund
     * Event: sales.refund.save.after
     */
    public function handle($refund): void
    {
        $debugMode = (bool) core()->getConfigData('sales.payment_methods.paymob.debug_mode');
        
        try {
            // 1️⃣ الحصول على الطلب
            $order   = $refund->order ?? null;
            $orderId = $order->id ?? $refund->order_id ?? null;

            if (! $orderId) {
                $this->log('RefundPaymob: missing order id on refund', [], $debugMode);
                return;
            }

            // 2️⃣ قراءة بيانات الدفع
            $paymentRow = DB::table('order_payment')->where('order_id', $orderId)->first();

            if (! $paymentRow) {
                $this->log('RefundPaymob: order_payment not found', [
                    'order_id' => $orderId,
                ], $debugMode);
                return;
            }

            // 3️⃣ فك additional
            $additional = $paymentRow->additional ?? [];

            if (is_string($additional) && $additional !== '') {
                $decoded = json_decode($additional, true);
                $additional = is_array($decoded) ? $decoded : [];
            } elseif (! is_array($additional)) {
                $additional = [];
            }

            $txId = $additional['paymob_transaction_id'] ?? null;

            if (! $txId) {
                $this->log('RefundPaymob: missing paymob_transaction_id', [
                    'order_id' => $orderId,
                    'additional' => $additional,
                ], $debugMode);
                return;
            }

            // 4️⃣ حساب مبلغ الريفند بالسنت
            $currency = strtoupper($order->order_currency_code ?? 'EGP');
            $factor   = ($currency === 'OMR') ? 1000 : 100;

            $amount      = (float) ($refund->grand_total ?? 0);
            $amountCents = (int) round($amount * $factor);

            if ($amountCents <= 0) {
                $this->log('RefundPaymob: non-positive amount_cents', [
                    'order_id'     => $orderId,
                    'grand_total'  => $refund->grand_total ?? null,
                    'currency'     => $currency,
                ], $debugMode);
                return;
            }

            // 5️⃣ مفتاح Paymob Secret
            $secretKey = core()->getConfigData('sales.payment_methods.paymob.secret_key');

            if (! $secretKey) {
                $this->log('RefundPaymob: missing secret key', [], $debugMode, 'error');
                return;
            }

            // 6️⃣ تنفيذ Refund عبر Paymob API
            $payload = [
                'transaction_id' => (string) $txId,
                'amount_cents'   => $amountCents,
            ];

            $this->log('RefundPaymob: attempting refund', [
                'order_id' => $orderId,
                'payload'  => $payload,
            ], $debugMode);

            $response = Http::acceptJson()
                ->withHeaders([
                    'Authorization' => 'Token ' . $secretKey,
                ])
                ->post('https://accept.paymob.com/api/acceptance/void_refund/refund', $payload);

            $refundResponse = $response->json();

            $this->log('RefundPaymob: API response', [
                'order_id' => $orderId,
                'status'   => $response->status(),
                'body'     => $refundResponse,
            ], $debugMode);

            if (! $response->successful()) {
                $this->log('RefundPaymob: API failed', [
                    'order_id' => $orderId,
                    'status'   => $response->status(),
                    'response' => $response->body(),
                ], $debugMode, 'error');
                return;
            }

            // 7️⃣ تخزين رد Paymob داخل additional
            $additional['paymob_refund_last_response'] = $refundResponse;

            DB::table('order_payment')
                ->where('order_id', $orderId)
                ->update([
                    'additional' => json_encode($additional, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);

            $this->log('RefundPaymob: Success', [
                'order_id'       => $orderId,
                'transaction_id' => $txId,
                'amount_cents'   => $amountCents,
                'response'       => $refundResponse,
            ], $debugMode);

        } catch (\Throwable $e) {
            $this->log('RefundPaymob: exception', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], $debugMode, 'error');
        }
    }

    /**
     * تسجيل مركزي للـ Debug
     */
    private function log(string $message, array $context = [], bool $debugMode = false, string $level = 'info'): void
    {
        if ($debugMode) {
            // تسجيل تفصيلي في ملف مخصص
            $logFile = storage_path('logs/paymob.log');
            $timestamp = now()->format('Y-m-d H:i:s');
            $contextStr = ! empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '';
            
            $logMessage = "[{$timestamp}] {$level}: {$message}";
            if ($contextStr) {
                $logMessage .= "\n" . $contextStr;
            }
            $logMessage .= "\n" . str_repeat('-', 80) . "\n";
            
            file_put_contents($logFile, $logMessage, FILE_APPEND);
        }

        // تسجيل عام في laravel.log
        if ($level === 'error') {
            Log::error($message, $context);
        } else {
            Log::info($message, $context);
        }
    }
}