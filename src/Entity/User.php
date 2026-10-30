<?php

namespace App\Entity;

use App\Preference\Language;
use App\Preference\Theme;
use App\Repository\UserRepository;
use App\Security\Role;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'email.already_used')]
#[Vich\Uploadable]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'email.not_blank')]
    #[Assert\Email(message: 'email.invalid')]
    private string $email = '';

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private string $password = '';

    #[ORM\Column]
    private bool $isVerified = false;

    /**
     * A disabled user cannot log in, and is logged out at their next request.
     */
    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * The photo of the profile, while it is uploaded: VichUploader stores it, and keeps its name.
     */
    #[Vich\UploadableField(mapping: 'avatar', fileNameProperty: 'avatarName')]
    #[Assert\Image(
        maxSize: '2M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        minWidth: 128,
        minHeight: 128,
        maxSizeMessage: 'avatar.too_large',
        mimeTypesMessage: 'avatar.wrong_type',
        minWidthMessage: 'avatar.too_small',
        minHeightMessage: 'avatar.too_small',
    )]
    private ?File $avatarFile = null;

    /**
     * The name of the photo in its storage (config/packages/flysystem.yaml).
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatarName = null;

    /**
     * Changed with the photo: Doctrine sees no change of the user otherwise, and VichUploader
     * would not store the new one.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * The language of the interface and of the emails sent to this user.
     */
    #[ORM\Column(length: 5, enumType: Language::class, options: [
        'default' => 'en',
    ])]
    private Language $language = Language::English;

    #[ORM\Column(length: 10, enumType: Theme::class, options: [
        'default' => 'system',
    ])]
    private Theme $theme = Theme::System;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getLanguage(): Language
    {
        return $this->language;
    }

    public function setLanguage(Language $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function getTheme(): Theme
    {
        return $this->theme;
    }

    public function setTheme(Theme $theme): static
    {
        $this->theme = $theme;

        return $this;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        if ($this->email === '') {
            throw new \LogicException('A user without an email cannot be authenticated.');
        }

        return $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);
        // An uploaded file cannot be serialized: the session has no use for it.
        $data["\0" . self::class . "\0avatarFile"] = null;

        return $data;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * The roles granted by hand: ROLE_USER, which every user has, is not one of them.
     *
     * @return list<Role>
     */
    public function getGrantedRoles(): array
    {
        return array_values(array_filter(array_map(Role::tryFrom(...), $this->roles)));
    }

    /**
     * @param list<Role> $roles
     */
    public function setGrantedRoles(array $roles): static
    {
        $this->roles = array_map(static fn(Role $role): string => $role->value, $roles);

        return $this;
    }

    /**
     * Whether the user of the session ($this) is still the one in the database ($user): if not, they
     * are logged out. Compares what Symfony compares by default, and whether the user is enabled.
     */
    public function isEqualTo(UserInterface $user): bool
    {
        if (! $user instanceof self) {
            return false;
        }

        // The password of the user of the session is CRC32C-hashed (__serialize()).
        $samePassword = \in_array($this->password, [$user->password, hash('crc32c', $user->password)], true);
        $ownRoles = $this->getRoles();
        $otherRoles = $user->getRoles();
        sort($ownRoles);
        sort($otherRoles);

        return $user->enabled && $this->email === $user->email && $samePassword && $ownRoles === $otherRoles;
    }

    public function getAvatarFile(): ?File
    {
        return $this->avatarFile;
    }

    public function setAvatarFile(?File $avatarFile): static
    {
        $this->avatarFile = $avatarFile;
        if ($avatarFile !== null) {
            $this->updatedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getAvatarName(): ?string
    {
        return $this->avatarName;
    }

    public function setAvatarName(?string $avatarName): static
    {
        $this->avatarName = $avatarName;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
