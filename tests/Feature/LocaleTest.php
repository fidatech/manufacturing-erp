<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_reads_the_system_in_dari_or_pashto_from_right_to_left(): void
    {
        $user = $this->admin();

        $this->get(route('dashboard'))->assertOk()->assertSee('dir="ltr"', false)->assertSee('Purchases')->assertSee('دری')->assertSee('پښتو');

        $this->post(route('preferences.locale'), ['locale' => 'fa'])->assertRedirect();
        $this->assertSame('fa', $user->fresh()->locale);
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('lang="fa"', false)->assertSee('dir="rtl"', false)->assertSee('app.rtl.css', false)
            ->assertSee('داشبورد')->assertSee('خریدها')->assertSee('فروشات')->assertSee('حساب‌ها');
        $this->get(route('accounts.index', ['type' => 'customer']))->assertOk()->assertSee('مشتریان')->assertSee('افزودن مشتری');
        $this->get(route('purchases.create', ['type' => 'order']))->assertOk()->assertSee('فروشنده')->assertSee('گدام')->assertSee('فروشندهٔ جدید');

        $this->post(route('preferences.locale'), ['locale' => 'ps'])->assertRedirect();
        $this->get(route('dashboard'))->assertOk()->assertSee('lang="ps"', false)->assertSee('dir="rtl"', false)->assertSee('ډشبورډ')->assertSee('پېرونه')->assertSee('پلورنې');

        // what is wrong is said in the user's language
        $this->post(route('warehouses.store'), ['name' => ''])->assertSessionHasErrors(['name' => 'نوم اړین دی.']);

        $this->post(route('preferences.locale'), ['locale' => 'en'])->assertRedirect();
        $this->get(route('dashboard'))->assertSee('dir="ltr"', false)->assertSee('Purchases');
        $this->post(route('preferences.locale'), ['locale' => 'xx'])->assertSessionHasErrors('locale');
    }

    public function test_the_language_can_be_chosen_on_the_sign_in_page(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Sign in')->assertSee('دری')->assertSee('پښتو');

        $this->post(route('preferences.locale'), ['locale' => 'fa'])->assertRedirect();
        $this->get(route('login'))->assertOk()->assertSee('dir="rtl"', false)->assertSee('ورود')->assertSee('رمز عبور');
        $this->post(route('login.store'), ['email' => 'nobody@example.com', 'password' => 'wrong-password'])->assertSessionHasErrors(['email' => 'ایمیل یا رمز عبور درست نیست.']);
    }

    public function test_dari_and_pashto_have_the_same_texts_with_the_same_placeholders(): void
    {
        $fa = json_decode(file_get_contents(lang_path('fa.json')), true, flags: JSON_THROW_ON_ERROR);
        $ps = json_decode(file_get_contents(lang_path('ps.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(array_keys($fa), array_keys($ps));
        $this->assertGreaterThan(900, count($fa));

        $names = fn (string $text) => collect(preg_match_all('/:[a-z_]+/', $text, $found) ? $found[0] : [])->sort()->values()->all();
        foreach ($fa as $english => $dari) {
            $this->assertSame($names($english), $names($dari), "Dari: {$english}");
            $this->assertSame($names($english), $names($ps[$english]), "Pashto: {$english}");
        }
        foreach (['fa', 'ps'] as $locale) {
            $this->assertSame(array_keys(require lang_path('en/app.php')), array_keys(require lang_path($locale.'/app.php')));
        }
    }
}
