<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'transaction_id' => 'txn_' . $this->faker->uuid(),
            'provider' => $this->faker->randomElement([
                Transaction::PROVIDER_STRIPE,
                Transaction::PROVIDER_PAYPAL,
                Transaction::PROVIDER_RAZORPAY
            ]),
            'amount' => $this->faker->randomFloat(2, 1, 1000),
            'currency' => $this->faker->randomElement(['USD', 'EUR', 'GBP', 'INR']),
            'type' => $this->faker->randomElement([
                Transaction::TYPE_CREDIT,
                Transaction::TYPE_DEBIT,
                Transaction::TYPE_REFUND
            ]),
            'status' => $this->faker->randomElement([
                Transaction::STATUS_PENDING,
                Transaction::STATUS_COMPLETED,
                Transaction::STATUS_FAILED,
                Transaction::STATUS_CANCELLED
            ]),
            'description' => $this->faker->sentence(),
            'provider_data' => [
                'webhook_id' => $this->faker->uuid(),
                'event_type' => $this->faker->word(),
                'processed_at' => $this->faker->dateTime()->format('c')
            ],
            'metadata' => [
                'ip_address' => $this->faker->ipv4(),
                'user_agent' => $this->faker->userAgent(),
                'source' => $this->faker->randomElement(['web', 'mobile', 'api'])
            ]
        ];
    }

    /**
     * Indicate that the transaction is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Transaction::STATUS_COMPLETED,
        ]);
    }

    /**
     * Indicate that the transaction is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Transaction::STATUS_PENDING,
        ]);
    }

    /**
     * Indicate that the transaction is failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Transaction::STATUS_FAILED,
        ]);
    }

    /**
     * Indicate that the transaction is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Transaction::STATUS_CANCELLED,
        ]);
    }

    /**
     * Indicate that the transaction is a credit.
     */
    public function credit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Transaction::TYPE_CREDIT,
        ]);
    }

    /**
     * Indicate that the transaction is a debit.
     */
    public function debit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Transaction::TYPE_DEBIT,
        ]);
    }

    /**
     * Indicate that the transaction is a refund.
     */
    public function refund(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Transaction::TYPE_REFUND,
        ]);
    }

    /**
     * Indicate that the transaction is from Stripe.
     */
    public function stripe(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => Transaction::PROVIDER_STRIPE,
            'transaction_id' => 'pi_' . $this->faker->uuid(),
            'provider_data' => [
                'stripe_payment_intent_id' => 'pi_' . $this->faker->uuid(),
                'stripe_charge_id' => 'ch_' . $this->faker->uuid(),
                'payment_method' => 'card',
                'last4' => $this->faker->numerify('####'),
                'brand' => $this->faker->randomElement(['visa', 'mastercard', 'amex'])
            ]
        ]);
    }

    /**
     * Indicate that the transaction is from PayPal.
     */
    public function paypal(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => Transaction::PROVIDER_PAYPAL,
            'transaction_id' => 'paypal_' . $this->faker->uuid(),
            'provider_data' => [
                'paypal_payment_id' => $this->faker->uuid(),
                'paypal_payer_id' => $this->faker->uuid(),
                'payment_method' => 'paypal',
                'payer_email' => $this->faker->email()
            ]
        ]);
    }

    /**
     * Indicate that the transaction is from Razorpay.
     */
    public function razorpay(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => Transaction::PROVIDER_RAZORPAY,
            'currency' => 'INR',
            'transaction_id' => 'razorpay_' . $this->faker->uuid(),
            'provider_data' => [
                'razorpay_payment_id' => 'pay_' . $this->faker->uuid(),
                'razorpay_order_id' => 'order_' . $this->faker->uuid(),
                'payment_method' => $this->faker->randomElement(['card', 'netbanking', 'upi']),
                'bank' => $this->faker->randomElement(['HDFC', 'ICICI', 'SBI', 'AXIS'])
            ]
        ]);
    }

    /**
     * Create a transaction with specific amount.
     */
    public function amount(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
        ]);
    }

    /**
     * Create a transaction for specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}