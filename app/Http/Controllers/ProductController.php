<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductController extends Controller
{
    public function  index()
    {
        $products = Product::all();
        return view('product.index', compact('products'));
    }

    public function checkout(Request $request)
    {

        $stripe = new \Stripe\StripeClient(env("STRIPE_SECRET_KEY"));
        $products = Product::all();
        $line_items = array();
        $totalPrice = 0;
        foreach ($products as $product) {
            $totalPrice += $product->price;
            $line_items[] = [
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $product->name,
                        'images' => [$product->image]
                    ],
                    'unit_amount' => $product->price * 100,
                ],
                'quantity' => 1,
            ];
        }

        $checkout_session = $stripe->checkout->sessions->create([
            'line_items' => $line_items,
            'mode' => 'payment',
            'success_url' => route('checkout.success', [], true) . "?session_id={CHECKOUT_SESSION_ID}",
            'cancel_url' => route('checkout.cancel', [], true),
        ]);

        $order  = new Order();
        $order->status = "unpaid";
        $order->total_price = $totalPrice;
        $order->session_id  = $checkout_session->id;
        $order->save();

        return redirect($checkout_session->url);
    }

    public function success()
    {

        $session_id = request()->get('session_id');
        $stripe = new \Stripe\StripeClient(env("STRIPE_SECRET_KEY"));

        try {
            $session = $stripe->checkout->sessions->retrieve($session_id);
            if (!$session) {
                throw new NotFoundHttpException;
            }
            $customer = $stripe->customers->retrieve($session->customer);
            $order = Order::where('session_id', $session_id)->where('status', 'unpaid')->first();
            if (isset($order)) {
                $order->status = "Paid";
                $order->save();
            }

            return view('product.checkout.success', compact('products', 'customer'));
        } catch (Exception $e) {
            throw new NotFoundHttpException;
        }
    }

    public function cancel()
    {
    }

    public function webhook()
    {


        $stripe = new \Stripe\StripeClient(env("STRIPE_SECRET_KEY"));


        // This is your Stripe CLI webhook secret for testing your endpoint locally.
        $endpoint_secret = 'whsec_c1e4301f4329d261ab76eb9797db501af856a56a9e5ffe9f0d14efe5db665a8f';

        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        $event = null;

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                $endpoint_secret
            );
        } catch (\UnexpectedValueException $e) {
            return response("",400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return response("",400);
        }

        // Handle the event
        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;

                $session_id = $session->id;

                $order = Order::where('session_id', $session_id)->where('status', 'unpaid')->first();
                if (isset($order)) {
                    $order->status = "Paid";
                    $order->save();
                }
                // ... handle other event types
            default:
                echo 'Received unknown event type ' . $event->type;
        }

        return response("");
    }
}
