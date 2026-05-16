<?php

namespace App\Filament\Admin\Pages;

use App\Models\ShopIntegrationSetting;
use App\Services\Payments\LiqPayClient;
use App\Services\Payments\WayForPayClient;
use App\Support\GoogleMapsApiKey;
use App\Support\NovaPoshtaApiKey;
use App\Support\OnlinePaymentSettings;
use App\Support\ShopHeaderContacts;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Throwable;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageShopIntegrations extends Page
{
    use CanUseDatabaseTransactions;

    protected static ?string $slug = 'shop-integrations';

    protected static ?string $title = 'Інтеграції';

    protected static ?string $navigationLabel = 'Інтеграції';

    protected static string|UnitEnum|null $navigationGroup = 'Налаштування';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 100;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Виклик з карти самовивозу (Alpine / Livewire): оновити координати мітки магазину у формі.
     */
    public function setPickupMapCoords(float $lat, float $lng): void
    {
        if (! is_array($this->data)) {
            $this->data = [];
        }
        $this->data['pickup_lat'] = round($lat, 7);
        $this->data['pickup_lng'] = round($lng, 7);
    }

    public function mount(): void
    {
        $record = ShopIntegrationSetting::record();

        $this->form->fill([
            'nova_poshta_api_key' => '',
            'clear_nova_poshta_key' => false,
            'google_maps_api_key' => app(GoogleMapsApiKey::class)->current(),
            'clear_google_maps_key' => false,
            'pickup_address' => $record?->pickup_address ?? '',
            'pickup_lat' => $record?->pickup_lat,
            'pickup_lng' => $record?->pickup_lng,
            'contact_phone' => $record?->contact_phone ?? '',
            'contact_email' => $record?->contact_email ?? '',
            'contact_instagram' => $record?->contact_instagram ?? '',
            'contact_viber' => $record?->contact_viber ?? '',
            'contact_whatsapp' => $record?->contact_whatsapp ?? '',
            'contact_telegram' => $record?->contact_telegram ?? '',
            'online_payment_provider' => $record?->online_payment_provider ?? 'liqpay',
            'wayforpay_merchant_account' => $record?->wayforpay_merchant_account ?? '',
            'wayforpay_secret_key' => '',
            'clear_wayforpay_secret' => false,
            'wayforpay_merchant_domain' => $record?->wayforpay_merchant_domain ?? '',
        ]);
    }

    /**
     * Порожній обробник: форма без глобальної кнопки «Зберегти», щоб Enter у полі не викликав збереження інших блоків.
     */
    public function noopFormSubmit(): void {}

    public function saveNovaPoshta(): void
    {
        try {
            $this->beginDatabaseTransaction();
            $this->callHook('beforeValidate');
            $data = $this->form->getState();
            $this->callHook('afterValidate');
            $this->callHook('beforeSave');

            $record = ShopIntegrationSetting::record();

            if (! empty($data['clear_nova_poshta_key'])) {
                $record->nova_poshta_api_key = null;
            } else {
                $keyFromForm = $data['nova_poshta_api_key'] ?? null;
                $trimmedNp = is_string($keyFromForm) ? trim($keyFromForm) : '';
                // Оновлюємо ключ лише якщо в полі введено новий непорожній рядок (порожнє = «не змінювати»).
                if ($trimmedNp !== '') {
                    if (strlen($trimmedNp) < 32) {
                        Notification::make()
                            ->danger()
                            ->title('Ключ Нової Пошти занадто короткий')
                            ->body('Потрібен повний ключ у форматі UUID (зазвичай 36 символів). Скопіюйте його з my.novaposhta.ua → Налаштування → Безпека → API 2.0 (усю довгу строку).')
                            ->persistent()
                            ->send();
                        throw new Halt;
                    }
                    $record->nova_poshta_api_key = $trimmedNp;
                }
            }

            $record->save();
            $this->callHook('afterSave');
        } catch (Halt $exception) {
            $exception->shouldRollbackDatabaseTransaction() ?
                $this->rollBackDatabaseTransaction() :
                $this->commitDatabaseTransaction();

            return;
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();

            throw $exception;
        }

        $this->commitDatabaseTransaction();
        $this->refreshFormFromRecord();

        $np = app(NovaPoshtaApiKey::class);
        $body = $np->isConfigured()
            ? 'Ключ для чекауту активний.'.($np->hasStoredKey() ? ' Джерело: збережений у базі.' : ' Джерело: .env.')
            : 'Ключ не заданий ні в базі, ні в .env — підказки НП на чекауті вимкнені.';

        Notification::make()
            ->success()
            ->title('Збережено: Нова Пошта')
            ->body($body)
            ->send();
    }

    public function saveGoogleMaps(): void
    {
        try {
            $this->beginDatabaseTransaction();
            $this->callHook('beforeValidate');
            $data = $this->form->getState();
            $this->callHook('afterValidate');
            $this->callHook('beforeSave');

            $record = ShopIntegrationSetting::record();

            if (! empty($data['clear_google_maps_key'])) {
                $record->google_maps_api_key = null;
            } else {
                $this->persistGoogleMapsKeyFromFormIfNonEmpty($data, $record);
            }

            $record->save();
            $this->callHook('afterSave');
        } catch (Halt $exception) {
            $exception->shouldRollbackDatabaseTransaction() ?
                $this->rollBackDatabaseTransaction() :
                $this->commitDatabaseTransaction();

            return;
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();

            throw $exception;
        }

        $this->commitDatabaseTransaction();
        $this->refreshFormFromRecord();

        $maps = app(GoogleMapsApiKey::class);
        $body = $maps->isConfigured()
            ? 'Ключ активний: чекаут (JavaScript API) і карта в підвалі сайту (Embed API), якщо задані координати самовивозу. Оновіть вітрину (Ctrl+F5).'.($maps->hasStoredKey() ? ' Джерело: база.' : ' Джерело: .env.')
            : 'Ключ не заданий — на чекауті та у футері без ключа використовується OpenStreetMap (або додайте GOOGLE_MAPS_API_KEY у .env).';

        Notification::make()
            ->success()
            ->title('Збережено: Google Maps')
            ->body($body)
            ->send();
    }

    public function saveOnlinePayment(): void
    {
        try {
            $this->beginDatabaseTransaction();
            $this->callHook('beforeValidate');
            $data = $this->form->getState();
            $this->callHook('afterValidate');
            $this->callHook('beforeSave');

            $record = ShopIntegrationSetting::record();

            $provider = (string) ($data['online_payment_provider'] ?? OnlinePaymentSettings::PROVIDER_LIQPAY);
            if (! in_array($provider, [OnlinePaymentSettings::PROVIDER_LIQPAY, OnlinePaymentSettings::PROVIDER_WAYFORPAY], true)) {
                $provider = OnlinePaymentSettings::PROVIDER_LIQPAY;
            }
            $record->online_payment_provider = $provider;

            if (! empty($data['clear_wayforpay_secret'])) {
                $record->wayforpay_secret_key = null;
            } else {
                $secretFromForm = $data['wayforpay_secret_key'] ?? null;
                $trimmedSecret = is_string($secretFromForm) ? trim($secretFromForm) : '';
                if ($trimmedSecret !== '') {
                    $record->wayforpay_secret_key = $trimmedSecret;
                }
            }

            $acc = $data['wayforpay_merchant_account'] ?? null;
            $record->wayforpay_merchant_account = is_string($acc) && trim($acc) !== '' ? trim($acc) : null;

            $dom = $data['wayforpay_merchant_domain'] ?? null;
            $record->wayforpay_merchant_domain = is_string($dom) && trim($dom) !== '' ? trim($dom) : null;

            $record->save();

            if ($record->online_payment_provider === OnlinePaymentSettings::PROVIDER_WAYFORPAY) {
                if (! app(WayForPayClient::class)->isConfigured()) {
                    Notification::make()
                        ->danger()
                        ->title('WayForPay: не вистачає даних')
                        ->body('Потрібні Merchant login і Secret key (у формі або WAYFORPAY_* у .env). Домен: поле нижче або host з APP_URL.')
                        ->persistent()
                        ->send();
                    throw new Halt;
                }
            } elseif ($record->online_payment_provider === OnlinePaymentSettings::PROVIDER_LIQPAY) {
                if (! app(LiqPayClient::class)->isConfigured()) {
                    Notification::make()
                        ->warning()
                        ->title('LiqPay не налаштовано')
                        ->body('Додайте LIQPAY_PUBLIC_KEY та LIQPAY_PRIVATE_KEY у .env або оберіть WayForPay і заповніть поля нижче.')
                        ->send();
                }
            }

            $this->callHook('afterSave');
        } catch (Halt $exception) {
            $exception->shouldRollbackDatabaseTransaction() ?
                $this->rollBackDatabaseTransaction() :
                $this->commitDatabaseTransaction();

            return;
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();

            throw $exception;
        }

        $this->commitDatabaseTransaction();
        $this->refreshFormFromRecord();

        $s = app(OnlinePaymentSettings::class);
        $label = $s->providerLabel();
        $ok = $s->isConfigured();
        $body = $ok
            ? 'Активний провайдер: '.$label.'. Онлайн-оплата на чекауті увімкнена.'
            : 'Провайдер обрано, але ключі ще не повні — перевірте поля або .env.';

        Notification::make()
            ->success()
            ->title('Збережено: онлайн-оплата')
            ->body($body)
            ->send();
    }

    public function savePickup(): void
    {
        $mapsKeyUpdated = false;
        try {
            $this->beginDatabaseTransaction();
            $this->callHook('beforeValidate');
            $data = $this->form->getState();
            $this->callHook('afterValidate');
            $this->callHook('beforeSave');

            $record = ShopIntegrationSetting::record();

            $previousStoredMapsKey = is_string($record->google_maps_api_key) ? trim($record->google_maps_api_key) : '';

            $pickupAddr = $data['pickup_address'] ?? null;
            $record->pickup_address = is_string($pickupAddr) && trim($pickupAddr) !== '' ? trim($pickupAddr) : null;

            $latRaw = $data['pickup_lat'] ?? null;
            $lngRaw = $data['pickup_lng'] ?? null;
            $record->pickup_lat = ($latRaw !== null && $latRaw !== '') ? (float) $latRaw : null;
            $record->pickup_lng = ($lngRaw !== null && $lngRaw !== '') ? (float) $lngRaw : null;

            $mapsKeyFromForm = $data['google_maps_api_key'] ?? null;
            $trimmedMaps = is_string($mapsKeyFromForm) ? trim($mapsKeyFromForm) : '';
            $mapsKeyUpdated = $trimmedMaps !== '' && $trimmedMaps !== $previousStoredMapsKey;
            $this->persistGoogleMapsKeyFromFormIfNonEmpty($data, $record);

            $record->save();
            $this->callHook('afterSave');
        } catch (Halt $exception) {
            $exception->shouldRollbackDatabaseTransaction() ?
                $this->rollBackDatabaseTransaction() :
                $this->commitDatabaseTransaction();

            return;
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();

            throw $exception;
        }

        $this->commitDatabaseTransaction();
        $this->refreshFormFromRecord();

        $record = ShopIntegrationSetting::record();
        $hasAddr = is_string($record?->pickup_address) && trim($record->pickup_address) !== '';
        $hasCoords = $record?->pickup_lat !== null && $record?->pickup_lng !== null;
        $body = match (true) {
            $hasAddr && $hasCoords => 'Адреса та координати збережені — на чекауті буде текст і карта самовивозу.',
            $hasAddr => 'Адресу збережено; координати опційні для мітки на карті.',
            default => 'Дані самовивозу очищено або ще не заповнені.',
        };
        if ($mapsKeyUpdated) {
            $body .= ' Ключ Google Maps оновлено в базі.';
        }

        Notification::make()
            ->success()
            ->title('Збережено: самовивіз')
            ->body($body)
            ->send();
    }

    /**
     * Порожнє поле ключа в формі означає «не змінювати вже збережений» (як у блоці Google Maps).
     */
    private function persistGoogleMapsKeyFromFormIfNonEmpty(array $data, ShopIntegrationSetting $record): void
    {
        $mapsKeyFromForm = $data['google_maps_api_key'] ?? null;
        $trimmed = is_string($mapsKeyFromForm) ? trim($mapsKeyFromForm) : '';
        if ($trimmed !== '') {
            $record->google_maps_api_key = $trimmed;
        }
    }

    private function refreshFormFromRecord(): void
    {
        $record = ShopIntegrationSetting::record();

        $this->form->fill([
            'nova_poshta_api_key' => '',
            'clear_nova_poshta_key' => false,
            'google_maps_api_key' => app(GoogleMapsApiKey::class)->current(),
            'clear_google_maps_key' => false,
            'pickup_address' => $record?->pickup_address ?? '',
            'pickup_lat' => $record?->pickup_lat,
            'pickup_lng' => $record?->pickup_lng,
            'contact_phone' => $record?->contact_phone ?? '',
            'contact_email' => $record?->contact_email ?? '',
            'contact_instagram' => $record?->contact_instagram ?? '',
            'contact_viber' => $record?->contact_viber ?? '',
            'contact_whatsapp' => $record?->contact_whatsapp ?? '',
            'contact_telegram' => $record?->contact_telegram ?? '',
            'online_payment_provider' => $record?->online_payment_provider ?? 'liqpay',
            'wayforpay_merchant_account' => $record?->wayforpay_merchant_account ?? '',
            'wayforpay_secret_key' => '',
            'clear_wayforpay_secret' => false,
            'wayforpay_merchant_domain' => $record?->wayforpay_merchant_domain ?? '',
        ]);
    }

    public function saveHeaderContacts(): void
    {
        try {
            $this->beginDatabaseTransaction();
            $this->callHook('beforeValidate');
            $data = $this->form->getState();
            $this->callHook('afterValidate');
            $this->callHook('beforeSave');

            $record = ShopIntegrationSetting::record();
            $record->contact_phone = self::nullIfBlank($data['contact_phone'] ?? null);
            $record->contact_email = self::nullIfBlank($data['contact_email'] ?? null);
            $record->contact_instagram = self::nullIfBlank($data['contact_instagram'] ?? null);
            $record->contact_viber = self::nullIfBlank($data['contact_viber'] ?? null);
            $record->contact_whatsapp = self::nullIfBlank($data['contact_whatsapp'] ?? null);
            $record->contact_telegram = self::nullIfBlank($data['contact_telegram'] ?? null);
            $record->save();
            $this->callHook('afterSave');
        } catch (Halt $exception) {
            $exception->shouldRollbackDatabaseTransaction() ?
                $this->rollBackDatabaseTransaction() :
                $this->commitDatabaseTransaction();

            return;
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();

            throw $exception;
        }

        $this->commitDatabaseTransaction();
        $this->refreshFormFromRecord();

        $items = ShopHeaderContacts::itemsFrom(ShopIntegrationSetting::record());
        $body = count($items) > 0
            ? 'У шапці відображаються '.count($items).' контакт(ів) зі збережених даних (у разі невалідного значення воно на сайті не показується).'
            : 'Усі поля очищено — блок контактів у шапці приховано.';

        Notification::make()
            ->success()
            ->title('Збережено: контакти в шапці')
            ->body($body)
            ->send();
    }

    private static function nullIfBlank(mixed $v): ?string
    {
        if (! is_string($v)) {
            return null;
        }
        $t = trim($v);

        return $t === '' ? null : $t;
    }

    /**
     * Картка статусу інтеграції (зелений / червоний акцент, іконка, деталі).
     *
     * @param  list<string>  $detailLines
     */
    private static function integrationStatusHtml(bool $ok, string $labelWhenOk, string $labelWhenBad, array $detailLines): HtmlString
    {
        $label = $ok ? $labelWhenOk : $labelWhenBad;
        $badgeText = $ok ? 'Активно' : 'Не готово';

        // Явні width/height + inline style: у панелі Filament інколи діє svg { max-width:100% }, через що іконка розтягується на всю колонку.
        $svgAttrs = ' xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="24" height="24" style="width:24px;height:24px;max-width:24px;max-height:24px;display:block;flex-shrink:0;" aria-hidden="true"';

        $icon = $ok
            ? '<svg'.$svgAttrs.' class="text-emerald-600 dark:text-emerald-400"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>'
            : '<svg'.$svgAttrs.' class="text-rose-600 dark:text-rose-400"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>';

        $panelBorder = $ok
            ? 'border-emerald-200/90 dark:border-emerald-800/50'
            : 'border-rose-200/90 dark:border-rose-800/50';
        $headBg = $ok
            ? 'bg-gradient-to-r from-emerald-50/95 to-white dark:from-emerald-950/40 dark:to-gray-900/60'
            : 'bg-gradient-to-r from-rose-50/95 to-white dark:from-rose-950/40 dark:to-gray-900/60';

        $badgeClass = $ok
            ? 'bg-emerald-100 text-emerald-900 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-500/20 dark:text-emerald-100 dark:ring-emerald-400/25'
            : 'bg-rose-100 text-rose-900 ring-1 ring-inset ring-rose-600/20 dark:bg-rose-500/20 dark:text-rose-100 dark:ring-rose-400/25';

        $dotClass = $ok ? 'bg-emerald-500 dark:bg-emerald-400' : 'bg-rose-500 dark:bg-rose-400';

        $items = collect($detailLines)->map(function (string $t) use ($dotClass): string {
            return '<li class="flex gap-3 py-2 first:pt-1 last:pb-1">'
                .'<span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full '.$dotClass.' opacity-80" aria-hidden="true"></span>'
                .'<span class="min-w-0 flex-1 leading-relaxed text-gray-700 dark:text-gray-200">'.e($t).'</span>'
                .'</li>';
        })->implode('');

        $html = '<div class="integration-status-card overflow-hidden rounded-xl border '.$panelBorder.' bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-950/50 dark:ring-white/10">'
            .'<div class="flex items-center gap-3 px-4 py-3.5 '.$headBg.'">'
            .'<div class="flex shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" style="width:2.75rem;height:2.75rem;min-width:2.75rem;max-width:2.75rem;min-height:2.75rem;max-height:2.75rem;">'
            .$icon
            .'</div>'
            .'<div class="min-w-0 flex-1">'
            .'<p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Стан інтеграції</p>'
            .'<p class="mt-0.5 text-sm font-semibold leading-snug text-gray-900 dark:text-white">'.e($label).'</p>'
            .'</div>'
            .'<span class="shrink-0 rounded-lg px-2.5 py-1 text-xs font-bold '.$badgeClass.'">'.e($badgeText).'</span>'
            .'</div>'
            .'<ul class="m-0 list-none border-t border-gray-100 bg-gray-50/80 px-4 py-1 dark:border-gray-800 dark:bg-gray-900/30 text-sm">'
            .$items
            .'</ul>'
            .'</div>';

        return new HtmlString($html);
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Нова Пошта')
                    ->description('Необов’язково: лише якщо потрібні підказки міста й списку відділень на чекауті. Без ключа покупці вводять місто й відділення вручну. Ключ API 2.0 безкоштовний у кабінеті novaposhta.ua. Збереження цього блоку не змінює адресу самовивозу й ключ карти.')
                    ->footerActionsAlignment(Alignment::End)
                    ->footerActions([
                        Action::make('saveNovaPoshta')
                            ->label('Зберегти блок')
                            ->action(fn () => $this->saveNovaPoshta()),
                    ])
                    ->schema([
                        ViewField::make('np_integration_status')
                            ->hiddenLabel()
                            ->view('filament.admin.shop-integration-status')
                            ->viewData(function (): array {
                                $np = app(NovaPoshtaApiKey::class);
                                $envHas = trim((string) config('services.nova_poshta.api_key')) !== '';
                                $lines = [
                                    $np->hasStoredKey()
                                        ? 'Ключ у базі: так (зашифровано).'
                                        : 'Ключ у базі: ні.',
                                    $envHas
                                        ? 'Змінна NOVA_POSHTA_API_KEY у .env: задана.'
                                        : 'Змінна NOVA_POSHTA_API_KEY у .env: не задана.',
                                    $np->isConfigured()
                                        ? 'Для чекауту: ключ є — автопідказки міста й відділень увімкнені.'
                                        : 'Для чекауту: ключа немає — лише ручний ввід.',
                                ];

                                return [
                                    'html' => self::integrationStatusHtml(
                                        $np->isConfigured(),
                                        'Працює — API Нової Пошти підключено',
                                        'Не налаштовано — підказки міста й відділень вимкнені',
                                        $lines,
                                    ),
                                ];
                            })
                            ->dehydrated(false),
                        TextInput::make('nova_poshta_api_key')
                            ->label('Ключ API')
                            ->password()
                            ->revealable()
                            ->maxLength(512)
                            ->saved(true)
                            ->dehydrated(true)
                            ->helperText('Ключ виглядає як довгий UUID (зазвичай 36 символів). Вставте його повністю з my.novaposhta.ua → Безпека → API 2.0. Залиште поле порожнім, щоб не змінювати вже збережений ключ.')
                            ->autocomplete(false),
                        Checkbox::make('clear_nova_poshta_key')
                            ->label('Видалити збережений у базі ключ (далі використовуватиметься лише .env)')
                            ->default(false),
                    ])
                    ->columnSpanFull(),
                Section::make('Google Maps (чекаут)')
                    ->description('Необов’язково: карта відділень Нової Пошти на оформленні замовлення та вбудована карта в підвалі сайту (за координатами самовивозу). У Google Cloud увімкніть Maps JavaScript API (чекаут) і Maps Embed API (футер). Обмежте ключ по HTTP referrers. Щоб записати ключ у базу, натисніть «Зберегти блок» тут або збережіть блок «Самовивіз», якщо поле ключа вже заповнене.')
                    ->footerActionsAlignment(Alignment::End)
                    ->footerActions([
                        Action::make('saveGoogleMaps')
                            ->label('Зберегти блок')
                            ->action(fn () => $this->saveGoogleMaps()),
                    ])
                    ->schema([
                        ViewField::make('maps_integration_status')
                            ->hiddenLabel()
                            ->view('filament.admin.shop-integration-status')
                            ->viewData(function (): array {
                                $maps = app(GoogleMapsApiKey::class);
                                $envHas = trim((string) config('services.google.maps_api_key')) !== '';
                                $lines = [
                                    $maps->hasStoredKey()
                                        ? 'Ключ у базі: так (зашифровано).'
                                        : 'Ключ у базі: ні.',
                                    $envHas
                                        ? 'Змінна GOOGLE_MAPS_API_KEY у .env: задана.'
                                        : 'Змінна GOOGLE_MAPS_API_KEY у .env: не задана.',
                                    $maps->isConfigured()
                                        ? 'Для чекауту: Google Maps на карті відділень увімкнено (оновіть сторінку після зміни ключа).'
                                        : 'Для чекауту: карти Google немає — використовуються тайли OpenStreetMap.',
                                ];

                                return [
                                    'html' => self::integrationStatusHtml(
                                        $maps->isConfigured(),
                                        'Працює — карта Google Maps підключена',
                                        'Не налаштовано — на карті використовується OSM',
                                        $lines,
                                    ),
                                ];
                            })
                            ->dehydrated(false),
                        TextInput::make('google_maps_api_key')
                            ->label('Ключ Maps JavaScript API')
                            ->password()
                            ->revealable()
                            ->maxLength(512)
                            ->saved(true)
                            ->dehydrated(true)
                            ->helperText('Підставляється з бази (якщо порожньо там — з .env). Можна залишити як є. Порожнє поле при «Зберегти блок» тут — не змінює збережений у базі ключ; щоб видалити ключ з бази — прапорець нижче.')
                            ->autocomplete(false),
                        Checkbox::make('clear_google_maps_key')
                            ->label('Видалити збережений у базі ключ карти (далі лише .env)')
                            ->default(false),
                    ])
                    ->columnSpanFull(),
                Section::make('Онлайн-оплата (чекаут)')
                    ->description('LiqPay зазвичай налаштовується через LIQPAY_* у .env. Для WayForPay можна зберегти merchant, secret і домен у базі (secret — зашифровано). Callback для WayForPay: маршрут додатку `payments/wayforpay/callback` — вкажіть повний URL у кабінеті WayForPay згідно з їхньою інструкцією.')
                    ->footerActionsAlignment(Alignment::End)
                    ->footerActions([
                        Action::make('saveOnlinePayment')
                            ->label('Зберегти блок')
                            ->action(fn () => $this->saveOnlinePayment()),
                    ])
                    ->schema([
                        ViewField::make('payment_integration_status')
                            ->hiddenLabel()
                            ->view('filament.admin.shop-integration-status')
                            ->viewData(function (): array {
                                $s = app(OnlinePaymentSettings::class);
                                $lines = [
                                    'Активний провайдер: '.$s->providerLabel().'.',
                                    $s->isConfigured()
                                        ? 'Поточний провайдер налаштовано — онлайн-оплата на чекауті увімкнена (за правилами замовлення).'
                                        : 'Активний провайдер не повністю налаштований — перевірте поля цього блоку або .env.',
                                ];
                                if ($s->provider() === OnlinePaymentSettings::PROVIDER_WAYFORPAY) {
                                    $w = app(WayForPayClient::class);
                                    $lines[] = 'WayForPay: merchant '.($w->merchantAccount() !== '' ? 'задано' : 'не задано').', secret '.($w->secretKey() !== '' ? 'задано' : 'не задано').', домен '.($w->merchantDomainName() !== '' ? '«'.$w->merchantDomainName().'»' : 'не визначено').'.';
                                } else {
                                    $lines[] = 'LiqPay: ключі в .env '.(app(LiqPayClient::class)->isConfigured() ? 'задані' : 'не задані').'.';
                                }

                                return [
                                    'html' => self::integrationStatusHtml(
                                        $s->isConfigured(),
                                        'Працює — онлайн-оплата готова ('.$s->providerLabel().')',
                                        'Не налаштовано — оплата карткою на чекауті недоступна',
                                        $lines,
                                    ),
                                ];
                            })
                            ->dehydrated(false),
                        Select::make('online_payment_provider')
                            ->label('Провайдер на чекауті')
                            ->options([
                                OnlinePaymentSettings::PROVIDER_LIQPAY => 'LiqPay',
                                OnlinePaymentSettings::PROVIDER_WAYFORPAY => 'WayForPay',
                            ])
                            ->required()
                            ->native(false),
                        TextInput::make('wayforpay_merchant_account')
                            ->label('WayForPay — Merchant login (merchantAccount)')
                            ->maxLength(255)
                            ->helperText('Логін мерчанта з кабінету WayForPay. Для режиму LiqPay не використовується.'),
                        TextInput::make('wayforpay_secret_key')
                            ->label('WayForPay — Secret key')
                            ->password()
                            ->revealable()
                            ->maxLength(512)
                            ->saved(true)
                            ->dehydrated(true)
                            ->helperText('Порожнє поле не змінює вже збережений у базі ключ. Зберігається зашифровано.')
                            ->autocomplete(false),
                        Checkbox::make('clear_wayforpay_secret')
                            ->label('Видалити збережений у базі Secret key WayForPay')
                            ->default(false),
                        TextInput::make('wayforpay_merchant_domain')
                            ->label('WayForPay — Домен сайту (merchantDomainName)')
                            ->maxLength(255)
                            ->helperText('Наприклад example.com без https. Якщо порожньо — береться host з APP_URL або WAYFORPAY_MERCHANT_DOMAIN у .env.'),
                    ])
                    ->columnSpanFull(),
                Section::make('Самовивіз з магазину')
                    ->description('Показується на оформленні замовлення, коли покупець обирає «Самовивіз». Якщо в блоці «Google Maps» ви ввели новий ключ і натискаєте лише «Зберегти блок» тут — ключ теж збережеться в базу (не потрібно двічі зберігати).')
                    ->footerActionsAlignment(Alignment::End)
                    ->footerActions([
                        Action::make('savePickup')
                            ->label('Зберегти блок')
                            ->action(fn () => $this->savePickup()),
                    ])
                    ->schema([
                        ViewField::make('pickup_integration_status')
                            ->hiddenLabel()
                            ->view('filament.admin.shop-integration-status')
                            ->viewData(function (): array {
                                $r = ShopIntegrationSetting::record();
                                $hasAddr = is_string($r?->pickup_address) && trim((string) $r->pickup_address) !== '';
                                $hasCoords = $r?->pickup_lat !== null && $r?->pickup_lng !== null;
                                $lines = [
                                    $hasAddr
                                        ? 'Адреса для покупця: задана.'
                                        : 'Адреса для покупця: не задана.',
                                    $hasCoords
                                        ? 'Координати для карти: задані (потрібні для карти в підвалі сайту).'
                                        : 'Координати для карти: не задані — вбудована карта в підвалі без координат не показується.',
                                    match (true) {
                                        $hasAddr && $hasCoords => 'Підсумок: текст і мітка на карті на чекауті.',
                                        $hasAddr => 'Підсумок: лише текст адреси.',
                                        default => 'Підсумок: блок самовивозу для покупця порожній.',
                                    },
                                ];

                                return [
                                    'html' => self::integrationStatusHtml(
                                        $hasAddr,
                                        'Заповнено — самовивіз показується покупцю',
                                        'Не заповнено — адреса самовивозу відсутня',
                                        $lines,
                                    ),
                                ];
                            })
                            ->dehydrated(false),
                        Textarea::make('pickup_address')
                            ->label('Адреса пункту видачі')
                            ->rows(3)
                            ->maxLength(2000)
                            ->extraInputAttributes(['id' => 'filament-pickup-address-field'])
                            ->columnSpanFull(),
                        ViewField::make('pickup_map')
                            ->view('filament.admin.pickup-location-map')
                            ->viewData(fn (): array => [
                                'googleMapsKey' => app(GoogleMapsApiKey::class)->current(),
                            ])
                            ->dehydrated(false)
                            ->label('Мітка на карті')
                            ->helperText('Оберіть точку на карті або скористайтеся пошуком за адресою з поля вище. Якщо задано ключ Google Maps (блок вище або .env), тут відкриється Google; інакше — OpenStreetMap.')
                            ->columnSpanFull(),
                        TextInput::make('pickup_lat')
                            ->label('Широта (lat)')
                            ->numeric()
                            ->extraInputAttributes(['id' => 'filament-pickup-lat-field'])
                            ->helperText('Можна ввести вручну або вибрати на карті вище.'),
                        TextInput::make('pickup_lng')
                            ->label('Довгота (lng)')
                            ->numeric()
                            ->extraInputAttributes(['id' => 'filament-pickup-lng-field'])
                            ->helperText('Можна ввести вручну або вибрати на карті вище.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Контакти (шапка сайту)')
                    ->description('Телефон, пошта та посилання в лівому блоці шапки. Порожнє поле — відповідна іконка на сайті не з’являється. Якщо нічого не заповнено, весь блок контактів сховано.')
                    ->footerActionsAlignment(Alignment::End)
                    ->footerActions([
                        Action::make('saveHeaderContacts')
                            ->label('Зберегти блок')
                            ->action(fn () => $this->saveHeaderContacts()),
                    ])
                    ->schema([
                        ViewField::make('header_integration_status')
                            ->hiddenLabel()
                            ->view('filament.admin.shop-integration-status')
                            ->viewData(function (): array {
                                $r = ShopIntegrationSetting::record();
                                $items = ShopHeaderContacts::itemsFrom($r);
                                $ok = count($items) > 0;
                                $lines = [
                                    $ok
                                        ? 'У шапці відображається '.count($items).' контакт(ів).'
                                        : 'Жодне поле контактів не заповнено — блок у шапці прихований.',
                                    'Невалідні значення на сайті не показуються (перевірка при відображенні).',
                                ];

                                return [
                                    'html' => self::integrationStatusHtml(
                                        $ok,
                                        'Працює — контакти в шапці показуються',
                                        'Не налаштовано — блок контактів у шапці приховано',
                                        $lines,
                                    ),
                                ];
                            })
                            ->dehydrated(false),
                        TextInput::make('contact_phone')
                            ->label('Телефон')
                            ->maxLength(64)
                            ->helperText('Текст для відображення, напр. +38 099 403 43 59. Для дзвінка використовуються цифри з номера.'),
                        TextInput::make('contact_email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('contact_instagram')
                            ->label('Instagram')
                            ->maxLength(512)
                            ->helperText('Повне https-посилання або нік, напр. @zoogle / zoogle.'),
                        TextInput::make('contact_viber')
                            ->label('Viber')
                            ->maxLength(512)
                            ->helperText('Посилання https://… на viber.com / viber.me або deep link, що починається з viber:'),
                        TextInput::make('contact_whatsapp')
                            ->label('WhatsApp')
                            ->maxLength(512)
                            ->helperText('Напр. https://wa.me/38099… або посилання з api.whatsapp.com / whatsapp.com'),
                        TextInput::make('contact_telegram')
                            ->label('Telegram')
                            ->maxLength(255)
                            ->helperText('https://t.me/… / telegram.me/… або @username (латиниця, цифри, _)'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return list<Action>
     */
    protected function getFormActions(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return static::$title ?? 'Інтеграції';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('noopFormSubmit')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment($this->getFormActionsAlignment())
                    ->fullWidth($this->hasFullWidthFormActions())
                    ->sticky($this->areFormActionsSticky())
                    ->key('form-actions'),
            ]);
    }

    protected function hasFullWidthFormActions(): bool
    {
        return false;
    }
}
