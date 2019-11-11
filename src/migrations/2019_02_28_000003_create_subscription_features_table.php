<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubscriptionFeaturesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subscription_features', function (Blueprint $table) {
            $table->increments('id');
            $table->morphs('subscribable');
            $table->string('code');
            $table->string('value');
            $table->string('interval');
            $table->timestamps();

            $table->unique(['subscribable_id', 'subscribable_type', 'code'], 'uniq');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subscription_features');
    }
}
