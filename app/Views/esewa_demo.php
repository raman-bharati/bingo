<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>eSewa Demo Checkout</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f6f8; margin: 0; padding: 24px; }
        .card { max-width: 520px; margin: 24px auto; background: #fff; border-radius: 10px; padding: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        h1 { margin-top: 0; }
        .row { margin: 16px 0; }
        label { display: block; margin-bottom: 6px; font-weight: 600; }
        input { width: 100%; padding: 10px 12px; font-size: 16px; border: 1px solid #cfd5dd; border-radius: 6px; }
        button { background: #1a7f37; color: #fff; border: 0; padding: 12px 18px; font-size: 16px; border-radius: 6px; cursor: pointer; }
        .muted { color: #6b7280; font-size: 14px; }
    </style>
</head>
<body>
<div class="card">
    <h1>eSewa Demo Checkout</h1>
    <p class="muted">This is a beginner-friendly demo using the eSewa test environment.</p>

    <div class="row">
        <strong>Product:</strong> <?= esc($productName ?? 'Demo Course') ?>
    </div>

    <form method="post" action="<?= site_url('esewa/checkout') ?>">
        <?= csrf_field() ?>
        <div class="row">
            <label for="amount">Amount (<?= esc($currency ?? 'NPR') ?>)</label>
            <input id="amount" name="amount" type="number" step="0.01" value="<?= esc((string) ($amount ?? 100.00)) ?>">
        </div>
        <button type="submit">Pay with eSewa</button>
    </form>
</div>
</body>
</html>
