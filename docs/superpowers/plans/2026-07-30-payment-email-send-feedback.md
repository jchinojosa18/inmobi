# Payment Email Send Feedback Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** On `/payments/{id}`, show an indeterminate progress-bar toast while the receipt email is sending, then replace it with «Mensaje Enviado» when done.

**Architecture:** Keep `Show::sendEmail()` mail logic. Drive the corner toast with Livewire `wire:loading` (sending / progress bar) + Alpine listening to `payment-receipt-email-sent` (success text). Align `session()->flash('success')` and toast copy to the short i18n key `finance.flash.message_sent`.

**Tech Stack:** Laravel 11, Livewire 4, Alpine.js, Blade, Tailwind, PHPUnit via Sail, Pint.

## Global Constraints

- Run all commands via `./vendor/bin/sail` (never bare `php artisan` / `phpunit`).
- Diff mínimo: payments show Blade, flash i18n, `Show::sendEmail` flash key, related feature test. No mail/queue changes.
- Spec: `docs/superpowers/specs/2026-07-30-payment-email-send-feedback-design.md`.
- Success toast text ES: «Mensaje Enviado» / EN: «Message Sent».
- Sending state: indeterminate progress bar in the existing bottom-right toast zone (no «Enviando...» text required).
- Tests: `./vendor/bin/sail test --filter=PaymentShowEmailTest`; format: `./vendor/bin/sail pint --dirty`.
- No commit unless the user explicitly asks.

---

## File Map

| File | Responsibility |
|------|----------------|
| `lang/es/finance.php` | Add `flash.message_sent`; keep or leave `receipt_sent` unused |
| `lang/en/finance.php` | English pair for `message_sent` |
| `app/Livewire/Payments/Show.php` | Flash `message_sent` after successful send |
| `resources/views/livewire/payments/show.blade.php` | Button loading + dual-state toast (progress / sent) |
| `tests/Feature/Payments/PaymentShowEmailTest.php` | Assert flash text + toast markup |

---

### Task 1: i18n + flash key + failing assertions

**Files:**
- Modify: `lang/es/finance.php` (`flash` array)
- Modify: `lang/en/finance.php` (`flash` array)
- Modify: `app/Livewire/Payments/Show.php` (`sendEmail` flash)
- Modify: `tests/Feature/Payments/PaymentShowEmailTest.php`

**Interfaces:**
- Consumes: existing `Show::sendEmail(): void`, event `payment-receipt-email-sent`
- Produces: `__('finance.flash.message_sent')` → ES `Mensaje Enviado`, EN `Message Sent`; flash uses that key

- [ ] **Step 1: Add failing test assertions for short flash + toast markup**

In `tests/Feature/Payments/PaymentShowEmailTest.php`, update `test_send_email_always_uses_tenant_email_and_ignores_arbitrary_input` to also assert the session flash, and add a markup test:

```php
public function test_send_email_flashes_short_message_sent(): void
{
    Mail::fake();

    Role::findOrCreate('Admin', 'web');
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('Admin');

    $tenant = Tenant::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'tenant@example.com',
    ]);

    $contract = Contract::factory()->create([
        'organization_id' => $organization->id,
        'tenant_id' => $tenant->id,
    ]);

    $payment = Payment::factory()->create([
        'organization_id' => $organization->id,
        'contract_id' => $contract->id,
    ]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['payment' => $payment])
        ->call('sendEmail')
        ->assertHasNoErrors()
        ->assertDispatched('payment-receipt-email-sent')
        ->assertSessionHas('success', __('finance.flash.message_sent'));
}

public function test_email_send_toast_shows_progress_loading_and_message_sent_copy(): void
{
    Role::findOrCreate('Admin', 'web');
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('Admin');

    $tenant = Tenant::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'tenant@example.com',
    ]);

    $contract = Contract::factory()->create([
        'organization_id' => $organization->id,
        'tenant_id' => $tenant->id,
    ]);

    $payment = Payment::factory()->create([
        'organization_id' => $organization->id,
        'contract_id' => $contract->id,
        'receipt_folio' => 'R-TEST-1',
    ]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['payment' => $payment])
        ->assertSeeHtml('wire:loading')
        ->assertSeeHtml('wire:target="sendEmail"')
        ->assertSeeHtml('payment-receipt-email-sent')
        ->assertSee(__('finance.flash.message_sent'))
        ->assertDontSee(__('finance.flash.receipt_sent'));
}
```

