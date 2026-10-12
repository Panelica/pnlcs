<?php

use App\Models\Product;
use App\Support\UniqueSlug;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Products used to take Str::slug(name) as it came, so two products with the
 * same name shared a slug. The order page finds a product by its slug, so only
 * the oldest of them could be opened; every other one led to that product, or
 * to a 404 when it was hidden.
 *
 * The oldest keeps its slug, and with it every link already out there. Each
 * later one gets its own ("vps-s-2"); its old link never reached it anyway.
 * A product with no slug at all gets one from its name.
 */
return new class extends Migration
{
    public function up(): void
    {
        $seen = [];
        foreach (DB::table('products')->orderBy('id')->get(['id', 'name', 'slug']) as $product) {
            $slug = (string) $product->slug;
            if ($slug !== '' && ! isset($seen[$slug])) {
                $seen[$slug] = true;

                continue;
            }
            $new = UniqueSlug::for(Product::class, (string) $product->name, 'product', (int) $product->id);
            DB::table('products')->where('id', $product->id)->update(['slug' => $new]);
            $seen[$new] = true;
        }
    }

    public function down(): void
    {
        // The shared slugs were the bug; nothing to put back.
    }
};
