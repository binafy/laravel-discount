<?php

use Binafy\LaravelDiscount\Conditions\FirstPurchaseCondition;
use Binafy\LaravelDiscount\Conditions\MinimumItemCountCondition;
use Binafy\LaravelDiscount\Contracts\DiscountCondition;
use Binafy\LaravelDiscount\DiscountManager;
use Binafy\LaravelDiscount\Enums\DiscountType;
use Binafy\LaravelDiscount\Exceptions\DiscountConditionFailedException;
use Binafy\LaravelDiscount\Exceptions\InvalidDiscountConditionsException;
use Binafy\LaravelDiscount\Facades\LaravelDiscount;
use Binafy\LaravelDiscount\Models\Discount;
use Binafy\LaravelDiscount\Support\ConditionFactory;
use Binafy\LaravelDiscount\Support\DiscountContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Models\Product;
use Tests\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->manager = app(DiscountManager::class);

    if (! Schema::hasTable('products')) {
        Schema::create('products', function ($table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 15, 2)->default(0);
            $table->unsignedBigInteger('category_id')->nullable();
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('orders')) {
        Schema::create('orders', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }
});

afterEach(fn () => ConditionFactory::flushAliases());

function makeConditional(array $rules, array $attributes = []): Discount
{
    return Discount::query()->create(array_merge([
        'type' => DiscountType::Percentage,
        'value' => 10,
        'conditions' => ['rules' => $rules],
    ], $attributes));
}

function makeConditionUser(string $email = 'milwad.dev@gmail.com'): User
{
    return User::query()->create([
        'name' => 'Milwad',
        'email' => $email,
        'password' => bcrypt('password'),
    ]);
}

/*
|--------------------------------------------------------------------------
| Minimum Item Count
|--------------------------------------------------------------------------
*/

test('minimum item count passes when the order carries enough items', function () {
    $discount = makeConditional([['type' => 'minimum_item_count', 'count' => 3]]);

    expect($this->manager->isValid($discount, 500, quantity: 3))->toBeTrue()
        ->and($this->manager->isValid($discount, 500, quantity: 5))->toBeTrue();
});

test('minimum item count fails when the order is too small', function () {
    $discount = makeConditional([['type' => 'minimum_item_count', 'count' => 3]]);

    expect($this->manager->isValid($discount, 500, quantity: 2))->toBeFalse();
});

test('a failing condition names its reason', function () {
    $this->manager->validate(makeConditional([['type' => 'minimum_item_count', 'count' => 3]]), 500, quantity: 1);
})->throws(DiscountConditionFailedException::class, 'This discount requires at least 3 items.');

test('the failed condition is carried on the exception', function () {
    try {
        $this->manager->validate(makeConditional([['type' => 'minimum_item_count', 'count' => 3]]), 500);
    } catch (DiscountConditionFailedException $e) {
        expect($e->getCondition())->toBeInstanceOf(MinimumItemCountCondition::class)
            ->and($e->getDiscount()->id)->toBeInt();

        return;
    }

    $this->fail('The condition should have failed.');
});

/*
|--------------------------------------------------------------------------
| Category
|--------------------------------------------------------------------------
*/

test('category passes when any item is in a covered category', function () {
    $discount = makeConditional([['type' => 'category', 'categories' => [7, 9]]]);

    $items = [
        Product::query()->create(['name' => 'Laptop', 'price' => 800, 'category_id' => 3]),
        Product::query()->create(['name' => 'Mouse', 'price' => 100, 'category_id' => 7]),
    ];

    expect($this->manager->isValid($discount, 900, payload: ['items' => $items]))->toBeTrue();
});

test('category fails when no item is in a covered category', function () {
    $discount = makeConditional([['type' => 'category', 'categories' => [7, 9]]]);

    $items = [Product::query()->create(['name' => 'Laptop', 'price' => 800, 'category_id' => 3])];

    expect($this->manager->isValid($discount, 800, payload: ['items' => $items]))->toBeFalse();
});

test('category can require every item to match', function () {
    $discount = makeConditional([['type' => 'category', 'categories' => [7], 'match' => 'all']]);

    $mixed = [
        Product::query()->create(['name' => 'Laptop', 'price' => 800, 'category_id' => 3]),
        Product::query()->create(['name' => 'Mouse', 'price' => 100, 'category_id' => 7]),
    ];
    $uniform = [Product::query()->create(['name' => 'Pad', 'price' => 20, 'category_id' => 7])];

    expect($this->manager->isValid($discount, 900, payload: ['items' => $mixed]))->toBeFalse()
        ->and($this->manager->isValid($discount, 20, payload: ['items' => $uniform]))->toBeTrue();
});

test('category fails when the order has no items to check', function () {
    $discount = makeConditional([['type' => 'category', 'categories' => [7]]]);

    expect($this->manager->isValid($discount, 900))->toBeFalse();
});

