<?php

namespace Database\Seeders;

use App\Models\HomepageContent;
use App\Models\HomepageSection;
use Illuminate\Database\Seeder;

class HomepageSectionSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            [
                'slug' => 'hero',
                'title' => 'Hero Section',
                'sort_order' => 1,
                'is_enabled' => true,
                'content' => [
                    ['key' => 'badge_text', 'value' => 'Powered by Panelica Infrastructure', 'type' => 'text'],
                    ['key' => 'title', 'value' => 'Hosting That <span>Simply Works</span>', 'type' => 'html'],
                    ['key' => 'subtitle', 'value' => 'Panelica-Powered Isolated Infrastructure', 'type' => 'text'],
                    ['key' => 'description', 'value' => 'Launch your website on Panelica\'s isolated hosting platform with NVMe storage, per-account resource limits, and free SSL — from $1.99/month.', 'type' => 'text'],
                    ['key' => 'stat_1_icon', 'value' => 'ri-shield-user-line', 'type' => 'text'],
                    ['key' => 'stat_1_text', 'value' => 'Cgroups v2 Isolation', 'type' => 'text'],
                    ['key' => 'stat_2_icon', 'value' => 'ri-speed-line', 'type' => 'text'],
                    ['key' => 'stat_2_text', 'value' => 'Nginx + PHP-FPM', 'type' => 'text'],
                    ['key' => 'cta_text', 'value' => 'Get Started Now', 'type' => 'text'],
                    ['key' => 'cta_url', 'value' => '/client/register', 'type' => 'text'],
                    ['key' => 'callout_1_icon', 'value' => 'ri-gift-line', 'type' => 'text'],
                    ['key' => 'callout_1_text', 'value' => 'FREE Domain with annual plans', 'type' => 'text'],
                    ['key' => 'callout_2_icon', 'value' => 'ri-lock-line', 'type' => 'text'],
                    ['key' => 'callout_2_text', 'value' => 'Free SSL Included', 'type' => 'text'],
                ],
            ],
            [
                'slug' => 'domain-search',
                'title' => 'Domain Search',
                'sort_order' => 2,
                'is_enabled' => true,
                'content' => [
                    ['key' => 'title', 'value' => 'Find Your Perfect Domain', 'type' => 'text'],
                    ['key' => 'subtitle', 'value' => 'Search for your ideal domain name and secure it today', 'type' => 'text'],
                    ['key' => 'placeholder', 'value' => 'Enter your domain name... (e.g. mysite.com)', 'type' => 'text'],
                ],
            ],
            [
                'slug' => 'promo-cards',
                'title' => 'Promo Cards',
                'sort_order' => 3,
                'is_enabled' => true,
                'content' => [
                    ['key' => 'cards', 'value' => json_encode([
                        [
                            'title' => '.COM Domain',
                            'subtitle' => 'Register your brand today',
                            'old_price' => '$12.99',
                            'new_price' => '$9.99',
                            'period' => '/yr',
                            'cta_text' => 'Register Now',
                            'cta_url' => '/client/domain-search',
                            'gradient' => 'promo-card--1',
                        ],
                        [
                            'title' => 'Web Hosting',
                            'subtitle' => 'NVMe powered hosting',
                            'old_price' => '$4.99',
                            'new_price' => '$2.99',
                            'period' => '/mo',
                            'cta_text' => 'Get Started',
                            'cta_url' => '/client/store',
                            'gradient' => 'promo-card--2',
                        ],
                        [
                            'title' => 'WordPress Hosting',
                            'subtitle' => 'Optimized for WordPress',
                            'old_price' => '$5.99',
                            'new_price' => '$3.99',
                            'period' => '/mo',
                            'cta_text' => 'Get Started',
                            'cta_url' => '/client/store',
                            'gradient' => 'promo-card--3',
                        ],
                        [
                            'title' => 'VPS Server',
                            'subtitle' => 'Full root access included',
                            'old_price' => '$12.00',
                            'new_price' => '$6.99',
                            'period' => '/mo',
                            'cta_text' => 'Configure',
                            'cta_url' => '/client/store',
                            'gradient' => 'promo-card--4',
                        ],
                    ]), 'type' => 'json'],
                ],
            ],
            [
                'slug' => 'hosting-plans',
                'title' => 'Hosting Plans',
                'sort_order' => 4,
                'is_enabled' => true,
                'content' => [
                    ['key' => 'title', 'value' => 'Popular Web Hosting Plans', 'type' => 'text'],
                    ['key' => 'subtitle', 'value' => 'Choose the perfect plan for your website. Upgrade or downgrade anytime.', 'type' => 'text'],
                    ['key' => 'promo_icon', 'value' => 'ri-gift-2-line', 'type' => 'text'],
                    ['key' => 'promo_title', 'value' => 'FREE .COM Domain with Annual Plans', 'type' => 'text'],
                    ['key' => 'promo_text', 'value' => 'Get a free domain registration when you sign up for any annual hosting plan. No hidden fees.', 'type' => 'text'],
                    ['key' => 'promo_cta', 'value' => 'Claim Offer', 'type' => 'text'],
                ],
            ],
            [
                'slug' => 'infrastructure',
                'title' => 'Infrastructure',
                'sort_order' => 5,
                'is_enabled' => true,
                'content' => [
                    ['key' => 'title', 'value' => 'Enterprise-Grade Infrastructure', 'type' => 'text'],
                    ['key' => 'subtitle', 'value' => 'Built for performance, reliability, and security from the ground up', 'type' => 'text'],
                    ['key' => 'cards', 'value' => json_encode([
                        ['icon' => 'ri-hard-drive-3-line', 'icon_class' => 'infra-card__icon--1', 'title' => 'NVMe Storage', 'desc' => 'Every account sits on enterprise NVMe drives — dramatically faster read/write than spinning disks, so your pages load in a flash.'],
                        ['icon' => 'ri-speed-line', 'icon_class' => 'infra-card__icon--2', 'title' => 'Nginx + PHP-FPM Stack', 'desc' => 'Powered by Nginx reverse proxy with per-user PHP-FPM pools. Each site gets its own process, tuned for speed and stability.'],
                        ['icon' => 'ri-shield-user-line', 'icon_class' => 'infra-card__icon--3', 'title' => 'Cgroups v2 Isolation', 'desc' => 'Panelica enforces per-user CPU, memory, and I/O limits through Linux Cgroups v2. Your resources are yours alone — no shared bottlenecks.'],
                        ['icon' => 'ri-dashboard-line', 'icon_class' => 'infra-card__icon--4', 'title' => 'Panelica Control Panel', 'desc' => 'Our own modern panel for managing domains, emails, databases, files, and DNS — clean UI, fast, and built specifically for this platform.'],
                        ['icon' => 'ri-lock-line', 'icon_class' => 'infra-card__icon--5', 'title' => 'Free SSL & Security', 'desc' => 'Every domain gets a free SSL certificate, along with DDoS protection, web application firewall, and real-time malware scanning.'],
                        ['icon' => 'ri-equalizer-line', 'icon_class' => 'infra-card__icon--6', 'title' => 'Dedicated Resources', 'desc' => 'Every plan has defined CPU, RAM, and storage quotas enforced at the kernel level. You always get the full capacity you paid for.'],
                    ]), 'type' => 'json'],
                ],
            ],
            [
                'slug' => 'stats',
                'title' => 'Stats Counter',
                'sort_order' => 6,
                'is_enabled' => true,
                'content' => [
                    ['key' => 'items', 'value' => json_encode([
                        ['number' => '5', 'suffix' => '+', 'label' => 'PHP Versions'],
                        ['number' => '20', 'suffix' => '+', 'label' => 'Managed Services'],
                        ['number' => '24', 'suffix' => '/7', 'label' => 'Monitoring & Alerts'],
                        ['number' => '100', 'suffix' => '%', 'label' => 'Resource Isolation'],
                    ]), 'type' => 'json'],
                ],
            ],
            [
                'slug' => 'vps-plans',
                'title' => 'VPS Plans',
                'sort_order' => 7,
                'is_enabled' => true,
                'content' => [
                    ['key' => 'title', 'value' => 'VPS Server Plans', 'type' => 'text'],
                    ['key' => 'subtitle', 'value' => 'Full root access, dedicated resources, and instant deployment', 'type' => 'text'],
                    ['key' => 'visual_title', 'value' => 'Cloud VPS', 'type' => 'text'],
                    ['key' => 'visual_desc', 'value' => 'Full root access, dedicated CPU & RAM, and instant provisioning on Panelica infrastructure', 'type' => 'text'],
                ],
            ],
            [
                'slug' => 'testimonials',
                'title' => 'Testimonials',
                'sort_order' => 8,
                'is_enabled' => true,
                'content' => [
                    ['key' => 'title', 'value' => 'What Our Customers Say', 'type' => 'text'],
                    ['key' => 'subtitle', 'value' => 'Trusted by thousands of businesses worldwide', 'type' => 'text'],
                    ['key' => 'items', 'value' => json_encode([
                        [
                            'text' => 'We migrated 15 websites from our old host and the speed improvement was immediately noticeable. Page load times dropped by over 60%. Support team helped with every step of the migration.',
                            'name' => 'James Mitchell',
                            'role' => 'CTO, TechStart Inc.',
                            'initials' => 'JM',
                            'avatar_class' => 'testimonial-card__avatar--1',
                        ],
                        [
                            'text' => 'The VPS performance is incredible for the price. Full root access, NVMe storage, and their uptime has been flawless for the past 14 months. Best hosting decision we\'ve made.',
                            'name' => 'Sarah Rodriguez',
                            'role' => 'Founder, DigitalCraft Agency',
                            'initials' => 'SR',
                            'avatar_class' => 'testimonial-card__avatar--2',
                        ],
                        [
                            'text' => 'I run a small web agency and needed a platform I could count on. The reseller setup with Panelica\'s billing tools made onboarding my clients painless. Solid uptime and great ticket support.',
                            'name' => 'Alex Kim',
                            'role' => 'CEO, CloudNine Hosting',
                            'initials' => 'AK',
                            'avatar_class' => 'testimonial-card__avatar--3',
                        ],
                    ]), 'type' => 'json'],
                ],
            ],
            [
                'slug' => 'faq',
                'title' => 'FAQ',
                'sort_order' => 9,
                'is_enabled' => true,
                'content' => [
                    ['key' => 'title', 'value' => 'Frequently Asked Questions', 'type' => 'text'],
                    ['key' => 'subtitle', 'value' => 'Everything you need to know about our hosting services', 'type' => 'text'],
                    ['key' => 'items', 'value' => json_encode([
                        [
                            'question' => 'What is included with my hosting plan?',
                            'answer' => 'Every hosting plan includes free SSL certificates, NVMe SSD storage, unmetered bandwidth, Panelica panel access, email accounts, one-click WordPress installer, daily backups, and 24/7 technical support. Higher-tier plans also include priority support and additional server resources.',
                        ],
                        [
                            'question' => 'Can I upgrade my plan later?',
                            'answer' => 'Absolutely! You can upgrade or downgrade your hosting plan at any time directly from your client dashboard. The price difference will be prorated, so you only pay for what you use. No downtime is involved during the upgrade process.',
                        ],
                        [
                            'question' => 'Do you offer a money-back guarantee?',
                            'answer' => 'Yes, we offer a 30-day money-back guarantee on all shared and WordPress hosting plans. If you\'re not satisfied with our service for any reason, you can request a full refund within the first 30 days. VPS and dedicated server plans have a 7-day money-back guarantee.',
                        ],
                        [
                            'question' => 'Will you help me migrate my website?',
                            'answer' => 'Yes! We offer free website migration for all new customers. Our team will handle the entire process — files, databases, emails, and DNS. We can migrate from any platform including cPanel, Plesk, or DirectAdmin into your new Panelica-powered account with zero downtime.',
                        ],
                        [
                            'question' => 'What kind of support do you offer?',
                            'answer' => 'We provide 24/7 technical support through live chat, support tickets, and phone. Our support team consists of experienced system administrators and developers who can assist with server configuration, performance optimization, security issues, and more.',
                        ],
                    ]), 'type' => 'json'],
                ],
            ],
            [
                'slug' => 'cta-banner',
                'title' => 'CTA Banner',
                'sort_order' => 10,
                'is_enabled' => true,
                'content' => [
                    ['key' => 'title', 'value' => 'Ready to Get Started?', 'type' => 'text'],
                    ['key' => 'subtitle', 'value' => 'Join thousands of satisfied customers and take your online presence to the next level with our hosting solutions.', 'type' => 'text'],
                    ['key' => 'cta_text', 'value' => 'Start Your Journey', 'type' => 'text'],
                    ['key' => 'cta_url', 'value' => '/client/register', 'type' => 'text'],
                    ['key' => 'note_text', 'value' => 'No credit card required. 30-day money-back guarantee.', 'type' => 'text'],
                ],
            ],
            [
                'slug' => 'footer',
                'title' => 'Footer',
                'sort_order' => 11,
                'is_enabled' => true,
                'content' => [
                    ['key' => 'description', 'value' => 'PNLCS is the billing & hosting management platform by Panelica. Domains, hosting, VPS, and SSL — all under one roof.', 'type' => 'text'],
                    ['key' => 'email', 'value' => 'info@panelica.com', 'type' => 'text'],
                    ['key' => 'support_email', 'value' => 'support@panelica.com', 'type' => 'text'],
                    ['key' => 'website', 'value' => 'panelica.com', 'type' => 'text'],
                ],
            ],
        ];

        foreach ($sections as $sectionData) {
            $contentItems = $sectionData['content'] ?? [];
            unset($sectionData['content']);

            $section = HomepageSection::updateOrCreate(
                ['slug' => $sectionData['slug']],
                $sectionData
            );

            foreach ($contentItems as $item) {
                HomepageContent::updateOrCreate(
                    [
                        'section_slug' => $section->slug,
                        'content_key' => $item['key'],
                    ],
                    [
                        'content_value' => $item['value'],
                        'content_type' => $item['type'],
                    ]
                );
            }
        }
    }
}
