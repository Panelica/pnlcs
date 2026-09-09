<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resmi fatura için gereken alıcı kimliği faturaya da yazılsın.
 *
 * Alıcının vergi dairesi, TCKN'si ve gerçek/tüzel kişi olduğu bilgisi Client
 * kaydında duruyordu ama faturaya kopyalanmıyordu; kopyalanan tek şey tax_id
 * idi. UBL-TR bunların üçünü de istiyor: tüzel kişide vergi dairesi zorunlu,
 * gerçek kişide kimlik numarası VKN yerine TCKN olarak gidiyor.
 *
 * Neden Client'tan okumak yetmiyor: müşteri altı ay sonra unvanını değiştirse
 * geçmiş faturanın alıcısı da değişmiş görünürdü. Fatura, kesildiği andaki
 * hâli saklamak zorunda - zaten diğer buyer_* alanlarının var olma sebebi bu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('buyer_tax_office')->nullable()->after('buyer_tax_id');
            $table->string('buyer_national_id', 20)->nullable()->after('buyer_tax_office');
            $table->string('buyer_client_type', 20)->nullable()->after('buyer_national_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['buyer_tax_office', 'buyer_national_id', 'buyer_client_type']);
        });
    }
};
