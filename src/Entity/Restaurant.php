<?php

namespace App\Entity;

use App\Repository\RestaurantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: RestaurantRepository::class)]
#[ORM\Table(name: 'restaurants')]
#[ORM\Index(columns: ['customer_account_id'], name: 'idx_restaurants_customer_account')]
class Restaurant
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $uuid;

    #[ORM\ManyToOne(targetEntity: CustomerAccount::class, inversedBy: 'restaurants')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?CustomerAccount $customerAccount = null;

    #[ORM\Column(length: 150)]
    private string $name;

    #[ORM\Column(length: 100, unique: true)]
    private string $slug;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $countryCode = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $longitude = null;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Media $logoMedia = null;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Media $coverMedia = null;

    #[ORM\ManyToOne(targetEntity: Theme::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Theme $theme;

    #[ORM\Column(length: 30, options: ['default' => 'showcase'])]
    private string $menuTemplate = 'showcase';

    #[ORM\Column(length: 5)]
    private string $defaultLocale = 'tr';

    #[ORM\Column(type: 'json')]
    private array $enabledLocales = ['tr'];

    #[ORM\Column(length: 3)]
    private string $currencyCode = 'TRY';

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $colorOverrides = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $customCss = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $metaTitle = null;

    #[ORM\Column(length: 320, nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column]
    private bool $isActive = false;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    /** @var Collection<int, Category> */
    #[ORM\OneToMany(targetEntity: Category::class, mappedBy: 'restaurant')]
    private Collection $categories;

    /** @var Collection<int, Product> */
    #[ORM\OneToMany(targetEntity: Product::class, mappedBy: 'restaurant')]
    private Collection $products;

    /** @var Collection<int, RestaurantSocialLink> */
    #[ORM\OneToMany(targetEntity: RestaurantSocialLink::class, mappedBy: 'restaurant', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $socialLinks;

    /** @var Collection<int, RestaurantPage> */
    #[ORM\OneToMany(targetEntity: RestaurantPage::class, mappedBy: 'restaurant', cascade: ['persist', 'remove'])]
    private Collection $pages;

    /** @var Collection<int, QrCode> */
    #[ORM\OneToMany(targetEntity: QrCode::class, mappedBy: 'restaurant', cascade: ['persist', 'remove'])]
    private Collection $qrCodes;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'restaurants')]
    private Collection $users;

    public function __construct(string $name, string $slug, Theme $theme)
    {
        $this->uuid = Uuid::uuid7();
        $this->name = $name;
        $this->slug = $slug;
        $this->theme = $theme;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->categories = new ArrayCollection();
        $this->products = new ArrayCollection();
        $this->socialLinks = new ArrayCollection();
        $this->pages = new ArrayCollection();
        $this->qrCodes = new ArrayCollection();
        $this->users = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): UuidInterface
    {
        return $this->uuid;
    }

    public function getCustomerAccount(): ?CustomerAccount
    {
        return $this->customerAccount;
    }

    public function setCustomerAccount(?CustomerAccount $customerAccount): void
    {
        $this->customerAccount = $customerAccount;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): void
    {
        $this->address = $address;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): void
    {
        $this->city = $city;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function setCountryCode(?string $countryCode): void
    {
        $this->countryCode = $countryCode;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): void
    {
        $this->latitude = $latitude;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): void
    {
        $this->longitude = $longitude;
    }

    public function getLogoMedia(): ?Media
    {
        return $this->logoMedia;
    }

    public function setLogoMedia(?Media $logoMedia): void
    {
        $this->logoMedia = $logoMedia;
    }

    public function getCoverMedia(): ?Media
    {
        return $this->coverMedia;
    }

    public function setCoverMedia(?Media $coverMedia): void
    {
        $this->coverMedia = $coverMedia;
    }

    public function getTheme(): Theme
    {
        return $this->theme;
    }

    public function setTheme(Theme $theme): void
    {
        $this->theme = $theme;
    }

    public function getMenuTemplate(): string
    {
        return $this->menuTemplate;
    }

    public function setMenuTemplate(string $menuTemplate): void
    {
        $this->menuTemplate = $menuTemplate;
    }

    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    public function setDefaultLocale(string $defaultLocale): void
    {
        $normalizedLocale = strtolower(trim($defaultLocale));
        $this->defaultLocale = $normalizedLocale !== '' ? $normalizedLocale : 'tr';

        if (!in_array($this->defaultLocale, $this->enabledLocales, true)) {
            $this->enabledLocales[] = $this->defaultLocale;
        }

        $this->enabledLocales = $this->normalizeLocaleList($this->enabledLocales);
    }

    /**
     * @return string[]
     */
    public function getEnabledLocales(): array
    {
        return $this->enabledLocales;
    }

    /**
     * @param string[] $enabledLocales
     */
    public function setEnabledLocales(array $enabledLocales): void
    {
        $normalizedLocales = $this->normalizeLocaleList($enabledLocales);
        if ($normalizedLocales === []) {
            $normalizedLocales = ['tr'];
        }

        if (!in_array($this->defaultLocale, $normalizedLocales, true)) {
            $this->defaultLocale = $normalizedLocales[0];
        }

        $this->enabledLocales = $normalizedLocales;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(string $currencyCode): void
    {
        $this->currencyCode = $currencyCode;
    }

    public function getColorOverrides(): ?array
    {
        return $this->colorOverrides;
    }

    public function setColorOverrides(?array $colorOverrides): void
    {
        $this->colorOverrides = $colorOverrides;
    }

    public function getCustomCss(): ?string
    {
        return $this->customCss;
    }

    public function setCustomCss(?string $customCss): void
    {
        $this->customCss = $customCss;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): void
    {
        $this->metaTitle = $metaTitle;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): void
    {
        $this->metaDescription = $metaDescription;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
    }

    /** @return Collection<int, Category> */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    /** @return Collection<int, Product> */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    /** @return Collection<int, RestaurantSocialLink> */
    public function getSocialLinks(): Collection
    {
        return $this->socialLinks;
    }

    /** @return Collection<int, RestaurantPage> */
    public function getPages(): Collection
    {
        return $this->pages;
    }

    /** @return Collection<int, QrCode> */
    public function getQrCodes(): Collection
    {
        return $this->qrCodes;
    }

    /** @return Collection<int, User> */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    /**
     * @param string[] $locales
     *
     * @return string[]
     */
    private function normalizeLocaleList(array $locales): array
    {
        $normalized = [];
        foreach ($locales as $locale) {
            if (!is_string($locale)) {
                continue;
            }

            $code = strtolower(trim($locale));
            if ($code === '' || isset($normalized[$code])) {
                continue;
            }

            $normalized[$code] = $code;
        }

        return array_values($normalized);
    }
}
