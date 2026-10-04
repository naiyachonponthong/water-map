<?php

namespace Tests\Unit;

use Dotenv\Dotenv;
use FloodThai\Installer\Installer;
use FloodThai\Installer\Runner;
use Illuminate\Filesystem\Filesystem;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__.'/../../installer/Installer.php';
require_once __DIR__.'/../../installer/Runner.php';

class InstallerTest extends TestCase
{
    private string $root;

    private Installer $installer;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/floodthai-installer-test-'.bin2hex(random_bytes(8));
        mkdir($this->root, 0700, true);
        $this->installer = new Installer($this->root);
    }

    protected function tearDown(): void
    {
        // Exact freshly-created test directory, never a project or temp root.
        $this->assertStringStartsWith(str_replace('\\', '/', realpath(sys_get_temp_dir())).'/floodthai-installer-test-', str_replace('\\', '/', realpath($this->root)));
        (new Filesystem)->deleteDirectory($this->root);
    }

    private function input(): array
    {
        return ['app_name' => 'ศูนย์ทดสอบ', 'app_url' => 'https://flood.example.org', 'db_host' => 'localhost', 'db_port' => '3306',
            'db_name' => 'account_flood', 'db_user' => 'account_user', 'db_password' => 'db-secret', 'mode' => 'shared',
            'admin_name' => 'ผู้ดูแลทดสอบ', 'admin_phone' => '0812345678', 'admin_password' => 'UniquePassword!2026', 'admin_confirmation' => 'UniquePassword!2026'];
    }

    public function test_fresh_blank_key_is_not_mistaken_for_the_next_environment_line(): void
    {
        file_put_contents($this->root.'/.env', "APP_KEY=\nAPP_DEBUG=true\n");
        $this->assertFalse($this->installer->locked());
        file_put_contents($this->root.'/.env', "APP_KEY=\"\"\nAPP_DEBUG=true\n");
        $this->assertFalse($this->installer->locked());
        $this->assertTrue($this->installer->needsSetup());
    }

    public function test_existing_installation_is_locked_and_cannot_be_reconfigured(): void
    {
        $old = "APP_KEY=base64:existing-secret\nDB_DATABASE=real_data\n";
        file_put_contents($this->root.'/.env', $old);
        $this->assertTrue($this->installer->locked());
        try {
            $this->installer->prepare($this->input());
            $this->fail('Existing installation accepted');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('ตั้งค่าแล้ว', $e->getMessage());
        }
        $this->assertSame($old, file_get_contents($this->root.'/.env'));
    }

    public function test_only_matching_install_state_can_resume_and_tampering_fails_closed(): void
    {
        $env = "APP_KEY=base64:setup-secret\nAPP_INSTALLING=true\n";
        file_put_contents($this->root.'/.env', $env);
        $this->installer->write('state', ['env_hash' => hash('sha256', $env), 'phase' => 'migrate']);
        $this->assertFalse($this->installer->locked());
        file_put_contents($this->root.'/.env', $env."DB_DATABASE=other\n");
        $this->assertTrue($this->installer->locked());
        file_put_contents($this->root.'/.env', $env);
        $this->installer->write('installed', ['at' => 'now']);
        $this->assertTrue($this->installer->locked());
    }

    public function test_setup_tokens_are_per_host_private_and_rate_limited(): void
    {
        $token = $this->installer->token();
        $this->assertSame(64, strlen($token));
        $this->assertSame($token, $this->installer->token());
        $this->assertStringStartsWith('<?php http_response_code(404); exit; ?>', file_get_contents($this->installer->directory().'/setup-key.php'));
        $this->assertTrue($this->installer->authenticate($token));
        for ($i = 0; $i < 9; $i++) {
            $this->assertFalse($this->installer->authenticate('wrong'));
        }
        $this->expectException(RuntimeException::class);
        $this->installer->authenticate($token);
    }

    public function test_failed_environment_write_can_resume_without_replacing_the_key(): void
    {
        $env = "APP_KEY=base64:original-install-key\nAPP_INSTALLING=true\n";
        $this->installer->write('draft', ['env' => $env]);
        $this->installer->write('state', ['phase' => 'configure', 'env_hash' => hash('sha256', $env)]);
        $this->assertSame('migrate', (new Runner($this->installer))->advance());
        $this->assertSame($env, file_get_contents($this->root.'/.env'));
        $this->assertSame('migrate', $this->installer->read('state')['phase']);
        $this->assertFalse($this->installer->locked());
    }

    public function test_removed_key_cannot_bypass_resume_environment_integrity_check(): void
    {
        $env = "APP_KEY=base64:original-key\nAPP_INSTALLING=true\n";
        $this->installer->write('state', ['phase' => 'migrate', 'env_hash' => hash('sha256', $env)]);
        file_put_contents($this->root.'/.env', "APP_KEY=\nDB_DATABASE=other\n");
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ไฟล์ตั้งค่าถูกเปลี่ยน');
        (new Runner($this->installer))->advance();
    }

    public function test_cached_configuration_without_environment_stays_locked(): void
    {
        mkdir($this->root.'/bootstrap/cache', 0700, true);
        file_put_contents($this->root.'/bootstrap/cache/config.php', '<?php return [];');
        $this->assertTrue($this->installer->locked());
    }

    public function test_environment_quoting_round_trips_special_passwords_without_interpolation(): void
    {
        $input = $this->input();
        $input['db_password'] = 'spaces # " quotes \' backslash \\ and ${APP_NAME} $dollar';
        $env = Installer::environment($input, 'base64:unique-key');
        $parsed = Dotenv::parse($env);
        $this->assertSame($input['db_password'], $parsed['DB_PASSWORD']);
        $this->assertSame('false', $parsed['APP_DEBUG']);
        $this->assertSame('database', $parsed['QUEUE_CONNECTION']);
        $this->assertSame('null', $parsed['BROADCAST_CONNECTION']);
        $this->assertSame('', $parsed['SUPERADMIN_PASSWORD']);
        $this->assertStringNotContainsString($input['admin_password'], $env);
        $this->assertEquals($input, Installer::validate($input));
    }

    public function test_validation_rejects_http_sites_default_password_and_dsn_injection(): void
    {
        foreach ([['app_url' => 'http://public.example.org'], ['db_host' => 'localhost;dbname=secret'],
            ['db_password' => "evil\nAPP_DEBUG=true"], ['admin_password' => 'floodthai@2026', 'admin_confirmation' => 'floodthai@2026']] as $change) {
            try {
                Installer::validate(array_merge($this->input(), $change));
                $this->fail('Unsafe configuration accepted');
            } catch (RuntimeException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function test_no_database_with_existing_tables_is_accepted(): void
    {
        $db = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->onlyMethods(['query'])->getMock();
        $statement = $this->getMockBuilder(PDOStatement::class)->disableOriginalConstructor()->onlyMethods(['fetchColumn'])->getMock();
        $statement->expects($this->once())->method('fetchColumn')->willReturn('existing_users');
        $db->expects($this->once())->method('query')->willReturn($statement);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('มีตารางอยู่แล้ว');
        Installer::assertEmpty($db);
    }

    public function test_wrong_document_root_is_a_blocker_and_does_not_create_secret_files(): void
    {
        mkdir($this->root.'/public');
        $checks = $this->installer->checks(['DOCUMENT_ROOT' => $this->root, 'HTTPS' => 'on']);
        $check = array_values(array_filter($checks, fn ($c) => $c['label'] === 'โฟลเดอร์เว็บไซต์เป็น public'))[0];
        $this->assertFalse($check['ok']);
        $this->assertFalse(Installer::ready($checks));
        $this->assertDirectoryDoesNotExist($this->installer->directory());
        $this->assertFalse(Installer::secure(['HTTP_X_FORWARDED_PROTO' => 'https']));
    }
}
