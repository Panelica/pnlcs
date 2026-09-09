<p>Bu sözleşme, 6502 sayılı Tüketicinin Korunması Hakkında Kanun ve Mesafeli Sözleşmeler Yönetmeliği uyarınca düzenlenmiştir. Sipariş verdiğinizde bu sözleşme, siparişinizde yer alan hizmet, tutar ve süre bilgileriyle birlikte kurulmuş sayılır ve bir örneği fatura e-postanızla birlikte tarafınıza iletilir.</p>

<h2>Madde 1 — Taraflar</h2>
<h3>1.1. Satıcı</h3>
@include('legal.partials.seller')

<h3>1.2. Alıcı</h3>
<p>Sipariş sırasında müşteri panelinde beyan ettiğiniz ad, soyad/unvan, adres, e-posta ve telefon bilgileri esas alınır. Bu bilgiler siparişinizin ve faturanızın üzerinde yer alır ve işbu sözleşmenin ayrılmaz parçasıdır.</p>

<h2>Madde 2 — Konu</h2>
<p>İşbu sözleşmenin konusu, Alıcı'nın Satıcı'ya ait <a href="{{ $company['website'] }}">{{ $company['website'] }}</a> internet sitesi üzerinden elektronik ortamda siparişini verdiği, aşağıda nitelikleri ve satış bedeli belirtilen hizmetlerin satışı ve ifası ile ilgili olarak tarafların hak ve yükümlülüklerinin belirlenmesidir.</p>

<h2>Madde 3 — Sözleşme konusu hizmet ve bedel</h2>
<p>Hizmetin türü, süresi, adedi ve tüm vergiler dâhil satış bedeli, sipariş özetinde ve faturada ayrıntılı olarak gösterilmiştir. Sipariş özeti ve fatura, işbu sözleşmenin eki ve ayrılmaz parçasıdır.</p>
<p><strong>Fiyatlandırma ve para birimi:</strong> Hizmet bedelleri internet sitesinde ABD doları (USD) olarak gösterilmektedir. Fatura, siparişin verildiği tarihte Türkiye Cumhuriyet Merkez Bankası tarafından yayımlanan döviz satış kuru esas alınarak Türk lirası olarak düzenlenir. Uygulanan kur, kurun tarihi ve bülten numarası fatura üzerinde açıkça gösterilir.</p>
<p><strong>Vergiler:</strong> Türkiye'de yerleşik Alıcılar için satış bedeline %20 oranında katma değer vergisi eklenir. Fatura üzerinde matrah, KDV tutarı ve toplam tutar ayrı ayrı gösterilir.</p>
<p><strong>Teslimat masrafı:</strong> Hizmet elektronik ortamda ifa edildiğinden kargo veya teslimat masrafı bulunmamaktadır.</p>

<h2>Madde 4 — Ödeme</h2>
<p>Ödeme, Satıcı tarafından sunulan ödeme yöntemleriyle yapılır. Kabul edilen yöntemler <strong>havale/EFT</strong> ile <strong>kredi ve banka kartıdır</strong>; siparişte kullanılabilir yöntemler ödeme adımında Alıcı'ya gösterilir.</p>
<p><strong>Havale/EFT:</strong> Banka hesap bilgileri sipariş sonrasında müşteri panelinde ve fatura e-postasında yer alır.</p>
<p><strong>Kredi / banka kartı:</strong> Kart ödemeleri, 6493 sayılı Kanun kapsamında yetkilendirilmiş ve PCI DSS uyumlu bir ödeme kuruluşu aracılığıyla tahsil edilir. Alıcı'nın kart bilgileri Satıcı tarafından görülmez ve saklanmaz. Alıcı, kartla ödemeyi seçerek, hizmetin otomatik yenilenmesi amacıyla kartından tekrarlayan tahsilat yapılmasına muvafakat etmiş olur; bu muvafakat müşteri panelinden dilediği zaman geri alınabilir.</p>
<p>Ödemenin Satıcı hesabına geçtiğinin teyidiyle birlikte hizmet ifasına başlanır. Ödeme yapılmadığı sürece Satıcı'nın hizmeti ifa yükümlülüğü doğmaz.</p>

