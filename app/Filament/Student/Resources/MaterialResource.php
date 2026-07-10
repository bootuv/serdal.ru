<?php

namespace App\Filament\Student\Resources;

use App\Filament\Student\Resources\MaterialResource\Pages;
use App\Models\TeacherMaterial;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;

class MaterialResource extends Resource
{
    protected static ?string $model = TeacherMaterial::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';

    protected static ?string $navigationLabel = 'Материалы';

    protected static ?string $modelLabel = 'Материал';

    protected static ?string $pluralModelLabel = 'Материалы';

    protected static ?int $navigationSort = 5;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['folder', 'teacher'])
            ->visibleToStudent(auth()->user());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterials::route('/'),
        ];
    }
}
