<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Redirecting to eSewa</title>
</head>
<body>
<p>Redirecting to eSewa...</p>
<form id="esewa-form" method="post" action="<?= esc($epayUrl) ?>">
    <input type="hidden" name="amt" value="<?= esc($amount) ?>">
    <input type="hidden" name="psc" value="<?= esc($serviceCharge) ?>">
    <input type="hidden" name="pdc" value="<?= esc($deliveryCharge) ?>">
    <input type="hidden" name="txAmt" value="<?= esc($tax) ?>">
    <input type="hidden" name="tAmt" value="<?= esc($total) ?>">
    <input type="hidden" name="pid" value="<?= esc($pid) ?>">
    <input type="hidden" name="scd" value="<?= esc($merchantCode) ?>">
    <input type="hidden" name="su" value="<?= esc($successUrl) ?>">
    <input type="hidden" name="fu" value="<?= esc($failureUrl) ?>">
    <noscript>
        <button type="submit">Continue to eSewa</button>
    </noscript>
</form>
<script>
    document.getElementById('esewa-form').submit();
</script>
</body>
</html>