test('category reads plain arrays as well as models', function () {
    $discount = makeConditional([['type' => 'category', 'categories' => [7]]]);

    expect($this->manager->isValid($discount, 900, payload: ['items' => [['category_id' => 7]]]))->toBeTrue();
});

test('category can follow a relation with dot notation', function () {
    $discount = makeConditional([
        ['type' => 'category', 'categories' => [7], 'attribute' => 'category.id'],
    ]);

    $items = [['category' => ['id' => 7]]];

    expect($this->manager->isValid($discount, 900, payload: ['items' => $items]))->toBeTrue();
});

test('category reads the attribute configured in the config file', function () {
    config()->set('laravel-discount.conditions.category.attribute', 'group_id');

    $discount = makeConditional([['type' => 'category', 'categories' => [7]]]);

    expect($this->manager->isValid($discount, 900, payload: ['items' => [['group_id' => 7]]]))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| First Purchase
|--------------------------------------------------------------------------
*/

test('first purchase passes for a user with no orders', function () {
    config()->set('laravel-discount.conditions.first_purchase.model', TestOrder::class);

    $discount = makeConditional([['type' => 'first_purchase']]);

    expect($this->manager->isValid($discount, 500, makeConditionUser()))->toBeTrue();
});

test('first purchase fails once the user has ordered', function () {
    config()->set('laravel-discount.conditions.first_purchase.model', TestOrder::class);

    $user = makeConditionUser();
    TestOrder::query()->create(['user_id' => $user->id]);

    $discount = makeConditional([['type' => 'first_purchase']]);

    expect($this->manager->isValid($discount, 500, $user))->toBeFalse();
});

test('first purchase does not confuse two users', function () {
    config()->set('laravel-discount.conditions.first_purchase.model', TestOrder::class);

    $buyer = makeConditionUser('buyer@example.com');
    $newcomer = makeConditionUser('newcomer@example.com');
    TestOrder::query()->create(['user_id' => $buyer->id]);

    $discount = makeConditional([['type' => 'first_purchase']]);

    expect($this->manager->isValid($discount, 500, $buyer))->toBeFalse()
        ->and($this->manager->isValid($discount, 500, $newcomer))->toBeTrue();
});

test('first purchase never passes for a guest', function () {
    config()->set('laravel-discount.conditions.first_purchase.model', TestOrder::class);

    $discount = makeConditional([['type' => 'first_purchase']]);

    expect($this->manager->isValid($discount, 500, sessionId: 'guest-session'))->toBeFalse();
});

test('first purchase complains when no purchase model is configured', function () {
    config()->set('laravel-discount.conditions.first_purchase.model', null);

    $this->manager->validate(makeConditional([['type' => 'first_purchase']]), 500, makeConditionUser());
})->throws(InvalidDiscountConditionsException::class, 'The first purchase condition needs a purchase model.');

test('first purchase can override the model per rule', function () {
    $user = makeConditionUser();

    $discount = makeConditional([
        ['type' => 'first_purchase', 'model' => TestOrder::class, 'column' => 'user_id'],
    ]);

    expect($this->manager->isValid($discount, 500, $user))->toBeTrue();

    TestOrder::query()->create(['user_id' => $user->id]);

    expect($this->manager->isValid($discount, 500, $user))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Combining rules
|--------------------------------------------------------------------------
*/

test('every rule must pass by default', function () {
    $discount = makeConditional([
        ['type' => 'minimum_item_count', 'count' => 2],
        ['type' => 'category', 'categories' => [7]],
    ]);

    $items = [['category_id' => 7]];

    expect($this->manager->isValid($discount, 900, quantity: 2, payload: ['items' => $items]))->toBeTrue()
        ->and($this->manager->isValid($discount, 900, quantity: 1, payload: ['items' => $items]))->toBeFalse();
});

test('rules match any lets a single passing rule carry the discount', function () {
    $discount = makeConditional([
        ['type' => 'minimum_item_count', 'count' => 2],
        ['type' => 'category', 'categories' => [7]],
    ]);
    $discount->update(['conditions' => $discount->conditions + ['rules_match' => 'any']]);

    // The category fails but the item count passes.
    expect($this->manager->isValid($discount, 900, quantity: 5))->toBeTrue();
});

test('rules match any still fails when every rule fails', function () {
    $discount = makeConditional([
        ['type' => 'minimum_item_count', 'count' => 5],
        ['type' => 'category', 'categories' => [7]],
    ]);
    $discount->update(['conditions' => $discount->conditions + ['rules_match' => 'any']]);

    expect($this->manager->isValid($discount, 900, quantity: 1))->toBeFalse();
});

test('a discount with no rules is unaffected', function () {
    $discount = Discount::query()->create(['type' => DiscountType::Percentage, 'value' => 10]);

    expect($this->manager->isValid($discount, 500))->toBeTrue()
        ->and($this->manager->conditions($discount))->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| Custom conditions
|--------------------------------------------------------------------------
*/

test('an application can register its own condition under an alias', function () {
    ConditionFactory::register('weekend_only', WeekendOnlyCondition::class);

    $discount = makeConditional([['type' => 'weekend_only', 'open' => false]]);

    expect($this->manager->isValid($discount, 500))->toBeFalse();

    expect($this->manager->isValid(makeConditional([['type' => 'weekend_only', 'open' => true]]), 500))->toBeTrue();
});

test('a condition can be stored by its class name without registering', function () {
    $discount = makeConditional([['type' => WeekendOnlyCondition::class, 'open' => true]]);

    expect($this->manager->isValid($discount, 500))->toBeTrue();
});

test('an unknown condition type is rejected', function () {
    $this->manager->validate(makeConditional([['type' => 'no_such_condition']]), 500);
})->throws(InvalidDiscountConditionsException::class, 'The discount condition [no_such_condition] is not registered');

test('a class that is not a condition is rejected', function () {
    $this->manager->validate(makeConditional([['type' => Product::class]]), 500);
})->throws(InvalidDiscountConditionsException::class);

test('a rule without a type is rejected', function () {
    $this->manager->validate(makeConditional([['count' => 2]]), 500);
})->throws(InvalidDiscountConditionsException::class, 'must be an array with a `type` key');

test('conditions can be inspected as objects', function () {
    $discount = makeConditional([
        ['type' => 'minimum_item_count', 'count' => 3],
        ['type' => 'first_purchase'],
    ]);

    expect($this->manager->conditions($discount))->toHaveCount(2)
        ->and($this->manager->conditions($discount)->first())->toBeInstanceOf(MinimumItemCountCondition::class)
        ->and($this->manager->conditions($discount)->last())->toBeInstanceOf(FirstPurchaseCondition::class);
});

/*
|--------------------------------------------------------------------------
| Applying
|--------------------------------------------------------------------------
*/

test('applying a discount whose condition fails throws', function () {
    LaravelDiscount::apply(makeConditional([['type' => 'minimum_item_count', 'count' => 3]]), 500, quantity: 1);
})->throws(DiscountConditionFailedException::class);

test('applying a discount whose condition passes discounts as usual', function () {
    $result = LaravelDiscount::apply(
        makeConditional([['type' => 'minimum_item_count', 'count' => 3]]),
        500,
        quantity: 3
    );

    expect($result->discountAmount)->toBe(50.0);
});

test('a discount whose condition fails is skipped when applying many', function () {
    $conditional = makeConditional([['type' => 'minimum_item_count', 'count' => 10]]);
    $plain = Discount::query()->create(['type' => DiscountType::Fixed, 'value' => 20]);

    $result = LaravelDiscount::applyMany([$conditional, $plain], 500, quantity: 1);

    expect($result->discountAmount)->toBe(20.0)
        ->and($result->discounts->pluck('id')->all())->toBe([$plain->id]);
});

test('conditions apply to the new discount types too', function () {
    $discount = Discount::query()->create([
        'type' => DiscountType::Tiered,
        'conditions' => [
            'tiers' => [['min' => 1000, 'value' => 10]],
            'rules' => [['type' => 'minimum_item_count', 'count' => 2]],
        ],
    ]);

    expect($this->manager->isValid($discount, 2000, quantity: 1))->toBeFalse()
        ->and($this->manager->isValid($discount, 2000, quantity: 2))->toBeTrue()
        ->and(LaravelDiscount::apply($discount, 2000, quantity: 2)->discountAmount)->toBe(200.0);
});

/*
|--------------------------------------------------------------------------
| Context
|--------------------------------------------------------------------------
*/

test('the context exposes the order being discounted', function () {
    $discount = makeConditional([]);
    $user = makeConditionUser();

    $context = new DiscountContext($discount, 500, $user, null, 3, ['items' => [1, 2], 'note' => ['x' => 'y']]);

    expect($context->userId())->toBe($user->id)
        ->and($context->items()->all())->toBe([1, 2])
        ->and($context->get('note.x'))->toBe('y')
        ->and($context->has('note.x'))->toBeTrue()
        ->and($context->has('missing'))->toBeFalse()
        ->and($context->quantity)->toBe(3);
});

test('the context reads a user given as an id', function () {
    expect((new DiscountContext(makeConditional([]), 500, 42))->userId())->toBe(42);
});

/*
|--------------------------------------------------------------------------
| Test doubles
|--------------------------------------------------------------------------
*/

class TestOrder extends Model
{
    protected $table = 'orders';

    protected $fillable = ['user_id'];
}

class WeekendOnlyCondition implements DiscountCondition
{
    public function __construct(protected bool $open = false) {}

    public static function fromArray(array $config): static
    {
        return new static((bool) ($config['open'] ?? false));
    }

    public function passes(DiscountContext $context): bool
    {
        return $this->open;
    }

    public function message(): string
    {
        return 'This discount only runs at the weekend.';
    }
}
