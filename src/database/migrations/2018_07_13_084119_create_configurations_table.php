<?php /** @noinspection ALL */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConfigurationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     */
    public function up()
    {
        Schema::create('configurations', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->string('type');
            $table->json('xpaths');
            $table->boolean('optional')->nullable()->default(false);
            $table->json('default')->nullable()->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down()
    {
        Schema::dropIfExists('configurations');
    }
}