<h2>Madde 5 — İfa ve teslim</h2>
<p>Hizmet, ödemenin teyidinden itibaren derhâl ve elektronik ortamda ifa edilir. Hizmete ilişkin erişim bilgileri, Alıcı'nın müşteri panelinde ve panelde kayıtlı e-posta adresine gönderilen bilgilendirme mesajında yer alır.</p>
<p>İfa süresi hiçbir hâlde otuz (30) günü aşamaz. Satıcı'nın hizmeti ifa edememesi hâlinde Alıcı bilgilendirilir ve tahsil edilen tutarın tamamı en geç on dört (14) gün içinde iade edilir.</p>

<h2>Madde 6 — Cayma hakkı</h2>
<p>Alıcı, tüketici sıfatını taşıması hâlinde, sözleşmenin kurulduğu tarihten itibaren <strong>on dört (14) gün</strong> içinde herhangi bir gerekçe göstermeksizin ve cezai şart ödemeksizin sözleşmeden cayma hakkına sahiptir.</p>
<p>Cayma bildirimi, süre içinde <a href="mailto:{{ $company['email'] }}">{{ $company['email'] }}</a> adresine e-posta ile veya müşteri panelinden destek talebi açılarak yapılabilir. Bildirimin süresi içinde gönderilmiş olması yeterlidir.</p>
<p>Cayma hakkının kullanılması hâlinde, Alıcı'nın hizmetten fiilen yararlandığı süreye orantılı bedel mahsup edilerek kalan tutar, cayma bildiriminin Satıcı'ya ulaşmasından itibaren <strong>on dört (14) gün</strong> içinde ödemenin yapıldığı yöntemle iade edilir.</p>

<h3>6.1. Satıcı'nın ayrıca tanıdığı para iade güvencesi</h3>
<p>Aşağıda sayılan istisnalar nedeniyle kanuni cayma hakkının doğmadığı hâllerde dahi Satıcı, barındırma paketlerinde sözleşmeyle bir para iade güvencesi tanır: <strong>yıllık ve daha uzun süreli paketlerde sipariş tarihinden itibaren otuz (30) gün, aylık paketlerde kırk sekiz (48) saat.</strong> Bu süre içinde gerekçe göstermeksizin iptal talep edilebilir ve ödenen tutarın tamamı iade edilir. Güvence yalnızca ilk satın almaya ilişkindir; yenileme ve yükseltme ödemelerini kapsamaz. Ayrıntı: <a href="{{ route('legal.show', 'refund') }}">İptal, Cayma ve İade Politikası</a>.</p>

<h3>6.2. Cayma hakkının kullanılamayacağı hâller</h3>
<p>Mesafeli Sözleşmeler Yönetmeliği'nin 15. maddesinin birinci fıkrasının (b), (ğ) ve (h) bentleri uyarınca aşağıdaki hizmetlerde cayma hakkı kullanılamaz:</p>
<ul>
    <li><strong>Alan adı kaydı, yenilemesi ve transferi.</strong> Alan adı, Alıcı'nın talebi doğrultusunda ve Alıcı'ya özgü olarak tescil kuruluşu nezdinde kaydedilmekte, bedeli kayıt anında geri alınamaz biçimde tescil kuruluşuna aktarılmaktadır.</li>
    <li><strong>SSL sertifikaları.</strong> Alıcı'ya özgü olarak düzenlendiğinden.</li>
    <li>Alıcı'nın onayı ile <strong>ifasına başlanan ve 14 gün dolmadan tamamen ifa edilen</strong> hizmetler.</li>
    <li>Alıcı'nın istekleri veya kişisel ihtiyaçları doğrultusunda özel olarak yapılandırılan hizmetler.</li>
    <li><strong>Yenileme ve yükseltme ödemeleri.</strong> Cayma hakkı yalnızca ilk satın almaya ilişkindir.</li>
    <li><strong>Kampanya kapsamında ücretsiz sağlanan kalemler.</strong> Asıl hizmetten cayılması hâlinde bu kalemlerin liste bedeli iade tutarından mahsup edilir.</li>
