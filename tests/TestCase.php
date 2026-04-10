<?php

namespace LBHurtado\Voucher\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Number;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
//use LBHurtado\Voucher\Models\MoneyIssuer;
use LBHurtado\Voucher\Tests\Fakes\FakePayoutProvider;
use LBHurtado\Voucher\Tests\Models\User;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected FakePayoutProvider $fakeProvider;

    protected function setUp(): void
    {
        parent::setUp();

//        Factory::guessFactoryNamesUsing(
//            fn (string $modelName) => 'LBHurtado\\Voucher\\Database\\Factories\\'.class_basename($modelName).'Factory'
//        );

        if (! defined('TESTING_PACKAGE_PATH')) {
            define('TESTING_PACKAGE_PATH', __DIR__.'/../resources/documents');
        }

        $this->loadEnvironment();
        $this->loadConfig();
        $this->bindFakePayoutProvider();

        $this->bootstrapTestDatabase();

        $this->loginTestUser();
    }

    public function fakePayoutProvider(): FakePayoutProvider
    {
        return $this->fakeProvider;
    }

    protected function getPackageProviders($app): array
    {
        $providers = [
            \FrittenKeeZ\Vouchers\VouchersServiceProvider::class,
            \LBHurtado\Voucher\VoucherServiceProvider::class,
            \LBHurtado\Wallet\WalletServiceProvider::class,
            \Bavix\Wallet\WalletServiceProvider::class,
            \LBHurtado\EmiCore\EmiCoreServiceProvider::class,
            \LBHurtado\Contact\ContactServiceProvider::class,
            \LBHurtado\Cash\CashServiceProvider::class,
            \Spatie\LaravelData\LaravelDataServiceProvider::class,
            \Spatie\SchemalessAttributes\SchemalessAttributesServiceProvider::class,
        ];

        if (class_exists(\LBHurtado\ModelInput\ModelInputServiceProvider::class)) {
            $providers[] = \LBHurtado\ModelInput\ModelInputServiceProvider::class;
        }

        if (class_exists(\LBHurtado\SettlementEnvelope\SettlementEnvelopeServiceProvider::class)) {
            $providers[] = \LBHurtado\SettlementEnvelope\SettlementEnvelopeServiceProvider::class;
        }

        return $providers;
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');

        $app['config']->set('data.validation_strategy', 'always');
        $app['config']->set('data.max_transformation_depth', 6);
        $app['config']->set('data.throw_when_max_transformation_depth_reached', 6);
        $app['config']->set('data.normalizers', [
            \Spatie\LaravelData\Normalizers\ModelNormalizer::class,
            \Spatie\LaravelData\Normalizers\ArrayableNormalizer::class,
            \Spatie\LaravelData\Normalizers\ObjectNormalizer::class,
            \Spatie\LaravelData\Normalizers\ArrayNormalizer::class,
            \Spatie\LaravelData\Normalizers\JsonNormalizer::class,
        ]);
        $app['config']->set('data.date_format', "Y-m-d\\TH:i:sP");

        $app['config']->set('model-status.status_model', \Spatie\ModelStatus\Status::class);
        $app['config']->set('vouchers.models.voucher', \LBHurtado\Voucher\Models\Voucher::class);

        $app['config']->set('instructions.feedback.mobile', '09171234567');
        $app['config']->set('instructions.feedback.email', 'example@example.com');
        $app['config']->set('instructions.feedback.webhook', 'http://example.com/webhook');

        $app['config']->set('auth.defaults.guard', 'web');

        $app['config']->set('account.system_user.identifier', 'system@test.com');
        $app['config']->set('account.system_user.identifier_column', 'email');
        $app['config']->set('account.system_user.model', User::class);

        \Illuminate\Support\Facades\Route::get('/redeem', fn () => 'ok')->name('redeem.start');

        Number::useCurrency('PHP');
    }

    protected function bootstrapTestDatabase(): void
    {
        // Test-only helper tables owned by this test suite.
        if (! Schema::hasTable('users')) {
            $this->runMigrationDirectory(__DIR__.'/database/migrations');
        }

        // Runtime voucher schema owned by 3neti/laravel-vouchers.
        if (! Schema::hasTable('vouchers')) {
            $this->runMigrationDirectory(
                $this->getPackageMigrationPath(\FrittenKeeZ\Vouchers\VouchersServiceProvider::class)
            );
        }

        // Runtime wallet schema owned by bavix/laravel-wallet.
        if (! Schema::hasTable('wallets')) {
            $this->runMigrationDirectory(
                $this->getPackageMigrationPath(\Bavix\Wallet\WalletServiceProvider::class)
            );
        }
    }

    protected function runMigrationDirectory(string $path): void
    {
        if (! is_dir($path)) {
            throw new \RuntimeException("Migration directory not found: {$path}");
        }

        $files = glob($path.'/*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            $migration = include $file;

            if (is_object($migration) && method_exists($migration, 'up')) {
                $migration->up();
            }
        }
    }

    protected function getPackageMigrationPath(string $providerClass): string
    {
        $reflection = new \ReflectionClass($providerClass);
        $root = dirname($reflection->getFileName(), 2);

        foreach ([
                     $root.'/database/migrations',
                     $root.'/database',
                 ] as $candidate) {
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException("Unable to locate migration path for [{$providerClass}].");
    }

    protected function bindFakePayoutProvider(): void
    {
        $this->fakeProvider = new FakePayoutProvider;
        $this->app->instance(PayoutProvider::class, $this->fakeProvider);
    }

    protected function loginTestUser(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User']
        );

        $this->actingAs($user, 'web');
    }

    protected function loadConfig(): void
    {
        $this->app['config']->set(
            'instructions',
            require __DIR__.'/../config/instructions.php'
        );
    }

    protected function setupSystemUser(): void
    {
        $system = User::factory()->create(['email' => 'system@test.com']);
        $system->wallet;
        $system->depositFloat(1_000_000);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->actingAs($user);
        \LBHurtado\Wallet\Actions\TopupWalletAction::run($user, 100_000);
    }

    protected function loadEnvironment(): void
    {
        $path = __DIR__.'/../.env';

        if (file_exists($path)) {
            \Dotenv\Dotenv::createImmutable(dirname($path), '.env')->load();
        }
    }
}