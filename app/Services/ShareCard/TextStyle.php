<?php

namespace App\Services\ShareCard;

final readonly class TextStyle
{
    public function __construct(
        public string $fontPath,
        public int $size,
        public string $color = '000000',
        public float $lineHeight = 1.0,
        public ?int $wrapWidth = null,
    ) {}

    public function withSize(int $size): self
    {
        return new self($this->fontPath, $size, $this->color, $this->lineHeight, $this->wrapWidth);
    }
}
