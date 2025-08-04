<?php
namespace App\QRPay\Drivers;

use App\Models\QrTransaction;
use App\QRPay\PayRouter;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Uzum
{
    public function __construct(public array $merchant)
    {
        $required = [];
        $missing  = array_diff($required, array_keys($this->merchant));

        if (! empty($missing)) {
            throw new Exception('Missing required parameters for Uzum: ' . implode(', ', $missing));
        }
    }

    public function perform($id, $token, $amount, $fiscal)
    {
        $transaction = QrTransaction::create([
            'transaction_id' => Str::uuid()->toString(),
            'order_id'       => $id,
            'user_id'        => auth()->id(),
            'system'         => 'uzum',
            'amount'         => $amount,
            'state'          => PayRouter::PENDING,
            'create_time'    => now(),
        ]);

        $response = $this->request_payment('v2/payment', [
            'amount'         => $amount * 100,
            'cashbox_code'   => $this->merchant['cachbox_code'],
            'otp_data'       => $token,
            'order_id'       => $id,
            'transaction_id' => $transaction->transaction_id,
            'service_id'     => $this->merchant['service_id'],
        ]);

        if ($response['error_code'] != 0) {
            throw new Exception(json_encode($response));
        }

        if ($this->fiscalize($response['payment_id'], $amount, $fiscal)) {
            $transaction->perform_time = now();
            $transaction->state        = PayRouter::SUCCESS;
            $transaction->save();
        } else {
            $transaction->cancel_time = now();
            $transaction->state       = PayRouter::CANCELED;
            $transaction->save();
        }

        return $transaction;
    }

    public function cancel($transaction_id)
    {
        $transaction = QrTransaction::where('transaction_id', $transaction_id)->first();

        if (! $transaction) {
            throw new Exception('Transaction not found');
        }
    }

    public function cancelFiscalization($payment_id)
    {

    }

    public function fiscalize($payment_id, $amount, $fiscal)
    {
        $fiscal       = $this->prepareFiscalData($fiscal);
        $operation_id = Str::uuid()->toString();

        $fiscalize = $this->request_fiscalize('v2/receipt', [
            'payment_id'   => $payment_id,
            'operation_id' => $operation_id,
            'date_time'    => now()->setTimezone('Asia/Tashkent')->toIso8601String(),
            'cash_amount'  => 0 * 100,
            'card_amount'  => intval($amount * 100),
            'items'        => $fiscal,
        ]);

        if (isset($fiscalize['code']) and $fiscalize['code'] == 0) {

        }
    }

    private function prepareFiscalData($fiscal)
    {
        $data = [];

        foreach ($fiscal as $fiscalData) {
            $data[] = [
                'product_name' => $fiscalData['productName'],
                'price'        => intval($fiscalData['price'] * 100),
                'count'        => $fiscalData['qty'],
                'spic'         => $fiscalData['vatBarcode'],
                'package_code' => $fiscalData['extraDetail']['packageCode'],
                'vat_percent'  => $fiscalData['VATPercent'],
                'owner_type'   => $this->merchant['owner_type'],
            ];
        }

        return $data;
    }

    public function request_payment($method, $data = [], $type = 'post')
    {
        $endpoint_url = 'https://developer.uzumbank.uz/api/apelsin-pay/merchant/';

        $ch = curl_init($endpoint_url . $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($type));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->payment_headers());

        if (! empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $json     = json_decode($response, true);

        Log::info('Uzum Payment Request', $data);
        Log::info('Uzum Payment Headers', $this->payment_headers());
        Log::info('Uzum Payment Response', $json);

        return $json;
    }

    public function request_fiscalize($method, $data = [], $type = 'post')
    {
        $endpoint_url = 'https://developer.uzumbank.uz/';

        $ch = curl_init($endpoint_url . $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($type));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->fiscal_headers());

        if (! empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $json     = json_decode($response, true);

        Log::info('Uzum Fiscal Request', $data);
        Log::info('Uzum Fiscal Headers', $this->fiscal_headers());
        Log::info('Uzum Fiscal Response', $json);

        return $json;
    }

    public function payment_headers()
    {
        $time = time();
        $hash = sha1($time . $this->merchant['secret_key']);

        return [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization:' . $this->merchant['merchant_service_user_id'] . ':' . $hash . ':' . $time,
        ];
    }

    public function fiscal_headers()
    {
        return [
            'X-Api-Key: ' . $this->merchant['fiscal_key'],
        ];
    }
}
