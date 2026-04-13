<?php

namespace App\Support\Pricing;

/**
 * Єдина інтерпретація ціни варіанта для вітрини, кошика та оформлення.
 *
 * @phpstan-type SerializedQuote array{regular: float, effective: float, strike: float|null}
 */
final readonly class VariantPriceQuote
{
    public function __construct(
        public float $regularPrice,
        public float $effectivePrice,
        public ?float $strikePrice,
    ) {}

    public function isOnSale(): bool
    {
        if ($this->strikePrice === null) {
            return false;
        }

        return $this->strikePrice > $this->effectivePrice + 0.0001;
    }

    /**
     * @return SerializedQuote
     */
    public function toArray(): array
    {
        return [
            'regular' => $this->regularPrice,
            'effective' => $this->effectivePrice,
            'strike' => $this->strikePrice,
        ];
    }
}
