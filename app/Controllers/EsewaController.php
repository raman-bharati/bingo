<?php

namespace App\Controllers;

use App\Models\OrderModel;

class EsewaController extends BaseController
{
    public function index()
    {
        $data = [
            'amount' => 100.00,
            'productName' => 'Demo Course',
            'currency' => 'NPR',
        ];

        return view('esewa_demo', $data);
    }

    public function checkout()
    {
        $amountInput = $this->request->getPost('amount');
        $amount = is_numeric($amountInput) ? round((float) $amountInput, 2) : 100.00;
        if ($amount <= 0) {
            $amount = 100.00;
        }

        $tax = 0.00;
        $serviceCharge = 0.00;
        $deliveryCharge = 0.00;
        $total = $amount + $tax + $serviceCharge + $deliveryCharge;

        $pid = 'ORDER-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        $productName = 'Demo Course';
        $currency = 'NPR';

        $merchantCode = env('esewa.merchantCode');
        $epayUrl = env('esewa.epayUrl', 'https://uat.esewa.com.np/epay/main');
        $successUrl = site_url('esewa/success');
        $failureUrl = site_url('esewa/failure');

        if (!$merchantCode) {
            return view('esewa_result', [
                'status' => 'failed',
                'message' => 'Missing eSewa merchant code. Set esewa.merchantCode in .env.',
            ]);
        }

        $orderModel = $this->orders();
        $orderId = $orderModel->insert([
            'order_code' => $pid,
            'product_name' => $productName,
            'currency' => $currency,
            'amount' => $amount,
            'tax' => $tax,
            'service_charge' => $serviceCharge,
            'delivery_charge' => $deliveryCharge,
            'total_amount' => $total,
            'status' => 'pending',
        ]);

        if (!$orderId) {
            log_message('error', 'Failed to create order: ' . json_encode($orderModel->errors()));
            return view('esewa_result', [
                'status' => 'failed',
                'message' => 'Could not create the order. Please try again.',
            ]);
        }

        session()->set('esewa', [
            'pid' => $pid,
            'amount' => $amount,
            'total' => $total,
        ]);

        $data = [
            'epayUrl' => $epayUrl,
            'amount' => number_format($amount, 2, '.', ''),
            'tax' => number_format($tax, 2, '.', ''),
            'serviceCharge' => number_format($serviceCharge, 2, '.', ''),
            'deliveryCharge' => number_format($deliveryCharge, 2, '.', ''),
            'total' => number_format($total, 2, '.', ''),
            'pid' => $pid,
            'merchantCode' => $merchantCode,
            'successUrl' => $successUrl,
            'failureUrl' => $failureUrl,
        ];

        return view('esewa_redirect', $data);
    }

    public function success()
    {
        $pid = $this->request->getGetPost('pid') ?? $this->request->getGetPost('oid');
        $refId = $this->request->getGetPost('refId');
        $amount = $this->request->getGetPost('amt');

        if (!$pid || !$refId) {
            return view('esewa_result', [
                'status' => 'failed',
                'message' => 'Missing or mismatched payment data.',
            ]);
        }

        $orderModel = $this->orders();
        $order = $orderModel->where('order_code', $pid)->first();
        if (!$order) {
            return view('esewa_result', [
                'status' => 'failed',
                'message' => 'Order not found.',
            ]);
        }

        if (!$amount) {
            $amount = $order['total_amount'];
        }

        $verified = $this->verifyEsewa($pid, $refId, $amount);
        if (!$verified) {
            $orderModel->update($order['id'], [
                'esewa_status' => 'verification_failed',
                'callback_payload' => $this->encodePayload($this->request->getGetPost()),
            ]);
            return view('esewa_result', [
                'status' => 'failed',
                'message' => 'Payment verification failed.',
            ]);
        }

        $orderModel->update($order['id'], [
            'status' => 'paid',
            'esewa_ref_id' => $refId,
            'esewa_status' => 'success',
            'callback_payload' => $this->encodePayload($this->request->getGetPost()),
            'verified_at' => date('Y-m-d H:i:s'),
            'paid_at' => date('Y-m-d H:i:s'),
        ]);

        session()->remove('esewa');

        return view('esewa_result', [
            'status' => 'success',
            'message' => 'Payment verified. Thank you!',
            'pid' => $pid,
            'refId' => $refId,
        ]);
    }

    public function failure()
    {
        $pid = $this->request->getGetPost('pid') ?? $this->request->getGetPost('oid');
        if ($pid) {
            $orderModel = $this->orders();
            $order = $orderModel->where('order_code', $pid)->first();
            if ($order) {
                $orderModel->update($order['id'], [
                    'status' => 'failed',
                    'esewa_status' => 'failed',
                    'callback_payload' => $this->encodePayload($this->request->getGetPost()),
                ]);
            }
        }

        return view('esewa_result', [
            'status' => 'failed',
            'message' => 'Payment was cancelled or failed.',
        ]);
    }

