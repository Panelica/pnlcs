<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The public mail client guide, /guides/mail-setup.
 *
 * Same move as the SSL page next to it: the text was written inline in two
 * @if($tr) halves and is now keyed. The reasoning for seeding rows rather than
 * adding to lang/en/client.php is written out in
 * 2026_09_20_110000_seed_ssl_page_translations.
 *
 * EIGHT KEYS ARE DELIBERATELY NOT HERE. The server settings table on this page
 * is the same table as the one on the customer's email page, and that one was
 * keyed in 2026_09_03_090000_seed_client_email_client_settings_keys:
 * hosting.email.settings_purpose / _server / _port / _security and
 * _incoming_recommended / _incoming_alt / _outgoing / _outgoing_alt. The values
 * there match this page word for word, so the view reads those keys. Two copies
 * of a table's headings can disagree; one cannot.
 *
 * The server name, the mail domain and the webmail address are derived per
 * install (PageController::mailSetup) and arrive as :host, :domain and :link.
 * The view escapes them before they are printed, because sentences carrying
 * mid-sentence emphasis are rendered with {!! !!}.
 *
 * Cached translation groups are flushed so the strings appear immediately
 * (see 2026_08_13_150000_flush_stale_translation_cache).
 */
return new class extends Migration
{
    private const GROUP = 'client';

    /** [key => [en, tr]] */
    private function rows(): array
    {
        return [
            'pages.mail_setup.title' => ['Email Setup', 'E-posta Kurulumu'],
            'pages.mail_setup.description' => [
                'The server name, ports and security settings needed to set up your hosting mailbox in Outlook, iPhone, Android and Thunderbird.',
                'Hosting hesabınızdaki e-posta adresini Outlook, iPhone, Android ve Thunderbird üzerinde kurmak için gereken sunucu adı, port ve güvenlik ayarları.',
            ],
            'pages.mail_setup.intro' => [
                'Everything you need to use your mailbox on your phone and in your desktop mail program is on this page. In most programs, entering the address and password is enough — the settings are found automatically.',
                'Hesabınızda açtığınız e-posta adresini telefonunuzda ve bilgisayarınızdaki posta programında kullanmak için gereken her şey bu sayfada. Çoğu programda adres ile parolayı yazmanız yeterli, ayarları kendisi buluyor.',
            ],
            'pages.mail_setup.on_this_page' => ['ON THIS PAGE', 'BU SAYFADA'],

            // Section headings: each is both the nav entry and the <h2>.
            // Outlook, Android and Thunderbird are product names and stay as
            // they are in the markup.
            'pages.mail_setup.settings' => ['Server settings', 'Sunucu ayarları'],
            'pages.mail_setup.automatic' => ['Automatic setup', 'Otomatik kurulum'],
            'pages.mail_setup.iphone' => ['iPhone and iPad', 'iPhone ve iPad'],
            'pages.mail_setup.webmail' => ['Email in the browser', 'Tarayıcıdan e-posta'],
            'pages.mail_setup.imap_pop3' => ['IMAP or POP3', 'IMAP mi POP3 mü'],
            'pages.mail_setup.problems' => ['Common problems', 'Sık karşılaşılan sorunlar'],

            'pages.mail_setup.settings_intro' => [
                'Whichever program you use, these are the values to enter. The incoming and outgoing server names are the same; only the port and protocol differ.',
                'Hangi programı kullanırsanız kullanın, girilecek değerler bunlar. Gelen ve giden sunucu adı aynı; değişen sadece port ve protokol.',
            ],
            'pages.mail_setup.username_note' => [
                '<strong>Username: your full email address.</strong> Not just the part before the <code>@</code> — enter it as <code>info@:domain</code>. This is the most common mistake.',
                '<strong>Kullanıcı adı: e-posta adresinizin tamamı.</strong> Sadece soldaki kısım değil — <code>info@:domain</code> gibi, <code>@</code> ve sonrasıyla birlikte. En sık yapılan hata budur.',
            ],
            'pages.mail_setup.password_note' => [
                '<strong>Password:</strong> the password you set when creating the mailbox. Not your client area password.',
                '<strong>Parola:</strong> e-posta kutusunu açarken belirlediğiniz parola. Müşteri panelindeki hesap parolanız değil.',
            ],
            'pages.mail_setup.auth_note' => [
                '<strong>Outgoing server authentication must be enabled</strong>, using the same username and password as the incoming server. Without it you cannot send mail.',
                '<strong>Giden sunucu kimlik doğrulaması açık olmalı</strong> ve gelen sunucuyla aynı kullanıcı adı ile parolayı kullanmalı. Kapalıysa posta gönderemezsiniz.',
            ],
            'pages.mail_setup.tls_note' => [
                'Connections are encrypted with <strong>TLS 1.2 or newer</strong>. The certificate is issued by Let\'s Encrypt, so your program will not warn you.',
                'Bağlantı <strong>TLS 1.2 ve üzeri</strong> ile şifreleniyor. Sertifika Let\'s Encrypt tarafından veriliyor, program uyarı vermez.',
            ],
            'pages.mail_setup.host_note' => [
                'Enter <strong>:host</strong> as the server name, not your own domain. Even though your domain\'s mail is delivered here, the certificate is issued for this name; any other name will make your program show a security warning.',
                'Sunucu adı olarak kendi alan adınızı değil, <strong>:host</strong> yazın. Alan adınızın postası bu sunucuya düşse de sertifika bu ada verilmiştir; başka bir ad yazarsanız programınız güvenlik uyarısı gösterir.',
            ],

            'pages.mail_setup.automatic_p1' => [
                'Outlook, Thunderbird and Apple Mail can ask the server for the settings themselves. In these programs it is usually enough to <strong>enter your email address and password</strong> — you do not need to type the server name or ports.',
                'Outlook, Thunderbird ve Apple Mail ayarları sunucudan kendisi sorabiliyor. Bu programlarda çoğu zaman <strong>e-posta adresi ile parolayı yazmanız yeterli</strong>; sunucu adını ve portları elle girmenize gerek kalmaz.',
            ],
            'pages.mail_setup.automatic_p2' => [
                'If automatic setup does not work for some reason, the program will ask you for the settings. Use the table above; the sections below give the steps for each program.',
                'Otomatik kurulum bir sebeple çalışmazsa program sizden ayarları isteyecektir. O zaman yukarıdaki tabloyu kullanın; aşağıdaki bölümlerde her program için adımlar var.',
            ],

            'pages.mail_setup.outlook_new_title' => ['New Outlook and Microsoft 365', 'Yeni Outlook ve Microsoft 365'],
            'pages.mail_setup.outlook_new_1' => [
                'Open Outlook and go to <strong>File &rarr; Add Account</strong>.',
                'Outlook\'u açın, <strong>Dosya &rarr; Hesap Ekle</strong> yolunu izleyin.',
            ],
            'pages.mail_setup.outlook_new_2' => [
                'Enter your email address and click <strong>Connect</strong>.',
                'E-posta adresinizi yazıp <strong>Bağlan</strong>\'a basın.',
            ],
            'pages.mail_setup.outlook_new_3' => [
                'Enter your password. Outlook fetches the settings from the server and finishes the setup.',
                'Parolanızı girin. Outlook ayarları sunucudan kendisi alır ve kurulum biter.',
            ],
            'pages.mail_setup.outlook_new_note' => [
                'If Outlook cannot find the settings and asks for an account type, choose <strong>IMAP</strong> and fill in the table above.',
                'Outlook ayarları bulamaz ve hesap türü sorarsa <strong>IMAP</strong>\'i seçip yukarıdaki tabloyu doldurun.',
            ],
            'pages.mail_setup.outlook_manual_title' => ['Manual setup (classic Outlook)', 'Elle kurulum (klasik Outlook)'],
            'pages.mail_setup.outlook_manual_1' => [
                '<strong>File &rarr; Add Account</strong>, then enter your email address.',
                '<strong>Dosya &rarr; Hesap Ekle</strong>, e-posta adresinizi yazın.',
            ],
            'pages.mail_setup.outlook_manual_2' => [
                'Open <strong>Advanced options</strong>, tick <strong>Let me set up my account manually</strong> and click <strong>Connect</strong>.',
                '<strong>Gelişmiş seçenekler</strong>\'i açıp <strong>Hesabımı el ile ayarlamama izin ver</strong> kutusunu işaretleyin, <strong>Bağlan</strong>\'a basın.',
            ],
            'pages.mail_setup.outlook_manual_3' => [
                'Choose <strong>IMAP</strong> as the account type.',
                'Hesap türü olarak <strong>IMAP</strong>\'i seçin.',
            ],
            'pages.mail_setup.outlook_manual_4' => [
                'Incoming: <code>:host</code>, port <strong>993</strong>, encryption <strong>SSL/TLS</strong>.',
                'Gelen posta: <code>:host</code>, port <strong>993</strong>, şifreleme <strong>SSL/TLS</strong>.',
            ],
            'pages.mail_setup.outlook_manual_5' => [
                'Outgoing: <code>:host</code>, port <strong>465</strong>, encryption <strong>SSL/TLS</strong>.',
                'Giden posta: <code>:host</code>, port <strong>465</strong>, şifreleme <strong>SSL/TLS</strong>.',
            ],
            'pages.mail_setup.outlook_manual_6' => [
                'Click <strong>Connect</strong> and enter your password.',
                '<strong>Bağlan</strong>\'a basıp parolanızı girin.',
            ],
            'pages.mail_setup.outlook_manual_note' => [
                'When asked for a username, enter your full email address. In the outgoing server settings, make sure <strong>“My outgoing server (SMTP) requires authentication”</strong> is ticked and <strong>“Use same settings as my incoming mail server”</strong> is selected.',
                'Kullanıcı adı sorulduğunda e-posta adresinizin tamamını yazın. Giden sunucu ayarlarında <strong>“Giden sunucum (SMTP) kimlik doğrulaması gerektiriyor”</strong> seçeneğinin işaretli, <strong>“Gelen posta sunucumla aynı ayarları kullan”</strong> seçili olduğundan emin olun.',
            ],

            'pages.mail_setup.iphone_1' => [
                'Open <strong>Settings</strong>. On iOS 18 and later go to <strong>Apps &rarr; Mail</strong>; on earlier versions go straight to <strong>Mail</strong>.',
                '<strong>Ayarlar</strong>\'ı açın. iOS 18 ve sonrasında <strong>Uygulamalar &rarr; Mail</strong>, daha eski sürümlerde doğrudan <strong>Mail</strong>\'e girin.',
            ],
            'pages.mail_setup.iphone_2' => [
                '<strong>Mail Accounts &rarr; Add Account &rarr; Other &rarr; Add Mail Account</strong>.',
                '<strong>Mail Hesapları &rarr; Hesap Ekle &rarr; Diğer &rarr; Mail Hesabı Ekle</strong>.',
            ],
            'pages.mail_setup.iphone_3' => [
                'Enter your name, email address and password, then tap <strong>Next</strong>.',
                'Adınızı, e-posta adresinizi ve parolanızı yazıp <strong>İleri</strong>\'ye dokunun.',
            ],
            'pages.mail_setup.iphone_4' => [
                'Make sure <strong>IMAP</strong> is selected in the tabs at the top.',
                'Üstteki sekmelerden <strong>IMAP</strong>\'in seçili olduğundan emin olun.',
            ],
            'pages.mail_setup.iphone_5' => [
                'For both <strong>Incoming Mail Server</strong> and <strong>Outgoing Mail Server</strong>, enter <code>:host</code> as the host name, your full email address as the username, and your mailbox password.',
                '<strong>Gelen Postalar Sunucusu</strong> ve <strong>Giden Postalar Sunucusu</strong> alanlarının ikisine de sunucu adı olarak <code>:host</code>, kullanıcı adı olarak e-posta adresinizin tamamını, parola olarak da kutu parolanızı yazın.',
            ],
            'pages.mail_setup.iphone_6' => [
                'Tap <strong>Next</strong>, and once verification finishes tap <strong>Save</strong>.',
                '<strong>İleri</strong>\'ye dokunun, doğrulama bitince <strong>Kaydet</strong>\'e basın.',
            ],
            'pages.mail_setup.iphone_note' => [
                'Although the username and password look optional under the outgoing server, <strong>you must fill in both</strong>; leaving them empty means you cannot send mail from the phone.',
                'Giden sunucu bölümünde kullanıcı adı ve parola “isteğe bağlı” görünse de <strong>ikisini de doldurmanız gerekiyor</strong>; boş bırakırsanız telefondan posta gönderemezsiniz.',
            ],

            'pages.mail_setup.android_intro' => [
                'These steps follow the Gmail app. Names differ in Samsung Email and other apps, but the information asked for is the same.',
                'Aşağıdaki adımlar Gmail uygulamasına göredir. Samsung E-posta ve diğer uygulamalarda isimler değişse de istenen bilgiler aynıdır.',
            ],
            'pages.mail_setup.android_1' => [
                'Open the Gmail app, tap your profile picture at the top right and choose <strong>Add another account</strong>.',
                'Gmail uygulamasını açın, sağ üstteki profil resmine dokunup <strong>Başka hesap ekle</strong>\'yi seçin.',
            ],
            'pages.mail_setup.android_2' => [
                'Tap <strong>Other</strong> at the bottom of the list.',
                'Listenin sonundaki <strong>Diğer</strong> seçeneğine dokunun.',
            ],
            'pages.mail_setup.android_3' => [
                'Enter your email address, tap <strong>Manual setup</strong> and choose <strong>Personal (IMAP)</strong>.',
                'E-posta adresinizi yazın, <strong>Elle kurulum</strong>\'a dokunup <strong>Kişisel (IMAP)</strong>\'i seçin.',
            ],
            'pages.mail_setup.android_4' => ['Enter your password.', 'Parolanızı girin.'],
            'pages.mail_setup.android_5' => [
                'Incoming server: <code>:host</code>, port <strong>993</strong>, security <strong>SSL/TLS</strong>.',
                'Gelen sunucu: <code>:host</code>, port <strong>993</strong>, güvenlik <strong>SSL/TLS</strong>.',
            ],
            'pages.mail_setup.android_6' => [
                'Outgoing server (SMTP): <code>:host</code>, port <strong>465</strong>, security <strong>SSL/TLS</strong>, with <strong>Require sign-in</strong> enabled.',
                'Giden sunucu (SMTP): <code>:host</code>, port <strong>465</strong>, güvenlik <strong>SSL/TLS</strong>, <strong>Oturum açmayı gerektir</strong> açık.',
            ],

            'pages.mail_setup.thunderbird_1' => [
                '<strong>Account Settings &rarr; Account Actions &rarr; Add Mail Account</strong>.',
                '<strong>Hesap Ayarları &rarr; Hesap İşlemleri &rarr; E-posta Hesabı Ekle</strong>.',
            ],
            'pages.mail_setup.thunderbird_2' => [
                'Enter your name, email address and password, then click <strong>Continue</strong>.',
                'Adınızı, e-posta adresinizi ve parolanızı yazıp <strong>Devam</strong>\'a basın.',
            ],
            'pages.mail_setup.thunderbird_3' => [
                'Thunderbird fetches the settings from the server. Confirm the suggestion with <strong>IMAP</strong> selected and click <strong>Done</strong>.',
                'Thunderbird ayarları sunucudan kendisi alır. <strong>IMAP</strong> seçili gelen öneriyi onaylayıp <strong>Bitti</strong>\'ye basın.',
            ],

            'pages.mail_setup.webmail_p1' => [
                'You can also read your mail in a browser without setting anything up. It is handy on someone else\'s computer, or to confirm the account works before configuring a program.',
                'Hiçbir kurulum yapmadan, tarayıcıdan da postanıza girebilirsiniz. Başkasının bilgisayarındayken veya kurulumu denemeden önce hesabın çalıştığını görmek için pratiktir.',
            ],
            // :link is the webmail address, built in the view so the URL and
            // its target/rel attributes stay out of the translation.
            'pages.mail_setup.webmail_p2' => [
                'Address: :link — the username is your full email address and the password is your mailbox password. The <strong>Webmail</strong> button on the <strong>My Services &rarr; Email Accounts</strong> page in the client area takes you to the same place.',
                'Adres: :link — kullanıcı adı e-posta adresinizin tamamı, parola kutu parolanızdır. Müşteri panelinde <strong>Hizmetlerim &rarr; E-posta Hesapları</strong> sayfasındaki <strong>Webmail</strong> düğmesi de sizi buraya getirir.',
            ],

            'pages.mail_setup.imap_p1' => [
                '<strong>Use IMAP.</strong> Your mail stays on the server and your phone and computer see the same mailbox: a message you read on one shows as read on the other, and moving it to a folder moves it on both.',
                '<strong>IMAP kullanın.</strong> Postalarınız sunucuda durur, telefonunuz ile bilgisayarınız aynı kutuyu görür: birinde okuduğunuz posta diğerinde de okunmuş görünür, bir klasöre taşıdığınızda her iki cihazda da taşınır.',
            ],
            'pages.mail_setup.pop3_p1' => [
                '<strong>POP3</strong> downloads messages and, with most settings, deletes them from the server. It works if you use a single device and want the mail kept only there; if you check from two devices, the mailboxes will not match. And because the mail lives only on that device, losing the device loses the mail.',
                '<strong>POP3</strong> postaları sunucudan indirip çoğu ayarda sunucudan siler. Tek bir cihaz kullanıyorsanız ve postaları yalnızca o cihazda saklamak istiyorsanız işe yarar; iki cihazdan bakıyorsanız kutularınız birbirini tutmaz. Ayrıca postalar yalnızca o cihazda olduğu için cihaz kaybolduğunda postalar da gider.',
            ],
            'pages.mail_setup.quota_p1' => [
                'You can follow your mailbox usage on the email page in the client area. With IMAP the mail stays on the server, so this is what fills the quota — deleting old messages with large attachments frees up space.',
                'Kutu doluluğunuzu müşteri panelindeki e-posta sayfasından takip edebilirsiniz. IMAP\'ta postalar sunucuda durduğu için kotayı asıl dolduran budur; eski ve büyük ekli postaları silmek yer açar.',
            ],

            'pages.mail_setup.problem_send_title' => ['I can receive but not send', 'Posta alıyorum ama gönderemiyorum'],
            'pages.mail_setup.problem_send_p1' => [
                'Almost always because outgoing server authentication is off. Turn it on in your program\'s SMTP settings and make sure it uses the same username and password as the incoming server.',
                'Neredeyse her zaman giden sunucu kimlik doğrulamasının kapalı olmasındandır. Programınızın SMTP ayarlarında kimlik doğrulamayı açın ve gelen sunucuyla aynı kullanıcı adı ile parolayı kullandığından emin olun.',
            ],
            'pages.mail_setup.problem_send_p2' => [
                'Some internet providers and company networks <strong>block port 25</strong>. We ask you to use 465 or 587 anyway; if 25 is set, change it to 465.',
                'Bazı internet sağlayıcıları ve şirket ağları giden posta için kullanılan <strong>25. portu kapatır</strong>. Zaten 465 veya 587 kullanmanızı istiyoruz; 25 yazılıysa 465 ile değiştirin.',
            ],
            'pages.mail_setup.problem_auth_title' => ['The username or password is rejected', 'Kullanıcı adı veya parola kabul edilmiyor'],
            'pages.mail_setup.problem_auth_p1' => [
                'Make sure the username is the full email address: <code>info@:domain</code>, not <code>info</code>. If you do not remember the password, you can set a new one on the <strong>My Services &rarr; Email Accounts</strong> page in the client area — you do not need the old one.',
                'Kullanıcı adına e-posta adresinin tamamını yazdığınızdan emin olun: <code>info</code> değil <code>info@:domain</code>. Parolayı hatırlamıyorsanız müşteri panelinde <strong>Hizmetlerim &rarr; E-posta Hesapları</strong> sayfasından yeni bir parola belirleyebilirsiniz; eski parolayı bilmenize gerek yok.',
            ],
            'pages.mail_setup.problem_cert_title' => ['I get a certificate warning', 'Sertifika uyarısı görüyorum'],
            'pages.mail_setup.problem_cert_p1' => [
                'You have entered your own domain as the server name. Replace it with <code>:host</code> and the warning will go away. Do not dismiss the warning and continue; your connection would be left unprotected.',
                'Sunucu adı olarak kendi alan adınızı yazmışsınızdır. <code>:host</code> ile değiştirin; uyarı kalkacaktır. Uyarıyı “yok say” diyerek geçmeyin, bağlantınız korumasız kalır.',
            ],
            'pages.mail_setup.problem_sync_title' => ['Mail shows on the phone but not on the computer', 'Postalar telefonda görünüp bilgisayarda görünmüyor'],
            'pages.mail_setup.problem_sync_p1' => [
                'One of the devices was set up with POP3 and has downloaded the mail off the server. Set both devices up with IMAP.',
                'Cihazlardan biri POP3 ile kurulmuş ve postaları sunucudan indirip silmiş demektir. Her iki cihazı da IMAP ile kurun.',
            ],
            'pages.mail_setup.problem_spam_title' => ['My messages land in the recipient\'s spam folder', 'Gönderdiğim postalar karşı tarafta spam\'e düşüyor'],
            'pages.mail_setup.problem_spam_p1' => [
                'This happens when your domain is missing SPF, DKIM and DMARC records. If we host your domain\'s DNS, these are created when the account is set up. If your DNS is elsewhere, open a support ticket and we will send you the records to add.',
                'Alan adınızın SPF, DKIM ve DMARC kayıtları eksikse böyle olur. Alan adının DNS\'i bizdeyse bu kayıtlar hesap açılırken tanımlanır. DNS\'i başka bir yerdeyse destek talebi açın, hangi kayıtları eklemeniz gerektiğini gönderelim.',
            ],

            'pages.mail_setup.footer_note' => [
                'If the account still does not work after following these steps, open a support ticket in the client area; telling us which program you use and the exact error message helps us solve it faster.',
                'Adımları uyguladığınız halde hesap çalışmıyorsa müşteri panelinden destek talebi açın; hangi programı kullandığınızı ve aldığınız hata mesajını yazarsanız daha hızlı çözeriz.',
            ],
        ];
    }

    public function up(): void
    {
        $now = now();

        foreach ($this->rows() as $key => [$en, $tr]) {
            foreach (['en' => $en, 'tr' => $tr] as $language => $value) {
                // An operator may have already written this string through the
                // translation editor. Their text wins; a seed that replaced it
                // would be data loss.
                $exists = DB::table('dynamic_translations')
                    ->where('language', $language)->where('group', self::GROUP)->where('key', $key)->exists();

                if (! $exists) {
                    DB::table('dynamic_translations')->insert([
                        'language' => $language, 'group' => self::GROUP, 'key' => $key, 'value' => $value,
                        'is_auto_translated' => false, 'is_reviewed' => true,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }

        try {
            foreach (DB::table('dynamic_translations')->distinct()->pluck('language') as $language) {
                Cache::forget("translations:{$language}:".self::GROUP);
            }
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        DB::table('dynamic_translations')
            ->where('group', self::GROUP)
            ->whereIn('language', ['en', 'tr'])
            ->whereIn('key', array_keys($this->rows()))
            ->delete();
    }
};
