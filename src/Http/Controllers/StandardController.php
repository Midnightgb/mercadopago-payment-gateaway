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
        $cart = Cart::getCart();

        $data = (new OrderResource($cart))->jsonSerialize();

        $order = $this->orderRepository->create($data);

        Cart::deActivateCart();

        session()->flash('order_id', $order->id);

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
