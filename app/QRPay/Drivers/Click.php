<?php
namespace App\QRPay\Drivers;

use App\Models\QrTransaction;
use App\QRPay\PayRouter;
use Exception;

class Click
{
    public function __construct(public array $merchant)
    {
        $required = ['service_id', 'tin', 'secret_key', 'merchant_user_id'];
        $missing  = array_diff($required, array_keys($this->merchant));

        if (! empty($missing)) {
            throw new Exception('Missing required parameters for Click: ' . implode(', ', $missing));
        }
    }

    public function perform($id, $token, $amount, $fiscal)
    {
        $fiscalData = $this->prepareFiscalData($fiscal);

        $transaction = QrTransaction::create([
            'qr_order_id'    => $id,
            'user_id'     => auth()->id(),
            'system'      => 'click',
            'amount'      => (int) $amount,
            'state'       => PayRouter::PENDING,
            'create_time' => now(),
        ]);

        $response = $this->request('click_pass/payment', [
            'service_id' => $this->merchant['service_id'],
            'otp_data'   => $token,
            'amount'     => $amount,
        ]);

        if ($response['error_code'] != 0) {
            return $this->getErrorResponse($response);
        }

        $transaction->transaction_id = $response['payment_id'];

        if ($this->fiscalize($response['payment_id'], $amount, $fiscal)) {
            $transaction->state        = PayRouter::SUCCESS;
            $transaction->perform_time = now();
            $transaction->save();

            return $transaction;
        }

        $transaction->state       = PayRouter::CANCELED;
        $transaction->cancel_time = now();
        $transaction->save();

        return $transaction;
    }

    public function fiscalize($payment_id, $amount, $fiscal)
    {
        $fiscal = $this->prepareFiscalData($fiscal);

        $response = $this->request('payment/ofd_data/submit_items', [
            'service_id'     => $this->merchant['service_id'],
            'payment_id'     => $payment_id,
            'items'          => $fiscal,
            'received_cash'  => 0,
            'received_card'  => 0,
            'received_ecash' => intval($amount * 100),
        ]);

        if ($response['error_code'] != 0) {
            return $this->getErrorResponse($response);
        }

        return true;
    }

    public function cancel(QrTransaction $transaction)
    {
        if ($transaction->state != PayRouter::SUCCESS) {
            throw new Exception("Faqat muvaffaqiyatli to'lovlar ortga qaytarilishi mumkin!");
        }

        $response = $this->request('payment/reversal/' . $this->merchant['service_id'] . '/' . $transaction->transaction_id, type: 'delete');

        if ($response['error_code'] != 0) {
            return $this->getErrorResponse($response);
        }

        $transaction->state       = PayRouter::CANCELED_AFTER_SUCCESS;
        $transaction->cancel_time = now();
        $transaction->save();

        return $transaction;
    }

    private function prepareFiscalData($fiscal)
    {
        $data = [];

        foreach ($fiscal as $fiscalData) {
            $data[] = [
                'Name'           => $fiscalData['productName'],
                'SPIC'           => $fiscalData['vatBarcode'],
                'PackageCode'    => $fiscalData['extraDetail']['packageCode'],
                'Price'          => intval($fiscalData['amount'] * 100),
                'Amount'         => (int) $fiscalData['qty'],
                'VAT'            => intval($fiscalData['nds'] * 100),
                'VATPercent'     => $fiscalData['ndsPercent'],
                'CommissionInfo' => [
                    'TIN' => $this->merchant['tin'],
                ],
            ];
        }

        return $data;
    }

    public function request($method, $data = [], $type = 'POST')
    {
        $endpoint_url = 'https://api.click.uz/v2/merchant/';

        $ch = curl_init($endpoint_url . $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($type));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers());

        if (! empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $json     = json_decode($response, true);

        return $json;
    }

    public function headers()
    {
        $time   = time();
        $digest = sha1($time . $this->merchant['secret_key']);

        return [
            'Accept: application/json',
            'Content-Type: application/json',
            'Auth: ' . $this->merchant['merchant_user_id'] . ':' . $digest . ':' . $time,
        ];
    }

    private function getErrorResponse($response)
    {
        $paymentErrors = [
            -5025 => 'Оплата с корпоративных карт недоступна. Пожалуйста, обратитесь в свой банк.',
            -5017 => 'Недостаточно средств на счете',
            -4008 => 'Произошла ошибка в биллинге поставщика. Пожалуйста, попробуйте еще раз',
            -4004 => 'Ошибка при обработке платежа в банке. Пожалуйста, попробуйте позже',
            -1    => 'Ошибка при обработке платежа в банке. Пожалуйста, попробуйте позже',
            -5027 => 'Сумма оплаты превышает лимит разового платежа',
            -4003 => 'Ошибка при обработке платежа в банке. Пожалуйста, попробуйте позже',
            -4006 => 'Ошибка в биллинге. Пожалуйста, попробуйте позже',
            -4015 => 'Оплата за услугу временно невозможна. Пожалуйста, попробуйте позже.',
        ];

        return [
            'logo' => asset('images/qrpay/click_fail.png'),
            'code'    => $response['error_code'],
            'message' => $paymentErrors[$response['error_code']] . ' (Click)',
        ];
    }
}
