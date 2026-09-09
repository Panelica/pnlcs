<p>Bu Kullanım Koşulları ve Hizmet Sözleşmesi ("Sözleşme"), aşağıda bilgileri yer alan {{ \App\Http\Controllers\LegalController::partyName($company, $tr) }} ("Şirket", "biz") ile hizmetlerimizden yararlanan gerçek veya tüzel kişi ("Müşteri", "siz") arasında kurulur. Hesap oluşturmanız, sipariş vermeniz veya hizmetlerimizi kullanmanız bu Sözleşmeyi kabul ettiğiniz anlamına gelir.</p>

@include('legal.partials.seller')

<h2>1. Tanımlar</h2>
<p><strong>Hizmet</strong>, tarafımızca sunulan barındırma (hosting), alan adı kaydı ve transferi, e-posta, SSL sertifikası, uygulama kurulumu ve bunlara bağlı destek hizmetlerinin tamamını ifade eder. <strong>Hesap</strong>, müşteri panelinde adınıza açılan kaydı; <strong>İçerik</strong>, hizmet üzerinde barındırdığınız her türlü dosya, veritabanı, e-posta ve veriyi ifade eder.</p>

<h2>2. Sözleşmenin kurulması ve süresi</h2>
<p>Sözleşme, siparişinizin tarafımızca onaylanması ve hizmetin açılmasıyla kurulur. Aksi belirtilmedikçe hizmetler, seçtiğiniz faturalama dönemi (aylık, üç aylık, altı aylık veya yıllık) boyunca geçerlidir ve dönem sonunda, taraflardan biri iptal etmediği sürece aynı süreyle kendiliğinden yenilenir.</p>
<p>Yenilemeyi durdurmak için, dönem bitiminden önce müşteri panelinizden iptal talebi oluşturmanız yeterlidir. İptal talebi oluşturulduktan sonra yeni fatura kesilmez.</p>

<h2>3. Ücretler, faturalama ve para birimi</h2>
<p>Hizmet bedelleri sitemizde <strong>ABD doları (USD)</strong> olarak gösterilir. Fatura, siparişin verildiği gün Türkiye Cumhuriyet Merkez Bankası tarafından yayımlanan döviz satış kuru esas alınarak <strong>Türk lirası (TRY)</strong> olarak düzenlenir. Uygulanan kur, kurun tarihi ve bülten numarası faturanızın üzerinde açıkça yazar; dilediğiniz zaman Merkez Bankası'nın kendi yayınından doğrulayabilirsiniz.</p>
<p>Türkiye'de yerleşik müşteriler için faturaya <strong>%20 KDV</strong> eklenir. Yurt dışında yerleşik müşteriler için vergi, ilgili ülke mevzuatı ve Türkiye'nin taraf olduğu düzenlemeler çerçevesinde uygulanır.</p>
<p>Faturalar, düzenlendikleri tarihte ödenmek üzere gönderilir. Vadesi geçen faturalar için hizmet, ödeme yapılana kadar askıya alınabilir. Askıya alma öncesinde en az bir hatırlatma e-postası gönderilir.</p>

<h2>4. Ödeme yöntemleri</h2>
<p>Kabul ettiğimiz ödeme yöntemleri <strong>havale/EFT</strong> ile <strong>kredi ve banka kartıdır</strong>. Siparişiniz için o an kullanılabilir yöntemler ödeme adımında listelenir.</p>
<ul>
    <li><strong>Havale/EFT:</strong> Banka bilgilerimiz siparişinizin ardından müşteri panelinizde ve fatura e-postanızda yer alır. Ödemeniz hesabımıza geçtiğinde hizmetiniz açılır.</li>
    <li><strong>Kredi / banka kartı:</strong> Kart ödemeleri, ödeme kuruluşu lisansına sahip ve PCI DSS uyumlu bir kuruluş üzerinden alınır. <strong>Kart bilgileriniz bizim sistemlerimize hiç girmez ve tarafımızca saklanmaz.</strong> Ödeme onaylandığında hizmetiniz otomatik olarak açılır.</li>
</ul>
<p>Kartla yapılan ödemelerde, hizmetin otomatik yenilenmesi için kartınızdan tekrarlayan tahsilat yapılabilmesine, siparişi onaylarken izin vermiş olursunuz. Bu izni dilediğiniz zaman müşteri panelinizden geri alabilirsiniz; izin geri alındığında hizmet, ödemesi yapılmış dönemin sonunda kapanır.</p>

