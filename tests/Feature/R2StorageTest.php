<?php

namespace Tests\Feature;

use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class R2StorageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.disks.r2' => array_replace(config('filesystems.disks.r2'), [
            'key' => 'test-access-key-canary', 'secret' => 'test-secret-key-canary',
            'endpoint' => 'https://'.str_repeat('a', 32).'.r2.cloudflarestorage.com',
            'bucket' => 'terangdesa-bukti',
        ])]);
    }

    private function runCheck(bool $write = false): array
    {
        $code = Artisan::call('terangdesa:r2-check', $write ? ['--write-test' => true] : []);
        $output = Artisan::output();
        $this->assertStringNotContainsString('test-access-key-canary', $output);
        $this->assertStringNotContainsString('test-secret-key-canary', $output);

        return [$code, json_decode($output, true, 512, JSON_THROW_ON_ERROR)];
    }

    private function disk(): object
    {
        $disk = Mockery::mock(AwsS3V3Adapter::class);
        $client = Mockery::mock();
        $disk->shouldReceive('getClient')->once()->andReturn($client);
        $client->shouldReceive('headBucket')->once()->with(['Bucket' => 'terangdesa-bukti'])->andReturn([]);
        Storage::shouldReceive('disk')->once()->with('r2')->andReturn($disk);

        return $disk;
    }

    public function test_missing_credentials_stop_before_connecting(): void
    {
        config(['filesystems.disks.r2.key' => '', 'filesystems.disks.r2.secret' => '']);
        Storage::shouldReceive('disk')->never();
        [$code, $result] = $this->runCheck();
        $this->assertSame(1, $code);
        $this->assertSame('pending', $result['status']);
        $this->assertSame(['R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY'], $result['missing']);
    }

    public function test_invalid_endpoints_do_not_receive_credentials(): void
    {
        Storage::shouldReceive('disk')->never();
        foreach (['http://'.str_repeat('a', 32).'.r2.cloudflarestorage.com',
            'https://storage.example.test', 'https://'.str_repeat('a', 32).'.r2.cloudflarestorage.com/another-bucket',
            'https://username@'.str_repeat('a', 32).'.r2.cloudflarestorage.com'] as $endpoint) {
            config(['filesystems.disks.r2.endpoint' => $endpoint]);
            [$code, $result] = $this->runCheck();
            $this->assertSame(1, $code);
            $this->assertSame('configuration', $result['stage']);
        }
    }

    public function test_default_check_only_reads_bucket_metadata(): void
    {
        $disk = $this->disk();
        $disk->shouldNotReceive('put');
        $disk->shouldNotReceive('delete');
        [$code, $result] = $this->runCheck();
        $this->assertSame(0, $code);
        $this->assertSame('ready', $result['status']);
        $this->assertFalse($result['write_test']);
    }

    public function test_write_check_reads_and_removes_only_its_unique_object(): void
    {
        $disk = $this->disk();
        $path = null;
        $payload = null;
        $disk->shouldReceive('put')->once()->withArgs(function ($key, $body, $options) use (&$path, &$payload) {
            $path = $key; $payload = $body;
            return preg_match('/^connection-checks\/[a-f0-9-]{36}\.txt$/', $key) && $options['ContentType'] === 'text/plain';
        })->andReturnTrue();
        $disk->shouldReceive('get')->once()->withArgs(function ($key) use (&$path) { return $key === $path; })
            ->andReturnUsing(function () use (&$payload) { return $payload; });
        $disk->shouldReceive('delete')->once()->withArgs(function ($key) use (&$path) { return $key === $path; })->andReturnTrue();
        $disk->shouldReceive('exists')->once()->withArgs(function ($key) use (&$path) { return $key === $path; })->andReturnFalse();
        [$code, $result] = $this->runCheck(true);
        $this->assertSame(0, $code);
        $this->assertSame('ready', $result['status']);
        $this->assertSame('deleted', $result['cleanup']);
    }

    public function test_failed_read_still_cleans_up_and_hides_sdk_exception(): void
    {
        $disk = $this->disk();
        $disk->shouldReceive('put')->once()->andReturnTrue();
        $disk->shouldReceive('get')->once()->andThrow(new RuntimeException('test-secret-key-canary private-request-canary'));
        $disk->shouldReceive('delete')->once()->andReturnTrue();
        $disk->shouldReceive('exists')->once()->andReturnFalse();
        [$code, $result] = $this->runCheck(true);
        $this->assertSame(1, $code);
        $this->assertSame('read', $result['stage']);
        $this->assertSame('deleted', $result['cleanup']);
        $this->assertStringNotContainsString('private-request-canary', Artisan::output());
    }

    public function test_cleanup_failure_is_reported_with_only_test_object_path(): void
    {
        $disk = $this->disk();
        $payload = null;
        $disk->shouldReceive('put')->once()->andReturnUsing(function ($path, $body) use (&$payload) { $payload = $body; return true; });
        $disk->shouldReceive('get')->once()->andReturnUsing(function () use (&$payload) { return $payload; });
        $disk->shouldReceive('delete')->once()->andReturnFalse();
        [$code, $result] = $this->runCheck(true);
        $this->assertSame(1, $code);
        $this->assertSame('cleanup', $result['stage']);
        $this->assertStringStartsWith('connection-checks/', $result['test_object']);
    }

    public function test_private_adapter_can_generate_s3_temporary_urls_without_network(): void
    {
        $disk = Storage::disk('r2');
        $this->assertInstanceOf(AwsS3V3Adapter::class, $disk);
        $url = $disk->temporaryUrl('example/test.jpg', now()->addMinutes(5));
        $this->assertSame('https', parse_url($url, PHP_URL_SCHEME));
        $this->assertSame(str_repeat('a', 32).'.r2.cloudflarestorage.com', parse_url($url, PHP_URL_HOST));
        $this->assertSame('/terangdesa-bukti/example/test.jpg', parse_url($url, PHP_URL_PATH));
        $this->assertStringNotContainsString('test-secret-key-canary', $url);
    }
}
