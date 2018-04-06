<?php

declare(strict_types = 1);

namespace App\Invoice;

use App\Entity\Invoice;
use Mpdf\Mpdf;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Twig\Environment;

class Mailer
{
    private Environment $twig;
    private Filesystem $filesystem;
    private MailerInterface $mailer;
    private string $storage;

    public function __construct(Environment $twig, Filesystem $filesystem, MailerInterface $mailer, string $storage)
    {
        $this->twig = $twig;
        $this->filesystem = $filesystem;
        $this->mailer = $mailer;
        $this->storage = $storage;
    }

    public function sendInvoice(Invoice $invoice): void
    {
        $html = $this->twig->render('invoice.html.twig', ['invoice' => $invoice]);

        $this->filesystem->mkdir([$this->storage, $this->storage.'/'.$invoice->getPeriod()]);
        $file = sprintf('%s/%s/magazine-invoice-%s.pdf', $this->storage, $invoice->getPeriod(), $invoice->getId());

        $mpdf = new Mpdf();
        $mpdf->SetDisplayMode('fullpage');
        $mpdf->WriteHTML($html);
        $mpdf->Output($file);

        $message = (new TemplatedEmail())
            ->to(new Address($invoice->getEmail()))
            ->from('billing@MagazineShop247.com')
            ->subject(sprintf('Your invoice for %s', $invoice->getPeriod()))
            ->htmlTemplate('email.txt.twig')
            ->context(['invoice' => $invoice])
            ->attachFromPath($file);

        $this->mailer->send($message);
    }
}