<h2>4.a. Para iade güvencesi</h2>
<p>Barındırma paketlerinde, yıllık ve daha uzun süreli satın almalarda <strong>30 gün</strong>, aylık satın almalarda <strong>48 saat</strong> içinde gerekçe göstermeden iptal edip ödediğiniz tutarın tamamını geri alabilirsiniz. Güvence ilk satın almaya ilişkindir; alan adı, SSL ve yenileme ödemelerini kapsamaz. Koşulların tamamı <a href="{{ route('legal.show', 'refund') }}">İptal, Cayma ve İade Politikası</a>'ndadır.</p>

<h2>5. Hizmetin askıya alınması ve sona ermesi</h2>
<p>Aşağıdaki hâllerde hizmeti askıya alabilir veya sona erdirebiliriz:</p>
<ul>
    <li>Vadesi geçmiş ve hatırlatmaya rağmen ödenmemiş bir faturanın bulunması,</li>
    <li><a href="{{ route('legal.show', 'aup') }}">Kabul Edilebilir Kullanım Politikası</a>'nın ihlali,</li>
    <li>Hizmetin, sunucunun veya diğer müşterilerin güvenliğini somut biçimde tehdit eden bir durum,</li>
    <li>Yetkili bir mahkeme, savcılık veya idari makamdan gelen bağlayıcı bir karar.</li>
</ul>
<p>Güvenliği doğrudan ve acil biçimde tehdit eden hâller dışında, askıya almadan önce sizi bilgilendirir ve makul bir düzeltme süresi tanırız. Askıya alınan bir hesabın verileri, askı tarihinden itibaren <strong>30 gün</strong> boyunca saklanır; bu sürenin sonunda kalıcı olarak silinebilir.</p>

<h2>6. Yedekleme ve veri sorumluluğu</h2>
<p>Apollo, Ares ve Zeus paketlerinde otomatik yedekleme açıktır ve yedeklerinize Panelica panelinden ulaşabilirsiniz. Ücretsiz kampanya paketi Hermes yedekleme içermez.</p>
<div class="legal-note">
    <p>Sunduğumuz yedekleme bir kolaylıktır, arşiv garantisi değildir. <strong>İçeriğinizin kendi bağımsız kopyasını almak sizin sorumluluğunuzdadır.</strong> Yedeklerin eksik, bozuk veya geri yüklenemez olması hâlinde sorumluluğumuz, ilgili aya ait hizmet bedeliyle sınırlıdır.</p>
</div>

<h2>7. Yükümlülükleriniz</h2>
<ul>
    <li>Hesap bilgilerinizi doğru ve güncel tutmak. Fatura ve önemli bildirimler, panelinizdeki e-posta adresine gönderilir; ulaşmayan bildirimlerden bu adresi güncel tutmamış olmanız hâlinde sorumlu değiliz.</li>
    <li>Hesabınızın şifresini ve varsa iki adımlı doğrulama bilgilerini gizli tutmak. Hesabınız üzerinden yapılan işlemlerden siz sorumlusunuz.</li>
    <li>Barındırdığınız İçeriğin hukuka uygunluğunu sağlamak, gerekli izin, lisans ve bildirimleri temin etmek.</li>
    <li>Uygulamalarınızı, temalarınızı ve eklentilerinizi güncel tutmak. Güncellenmemiş yazılım kaynaklı güvenlik ihlalleri kapsam dışındadır.</li>
</ul>

<h2>8. Fikri mülkiyet</h2>
<p>Barındırdığınız İçerik size aittir; bu Sözleşme size ait hiçbir hak devretmez. Hizmeti sunabilmek için gereken ölçüde (depolama, yedekleme, iletim, gösterim) İçeriğinizi işlememize izin vermiş sayılırsınız. Bu izin, hizmet sona erdiğinde kendiliğinden ortadan kalkar.</p>
<p>Sitemiz, panel arayüzü, dokümantasyon ve markalarımız üzerindeki haklar bize veya lisans verenlerimize aittir.</p>

