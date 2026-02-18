<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>eSewa Result</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f6f8; margin: 0; padding: 24px; }
        .card { max-width: 520px; margin: 24px auto; background: #fff; border-radius: 10px; padding: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .success { color: #1a7f37; }
        .failed { color: #b42318; }
        .row { margin: 10px 0; }
    </style>
</head>
<body>
<div class="card">
    <h1 class="<?= esc($status) ?>">
        <?= $status === 'success' ? 'Payment Success' : 'Payment Failed' ?>
    </h1>
    <p><?= esc($message ?? '') ?></p>

    <?php if (!empty($pid)): ?>
        <div class="row"><strong>Order ID:</strong> <?= esc($pid) ?></div>
    <?php endif; ?>

    <?php if (!empty($refId)): ?>
        <div class="row"><strong>Reference ID:</strong> <?= esc($refId) ?></div>
    <?php endif; ?>

    <div class="row">
        <a href="<?= site_url('esewa') ?>">Back to demo</a>
    </div>
</div>
</body>
</html>
