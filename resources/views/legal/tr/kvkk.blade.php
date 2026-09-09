<p>Bu metin, 6698 sayılı Kişisel Verilerin Korunması Kanunu'nun ("Kanun") 10. maddesi ile Aydınlatma Yükümlülüğünün Yerine Getirilmesinde Uyulacak Usul ve Esaslar Hakkında Tebliğ uyarınca, veri sorumlusu sıfatıyla hazırlanmıştır.</p>

<h2>1. Veri sorumlusunun kimliği</h2>
@include('legal.partials.seller')

<h2>2. İşlenen kişisel veriler ve işlenme amaçları</h2>
<table>
    <thead><tr><th>Veri kategorisi</th><th>İşlenen veriler</th><th>İşlenme amacı</th></tr></thead>
    <tbody>
        <tr>
            <td>Kimlik</td>
            <td>Ad, soyad, unvan, T.C. kimlik numarası veya vergi numarası</td>
            <td>Sözleşmenin kurulması, fatura düzenlenmesi, yasal yükümlülüklerin yerine getirilmesi</td>
        </tr>
        <tr>
            <td>İletişim</td>
            <td>E-posta adresi, telefon numarası, adres</td>
            <td>Hizmete ilişkin bildirimlerin iletilmesi, destek verilmesi, fatura gönderimi</td>
        </tr>
        <tr>
            <td>Müşteri işlem</td>
            <td>Sipariş ve hizmet kayıtları, alan adı kayıtları, destek talepleri</td>
            <td>Hizmetin sunulması ve yönetilmesi, talep ve şikâyetlerin takibi</td>
        </tr>
        <tr>
            <td>Finans</td>
            <td>Fatura bilgileri, ödeme tutar ve tarihleri, havale referansı, kart türü ve son dört hane, IBAN (yalnızca iade hâlinde)</td>
            <td>Tahsilat, muhasebe ve vergi yükümlülükleri, iade işlemleri</td>
        </tr>
        <tr>
            <td>İşlem güvenliği</td>
            <td>IP adresi, giriş kayıtları, oturum bilgileri, sunucu ve güvenlik logları</td>
            <td>Bilgi güvenliğinin sağlanması, yetkisiz erişimin önlenmesi, 5651 sayılı Kanun kapsamındaki yükümlülükler</td>
        </tr>
        <tr>
            <td>Pazarlama</td>
            <td>İzin kaydı, gönderim ve açılma bilgisi</td>
            <td>Açık rızanız bulunması hâlinde kampanya ve duyuruların iletilmesi</td>
        </tr>
    </tbody>
</table>
<p><strong>Özel nitelikli kişisel verilerinizi talep etmiyor ve işlemiyoruz.</strong> Kredi kartı numaranız, son kullanma tarihiniz ve güvenlik kodunuz sistemlerimize hiç girmemekte ve tarafımızca saklanmamaktadır; kart ödemeleri PCI DSS uyumlu bir ödeme kuruluşu üzerinden alınmaktadır.</p>

<h2>3. Kişisel verilerin toplanma yöntemi ve hukuki sebebi</h2>
<p>Kişisel verileriniz; internet sitemiz, müşteri paneli, e-posta ve destek kanalları üzerinden, kısmen otomatik ve otomatik olan yollarla toplanmaktadır.</p>
<p>Toplama işlemi Kanun'un 5. maddesinde sayılan aşağıdaki hukuki sebeplere dayanmaktadır:</p>
<ul>
    <li><strong>Sözleşmenin kurulması veya ifasıyla doğrudan doğruya ilgili olması</strong> (m.5/2-c) — hesap açılması, hizmetin sunulması, faturalandırma,</li>
    <li><strong>Veri sorumlusunun hukuki yükümlülüğünü yerine getirmesi</strong> (m.5/2-ç) — vergi, ticaret ve 5651 sayılı Kanun kapsamındaki saklama yükümlülükleri,</li>
    <li><strong>Bir hakkın tesisi, kullanılması veya korunması</strong> (m.5/2-e) — olası uyuşmazlıklarda delil teşkil etmesi,</li>
    <li><strong>Meşru menfaat</strong> (m.5/2-f) — sistem ve ağ güvenliğinin sağlanması, kötüye kullanımın önlenmesi,</li>
    <li><strong>Açık rıza</strong> (m.5/1) — yalnızca ticari elektronik ileti gönderimi bakımından.</li>
</ul>

<h2>4. Kişisel verilerin aktarılması</h2>
<p>Kişisel verileriniz, Kanun'un 8. ve 9. maddeleri çerçevesinde ve yalnızca aşağıdaki amaçlarla sınırlı olmak üzere aktarılmaktadır:</p>
<table>
    <thead><tr><th>Aktarılan taraf</th><th>Aktarım amacı</th><th>Konum</th></tr></thead>
    <tbody>
        <tr><td>Sunucu ve veri merkezi hizmet sağlayıcısı</td><td>Barındırma altyapısının sağlanması</td><td>Almanya</td></tr>
        <tr><td>Alan adı tescil kuruluşu</td><td>Alan adı kaydı ve ICANN yükümlülükleri</td><td>Türkiye</td></tr>
        <tr><td>Bankalar ve ödeme kuruluşları</td><td>Tahsilat, kart ödemelerinin alınması ve iade işlemleri</td><td>Türkiye</td></tr>
        <tr><td>Mali müşavir ve denetim</td><td>Muhasebe ve vergi yükümlülükleri</td><td>Türkiye</td></tr>
        <tr><td>Yetkili kamu kurum ve kuruluşları</td><td>Hukuken bağlayıcı talepler</td><td>Türkiye</td></tr>
    </tbody>
