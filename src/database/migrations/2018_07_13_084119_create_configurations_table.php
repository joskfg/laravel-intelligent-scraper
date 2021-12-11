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
            $table->string('name')->primary()
                ->comment('The name of the field.');
            $table->string('type')
                ->comment('The scrape type.');
            $table->json('xpaths')
                ->comment('Array of XPaths to extract data from scrape.');
            $table->string('chain_type')
                ->comment('Allow automatic scraping of the field scrapped value using another type.')
                ->nullable()->default(null);
            $table->boolean('optional')
                ->comment('Whether the field is optional.')
                ->nullable()->default(false);
            $table->json('default')
                ->comment('The default value for the field.')
                ->nullable()->default(null);

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
