<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A value for a given attribute on an Object Entity.
 *
 * @category Entity
 *
 * @ApiResource(
 *  normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *  denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *  itemOperations={
 *      "get"={"path"="/admin/values/{id}"},
 *      "put"={"path"="/admin/values/{id}"},
 *      "delete"={"path"="/admin/values/{id}"}
 *  },
 *  collectionOperations={
 *      "get"={"path"="/admin/values"},
 *      "post"={"path"="/admin/values"}
 *  })
 * @ORM\Entity(repositoryClass="App\Repository\ValueRepository")
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class, properties={
 *     "attribute.id": "exact",
 *     "objectEntity.id": "exact"
 * })
 * @ApiFilter(ExistsFilter::class, properties={
 *     "stringValue",
 *     "dateTimeValue",
 * })
 */
class Value
{
    /**
     * @var UuidInterface The UUID identifier of this resource
     *
     * @example e2984465-190a-4562-829e-a8cca81aa35d
     *
     * @Assert\Uuid
     * @Groups({"read"})
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    private $id;

    // TODO:indexeren
    /**
     * @var string An uri
     *
     * @Assert\Url
     * @Groups({"read", "write"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $uri;

    // TODO:indexeren
    /**
     * @var string The actual value if is of type string
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="text", nullable=true)
     */
    private $stringValue; //TODO make this type=string again!?

    /**
     * @var int Integer if the value is type integer
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="integer", nullable=true)
     */
    private $integerValue;

    /**
     * @var float Float if the value is type number
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="float", nullable=true)
     */
    private $numberValue;

    /**
     * @var bool Boolean if the value is type boolean
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $booleanValue;

    /**
     * @var array Array if the value is type array
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private $arrayValue;

    /**
     * @var DateTime DateTime if the value is type DateTime
     *
     * @Groups({"read", "write"})
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateTimeValue;

    /**
     * @Groups({"read","write"})
     * @MaxDepth(1)
     * @ORM\OneToMany(targetEntity=File::class, mappedBy="value", cascade={"persist", "remove"})
     */
    private $files;

    /**
     * @Groups({"read","write"})
     * @ORM\ManyToOne(targetEntity=Attribute::class, inversedBy="attributeValues")
     * @ORM\JoinColumn(nullable=false)
     * @MaxDepth(1)
     */
    private Attribute $attribute;

    /**
     * @Groups({"write"})
     * @ORM\ManyToOne(targetEntity=ObjectEntity::class, inversedBy="objectValues", fetch="EAGER", cascade={"persist"})
     * @ORM\JoinColumn(nullable=false)
     * @MaxDepth(1)
     */
    private $objectEntity; // parent object

    /**
     * @MaxDepth(1)
     * @ORM\ManyToMany(targetEntity=ObjectEntity::class, mappedBy="subresourceOf", fetch="EAGER", cascade={"persist"})
     */
    private $objects; // sub objects

    /**
     * @var Datetime The moment this resource was created
     *
     * @Groups({"read"})
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateCreated;

    /**
     * @var Datetime The moment this resource last Modified
     *
     * @Groups({"read"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateModified;

    public function __construct()
    {
        $this->files = new ArrayCollection();
        $this->objects = new ArrayCollection();
    }

    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    public function setId(UuidInterface $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getUri(): ?string
    {
        return $this->uri;
    }

    public function setUri(?string $uri): self
    {
        $this->uri = $uri;

        return $this;
    }

    public function getStringValue(): ?string
    {
        return $this->stringValue;
    }

    public function setStringValue(?string $stringValue): self
    {
        $this->stringValue = $stringValue;

        return $this;
    }

    public function getIntegerValue(): ?int
    {
        return $this->integerValue;
    }

    /**
     * Also stores the value in the stringValue in order to facilitate filtering on the string value. (see ObjectEntityRepository).
     *
     * @param int|null $integerValue
     *
     * @return $this
     */
    public function setIntegerValue(?int $integerValue): self
    {
        $this->integerValue = $integerValue;
        $this->stringValue = $integerValue !== null ? "$integerValue" : null;

        return $this;
    }

    public function getNumberValue(): ?float
    {
        return $this->numberValue;
    }

    /**
     * Also stores the value in the stringValue in order to facilitate filtering on the string value. (see ObjectEntityRepository).
     *
     * @param float|null $numberValue
     *
     * @return $this
     */
    public function setNumberValue(?float $numberValue): self
    {
        $this->numberValue = $numberValue;
        $this->stringValue = $numberValue !== null ? (string) $numberValue : null;

        return $this;
    }

    public function getBooleanValue(): ?bool
    {
        return $this->booleanValue;
    }

