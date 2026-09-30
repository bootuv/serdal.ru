<x-mail::message>
<x-slot:preheader>{{ $application->full_name }} хочет вести занятия</x-slot:preheader>
# Новая заявка учителя

<x-mail::rows :rows="array_values(array_filter([
    ['label' => 'Имя', 'value' => $application->full_name],
    ['label' => 'Почта', 'value' => $application->email],
    $application->phone ? ['label' => 'Телефон', 'value' => $application->phone] : null,
]))" />

<x-mail::button :url="route('cabinet.admin.applications')">
Открыть заявки
</x-mail::button>
</x-mail::message>
