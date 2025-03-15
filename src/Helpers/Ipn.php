<?php

namespace Midnight\MercadoPago\Helpers;

use Illuminate\Support\Facades\Log;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\InvoiceRepository;

class Ipn
{
    /**
     * Create a new helper instance.
     *
     * @return void
     */
    public function __construct(
        protected OrderRepository $orderRepository,
        protected InvoiceRepository $invoiceRepository
    ) {}

    /**
     * Process IPN.
     *
     * @param  array  $request
     * @return void
     */
    public function processIpn($request)
    {
        try {
            if (isset($request['type']) && $request['type'] == 'payment') {
                $paymentId = $request['data']['id'];

                // Initialize the SDK
                MercadoPagoConfig::setAccessToken(core()->getConfigData('sales.payment_methods.mercadopago_standard.access_token'));

                // Get the payment information
                $client = new PaymentClient();
                $payment = $client->get($paymentId);

                if ($payment) {
                    $orderId = $payment->external_reference;
                    $order = $this->orderRepository->find($orderId);

                    if ($order) {
                        // Process payment status
                        switch ($payment->status) {
                            case 'approved':
                                if ($order->status === 'pending') {
                                    $this->createInvoice($order);
                                }
                                break;

                            case 'pending':
                            case 'in_process':
                                // Order remains in pending state
                                break;

                            case 'rejected':
                            case 'cancelled':
                            case 'refunded':
                            case 'charged_back':
                                $order->status = 'canceled';
                                $order->save();
                                break;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('MercadoPago IPN Error: ' . $e->getMessage());
        }
    }

    /**
     * Create invoice.
     *
     * @param  \Midnight\Sales\Contracts\Order  $order
     * @return void
     */
    protected function createInvoice($order)
    {
        if ($order->payment->method === 'mercadopago_standard') {
            $this->invoiceRepository->create($this->prepareInvoiceData($order));
        }
    }

    /**
     * Prepare invoice data.
     *
     * @param  \Midnight\Sales\Contracts\Order  $order
     * @return array
     */
    protected function prepareInvoiceData($order)
    {
        $invoiceData = [
            'order_id' => $order->id,
        ];

        foreach ($order->items as $item) {
            $invoiceData['invoice']['items'][$item->id] = $item->qty_to_invoice;
        }

        return $invoiceData;
    }
}
