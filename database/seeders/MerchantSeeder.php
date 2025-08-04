<?php
namespace Database\Seeders;

use App\Models\Merchant;
use Illuminate\Database\Seeder;

class MerchantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
            [
                'branch_id' => 1,

                'payme'     => [
                    'merchant_id' => '',
                    'key'         => '',
                    'account'     => 'order_id',
                ],

                'click'     => [
                    'service_id'       => '',
                    'merchant_id'      => '',
                    'merchant_user_id' => '',
                    'secret_key'       => '',
                    'tin'              => '',
                ]
            ]
        ];

        $data = collect($data)->map(function ($item) {
            $item['payme'] = json_encode($item['payme']);
            $item['click'] = json_encode($item['click']);
            $item['uzum']  = json_encode($item['uzum']);
            return $item;
        })->toArray();

        Merchant::upsert($data, ['branch_id'], ['payme', 'click', 'uzum']);
    }
}
