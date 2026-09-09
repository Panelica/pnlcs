<p>Bu belge, barındırma hizmetlerimiz için verdiğimiz çalışma süresi taahhüdünü ve taahhüdün tutmaması hâlinde ne yapacağımızı tanımlar. <a href="{{ route('legal.show', 'terms') }}">Kullanım Koşulları</a>'nın ayrılmaz parçasıdır.</p>

<h2>1. Taahhüt</h2>
<p>Ücretli barındırma paketlerinde, takvim ayı bazında <strong>%99,9 ağ ve sunucu erişilebilirliği</strong> taahhüt ediyoruz. Bu, ayda yaklaşık <strong>43 dakika</strong>lık plansız kesintiye karşılık gelir.</p>
<p>Taahhüt, sunucunun ve ağın erişilebilirliğini kapsar. Sizin uygulamanızın kendi hatası nedeniyle çalışmaması bu kapsamda değildir.</p>

<h2>2. Kapsam dışı süreler</h2>
<p>Aşağıdaki süreler kesinti hesabına dâhil edilmez:</p>
<ul>
    <li><strong>Planlı bakım.</strong> En az 72 saat önceden e-posta ile duyurulur, mümkün olduğunca Türkiye saatiyle 02:00–06:00 arasında yapılır ve ayda toplam 4 saati aşmaz.</li>
    <li><strong>Acil güvenlik bakımı.</strong> Aktif olarak istismar edilen bir zafiyetin kapatılması. Duyuru sonradan yapılır. Beklemek, kesintiden daha büyük zarar doğurur.</li>
    <li>Sizin uygulamanız, temanız, eklentiniz veya yapılandırmanızdan kaynaklanan sorunlar.</li>
    <li>Paket kaynak sınırlarınızın aşılması nedeniyle oluşan yavaşlama veya hata.</li>
    <li><a href="{{ route('legal.show', 'aup') }}">Kabul Edilebilir Kullanım Politikası</a> ihlali veya ödenmemiş fatura nedeniyle yapılan askıya almalar.</li>
    <li>Alan adınızın DNS ayarları, süresinin dolması veya tescil kuruluşundaki durumundan kaynaklanan erişim sorunları.</li>
    <li>Sizin ile veri merkezimiz arasındaki genel internet altyapısında yaşanan, bizim kontrolümüz dışındaki sorunlar.</li>
    <li>Mücbir sebepler: doğal afet, savaş, genel grev, ülke çapında altyapı veya elektrik kesintisi, yetkili makam kararı.</li>
</ul>

<h2>3. Alacaklandırma</h2>
<p>Taahhüdün altında kalırsak, ilgili aya ait hizmet bedeli üzerinden hesabınıza kredi tanımlarız:</p>
<table>
    <thead><tr><th>Aylık erişilebilirlik</th><th>Yaklaşık kesinti</th><th>Kredi</th></tr></thead>
    <tbody>
        <tr><td>%99,90 – %99,00</td><td>43 dk – 7,2 saat</td><td>Aylık bedelin %10'u</td></tr>
        <tr><td>%99,00 – %98,00</td><td>7,2 – 14,4 saat</td><td>Aylık bedelin %25'i</td></tr>
        <tr><td>%98,00 – %95,00</td><td>14,4 – 36 saat</td><td>Aylık bedelin %50'si</td></tr>
        <tr><td>%95,00'in altı</td><td>36 saatten fazla</td><td>Aylık bedelin %100'ü</td></tr>
    </tbody>
</table>
<p>Yıllık ödeme yapıyorsanız aylık bedel, yıllık bedelin on ikide biri olarak hesaplanır.</p>

<h3>Nasıl talep edilir?</h3>
<p>Alacaklandırma otomatik değildir; talep etmeniz gerekir. Kesintinin yaşandığı ayın bitiminden itibaren <strong>30 gün</strong> içinde müşteri panelinizden destek talebi açın ve kesinti tarih ile saatlerini belirtin. Talebinizi kendi izleme kayıtlarımızla karşılaştırır ve <strong>10 iş günü</strong> içinde sonuçlandırırız.</p>
<p>Kredi, hesabınıza bakiye olarak tanımlanır ve sonraki faturalarınızda kullanılır. Nakit ödeme yapılmaz. Bir takvim ayında tanımlanacak toplam kredi, o ayın hizmet bedelini aşamaz.</p>

<h2>4. Ücretsiz paketler</h2>
<p>Ücretsiz kampanya paketi Hermes, bu SLA kapsamında değildir. Ücretsiz pakette hizmetin sürekliliği için elimizden gelen çabayı gösteririz, ancak taahhüt vermeyiz ve alacaklandırma uygulanmaz.</p>

<h2>5. Destek yanıt süreleri</h2>
<p>Destek taleplerini müşteri panelinden ya da <a href="mailto:{{ $company['email'] }}">{{ $company['email'] }}</a> adresine e-posta ile oluşturabilirsiniz. Hedef ilk yanıt sürelerimiz:</p>
<table>
    <thead><tr><th>Öncelik</th><th>Örnek</th><th>Hedef ilk yanıt</th></tr></thead>
    <tbody>
        <tr><td>Kritik</td><td>Site tamamen erişilemez, sunucu kapalı</td><td>1 saat</td></tr>
        <tr><td>Yüksek</td><td>E-posta gönderilemiyor, SSL hatası, ciddi yavaşlama</td><td>4 saat</td></tr>
        <tr><td>Normal</td><td>Yapılandırma soruları, panel kullanımı</td><td>12 saat</td></tr>
        <tr><td>Düşük</td><td>Bilgi talebi, paket yükseltme sorusu</td><td>24 saat</td></tr>
    </tbody>
</table>
<div class="legal-note">
    <p>Bu süreler <strong>hedeftir, taahhüt değildir</strong> ve aşılması alacaklandırma doğurmaz. Sunucularımız 7/24 otomatik olarak izlenir ve kritik alarmlar bize anında ulaşır; ancak 7/24 canlı telefon desteği sunmuyoruz. Neyi verdiğimiz konusunda net olmayı, sonradan tutamayacağımız bir söz vermeye tercih ediyoruz.</p>
</div>

<h2>6. Veri dayanıklılığı</h2>
<p>Apollo, Ares ve Zeus paketlerinde otomatik yedekleme çalışır ve yedeklerinize Panelica panelinden erişebilirsiniz. Ücretsiz Hermes paketi yedekleme içermez.</p>
<p>Yedekleme bir kolaylıktır, arşiv garantisi değildir; içeriğinizin kendi bağımsız kopyasını almanız gerekir. Yedeklerin eksik veya geri yüklenemez olması hâlinde sorumluluğumuz ilgili aya ait hizmet bedeliyle sınırlıdır.</p>

<h2>7. Bu belgenin sınırı</h2>
<p>Bu SLA'da tanımlanan alacaklandırma, çalışma süresi taahhüdünün tutmaması hâlinde başvurabileceğiniz <strong>tek ve münhasır giderim yoludur</strong>. Bu sınır, kastımızdan veya ağır ihmalimizden doğan zararlar ile tüketici mevzuatının tanıdığı zorunlu hakları kapsamaz.</p>
