<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('homepage.heroTitle', [
            'lt' => 'Elektros inžinerijos sprendimai, kuriais galite pasitikėti',
            'en' => 'Electrical engineering solutions you can rely on',
        ]);
        $this->migrator->add('homepage.heroSubtitle', [
            'lt' => 'Nuo projektavimo iki įgyvendinimo — patikimi elektros instaliacijos sprendimai namams ir verslui.',
            'en' => 'From design to delivery — reliable electrical installation solutions for homes and businesses.',
        ]);
        $this->migrator->add('homepage.introTitle', [
            'lt' => 'Apie AmpSquad',
            'en' => 'About AmpSquad',
        ]);
        $this->migrator->add('homepage.introContent', [
            'lt' => 'Esame komanda inžinierių, sutelkusi dėmesį į kokybišką ir saugų elektros darbų atlikimą. Dirbame su gyvenamaisiais, komerciniais ir pramoniniais objektais.',
            'en' => 'We are a team of engineers focused on safe, high-quality electrical work. We work with residential, commercial, and industrial projects alike.',
        ]);
        $this->migrator->add('homepage.ctaTitle', [
            'lt' => 'Turite projektą omenyje?',
            'en' => 'Have a project in mind?',
        ]);
        $this->migrator->add('homepage.ctaButtonLabel', [
            'lt' => 'Susisiekite su mumis',
            'en' => 'Get in touch',
        ]);
    }
};
