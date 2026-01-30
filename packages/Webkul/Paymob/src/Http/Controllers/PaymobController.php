<?php

namespace Webkul\Paymob\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

use Webkul\Checkout\Facades\Cart;
use Webkul\Checkout\Repositories\CartRepository;
use Webkul\Sales\Transformers\OrderResource;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\InvoiceRepository;

class PaymobController extends Controller
{
    protected OrderRepository $orderRepository;
    protected InvoiceRepository $invoiceRepository;
    protected CartRepository $cartRepository;
    protected bool $debugMode;

    public function __construct(
        OrderRepository $orderRepository,
        InvoiceRepository $invoiceRepository,
        CartRepository $cartRepository
    ) {
        $this->orderRepository   = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->cartRepository    = $cartRepository;
        $this->debugMode         = (bool) core()->getConfigData('sales.payment_methods.paymob.debug_mode');
    }

    /**
     * Redirect to Paymob Unified Checkout
     */
    public function redirect()
    {
        $this->debugLog('=== Paymob Redirect Started ===');
        
        $cart = Cart::getCart();

        if (! $cart || ! $cart->items()->count()) {
            $this->debugLog('Cart is empty or not found', ['cart_id' => $cart->id ?? 'null']);
            return redirect()->route('shop.checkout.cart.index');
        }

        $publicKey      = core()->getConfigData('sales.payment_methods.paymob.public_key');
        $secretKey      = core()->getConfigData('sales.payment_methods.paymob.secret_key');
        $integrationIds = core()->getConfigData('sales.payment_methods.paymob.integration_ids');

        if (! $publicKey || ! $secretKey || ! $integrationIds) {
            $this->debugLog('Missing configuration', [
                'has_public_key' => !empty($publicKey),
                'has_secret_key' => !empty($secretKey),
                'has_integration_ids' => !empty($integrationIds),
            ]);
            return redirect()->route('shop.checkout.cart.index')
                ->with('error', 'Paymob configuration is incomplete.');
        }

        $paymentMethods = array_values(
            array_filter(array_map('intval', explode(',', (string) $integrationIds)))
        );

        $amountCents = (int) round(((float) $cart->grand_total) * 100);

        $billing = $cart->billing_address;

        $billingData = [
            'apartment'        => 'N/A',
            'email'            => $billing->email ?? 'customer@example.com',
            'floor'            => 'N/A',
            'first_name'       => $billing->first_name ?? 'Customer',
            'last_name'        => $billing->last_name ?? 'Name',
            'phone_number'     => $billing->phone ?? '01000000000',
            'street'           => implode(' ', (array) ($billing->address ?? [])),
            'building'         => 'N/A',
            'shipping_method'  => 'PKG',
            'postal_code'      => $billing->postcode ?? '00000',
            'city'             => $billing->city ?? 'Cairo',
            'country'          => $billing->country ?? 'EG',
            'state'            => $billing->state ?? 'Cairo',
        ];

        $intentionPayload = [
            'amount'            => $amountCents,
            'currency'          => 'EGP',
            'merchant_order_id' => (string) $cart->id,
            'return_url'        => route('paymob.callback'),
            'payment_methods'   => $paymentMethods,
            'billing_data'      => $billingData,
            'items'             => [],
        ];

        $this->debugLog('Creating Paymob intention', [
            'cart_id'  => $cart->id,
            'amount'   => $amountCents,
            'payload'  => $intentionPayload,
        ]);

        try {
            $res = Http::acceptJson()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $secretKey,
                ])
                ->post('https://accept.paymob.com/v1/intention/', $intentionPayload)
                ->throw()
                ->json();

            $this->debugLog('Paymob intention response', [
                'response' => $res,
            ]);

            $clientSecret = $res['client_secret'] ?? $res['cs'] ?? null;

            if (! $clientSecret) {
                throw new \RuntimeException('Missing client secret in response');
            }

            session([
                'paymob_cart_id'      => $cart->id,
                'paymob_cents_amount' => $amountCents,
            ]);

            $url =
                'https://accept.paymob.com/unifiedcheckout/?publicKey='
                . urlencode($publicKey)
                . '&clientSecret='
                . urlencode($clientSecret);

            $this->debugLog('Redirecting to Paymob', ['url' => $url]);

            return redirect()->away($url);

        } catch (\Throwable $e) {
            $this->debugLog('Paymob redirect error', [
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 'error');
            
            return redirect()->route('shop.checkout.cart.index')
                ->with('error', 'Payment initialization failed.');
        }
    }

    /**
     * Callback
     */
    public function callback(Request $request)
    {
        $this->debugLog('=== Paymob Callback Received ===', [
            'query_params' => $request->query(),
            'all_params'   => $request->all(),
        ]);

        $success = filter_var($request->query('success'), FILTER_VALIDATE_BOOLEAN);

        if (! $success) {
            $this->debugLog('Payment failed - success=false');
            return redirect()->route('shop.checkout.cart.index')
                ->with('error', 'Payment failed.');
        }

        $cartId = session()->pull('paymob_cart_id');
        $cart   = $cartId ? $this->cartRepository->find($cartId) : null;

        if (! $cart || ! $cart->items()->count()) {
            $this->debugLog('Cart not found in callback', ['cart_id' => $cartId]);
            return redirect()->route('shop.checkout.cart.index')
                ->with('error', 'Cart not found.');
        }

        try {
            DB::beginTransaction();

            $this->debugLog('Creating order from cart', ['cart_id' => $cart->id]);

            $orderPayload = (new OrderResource($cart))->jsonSerialize();
            $orderPayload['payment']['method'] = 'paymob';

            $order = $this->orderRepository->create($orderPayload);

            $this->debugLog('Order created', ['order_id' => $order->id]);

            $additional = [
                'paymob_transaction_id' => $request->query('id'),
                'paymob_amount_cents'   => session('paymob_cents_amount'),
            ];

            DB::table('order_payment')->updateOrInsert(
                ['order_id' => $order->id],
                [
                    'method'       => 'paymob',
                    'method_title' => 'Paymob',
                    'additional'   => json_encode($additional, JSON_UNESCAPED_UNICODE),
                    'updated_at'   => now(),
                ]
            );

            $this->debugLog('Payment record updated', [
                'order_id'   => $order->id,
                'additional' => $additional,
            ]);

            Cart::deActivateCart();

            $invoiceItems = [];
            foreach ($order->items as $item) {
                if ($item->qty_to_invoice > 0) {
                    $invoiceItems[$item->id] = $item->qty_to_invoice;
                }
            }

            if ($order->canInvoice() && ! empty($invoiceItems)) {
                $this->invoiceRepository->create([
                    'order_id' => $order->id,
                    'invoice'  => ['items' => $invoiceItems],
                ]);
                $this->debugLog('Invoice created', ['order_id' => $order->id]);
            }

            DB::commit();

            session()->flash('order_id', $order->id);

            $this->debugLog('=== Order completed successfully ===', ['order_id' => $order->id]);

            return redirect()->route('shop.checkout.onepage.success');

        } catch (\Throwable $e) {
            DB::rollBack();

            $this->debugLog('Callback error', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ], 'error');

            return redirect()->route('shop.checkout.cart.index')
                ->with('error', 'Could not create order.');
        }
    }

    /**
     * Debug logging helper
     */
    private function debugLog(string $message, array $context = [], string $level = 'info'): void
    {
        if ($this->debugMode) {
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

        if ($level === 'error') {
            Log::error('Paymob: ' . $message, $context);
        } else {
            Log::info('Paymob: ' . $message, $context);
        }
    }
}