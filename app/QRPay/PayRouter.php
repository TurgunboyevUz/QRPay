<?php
namespace App\QRPay;

use App\Models\QrTransaction;
use App\QRPay\Drivers\Click;
use App\QRPay\Drivers\Payme;
use App\QRPay\Drivers\Uzum;
use Throwable;

class PayRouter
{
    public const PENDING                = 1;
    public const SUCCESS                = 2;
    public const CANCELED               = -1;
    public const CANCELED_AFTER_SUCCESS = -2;

    private $drivers = [
        'payme' => Payme::class,
        'click' => Click::class,
        'uzum'  => Uzum::class,
    ];

    private $transaction;
    private $system       = '';
    private $status       = false;
    private $errorMessage = [];

    public function __construct(public array $merchants)
    {}

    public function performQrPayment($id, $token, $amount, $fiscal)
    {
        try {
            $transaction        = $this->getPaymentDriver($token)->perform($id, $token, $amount, $fiscal);
            $this->status       = $transaction instanceof QrTransaction && $transaction->state == self::SUCCESS;
            $this->transaction  = $this->status ? null : $transaction;
            $this->errorMessage = $this->status ? [] : $transaction;
        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }

        return $this;
    }

    public function cancelQrPayment($merchant, $transaction_id)
    {
        try {
            $transaction = QrTransaction::where('transaction_id', $transaction_id)->first();

            if (! $transaction) {
                return $this;
            }

            $transaction        = $this->getPaymentDriver('', $transaction->system)->cancel($transaction);
            $this->status       = $transaction->state == self::CANCELED or $transaction->state == self::CANCELED_AFTER_SUCCESS;
            $this->transaction  = $this->status ? null : $transaction;
            $this->errorMessage = $this->status ? [] : $transaction;
        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }

        return $this;
    }

    public function getPaymentSystem($token)
    {
        $this->system = ! is_numeric($token) ? 'uzum' : match (strlen($token)) {
            18 => 'click',
            20 => 'payme'
        };

        return $this->system;
    }

    public function getPaymentDriver($token, $system = null)
    {
        $system = $system ?? $this->getPaymentSystem($token);

        return new $this->drivers[$system]($this->merchants[$system]);
    }

    public function isSuccess()
    {
        return $this->status;
    }

    public function getTransaction()
    {
        return $this->transaction;
    }

    public function getSuccessMessage()
    {
        return [
            'logo'    => asset('images/qrpay/' . $this->system . '_success.png'),
            'message' => 'Оплата успешно проведена через систему ' . ucfirst($this->system),
            'code'    => 200,
        ];
    }

    public function getErrorMessage()
    {
        return array_merge([
            'logo' => asset('images/qrpay/' . $this->system . '_fail.png'),
        ], $this->errorMessage);
    }
}
