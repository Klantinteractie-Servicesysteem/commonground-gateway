<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\TranslationRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=TranslationRepository::class)
 */
class Translation
{
    /**
     * @var UuidInterface The UUID identifier of this Entity.
     *
     * @example e2984465-190a-4562-829e-a8cca81aa35d
     *
     * @Assert\Uuid
     * @Groups({"read","read_secure"})
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    private $id;

    /**
     * @var string The table of this Translation.
     *
     * @Assert\NotNull
     * @Assert\Length(
     *     max = 255
     * )
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255)
     */
    private $translationTable;

    /**
     * @var string Translate from of this Translation.
     *
     * @Assert\NotNull
     * @Assert\Length(
     *     max = 255
     * )
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255)
     */
    private $translateFrom;

    /**
     * @var string Translate to of this Translation.
     *
     * @Assert\NotNull
     * @Assert\Length(
     *     max = 255
     * )
     * @Groups({"read","write"})
     * @ORM\Column(type="string", length=255)
     */
    private $translateTo;

    /**
     * @var array The languages of this Handler.
     *
     * @Assert\NotNull
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private $languages = [];

    public function getId()
    {
        return $this->id;
    }

    public function getTranslationTable(): ?string
    {
        return $this->translationTable;
    }

    public function setTranslationTable(string $translationTable): self
    {
        $this->translationTable = $translationTable;

        return $this;
    }

    public function getTranslateFrom(): ?string
    {
        return $this->translateFrom;
    }

    public function setTranslateFrom(string $translateFrom): self
    {
        $this->translateFrom = $translateFrom;

        return $this;
    }

    public function getTranslateTo(): ?string
    {
        return $this->translateTo;
    }

    public function setTranslateTo(string $translateTo): self
    {
        $this->translateTo = $translateTo;

        return $this;
    }

    public function getLanguages(): ?array
    {
        return $this->languages;
    }

    public function setLanguages(?array $languages): self
    {
        $this->languages = $languages;

        return $this;
    }
}