<?php

declare(strict_types = 1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity
 */
class Address
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private int $primaryKey;

    /**
     * @ORM\Column(type="uuid", unique=true)
     */
    private UuidInterface $id;

    /**
     * @ORM\Column
     */
    private string $street;

    /**
     * @ORM\Column
     */
    private string $house;

    /**
     * @ORM\Column
     */
    private string $city;

    /**
     * @ORM\Column
     */
    private string $zip;

    public function __construct(string $street, string $house, string $city, string $zip)
    {
        $this->id = Uuid::uuid4();
        $this->street = $street;
        $this->house = $house;
        $this->city = $city;
        $this->zip = $zip;
    }

    public function __toString(): string
    {
        return sprintf('%s %s, %s %s [#%s]', $this->street, $this->house, $this->zip, $this->house, $this->id);
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function getHouse(): string
    {
        return $this->house;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getZip(): string
    {
        return $this->zip;
    }
}
