<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShopifyConfigTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shopify_config', function (Blueprint $table) {
            $table->integer('service_id')->unsigned()->primary();
            $table->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
            
            $table->string('shop_domain');
            $table->string('api_key');
            $table->longText('api_secret');
            $table->longText('access_token');
            $table->string('api_version')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shopify_config');
    }
} 