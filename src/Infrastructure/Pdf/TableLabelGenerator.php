<?php

namespace App\Infrastructure\Pdf;

use App\Entity\PdfTemplate;
use App\Entity\QrCode;
use App\Entity\Restaurant;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class TableLabelGenerator
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    /**
     * Generate a PDF containing QR code labels for the given QR codes.
     *
     * @param QrCode[] $qrCodes
     * @return string Binary PDF content
     */
    public function generate(PdfTemplate $template, array $qrCodes, Restaurant $restaurant): string
    {
        $html = $this->twig->render($template->getTemplatePath(), [
            'restaurant' => $restaurant,
            'qrCodes' => $qrCodes,
            'template' => $template,
        ]);

        $options = new Options();
        $options->setIsRemoteEnabled(true);
        $options->setDefaultFont('Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper($template->getPageSize(), 'portrait');
        $dompdf->render();

        return $dompdf->output() ?: '';
    }
}
