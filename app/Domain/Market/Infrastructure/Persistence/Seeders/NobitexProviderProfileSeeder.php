<?php

namespace App\Domain\Market\Infrastructure\Persistence\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NobitexProviderProfileSeeder extends Seeder
{
    public function run(): void
    {
        $providerId = DB::table('market_providers')->where('slug', 'nobitex')->value('id');

        if ($providerId === null) {
            $this->command?->warn('Nobitex provider was not found; skipping its profile seed.');

            return;
        }

        // Draft by design: an operator reviews the editorial facts and sources
        // in Backoffice before it becomes publicly visible.
        DB::table('market_provider_profiles')->insertOrIgnore([
            'provider_id' => $providerId,
            'type' => 'exchange',
            'publication_status' => 'draft',
            'summary' => json_encode([
                'fa' => 'پروفایل صرافی نوبیتکس؛ پیش‌نویس قابل بازبینی پیش از انتشار.',
                'en' => 'Nobitex exchange profile, ready for editorial review before publication.',
                'de' => 'Nobitex-Börsenprofil, bereit für die redaktionelle Prüfung vor der Veröffentlichung.',
            ]),
            'description' => json_encode([
                'fa' => 'این پروفایل اولیه برای تکمیل و تأیید توسط تیم محتوا آماده شده است.',
                'en' => 'This initial profile is ready for the content team to complete and verify.',
                'de' => 'Dieses erste Profil kann vom Content-Team vervollständigt und geprüft werden.',
            ]),
            'seo_title' => json_encode([
                'fa' => 'پروفایل صرافی نوبیتکس',
                'en' => 'Nobitex Exchange Profile',
                'de' => 'Nobitex-Börsenprofil',
            ]),
            'seo_description' => json_encode([
                'fa' => 'اطلاعات و منابع رسمی نوبیتکس، پس از بررسی تیم محتوا.',
                'en' => 'Nobitex information and official sources, pending editorial review.',
                'de' => 'Nobitex-Informationen und offizielle Quellen, die redaktionell geprüft werden müssen.',
            ]),
            'facts' => json_encode([]),
            'sources' => json_encode([[
                'label' => [
                    'fa' => 'وب‌سایت رسمی',
                    'en' => 'Official website',
                    'de' => 'Offizielle Website',
                ],
                'url' => 'https://nobitex.ir',
            ]]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
