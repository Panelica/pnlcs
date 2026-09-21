{{--
     E-posta kurulum rehberi.

     Sayfadaki her sunucu adı, port ve güvenlik tipi canlı sistemden
     doğrulanarak yazıldı: dovecot 993/995 SSL, postfix 465 SSL ve 587
     STARTTLS dinliyor, sertifika mail/webmail/autoconfig/autodiscover
     adlarını kapsıyor. Sunucunun davranışı değişirse bu metin de
     değişmeli, tersi değil.

     legal.layout paylaşılıyor: Yasal belgeler, Hakkımızda ve SSL
     sayfaları da aynı düzeni kullanıyor.

     Metin çeviri anahtarlarında; değerler
     2026_09_20_110001_seed_mail_setup_page_translations ile gelir.

     Sunucu ayarları tablosunun başlıkları ve satır etiketleri BU SAYFANIN
     anahtarları değil: aynı tablo müşteri panelindeki e-posta sayfasında da
     var ve orada client.hosting.email.settings_* olarak anahtarlanmıştı
     (2026_09_03_090000_seed_client_email_client_settings_keys). Tablonun iki
     kopyası birbirinden ayrı düşebilir, tek kopyası düşemez.

     Sunucu adı ile alan adı kuruluma göre değişiyor (PageController::mailSetup)
     ve cümlelere :host / :domain olarak giriyor. Cümlenin ortasında <strong>
     taşıyan satırlar trans_markup() ile basılıyor: değer önce tamamen
     kaçırılır, sonra yalnızca izin verilen birkaç satır-içi etiket geri konur
     (App\Support\InlineMarkup). Sayfa oturum açmadan görülüyor ve değerler
     çeviri düzenleyicisinden geliyor; düz {!! !!} olsaydı düzenleyiciye
     yazılan bir <script> her ziyaretçide çalışırdı. :host / :domain yerine
     konduktan SONRA kaçırıldıkları için burada ayrıca e() çağrılmıyor.
--}}
@extends('legal.layout')

@php
    // Raw on purpose. These are replaced into the sentence first and the whole
    // sentence is escaped afterwards by trans_markup(); escaping them here as
    // well would print "&amp;" to a customer whose host name carried an "&".
    $host = $mail['host'];
    $domain = $mail['domain'];
@endphp

@section('legal-title', __('client.pages.mail_setup.title'))
@section('legal-description', __('client.pages.mail_setup.description'))

