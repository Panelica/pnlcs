<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The public SSL page, /ssl.
 *
 * The page carried its English and its Turkish inline, in two @if($tr) halves,
 * and nothing outside the file could reach either one. This moves the visible
 * text into keys so the copy has one home and can be corrected without a
 * deployment.
 *
 * WHY THE TABLE AND NOT lang/en/client.php. The sibling page about.blade.php
 * reads client.pages.*, so the key names follow it - group "client", prefix
 * "pages.ssl." - but the values are seeded here, the way every customer-facing
 * string added since has been (lang/en/client.php holds 426 keys and not one
 * of the 345 hosting.* strings the client area renders; those are all rows).
 * Adding 40 English keys to the file instead would put pl and zh 40 keys
 * behind English, and TranslationParityTest ("the complete languages stay
 * complete") holds those two at exactly zero - it would demand Polish and
 * Chinese marketing copy that nobody here can review. Every other locale
 * reads English through fallback_locale (config/app.php:83), which is what it
 * was served before this change.
 *
 * Turkish is the source: the page was written in Turkish and the English on it
 * is a translation of that. Both halves are carried over verbatim; only the
 * markup around them moved.
 *
 * Emphasis tags stay inside the value where they sit mid-sentence, because a
 * key must hold a sentence and not a fragment - the same shape as
 * lang/en/admin.php:235. The view prints those with {!! !!}. The one route
 * link is passed in as :link so the URL stays in the view.
 *
 * Cached translation groups are flushed so the strings appear immediately
 * (see 2026_08_13_150000_flush_stale_translation_cache).
 */
return new class extends Migration
{
    private const GROUP = 'client';

    /** [key => [en, tr]] */
    private function rows(): array
    {
        return [
            'pages.ssl.title' => ['SSL Certificates', 'SSL Sertifikaları'],
            'pages.ssl.description' => [
                'An SSL certificate is included free with every hosting plan, installed automatically and renewed on its own. No setup, no 90-day chore.',
                'Bütün hosting paketlerinde SSL sertifikası ücretsizdir, otomatik kurulur ve kendiliğinden yenilenir. Kurulum yok, 90 günde bir uğraşmak yok.',
            ],
            'pages.ssl.intro' => [
                'Free on every plan, automatic, with no end date. Nothing for you to install, and nothing to renew every 90 days.',
                'Bütün paketlerde ücretsiz, otomatik ve süresiz. Kurmanız gereken bir şey yok, 90 günde bir yenilemeniz gereken bir şey de yok.',
            ],
            // The side navigation title. Its own key rather than the existing
            // client.pages.on_this_page ("On this page"): this page and the
            // mail guide write it in capitals and legal/document.blade.php
            // does the same, so reusing about's key would change what a
            // visitor reads today.
            'pages.ssl.on_this_page' => ['ON THIS PAGE', 'BU SAYFADA'],

            // Section headings. Each one is the nav entry and the <h2>.
            'pages.ssl.included' => ['What\'s included', 'Pakete dahil'],
            'pages.ssl.how' => ['How it works', 'Nasıl çalışıyor'],
            'pages.ssl.covers' => ['What it covers', 'Neyi kapsıyor'],
            'pages.ssl.renewal' => ['The renewal chore', 'Elle yenileme derdi'],
            'pages.ssl.corporate' => ['Company-validated certificates', 'Şirket doğrulamalı sertifika'],

            'pages.ssl.included_intro' => [
                'Whichever hosting plan you buy, an SSL certificate is part of the price. It is not a separate product you purchase; it comes with the account.',
                'Hangi hosting paketini alırsanız alın, SSL sertifikası fiyata dahildir. Ayrıca satın alınan bir ürün değil, hesabın kendisiyle gelen bir özelliktir.',
            ],
            'pages.ssl.included_free_label' => ['Free', 'Ücretsiz'],
            'pages.ssl.included_free' => [
                'nothing beyond the plan price, and nothing at renewal either.',
                'paket ücretinin dışında hiçbir bedel yok, yenilemede de yok.',
            ],
            'pages.ssl.included_install_label' => ['Installed automatically', 'Otomatik kurulum'],
            'pages.ssl.included_install' => [
                'you do not have to request it; the certificate is obtained and applied on its own.',
                'talep etmenize gerek yok, sertifika kendiliğinden alınır ve siteye tanımlanır.',
            ],
            'pages.ssl.included_renew_label' => ['Renewed automatically', 'Otomatik yenileme'],
            'pages.ssl.included_renew' => [
                'it renews before it expires. Nothing is required from you.',
                'süresi dolmadan kendiliğinden yenilenir. Sizin bir şey yapmanız gerekmez.',
            ],
            'pages.ssl.included_domains_label' => ['No limit on domains', 'Alan adı sayısı sınırsız'],
            'pages.ssl.included_domains' => [
                'every domain and subdomain in your account gets a certificate.',
                'hesabınızdaki her alan adı ve alt alan adı için ayrı sertifika alınır.',
            ],
            'pages.ssl.included_issuer' => [
                'Certificates are issued by <strong>Let\'s Encrypt</strong> and are trusted by every browser, mobile operating system and payment platform. In terms of the padlock and HTTPS, there is no difference from a paid certificate.',
                'Sertifikalar <strong>Let\'s Encrypt</strong> tarafından veriliyor. Bütün tarayıcılar, mobil işletim sistemleri ve ödeme altyapıları tarafından tanınıyor; kilit simgesi ve HTTPS bakımından ücretli bir sertifikadan farkı yok.',
            ],

            'pages.ssl.how_p1' => [
                'When your account is created, the server installs a temporary certificate so your site can be reached over HTTPS from the first moment. Browsers show a warning on that temporary certificate; this is expected and short-lived.',
                'Hesabınız açıldığında sunucu, siteniz ilk andan itibaren HTTPS ile açılabilsin diye geçici bir sertifika koyar. Bu geçici sertifikada tarayıcı uyarı gösterir; normaldir ve kısa sürelidir.',
            ],
            'pages.ssl.how_p2' => [
                '<strong>As soon as your domain points to our server,</strong> the real certificate is obtained automatically and replaces the temporary one. The warning disappears and the padlock appears.',
                '<strong>Alan adınız sunucumuzu göstermeye başladığı anda</strong> gerçek sertifika otomatik olarak alınır ve geçici olanın yerine geçer. Uyarı kaybolur, kilit simgesi belirir.',
            ],
            'pages.ssl.how_p3' => [
                'The order matters: to issue the certificate, the authority must verify that the domain resolves to us. So when you point a new domain, the certificate can take a short while to settle.',
                'Bu sıra önemli: sertifikayı verebilmek için alan adının bize baktığının doğrulanması gerekiyor. Bu yüzden yeni bir alan adı yönlendirdiğinizde sertifikanın oturması kısa bir zaman alabilir.',
            ],
            'pages.ssl.how_p4' => [
                'After that there is nothing left for you to do. We check certificate health every day, so if a renewal fails we see it before you do.',
                'Sonrasında ilgilenmeniz gereken bir şey kalmaz. Sertifikaların durumunu her gün kontrol ediyoruz; bir yenileme başarısız olursa bunu siz fark etmeden önce biz görüyoruz.',
            ],

            'pages.ssl.covers_domain_label' => ['Your domain and its www form', 'Alan adınız ve www hâli'],
            'pages.ssl.covers_domain' => [
                '<code>yoursite.com</code> and <code>www.yoursite.com</code> on the same certificate.',
                '<code>siteniz.com</code> ve <code>www.siteniz.com</code> aynı sertifikada.',
            ],
            'pages.ssl.covers_subdomains_label' => ['Your subdomains', 'Alt alan adlarınız'],
            'pages.ssl.covers_subdomains' => [
                'every subdomain you create in the panel is added to the certificate. Nothing extra is needed for <code>blog.yoursite.com</code> or <code>shop.yoursite.com</code>.',
                'panelden oluşturduğunuz her alt alan adı sertifikaya eklenir. <code>blog.siteniz.com</code>, <code>shop.siteniz.com</code> için ayrıca bir şey yapmanız gerekmez.',
            ],
            'pages.ssl.covers_mail_label' => ['Webmail and the mail server', 'Webmail ve posta sunucusu'],
            'pages.ssl.covers_mail' => [
                'your email accounts run over encrypted connections too.',
                'e-posta hesaplarınız da şifreli bağlantıyla çalışır.',
            ],
            'pages.ssl.covers_redirect_label' => ['HTTPS redirection', 'HTTPS yönlendirmesi'],
            'pages.ssl.covers_redirect' => [
                'your site can redirect to HTTPS automatically; you can turn this on or off in the panel.',
                'isterseniz siteniz kendiliğinden HTTPS\'e yönlendirilir; panelden açıp kapatabilirsiniz.',
            ],
            'pages.ssl.covers_note' => [
                'Certificates are valid for 90 days and are renewed before they expire. Because paid certificates run for a year, the shorter term is sometimes mistaken for a shortcoming; it is the opposite — the shorter the certificate, the narrower the window if a key is ever stolen. Since we handle renewal, the term makes no difference to you.',
                'Sertifikalar 90 gün geçerlidir ve süresi dolmadan yenilenir. Bu, ücretli sertifikaların bir yıllık süresinden kısa olduğu için kimi zaman eksiklik sanılıyor; aslında tersi — sertifika ne kadar kısa süreliyse çalınması hâlinde açık kalan pencere o kadar dar olur. Yenilemeyi biz yaptığımız için süre sizin açınızdan bir fark yaratmaz.',
            ],

            'pages.ssl.renewal_p1' => [
                'Almost every hosting company offers free SSL today. The difference is in how it is delivered.',
                'Ücretsiz SSL\'i bugün hemen her hosting firması veriyor. Fark, verilme biçiminde.',
            ],
            'pages.ssl.renewal_p2' => [
                'With some providers <em>you</em> install the certificate and <em>you</em> renew it every 90 days — often by adding a DNS record and waiting for validation. The day you forget, your visitors get a security warning.',
                'Bazı sağlayıcılarda sertifikayı <em>siz</em> kuruyorsunuz ve 90 günde bir <em>siz</em> yeniliyorsunuz — çoğu zaman DNS kaydı ekleyip doğrulama beklemek gerekiyor. Unuttuğunuz gün siteniz ziyaretçiye güvenlik uyarısı gösteriyor.',
            ],
            'pages.ssl.renewal_p3' => [
                'There is no such step here. Neither issuance nor renewal asks anything of you; you do not even need to log in.',
                'Bizde böyle bir işlem yok. Sertifika alınırken de yenilenirken de sizin bir şey yapmanız gerekmiyor, panele girmeniz bile gerekmiyor.',
            ],

            'pages.ssl.corporate_p1' => [
                'The free certificate proves that the domain belongs to you. For a website that is enough, and as far as the browser padlock goes it lacks nothing.',
                'Ücretsiz sertifika, alan adının size ait olduğunu doğrular. Bu, siteler için yeterlidir ve tarayıcıdaki kilit simgesi bakımından hiçbir eksiği yoktur.',
            ],
            'pages.ssl.corporate_p2' => [
                'Some corporate uses ask for more: a certificate that also <strong>validates your company\'s identity</strong> (OV), shows your company name in the browser (EV), or carries a stated warranty. These cannot be issued for free; they require a separate validation process and paperwork.',
                'Bazı kurumsal kullanımlarda daha fazlası isteniyor: sertifikanın <strong>şirketinizin kimliğini de doğrulaması</strong> (OV), tarayıcıda şirket unvanının görünmesi (EV), ya da belli bir para garantisi. Bunlar ücretsiz sertifikalarla verilemiyor; ayrı bir doğrulama süreci ve evrak gerektiriyor.',
            ],
            // :link is built in the view so the route stays there.
            'pages.ssl.corporate_p3' => [
                'If you need one, :link and we will work out which one fits and how long it takes.',
                'Böyle bir sertifikaya ihtiyacınız varsa :link; ihtiyacınıza uygun olanı ve süresini birlikte belirleyelim.',
            ],
            'pages.ssl.corporate_link' => ['write to us', 'bize yazın'],
        ];
    }

    public function up(): void
    {
        $now = now();

        foreach ($this->rows() as $key => [$en, $tr]) {
            foreach (['en' => $en, 'tr' => $tr] as $language => $value) {
                // An operator may have already written this string through the
                // translation editor. Their text wins; a seed that replaced it
                // would be data loss.
                $exists = DB::table('dynamic_translations')
                    ->where('language', $language)->where('group', self::GROUP)->where('key', $key)->exists();

                if (! $exists) {
                    DB::table('dynamic_translations')->insert([
                        'language' => $language, 'group' => self::GROUP, 'key' => $key, 'value' => $value,
                        'is_auto_translated' => false, 'is_reviewed' => true,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }

        try {
            foreach (DB::table('dynamic_translations')->distinct()->pluck('language') as $language) {
                Cache::forget("translations:{$language}:".self::GROUP);
            }
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        DB::table('dynamic_translations')
            ->where('group', self::GROUP)
            ->whereIn('language', ['en', 'tr'])
            ->whereIn('key', array_keys($this->rows()))
            ->delete();
    }
};
