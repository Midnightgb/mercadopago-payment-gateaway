<?php

namespace Midnight\MercadoPago\Http\Controllers;

use Webkul\Checkout\Facades\Cart;
use Midnight\MercadoPago\Helpers\Ipn;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Transformers\OrderResource;
use Illuminate\Http\Request;

class StandardController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected OrderRepository $orderRepository,
        protected Ipn $ipnHelper
    ) {}

    /**
     * Redirects to the mercadopago.
     *
     * @return \Illuminate\View\View
     */
    public function redirect()
    {
        try {
            $cart = Cart::getCart();

            if ($cart) {
                // Clear any previous order_id from session to prevent conflicts
                session()->forget('order_id');
                
                // Create new order for this checkout session
                $data = (new OrderResource($cart))->jsonSerialize();
                $order = $this->orderRepository->create($data);
                
                // Store the new order ID in session
                session()->put('order_id', $order->id);
                
                \Log::info("MercadoPago: Created new order {$order->id} for cart {$cart->id}");
            }
        } catch (\Throwable $e) {
            \Log::error('MercadoPago redirect order creation failed: ' . $e->getMessage());
            
            // If order creation fails, redirect back to cart with error
            session()->flash('error', 'Unable to process order. Please try again.');
            return redirect()->route('shop.checkout.cart.index');
        }

        return view('mercadopago::standard-redirect');
    }

    /**
     * Cancel payment from mercadopago.
     *
     * @return \Illuminate\Http\Response
     */
    public function cancel()
    {
        // Handle order cancellation properly
        $orderId = session()->get('order_id');
        
        if ($orderId) {
            try {
                $order = $this->orderRepository->find($orderId);
                
                if ($order && $order->status === 'pending') {
                    // Cancel the order and restore inventory
                    $order->status = 'canceled';
                    $order->save();
                    
                    // Restore inventory for each item
                    foreach ($order->items as $item) {
                        if ($item->product) {
                            $item->product->inventory_source_products()->increment('qty', $item->qty_ordered);
                        }
                    }
                    
                    \Log::info("MercadoPago: Cancelled order {$orderId} and restored inventory");
                }
            } catch (\Exception $e) {
                \Log::error("MercadoPago: Error cancelling order {$orderId}: " . $e->getMessage());
            }
            
            // Clear the order from session
            session()->forget('order_id');
        }

        session()->flash('error', trans('mercadopago::app.checkout.cart.payment-cancelled'));

        return redirect()->route('shop.checkout.cart.index');
    }

    /**
     * Pending payment from mercadopago.
     *
     * @return \Illuminate\Http\Response
     */
    public function pending()
    {
        // Don't clear order_id here since payment might still be processed
        session()->flash('warning', trans('mercadopago::app.checkout.cart.payment-pending'));

        return redirect()->route('shop.checkout.cart.index');
    }

    /**
     * Success payment.
     *
     * @return \Illuminate\Http\Response
     */
    public function success()
    {
        $orderId = session()->get('order_id');
        $order = null;

        if ($orderId) {
            $order = $this->orderRepository->find($orderId);
        }

        // Fallback: create order if it doesn't exist (legacy support)
        if (!$order) {
            try {
                $cart = Cart::getCart();
                if ($cart) {
                    $data = (new OrderResource($cart))->jsonSerialize();
                    $order = $this->orderRepository->create($data);
                    \Log::info("MercadoPago: Created fallback order {$order->id}");
                }
            } catch (\Throwable $e) {
                \Log::error('MercadoPago success fallback order creation failed: ' . $e->getMessage());
            }
        }

        // Deactivate cart and clear session
        try { 
            Cart::deActivateCart(); 
        } catch (\Throwable $e) { 
            \Log::warning('Failed to deactivate cart: ' . $e->getMessage());
        }

        if ($order) {
            session()->flash('order_id', $order->id);
            \Log::info("MercadoPago: Successfully processed order {$order->id}");
        }

        // Clear the order_id from session to prevent reuse
        session()->forget('order_id');

        return redirect()->route('shop.checkout.onepage.success');
    }

    /**
     * MercadoPago IPN listener.
     *
     * @return \Illuminate\Http\Response
     */
    public function ipn(Request $request)
    {
        try {
            $this->ipnHelper->processIpn($request->all());
            return response()->json(['status' => 'OK'], 200);
        } catch (\Exception $e) {
            // Log the error but always return 200 to prevent MercadoPago retries
            \Log::error('MercadoPago IPN Controller Error: ' . $e->getMessage());
            return response()->json(['status' => 'ERROR', 'message' => 'Internal error'], 200);
        }
    }
}