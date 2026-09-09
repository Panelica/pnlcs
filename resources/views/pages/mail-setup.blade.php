{{--
     E-posta kurulum rehberi.

     Sayfadaki her sunucu adı, port ve güvenlik tipi canlı sistemden
     doğrulanarak yazıldı: dovecot 993/995 SSL, postfix 465 SSL ve 587
     STARTTLS dinliyor, sertifika mail/webmail/autoconfig/autodiscover
     adlarını kapsıyor. Sunucunun davranışı değişirse bu metin de
     değişmeli, tersi değil.

     legal.layout paylaşılıyor: Yasal belgeler, Hakkımızda ve SSL
     sayfaları da aynı düzeni kullanıyor.
--}}
@extends('legal.layout')

@php $tr = app()->getLocale() !== 'en'; @endphp

@section('legal-title', $tr ? 'E-posta Kurulumu' : 'Email Setup')
@section('legal-description', $tr
    ? 'Hosting hesabınızdaki e-posta adresini Outlook, iPhone, Android ve Thunderbird üzerinde kurmak için gereken sunucu adı, port ve güvenlik ayarları.'
    : 'The server name, ports and security settings needed to set up your hosting mailbox in Outlook, iPhone, Android and Thunderbird.')

@section('legal-content')
    <div class="legal-head">
        <h1>{{ $tr ? 'E-posta Kurulumu' : 'Email Setup' }}</h1>
        <p>
            {{ $tr
                ? 'Hesabınızda açtığınız e-posta adresini telefonunuzda ve bilgisayarınızdaki posta programında kullanmak için gereken her şey bu sayfada. Çoğu programda adres ile parolayı yazmanız yeterli, ayarları kendisi buluyor.'
                : 'Everything you need to use your mailbox on your phone and in your desktop mail program is on this page. In most programs, entering the address and password is enough — the settings are found automatically.' }}
        </p>
    </div>

    <div class="legal-grid">
        <nav class="legal-side">
            <p class="legal-side-title">{{ $tr ? 'BU SAYFADA' : 'ON THIS PAGE' }}</p>
            <a href="#ayarlar">{{ $tr ? 'Sunucu ayarları' : 'Server settings' }}</a>
            <a href="#otomatik">{{ $tr ? 'Otomatik kurulum' : 'Automatic setup' }}</a>
            <a href="#outlook">Outlook</a>
            <a href="#iphone">{{ $tr ? 'iPhone ve iPad' : 'iPhone and iPad' }}</a>
            <a href="#android">Android</a>
            <a href="#thunderbird">Thunderbird</a>
            <a href="#webmail">{{ $tr ? 'Tarayıcıdan e-posta' : 'Email in the browser' }}</a>
            <a href="#imap-pop3">{{ $tr ? 'IMAP mi POP3 mü' : 'IMAP or POP3' }}</a>
            <a href="#sorunlar">{{ $tr ? 'Sık karşılaşılan sorunlar' : 'Common problems' }}</a>
        </nav>

        <article class="legal-body">
            <h2 id="ayarlar">{{ $tr ? 'Sunucu ayarları' : 'Server settings' }}</h2>

            @if($tr)
                <p>Hangi programı kullanırsanız kullanın, girilecek değerler bunlar. Gelen ve giden sunucu adı aynı; değişen sadece port ve protokol.</p>
            @else
                <p>Whichever program you use, these are the values to enter. The incoming and outgoing server names are the same; only the port and protocol differ.</p>
            @endif

            <table>
                <thead>
                    <tr>
                        <th>{{ $tr ? 'Ne için' : 'Purpose' }}</th>
                        <th>{{ $tr ? 'Sunucu' : 'Server' }}</th>
                        <th>{{ $tr ? 'Port' : 'Port' }}</th>
                        <th>{{ $tr ? 'Güvenlik' : 'Security' }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>IMAP</strong><br><span style="opacity:.7">{{ $tr ? 'gelen posta — önerilen' : 'incoming — recommended' }}</span></td>
                        <td>{{ $mail['host'] }}</td>
                        <td><strong>993</strong></td>
                        <td>SSL/TLS</td>
                    </tr>
                    <tr>
                        <td><strong>POP3</strong><br><span style="opacity:.7">{{ $tr ? 'gelen posta — alternatif' : 'incoming — alternative' }}</span></td>
                        <td>{{ $mail['host'] }}</td>
                        <td><strong>995</strong></td>
                        <td>SSL/TLS</td>
                    </tr>
                    <tr>
                        <td><strong>SMTP</strong><br><span style="opacity:.7">{{ $tr ? 'giden posta' : 'outgoing' }}</span></td>
                        <td>{{ $mail['host'] }}</td>
                        <td><strong>465</strong></td>
                        <td>SSL/TLS</td>
                    </tr>
                    <tr>
                        <td><strong>SMTP</strong><br><span style="opacity:.7">{{ $tr ? 'giden posta — alternatif' : 'outgoing — alternative' }}</span></td>
                        <td>{{ $mail['host'] }}</td>
                        <td><strong>587</strong></td>
                        <td>STARTTLS</td>
                    </tr>
                </tbody>
            </table>

            @if($tr)
                <ul>
                    <li><strong>Kullanıcı adı: e-posta adresinizin tamamı.</strong> Sadece soldaki kısım değil — <code>info@{{ $mail['domain'] }}</code> gibi, <code>@</code> ve sonrasıyla birlikte. En sık yapılan hata budur.</li>
                    <li><strong>Parola:</strong> e-posta kutusunu açarken belirlediğiniz parola. Müşteri panelindeki hesap parolanız değil.</li>
                    <li><strong>Giden sunucu kimlik doğrulaması açık olmalı</strong> ve gelen sunucuyla aynı kullanıcı adı ile parolayı kullanmalı. Kapalıysa posta gönderemezsiniz.</li>
                    <li>Bağlantı <strong>TLS 1.2 ve üzeri</strong> ile şifreleniyor. Sertifika Let's Encrypt tarafından veriliyor, program uyarı vermez.</li>
                </ul>

                <div class="legal-note">
                    <p style="margin:0">Sunucu adı olarak kendi alan adınızı değil, <strong>{{ $mail['host'] }}</strong> yazın. Alan adınızın postası bu sunucuya düşse de sertifika bu ada verilmiştir; başka bir ad yazarsanız programınız güvenlik uyarısı gösterir.</p>
                </div>
            @else
                <ul>
                    <li><strong>Username: your full email address.</strong> Not just the part before the <code>@</code> — enter it as <code>info@{{ $mail['domain'] }}</code>. This is the most common mistake.</li>
                    <li><strong>Password:</strong> the password you set when creating the mailbox. Not your client area password.</li>
                    <li><strong>Outgoing server authentication must be enabled</strong>, using the same username and password as the incoming server. Without it you cannot send mail.</li>
                    <li>Connections are encrypted with <strong>TLS 1.2 or newer</strong>. The certificate is issued by Let's Encrypt, so your program will not warn you.</li>
                </ul>

                <div class="legal-note">
                    <p style="margin:0">Enter <strong>{{ $mail['host'] }}</strong> as the server name, not your own domain. Even though your domain's mail is delivered here, the certificate is issued for this name; any other name will make your program show a security warning.</p>
                </div>
            @endif

            <h2 id="otomatik">{{ $tr ? 'Otomatik kurulum' : 'Automatic setup' }}</h2>

            @if($tr)
                <p>Outlook, Thunderbird ve Apple Mail ayarları sunucudan kendisi sorabiliyor. Bu programlarda çoğu zaman <strong>e-posta adresi ile parolayı yazmanız yeterli</strong>; sunucu adını ve portları elle girmenize gerek kalmaz.</p>
                <p>Otomatik kurulum bir sebeple çalışmazsa program sizden ayarları isteyecektir. O zaman yukarıdaki tabloyu kullanın; aşağıdaki bölümlerde her program için adımlar var.</p>
            @else
                <p>Outlook, Thunderbird and Apple Mail can ask the server for the settings themselves. In these programs it is usually enough to <strong>enter your email address and password</strong> — you do not need to type the server name or ports.</p>
                <p>If automatic setup does not work for some reason, the program will ask you for the settings. Use the table above; the sections below give the steps for each program.</p>
            @endif

            <h2 id="outlook">Outlook</h2>

            @if($tr)
                <h3>Yeni Outlook ve Microsoft 365</h3>
                <ol>
                    <li>Outlook'u açın, <strong>Dosya &rarr; Hesap Ekle</strong> yolunu izleyin.</li>
                    <li>E-posta adresinizi yazıp <strong>Bağlan</strong>'a basın.</li>
                    <li>Parolanızı girin. Outlook ayarları sunucudan kendisi alır ve kurulum biter.</li>
                </ol>
                <p>Outlook ayarları bulamaz ve hesap türü sorarsa <strong>IMAP</strong>'i seçip yukarıdaki tabloyu doldurun.</p>

                <h3>Elle kurulum (klasik Outlook)</h3>
                <ol>
                    <li><strong>Dosya &rarr; Hesap Ekle</strong>, e-posta adresinizi yazın.</li>
                    <li><strong>Gelişmiş seçenekler</strong>'i açıp <strong>Hesabımı el ile ayarlamama izin ver</strong> kutusunu işaretleyin, <strong>Bağlan</strong>'a basın.</li>
                    <li>Hesap türü olarak <strong>IMAP</strong>'i seçin.</li>
                    <li>Gelen posta: <code>{{ $mail['host'] }}</code>, port <strong>993</strong>, şifreleme <strong>SSL/TLS</strong>.</li>
                    <li>Giden posta: <code>{{ $mail['host'] }}</code>, port <strong>465</strong>, şifreleme <strong>SSL/TLS</strong>.</li>
                    <li><strong>Bağlan</strong>'a basıp parolanızı girin.</li>
                </ol>
                <p>Kullanıcı adı sorulduğunda e-posta adresinizin tamamını yazın. Giden sunucu ayarlarında <strong>“Giden sunucum (SMTP) kimlik doğrulaması gerektiriyor”</strong> seçeneğinin işaretli, <strong>“Gelen posta sunucumla aynı ayarları kullan”</strong> seçili olduğundan emin olun.</p>
            @else
                <h3>New Outlook and Microsoft 365</h3>
                <ol>
                    <li>Open Outlook and go to <strong>File &rarr; Add Account</strong>.</li>
                    <li>Enter your email address and click <strong>Connect</strong>.</li>
                    <li>Enter your password. Outlook fetches the settings from the server and finishes the setup.</li>
                </ol>
                <p>If Outlook cannot find the settings and asks for an account type, choose <strong>IMAP</strong> and fill in the table above.</p>

                <h3>Manual setup (classic Outlook)</h3>
                <ol>
                    <li><strong>File &rarr; Add Account</strong>, then enter your email address.</li>
                    <li>Open <strong>Advanced options</strong>, tick <strong>Let me set up my account manually</strong> and click <strong>Connect</strong>.</li>
                    <li>Choose <strong>IMAP</strong> as the account type.</li>
                    <li>Incoming: <code>{{ $mail['host'] }}</code>, port <strong>993</strong>, encryption <strong>SSL/TLS</strong>.</li>
                    <li>Outgoing: <code>{{ $mail['host'] }}</code>, port <strong>465</strong>, encryption <strong>SSL/TLS</strong>.</li>
                    <li>Click <strong>Connect</strong> and enter your password.</li>
                </ol>
                <p>When asked for a username, enter your full email address. In the outgoing server settings, make sure <strong>“My outgoing server (SMTP) requires authentication”</strong> is ticked and <strong>“Use same settings as my incoming mail server”</strong> is selected.</p>
            @endif

            <h2 id="iphone">{{ $tr ? 'iPhone ve iPad' : 'iPhone and iPad' }}</h2>

            @if($tr)
                <ol>
                    <li><strong>Ayarlar</strong>'ı açın. iOS 18 ve sonrasında <strong>Uygulamalar &rarr; Mail</strong>, daha eski sürümlerde doğrudan <strong>Mail</strong>'e girin.</li>
                    <li><strong>Mail Hesapları &rarr; Hesap Ekle &rarr; Diğer &rarr; Mail Hesabı Ekle</strong>.</li>
                    <li>Adınızı, e-posta adresinizi ve parolanızı yazıp <strong>İleri</strong>'ye dokunun.</li>
                    <li>Üstteki sekmelerden <strong>IMAP</strong>'in seçili olduğundan emin olun.</li>
                    <li><strong>Gelen Postalar Sunucusu</strong> ve <strong>Giden Postalar Sunucusu</strong> alanlarının ikisine de sunucu adı olarak <code>{{ $mail['host'] }}</code>, kullanıcı adı olarak e-posta adresinizin tamamını, parola olarak da kutu parolanızı yazın.</li>
                    <li><strong>İleri</strong>'ye dokunun, doğrulama bitince <strong>Kaydet</strong>'e basın.</li>
                </ol>
                <p>Giden sunucu bölümünde kullanıcı adı ve parola “isteğe bağlı” görünse de <strong>ikisini de doldurmanız gerekiyor</strong>; boş bırakırsanız telefondan posta gönderemezsiniz.</p>
            @else
                <ol>
                    <li>Open <strong>Settings</strong>. On iOS 18 and later go to <strong>Apps &rarr; Mail</strong>; on earlier versions go straight to <strong>Mail</strong>.</li>
                    <li><strong>Mail Accounts &rarr; Add Account &rarr; Other &rarr; Add Mail Account</strong>.</li>
                    <li>Enter your name, email address and password, then tap <strong>Next</strong>.</li>
                    <li>Make sure <strong>IMAP</strong> is selected in the tabs at the top.</li>
                    <li>For both <strong>Incoming Mail Server</strong> and <strong>Outgoing Mail Server</strong>, enter <code>{{ $mail['host'] }}</code> as the host name, your full email address as the username, and your mailbox password.</li>
                    <li>Tap <strong>Next</strong>, and once verification finishes tap <strong>Save</strong>.</li>
                </ol>
                <p>Although the username and password look optional under the outgoing server, <strong>you must fill in both</strong>; leaving them empty means you cannot send mail from the phone.</p>
            @endif

            <h2 id="android">Android</h2>

            @if($tr)
                <p>Aşağıdaki adımlar Gmail uygulamasına göredir. Samsung E-posta ve diğer uygulamalarda isimler değişse de istenen bilgiler aynıdır.</p>
                <ol>
                    <li>Gmail uygulamasını açın, sağ üstteki profil resmine dokunup <strong>Başka hesap ekle</strong>'yi seçin.</li>
                    <li>Listenin sonundaki <strong>Diğer</strong> seçeneğine dokunun.</li>
                    <li>E-posta adresinizi yazın, <strong>Elle kurulum</strong>'a dokunup <strong>Kişisel (IMAP)</strong>'i seçin.</li>
                    <li>Parolanızı girin.</li>
                    <li>Gelen sunucu: <code>{{ $mail['host'] }}</code>, port <strong>993</strong>, güvenlik <strong>SSL/TLS</strong>.</li>
                    <li>Giden sunucu (SMTP): <code>{{ $mail['host'] }}</code>, port <strong>465</strong>, güvenlik <strong>SSL/TLS</strong>, <strong>Oturum açmayı gerektir</strong> açık.</li>
                </ol>
            @else
                <p>These steps follow the Gmail app. Names differ in Samsung Email and other apps, but the information asked for is the same.</p>
                <ol>
                    <li>Open the Gmail app, tap your profile picture at the top right and choose <strong>Add another account</strong>.</li>
                    <li>Tap <strong>Other</strong> at the bottom of the list.</li>
                    <li>Enter your email address, tap <strong>Manual setup</strong> and choose <strong>Personal (IMAP)</strong>.</li>
                    <li>Enter your password.</li>
                    <li>Incoming server: <code>{{ $mail['host'] }}</code>, port <strong>993</strong>, security <strong>SSL/TLS</strong>.</li>
                    <li>Outgoing server (SMTP): <code>{{ $mail['host'] }}</code>, port <strong>465</strong>, security <strong>SSL/TLS</strong>, with <strong>Require sign-in</strong> enabled.</li>
                </ol>
            @endif

            <h2 id="thunderbird">Thunderbird</h2>

            @if($tr)
                <ol>
                    <li><strong>Hesap Ayarları &rarr; Hesap İşlemleri &rarr; E-posta Hesabı Ekle</strong>.</li>
                    <li>Adınızı, e-posta adresinizi ve parolanızı yazıp <strong>Devam</strong>'a basın.</li>
                    <li>Thunderbird ayarları sunucudan kendisi alır. <strong>IMAP</strong> seçili gelen öneriyi onaylayıp <strong>Bitti</strong>'ye basın.</li>
                </ol>
            @else
                <ol>
                    <li><strong>Account Settings &rarr; Account Actions &rarr; Add Mail Account</strong>.</li>
                    <li>Enter your name, email address and password, then click <strong>Continue</strong>.</li>
                    <li>Thunderbird fetches the settings from the server. Confirm the suggestion with <strong>IMAP</strong> selected and click <strong>Done</strong>.</li>
                </ol>
            @endif

            <h2 id="webmail">{{ $tr ? 'Tarayıcıdan e-posta' : 'Email in the browser' }}</h2>

            @if($tr)
                <p>Hiçbir kurulum yapmadan, tarayıcıdan da postanıza girebilirsiniz. Başkasının bilgisayarındayken veya kurulumu denemeden önce hesabın çalıştığını görmek için pratiktir.</p>
                <p>Adres: <a href="{{ $mail['webmail'] }}" target="_blank" rel="noopener">{{ $mail['webmail_label'] }}</a> — kullanıcı adı e-posta adresinizin tamamı, parola kutu parolanızdır. Müşteri panelinde <strong>Hizmetlerim &rarr; E-posta Hesapları</strong> sayfasındaki <strong>Webmail</strong> düğmesi de sizi buraya getirir.</p>
            @else
                <p>You can also read your mail in a browser without setting anything up. It is handy on someone else's computer, or to confirm the account works before configuring a program.</p>
                <p>Address: <a href="{{ $mail['webmail'] }}" target="_blank" rel="noopener">{{ $mail['webmail_label'] }}</a> — the username is your full email address and the password is your mailbox password. The <strong>Webmail</strong> button on the <strong>My Services &rarr; Email Accounts</strong> page in the client area takes you to the same place.</p>
            @endif

            <h2 id="imap-pop3">{{ $tr ? 'IMAP mi POP3 mü' : 'IMAP or POP3' }}</h2>

            @if($tr)
                <p><strong>IMAP kullanın.</strong> Postalarınız sunucuda durur, telefonunuz ile bilgisayarınız aynı kutuyu görür: birinde okuduğunuz posta diğerinde de okunmuş görünür, bir klasöre taşıdığınızda her iki cihazda da taşınır.</p>
                <p><strong>POP3</strong> postaları sunucudan indirip çoğu ayarda sunucudan siler. Tek bir cihaz kullanıyorsanız ve postaları yalnızca o cihazda saklamak istiyorsanız işe yarar; iki cihazdan bakıyorsanız kutularınız birbirini tutmaz. Ayrıca postalar yalnızca o cihazda olduğu için cihaz kaybolduğunda postalar da gider.</p>
                <p>Kutu doluluğunuzu müşteri panelindeki e-posta sayfasından takip edebilirsiniz. IMAP'ta postalar sunucuda durduğu için kotayı asıl dolduran budur; eski ve büyük ekli postaları silmek yer açar.</p>
            @else
                <p><strong>Use IMAP.</strong> Your mail stays on the server and your phone and computer see the same mailbox: a message you read on one shows as read on the other, and moving it to a folder moves it on both.</p>
                <p><strong>POP3</strong> downloads messages and, with most settings, deletes them from the server. It works if you use a single device and want the mail kept only there; if you check from two devices, the mailboxes will not match. And because the mail lives only on that device, losing the device loses the mail.</p>
                <p>You can follow your mailbox usage on the email page in the client area. With IMAP the mail stays on the server, so this is what fills the quota — deleting old messages with large attachments frees up space.</p>
            @endif

            <h2 id="sorunlar">{{ $tr ? 'Sık karşılaşılan sorunlar' : 'Common problems' }}</h2>

            @if($tr)
                <h3>Posta alıyorum ama gönderemiyorum</h3>
                <p>Neredeyse her zaman giden sunucu kimlik doğrulamasının kapalı olmasındandır. Programınızın SMTP ayarlarında kimlik doğrulamayı açın ve gelen sunucuyla aynı kullanıcı adı ile parolayı kullandığından emin olun.</p>
                <p>Bazı internet sağlayıcıları ve şirket ağları giden posta için kullanılan <strong>25. portu kapatır</strong>. Zaten 465 veya 587 kullanmanızı istiyoruz; 25 yazılıysa 465 ile değiştirin.</p>

                <h3>Kullanıcı adı veya parola kabul edilmiyor</h3>
                <p>Kullanıcı adına e-posta adresinin tamamını yazdığınızdan emin olun: <code>info</code> değil <code>info@{{ $mail['domain'] }}</code>. Parolayı hatırlamıyorsanız müşteri panelinde <strong>Hizmetlerim &rarr; E-posta Hesapları</strong> sayfasından yeni bir parola belirleyebilirsiniz; eski parolayı bilmenize gerek yok.</p>

                <h3>Sertifika uyarısı görüyorum</h3>
                <p>Sunucu adı olarak kendi alan adınızı yazmışsınızdır. <code>{{ $mail['host'] }}</code> ile değiştirin; uyarı kalkacaktır. Uyarıyı “yok say” diyerek geçmeyin, bağlantınız korumasız kalır.</p>

                <h3>Postalar telefonda görünüp bilgisayarda görünmüyor</h3>
                <p>Cihazlardan biri POP3 ile kurulmuş ve postaları sunucudan indirip silmiş demektir. Her iki cihazı da IMAP ile kurun.</p>

                <h3>Gönderdiğim postalar karşı tarafta spam'e düşüyor</h3>
                <p>Alan adınızın SPF, DKIM ve DMARC kayıtları eksikse böyle olur. Alan adının DNS'i bizdeyse bu kayıtlar hesap açılırken tanımlanır. DNS'i başka bir yerdeyse destek talebi açın, hangi kayıtları eklemeniz gerektiğini gönderelim.</p>
            @else
                <h3>I can receive but not send</h3>
                <p>Almost always because outgoing server authentication is off. Turn it on in your program's SMTP settings and make sure it uses the same username and password as the incoming server.</p>
                <p>Some internet providers and company networks <strong>block port 25</strong>. We ask you to use 465 or 587 anyway; if 25 is set, change it to 465.</p>

                <h3>The username or password is rejected</h3>
                <p>Make sure the username is the full email address: <code>info@{{ $mail['domain'] }}</code>, not <code>info</code>. If you do not remember the password, you can set a new one on the <strong>My Services &rarr; Email Accounts</strong> page in the client area — you do not need the old one.</p>

                <h3>I get a certificate warning</h3>
                <p>You have entered your own domain as the server name. Replace it with <code>{{ $mail['host'] }}</code> and the warning will go away. Do not dismiss the warning and continue; your connection would be left unprotected.</p>

                <h3>Mail shows on the phone but not on the computer</h3>
                <p>One of the devices was set up with POP3 and has downloaded the mail off the server. Set both devices up with IMAP.</p>

                <h3>My messages land in the recipient's spam folder</h3>
                <p>This happens when your domain is missing SPF, DKIM and DMARC records. If we host your domain's DNS, these are created when the account is set up. If your DNS is elsewhere, open a support ticket and we will send you the records to add.</p>
            @endif

            <p style="margin-top:32px">
                {{ $tr
                    ? 'Adımları uyguladığınız halde hesap çalışmıyorsa müşteri panelinden destek talebi açın; hangi programı kullandığınızı ve aldığınız hata mesajını yazarsanız daha hızlı çözeriz.'
                    : 'If the account still does not work after following these steps, open a support ticket in the client area; telling us which program you use and the exact error message helps us solve it faster.' }}
            </p>
        </article>
    </div>
@endsection