<h2>9. Sorumluluğun sınırı</h2>
<p>Hizmetler, mevzuatın izin verdiği azami ölçüde "olduğu gibi" sunulur. Kâr kaybı, iş kaybı, itibar kaybı, veri kaybı ve dolaylı zararlardan sorumlu değiliz.</p>
<p>Her hâlükârda toplam sorumluluğumuz, talebin doğduğu olaydan önceki <strong>on iki (12) ay</strong> içinde ilgili hizmet için bize ödediğiniz tutarı aşamaz.</p>
<p>Bu bölüm; kastımızdan veya ağır ihmalimizden doğan zararları, ölüm ve bedensel zararları ve tüketici mevzuatının sınırlandırılmasına izin vermediği hakları kapsamaz. Tüketici sıfatını taşıyorsanız, bu Sözleşme 6502 sayılı Tüketicinin Korunması Hakkında Kanun'dan doğan haklarınızı hiçbir şekilde sınırlandırmaz.</p>

<h2>10. Çalışma süresi</h2>
<p>Çalışma süresi taahhüdümüz ve taahhüdün tutmaması hâlinde uygulanacak alacaklandırma, <a href="{{ route('legal.show', 'sla') }}">Hizmet Seviyesi Taahhüdü</a>'nde düzenlenmiştir.</p>

<h2>11. Alan adları</h2>
<p>Alan adı kaydı ve yenilemesi, ICANN kuralları ile ilgili tescil kuruluşunun (registry) politikalarına tabidir ve ayrı bir sözleşme ile düzenlenir: <a href="{{ route('legal.show', 'domain') }}">Alan Adı Kayıt Sözleşmesi</a>.</p>

<h2>12. Kişisel veriler</h2>
<p>Kişisel verilerinizin işlenmesine ilişkin bilgiler <a href="{{ route('legal.show', 'privacy') }}">Gizlilik Politikası</a>, <a href="{{ route('legal.show', 'kvkk') }}">KVKK Aydınlatma Metni</a> ve müşterilerimiz adına işlenen veriler bakımından <a href="{{ route('legal.show', 'dpa') }}">Veri İşleme Sözleşmesi</a>'nde yer alır.</p>

<h2>13. Değişiklikler</h2>
<p>Bu Sözleşmede değişiklik yapabiliriz. Aleyhinize sonuç doğuran esaslı değişiklikler, yürürlüğe girmeden en az <strong>30 gün</strong> önce e-posta ile bildirilir. Bildirimin ardından hizmeti kullanmaya devam etmeniz kabul anlamına gelir; kabul etmiyorsanız, değişiklik yürürlüğe girmeden hizmeti ücretsiz olarak sonlandırabilir ve kullanılmamış döneme ait bedelin iadesini talep edebilirsiniz.</p>
<p>Bir siparişe, siparişin verildiği tarihte yürürlükte olan metin uygulanır.</p>

<h2>14. Devir</h2>
<p>Bu Sözleşmeden doğan hak ve yükümlülüklerinizi yazılı onayımız olmaksızın devredemezsiniz. Şirketin birleşme, bölünme veya işletme devri hâllerinde Sözleşmeyi devretme hakkı saklıdır.</p>

<h2>15. Uygulanacak hukuk ve yetki</h2>
<p>Bu Sözleşmeye Türkiye Cumhuriyeti hukuku uygulanır. Uyuşmazlıklarda, Şirket merkezinin bulunduğu yer olan <strong>Büyükçekmece Mahkemeleri ve İcra Daireleri</strong> yetkilidir.</p>
<p>Tüketici sıfatını taşıyorsanız, bu yetki şartı sizin için bağlayıcı değildir: parasal sınırlar dâhilinde kendi yerleşim yerinizdeki <strong>Tüketici Hakem Heyeti</strong>'ne veya <strong>Tüketici Mahkemesi</strong>'ne başvurabilirsiniz. Avrupa Birliği'nde yerleşikseniz, kendi ülkenizin tüketici mevzuatının size tanıdığı zorunlu haklar saklıdır.</p>

<h2>16. Bölünebilirlik ve bütünlük</h2>
<p>Bu Sözleşmenin bir hükmünün geçersiz sayılması, diğer hükümlerin geçerliliğini etkilemez. Bu Sözleşme, atıf yaptığı politikalarla birlikte taraflar arasındaki anlaşmanın tamamını oluşturur.</p>
