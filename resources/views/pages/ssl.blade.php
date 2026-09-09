{{--
     SSL sertifikaları.

     Bu sayfa bir ürün satmıyor; pakete dahil olan bir özelliği anlatıyor.
     Yazan her şey sunucunun bugünkü davranışıdır — sertifika sağlayıcısı,
     kapsam ve yenileme süresi canlı sistemden doğrulanarak yazıldı. Bir
     davranış değişirse bu metin de değişmeli, tersi değil.

     legal.layout paylaşılıyor: Hakkımızda sayfası da aynı düzeni kullanıyor.
--}}
@extends('legal.layout')

@php $tr = app()->getLocale() !== 'en'; @endphp

@section('legal-title', $tr ? 'SSL Sertifikaları' : 'SSL Certificates')
@section('legal-description', $tr
    ? 'Bütün hosting paketlerinde SSL sertifikası ücretsizdir, otomatik kurulur ve kendiliğinden yenilenir. Kurulum yok, 90 günde bir uğraşmak yok.'
    : 'An SSL certificate is included free with every hosting plan, installed automatically and renewed on its own. No setup, no 90-day chore.')

@section('legal-content')
    <div class="legal-head">
        <h1>{{ $tr ? 'SSL Sertifikaları' : 'SSL Certificates' }}</h1>
        <p>
            {{ $tr
                ? 'Bütün paketlerde ücretsiz, otomatik ve süresiz. Kurmanız gereken bir şey yok, 90 günde bir yenilemeniz gereken bir şey de yok.'
                : 'Free on every plan, automatic, with no end date. Nothing for you to install, and nothing to renew every 90 days.' }}
        </p>
    </div>

    <div class="legal-grid">
        <nav class="legal-side">
            <p class="legal-side-title">{{ $tr ? 'BU SAYFADA' : 'ON THIS PAGE' }}</p>
            <a href="#dahil">{{ $tr ? 'Pakete dahil' : "What's included" }}</a>
            <a href="#nasil">{{ $tr ? 'Nasıl çalışıyor' : 'How it works' }}</a>
            <a href="#kapsam">{{ $tr ? 'Neyi kapsıyor' : 'What it covers' }}</a>
            <a href="#fark">{{ $tr ? 'Elle yenileme derdi' : 'The renewal chore' }}</a>
            <a href="#kurumsal">{{ $tr ? 'Şirket doğrulamalı sertifika' : 'Company-validated certificates' }}</a>
        </nav>

        <article class="legal-body">
            <h2 id="dahil">{{ $tr ? 'Pakete dahil' : "What's included" }}</h2>

            @if($tr)
                <p>Hangi hosting paketini alırsanız alın, SSL sertifikası fiyata dahildir. Ayrıca satın alınan bir ürün değil, hesabın kendisiyle gelen bir özelliktir.</p>
                <ul>
                    <li><strong>Ücretsiz</strong> — paket ücretinin dışında hiçbir bedel yok, yenilemede de yok.</li>
                    <li><strong>Otomatik kurulum</strong> — talep etmenize gerek yok, sertifika kendiliğinden alınır ve siteye tanımlanır.</li>
                    <li><strong>Otomatik yenileme</strong> — süresi dolmadan kendiliğinden yenilenir. Sizin bir şey yapmanız gerekmez.</li>
                    <li><strong>Alan adı sayısı sınırsız</strong> — hesabınızdaki her alan adı ve alt alan adı için ayrı sertifika alınır.</li>
                </ul>
                <p>Sertifikalar <strong>Let's Encrypt</strong> tarafından veriliyor. Bütün tarayıcılar, mobil işletim sistemleri ve ödeme altyapıları tarafından tanınıyor; kilit simgesi ve HTTPS bakımından ücretli bir sertifikadan farkı yok.</p>
            @else
                <p>Whichever hosting plan you buy, an SSL certificate is part of the price. It is not a separate product you purchase; it comes with the account.</p>
                <ul>
                    <li><strong>Free</strong> — nothing beyond the plan price, and nothing at renewal either.</li>
                    <li><strong>Installed automatically</strong> — you do not have to request it; the certificate is obtained and applied on its own.</li>
                    <li><strong>Renewed automatically</strong> — it renews before it expires. Nothing is required from you.</li>
                    <li><strong>No limit on domains</strong> — every domain and subdomain in your account gets a certificate.</li>
                </ul>
                <p>Certificates are issued by <strong>Let's Encrypt</strong> and are trusted by every browser, mobile operating system and payment platform. In terms of the padlock and HTTPS, there is no difference from a paid certificate.</p>
            @endif

            <h2 id="nasil">{{ $tr ? 'Nasıl çalışıyor' : 'How it works' }}</h2>

            @if($tr)
                <p>Hesabınız açıldığında sunucu, siteniz ilk andan itibaren HTTPS ile açılabilsin diye geçici bir sertifika koyar. Bu geçici sertifikada tarayıcı uyarı gösterir; normaldir ve kısa sürelidir.</p>
                <p><strong>Alan adınız sunucumuzu göstermeye başladığı anda</strong> gerçek sertifika otomatik olarak alınır ve geçici olanın yerine geçer. Uyarı kaybolur, kilit simgesi belirir.</p>
                <p>Bu sıra önemli: sertifikayı verebilmek için alan adının bize baktığının doğrulanması gerekiyor. Bu yüzden yeni bir alan adı yönlendirdiğinizde sertifikanın oturması kısa bir zaman alabilir.</p>
                <p>Sonrasında ilgilenmeniz gereken bir şey kalmaz. Sertifikaların durumunu her gün kontrol ediyoruz; bir yenileme başarısız olursa bunu siz fark etmeden önce biz görüyoruz.</p>
            @else
                <p>When your account is created, the server installs a temporary certificate so your site can be reached over HTTPS from the first moment. Browsers show a warning on that temporary certificate; this is expected and short-lived.</p>
                <p><strong>As soon as your domain points to our server,</strong> the real certificate is obtained automatically and replaces the temporary one. The warning disappears and the padlock appears.</p>
                <p>The order matters: to issue the certificate, the authority must verify that the domain resolves to us. So when you point a new domain, the certificate can take a short while to settle.</p>
                <p>After that there is nothing left for you to do. We check certificate health every day, so if a renewal fails we see it before you do.</p>
            @endif

            <h2 id="kapsam">{{ $tr ? 'Neyi kapsıyor' : 'What it covers' }}</h2>

            @if($tr)
                <ul>
                    <li><strong>Alan adınız ve www hâli</strong> — <code>siteniz.com</code> ve <code>www.siteniz.com</code> aynı sertifikada.</li>
                    <li><strong>Alt alan adlarınız</strong> — panelden oluşturduğunuz her alt alan adı sertifikaya eklenir. <code>blog.siteniz.com</code>, <code>shop.siteniz.com</code> için ayrıca bir şey yapmanız gerekmez.</li>
                    <li><strong>Webmail ve posta sunucusu</strong> — e-posta hesaplarınız da şifreli bağlantıyla çalışır.</li>
                    <li><strong>HTTPS yönlendirmesi</strong> — isterseniz siteniz kendiliğinden HTTPS'e yönlendirilir; panelden açıp kapatabilirsiniz.</li>
                </ul>
                <p>Sertifikalar 90 gün geçerlidir ve süresi dolmadan yenilenir. Bu, ücretli sertifikaların bir yıllık süresinden kısa olduğu için kimi zaman eksiklik sanılıyor; aslında tersi — sertifika ne kadar kısa süreliyse çalınması hâlinde açık kalan pencere o kadar dar olur. Yenilemeyi biz yaptığımız için süre sizin açınızdan bir fark yaratmaz.</p>
            @else
                <ul>
                    <li><strong>Your domain and its www form</strong> — <code>yoursite.com</code> and <code>www.yoursite.com</code> on the same certificate.</li>
                    <li><strong>Your subdomains</strong> — every subdomain you create in the panel is added to the certificate. Nothing extra is needed for <code>blog.yoursite.com</code> or <code>shop.yoursite.com</code>.</li>
                    <li><strong>Webmail and the mail server</strong> — your email accounts run over encrypted connections too.</li>
                    <li><strong>HTTPS redirection</strong> — your site can redirect to HTTPS automatically; you can turn this on or off in the panel.</li>
                </ul>
                <p>Certificates are valid for 90 days and are renewed before they expire. Because paid certificates run for a year, the shorter term is sometimes mistaken for a shortcoming; it is the opposite — the shorter the certificate, the narrower the window if a key is ever stolen. Since we handle renewal, the term makes no difference to you.</p>
            @endif

            <h2 id="fark">{{ $tr ? 'Elle yenileme derdi' : 'The renewal chore' }}</h2>

            @if($tr)
                <p>Ücretsiz SSL'i bugün hemen her hosting firması veriyor. Fark, verilme biçiminde.</p>
                <p>Bazı sağlayıcılarda sertifikayı <em>siz</em> kuruyorsunuz ve 90 günde bir <em>siz</em> yeniliyorsunuz — çoğu zaman DNS kaydı ekleyip doğrulama beklemek gerekiyor. Unuttuğunuz gün siteniz ziyaretçiye güvenlik uyarısı gösteriyor.</p>
                <p>Bizde böyle bir işlem yok. Sertifika alınırken de yenilenirken de sizin bir şey yapmanız gerekmiyor, panele girmeniz bile gerekmiyor.</p>
            @else
                <p>Almost every hosting company offers free SSL today. The difference is in how it is delivered.</p>
                <p>With some providers <em>you</em> install the certificate and <em>you</em> renew it every 90 days — often by adding a DNS record and waiting for validation. The day you forget, your visitors get a security warning.</p>
                <p>There is no such step here. Neither issuance nor renewal asks anything of you; you do not even need to log in.</p>
            @endif

            <h2 id="kurumsal">{{ $tr ? 'Şirket doğrulamalı sertifika' : 'Company-validated certificates' }}</h2>

            @if($tr)
                <p>Ücretsiz sertifika, alan adının size ait olduğunu doğrular. Bu, siteler için yeterlidir ve tarayıcıdaki kilit simgesi bakımından hiçbir eksiği yoktur.</p>
                <p>Bazı kurumsal kullanımlarda daha fazlası isteniyor: sertifikanın <strong>şirketinizin kimliğini de doğrulaması</strong> (OV), tarayıcıda şirket unvanının görünmesi (EV), ya da belli bir para garantisi. Bunlar ücretsiz sertifikalarla verilemiyor; ayrı bir doğrulama süreci ve evrak gerektiriyor.</p>
                <p>Böyle bir sertifikaya ihtiyacınız varsa <a href="{{ route('client.contact') }}">bize yazın</a>; ihtiyacınıza uygun olanı ve süresini birlikte belirleyelim.</p>
            @else
                <p>The free certificate proves that the domain belongs to you. For a website that is enough, and as far as the browser padlock goes it lacks nothing.</p>
                <p>Some corporate uses ask for more: a certificate that also <strong>validates your company's identity</strong> (OV), shows your company name in the browser (EV), or carries a stated warranty. These cannot be issued for free; they require a separate validation process and paperwork.</p>
                <p>If you need one, <a href="{{ route('client.contact') }}">write to us</a> and we will work out which one fits and how long it takes.</p>
            @endif
        </article>
    </div>
@endsection
