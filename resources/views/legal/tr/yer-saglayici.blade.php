{{--
     Bu sayfa bir "belge" taraması değil, kayıt bilgisidir. BTK yer sağlayıcılık
     için indirilebilir bir faaliyet belgesi düzenlemiyor; bildirim alıyor ve
     bildirimde bulunanı kamuya açık listesinde yayımlıyor. Ziyaretçinin
     doğrulayabileceği tek kanıt o listedir, bu yüzden metnin merkezinde
     taranmış bir görsel değil doğrulama bağlantısı duruyor.

     Firma kimliği burada yazılı değil; diğer yasal belgelerde olduğu gibi
     Ayarlar'dan geliyor (legal.partials.seller). BTK kaydındaki unvan, adres
     ve telefon ile Ayarlar'daki değerler aynı kalmalıdır — biri değişirse
     diğeri de güncellenmelidir, aksi hâlde sayfa kendi kendini yalanlar.
--}}
<p>4 Mayıs 2007 tarihli ve 5651 sayılı <em>İnternet Ortamında Yapılan Yayınların Düzenlenmesi ve Bu Yayınlar Yoluyla İşlenen Suçlarla Mücadele Edilmesi Hakkında Kanun</em>'un 5. maddesi uyarınca yer sağlayıcılık bildirimimiz Bilgi Teknolojileri ve İletişim Kurumu'na (BTK) yapılmış ve Kurum tarafından alınmıştır.</p>

<h2>1. Yer sağlayıcı kimliği</h2>
@include('legal.partials.seller')

<h2>2. Bildirim kaydı</h2>
<table>
    <tbody>
        <tr><th style="width:32%;">Bildirim yapılan kurum</th><td>Bilgi Teknolojileri ve İletişim Kurumu (BTK)</td></tr>
        <tr><th>Yasal dayanak</th><td>5651 sayılı Kanun, 5. madde — yer sağlayıcının yükümlülükleri</td></tr>
        <tr><th>Bildirim tarihi</th><td>28 Ağustos 2026</td></tr>
        <tr><th>Bildirime konu internet adresi</th><td>{{ $company['website'] }}</td></tr>
        <tr><th>Kayıt durumu</th><td>Bildirim alınmıştır; kayıt BTK'nın kamuya açık yer sağlayıcı listesinde yayımlanmaktadır.</td></tr>
    </tbody>
</table>

<h2>3. Kaydımızı doğrulayın</h2>
<p>Beyanımıza güvenmek zorunda değilsiniz. Kaydımızı doğrudan BTK'nın resmî listesinden sorgulayabilirsiniz:</p>
<p><a href="https://internet.btk.gov.tr/yer-saglayici-listesi" target="_blank" rel="noopener noreferrer"><strong>internet.btk.gov.tr/yer-saglayici-listesi</strong></a></p>
<p>Açılan sayfadaki arama kutusuna unvanımızı veya <strong>{{ parse_url($company['website'], PHP_URL_HOST) ?: $company['website'] }}</strong> yazmanız yeterlidir. Sorgu sonucunda görünen unvan, adres ve telefon bilgileri yukarıdaki tabloyla aynı olmalıdır.</p>

<div class="legal-note">
    <p><strong>Bildirim, yetkilendirme değildir.</strong> BTK'nın kendi ifadesiyle: <em>"5651 sayılı Kanun'un 5 inci maddesi kapsamındaki yer sağlayıcılık bildirimi; yetkilendirme veya faaliyet izni anlamına gelmemekte olup sorgu sonucunda elde edilen veriler bildirimde bulunanların beyanına dayanmaktadır."</em> Bu sayfa, yasal bir bildirim yükümlülüğünün yerine getirildiğini gösterir; bir kalite belgesi, lisans veya Kurum onayı olarak sunulmamaktadır.</p>
</div>

<h2>4. Yer sağlayıcı olarak yükümlülüklerimiz</h2>
<p>5651 sayılı Kanun'un 5. maddesi yer sağlayıcıya üç temel yükümlülük getirir:</p>
<ul>
    <li><strong>İçeriği kontrol yükümlülüğü bulunmaz.</strong> Yer sağlayıcı, barındırdığı içeriği kontrol etmek veya hukuka aykırılık araştırmakla yükümlü değildir. Müşterilerimizin sunucularımızda yayımladığı içeriğin sorumluluğu içerik sağlayıcıya aittir.</li>
    <li><strong>Haberdar edilmesi hâlinde içeriği kaldırır.</strong> Hukuka aykırı bir içerikten usulüne uygun biçimde haberdar edildiğimizde, teknik olarak imkân bulunduğu ölçüde içeriği yayından kaldırırız. Başvuru usulü <a href="{{ route('legal.show', 'abuse') }}">Kötüye Kullanım ve Telif İhlali Bildirimi</a> belgemizde açıklanmıştır.</li>
    <li><strong>Trafik bilgisini saklar.</strong> Yer sağlayıcı trafik bilgisini Kanun ve ilgili yönetmelikte öngörülen süre boyunca saklamak, doğruluğunu ve gizliliğini korumakla yükümlüdür. Bu kayıtlar yalnızca yetkili mercilerin usulüne uygun talebi üzerine paylaşılır; hangi verileri ne kadar süreyle sakladığımız <a href="{{ route('legal.show', 'privacy') }}">Gizlilik Politikası</a> ve <a href="{{ route('legal.show', 'kvkk') }}">KVKK Aydınlatma Metni</a> belgelerimizde ayrıntılı olarak yer alır.</li>
</ul>
<p>Ayrıca barındırdığımız ve tescil ettirdiğimiz alan adlarına ilişkin bilgileri, Kurum'un belirlediği biçimde düzenli aralıklarla BTK sistemine bildirmekteyiz.</p>

<h2>5. İhbar ve başvuru</h2>
<p>Hukuka aykırı içerik ihbarlarınızı <a href="mailto:{{ $company['abuse_email'] }}">{{ $company['abuse_email'] }}</a> adresine iletebilirsiniz. Başvurunuzun hangi bilgileri içermesi gerektiği ve hangi sürede sonuçlandırıldığı <a href="{{ route('legal.show', 'abuse') }}">Kötüye Kullanım ve Telif İhlali Bildirimi</a> belgemizde açıklanmıştır.</p>
