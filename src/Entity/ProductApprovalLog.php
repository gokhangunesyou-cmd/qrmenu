<?php

namespace App\Entity;

use App\Enum\ApprovalAction;
use App\Repository\ProductApprovalLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductApprovalLogRepository::class)]
#[ORM\Table(name: 'product_approval_log')]
#[ORM\Index(columns: ['product_id', 'created_at'], name: 'idx_approval_log_product')]
class ProductApprovalLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(length: 20, enumType: ApprovalAction::class)]
    private ApprovalAction $action;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $performedBy;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Product $product, ApprovalAction $action, User $performedBy, ?string $note = null)
    {
        $this->product = $product;
        $this->action = $action;
        $this->performedBy = $performedBy;
        $this->note = $note;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getAction(): ApprovalAction
    {
        return $this->action;
    }

    public function getPerformedBy(): User
    {
        return $this->performedBy;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
