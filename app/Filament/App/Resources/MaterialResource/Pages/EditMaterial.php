<?php

namespace App\Filament\App\Resources\MaterialResource\Pages;

use App\Filament\App\Resources\MaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaterial extends EditRecord
{
    protected static string $resource = MaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('open')
                ->label('Открыть файл')
                ->icon('heroicon-m-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => $this->record->file_url)
                ->openUrlInNewTab(),

            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        // Возвращаемся в папку, где лежит файл
        return $this->getResource()::getUrl('index', array_filter(['folder' => $this->record->folder_id]));
    }
}
