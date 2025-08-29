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
            // Handle different webhook formats
            $paymentId = null;
            $notificationType = null;

            // Format 1: {"type":"payment","data":{"id":"123"}}
            if (isset($request['type']) && isset($request['data']['id'])) {
                $notificationType = $request['type'];
                $paymentId = $request['data']['id'];
            }
            // Format 2: {"topic":"payment","id":"123"}
            elseif (isset($request['topic']) && isset($request['id'])) {
                $notificationType = $request['topic'];
                $paymentId = $request['id'];
            }
            // Format 3: {"action":"payment.updated","data":{"id":"123"}}
            elseif (isset($request['action']) && isset($request['data']['id'])) {
                $notificationType = explode('.', $request['action'])[0]; // Extract 'payment' from 'payment.updated'
                $paymentId = $request['data']['id'];
            }

            // Validate we have the required data
            if (!$notificationType || !$paymentId) {
                return;
            }

            // Only process payment notifications
            if ($notificationType !== 'payment') {
                return;
            }

            // Validate payment ID
            if (empty($paymentId)) {
                Log::error('MercadoPago IPN Error: Empty payment ID');
                return;
            }

            // Check if this looks like test data
            if (in_array($paymentId, ['123456', '123', 'test', 'sample'])) {
                return;
            }

            // Initialize the SDK
            $accessToken = core()->getConfigData('sales.payment_methods.mercadopago_standard.access_token');
            if (empty($accessToken)) {
                Log::error('MercadoPago IPN Error: Access token not configured');
                return;
            }

            MercadoPagoConfig::setAccessToken($accessToken);

            // Get the payment information with detailed error handling
            $client = new PaymentClient();

            try {
                $payment = $client->get($paymentId);
            } catch (\MercadoPago\Exceptions\MPApiException $apiException) {
                // If payment not found (404), it's likely test data - warn instead of error
                if ($apiException->getApiResponse()?->getStatusCode() === 404) {
                    Log::warning("MercadoPago IPN: Payment {$paymentId} not found in API - likely test data");
                } else {
                    Log::error("MercadoPago IPN API Error: " . $apiException->getMessage());
                }
                return;
            } catch (\Exception $e) {
                Log::error("MercadoPago IPN General API Error: " . $e->getMessage());
                return;
            }

            if ($payment && isset($payment->external_reference)) {
                $orderId = $payment->external_reference;

                // Try to find the order
                $order = $this->orderRepository->find($orderId);

                if (!$order) {
                    // Fallback: try to find by cart_id if the order was created with cart ID as external reference
                    try {
                        if (method_exists($this->orderRepository->model, 'where')) {
                            $order = $this->orderRepository->model::where('cart_id', $orderId)->first();
                        }
                    } catch (\Throwable $e) {
                        // Ignore fallback errors
                    }
                }

                if ($order) {
                    Log::info("MercadoPago IPN: Processing payment {$paymentId} for order {$orderId} with status {$payment->status}");

                    // Get current order status to avoid unnecessary changes
                    $currentStatus = $order->status;

                    // Process payment status
                    switch ($payment->status) {
                        case 'approved':
                            if ($currentStatus === 'pending') {
                                $this->createInvoice($order);
                                Log::info("MercadoPago IPN: Invoice created for order {$orderId}");
                            }
                            break;

                        case 'pending':
                        case 'in_process':
                        case 'in_mediation':
                            // Order remains in pending state - no action needed
                            break;

                        case 'rejected':
                        case 'cancelled':
                        case 'refunded':
                        case 'charged_back':
                        case 'expired':
                            if (!in_array($currentStatus, ['canceled', 'cancelled'])) {
                                $this->cancelOrderAndRestoreInventory($order, $payment->status);
                                Log::info("MercadoPago IPN: Order {$orderId} cancelled due to payment status: {$payment->status}");
                            }
                            break;

                        default:
                            Log::warning("MercadoPago IPN: Unknown payment status {$payment->status} for order {$orderId}");
                            break;
                    }
                } else {
                    Log::warning("MercadoPago IPN: Order {$orderId} not found - this could be test data or an old payment");
                }
            } else {
                Log::error("MercadoPago IPN Error: Payment {$paymentId} not found or missing external_reference");
            }
        } catch (\Exception $e) {
            Log::error('MercadoPago IPN Error: ' . $e->getMessage());
            Log::error('MercadoPago IPN Error Trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Create invoice.
     *
     * @param  \Webkul\Sales\Contracts\Order  $order
     * @return void
     */
    protected function createInvoice($order)
    {
        if ($order->payment->method === 'mercadopago_standard') {
            try {
                $this->invoiceRepository->create($this->prepareInvoiceData($order));
                
                // Update order status to processing after invoice creation
                $order->status = 'processing';
                $order->save();
                
                Log::info("MercadoPago IPN: Order {$order->id} status updated to processing");
            } catch (\Exception $e) {
                Log::error("MercadoPago IPN: Failed to create invoice for order {$order->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Cancel order and restore inventory.
     *
     * @param  \Webkul\Sales\Contracts\Order  $order
     * @param  string  $paymentStatus
     * @return void
     */
    protected function cancelOrderAndRestoreInventory($order, $paymentStatus)
    {
        try {
            // Set order status to canceled
            $order->status = 'canceled';
            $order->save();

            // Restore inventory for each item
            foreach ($order->items as $item) {
                if ($item->product) {
                    // Check if the product has inventory sources
                    $inventorySource = $item->product->inventory_source_products()->first();
                    
                    if ($inventorySource) {
                        // Restore the quantity
                        $inventorySource->increment('qty', $item->qty_ordered);
                        
                        Log::info("MercadoPago IPN: Restored {$item->qty_ordered} units of product {$item->product->id} for cancelled order {$order->id}");
                    } else {
                        Log::warning("MercadoPago IPN: No inventory source found for product {$item->product->id} in order {$order->id}");
                    }
                }
            }
            
            Log::info("MercadoPago IPN: Successfully cancelled order {$order->id} and restored inventory due to payment status: {$paymentStatus}");
        } catch (\Exception $e) {
            Log::error("MercadoPago IPN: Error cancelling order {$order->id} and restoring inventory: " . $e->getMessage());
        }
    }

    /**
     * Prepare invoice data.
     *
     * @param  \Webkul\Sales\Contracts\Order  $order
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