<?php

namespace App\DTO\Response\Admin;

final readonly class PdfTemplateResponse
{
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $description,
        public ?string $previewImageUrl,
        public string $pageSize,
        public int $labelsPerPage,
    ) {
    }
}