</table>
<p>Yurt dışına aktarım, Kanun'un 9. maddesi çerçevesinde ve uygun güvencelerin sağlanması koşuluyla yapılmaktadır. <strong>Kişisel verileriniz pazarlama amacıyla üçüncü kişilere satılmamakta ve devredilmemektedir.</strong></p>

<h2>5. Saklama süreleri</h2>
<table>
    <thead><tr><th>Veri</th><th>Saklama süresi</th></tr></thead>
    <tbody>
        <tr><td>Fatura ve muhasebe kayıtları</td><td>10 yıl (TTK m.82, VUK m.253)</td></tr>
        <tr><td>Sözleşme ve müşteri kayıtları</td><td>Hizmetin sona ermesinden itibaren 10 yıl</td></tr>
        <tr><td>Trafik ve erişim kayıtları</td><td>2 yıl (5651 sayılı Kanun)</td></tr>
        <tr><td>Sunucu ve güvenlik logları</td><td>90 gün</td></tr>
        <tr><td>Destek talepleri</td><td>Kapanmasından itibaren 3 yıl</td></tr>
        <tr><td>Ticari elektronik ileti izin kayıtları</td><td>İznin geri alınmasından itibaren 3 yıl (Ticari İletişim Yönetmeliği)</td></tr>
    </tbody>
</table>
<p>Sürelerin dolmasının ardından kişisel verileriniz, Kişisel Verilerin Silinmesi, Yok Edilmesi veya Anonim Hâle Getirilmesi Hakkında Yönetmelik uyarınca silinir, yok edilir veya anonim hâle getirilir.</p>

<h2>6. Kanun'un 11. maddesi kapsamındaki haklarınız</h2>
<p>Veri sorumlusuna başvurarak;</p>
<ul>
    <li>Kişisel verinizin işlenip işlenmediğini öğrenme,</li>
    <li>İşlenmişse buna ilişkin bilgi talep etme,</li>
    <li>İşlenme amacını ve amacına uygun kullanılıp kullanılmadığını öğrenme,</li>
    <li>Yurt içinde veya yurt dışında aktarıldığı üçüncü kişileri bilme,</li>
    <li>Eksik veya yanlış işlenmişse düzeltilmesini isteme,</li>
    <li>Kanun'un 7. maddesindeki şartlar çerçevesinde silinmesini veya yok edilmesini isteme,</li>
    <li>Düzeltme, silme ve yok etme işlemlerinin aktarıldığı üçüncü kişilere bildirilmesini isteme,</li>
    <li>Münhasıran otomatik sistemlerle analiz edilmesi suretiyle aleyhinize bir sonucun ortaya çıkmasına itiraz etme,</li>
    <li>Kanuna aykırı işlenmesi sebebiyle zarara uğramanız hâlinde zararın giderilmesini talep etme</li>
</ul>
<p>haklarına sahipsiniz.</p>

<h2>7. Başvuru usulü</h2>
<p>Haklarınıza ilişkin taleplerinizi, Veri Sorumlusuna Başvuru Usul ve Esasları Hakkında Tebliğ uyarınca aşağıdaki yollarla iletebilirsiniz:</p>
<ul>
    <li>Yazılı olarak, kimliğinizi tevsik edici belgelerle birlikte yukarıdaki adrese ıslak imzalı dilekçe ile,</li>
    <li>Kayıtlı elektronik posta (KEP) adresi, güvenli elektronik imza veya mobil imza ile,</li>
    <li>Daha önce bize bildirdiğiniz ve sistemimizde kayıtlı bulunan elektronik posta adresinizden <a href="mailto:{{ $company['kvkk_email'] }}">{{ $company['kvkk_email'] }}</a> adresine göndereceğiniz e-posta ile.</li>
</ul>
<p>Başvurunuzda adınız, soyadınız, imzanız (yazılı başvuruda), T.C. kimlik numaranız veya yabancılar için pasaport numaranız, tebligata esas adresiniz, varsa e-posta ve telefonunuz ile talep konunuz açıkça yer almalıdır.</p>
<p>Talebiniz, niteliğine göre en kısa sürede ve <strong>en geç otuz (30) gün</strong> içinde ücretsiz olarak sonuçlandırılır. İşlemin ayrıca bir maliyet gerektirmesi hâlinde Kurul tarafından belirlenen tarifedeki ücret alınabilir.</p>
<p>Başvurunuzun reddedilmesi, verilen cevabı yetersiz bulmanız veya süresinde cevap verilmemesi hâlinde; cevabı öğrendiğiniz tarihten itibaren <strong>otuz gün</strong> ve her hâlde başvuru tarihinden itibaren <strong>altmış gün</strong> içinde <strong>Kişisel Verileri Koruma Kurulu</strong>'na şikâyette bulunabilirsiniz.</p>
