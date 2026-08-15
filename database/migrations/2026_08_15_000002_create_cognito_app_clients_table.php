<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cognito_app_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained()->onDelete('cascade');
            $table->string('client_id')->unique()->comment('Cognito App Client ID');
            $table->string('client_secret')->nullable()->comment('Cognito App Client Secret (if confidential)');
            $table->string('platform')->nullable()->comment('web, android, ios, desktop');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['app_id', 'client_id']);
            $table->index('client_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cognito_app_clients');
    }
};
