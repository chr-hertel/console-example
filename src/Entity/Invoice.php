<?php

declare(strict_types = 1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity
 */
class Invoice
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
     * @ORM\ManyToOne(targetEntity="Customer")
     * @ORM\JoinColumn(referencedColumnName="primary_key")
     */
    private Customer $customer;

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

    /**
     * @ORM\Column
     */
    private string $magazine;

    /**
     * @ORM\Column(type="integer")
     */
    private int $price;

    /**
     * @ORM\Column(type="date_immutable")
     */
    private DateTimeImmutable $period;

    /**
     * @ORM\Column(type="boolean")
     */
    private bool $open = true;

    public static function forCustomer(Customer $customer, DateTimeImmutable $period): self
    {
        $invoice = new self();

        $invoice->id = Uuid::uuid4();
        $invoice->customer = $customer;
        $invoice->firstname = $customer->getFirstname();
        $invoice->lastname = $customer->getLastname();
        $invoice->email = $customer->getEmail();
        $invoice->street = $customer->getBillingStreet();
        $invoice->house = $customer->getBillingHouse();
        $invoice->city = $customer->getBillingCity();
        $invoice->zip = $customer->getBillingZip();
        $invoice->magazine = $customer->getMagazineName();
        $invoice->price = $customer->getMagazinePrice();
        $invoice->period = $period;

        return $invoice;
    }

    public function __toString(): string
    {
        return sprintf('#%s, %s %s', $this->id, $this->firstname, $this->lastname);
    }

    public function getId(): string
    {
        return $this->id->toString();
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

    public function getMagazine(): string
    {
        return $this->magazine;
    }

    public function getPrice(): string
    {
        return number_format($this->price / 100, 2);
    }

    public function getPeriod(): string
    {
        return $this->period->format('Y-m');
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

    public function paid(): void
    {
        $this->open = false;
    }
}
