<p>Bu form, 6502 sayılı Tüketicinin Korunması Hakkında Kanun ve Mesafeli Sözleşmeler Yönetmeliği'nin 5. maddesi uyarınca, sipariş vermeden önce tarafınıza sunulması zorunlu bilgileri içerir. Siparişinizi onaylamadan önce bu formu okumanız gerekmektedir.</p>

<h2>1. Satıcıya ilişkin bilgiler</h2>
@include('legal.partials.seller')
<p>Şikâyet ve talepleriniz için yukarıdaki e-posta adresini veya müşteri panelindeki destek bölümünü kullanabilirsiniz.</p>

<h2>2. Hizmetin temel nitelikleri</h2>
<p>Satın alacağınız hizmetin türü, kapsamı (disk alanı, trafik, e-posta hesabı sayısı, alan adı sayısı gibi sınırlar), süresi ve fiyatı; sipariş öncesinde ürün sayfasında ve sipariş özetinde ayrıntılı olarak gösterilir. Sipariş özeti, işbu formun ayrılmaz parçasıdır.</p>
<p>Hizmetlerimiz elektronik ortamda sunulur; fiziksel bir teslimat söz konusu değildir.</p>

<h2>3. Fiyat ve ödeme</h2>
<ul>
    <li><strong>Gösterim para birimi:</strong> Fiyatlar internet sitesinde ABD doları (USD) olarak gösterilir.</li>
    <li><strong>Faturalama para birimi:</strong> Fatura, siparişin verildiği tarihte Türkiye Cumhuriyet Merkez Bankası tarafından yayımlanan döviz satış kuru esas alınarak Türk lirası (TRY) olarak düzenlenir.</li>
    <li><strong>Kur bilgisi:</strong> Uygulanan kur, kurun tarihi ve TCMB bülten numarası fatura üzerinde açıkça yazılır; Merkez Bankası'nın kendi yayınından doğrulayabilirsiniz.</li>
    <li><strong>Vergiler:</strong> Türkiye'de yerleşik müşteriler için %20 KDV eklenir. Fatura üzerinde matrah, KDV ve toplam ayrı gösterilir.</li>
    <li><strong>Toplam tutar:</strong> Ödeyeceğiniz tüm vergiler dâhil toplam tutar, ödemeyi onaylamadan önce sipariş özetinde gösterilir. Gösterilenin dışında herhangi bir ek ücret alınmaz.</li>
    <li><strong>Ödeme yöntemi:</strong> Havale/EFT ve kredi/banka kartı. Siparişinizde kullanılabilir yöntemler ödeme adımında listelenir. Havale bilgileri sipariş sonrasında müşteri panelinizde ve fatura e-postanızda yer alır. Kart ödemeleri PCI DSS uyumlu bir ödeme kuruluşu üzerinden alınır; kart bilgileriniz tarafımızca görülmez ve saklanmaz.</li>
    <li><strong>Otomatik yenileme ve tekrarlayan tahsilat:</strong> Kartla ödeme yaptığınızda, hizmetin dönem sonunda otomatik yenilenmesi için kartınızdan tekrarlayan tahsilat yapılmasına izin vermiş olursunuz. Bu izni müşteri panelinizden dilediğiniz zaman geri alabilirsiniz.</li>
    <li><strong>Teslimat masrafı:</strong> Yoktur.</li>
</ul>

<h2>4. İfa süresi</h2>
<p>Hizmet, ödemenizin teyidinden itibaren derhâl ve elektronik ortamda ifa edilir. İfa süresi hiçbir hâlde otuz (30) günü aşamaz.</p>

<h2>5. Sözleşmenin süresi ve yenilenmesi</h2>
<p>Hizmet, seçtiğiniz faturalama dönemi (aylık, üç aylık, altı aylık veya yıllık) boyunca geçerlidir. Dönem sonunda iptal etmediğiniz sürece aynı süreyle <strong>kendiliğinden yenilenir</strong> ve yeni dönem için fatura düzenlenir. Yenileme öncesinde tarafınıza bildirim yapılır.</p>
<p>Yenilemeyi durdurmak için, dönem bitiminden önce müşteri panelinizden iptal talebi oluşturmanız yeterlidir. Asgari bir taahhüt süresi bulunmamaktadır.</p>