    public function webhook()
    {
        $payload = $this->request->getJSON(true);
        if (!$payload) {
            $payload = $this->request->getPost();
        }

        if (!$payload) {
            return $this->response->setStatusCode(400)->setJSON([
                'status' => 'error',
                'message' => 'Empty payload.',
            ]);
        }

        if (!$this->isSignatureValid($payload)) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Invalid signature.',
            ]);
        }

        $pid = $payload['pid'] ?? $payload['oid'] ?? null;
        if (!$pid) {
            return $this->response->setStatusCode(400)->setJSON([
                'status' => 'error',
                'message' => 'Missing order id.',
            ]);
        }

        $orderModel = $this->orders();
        $order = $orderModel->where('order_code', $pid)->first();
        if (!$order) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Order not found.',
            ]);
        }

        $rawStatus = (string) ($payload['status'] ?? $payload['state'] ?? '');
        $normalizedStatus = $this->normalizeWebhookStatus($rawStatus);

        $verified = true;
        if ($normalizedStatus === 'paid' && $this->shouldVerifyOnWebhook()) {
            $amount = $payload['amt'] ?? $order['total_amount'];
            $refId = (string) ($payload['refId'] ?? $payload['reference_id'] ?? '');
            if (!$refId) {
                $verified = false;
            } else {
                $verified = $this->verifyEsewa($pid, $refId, $amount);
            }
        }

        if ($normalizedStatus === 'paid' && !$verified) {
            $normalizedStatus = 'pending';
            $rawStatus = 'verification_failed';
        }

        $update = [
            'status' => $normalizedStatus,
            'esewa_status' => $rawStatus ?: null,
            'esewa_ref_id' => $payload['refId'] ?? $payload['reference_id'] ?? $order['esewa_ref_id'],
            'callback_payload' => $this->encodePayload($payload),
        ];

        if ($normalizedStatus === 'paid') {
            $update['verified_at'] = date('Y-m-d H:i:s');
            $update['paid_at'] = date('Y-m-d H:i:s');
        }

        $orderModel->update($order['id'], $update);

        return $this->response->setJSON([
            'status' => 'ok',
        ]);
    }

    private function verifyEsewa(string $pid, string $refId, $amount): bool
    {
        $merchantCode = env('esewa.merchantCode');
        $verifyUrl = env('esewa.verifyUrl', 'https://uat.esewa.com.np/epay/transrec');

        if (!$merchantCode || !$verifyUrl) {
            return false;
        }

        $client = \Config\Services::curlrequest();

        try {
            $response = $client->post($verifyUrl, [
                'form_params' => [
                    'amt' => $amount,
                    'scd' => $merchantCode,
                    'pid' => $pid,
                    'rid' => $refId,
                ],
                'timeout' => 10,
            ]);
        } catch (\Throwable $e) {
            return false;
        }

        $body = (string) $response->getBody();

        return stripos($body, 'Success') !== false;
    }

    private function orders(): OrderModel
    {
        return new OrderModel();
    }

    private function encodePayload($payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function isSignatureValid(array $payload): bool
    {
        $secret = env('esewa.signatureSecret');
        if (!$secret) {
            log_message('warning', 'eSewa signature secret not set. Skipping signature validation.');
            return true;
        }

        $signature = $this->request->getHeaderLine('X-Signature');
        if (!$signature) {
            $signature = $this->request->getHeaderLine('X-Esewa-Signature');
        }

        if (!$signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $this->buildSignaturePayload($payload), $secret);

        return hash_equals($expected, $signature);
    }

    private function buildSignaturePayload(array $payload): string
    {
        $fields = env('esewa.signatureFields', 'pid,refId,amt,status');
        $separator = env('esewa.signatureSeparator', '&');

        $keys = array_filter(array_map('trim', explode(',', $fields)));
        $pairs = [];
        foreach ($keys as $key) {
            $pairs[] = $key . '=' . ($payload[$key] ?? '');
        }

        return implode($separator, $pairs);
    }

    private function normalizeWebhookStatus(string $status): string
    {
        $value = strtolower(trim($status));
        if (in_array($value, ['success', 'complete', 'completed', 'paid'], true)) {
            return 'paid';
        }

        if (in_array($value, ['failed', 'failure', 'cancelled', 'canceled'], true)) {
            return 'failed';
        }

        return 'pending';
    }

    private function shouldVerifyOnWebhook(): bool
    {
        $value = env('esewa.verifyOnWebhook', true);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
