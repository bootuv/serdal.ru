#!/bin/bash

# Список файлов ресурсов для обновления
RESOURCES=(
    "/Users/berd/Documents/Websites/serdal.ru/app/Filament/Resources/RoomResource.php"
    "/Users/berd/Documents/Websites/serdal.ru/app/Filament/Resources/DirectResource.php"
    "/Users/berd/Documents/Websites/serdal.ru/app/Filament/Resources/SubjectResource.php"
    "/Users/berd/Documents/Websites/serdal.ru/app/Filament/Resources/MeetingSessionResource.php"
    "/Users/berd/Documents/Websites/serdal.ru/app/Filament/App/Resources/RoomResource.php"
    "/Users/berd/Documents/Websites/serdal.ru/app/Filament/App/Resources/StudentResource.php"
    "/Users/berd/Documents/Websites/serdal.ru/app/Filament/App/Resources/RecordingResource.php"
    "/Users/berd/Documents/Websites/serdal.ru/app/Filament/App/Resources/MeetingSessionResource.php"
    "/Users/berd/Documents/Websites/serdal.ru/app/Filament/App/Resources/LessonTypeResource.php"
)

echo "Добавление defaultSort во все ресурсы..."

for file in "${RESOURCES[@]}"; do
    if [ -f "$file" ]; then
        echo "Обработка: $file"
        # Добавить ->defaultSort('created_at', 'desc') перед ->actions(
        sed -i.bak "s/->actions(/->defaultSort('created_at', 'desc')\n            ->actions(/g" "$file"
        echo "✓ Обновлено: $file"
    else
        echo "✗ Файл не найден: $file"
    fi
done

echo "Готово!"
