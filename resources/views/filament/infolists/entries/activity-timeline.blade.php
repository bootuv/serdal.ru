@php
    $activities = $getState();
@endphp

@include('filament.components.activity-timeline', ['activities' => $activities])