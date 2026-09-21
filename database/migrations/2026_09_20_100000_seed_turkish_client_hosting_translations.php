<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Turkish for the client area: the 289 keys in the "client" group that were
 * only ever seeded in English.
 *
 * Roughly forty migrations seed dynamic_translations and almost all of them
 * insert English alone - 2026_08_13_130000_seed_client_hosting_dashboard_translations
 * is the shape they all share. The table therefore held 365 English rows
 * against 56 Turkish ones, and a Turkish customer read English on every
 * hosting screen: backups, apps, cron, databases, DNS, files, FTP and
 * subdomains. The language files are not the problem - lang/tr carries all
 * 4471 of lang/en's keys - these strings simply never had a file to live in.
 *
 * Three of them are the reported bug. "5 websites", "5 containers" and
 * "Run up to 5 apps" fell through to the English plural on a Turkish page.
 * Turkish does not pluralise with -s, and after a number it takes no plural
 * suffix at all: "5 web sitesi", never "5 web siteleri".
 *
 * GLOSSARY - one Turkish word per concept, used everywhere here and matching
 * what lang/tr and the existing Turkish rows already say:
 *   app, application ....... uygulama        (lang/tr/client.php: cart.app_count)
 *   backup ................. yedek
 *   restore point .......... geri yükleme noktası
 *   container .............. konteyner       (component -> bileşen, as seeded
 *                                             by 2026_08_27_220000)
 *   plan ................... paket           (lang/tr: "Şu anda kullandığınız paket")
 *   domain ................. alan adı        (label case: "Alan Adı")
 *   subdomain .............. alt alan adı
 *   service (the purchase) . hizmet
 *   mailbox ................ posta kutusu    (2026_09_03_090000)
 *   hosting panel .......... hosting kontrol paneli (2026_08_28_130000)
 *   record (DNS) ........... kayıt
 *   memory ................. bellek          ("RAM" only where English says RAM)
 *   Cancel/Delete/Create/Save/Edit/Upload/Download/Copy/Start
 *                    ....... İptal/Sil/Oluştur/Kaydet/Düzenle/Yükle/İndir/
 *                            Kopyala/Başlat  (lang/tr/common.php)
 * Product and protocol names stay as they are: Outlook, IMAP, SMTP, SSL, cron,
 * FTP, FTPS, SFTP, phpMyAdmin, Docker, WordPress, Laravel, Node.js, Python,
 * PHP, MySQL, DNS, TTL, CPU, vCPU, RAM, SSD, n8n.
 *
 * The customer is addressed politely throughout (-iniz), which is the form the
 * rows already in the table use.
 *
 * TURKISH ONLY. Every English row here was inserted by one of the migrations
 * above and is not ours to touch; re-inserting it would only mean down() could
 * not tell it apart from theirs. The insert is guarded by exists() so a
 * Turkish string an operator has already saved in the editor is never
 * overwritten, and down() removes exactly the rows this migration adds - the
 * same shape as 2026_08_28_130000_translate_runtime_app_keys_tr_pl_zh.
 *
 * Cached groups are flushed so the strings appear without waiting for the TTL
 * (see 2026_08_13_150000_flush_stale_translation_cache).
 */
