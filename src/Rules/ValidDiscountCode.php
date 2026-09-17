<?php

namespace Binafy\LaravelDiscount\Rules;

use Binafy\LaravelDiscount\DiscountManager;
use Binafy\LaravelDiscount\Exceptions\DiscountException;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Database\Eloquent\Model;

class ValidDiscountCode implements Rule
{
    protected string $message = 'The discount code is not valid.';

    /**
     * @param  float  $orderAmount  The total the code would be applied to.
     * @param  int  $quantity  How many items that total covers, which "buy X
     *                         get Y" discounts and item-count conditions need.
     * @param  array<string, mixed>  $payload  Anything the discount's conditions
     *                                         need, most often the order's items.
     */
    public function __construct(
        protected float $orderAmount = 0,
        protected Model|int|null $user = null,
        protected ?string $sessionId = null,
        protected int $quantity = 1,
        protected array $payload = [],
    ) {}

    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value): bool
    {
        $manager = app(DiscountManager::class);

        try {
            $manager->validate(
                $manager->findByCode((string) $value),
                $this->orderAmount,
                $this->user,
                $this->sessionId,
                $this->quantity,
                $this->payload
            );

            return true;
        } catch (DiscountException $exception) {
            $this->message = $exception->getMessage();

            return false;
        }
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return $this->message;
    }
}
