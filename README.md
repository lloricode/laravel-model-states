# WIP states for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/state.svg?style=flat-square)](https://packagist.org/packages/spatie/:package_name)
[![Build Status](https://img.shields.io/travis/spatie/state/master.svg?style=flat-square)](https://travis-ci.org/spatie/:package_name)
[![Quality Score](https://img.shields.io/scrutinizer/g/spatie/state.svg?style=flat-square)](https://scrutinizer-ci.com/g/spatie/:package_name)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/state.svg?style=flat-square)](https://packagist.org/packages/spatie/:package_name)

## Installation

You can install the package via composer:

```bash
composer require spatie/laravel-state
```

## Usage

> **Note**: make sure you're familiar with the basics of the [state pattern](https://en.wikipedia.org/wiki/State_pattern) before using this package.

This package adds state support to your Laravel models. 

Start of by using the `Spatie\State\HasStates` trait in your model. Now you're able to define state fields. 
These are defined in the `$states` array on your model class. It requires you to map a field name unto a state class.
Here's an example of a `Payment` class with one state field, simply called `state`.

```php
use App\States\PaymentState;
use Spatie\State\HasStates;

/**
 * @property \App\States\PaymentState state
 */
class Payment extends Model
{
    use HasStates;

    protected $states = [
        'state' => PaymentState::class,
    ];
}
```

> **Note**: by adding a `@property` docblock, you'll get IDE autocompletion and static analysis support on your state fields.

You will always have to create an abstract class which will represent the possible states for that field. This class should extend the `Spatie\State\State` class. In our case, this class is called `PaymentState`. All concrete payment states should extend this base state class. Each concrete implementation can provide state-specific behaviour, as described by the [state pattern](https://en.wikipedia.org/wiki/State_pattern). 

This is what such a base class might look like:

```php
use Spatie\State\State;

abstract class PaymentState extends State
{
    abstract public function color(): string;
}
```

And this is a possible concrete implementation:

```php
class Paid extends PaymentState
{
    public static $name = 'paid';

    public function color(): string
    {
        return 'green';
    }
}
```

Now you can use the `state` field on your model directly as a `PaymentState` object, it will be properly saved and loaded to and from the database behind the scenes.

```php
$payment = Payment::create();

$payment->state = new Paid();

$payment->save();

// Color depending on the current state
echo $payment->state->color();
```

### Defaults

If you want a state to have a default value, you can do so in your model's constructor.

```php
// …

class Payment extends Model
{
    // …

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        $this->state = $this->state ?? new Created();
    }
}
```

### State names

By default, the state's classname will be saved into the database. If you want the state to be saved using another value, you can provide a static `$name` property on your concrete state classes.

```php
class Paid extends PaymentState
{
    public static $name = 'paid';

    // …
}
```

#### Resolving states from the database

If you're using custom names, you'll need to make sure they can be resolved back from the database. There's two ways to do this:

- Manually provide the available states on an abstract state class
- Keep the abstract state class and its concrete implementations together in the same directory, which allows them to be resolved automatically.

Here's what the manual mapping looks like:

```php
abstract class PaymentState extends State
{
    public static $states =[
        Canceled::class,
        Created::class,
        Failed::class,
        Paid::class,
        Pending::class,
        PaidWithoutName::class,
    ];
    
    // …
}
```

Note that you only need to provide a manual mapping, if the concrete state classes don't live within the same directory as their abstract state class.

### State transitions

Transitions offer a structured way of transitioning the state of a model from one to another.

Imagine transitioning a payment's state from pending to failed, which will also save an error message to the database.
Here's what such a basic transition class might look like.

```php
use Spatie\State\Transition;

class PendingToFailed extends Transition
{
    private $payment;
    private $message;

    public function __construct(Payment $payment, string $message)
    {
        $this->payment = $payment;
        $this->message = $message;
    }

    public function handle(): Payment
    {
        $this->payment->state = new Failed($this->payment);
        $this->payment->failed_at = now();
        $this->payment->error_message = $this->message;

        $this->payment->save();

        return $this->payment;
    }
}
```

And this is how it would be used:

```php
$payment->state->transition(PendingToFailed::class, 'error message');
```

> **Note**: the `State::transition` method will take as much additional arguments as you'd like, 
> these arguments will be passed to the transition's constructor. 
> The first argument in the transition's constructor will always be the model that the transition is performed on. 

If you want to be more explicit about configuring your transition, you can also use it like so:

```php
$payment->state->transition(new CreatedToFailed($payment, 'error message'));
```

#### Ensuring valid transitions

Our above example is still flawed, as it's possible to perform this transition whatever the current state of the payment.

If you want transitions to only work with specific states, you may implement the `canTransition()` method.

```php
class CreatedToFailed extends Transition
{
    // …

    public function canTransition(): bool
    {
        return $this->payment->state->is(Created::class);
    
        // return $this->payment->state->isOneOf(Created::class, Pending::class);
    }
}
```

If the check in `canTransition()` fails, a `\Spatie\State\Exceptions\CannotPerformTransition` exception will be thrown.

#### Injecting dependencies in transitions

Just like Laravel jobs, you're able to inject dependencies automatically in the `handle()` method of every transition.

```php
class TransitionWithDependency extends Transition
{
    // …

    public function handle(Dependency $dependency)
    {
        // $dependency is resolved from the container
    }
}
```

> **Note**: be careful not to have too many side effects within a transition. If you're injecting many dependencies, it's probably a sign that you should refactor your code and use an event-based system to handle complex side effects.

### Querybuilder support

Every model using the `HasStates` trait will have a `whereState($field, $states)` and a `whereNotState($field, $states)` scope available. It's used like so:

```php
$payments = Payment::whereState('state', Paid::class);
$payments = Payment::whereState('state', [Pending::class, Paid::class]);

$payments = Payment::whereNotState('state', Pending::class);
$payments = Payment::whereNotState('state', [Failed::class, Canceled::class]);
```

### State validation

This package provides a validation rule to validate incoming request data. It can be used like so:

```php
use Spatie\State\Validation\ValidStateRule;

request()->validate([
    'state' => new ValidStateRule(PaymentState::class),
]);

// Allowing null
request()->validate([
    'state' => ValidStateRule::make(PaymentState::class)->nullable(),
]);
```

Only valid state values of `PaymentState` implementations will be allowed.

### Testing

``` bash
composer test
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email freek@spatie.be instead of using the issue tracker.

## Postcardware

You're free to use this package, but if it makes it to your production environment we highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using.

Our address is: Spatie, Samberstraat 69D, 2060 Antwerp, Belgium.

We publish all received postcards [on our company website](https://spatie.be/en/opensource/postcards).

## Credits

- [Brent Roose](https://github.com/brendt)
- [All Contributors](../../contributors)

## Support us

Spatie is a webdesign agency based in Antwerp, Belgium. You'll find an overview of all our open source projects [on our website](https://spatie.be/opensource).

Does your business depend on our contributions? Reach out and support us on [Patreon](https://www.patreon.com/spatie). 
All pledges will be dedicated to allocating workforce on maintenance and new awesome stuff.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
