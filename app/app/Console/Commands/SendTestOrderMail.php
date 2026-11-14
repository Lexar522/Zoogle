<?php

namespace App\Console\Commands;

use App\Mail\OrderPlaced;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestOrderMail extends Command
{
    protected $signature = 'mail:send-test-order {email : Адреса для тестового листа}';

    protected $description = 'Надіслати тестовий лист підтвердження замовлення';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Некоректна адреса.');

            return self::FAILURE;
        }

        $order = Order::query()->create([
            'customer_name' => 'Олександр',
            'customer_phone' => '380501112233',
            'customer_email' => $email,
            'total' => 1287.50,
            'status' => Order::STATUS_NEW,
            'payment_status' => 'pending',
            'delivery_type' => Order::DELIVERY_NOVA_POSHTA_WAREHOUSE,
            'delivery_city' => 'Київ',
            'delivery_branch' => 'Відділення №12, вул. Хрещатик, 22',
            'customer_notes' => 'Тестовий лист оформлення замовлення.',
        ]);

        $order->items()->create([
            'title_snapshot' => 'Сіно лугове · 1 кг',
            'price' => 249.00,
            'qty' => 2,
            'line_total' => 498.00,
            'option_value_ids' => [],
        ]);
        $order->items()->create([
            'title_snapshot' => 'Гамак для гризунів · бежевий',
            'price' => 789.50,
            'qty' => 1,
            'line_total' => 789.50,
            'option_value_ids' => [],
        ]);

        Mail::to($email)->send(new OrderPlaced($order->fresh(['items'])));

        $this->info('Надіслано на '.$email);
        $this->line('Замовлення: '.$order->number);
        $this->line('Mailer: '.config('mail.default'));

        return self::SUCCESS;
    }
}
