<?php

namespace LBHurtado\Voucher\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\Voucher\Tests\Fakes\FakePayoutProvider;
use LBHurtado\Voucher\Tests\Models\User;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected FakePayoutProvider $fakeProvider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginTestUser();
        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'LBHurtado\\Voucher\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
        if (! defined('TESTING_PACKAGE_PATH')) {
            define('TESTING_PACKAGE_PATH', __DIR__.'/../resources/documents');
        }
        $this->loadEnvironment();
        $this->loadConfig();
    }

    public function fakePayoutProvider(): FakePayoutProvider
    {
        return $this->fakeProvider;
    }

    protected function getPackageProviders($app)
    {
        $providers = [
            \FrittenKeeZ\Vouchers\VouchersServiceProvider::class,
            \LBHurtado\Voucher\VoucherServiceProvider::class,
            \LBHurtado\Wallet\WalletServiceProvider::class,
            \Bavix\Wallet\WalletServiceProvider::class,
            \LBHurtado\EmiCore\EmiCoreServiceProvider::class,
            \LBHurtado\Contact\ContactServiceProvider::class,
            \Spatie\LaravelData\LaravelDataServiceProvider::class,
            \Spatie\SchemalessAttributes\SchemalessAttributesServiceProvider::class,
        ];

        // Conditionally load optional providers if installed
        if (class_exists(\LBHurtado\ModelInput\ModelInputServiceProvider::class)) {
            $providers[] = \LBHurtado\ModelInput\ModelInputServiceProvider::class;
        }
        if (class_exists(\LBHurtado\ModelChannel\ModelChannelServiceProvider::class)) {
            $providers[] = \LBHurtado\ModelChannel\ModelChannelServiceProvider::class;
        }
        if (class_exists(\LBHurtado\SettlementEnvelope\SettlementEnvelopeServiceProvider::class)) {
            $providers[] = \LBHurtado\SettlementEnvelope\SettlementEnvelopeServiceProvider::class;
        }

        return $providers;
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        config()->set('data.validation_strategy', 'always');
        config()->set('data.max_transformation_depth', 6);
        config()->set('data.throw_when_max_transformation_depth_reached', 6);
        config()->set('data.normalizers', [
            \Spatie\LaravelData\Normalizers\ModelNormalizer::class,
            // Spatie\LaravelData\Normalizers\FormRequestNormalizer::class,
            \Spatie\LaravelData\Normalizers\ArrayableNormalizer::class,
            \Spatie\LaravelData\Normalizers\ObjectNormalizer::class,
            \Spatie\LaravelData\Normalizers\ArrayNormalizer::class,
            \Spatie\LaravelData\Normalizers\JsonNormalizer::class,
        ]);
        config()->set('data.date_format', "Y-m-d\TH:i:sP");

        config()->set('model-status.status_model', \Spatie\ModelStatus\Status::class);
        config()->set('vouchers.models.voucher', \LBHurtado\Voucher\Models\Voucher::class);

        config()->set('instructions.feedback.mobile', '09171234567');
        config()->set('instructions.feedback.email', 'example@example.com');
        config()->set('instructions.feedback.webhook', 'http://example.com/webhook');

        //        // Configure the web guard for authentication
        //        $app['config']->set('auth.guards.web', [
        //            'driver' => 'session', // Use the session driver for web guard
        //            'provider' => 'users', // Reference the users provider below
        //        ]);
        //
        //        // Add an authentication provider using the array driver
        //        $app['config']->set('auth.providers.users', [
        //            'driver' => 'eloquent', // Use the lightweight array driver
        //            'model' => \Illuminate\Foundation\Auth\User::class, // Optionally specify Laravel's default User model
        //        ]);

        $app['config']->set('auth.defaults.guard', 'web');

        // Bind FakePayoutProvider for all voucher tests
        $this->fakeProvider = new FakePayoutProvider;
        $app->instance(PayoutProvider::class, $this->fakeProvider);

        // System user config for wallet operations
        config()->set('account.system_user.identifier', 'system@test.com');
        config()->set('account.system_user.identifier_column', 'email');
        config()->set('account.system_user.model', User::class);

        // Register dummy routes for package testing (normally defined by host app)
        \Illuminate\Support\Facades\Route::get('/redeem', fn () => 'ok')->name('redeem.start');

        // Run test migrations only if tables don't exist yet
        // (RefreshDatabase will handle them via service provider auto-loading)
        if (! \Illuminate\Support\Facades\Schema::hasTable('vouchers')) {
            (include 'vendor/frittenkeez/laravel-vouchers/publishes/migrations/2018_06_12_000000_create_voucher_tables.php')->up();
            (include __DIR__.'/../database/test-migrations/0001_01_01_000000_create_users_table.php')->up();
            (include __DIR__.'/../database/test-migrations/2024_07_02_202500_create_money_issuers_table.php')->up();
            (include __DIR__.'/../database/test-migrations/2024_08_03_202500_create_statuses_table.php')->up();
            (include __DIR__.'/../database/test-migrations/2024_08_04_202500_create_tag_tables.php')->up();
        }
    }

//    protected function loadSettlementEnvelopeMigrations(): void
//    {
//        $settlementEnvelopePath = __DIR__.'/../../settlement-envelope/database/migrations';
//        if (is_dir($settlementEnvelopePath)) {
//            foreach (glob($settlementEnvelopePath.'/*.php') as $migration) {
//                $migrationClass = include $migration;
//                $migrationClass->up();
//            }
//        }
//
//        // Configure settlement-envelope
//        config()->set('settlement-envelope.driver_directory', __DIR__.'/../../settlement-envelope/drivers');
//        config()->set('settlement-envelope.storage_disk', 'local');
//    }

    // Define a reusable method for logging in a user
    protected function loginTestUser()
    {
        $user = new User([
            'id' => 1, // Unique ID for the user
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->actingAs($user); // Simulate authentication as this user
    }

    protected function loadConfig()
    {
        $this->app['config']->set(
            'instructions',
            require __DIR__.'/../config/instructions.php'
        );
    }

    protected function setupSystemUser(): void
    {
        // Create system user for wallet operations
        $system = User::factory()->create(['email' => 'system@test.com']);
        $system->wallet;
        $system->depositFloat(1_000_000);

        // Create and authenticate a real DB user (needed for voucher generation)
        $user = User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);
        $this->actingAs($user);
        \LBHurtado\Wallet\Actions\TopupWalletAction::run($user, 100_000);
    }

    protected function loadEnvironment()
    {
        $path = __DIR__.'/../.env';

        if (file_exists($path)) {
            \Dotenv\Dotenv::createImmutable(dirname($path), '.env')->load();
        }
    }
}
