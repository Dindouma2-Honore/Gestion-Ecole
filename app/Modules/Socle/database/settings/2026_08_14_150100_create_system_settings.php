<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('systeme.delaiToleranceMinutes', 15);
        $this->migrator->add('systeme.delaiPremiereRelanceHeures', 24);
        $this->migrator->add('systeme.delaiDeuxiemeRelanceHeures', 48);
    }
};
