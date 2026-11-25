<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('courier_providers', function (Blueprint $table) {
            $table->id(); $table->string('code',40)->unique(); $table->string('name',120); $table->text('description')->nullable(); $table->string('logo_url')->nullable();
            $table->boolean('is_enabled')->default(false)->index(); $table->unsignedSmallInteger('priority')->default(100)->index(); $table->string('environment',20)->default('sandbox');
            $table->json('capabilities')->nullable(); $table->json('service_zones')->nullable(); $table->text('credentials')->nullable(); $table->json('settings')->nullable();
            $table->timestamp('last_health_check_at')->nullable(); $table->string('health_status',30)->default('not_configured'); $table->timestamps();
        });
        Schema::create('courier_rate_rules', function (Blueprint $table) {
            $table->id(); $table->foreignId('courier_provider_id')->constrained('courier_providers')->cascadeOnDelete();
            $table->string('zone_code',40); $table->unsignedInteger('min_weight_grams')->default(0); $table->unsignedInteger('max_weight_grams')->nullable();
            $table->decimal('base_charge',12,2); $table->decimal('additional_per_kg',12,2)->default(0); $table->decimal('cod_charge_percent',7,3)->default(0);
            $table->boolean('is_enabled')->default(true); $table->unsignedSmallInteger('sort_order')->default(0); $table->timestamps();
            $table->index(['courier_provider_id','zone_code','is_enabled']);
        });
        Schema::create('courier_provider_audits', function (Blueprint $table) {
            $table->id(); $table->foreignId('courier_provider_id')->nullable()->constrained('courier_providers')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete(); $table->string('action',40); $table->json('changes')->nullable(); $table->timestamp('created_at')->useCurrent();
            $table->index(['courier_provider_id','created_at']);
        });
        $now = now();
        foreach ([['steadfast','Steadfast'],['carrybee','CarryBee'],['bangladesh_post','Bangladesh Post Office'],['pathao','Pathao Courier'],['redx','REDX']] as $i => [$code,$name]) {
            DB::table('courier_providers')->insert(['code'=>$code,'name'=>$name,'is_enabled'=>false,'priority'=>($i+1)*10,'environment'=>'sandbox','capabilities'=>json_encode(['booking','tracking','cod']),'service_zones'=>json_encode(['inside_dhaka','outside_dhaka','remote_special']),'credentials'=>null,'settings'=>json_encode([]),'health_status'=>'not_configured','created_at'=>$now,'updated_at'=>$now]);
        }
    }
    public function down(): void { Schema::dropIfExists('courier_provider_audits'); Schema::dropIfExists('courier_rate_rules'); Schema::dropIfExists('courier_providers'); }
};
