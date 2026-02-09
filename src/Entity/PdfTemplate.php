<?php

namespace App\Entity;

use App\Repository\PdfTemplateRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PdfTemplateRepository::class)]
#[ORM\Table(name: 'pdf_templates')]
class PdfTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $slug;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $previewImageUrl = null;

    #[ORM\Column(length: 255)]
    private string $templatePath;

    #[ORM\Column(length: 10)]
    private string $pageSize = 'A4';

    #[ORM\Column(type: 'smallint')]
    private int $labelsPerPage = 6;

    #[ORM\Column(type: 'json')]
    private array $placeholders = [];

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $slug, string $name, string $templatePath)
    {
        $this->slug = $slug;
        $this->name = $name;
        $this->templatePath = $templatePath;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getPreviewImageUrl(): ?string
    {
        return $this->previewImageUrl;
    }

    public function getTemplatePath(): string
    {
        return $this->templatePath;
    }

    public function getPageSize(): string
    {
        return $this->pageSize;
    }

    public function getLabelsPerPage(): int
    {
        return $this->labelsPerPage;
    }

    public function getPlaceholders(): array
    {
        return $this->placeholders;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