Reuse the same factory setup pattern already in the file (Admin role + org + tenant email + contract + payment). Ensure the payment has `receipt_folio` so the share card (and toast) render.

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/sail test --filter=PaymentShowEmailTest`

Expected: FAIL — missing `finance.flash.message_sent` and/or toast still shows `receipt_sent`.

- [ ] **Step 3: Add i18n keys and update flash in Show.php**

In `lang/es/finance.php` inside `flash`:

```php
'message_sent' => 'Mensaje Enviado',
```

In `lang/en/finance.php` inside `flash`:

```php
'message_sent' => 'Message Sent',
```

Leave `receipt_sent` in place (unused by this screen) to avoid unrelated churn.

In `app/Livewire/Payments/Show.php` inside `sendEmail()`:

```php
session()->flash('success', __('finance.flash.message_sent'));
$this->dispatch('payment-receipt-email-sent');
```

- [ ] **Step 4: Re-run tests**

Run: `./vendor/bin/sail test --filter=PaymentShowEmailTest`

Expected: `test_send_email_flashes_short_message_sent` PASS; markup test still FAIL until Task 2 updates the Blade toast.

---

### Task 2: Toast UI — progress bar while sending, «Mensaje Enviado» on success

**Files:**
- Modify: `resources/views/livewire/payments/show.blade.php` (send button ~88–95 and toast ~131–142)

**Interfaces:**
- Consumes: `wire:click="sendEmail"`, event `payment-receipt-email-sent`, `__('finance.flash.message_sent')`
- Produces: sending toast via `wire:loading wire:target="sendEmail"` with indeterminate bar; success toast via Alpine `sent` flag

- [ ] **Step 1: Disable button while sending**

Replace the send button block with:

```blade
<x-ui.button
    type="button"
    wire:click="sendEmail"
    wire:loading.attr="disabled"
    wire:target="sendEmail"
    wire:loading.class="opacity-60 cursor-not-allowed"
    class="mt-3"
    :disabled="! $payment->contract?->tenant?->email"
>
    {{ __('finance.payments.send_email') }}
</x-ui.button>
```

Confirm `x-ui.button` forwards unknown attributes (`wire:loading.*`) to the underlying `<button>`. If it does not, wrap with a native button or add attribute bag support — prefer checking `resources/views/components/ui/button.blade.php` first and match how `payments/quick-register-modal.blade.php` uses `wire:loading` on buttons.

- [ ] **Step 2: Replace toast with dual-state corner feedback**

Replace the existing toast `div` (the one listening to `payment-receipt-email-sent`) with:

```blade
<div
    x-data="{ sent: false }"
    x-on:payment-receipt-email-sent.window="sent = true; setTimeout(() => sent = false, 2500)"
    class="pointer-events-none fixed bottom-6 right-6 z-[70]"
>
    <div
        wire:loading
        wire:target="sendEmail"
        class="w-56 overflow-hidden rounded-lg bg-slate-800/95 px-4 py-3 shadow-lg"
        role="status"
        aria-live="polite"
        aria-busy="true"
    >
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-600">
            <div class="h-full w-1/3 animate-[paymentEmailProgress_1s_ease-in-out_infinite] rounded-full bg-white"></div>
        </div>
    </div>

    <div
        x-show="sent"
        x-cloak
        x-transition.opacity
        class="rounded-lg bg-slate-800/95 px-4 py-2 text-sm font-medium text-white shadow-lg"
        role="status"
        aria-live="polite"
    >
        {{ __('finance.flash.message_sent') }}
    </div>
</div>

<style>
    @keyframes paymentEmailProgress {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(300%); }
    }
</style>
```

Notes:
- Prefer a Tailwind arbitrary animation if the project already has a similar keyframe; otherwise the scoped `@keyframes` above is fine for this one screen.
- Do not show «Enviando...» text — the indeterminate bar is the sending signal.
- On validation failure (no tenant email), `wire:loading` ends and `payment-receipt-email-sent` is not dispatched → no success toast.

- [ ] **Step 3: Run feature tests**

Run: `./vendor/bin/sail test --filter=PaymentShowEmailTest`

Expected: all PASS, including markup assertions for `wire:loading`, `wire:target="sendEmail"`, event name, and `Mensaje Enviado` / locale equivalent; must not see the old Mailpit `receipt_sent` string on this page.

- [ ] **Step 4: Format dirty PHP**

Run: `./vendor/bin/sail pint --dirty`

Expected: clean / no errors.

- [ ] **Step 5: Manual smoke (optional but recommended)**

With Sail + Mailpit up, open a payment with folio + tenant email → click **Enviar por email** → corner shows progress bar → then **Mensaje Enviado** → auto-hide. Confirm layout banner (if present) also says **Mensaje Enviado**.

---

## Spec coverage checklist

| Spec requirement | Task |
|---|---|
| Indeterminate progress bar in corner toast while sending | Task 2 |
| Success text only «Mensaje Enviado» | Task 1 + 2 |
| Button disabled during request | Task 2 |
| Error path: no success toast | Task 2 (no event + wire:loading clears) |
| Align flash to short copy | Task 1 |
| No mail/queue / recipient changes | (out of scope — untouched) |
| `PaymentShowEmailTest` + pint | Task 1–2 |
