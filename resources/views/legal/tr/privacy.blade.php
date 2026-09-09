<p>Bu politika, {{ \App\Http\Controllers\LegalController::partyName($company, $tr) }} olarak <strong>kendi müşterilerimize ait</strong> kişisel verileri nasıl işlediğimizi açıklar. Türkiye'de 6698 sayılı Kişisel Verilerin Korunması Kanunu (KVKK), Avrupa Birliği'nde Genel Veri Koruma Tüzüğü (GDPR), Birleşik Krallık'ta UK GDPR ve Kaliforniya'da CCPA/CPRA kapsamındaki yükümlülüklerimizi kapsar.</p>

<div class="legal-note">
    <p>Bu politika, <strong>bizim veri sorumlusu</strong> olduğumuz verilerle ilgilidir: hesabınız, faturanız, destek yazışmanız. Sizin sitenizde barındırdığınız ziyaretçi verilerinde ise veri sorumlusu sizsiniz, biz yalnızca <strong>veri işleyeniz</strong> — bu ilişki <a href="{{ route('legal.show', 'dpa') }}">Veri İşleme Sözleşmesi</a>'nde düzenlenmiştir.</p>
</div>

<h2>1. Veri sorumlusu</h2>
@include('legal.partials.seller')

<h2>2. İşlediğimiz veriler</h2>
<table>
    <thead><tr><th>Kategori</th><th>İçerdiği veriler</th><th>Kaynağı</th></tr></thead>
    <tbody>
        <tr><td>Kimlik ve iletişim</td><td>Ad soyad, unvan, e-posta, telefon, adres, ülke</td><td>Sizden, kayıt sırasında</td></tr>
        <tr><td>Fatura ve finans</td><td>Vergi dairesi ve numarası, fatura kayıtları, ödeme tutarı ve tarihi, havale referansı</td><td>Sizden ve bankamızdan</td></tr>
        <tr><td>Hesap ve hizmet</td><td>Kullanıcı adı, şifre özeti (hash), hizmet ve alan adı kayıtları, kaynak kullanımı</td><td>Sistemlerimizce üretilir</td></tr>
        <tr><td>Alan adı tescil</td><td>Tescil için zorunlu ad, adres, e-posta ve telefon bilgileri</td><td>Sizden; tescil kuruluşuna aktarılır</td></tr>
        <tr><td>Teknik kayıtlar</td><td>IP adresi, tarayıcı bilgisi, giriş zamanları, sunucu ve güvenlik logları</td><td>Otomatik olarak toplanır</td></tr>
        <tr><td>Destek</td><td>Destek talepleri, e-posta yazışmaları, ekler</td><td>Sizden</td></tr>
    </tbody>
</table>
<p>Kredi kartı bilgilerinizi <strong>toplamıyoruz ve saklamıyoruz.</strong> Kart ödemeleri, PCI DSS uyumlu bir ödeme kuruluşu üzerinden yürütülür; kart numarası, son kullanma tarihi ve güvenlik kodu bizim sistemlerimize hiç girmez. Bize yalnızca ödemenin sonucu (onaylandı/reddedildi), tutarı ve kartın son dört hanesi ile türü gibi işlemi eşleştirmeye yarayan bilgiler ulaşır.</p>

<h2>3. İşleme amaçları ve hukuki sebepler</h2>
<table>
    <thead><tr><th>Amaç</th><th>Hukuki sebep (KVKK m.5 / GDPR m.6)</th></tr></thead>
    <tbody>
        <tr><td>Hizmetin kurulması, sunulması ve yönetilmesi</td><td>Sözleşmenin ifası</td></tr>
        <tr><td>Faturalama, tahsilat ve muhasebe</td><td>Hukuki yükümlülük ve sözleşmenin ifası</td></tr>
        <tr><td>Alan adı tescili ve WHOIS yükümlülükleri</td><td>Sözleşmenin ifası ve hukuki yükümlülük</td></tr>
        <tr><td>Destek verilmesi</td><td>Sözleşmenin ifası</td></tr>
        <tr><td>Ağ ve sistem güvenliği, kötüye kullanımın önlenmesi</td><td>Meşru menfaat</td></tr>
        <tr><td>Zorunlu hizmet bildirimleri (kesinti, bakım, fatura)</td><td>Sözleşmenin ifası</td></tr>
        <tr><td>Ticari elektronik ileti (kampanya, duyuru)</td><td>Açık rıza — dilediğiniz an geri alabilirsiniz</td></tr>
        <tr><td>Hukuki taleplerin takibi ve savunma</td><td>Meşru menfaat ve hukuki yükümlülük</td></tr>
    </tbody>