@section('legal-content')
    <div class="legal-head">
        <h1>{{ __('client.pages.mail_setup.title') }}</h1>
        <p>{{ __('client.pages.mail_setup.intro') }}</p>
    </div>

    <div class="legal-grid">
        <nav class="legal-side">
            <p class="legal-side-title">{{ __('client.pages.mail_setup.on_this_page') }}</p>
            <a href="#ayarlar">{{ __('client.pages.mail_setup.settings') }}</a>
            <a href="#otomatik">{{ __('client.pages.mail_setup.automatic') }}</a>
            <a href="#outlook">Outlook</a>
            <a href="#iphone">{{ __('client.pages.mail_setup.iphone') }}</a>
            <a href="#android">Android</a>
            <a href="#thunderbird">Thunderbird</a>
            <a href="#webmail">{{ __('client.pages.mail_setup.webmail') }}</a>
            <a href="#imap-pop3">{{ __('client.pages.mail_setup.imap_pop3') }}</a>
            <a href="#sorunlar">{{ __('client.pages.mail_setup.problems') }}</a>
        </nav>

        <article class="legal-body">
            <h2 id="ayarlar">{{ __('client.pages.mail_setup.settings') }}</h2>

            <p>{{ __('client.pages.mail_setup.settings_intro') }}</p>

            <table>
                <thead>
                    <tr>
                        <th>{{ __('client.hosting.email.settings_purpose') }}</th>
                        <th>{{ __('client.hosting.email.settings_server') }}</th>
                        <th>{{ __('client.hosting.email.settings_port') }}</th>
                        <th>{{ __('client.hosting.email.settings_security') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>IMAP</strong><br><span style="opacity:.7">{{ __('client.hosting.email.settings_incoming_recommended') }}</span></td>
                        <td>{{ $mail['host'] }}</td>
                        <td><strong>993</strong></td>
                        <td>SSL/TLS</td>
                    </tr>
                    <tr>
                        <td><strong>POP3</strong><br><span style="opacity:.7">{{ __('client.hosting.email.settings_incoming_alt') }}</span></td>
                        <td>{{ $mail['host'] }}</td>
                        <td><strong>995</strong></td>
                        <td>SSL/TLS</td>
                    </tr>
                    <tr>
                        <td><strong>SMTP</strong><br><span style="opacity:.7">{{ __('client.hosting.email.settings_outgoing') }}</span></td>
                        <td>{{ $mail['host'] }}</td>
                        <td><strong>465</strong></td>
                        <td>SSL/TLS</td>
                    </tr>
                    <tr>
                        <td><strong>SMTP</strong><br><span style="opacity:.7">{{ __('client.hosting.email.settings_outgoing_alt') }}</span></td>
                        <td>{{ $mail['host'] }}</td>
                        <td><strong>587</strong></td>
                        <td>STARTTLS</td>
                    </tr>
                </tbody>
            </table>

            <ul>
                <li>{{ trans_markup('client.pages.mail_setup.username_note', ['domain' => $domain]) }}</li>
                <li>{{ trans_markup('client.pages.mail_setup.password_note') }}</li>
                <li>{{ trans_markup('client.pages.mail_setup.auth_note') }}</li>
                <li>{{ trans_markup('client.pages.mail_setup.tls_note') }}</li>
            </ul>

            <div class="legal-note">
                <p style="margin:0">{{ trans_markup('client.pages.mail_setup.host_note', ['host' => $host]) }}</p>
            </div>

            <h2 id="otomatik">{{ __('client.pages.mail_setup.automatic') }}</h2>

            <p>{{ trans_markup('client.pages.mail_setup.automatic_p1') }}</p>
            <p>{{ __('client.pages.mail_setup.automatic_p2') }}</p>

            <h2 id="outlook">Outlook</h2>

            <h3>{{ __('client.pages.mail_setup.outlook_new_title') }}</h3>
            <ol>
                <li>{{ trans_markup('client.pages.mail_setup.outlook_new_1') }}</li>
                <li>{{ trans_markup('client.pages.mail_setup.outlook_new_2') }}</li>
                <li>{{ __('client.pages.mail_setup.outlook_new_3') }}</li>
            </ol>
            <p>{{ trans_markup('client.pages.mail_setup.outlook_new_note') }}</p>

            <h3>{{ __('client.pages.mail_setup.outlook_manual_title') }}</h3>
            <ol>
                <li>{{ trans_markup('client.pages.mail_setup.outlook_manual_1') }}</li>
                <li>{{ trans_markup('client.pages.mail_setup.outlook_manual_2') }}</li>
                <li>{{ trans_markup('client.pages.mail_setup.outlook_manual_3') }}</li>
                <li>{{ trans_markup('client.pages.mail_setup.outlook_manual_4', ['host' => $host]) }}</li>
                <li>{{ trans_markup('client.pages.mail_setup.outlook_manual_5', ['host' => $host]) }}</li>
                <li>{{ trans_markup('client.pages.mail_setup.outlook_manual_6') }}</li>
            </ol>
            <p>{{ trans_markup('client.pages.mail_setup.outlook_manual_note') }}</p>

            <h2 id="iphone">{{ __('client.pages.mail_setup.iphone') }}</h2>

            <ol>
                <li>{{ trans_markup('client.pages.mail_setup.iphone_1') }}</li>
                <li>{{ trans_markup('client.pages.mail_setup.iphone_2') }}</li>
                <li>{{ trans_markup('client.pages.mail_setup.iphone_3') }}</li>
                <li>{{ trans_markup('client.pages.mail_setup.iphone_4') }}</li>
                <li>{{ trans_markup('client.pages.mail_setup.iphone_5', ['host' => $host]) }}</li>
                <li>{{ trans_markup('client.pages.mail_setup.iphone_6') }}</li>
            </ol>
            <p>{{ trans_markup('client.pages.mail_setup.iphone_note') }}</p>

            <h2 id="android">Android</h2>

            <p>{{ __('client.pages.mail_setup.android_intro') }}</p>
            <ol>
                <li>{{ trans_markup('client.pages.mail_setup.android_1') }}</li>
                <li>{{ trans_markup('client.pages.mail_setup.android_2') }}</li>
                <li>{{ trans_markup('client.pages.mail_setup.android_3') }}</li>
                <li>{{ __('client.pages.mail_setup.android_4') }}</li>
                <li>{{ trans_markup('client.pages.mail_setup.android_5', ['host' => $host]) }}</li>
                <li>{{ trans_markup('client.pages.mail_setup.android_6', ['host' => $host]) }}</li>
            </ol>

            <h2 id="thunderbird">Thunderbird</h2>

            <ol>
                <li>{{ trans_markup('client.pages.mail_setup.thunderbird_1') }}</li>
                <li>{{ trans_markup('client.pages.mail_setup.thunderbird_2') }}</li>
                <li>{{ trans_markup('client.pages.mail_setup.thunderbird_3') }}</li>
            </ol>

            <h2 id="webmail">{{ __('client.pages.mail_setup.webmail') }}</h2>

            <p>{{ __('client.pages.mail_setup.webmail_p1') }}</p>
            {{-- Kaçırma trans_markup()'ın içinde bir kez yapılıyor. --}}
            <p>{{ trans_markup('client.pages.mail_setup.webmail_p2', [
                'link' => '<a href="'.$mail['webmail'].'" target="_blank" rel="noopener">'.$mail['webmail_label'].'</a>',
            ]) }}</p>

            <h2 id="imap-pop3">{{ __('client.pages.mail_setup.imap_pop3') }}</h2>

            <p>{{ trans_markup('client.pages.mail_setup.imap_p1') }}</p>
            <p>{{ trans_markup('client.pages.mail_setup.pop3_p1') }}</p>
            <p>{{ __('client.pages.mail_setup.quota_p1') }}</p>

            <h2 id="sorunlar">{{ __('client.pages.mail_setup.problems') }}</h2>

            <h3>{{ __('client.pages.mail_setup.problem_send_title') }}</h3>
            <p>{{ __('client.pages.mail_setup.problem_send_p1') }}</p>
            <p>{{ trans_markup('client.pages.mail_setup.problem_send_p2') }}</p>

            <h3>{{ __('client.pages.mail_setup.problem_auth_title') }}</h3>
            <p>{{ trans_markup('client.pages.mail_setup.problem_auth_p1', ['domain' => $domain]) }}</p>

            <h3>{{ __('client.pages.mail_setup.problem_cert_title') }}</h3>
            <p>{{ trans_markup('client.pages.mail_setup.problem_cert_p1', ['host' => $host]) }}</p>

            <h3>{{ __('client.pages.mail_setup.problem_sync_title') }}</h3>
            <p>{{ __('client.pages.mail_setup.problem_sync_p1') }}</p>

            <h3>{{ __('client.pages.mail_setup.problem_spam_title') }}</h3>
            <p>{{ __('client.pages.mail_setup.problem_spam_p1') }}</p>

            <p style="margin-top:32px">{{ __('client.pages.mail_setup.footer_note') }}</p>
        </article>
    </div>
@endsection