    /**
     * Also stores the value in the stringValue in order to facilitate filtering on the string value. (see ObjectEntityRepository).
     *
     * @param bool|null $booleanValue
     *
     * @return $this
     */
    public function setBooleanValue(?bool $booleanValue): self
    {
        $this->booleanValue = $booleanValue;
//        $this->stringValue = $booleanValue !== null ? (string) $booleanValue : null; // results in a string of: "1" or "0"
        $this->stringValue = $booleanValue !== null ? ($booleanValue ? 'true' : 'false') : null;

        return $this;
    }

    public function getArrayValue(): ?array
    {
        return $this->arrayValue;
    }

    public function setArrayValue(?array $arrayValue): self
    {
        $this->arrayValue = $arrayValue;

        return $this;
    }

    public function getDateTimeValue(): ?DateTimeInterface
    {
        return $this->dateTimeValue;
    }

    /**
     * Also stores the value in the stringValue in order to facilitate filtering on the string value. (see ObjectEntityRepository).
     *
     * @param DateTimeInterface|null $dateTimeValue
     *
     * @return $this
     */
    public function setDateTimeValue(?DateTimeInterface $dateTimeValue): self
    {
        $this->dateTimeValue = $dateTimeValue;
        $this->stringValue = $dateTimeValue !== null ? $dateTimeValue->format('Y-m-d H:i:s') : null;

        return $this;
    }

    /**
     * @return Collection|Value[]
     */
    public function getObjects(): ?Collection
    {
        return $this->objects;
    }

    public function addObject(ObjectEntity $object): self
    {
        // let add this
        if (!$this->objects->contains($object)) {
            $this->objects->add($object);
        }

        // handle subresources
        if (!$object->getSubresourceOf()->contains($this)) {
            $object->addSubresourceOf($this);
        }

        // TODO: see https://conduction.atlassian.net/browse/CON-2030
        // todo: bij inversedby setten, validate ook de opgegeven value voor de inversedBy Attribute. hiermee kunnen we json logic naar boven checken.
        // Handle inversed by
        if ($this->getAttribute()->getInversedBy()) {
            $inversedByValue = $object->getValueByAttribute($this->getAttribute()->getInversedBy());
            if (!$inversedByValue->getObjects()->contains($this->getObjectEntity())) {
                $inversedByValue->addObject($this->getObjectEntity());
            }
        }

        return $this;
    }

    public function removeObject(ObjectEntity $object): self
    {
        // handle subresources
        if ($object->getSubresourceOf()->contains($this)) {
            $object->getSubresourceOf()->removeElement($this);
        }
        // let remove this
        if ($this->objects->contains($object)) {
            $this->objects->removeElement($object);
        }

        // Remove inversed by
        if ($this->getAttribute()->getInversedBy()) {
            $inversedByValue = $object->getValueByAttribute($this->getAttribute()->getInversedBy());
            if ($inversedByValue->getObjects()->contains($this->getObjectEntity())) {
                $inversedByValue->removeObject($this->getObjectEntity());
            }
        }

        return $this;
    }

