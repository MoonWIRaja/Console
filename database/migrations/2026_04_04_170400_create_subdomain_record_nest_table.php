<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subdomain_record_nest', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subdomain_record_id')->constrained('subdomain_records')->cascadeOnDelete();
            $table->unsignedInteger('nest_id');
            $table->timestamps();

            $table->unique(['subdomain_record_id', 'nest_id'], 'subdomain_record_nest_unique');
            $table->foreign('nest_id')->references('id')->on('nests')->cascadeOnDelete();
        });

        if (Schema::hasTable('subdomain_record_egg')) {
            $pairs = DB::table('subdomain_record_egg')
                ->join('eggs', 'eggs.id', '=', 'subdomain_record_egg.egg_id')
                ->select('subdomain_record_egg.subdomain_record_id', 'eggs.nest_id')
                ->distinct()
                ->get()
                ->map(fn ($row) => [
                    'subdomain_record_id' => (int) $row->subdomain_record_id,
                    'nest_id' => (int) $row->nest_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all();

            if (!empty($pairs)) {
                DB::table('subdomain_record_nest')->insert($pairs);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subdomain_record_nest');
    }
};