</table>
<p>Meşru menfaate dayandığımız hâllerde, menfaatimizin sizin hak ve özgürlüklerinizi aşmadığını değerlendirdik. Bu değerlendirmenin özetini talep edebilirsiniz.</p>

<h2>4. Saklama süreleri</h2>
<table>
    <thead><tr><th>Veri</th><th>Süre</th><th>Neden</th></tr></thead>
    <tbody>
        <tr><td>Fatura ve muhasebe kayıtları</td><td>10 yıl</td><td>Türk Ticaret Kanunu ve Vergi Usul Kanunu</td></tr>
        <tr><td>Hesap ve sözleşme kayıtları</td><td>Hizmet bitiminden sonra 10 yıl</td><td>Zamanaşımı süresi</td></tr>
        <tr><td>Trafik ve erişim logları</td><td>2 yıl</td><td>5651 sayılı Kanun</td></tr>
        <tr><td>Sunucu ve güvenlik logları</td><td>90 gün</td><td>Meşru menfaat — olay incelemesi</td></tr>
        <tr><td>Destek yazışmaları</td><td>Kapanmasından sonra 3 yıl</td><td>Hizmet kalitesi ve olası ihtilaf</td></tr>
        <tr><td>Askıya alınmış hesabın içeriği</td><td>30 gün</td><td>Geri dönüş imkânı tanımak</td></tr>
        <tr><td>Pazarlama izni ve geçmişi</td><td>İznin geri alınmasından sonra 3 yıl</td><td>İznin geri alındığını ispat</td></tr>
    </tbody>
</table>

<h2>5. Veri aktarımı</h2>
<p>Verilerinizi satmıyoruz. Yalnızca hizmeti sunmak için zorunlu olan taraflarla ve gereken kadarıyla paylaşıyoruz:</p>
<ul>
    <li><strong>Alan adı tescil kuruluşları ve aracı kurumlar</strong> — yalnızca kaydettiğiniz alan adları için, ICANN ve ilgili registry kurallarının zorunlu kıldığı tescil bilgileri.</li>
    <li><strong>Veri merkezi ve altyapı sağlayıcımız</strong> — sunucularımız Almanya'da barındırılmaktadır (Hetzner Online GmbH). Almanya, AB veri koruma rejimi kapsamındadır.</li>
    <li><strong>Bankamız ve ödeme kuruluşumuz</strong> — yalnızca ödemenin gerçekleştirilmesi için gereken bilgiler. Ödeme kuruluşu, kart verisi bakımından kendi gizlilik politikası kapsamında bağımsız veri sorumlusu sıfatıyla hareket eder.</li>
    <li><strong>Mali müşavir ve bağımsız denetim</strong> — muhasebe ve vergi yükümlülükleri kapsamında.</li>
    <li><strong>Yetkili kamu kurumları</strong> — yalnızca hukuken bağlayıcı ve usulüne uygun bir talep üzerine.</li>
</ul>
<p>Yurt dışına aktarım gereken hâllerde, aktarım GDPR kapsamında Avrupa Komisyonu'nun standart sözleşme hükümlerine (SCC) veya yeterlilik kararına; KVKK kapsamında ise Kanun'un aktarıma ilişkin hükümlerine dayanılarak yapılır.</p>

