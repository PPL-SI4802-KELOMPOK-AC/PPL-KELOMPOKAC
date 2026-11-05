<?php

namespace Tests\Feature;

use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use PDO;
use PDOException;
use Tests\TestCase;

class TiDbConnectionTest extends TestCase
{
    private function configuration(array $overrides = []): array
    {
        return array_replace(config('database.connections.mysql'), [
            'host' => 'gateway01.ap-southeast-1.prod.aws.tidbcloud.com', 'port' => 4000,
            'database' => 'terangdesa', 'username' => 'private-user-canary',
            'password' => 'ISI_PASSWORD_TIDB_DI_SINI',
            'options' => [PDO::MYSQL_ATTR_SSL_CA => resource_path('certs/isrg-root-x1.pem'),
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true],
        ], $overrides);
    }

    private function connection(array $configuration): MySqlConnection
    {
        $connection = Mockery::mock(MySqlConnection::class);
        $connection->shouldReceive('getConfig')->once()->andReturn($configuration);
        DB::shouldReceive('connection')->once()->andReturn($connection);

        return $connection;
    }

    public function test_mysql_configuration_enforces_server_certificate_verification(): void
    {
        $this->assertTrue(config('database.connections.mysql.options')[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]);
        $certificate = file_get_contents(resource_path('certs/isrg-root-x1.pem'));
        $this->assertSame('96bcec06264976f37460779acf28c5a7cfe8a3c0aae11a8ffcee05c0bddf08c6', openssl_x509_fingerprint($certificate, 'sha256'));
    }

    public function test_placeholder_password_stops_before_any_online_connection(): void
    {
        $this->connection($this->configuration())->shouldNotReceive('getPdo');
        $this->assertSame(1, Artisan::call('terangdesa:tidb-check'));
        $output = Artisan::output();
        $this->assertSame('pending', json_decode($output, true)['status']);
        $this->assertStringContainsString('DB_PASSWORD', $output);
        $this->assertStringNotContainsString('private-user-canary', $output);
    }

    public function test_disabled_certificate_verification_is_rejected(): void
    {
        $configuration = $this->configuration();
        $configuration['options'][PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        $this->connection($configuration)->shouldNotReceive('getPdo');
        $this->assertSame(1, Artisan::call('terangdesa:tidb-check'));
        $this->assertSame('failed', json_decode(Artisan::output(), true)['status']);
    }

    public function test_url_resolution_to_a_different_host_is_rejected(): void
    {
        $this->connection($this->configuration(['host' => '127.0.0.1']))->shouldNotReceive('getPdo');
        $this->assertSame(1, Artisan::call('terangdesa:tidb-check'));
        $this->assertSame('configuration', json_decode(Artisan::output(), true)['stage']);
    }

    public function test_authentication_error_redacts_credentials_and_raw_exception(): void
    {
        $error = new PDOException('private-password-canary private-user-canary');
        $error->errorInfo = ['28000', 1045, 'private-password-canary'];
        $this->connection($this->configuration(['password' => 'private-password-canary']))
            ->shouldReceive('getPdo')->once()->andThrow($error);
        $this->assertSame(1, Artisan::call('terangdesa:tidb-check'));
        $output = Artisan::output();
        $this->assertSame(1045, json_decode($output, true)['driver_code']);
        $this->assertStringContainsString('Autentikasi ditolak', $output);
        $this->assertStringNotContainsString('private-password-canary', $output);
        $this->assertStringNotContainsString('private-user-canary', $output);
    }
}
