<?php

namespace App\Http\Controllers;

use App\Filament\Admin\Resources\ProductQuestions\ProductQuestionResource;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Rules\NoProfanity;
use App\Support\StorefrontModerators;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/**
 * Питання покупця з картки товару. Адміністрація отримує сповіщення в адмінці й відповідає там.
 */
class ProductQuestionController extends Controller
{
    /** Приховане поле: люди його не бачать і не заповнюють, боти — заповнюють. */
    private const HONEYPOT_FIELD = 'website';

    private const NOTIFICATION_PREVIEW = 120;

    public function store(Request $request, string $slug): RedirectResponse
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->whereIn('product_type', OptionGroup::catalogListingProductTypes())
            ->where('is_available', true)
            ->firstOrFail();

        $back = route('catalog.show', $product->slug).'#questions';

        if (filled($request->input(self::HONEYPOT_FIELD))) {
            return redirect()->to($back);
        }

        $validator = Validator::make($request->all(), [
            'author_name' => ['required', 'string', 'min:2', 'max:80', new NoProfanity],
            'email' => ['nullable', 'email', 'max:160'],
            'body' => ['required', 'string', 'min:5', 'max:1000', new NoProfanity],
        ]);
        if ($validator->fails()) {
            return redirect()->to($back)->withErrors($validator, 'question')->withInput();
        }
        $data = $validator->validated();

        $question = ProductQuestion::query()->create([
            'product_id' => $product->id,
            'user_id' => $request->user()?->id,
            'author_name' => trim(strip_tags($data['author_name'])),
            'email' => filled($data['email'] ?? null) ? trim($data['email']) : ($request->user()?->email ?: null),
            'body' => trim(strip_tags($data['body'])),
            'status' => ProductQuestion::STATUS_PENDING,
        ]);

        $this->notifyStaff($question, $product);

        return redirect()->to($back)->with('question_sent', true);
    }

    public function destroy(Request $request, string $slug, ProductQuestion $question): RedirectResponse
    {
        abort_unless(ProductQuestion::canBeModeratedBy($request->user()), 403);

        $product = Product::query()->where('slug', $slug)->firstOrFail();
        abort_unless((int) $question->product_id === (int) $product->id, 404);

        $question->delete();

        return redirect()->to(route('catalog.show', $product->slug).'#questions')->with('question_deleted', true);
    }

    /** Сповіщення в дзвіночку адмінки. Збій сповіщення не заважає покупцю надіслати питання. */
    private function notifyStaff(ProductQuestion $question, Product $product): void
    {
        try {
            Notification::make()
                ->title('Нове питання: '.Str::limit(mt((string) $product->title), 60))
                ->body($question->author_name.': '.Str::limit($question->body, self::NOTIFICATION_PREVIEW))
                ->icon('heroicon-o-question-mark-circle')
                ->iconColor('warning')
                ->actions([
                    Action::make('answer')
                        ->label('Відповісти')
                        ->button()
                        ->url(ProductQuestionResource::getUrl('edit', ['record' => $question])),
                ])
                ->sendToDatabase(StorefrontModerators::all());
        } catch (Throwable $e) {
            Log::warning('product_question_notification_failed', [
                'question_id' => $question->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
