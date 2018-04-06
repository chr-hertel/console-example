<?php

declare(strict_types = 1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity
 */
class Magazine
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
     * @ORM\Column(unique=true)
     */
    private string $name;

    /**
     * @ORM\Column(type="integer")
     */
    private int $price;

    public function __construct(string $name, int $price)
    {
        $this->id = Uuid::uuid4();
        $this->name = $name;
        $this->price = $price;
    }

    public function __toString(): string
    {
        return sprintf('%s [#%s]', $this->name, $this->id);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrice(): int
    {
        return $this->price;
    }
}
