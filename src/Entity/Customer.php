<?php

declare(strict_types = 1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass="App\Repository\CustomerRepository")
 */
class Customer
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
    private string $firstname;

    /**
     * @ORM\Column
     */
    private string $lastname;

    /**
     * @ORM\Column
     */
    private string $email;

    /**
     * @ORM\ManyToOne(targetEntity="Address")
     * @ORM\JoinColumn(referencedColumnName="primary_key")
     */
    private Address $shippingAddress;

    /**
     * @ORM\ManyToOne(targetEntity="Address")
     * @ORM\JoinColumn(referencedColumnName="primary_key")
     */
    private Address $billingAddress;

    /**
     * @ORM\ManyToOne(targetEntity="Magazine")
     * @ORM\JoinColumn(referencedColumnName="primary_key")
     */
    private Magazine $magazine;

    /**
     * @ORM\Column(type="boolean")
     */
    private bool $active = true;

    /**
     * @var Invoice[]|Collection
     *
     * @ORM\OneToMany(targetEntity="Invoice", mappedBy="customer")
     */
    private Collection $invoices;

    public function __construct(
        string $firstname,
        string $lastname,
        string $email,
        Address $shippingAddress,
        Address $billingAddress,
        Magazine $magazine
    ) {
        $this->id = Uuid::uuid4();
        $this->firstname = $firstname;
        $this->lastname = $lastname;
        $this->email = $email;
        $this->shippingAddress = $shippingAddress;
        $this->billingAddress = $billingAddress;
        $this->magazine = $magazine;
        $this->invoices = new ArrayCollection();
    }

    public function __toString(): string
    {
        return sprintf('%s %s [#%s]', $this->firstname, $this->lastname, $this->id);
    }

    public function getFirstname(): string
    {
        return $this->firstname;
    }

    public function getLastname(): string
    {
        return $this->lastname;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getShippingStreet(): string
    {
        return $this->shippingAddress->getStreet();
    }

    public function getShippingHouse(): string
    {
        return $this->shippingAddress->getHouse();
    }

    public function getShippingCity(): string
    {
        return $this->shippingAddress->getCity();
    }

    public function getShippingZip(): string
    {
        return $this->shippingAddress->getZip();
    }

    public function getBillingStreet(): string
    {
        return $this->billingAddress->getStreet();
    }

    public function getBillingHouse(): string
    {
        return $this->billingAddress->getHouse();
    }

    public function getBillingCity(): string
    {
        return $this->billingAddress->getCity();
    }

    public function getBillingZip(): string
    {
        return $this->billingAddress->getZip();
    }

    public function getMagazineName(): string
    {
        return $this->magazine->getName();
    }

    public function getMagazinePrice(): int
    {
        return $this->magazine->getPrice();
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function disable(): void
    {
        $this->active = false;
    }
}
