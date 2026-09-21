{{--
     SSL sertifikaları.

     Bu sayfa bir ürün satmıyor; pakete dahil olan bir özelliği anlatıyor.
     Yazan her şey sunucunun bugünkü davranışıdır — sertifika sağlayıcısı,
     kapsam ve yenileme süresi canlı sistemden doğrulanarak yazıldı. Bir
     davranış değişirse bu metin de değişmeli, tersi değil.

     legal.layout paylaşılıyor: Hakkımızda sayfası da aynı düzeni kullanıyor.

     Metin artık dosyanın içinde değil, çeviri anahtarlarında: sayfa iki dili
     ayrı @if bloklarında taşıyordu ve bir düzeltme için dosyayı değiştirmek
     gerekiyordu. Anahtar adları kardeş sayfa about.blade.php'nin kullandığı
     client.pages.* düzenini sürdürür; değerler
     2026_09_20_110000_seed_ssl_page_translations ile gelir, oradaki başlık
     yorumu neden dosya değil de satır olduklarını anlatır.

     Cümlenin ortasındaki <strong>/<em>/<code> etiketleri değerin içinde
     kaldı — bir anahtar cümle taşır, parça değil. O satırlar trans_markup()
     ile basılıyor: değer önce tamamen kaçırılır, sonra yalnızca izin verilen
     birkaç satır-içi etiket geri konur (App\Support\InlineMarkup). Bu sayfa
     oturum açmadan görülüyor ve değerler çeviri düzenleyicisinden geliyor;
     düz {!! !!} olsaydı düzenleyiciye yazılan bir <script> her ziyaretçide
     çalışırdı. İletişim bağlantısı :link olarak veriliyor ki route() görünümde
     kalsın; izin listesi onu da aynı kuralla denetler.
--}}
@extends('legal.layout')

@section('legal-title', __('client.pages.ssl.title'))
@section('legal-description', __('client.pages.ssl.description'))

@section('legal-content')
    <div class="legal-head">
        <h1>{{ __('client.pages.ssl.title') }}</h1>
        <p>{{ __('client.pages.ssl.intro') }}</p>
    </div>

    <div class="legal-grid">
        <nav class="legal-side">
            <p class="legal-side-title">{{ __('client.pages.ssl.on_this_page') }}</p>
            <a href="#dahil">{{ __('client.pages.ssl.included') }}</a>
            <a href="#nasil">{{ __('client.pages.ssl.how') }}</a>
            <a href="#kapsam">{{ __('client.pages.ssl.covers') }}</a>
            <a href="#fark">{{ __('client.pages.ssl.renewal') }}</a>
            <a href="#kurumsal">{{ __('client.pages.ssl.corporate') }}</a>
        </nav>

        <article class="legal-body">
            <h2 id="dahil">{{ __('client.pages.ssl.included') }}</h2>

            <p>{{ __('client.pages.ssl.included_intro') }}</p>
            <ul>
                <li><strong>{{ __('client.pages.ssl.included_free_label') }}</strong> — {{ __('client.pages.ssl.included_free') }}</li>
                <li><strong>{{ __('client.pages.ssl.included_install_label') }}</strong> — {{ __('client.pages.ssl.included_install') }}</li>
                <li><strong>{{ __('client.pages.ssl.included_renew_label') }}</strong> — {{ __('client.pages.ssl.included_renew') }}</li>
                <li><strong>{{ __('client.pages.ssl.included_domains_label') }}</strong> — {{ __('client.pages.ssl.included_domains') }}</li>
            </ul>
            <p>{{ trans_markup('client.pages.ssl.included_issuer') }}</p>

            <h2 id="nasil">{{ __('client.pages.ssl.how') }}</h2>

            <p>{{ __('client.pages.ssl.how_p1') }}</p>
            <p>{{ trans_markup('client.pages.ssl.how_p2') }}</p>
            <p>{{ __('client.pages.ssl.how_p3') }}</p>
            <p>{{ __('client.pages.ssl.how_p4') }}</p>

            <h2 id="kapsam">{{ __('client.pages.ssl.covers') }}</h2>

            <ul>
                <li><strong>{{ __('client.pages.ssl.covers_domain_label') }}</strong> — {{ trans_markup('client.pages.ssl.covers_domain') }}</li>
                <li><strong>{{ __('client.pages.ssl.covers_subdomains_label') }}</strong> — {{ trans_markup('client.pages.ssl.covers_subdomains') }}</li>
                <li><strong>{{ __('client.pages.ssl.covers_mail_label') }}</strong> — {{ __('client.pages.ssl.covers_mail') }}</li>
                <li><strong>{{ __('client.pages.ssl.covers_redirect_label') }}</strong> — {{ __('client.pages.ssl.covers_redirect') }}</li>
            </ul>
            <p>{{ __('client.pages.ssl.covers_note') }}</p>

            <h2 id="fark">{{ __('client.pages.ssl.renewal') }}</h2>

            <p>{{ __('client.pages.ssl.renewal_p1') }}</p>
            <p>{{ trans_markup('client.pages.ssl.renewal_p2') }}</p>
            <p>{{ __('client.pages.ssl.renewal_p3') }}</p>

            <h2 id="kurumsal">{{ __('client.pages.ssl.corporate') }}</h2>

            <p>{{ __('client.pages.ssl.corporate_p1') }}</p>
            <p>{{ trans_markup('client.pages.ssl.corporate_p2') }}</p>
            {{-- Kaçırma trans_markup()'ın içinde bir kez yapılıyor; burada
                 e() çağırmak çifte kaçırma olurdu. --}}
            <p>{{ trans_markup('client.pages.ssl.corporate_p3', [
                'link' => '<a href="'.route('client.contact').'">'.__('client.pages.ssl.corporate_link').'</a>',
            ]) }}</p>
        </article>
    </div>
@endsection