<h2>6. Cayma hakkı</h2>
<p>Tüketici sıfatını taşıyorsanız, sözleşmenin kurulduğu tarihten itibaren <strong>on dört (14) gün</strong> içinde hiçbir gerekçe göstermeksizin ve cezai şart ödemeksizin cayma hakkınız bulunmaktadır.</p>
<p><strong>Cayma bildirimini iletebileceğiniz adres:</strong> <a href="mailto:{{ $company['email'] }}">{{ $company['email'] }}</a> — veya müşteri panelinizden destek talebi açarak.</p>
<p>Cayma hâlinde, hizmetten fiilen yararlandığınız süreye orantılı bedel mahsup edilerek kalan tutar, bildiriminizin bize ulaşmasından itibaren <strong>on dört (14) gün</strong> içinde, ödemeyi yaptığınız yöntemle iade edilir. İade nedeniyle sizden herhangi bir masraf talep edilmez.</p>

<h3>6.1. Ayrıca tanıdığımız para iade güvencesi</h3>
<p>Aşağıdaki istisnalar nedeniyle kanuni cayma hakkının doğmadığı hâllerde bile, barındırma paketlerinde size sözleşmeyle bir iade güvencesi veriyoruz: <strong>yıllık ve daha uzun süreli paketlerde 30 gün, aylık paketlerde 48 saat.</strong> Bu süre içinde gerekçe göstermeden iptal edebilir, ödediğiniz tutarın tamamını geri alabilirsiniz. Güvence yalnızca ilk satın alma içindir.</p>

<h3>6.2. Cayma hakkının kullanılamayacağı hizmetler</h3>
<div class="legal-note">
    <p>Mesafeli Sözleşmeler Yönetmeliği m.15 uyarınca aşağıdaki hizmetlerde <strong>cayma hakkı kullanılamaz.</strong> Sipariş vermekle bu durumdan haberdar olduğunuzu kabul etmiş olursunuz:</p>
    <ul>
        <li><strong>Alan adı kaydı, yenilemesi ve transferi</strong> — bedel, kayıt anında geri alınamaz biçimde tescil kuruluşuna aktarılır ve alan adı size özgülenir.</li>
        <li><strong>SSL sertifikaları</strong> — adınıza düzenlendiği anda size özgü hâle gelir.</li>
        <li><strong>Onayınızla ifasına başlanan ve 14 gün dolmadan tamamen ifa edilen hizmetler.</strong></li>
        <li><strong>Talebiniz üzerine özel olarak yapılandırılan hizmetler.</strong></li>
        <li><strong>Yenileme ve yükseltme ödemeleri</strong> — cayma hakkı yalnızca ilk satın alma içindir.</li>
        <li><strong>Kampanyayla ücretsiz verilen kalemler</strong> — asıl hizmetten cayarsanız bu kalemlerin bedeli iade tutarından düşülür.</li>
    </ul>
    <p style="margin-top:10px">Bu istisnalar Mesafeli Sözleşmeler Yönetmeliği m.15/1-(b), (ğ) ve (h) bentlerine dayanmaktadır.</p>
</div>

<h2>7. Şikâyet ve uyuşmazlık çözüm mercileri</h2>
<p>Şikâyetlerinizi öncelikle <a href="mailto:{{ $company['email'] }}">{{ $company['email'] }}</a> adresine iletmenizi rica ederiz.</p>
<p>Çözülemeyen uyuşmazlıklarda, Ticaret Bakanlığı tarafından her yıl aralık ayında ilan edilen parasal sınırlar dâhilinde, hizmeti satın aldığınız veya ikametgâhınızın bulunduğu yerdeki <strong>Tüketici Hakem Heyeti</strong>'ne, parasal sınırın üzerindeki uyuşmazlıklarda ise <strong>Tüketici Mahkemesi</strong>'ne başvurabilirsiniz.</p>

<h2>8. Kişisel verileriniz</h2>
<p>Sipariş sırasında verdiğiniz kişisel verilerin işlenmesine ilişkin ayrıntılı bilgi <a href="{{ route('legal.show', 'kvkk') }}">KVKK Aydınlatma Metni</a> ve <a href="{{ route('legal.show', 'privacy') }}">Gizlilik Politikası</a>'nda yer almaktadır.</p>

<h2>9. Diğer koşullar</h2>
<p>Siparişinize ayrıca <a href="{{ route('legal.show', 'terms') }}">Kullanım Koşulları</a>, <a href="{{ route('legal.show', 'aup') }}">Kabul Edilebilir Kullanım Politikası</a>, <a href="{{ route('legal.show', 'sla') }}">Hizmet Seviyesi Taahhüdü</a>, <a href="{{ route('legal.show', 'refund') }}">İptal, Cayma ve İade Politikası</a> ve alan adı satın alıyorsanız <a href="{{ route('legal.show', 'domain') }}">Alan Adı Kayıt Sözleşmesi</a> uygulanır.</p>

<p><strong>Bu formu okuduğunuzu ve sipariş vermeden önce yukarıdaki bilgilerin tamamının tarafınıza sunulduğunu, siparişi onaylamakla kabul etmiş olursunuz.</strong></p>
