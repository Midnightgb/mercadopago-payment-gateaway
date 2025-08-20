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
        // Crear la orden antes de salir del sitio (evita pérdida cuando el usuario no vuelve, ej. PIX en desktop)
        try {
            $cart = Cart::getCart();

            if ($cart && ! session()->has('order_id')) {
                $data = (new OrderResource($cart))->jsonSerialize();
                $order = $this->orderRepository->create($data);
                session()->put('order_id', $order->id);
            }
        } catch (\Throwable $e) {
            \Log::error('MercadoPago redirect order creation failed: ' . $e->getMessage());
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
        // Si ya se creó la orden en redirect(), solo reutilizarla
        $orderId = session()->get('order_id');
        if ($orderId) {
            $order = $this->orderRepository->find($orderId);
        }

        // Fallback legacy: si no existe (flujo antiguo o error), crearla ahora
        if (empty($order)) {
            try {
                $cart = Cart::getCart();
                if ($cart) {
                    $data = (new OrderResource($cart))->jsonSerialize();
                    $order = $this->orderRepository->create($data);
                    session()->put('order_id', $order->id);
                }
            } catch (\Throwable $e) {
                \Log::error('MercadoPago success fallback order creation failed: ' . $e->getMessage());
            }
        }

        // Desactivar el carrito si existe
        try { Cart::deActivateCart(); } catch (\Throwable $e) { /* ignore */ }

        if (isset($order) && $order) {
            session()->flash('order_id', $order->id);
        }

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
