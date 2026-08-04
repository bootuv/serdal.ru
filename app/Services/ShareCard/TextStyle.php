<?php

namespace App\Services\ShareCard;

final readonly class TextStyle
{
    public function __construct(
        public string $fontPath,
        public float $size,
        public string $color = '000000',
        public float $lineHeight = 1.0,
        public ?int $wrapWidth = null,
    ) {}

    public function withSize(float $size): self
    {
        return new self($this->fontPath, $size, $this->color, $this->lineHeight, $this->wrapWidth);
    }

    public function withLineHeight(float $lineHeight): self
    {
        return new self($this->fontPath, $this->size, $this->color, $lineHeight, $this->wrapWidth);
    }
}
