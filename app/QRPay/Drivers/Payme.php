<?php
namespace App\QRPay\Drivers;

use App\Models\QrTransaction;
use App\QRPay\PayRouter;
use Exception;

class Payme
{
    public const SUCCESS   = 4;
    public const CANCELING = 21;
    public const CANCELED  = 50;

    public function __construct(public array $merchant)
    {
        $required = ['account', 'merchant_id', 'key'];
        $missing  = array_diff($required, array_keys($this->merchant));

        if (! empty($missing)) {
            throw new Exception('Missing required parameters for Payme: ' . implode(', ', $missing));
        }
    }

    public function perform($id, $token, $amount, $fiscal)
    {
        $fiscal      = $this->prepareFiscalData($fiscal);
        $transaction = $this->createReceipt($id, $amount, $fiscal);
        $payment     = $this->payReceipt($transaction->transaction_id, $token);

        if (is_array($payment)) {
            return $payment;
        }

        if ($payment) {
            $transaction->perform_time = now();
            $transaction->state        = PayRouter::SUCCESS;
            $transaction->save();

            return $transaction;
        }

        $transaction->cancel_time = now();
        $transaction->state       = PayRouter::CANCELED;
        $transaction->save();

        return $transaction;
    }

    public function cancel(QrTransaction $transaction)
    {
        if ($transaction->stste != PayRouter::SUCCESS) {
            throw new Exception("Только успешные платежи могут быть возвращены!");
        }

        $response = $this->request('receipts.cancel', compact('id'));

        if (isset($response['error'])) {
            return $this->getErrorResponse($response);
        }

        $state = $response['result']['receipt']['state'];

        if ($state == self::CANCELING or $state == self::CANCELED) {
            $transaction->state       = PayRouter::CANCELED_AFTER_SUCCESS;
            $transaction->cancel_time = now();
            $transaction->save();

            return $transaction;
        }

        return $transaction;
    }

    public function createReceipt($id, $amount, $fiscal)
    {
        $response = $this->request('receipts.create', [
            'amount'  => intval($amount * 100),
            'account' => [$this->merchant['account'] => $id],
            'detail'  => [
                'receipt_type' => 0,
                'items'        => $fiscal,
            ],
        ]);

        if (isset($response['error'])) {
            return $this->getErrorResponse($response);
        }

        $transaction = QrTransaction::create([
            'qr_order_id'       => $id,
            'user_id'        => auth()->id(),
            'transaction_id' => $response['result']['receipt']['_id'],
            'system'         => 'payme',
            'amount'         => $amount,
            'state'          => PayRouter::PENDING,
            'create_time'    => now(),
        ]);

        return $transaction;
    }

    public function payReceipt($id, $token)
    {
        $response = $this->request('receipts.pay', compact('id', 'token'));

        if (isset($response['error'])) {
            return $this->getErrorResponse($response);
        }

        return $response['result']['receipt']['state'] == 4;
    }

    public function prepareFiscalData($fiscal)
    {
        $data = [];

        foreach ($fiscal as $fiscalData) {
            $data[] = [
                'title'        => $fiscalData['productName'],
                'price'        => (int) $fiscalData['price'] * 100,
                'count'        => (int) $fiscalData['qty'],
                'code'         => $fiscalData['vatBarcode'],
                'package_code' => $fiscalData['extraDetail']['packageCode'],
                'vat_percent'  => (int) $fiscalData['ndsPercent'],
            ];
        }

        return $data;
    }

    public function request($method, $params = [])
    {
        $endpoint_url = 'https://checkout.paycom.uz/api';

        $data = [
            'jsonrpc' => '2.0',
            'method'  => $method,
            'params'  => $params,
        ];

        $ch = curl_init($endpoint_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers());

        $response = curl_exec($ch);
        $json     = json_decode($response, true);

        return $json;
    }

    public function headers()
    {
        return [
            'X-Auth:' . $this->merchant['merchant_id'] . ':' . $this->merchant['key'],
            'Content-Type: application/json',
        ];
    }

    private function getErrorResponse($response)
    {
        return [
            'logo'    => asset('images/qrpay/payme_fail.png'),
            'code'    => $response['error']['code'],
            'message' => $response['error']['message'] . ' (Payme)',
        ];
    }
}