<h2>6. Güvenlik</h2>
<ul>
    <li>Tüm site ve panel trafiği TLS ile şifrelenir; sertifikalar otomatik yenilenir.</li>
    <li>Şifreler geri döndürülemez özet (hash) olarak saklanır, hiçbir zaman düz metin tutulmaz.</li>
    <li>Yönetim erişimi anahtar tabanlıdır ve iki adımlı doğrulama uygulanır.</li>
    <li>Her müşteri hesabı işletim sistemi düzeyinde diğerlerinden yalıtılmıştır.</li>
    <li>Sunucu güvenlik duvarı, ModSecurity ve zararlı yazılım taraması etkindir.</li>
    <li>Veritabanı yedekleri günlük alınır ve erişimi kısıtlı bir dizinde tutulur.</li>
</ul>
<p>Kişisel verilerinizin güvenliğini etkileyen bir ihlal yaşanması hâlinde, Kişisel Verileri Koruma Kurulu'na <strong>72 saat</strong> içinde, ilgili kişilere ise makul olan en kısa sürede bildirim yaparız.</p>

<h2>7. Haklarınız</h2>
<p>Bulunduğunuz ülkeye göre aşağıdaki haklara sahipsiniz:</p>
<ul>
    <li>Kişisel verilerinizin işlenip işlenmediğini öğrenme ve bir kopyasını alma,</li>
    <li>Eksik veya yanlış işlenmişse düzeltilmesini isteme,</li>
    <li>Silinmesini veya yok edilmesini isteme (yasal saklama yükümlülüğümüz bulunmayan veriler bakımından),</li>
    <li>İşlemenin sınırlandırılmasını isteme,</li>
    <li>Verilerinizi yapılandırılmış ve makinece okunabilir biçimde alma (veri taşınabilirliği),</li>
    <li>Meşru menfaate dayalı işlemeye itiraz etme,</li>
    <li>Açık rızaya dayanan işlemede rızanızı geri alma,</li>
    <li>Otomatik sistemlerle analiz edilmesi sonucu aleyhinize bir sonuç doğmasına itiraz etme,</li>
    <li>Kanuna aykırı işleme nedeniyle zarara uğramışsanız tazminat talep etme.</li>
</ul>
<p>Kaliforniya'da yerleşikseniz, CCPA/CPRA kapsamında topladığımız veri kategorilerini öğrenme, silinmesini isteme ve satışa/paylaşıma itiraz etme haklarınız vardır. <strong>Kişisel verilerinizi satmıyor ve reklam amacıyla paylaşmıyoruz.</strong> Bu haklarınızı kullandığınız için size ayrımcılık uygulanmaz.</p>

<h3>Başvuru</h3>
<p>Taleplerinizi <a href="mailto:{{ $company['kvkk_email'] }}">{{ $company['kvkk_email'] }}</a> adresine iletebilirsiniz. Başvurunuzu <strong>en geç 30 gün</strong> içinde sonuçlandırırız. Talebiniz karmaşıksa süre bir kez uzatılabilir ve bu durumda size bilgi verilir. Başvurular ücretsizdir; açıkça dayanaksız veya aşırı tekrar eden talepler için makul bir ücret talep edebiliriz.</p>
<p>Cevabımızı yeterli bulmazsanız <strong>Kişisel Verileri Koruma Kurumu</strong>'na (kvkk.gov.tr) şikâyette bulunabilirsiniz. AB'de yerleşikseniz, kendi ülkenizin veri koruma otoritesine başvurma hakkınız saklıdır.</p>

<h2>8. Çerezler</h2>
<p>Sitede kullanılan çerezler ve bunları nasıl yöneteceğiniz <a href="{{ route('legal.show', 'cookies') }}">Çerez Politikası</a>'nda açıklanmıştır.</p>

<h2>9. Çocukların verileri</h2>
<p>Hizmetlerimiz 18 yaşından küçüklere yönelik değildir ve bilerek çocuklardan kişisel veri toplamayız. Böyle bir veriyi öğrendiğimizde gecikmeden sileriz.</p>

<h2>10. Değişiklikler</h2>
<p>Bu politikayı güncelleyebiliriz. Esaslı değişiklikleri yürürlükten en az 30 gün önce e-posta ile bildiririz. Sayfanın altındaki yürürlük tarihi, hangi metnin geçerli olduğunu gösterir.</p>
