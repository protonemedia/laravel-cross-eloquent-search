<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('video_id')->nullable();
            $table->string('title');
            $table->date('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('post_id');
            $table->string('body');
            $table->date('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('videos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->date('published_at')->nullable();
            $table->timestamps();

            $table->fullText('title');
        });

        Schema::create('blogs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('subtitle');
            $table->string('body');

            if (config('database.default') === 'mysql') {
                $table->fullText('title');
                $table->fullText(['title', 'subtitle']);
                $table->fullText(['title', 'subtitle', 'body']);
            }

            $table->unsignedInteger('video_id')->nullable();

            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('body')->nullable();

            if (config('database.default') === 'mysql') {
                $table->fullText('title');
                $table->fullText(['title', 'subtitle']);
                $table->fullText(['title', 'subtitle', 'body']);
            }

            $table->unsignedInteger('video_id')->nullable();

            $table->timestamps();
        });

        // Create PostgreSQL extensions and indexes
        if (config('database.default') === 'pgsql') {
            // Enable pg_trgm extension for similarity search (SOUNDS LIKE equivalent)
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            // Blogs table indexes
            DB::statement('CREATE INDEX IF NOT EXISTS blogs_title_fts ON blogs USING gin (to_tsvector(\'english\', title))');
            DB::statement('CREATE INDEX IF NOT EXISTS blogs_title_subtitle_fts ON blogs USING gin (to_tsvector(\'english\', title || \' \' || subtitle))');
            DB::statement('CREATE INDEX IF NOT EXISTS blogs_all_fts ON blogs USING gin (to_tsvector(\'english\', title || \' \' || subtitle || \' \' || body))');
            
            // Pages table indexes (handling nullable fields)
            DB::statement('CREATE INDEX IF NOT EXISTS pages_title_fts ON pages USING gin (to_tsvector(\'english\', title))');
            DB::statement('CREATE INDEX IF NOT EXISTS pages_title_subtitle_fts ON pages USING gin (to_tsvector(\'english\', COALESCE(title, \'\') || \' \' || COALESCE(subtitle, \'\')))');
            DB::statement('CREATE INDEX IF NOT EXISTS pages_all_fts ON pages USING gin (to_tsvector(\'english\', COALESCE(title, \'\') || \' \' || COALESCE(subtitle, \'\') || \' \' || COALESCE(body, \'\')))');
        }
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('posts');
    }
}
