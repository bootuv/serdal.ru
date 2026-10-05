// Демо-кабинет (App\Demo, раскладка cabinet с prop demo): из cabinet.js — только то, что работает без Livewire и сокетов.
// Подключается до Alpine: модули регистрируют window.richEditor / blockEditor и Alpine.data('photoCrop') на alpine:init.
import './rich-editor';
import './block-editor';
import './lightbox';
import './video-player';
import './photo-crop';
