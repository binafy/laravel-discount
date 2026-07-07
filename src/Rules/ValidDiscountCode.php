<?php

namespace Binafy\LaravelDiscount\Rules;

use Binafy\LaravelDiscount\DiscountManager;
use Binafy\LaravelDiscount\Exceptions\DiscountException;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Database\Eloquent\Model;

class ValidDiscountCode implements Rule
{
    protected string $message = 'The discount code is not valid.';

    public function __construct(
        protected float $orderAmount = 0,
        protected Model|int|null $user = null,
        protected ?string $sessionId = null,
    ) {
    }

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
                $this->sessionId
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