</ul>
<p>Alıcı, sipariş vermeden önce bu istisnalar hakkında <a href="{{ route('legal.show', 'pre-information') }}">Ön Bilgilendirme Formu</a> ile bilgilendirilmiş olduğunu kabul eder.</p>

<h2>Madde 7 — Alıcı'nın yükümlülükleri</h2>
<ul>
    <li>Sipariş sırasında verdiği bilgilerin doğru ve eksiksiz olmasını sağlamak,</li>
    <li>Hizmeti <a href="{{ route('legal.show', 'aup') }}">Kabul Edilebilir Kullanım Politikası</a>'na uygun kullanmak,</li>
    <li>Hesap erişim bilgilerini gizli tutmak,</li>
    <li>Panelde kayıtlı e-posta adresini güncel tutmak; fatura, yenileme ve kesinti bildirimleri bu adrese gönderilir.</li>
</ul>

<h2>Madde 8 — Satıcı'nın yükümlülükleri</h2>
<ul>
    <li>Hizmeti sözleşmeye ve ilan edilen niteliklere uygun olarak ifa etmek,</li>
    <li><a href="{{ route('legal.show', 'sla') }}">Hizmet Seviyesi Taahhüdü</a>'nde belirtilen çalışma süresi taahhüdüne uymak,</li>
    <li>Alıcı'nın kişisel verilerini <a href="{{ route('legal.show', 'kvkk') }}">KVKK Aydınlatma Metni</a>'nde açıklanan çerçevede işlemek,</li>
    <li>Destek taleplerini <a href="{{ route('legal.show', 'sla') }}">SLA</a>'da belirtilen hedef süreler içinde yanıtlamak.</li>
</ul>

<h2>Madde 9 — Sözleşmenin süresi ve yenilenmesi</h2>
<p>Sözleşme, siparişte seçilen faturalama dönemi boyunca geçerlidir. Dönem sonunda, taraflardan biri iptal bildiriminde bulunmadığı sürece aynı süreyle kendiliğinden yenilenir. Yenileme öncesinde Alıcı'ya bildirim yapılır. Alıcı, dönem bitiminden önce müşteri panelinden iptal talebi oluşturarak yenilemeyi durdurabilir.</p>

<h2>Madde 10 — Temerrüt ve askıya alma</h2>
<p>Alıcı'nın ödeme yükümlülüğünü yerine getirmemesi hâlinde Satıcı, en az bir hatırlatma bildirimi göndermek kaydıyla hizmeti askıya alabilir. Askıya alınan hesabın verileri otuz (30) gün süreyle saklanır; bu sürenin sonunda silinebilir.</p>

<h2>Madde 11 — Uyuşmazlıkların çözümü</h2>
<p>Alıcı, şikâyet ve itirazlarını, Ticaret Bakanlığı tarafından her yıl aralık ayında belirlenen parasal sınırlar dâhilinde, mal veya hizmeti satın aldığı yahut ikametgâhının bulunduğu yerdeki <strong>Tüketici Hakem Heyeti</strong>'ne veya <strong>Tüketici Mahkemesi</strong>'ne yapabilir.</p>
<p>Alıcı'nın tüketici sıfatını taşımadığı hâllerde, Satıcı merkezinin bulunduğu yer olan <strong>Büyükçekmece Mahkemeleri ve İcra Daireleri</strong> yetkilidir.</p>

<h2>Madde 12 — Yürürlük</h2>
<p>Alıcı, siparişi onaylamakla işbu sözleşmenin tüm koşullarını okuduğunu, anladığını ve kabul ettiğini beyan eder. Sözleşme, siparişin Satıcı tarafından onaylanmasıyla yürürlüğe girer ve bir örneği Alıcı'ya elektronik ortamda gönderilir.</p>
