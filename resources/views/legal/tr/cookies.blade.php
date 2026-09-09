<p>Bu politika, {{ $company['website'] }} adresinde kullanılan çerezleri ve benzeri teknolojileri açıklar. Kısa cevabı baştan verelim: <strong>sitemizde reklam çerezi, izleme pikseli veya üçüncü taraf analiz aracı bulunmuyor.</strong> Kullandığımız çerezlerin tamamı sitenin çalışması için gereklidir ya da sizin bir tercihinizi hatırlar.</p>

<h2>1. Çerez nedir?</h2>
<p>Çerez, ziyaret ettiğiniz sitenin tarayıcınıza kaydettiği küçük bir metin dosyasıdır. Bir sonraki ziyaretinizde site bu dosyayı okuyarak sizi tanır — örneğin oturumunuzun açık olduğunu ya da hangi dili seçtiğinizi.</p>

<h2>2. Kullandığımız çerezler</h2>
<table>
    <thead><tr><th>Çerez</th><th>Amacı</th><th>Süresi</th><th>Türü</th></tr></thead>
    <tbody>
        <tr>
            <td><code>{{ config('session.cookie') }}</code></td>
            <td>Oturumunuzu açık tutar. Bu çerez olmadan müşteri paneline giriş yapılamaz.</td>
            <td>2 saat</td>
            <td>Zorunlu</td>
        </tr>
        <tr>
            <td><code>XSRF-TOKEN</code></td>
            <td>Formlarınızı siteler arası istek sahteciliğine (CSRF) karşı korur. Güvenlik amaçlıdır.</td>
            <td>2 saat</td>
            <td>Zorunlu</td>
        </tr>
        <tr>
            <td><code>pnlcs_locale</code></td>
            <td>Seçtiğiniz dili hatırlar, böylece her ziyarette yeniden seçmeniz gerekmez.</td>
            <td>30 gün</td>
            <td>Tercih</td>
        </tr>
        <tr>
            <td><code>pnlcs_theme</code></td>
            <td>Açık veya koyu tema tercihinizi hatırlar.</td>
            <td>Tarayıcı kapanana kadar</td>
            <td>Tercih</td>
        </tr>
        <tr>
            <td><code>pnlcs_aff</code></td>
            <td>Siteye bir ortaklık (referans) bağlantısıyla geldiyseniz, hangi ortağın yönlendirdiğini kaydeder; ortağa hak ettiği komisyonun ödenmesi için gereklidir. Yalnızca referans bağlantısıyla gelindiğinde oluşturulur.</td>
            <td>90 gün</td>
            <td>Zorunlu (işlevsel)</td>
        </tr>
    </tbody>
</table>

<h2>3. Kullanmadıklarımız</h2>
<p>Açıkça belirtmek isteriz — sitede aşağıdakilerin <strong>hiçbiri</strong> yoktur:</p>
<ul>
    <li>Google Analytics veya başka bir üçüncü taraf analiz aracı,</li>
    <li>Facebook, Google Ads, TikTok veya benzeri reklam pikselleri,</li>
    <li>Yeniden hedefleme (retargeting) çerezleri,</li>
    <li>Isı haritası, oturum kaydı veya davranış izleme araçları,</li>
    <li>Reklam ağlarıyla veri paylaşımı.</li>
</ul>
<p>Kullandığımız çerezlerin tamamı ya hizmetin çalışması için zorunlu ya da sizin tercihinizi hatırlayan çerezlerdir; bu nedenle GDPR ve ePrivacy düzenlemeleri uyarınca <strong>önceden onay gerektirmezler</strong> ve size çerez izin penceresi göstermeyiz. İleride analiz veya reklam çerezi kullanmaya başlarsak, bunları yalnızca açık onayınızla çalıştırırız ve bu sayfayı güncelleriz.</p>

<h2>4. Üçüncü taraf kaynaklar</h2>
<p>Sitemiz çerez yerleştirmeyen, ancak sayfayı görüntülerken tarayıcınızın bağlanmasına neden olan iki dış kaynak kullanır:</p>
<ul>
    <li><strong>Google Fonts</strong> (<code>fonts.googleapis.com</code>, <code>fonts.gstatic.com</code>) — sayfa yazı tipleri.</li>
    <li><strong>jsDelivr CDN</strong> (<code>cdn.jsdelivr.net</code>) — arayüz simgeleri ve JavaScript kütüphaneleri.</li>
</ul>
<p>Bu bağlantılarda çerez oluşmaz; ancak teknik olarak IP adresiniz ilgili sunuculara ulaşır. Bu kaynakları kendi sunucumuza taşıma çalışmamız sürmektedir.</p>

<h2>5. Çerezleri nasıl yönetirsiniz?</h2>
<p>Tarayıcınızın ayarlarından çerezleri silebilir veya engelleyebilirsiniz:</p>
<ul>
    <li><strong>Chrome:</strong> Ayarlar &rsaquo; Gizlilik ve güvenlik &rsaquo; Üçüncü taraf çerezler</li>
    <li><strong>Firefox:</strong> Ayarlar &rsaquo; Gizlilik ve Güvenlik &rsaquo; Çerezler ve Site Verileri</li>
    <li><strong>Safari:</strong> Ayarlar &rsaquo; Gizlilik</li>
    <li><strong>Edge:</strong> Ayarlar &rsaquo; Çerezler ve site izinleri</li>
</ul>
<div class="legal-note">
    <p>Zorunlu çerezleri engellerseniz müşteri paneline giriş yapamaz, sipariş veremez ve destek talebi oluşturamazsınız. Bu çerezler bir tercih değil, sitenin çalışma koşuludur.</p>
</div>

<h2>6. İlgili belgeler</h2>
<p>Kişisel verilerinizin nasıl işlendiğine dair bilgi için <a href="{{ route('legal.show', 'privacy') }}">Gizlilik Politikası</a>'na, 6698 sayılı Kanun kapsamındaki aydınlatma metnimiz için <a href="{{ route('legal.show', 'kvkk') }}">KVKK Aydınlatma Metni</a>'ne bakabilirsiniz.</p>