return new class extends Migration
{
    private const GROUP = 'client';

    /** [key => [en, tr]] - the English is the source that was translated. */
    private function rows(): array
    {
        return [
            // Cart: the "start with an app" step.
            'cart.app_clear' => ['clear selection', 'seçimi kaldır'],
            'cart.app_intro' => [
                'You are buying the hosting above. If you already know what you want to run, pick it here and it will be installed and pointed at your domain when your account is created - otherwise skip this and install whatever you like from your control panel afterwards.',
                'Yukarıdaki hosting paketini satın alıyorsunuz. Ne çalıştıracağınızı şimdiden biliyorsanız uygulamayı buradan seçin; hesabınız açılırken kurulur ve alan adınıza yönlendirilir. Kararsızsanız bu adımı atlayın, dilediğiniz uygulamayı sonradan kontrol panelinizden kurabilirsiniz.',
            ],
            'cart.app_not_available' => ['That app is not available on this plan.', 'Bu uygulama seçtiğiniz pakette kullanılamıyor.'],
            'cart.app_optional' => ['optional', 'isteğe bağlı'],
            'cart.choose_app' => ['Start with an app', 'Bir uygulamayla başlayın'],

            // Hosting > Backups.
            'hosting.backups.all_domains' => ['All my domains', 'Tüm alan adlarım'],
            'hosting.backups.contents' => ['Contents', 'İçerik'],
            'hosting.backups.create' => ['Create backup', 'Yedek oluştur'],
            'hosting.backups.create_hint' => [
                'A backup can take a few minutes depending on the size of your sites. It will appear in the list below once it finishes.',
                'Yedekleme, sitelerinizin boyutuna göre birkaç dakika sürebilir. Tamamlandığında aşağıdaki listede görünür.',
            ],
            'hosting.backups.create_title' => ['Create Backup', 'Yedek Oluştur'],
            'hosting.backups.created' => ['Created', 'Oluşturulma'],
            'hosting.backups.delete' => ['Delete', 'Sil'],
            'hosting.backups.delete_confirm' => ['Delete this backup? This cannot be undone.', 'Bu yedek silinsin mi? Bu işlem geri alınamaz.'],
            'hosting.backups.download_hint' => ['Download from your hosting panel', 'Hosting kontrol panelinizden indirin'],
            'hosting.backups.empty' => ['No backups yet.', 'Henüz yedek yok.'],
            'hosting.backups.encrypted' => ['Encrypted', 'Şifreli'],
            'hosting.backups.full' => ['Full', 'Tam'],
            'hosting.backups.incremental' => ['Incremental', 'Artımlı'],
            'hosting.backups.name' => ['Label (optional)', 'Etiket (isteğe bağlı)'],
            'hosting.backups.name_ph' => ['Before theme update', 'Tema güncellemesinden önce'],
            'hosting.backups.no_domains' => ['No domains on this service yet.', 'Bu hizmette henüz alan adı yok.'],
            'hosting.backups.plan_disabled' => ['Backups are not included in your current plan.', 'Yedekleme, mevcut paketinize dahil değil.'],
            // Rendered after a number: "3 geri yükleme noktası", no plural suffix.
            'hosting.backups.points' => ['restore points', 'geri yükleme noktası'],
            'hosting.backups.restore_hint' => [
                'To download or restore a backup, open your hosting panel — archives can be several gigabytes, and restoring overwrites current files and databases, so both are done there.',
                'Bir yedeği indirmek veya geri yüklemek için hosting kontrol panelinizi açın — arşivler birkaç gigabayt olabilir ve geri yükleme mevcut dosyalarınızın ve veritabanlarınızın üzerine yazar, bu yüzden ikisi de orada yapılır.',
            ],
            'hosting.backups.restore_points' => ['Restore points', 'Geri yükleme noktaları'],
            'hosting.backups.scope' => ['What to back up', 'Neler yedeklensin'],
            'hosting.backups.size' => ['Size', 'Boyut'],
            'hosting.backups.subtitle' => ['Restore points for your sites.', 'Sitelerinizin geri yükleme noktaları.'],
            'hosting.backups.title' => ['Backups', 'Yedekler'],

            // Hosting > Apps (Docker containers).
            'hosting.containers.access_title' => ['Connection details', 'Bağlantı bilgileri'],
            'hosting.containers.access_url' => ['Address', 'Adres'],
            'hosting.containers.app' => ['App', 'Uygulama'],
            'hosting.containers.browse_all' => ['Browse all apps', 'Tüm uygulamalara göz at'],
            'hosting.containers.cancel' => ['Cancel', 'İptal'],
            'hosting.containers.collapse' => ['Show less', 'Daha az göster'],
            'hosting.containers.copy' => ['Copy', 'Kopyala'],
            'hosting.containers.crashing' => ['Not starting', 'Başlamıyor'],
            'hosting.containers.crashing_hint' => [
                'The app keeps restarting, usually because it needs configuration or more memory than your plan allows. Remove it and try a smaller one, or open a ticket.',
                'Uygulama sürekli yeniden başlıyor; genellikle ya yapılandırılması gerekir ya da paketinizin verdiğinden fazla belleğe ihtiyaç duyar. Kaldırıp daha küçük bir uygulama deneyin veya destek talebi açın.',
            ],
            'hosting.containers.delete' => ['Remove', 'Kaldır'],
            'hosting.containers.delete_confirm' => [
                'Remove this app? Its data will be deleted and cannot be recovered.',
                'Bu uygulama kaldırılsın mı? Tüm verileri silinir ve geri getirilemez.',
            ],
            'hosting.containers.domain' => ['Domain', 'Alan Adı'],
            'hosting.containers.domain_link' => ['Point here', 'Buraya yönlendir'],
            'hosting.containers.domain_needs_running' => [
                'Start the app to serve it on one of your domains.',
                'Uygulamayı alan adlarınızdan birinde yayınlamak için önce başlatın.',
            ],
            'hosting.containers.domain_none' => [
                'Add a domain to this account to serve this app on it.',
                'Bu uygulamayı bir alan adında yayınlamak için hesabınıza alan adı ekleyin.',
            ],
            'hosting.containers.domain_unlink' => ['Stop serving this app on this domain', 'Bu uygulamayı bu alan adında yayınlamayı durdur'],
            'hosting.containers.empty' => ['No apps installed yet.', 'Henüz kurulu uygulama yok.'],
            'hosting.containers.group_ai' => ['AI & Automation', 'Yapay Zekâ ve Otomasyon'],
            'hosting.containers.group_ai_hint' => ['Run models and automate work', 'Model çalıştırın, işlerinizi otomatikleştirin'],
            'hosting.containers.group_databases' => ['Databases & Search', 'Veritabanları ve Arama'],
            'hosting.containers.group_databases_hint' => ['Store and query your data', 'Verilerinizi saklayın ve sorgulayın'],
            'hosting.containers.group_desktops' => ['Desktops & Browsers', 'Masaüstleri ve Tarayıcılar'],
            'hosting.containers.group_desktops_hint' => ['A full desktop or browser in your account', 'Hesabınızın içinde tam bir masaüstü veya tarayıcı'],
            'hosting.containers.group_devtools' => ['Developer Tools', 'Geliştirici Araçları'],
            'hosting.containers.group_devtools_hint' => ['Code, build and run your own projects', 'Kendi projelerinizi yazın, derleyin ve çalıştırın'],
            'hosting.containers.group_files' => ['Files & Media', 'Dosyalar ve Medya'],
            'hosting.containers.group_files_hint' => ['Store, share and stream files', 'Dosyalarınızı saklayın, paylaşın ve yayınlayın'],
            'hosting.containers.group_monitoring' => ['Monitoring & Analytics', 'İzleme ve Analiz'],
            'hosting.containers.group_monitoring_hint' => ['Watch traffic, uptime and usage', 'Trafiği, çalışma süresini ve kullanımı izleyin'],
            'hosting.containers.group_network' => ['Network & Security', 'Ağ ve Güvenlik'],
            'hosting.containers.group_network_hint' => ['Proxies, VPNs and protection', 'Proxy, VPN ve koruma çözümleri'],
            'hosting.containers.group_other' => ['Other Apps', 'Diğer Uygulamalar'],
            'hosting.containers.group_other_hint' => ['Everything else in the catalogue', 'Katalogdaki diğer her şey'],
            'hosting.containers.group_team' => ['Team & Communication', 'Ekip ve İletişim'],
            'hosting.containers.group_team_hint' => ['Chat, wikis, notes and support', 'Sohbet, wiki, not ve destek uygulamaları'],
            'hosting.containers.group_websites' => ['Websites & CMS', 'Web Siteleri ve CMS'],
            'hosting.containers.group_websites_hint' => ['Publish a site, blog or shop', 'Site, blog veya mağaza yayınlayın'],
            'hosting.containers.install' => ['Install', 'Kur'],
            'hosting.containers.install_hint' => [
                'The app runs inside your account: its CPU and memory come out of your plan, and its files are stored in your home directory and count towards your disk quota.',
                'Uygulama hesabınızın içinde çalışır: CPU ve belleğini paketinizden kullanır, dosyaları ana dizininizde tutulur ve disk kotanıza sayılır.',
            ],
            'hosting.containers.install_title' => ['Install an App', 'Uygulama Kur'],
            'hosting.containers.installing' => ['Installing...', 'Kuruluyor...'],
            'hosting.containers.installing_note' => [
                'This downloads the app and can take a few minutes for larger ones. You can leave this page - the install carries on.',
                'Uygulama indirilir; büyük uygulamalarda bu birkaç dakika sürebilir. Bu sayfadan ayrılabilirsiniz, kurulum devam eder.',
            ],
            'hosting.containers.limit_reached' => ["You have reached your plan's app limit.", 'Paketinizin uygulama sınırına ulaştınız.'],
            'hosting.containers.name' => ['Name (optional)', 'Ad (isteğe bağlı)'],
            'hosting.containers.name_ph' => ['my-blog', 'blogum'],
            'hosting.containers.needs_cpu' => ['CPU this app needs', 'Bu uygulamanın ihtiyaç duyduğu CPU'],
            'hosting.containers.needs_light' => ['Light', 'Hafif'],
            'hosting.containers.needs_ram' => ['Memory this app needs', 'Bu uygulamanın ihtiyaç duyduğu bellek'],
            'hosting.containers.no_apps' => ['No apps are available on your plan yet.', 'Paketinizde kullanılabilir uygulama yok.'],
            'hosting.containers.not_your_app' => ['That app does not belong to this service.', 'Bu uygulama bu hizmete ait değil.'],
            'hosting.containers.open_app' => ['Open app', 'Uygulamayı aç'],
            'hosting.containers.open_panel' => ['Open the hosting panel', 'Hosting kontrol panelini aç'],
            'hosting.containers.over_plan' => ['Needs more memory than your plan', 'Paketinizin verdiğinden fazla bellek gerekiyor'],
            'hosting.containers.panel_hint' => [
                'A terminal, logs and advanced settings for each app live in your hosting panel.',
                'Her uygulamanın terminali, günlükleri ve gelişmiş ayarları hosting kontrol panelinizde bulunur.',
            ],
            'hosting.containers.plan_ceiling' => [
                'Every app runs inside your plan: up to :ram of memory and :cpu CPU, shared by all your apps.',
                'Her uygulama paketinizin içinde çalışır: en fazla :ram bellek ve :cpu CPU, tüm uygulamalarınız arasında paylaşılır.',
            ],
            'hosting.containers.plan_disabled' => ['Apps are not included in your current plan.', 'Uygulamalar mevcut paketinize dahil değil.'],
            'hosting.containers.popular' => ['Popular choice', 'Popüler seçim'],
            'hosting.containers.ports' => ['Ports', 'Portlar'],
            'hosting.containers.resources' => ['Resources', 'Kaynaklar'],
            'hosting.containers.restart' => ['Restart', 'Yeniden başlat'],
            'hosting.containers.running' => ['Running', 'Çalışıyor'],
            'hosting.containers.running_title' => ['Your Apps', 'Uygulamalarınız'],
            'hosting.containers.search_clear' => ['Clear search', 'Aramayı temizle'],
            'hosting.containers.search_none' => ['No app matches that search.', 'Bu aramaya uyan uygulama yok.'],
            'hosting.containers.search_ph' => ['Search apps - try wordpress, database, backup...', 'Uygulama ara - wordpress, veritabanı, yedekleme...'],
            // Plural. Turkish takes no plural suffix after a number:
            // "1 konteyner", "3 konteyner".
            'hosting.containers.services' => ['{1} 1 container|[2,*] :count containers', '{1} 1 konteyner|[2,*] :count konteyner'],
            'hosting.containers.services_hint' => [
                "This app runs more than one container - a database, a cache or a worker beside it. They all share your plan's memory and CPU.",
                'Bu uygulama birden fazla konteyner çalıştırır: yanında bir veritabanı, önbellek veya işçi süreç bulunur. Hepsi paketinizin belleğini ve CPU\'sunu paylaşır.',
            ],
            'hosting.containers.showing' => [':count apps', ':count uygulama'],
            'hosting.containers.start' => ['Start', 'Başlat'],
            'hosting.containers.stop' => ['Stop', 'Durdur'],
            'hosting.containers.stopped' => ['Stopped', 'Durduruldu'],
            'hosting.containers.subtitle' => ['Install and run applications on your hosting.', 'Hostinginizde uygulama kurun ve çalıştırın.'],
            'hosting.containers.terminal' => ['Terminal', 'Terminal'],
            'hosting.containers.terminal_default' => ['Open shell', 'Kabuk aç'],
            'hosting.containers.terminal_root' => ['As root', 'root olarak'],
            'hosting.containers.title' => ['Apps', 'Uygulamalar'],
            'hosting.containers.unlimited' => ['no limit', 'sınırsız'],

            // Hosting > Cron jobs.
            'hosting.cron.advanced' => ['Advanced', 'Gelişmiş'],
            'hosting.cron.basic' => ['Common', 'Sık kullanılan'],
            'hosting.cron.command' => ['Command', 'Komut'],
            'hosting.cron.command_hint' => [
                'Runs as your account user, isolated to your home. Use /usr/local/bin/php (or php81–php85) and ~ for your home directory.',
                'Hesap kullanıcınız olarak, ana dizininize kapalı biçimde çalışır. /usr/local/bin/php (veya php81–php85) kullanın; ana dizininiz için ~ yazabilirsiniz.',
            ],
            // A command example: identical in every language.
            'hosting.cron.command_ph' => [
                '/usr/local/bin/php ~/{domain}/public_html/artisan schedule:run',
                '/usr/local/bin/php ~/{domain}/public_html/artisan schedule:run',
            ],
            'hosting.cron.create' => ['Create', 'Oluştur'],
            'hosting.cron.create_title' => ['Create Cron Job', 'Cron Görevi Oluştur'],
            'hosting.cron.delete' => ['Delete', 'Sil'],
            'hosting.cron.delete_confirm' => ['Delete this cron job?', 'Bu cron görevi silinsin mi?'],
            'hosting.cron.disable' => ['Pause', 'Duraklat'],
            'hosting.cron.disabled' => ['Paused', 'Duraklatıldı'],
            'hosting.cron.dom' => ['Day', 'Gün'],
            'hosting.cron.domain' => ['Domain', 'Alan Adı'],
            'hosting.cron.dow' => ['Weekday', 'Haftanın günü'],
            'hosting.cron.email_on_error' => ['Email me on error', 'Hata olursa bana e-posta gönder'],
            'hosting.cron.email_ph' => ['you@example.com (optional)', 'siz@ornek.com (isteğe bağlı)'],
            'hosting.cron.empty' => ['No cron jobs yet.', 'Henüz cron görevi yok.'],
            'hosting.cron.enable' => ['Enable', 'Etkinleştir'],
            'hosting.cron.enabled' => ['Active', 'Etkin'],
            'hosting.cron.ex.laravel' => ['Laravel scheduler', 'Laravel zamanlayıcı'],
            'hosting.cron.ex.php' => ['PHP script', 'PHP betiği'],
            'hosting.cron.ex.phpver' => ['PHP 8.3 script', 'PHP 8.3 betiği'],
            'hosting.cron.ex.url' => ['Fetch a URL', 'Bir URL çağır'],
            'hosting.cron.ex.wp' => ['WordPress cron', 'WordPress cron'],
            'hosting.cron.examples' => ['Examples:', 'Örnekler:'],
            'hosting.cron.hr' => ['Hour', 'Saat'],
            'hosting.cron.limit_reached' => ["You have reached your plan's cron job limit.", 'Paketinizin cron görevi sınırına ulaştınız.'],
            'hosting.cron.min' => ['Min', 'Dk'],
            'hosting.cron.mon' => ['Month', 'Ay'],
            'hosting.cron.no_domains' => ['No domains on this service yet.', 'Bu hizmette henüz alan adı yok.'],
            'hosting.cron.no_output' => ['(no output)', '(çıktı yok)'],
            'hosting.cron.output' => ['Output', 'Çıktı'],
            'hosting.cron.p.daily' => ['Daily (midnight)', 'Her gün (gece yarısı)'],
            'hosting.cron.p.every15' => ['Every 15 minutes', '15 dakikada bir'],
            'hosting.cron.p.every30' => ['Every 30 minutes', '30 dakikada bir'],
            'hosting.cron.p.every5' => ['Every 5 minutes', '5 dakikada bir'],
            'hosting.cron.p.everyMinute' => ['Every minute', 'Her dakika'],
            'hosting.cron.p.hourly' => ['Hourly', 'Saatte bir'],
            'hosting.cron.p.monthly' => ['Monthly (1st)', 'Her ay (ayın 1\'i)'],
            'hosting.cron.p.weekly' => ['Weekly (Sunday)', 'Her hafta (Pazar)'],
            'hosting.cron.plan_disabled' => ['Cron jobs are not included in your current plan.', 'Cron görevleri mevcut paketinize dahil değil.'],
            'hosting.cron.run_now' => ['Run now', 'Şimdi çalıştır'],
            'hosting.cron.schedule' => ['Schedule', 'Zamanlama'],
            'hosting.cron.subtitle' => ['Schedule commands to run automatically.', 'Komutlarınızı otomatik çalışacak şekilde zamanlayın.'],
            'hosting.cron.task' => ['Task', 'Görev'],
            'hosting.cron.task_name' => ['Task name', 'Görev adı'],
            'hosting.cron.task_name_ph' => ['Nightly backup', 'Gecelik yedek'],
            'hosting.cron.title' => ['Cron Jobs', 'Cron Görevleri'],

            // Hosting > Service dashboard.
            'hosting.dashboard.as_of' => ['as of', 'son güncelleme'],
            'hosting.dashboard.cpu' => ['CPU', 'CPU'],
            'hosting.dashboard.domains' => ['Domains', 'Alan Adları'],
            'hosting.dashboard.no_domains' => ['No domains yet.', 'Henüz alan adı yok.'],
            'hosting.dashboard.ram' => ['Memory', 'Bellek'],

            // Hosting > Databases.
            'hosting.databases.add_user' => ['Add User', 'Kullanıcı Ekle'],
            'hosting.databases.change_password' => ['Password', 'Parola'],
            'hosting.databases.create' => ['Create', 'Oluştur'],
            'hosting.databases.create_title' => ['Create Database', 'Veritabanı Oluştur'],
            'hosting.databases.db_name' => ['Database name', 'Veritabanı adı'],
            'hosting.databases.db_user' => ['Username', 'Kullanıcı adı'],
            'hosting.databases.delete' => ['Delete', 'Sil'],
            'hosting.databases.delete_db_confirm' => [
                'Delete this database and all its data? This cannot be undone.',
                'Bu veritabanı ve içindeki tüm veriler silinsin mi? Bu işlem geri alınamaz.',
            ],
            'hosting.databases.delete_user_confirm' => ['Delete this database user?', 'Bu veritabanı kullanıcısı silinsin mi?'],
            'hosting.databases.domain' => ['Domain', 'Alan Adı'],
            'hosting.databases.empty' => ['No databases yet.', 'Henüz veritabanı yok.'],
            'hosting.databases.new_password' => ['New password', 'Yeni parola'],
            'hosting.databases.new_user' => ['Username', 'Kullanıcı adı'],
            'hosting.databases.no_domains' => ['No domains on this service yet.', 'Bu hizmette henüz alan adı yok.'],
            'hosting.databases.password' => ['Password', 'Parola'],
            'hosting.databases.phpmyadmin' => ['phpMyAdmin', 'phpMyAdmin'],
            'hosting.databases.primary' => ['primary', 'birincil'],
            'hosting.databases.role' => ['Role', 'Rol'],
            'hosting.databases.save' => ['Save', 'Kaydet'],
            'hosting.databases.subtitle' => ['MySQL databases and users for your domains.', 'Alan adlarınız için MySQL veritabanları ve kullanıcıları.'],
            'hosting.databases.title' => ['Databases', 'Veritabanları'],

            // Hosting > DNS zone.
            'hosting.dns.cancel' => ['Cancel', 'İptal'],
            'hosting.dns.create' => ['Add', 'Ekle'],
            'hosting.dns.create_title' => ['Add DNS Record', 'DNS Kaydı Ekle'],
            'hosting.dns.delete' => ['Delete', 'Sil'],
            'hosting.dns.delete_confirm' => [
                'Delete this DNS record? Changes can take time to propagate.',
                'Bu DNS kaydı silinsin mi? Değişikliklerin yayılması zaman alabilir.',
            ],
            'hosting.dns.domain' => ['Domain', 'Alan Adı'],
            'hosting.dns.edit' => ['Edit', 'Düzenle'],
            'hosting.dns.empty' => ['No DNS records yet.', 'Henüz DNS kaydı yok.'],
            'hosting.dns.name' => ['Name', 'Ad'],
            'hosting.dns.name_hint' => [
                'Use @ for the domain itself, or a subdomain name such as www. DNS changes may take up to a few hours to propagate.',
                'Alan adının kendisi için @ yazın, ya da www gibi bir alt alan adı girin. DNS değişikliklerinin yayılması birkaç saati bulabilir.',
            ],
            'hosting.dns.no_domains' => ['No domains on this service yet.', 'Bu hizmette henüz alan adı yok.'],
            'hosting.dns.priority' => ['Priority', 'Öncelik'],
            'hosting.dns.protected' => ['Managed', 'Yönetilen'],
            'hosting.dns.protected_hint' => [
                'This record keeps your site and mail reachable and is managed by the hosting platform.',
                'Bu kayıt sitenizin ve postanızın erişilebilir kalmasını sağlar; hosting platformu tarafından yönetilir.',
            ],
            // Rendered after a number: "12 kayıt", no plural suffix.
            'hosting.dns.records' => ['records', 'kayıt'],
            'hosting.dns.records_of' => ['Zone records', 'Bölge kayıtları'],
            'hosting.dns.save' => ['Save', 'Kaydet'],
            'hosting.dns.subtitle' => ['Manage the DNS records for your domains.', 'Alan adlarınızın DNS kayıtlarını yönetin.'],
            'hosting.dns.title' => ['DNS Zone', 'DNS Bölgesi'],
            'hosting.dns.ttl' => ['TTL', 'TTL'],
            'hosting.dns.type' => ['Type', 'Tür'],
            'hosting.dns.value' => ['Value', 'Değer'],
            'hosting.dns.zone' => ['Zone', 'Bölge'],

            // Hosting > File manager.
            'hosting.files.cancel' => ['Cancel', 'İptal'],
            'hosting.files.create' => ['Create', 'Oluştur'],
            'hosting.files.delete' => ['Delete', 'Sil'],
            'hosting.files.delete_confirm' => ['Delete this item? It will be moved to trash.', 'Bu öğe silinsin mi? Çöp kutusuna taşınacak.'],
            'hosting.files.download' => ['Download', 'İndir'],
            'hosting.files.download_failed' => ['Could not download that file.', 'Bu dosya indirilemedi.'],
            'hosting.files.drop_here' => ['Drop files here to upload', 'Yüklemek için dosyaları buraya bırakın'],
            'hosting.files.edit' => ['Edit', 'Düzenle'],
            'hosting.files.empty' => ['This folder is empty.', 'Bu klasör boş.'],
            'hosting.files.file_name' => ['File name', 'Dosya adı'],
            'hosting.files.folder_name' => ['Folder name', 'Klasör adı'],
            // Rendered after a number: "42 öğe", no plural suffix.
            'hosting.files.items' => ['items', 'öğe'],
            'hosting.files.load_failed' => ['Could not load this folder.', 'Bu klasör açılamadı.'],
            'hosting.files.modified' => ['Modified', 'Değiştirilme'],
            'hosting.files.name' => ['Name', 'Ad'],
            'hosting.files.new_file' => ['New File', 'Yeni Dosya'],
            'hosting.files.new_folder' => ['New Folder', 'Yeni Klasör'],
            'hosting.files.new_name' => ['New name', 'Yeni ad'],
            'hosting.files.permissions' => ['Perms', 'İzin'],
            'hosting.files.rename' => ['Rename', 'Yeniden adlandır'],
            'hosting.files.save' => ['Save', 'Kaydet'],
            'hosting.files.size' => ['Size', 'Boyut'],
            'hosting.files.subtitle' => [
                'Browse and manage the files in your hosting account.',
                'Hosting hesabınızdaki dosyaları görüntüleyin ve yönetin.',
            ],
            'hosting.files.title' => ['File Manager', 'Dosya Yöneticisi'],
            'hosting.files.upload' => ['Upload', 'Yükle'],
            'hosting.files.upload_done' => ['Done', 'Tamamlandı'],
            'hosting.files.uploading' => ['Uploading…', 'Yükleniyor…'],

            // Hosting > FTP.
            'hosting.ftp.accounts' => ['FTP Accounts', 'FTP Hesapları'],
            'hosting.ftp.change_password' => ['Password', 'Parola'],
            'hosting.ftp.connection' => ['Connection Details', 'Bağlantı Bilgileri'],
            'hosting.ftp.create' => ['Create', 'Oluştur'],
            'hosting.ftp.create_title' => ['Create FTP Account', 'FTP Hesabı Oluştur'],
            'hosting.ftp.delete' => ['Delete', 'Sil'],
            'hosting.ftp.delete_confirm' => ['Delete this FTP account?', 'Bu FTP hesabı silinsin mi?'],
            'hosting.ftp.directory' => ['Access directory', 'Erişim dizini'],
            'hosting.ftp.empty' => ['No FTP accounts yet.', 'Henüz FTP hesabı yok.'],
            'hosting.ftp.home' => ['Directory', 'Dizin'],
            'hosting.ftp.home_default' => ['Account home (all files)', 'Hesap ana dizini (tüm dosyalar)'],
            'hosting.ftp.host' => ['Host', 'Sunucu'],
            'hosting.ftp.limit_reached' => ["You have reached your plan's FTP account limit.", 'Paketinizin FTP hesabı sınırına ulaştınız.'],
            'hosting.ftp.new_password' => ['New password', 'Yeni parola'],
            'hosting.ftp.password' => ['Password', 'Parola'],
            'hosting.ftp.plan_disabled' => ['Your plan does not include FTP account creation.', 'Paketiniz FTP hesabı oluşturmayı içermiyor.'],
            'hosting.ftp.port' => ['Port', 'Port'],
            'hosting.ftp.protocol' => ['Protocol', 'Protokol'],
            'hosting.ftp.protocol_hint' => [
                'Connect with FTP or FTPS on port 21 — not SFTP (SFTP uses port 22 and a different login).',
                '21 numaralı porttan FTP veya FTPS ile bağlanın — SFTP ile değil (SFTP 22 numaralı portu ve farklı bir oturum bilgisini kullanır).',
            ],
            'hosting.ftp.quota_mb' => ['Quota (MB)', 'Kota (MB)'],
            'hosting.ftp.save' => ['Save', 'Kaydet'],
            'hosting.ftp.subtitle' => ['Manage FTP access to your hosting files.', 'Hosting dosyalarınıza FTP erişimini yönetin.'],
            'hosting.ftp.title' => ['FTP Accounts', 'FTP Hesapları'],
            'hosting.ftp.usage' => ['Usage', 'Kullanım'],
            'hosting.ftp.username' => ['Username', 'Kullanıcı adı'],

            // Hosting > Subdomains.
            'hosting.subdomains.create' => ['Create', 'Oluştur'],
            'hosting.subdomains.create_title' => ['Create Subdomain', 'Alt Alan Adı Oluştur'],
            'hosting.subdomains.delete' => ['Delete', 'Sil'],
            'hosting.subdomains.delete_confirm' => [
                'Delete this subdomain? Its files and configuration will be removed.',
                'Bu alt alan adı silinsin mi? Dosyaları ve yapılandırması kaldırılacak.',
            ],
            'hosting.subdomains.document_root' => ['Document root', 'Belge kökü'],
            'hosting.subdomains.domain' => ['Domain', 'Alan Adı'],
            'hosting.subdomains.empty' => ['No subdomains yet.', 'Henüz alt alan adı yok.'],
            'hosting.subdomains.full_name' => ['Subdomain', 'Alt Alan Adı'],
            'hosting.subdomains.limit_reached' => ["You have reached your plan's subdomain limit.", 'Paketinizin alt alan adı sınırına ulaştınız.'],
            'hosting.subdomains.name' => ['Subdomain', 'Alt Alan Adı'],
            'hosting.subdomains.no_domains' => ['No domains on this service yet.', 'Bu hizmette henüz alan adı yok.'],
            'hosting.subdomains.php' => ['PHP', 'PHP'],
            'hosting.subdomains.ssl' => ['Enable SSL', 'SSL\'i etkinleştir'],
            'hosting.subdomains.ssl_hint' => [
                'If the parent domain has SSL, the subdomain inherits it even when this is off.',
                'Üst alan adında SSL varsa, bu seçenek kapalı olsa bile alt alan adı SSL\'i devralır.',
            ],
            'hosting.subdomains.subtitle' => ['Create subdomains under your domains.', 'Alan adlarınızın altında alt alan adları oluşturun.'],
            'hosting.subdomains.title' => ['Subdomains', 'Alt Alan Adları'],

            // Hosting: section heading, tool links and the shared refusal.
            'hosting.title' => ['Hosting Management', 'Hosting Yönetimi'],
            'hosting.tools.apps' => ['Apps', 'Uygulamalar'],
            'hosting.tools.databases' => ['Databases', 'Veritabanları'],
            'hosting.tools.emails' => ['Email', 'E-posta'],
            'hosting.tools.files' => ['Files', 'Dosyalar'],
            'hosting.tools.laravel' => ['Laravel', 'Laravel'],
            'hosting.tools.nodejs' => ['Node.js', 'Node.js'],
            'hosting.tools.python' => ['Python', 'Python'],
            'hosting.unavailable' => ['This action is not available for this service.', 'Bu işlem bu hizmet için kullanılamıyor.'],

            // Client navigation. Matches lang/tr/client.php (nav.home_site).
            'nav.home_site' => ['Home', 'Anasayfa'],

            // Store: what a plan includes.
            'store.included_resources' => ['What this plan includes', 'Bu pakete dahil olanlar'],
            // Plural. "1 uygulama", "5 uygulamaya kadar" - no plural suffix
            // after the number.
            'store.res_apps' => ['{1} Run 1 app|[2,*] Run up to :count apps', '{1} 1 uygulama çalıştırın|[2,*] :count uygulamaya kadar çalıştırın'],
            'store.res_bandwidth' => [':value bandwidth', ':value trafik'],
            'store.res_cpu' => [':value vCPU', ':value vCPU'],
            'store.res_disk' => [':value SSD storage', ':value SSD disk alanı'],
            // Plural. "1 web sitesi", "5 web sitesi" - never "web siteleri"
            // after a number. This is the string the customer reported.
            'store.res_domains' => ['{1} 1 website|[2,*] :count websites', '{1} 1 web sitesi|[2,*] :count web sitesi'],
            'store.res_memory' => [':value RAM', ':value RAM'],
            'store.res_shared_note' => [
                'Apps run inside these limits and share them. How many you can run at once is really a question of memory: small apps need a few hundred MB, larger ones a gigabyte or more.',
                'Uygulamalar bu sınırların içinde çalışır ve sınırları paylaşır. Aynı anda kaç uygulama çalıştırabileceğiniz aslında bir bellek sorusudur: küçük uygulamalar birkaç yüz MB, büyükler bir gigabayt veya daha fazlasını ister.',
            ],
        ];
    }

    public function up(): void
    {
        $now = now();

        foreach ($this->rows() as $key => [, $turkish]) {
            $exists = DB::table('dynamic_translations')
                ->where('language', 'tr')->where('group', self::GROUP)->where('key', $key)->exists();

            if (! $exists) {
                DB::table('dynamic_translations')->insert([
                    'language' => 'tr', 'group' => self::GROUP, 'key' => $key, 'value' => $turkish,
                    'is_auto_translated' => false, 'is_reviewed' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        try {
            Cache::forget('translations:tr:'.self::GROUP);
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        DB::table('dynamic_translations')
            ->where('language', 'tr')->where('group', self::GROUP)
            ->whereIn('key', array_keys($this->rows()))
            ->delete();

        try {
            Cache::forget('translations:tr:'.self::GROUP);
        } catch (\Throwable $e) {
        }
    }
};
