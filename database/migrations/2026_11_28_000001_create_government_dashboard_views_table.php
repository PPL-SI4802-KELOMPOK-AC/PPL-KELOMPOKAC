<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = $this->driver();

        if ($driver === 'mysql') {
            // Keep constraints in the CREATE so a failed FK or index cannot leave a partial table.
            DB::statement(<<<'SQL'
                CREATE TABLE `government_dashboard_views` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `user_id` BIGINT UNSIGNED NOT NULL,
                    `name` VARCHAR(100) NOT NULL,
                    `normalized_name` VARBINARY(1024) NOT NULL,
                    `year` SMALLINT NULL,
                    `kabupaten` VARCHAR(4) NULL,
                    `kecamatan` VARCHAR(8) NULL,
                    `desa` VARCHAR(13) NULL,
                    `kondisi` VARCHAR(64) NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    UNIQUE KEY `government_dashboard_views_user_normalized_unique` (`user_id`, `normalized_name`),
                    CONSTRAINT `government_dashboard_views_user_id_foreign`
                        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL);

            return;
        }

        Schema::create('government_dashboard_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->binary('normalized_name', length: 1024);
            $table->smallInteger('year')->nullable();
            $table->string('kabupaten', 4)->nullable();
            $table->string('kecamatan', 8)->nullable();
            $table->string('desa', 13)->nullable();
            $table->string('kondisi', 64)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'normalized_name']);
        });
    }

    public function down(): void
    {
        $this->driver();

        Schema::dropIfExists('government_dashboard_views');
    }

    private function driver(): string
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new RuntimeException('Migration government_dashboard_views hanya mendukung driver mysql dan sqlite.');
        }

        return $driver;
    }
};
