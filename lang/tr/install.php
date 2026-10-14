<?php

// The install wizard.
return [
    'migrate_title' => 'Veritabanı tabloları oluşturuluyor',
    'migrate_intro' => 'PNLCS veritabanını hazırlıyor. Bu genellikle bir iki dakika sürer. Sayfayı açık tutun, iş bitince kendiliğinden devam eder.',
    'migrate_progress' => ':total adımın :done tanesi tamamlandı',
    'migrate_failed' => 'Veritabanı kurulumu durdu: :message',
    'migrate_retry' => 'Yeniden dene',
    'db_connect_failed' => 'Bu ayarlarla veritabanına bağlanılamadı: :message',
    'cron_title' => 'Son bir adım: zamanlayıcı',
    'cron_intro' => 'PNLCS e-postaları, hizmet yenilemelerini ve arka plan işlerini her dakika başlaması gereken bir zamanlayıcıyla yürütür. Bu satırı sitenin çalıştığı kullanıcının cron görevlerine ekleyin:',
    'cron_php' => 'Sunucunuzda php komutu başka bir PHP sürümünü açıyorsa onun yerine PHP 8.4\'ün yolunu yazın.',
    'cron_note' => 'Docker ile ya da tek komutlu kurulum betiğiyle kurduysanız bu adım zaten yapıldı.',
];
