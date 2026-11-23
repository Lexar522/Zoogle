<?php

namespace App\Http\Controllers;

use App\Models\ShopSponsorshipSetting;
use App\Models\SponsorAnimal;
use App\Models\Sponsorship;
use App\Support\SponsorPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SponsorshipController extends Controller
{
    /** Фото опікуна: до 5 МБ, лише звичайні формати зображень. */
    private const PHOTO_MAX_KB = 5120;

    /** Приховане поле: людина його не бачить, а боти заповнюють. */
    private const HONEYPOT_FIELD = 'website';

    public function index(): View
    {
        $this->ensureEnabled();

        return view('sponsorship.index', [
            'animals' => SponsorAnimal::query()->visible()->get(),
        ]);
    }

    public function show(SponsorAnimal $animal): View
    {
        $this->ensureEnabled();
        $this->ensureVisible($animal);

        return view('sponsorship.show', [
            'animal' => $animal,
            'terms' => $animal->offeredTerms(),
            'sponsors' => $animal->sponsorships()->publiclyActive()->get(),
        ]);
    }

    public function apply(Request $request, SponsorAnimal $animal): View|RedirectResponse
    {
        $this->ensureEnabled();
        $this->ensureVisible($animal);

        $terms = $animal->offeredTerms();
        $term = (string) $request->query('term', '');
        if (! isset($terms[$term])) {
            return redirect()->route('sponsorship.show', $animal);
        }

        return view('sponsorship.apply', [
            'animal' => $animal,
            'term' => $term,
            'price' => $terms[$term],
        ]);
    }

    public function store(Request $request, SponsorAnimal $animal): RedirectResponse
    {
        $this->ensureEnabled();
        $this->ensureVisible($animal);

        if (filled($request->input(self::HONEYPOT_FIELD))) {
            return redirect()->route('sponsorship.show', $animal);
        }

        $terms = $animal->offeredTerms();
        $data = $request->validate([
            'term' => ['required', 'string', Rule::in(array_keys($terms))],
            'sponsor_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^(?=(?:\D*\d){9,15}\D*$)\+?[\d\s()\-]+$/'],
            'message' => ['nullable', 'string', 'max:1000'],
            'show_publicly' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::PHOTO_MAX_KB],
        ]);

        $photoPath = $request->hasFile('photo') ? SponsorPhoto::store($request->file('photo')) : null;

        $sponsorship = Sponsorship::query()->create([
            'sponsor_animal_id' => $animal->id,
            'animal_name' => $animal->name,
            'term' => $data['term'],
            'months' => Sponsorship::TERMS[$data['term']]['months'],
            'amount' => $terms[$data['term']],
            'sponsor_name' => trim($data['sponsor_name']),
            'email' => trim($data['email']),
            'phone' => filled($data['phone'] ?? null) ? trim($data['phone']) : null,
            'message' => filled($data['message'] ?? null) ? trim($data['message']) : null,
            'show_publicly' => (bool) ($data['show_publicly'] ?? false),
            'photo_path' => $photoPath ?: null,
            'photo_status' => $photoPath ? Sponsorship::PHOTO_PENDING : Sponsorship::PHOTO_NONE,
            'status' => Sponsorship::STATUS_PENDING,
        ]);

        return redirect()->route('sponsorship.payment', $sponsorship->public_token);
    }

    public function payment(string $token): View
    {
        $this->ensureEnabled();

        $sponsorship = Sponsorship::query()->with('animal')->where('public_token', $token)->firstOrFail();

        return view('sponsorship.payment', [
            'sponsorship' => $sponsorship,
            'jarUrl' => $this->jarUrlWithAmount($sponsorship),
        ]);
    }

    private function ensureEnabled(): void
    {
        abort_unless(ShopSponsorshipSetting::isEnabled(), 404);
    }

    private function ensureVisible(SponsorAnimal $animal): void
    {
        abort_unless($animal->is_active, 404);
    }

    /**
     * Посилання на банку з сумою й номером заявки в коментарі (параметри Monobank a і t).
     */
    private function jarUrlWithAmount(Sponsorship $sponsorship): ?string
    {
        $jar = $sponsorship->animal?->resolvedJarUrl() ?? ShopSponsorshipSetting::resolved()['jar_url'];
        if ($jar === null) {
            return null;
        }

        $amount = (float) $sponsorship->amount;
        $query = http_build_query([
            'a' => fmod($amount, 1.0) === 0.0 ? (int) $amount : number_format($amount, 2, '.', ''),
            't' => $sponsorship->reference().' '.$sponsorship->animal_name,
        ]);

        return $jar.(str_contains($jar, '?') ? '&' : '?').$query;
    }
}