    /**
     * @return Collection|File[]
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function addFile(File $file): self
    {
        if (!$this->files->contains($file)) {
            $this->files->add($file);
            $file->setValue($this);
        }

        return $this;
    }

    public function removeFile(File $file): self
    {
        if ($this->files->removeElement($file)) {
            // set the owning side to null (unless already changed)
            if ($file->getValue() === $this) {
                $file->setValue(null);
            }
        }

        return $this;
    }

    public function getAttribute(): ?Attribute
    {
        return $this->attribute;
    }

    public function setAttribute(?Attribute $attribute): self
    {
        $this->attribute = $attribute;

        return $this;
    }

    public function getObjectEntity(): ?ObjectEntity
    {
        return $this->objectEntity;
    }

    public function setObjectEntity(?ObjectEntity $objectEntity): self
    {
        $this->objectEntity = $objectEntity;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function setValue($value)
    {
        if ($this->getAttribute()) {
            $doNotSetArrayTypes = ['object', 'datetime', 'date', 'file'];
            if ($this->getAttribute()->getMultiple() && !in_array($this->getAttribute()->getType(), $doNotSetArrayTypes)) {
                return $this->setArrayValue($value);
            }
            switch ($this->getAttribute()->getType()) {
                case 'string':
                    return $this->setStringValue($value);
                case 'integer':
                    if ($value < PHP_INT_MAX) {
                        return $this->setIntegerValue($value);
                    } else {
                        return $this;
                    }
                case 'boolean':
                    if (is_string($value)) {
                        // This is used for defaultValue, this is always a string type instead of a boolean
                        $value = $value === 'true';
                    }

                    return $this->setBooleanValue($value);
                case 'number':
                    return $this->setNumberValue($value);
                case 'date':
                case 'datetime':
                    // if we auto convert null to a date time we would always default to current_timestamp, so lets tackle that
                    if (!$value) {
                        if ($this->getAttribute()->getMultiple()) {
                            return $this->setArrayValue(null);
                        }

                        return $this->setDateTimeValue(null);
                    }
                    // if multiple is true value should be an array
                    if ($this->getAttribute()->getMultiple()) {
                        foreach ($value as &$datetime) {
                            $datetime = new DateTime($datetime);
                        }

                        return $this->setArrayValue($value);
                    }
                    // else $value = DateTime (string)
                    return $this->setDateTimeValue(new DateTime($value));
                case 'file':
                    if ($value === null) {
                        return $this;
                    }
                    $this->files->clear();
                    // if multiple is true value should be an array
                    if ($this->getAttribute()->getMultiple()) {
                        foreach ($value as $file) {
                            $this->addFile($file);
                        }

                        return $this;
                    }
                    // else $value = File::class
                    return $this->addFile($value);
                case 'object':
                    if ($value === null) {
                        return $this;
                    }
                    // todo: We never use setValue function for array of objects, but if we ever do, take a look at $removeObjectsOnPut in validationService and EavService!
//                    if ($this->request->getMethod() == 'PUT' && !$objectEntity->getHasErrors()) {
//                        foreach ($valueObject->getObjects() as $object) {
//                            // If we are not re-adding this object...
//                            if (!$saveSubObjects->contains($object)) {
//                                $this->removeObjectsOnPut[] = [
//                                    'valueObject' => $valueObject,
//                                    'object'      => $object,
//                                ];
//                            }
//                        }
//                        $valueObject->getObjects()->clear();
//                    }
                    $this->objects->clear();
                    // if multiple is true value should be an array
                    if ($this->getAttribute()->getMultiple()) {
                        foreach ($value as $object) {
                            $this->addObject($object);
                        }

                        return $this;
                    }
                    // else $value = ObjectEntity::class
                    return $this->addObject($value);
                case 'array':
                    return $this->setArrayValue($value);
            }
        } else {
            //TODO: correct error handling
            return false;
        }
    }

    public function getValue()
    {
        if ($this->getAttribute()) {
            $doNotGetArrayTypes = ['object', 'datetime', 'date', 'file'];
            if ($this->getAttribute()->getMultiple() && !in_array($this->getAttribute()->getType(), $doNotGetArrayTypes)) {
                return $this->getArrayValue();
            }
            switch ($this->getAttribute()->getType()) {
                case 'string':
                    return $this->getStringValue();
                case 'integer':
                    return $this->getIntegerValue();
                case 'boolean':
                    return $this->getBooleanValue();
                case 'number':
                    return $this->getNumberValue();
                case 'array':
                    return $this->getArrayValue();
                case 'date':
                case 'datetime':
                    $format = $this->getAttribute()->getType() == 'date' ? 'Y-m-d' : 'Y-m-d\TH:i:sP';

                    // We don't want to format null
                    if ((!$this->getDateTimeValue() && !$this->getAttribute()->getMultiple())
                        || (!$this->getArrayValue() && $this->getAttribute()->getMultiple())
                    ) {
                        return null;
                    }
                    // If we do have a value we want to format that
                    if ($this->getAttribute()->getMultiple()) {
                        $datetimeArray = $this->getArrayValue();
                        foreach ($datetimeArray as &$datetime) {
                            $datetime = $datetime->format($format);
                        }

                        return $datetimeArray;
                    }
                    $datetime = $this->getDateTimeValue();

                    return $datetime->format($format);
                case 'file':
                    $files = $this->getFiles();
                    if (!$this->getAttribute()->getMultiple()) {
                        return $files->first();
                    }
                    if (count($files) == 0) {
                        return null;
                    }

                    return $files;
                case 'object':
                    $objects = $this->getObjects();
                    if (!$this->getAttribute()->getMultiple()) {
                        return $objects->first();
                    }
                    if (count($objects) == 0) {
                        return null;
                    }

                    return $objects;
            }
        } else {
            //TODO: correct error handling
            return false;
        }
    }

    public function getDateCreated(): ?DateTimeInterface
    {
        return $this->dateCreated;
    }

    public function setDateCreated(DateTimeInterface $dateCreated): self
    {
        $this->dateCreated = $dateCreated;

        return $this;
    }

    public function getDateModified(): ?DateTimeInterface
    {
        return $this->dateModified;
    }

    public function setDateModified(DateTimeInterface $dateModified): self
    {
        $this->dateModified = $dateModified;

        return $this;
    }
}
