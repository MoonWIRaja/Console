<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="Thu, 01 Jan 1970 00:00:00 GMT">
    <title>Redirecting to Fiuu Checkout</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: #05080e;
            color: #f8f6ef;
            font-family: "Space Mono", "IBM Plex Mono", ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }

        .checkout-card {
            width: min(520px, 100%);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 18px;
            background: rgba(10, 15, 24, 0.92);
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.34);
            padding: 28px;
        }

        h1 {
            margin: 0 0 10px;
            font-size: 20px;
            line-height: 1.2;
        }

        p {
            margin: 0 0 16px;
            color: rgba(248, 246, 239, 0.7);
            font-size: 13px;
            line-height: 1.6;
        }

        code {
            color: #4ade80;
        }

        button {
            appearance: none;
            border: 0;
            border-radius: 999px;
            background: #22c55e;
            color: #041109;
            font-weight: 700;
            padding: 12px 18px;
            cursor: pointer;
            font: inherit;
        }
    </style>
</head>
<body>
    <div class="checkout-card">
        <h1>Redirecting to Fiuu checkout</h1>
        <p>Preparing secure payment session for reference <code>{{ $reference }}</code>. If nothing happens, use the button below.</p>

        <form id="fiuu-checkout-form" method="POST" action="{{ $action }}">
            @foreach ($payload as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <button type="submit">Continue to payment</button>
        </form>
    </div>

    <script>
        window.setTimeout(function () {
            document.getElementById('fiuu-checkout-form').submit();
        }, 25);
    </script>
</body>
</html>
