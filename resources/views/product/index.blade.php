<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Stripe Checkout</title>
</head>

<body class="antialiased" style="display: flex;gap:2rem;">
    <div style="display: flex;gap:3rem;">
        @foreach ($products as $product)
            <div class="flex: 1">
                <img width="300px" src="{{ $product->image }}" alt="">
                <h5>{{ $product->name }}</h5>
                <p>{{ $product->price }}</p>
            </div>
        @endforeach
    </div>
    <p>
    <form action="{{ route('checkout') }}" method="post">
        @csrf
        <button>Checkout</button>
    </form>
    </p>


</body>

</html>
