<?php

use App\Support\HelpIcons;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Иконки категорий базы знаний: эмодзи → ключи линейных иконок кабинета (App\Support\HelpIcons).
 * Эмодзи без пары остаются как есть — публичная справка и админка показывают их текстом.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->convert(fn (string $icon) => HelpIcons::EMOJI[$icon] ?? null);
    }

    public function down(): void
    {
        $this->convert(fn (string $icon) => HelpIcons::TO_EMOJI[$icon] ?? null);
    }

    private function convert(callable $map): void
    {
        DB::table('help_categories')->whereNotNull('icon')->orderBy('id')->get(['id', 'icon'])
            ->each(function ($row) use ($map) {
                $new = $map(trim((string) $row->icon));
                if ($new !== null && $new !== $row->icon) {
                    DB::table('help_categories')->where('id', $row->id)->update(['icon' => $new]);
                }
            });
    }
};
