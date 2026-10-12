<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->string('slug', 64)->unique();
            $table->timestamps();
        });

        Schema::create('translation_keys', function (Blueprint $table) {
            $table->id();
            $table->string('message_key', 191)->unique();
            $table->string('key_group', 64)->index();
            $table->string('description', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('translation_key_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
            $table->unique(['translation_key_id', 'language_id'], 'translations_key_language_unique');
            $table->index('language_id', 'translations_language_index');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('translations', function (Blueprint $table) {
                $table->fullText('content', 'translations_content_fulltext');
            });
        }

        Schema::create('translation_key_tag', function (Blueprint $table) {
            $table->foreignId('translation_key_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['translation_key_id', 'tag_id']);
            $table->index('tag_id', 'translation_key_tag_tag_index');
        });

        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->char('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('translation_revisions', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamp('updated_at')->nullable();
        });

        DB::table('translation_revisions')->insert([
            'id' => 1,
            'version' => 1,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_key_tag');
        Schema::dropIfExists('translations');
        Schema::dropIfExists('translation_keys');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('languages');
        Schema::dropIfExists('api_tokens');
        Schema::dropIfExists('translation_revisions');
    }
};
