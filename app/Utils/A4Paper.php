<?php

namespace App\Utils;

use OpenSpout\Writer\XLSX\Options as XlsxOptions;
use OpenSpout\Writer\XLSX\Options\PageOrientation;
use OpenSpout\Writer\XLSX\Options\PageSetup as SpoutPageSetup;
use OpenSpout\Writer\XLSX\Options\PaperSize;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One place for the shop's paper size: every PDF and Excel file the panel or the
 * mobile app produces is laid out for A4.
 */
class A4Paper
{
    /** mpdf `format` option. */
    public const MPDF_FORMAT = 'A4';

    /** Sheets wider than this many columns print in landscape so they stay readable. */
    private const LANDSCAPE_FROM_COLUMNS = 8;

    /**
     * A4, fit all columns on one page width (as many pages tall as needed), with
     * narrow margins. Used for PhpSpreadsheet / Laravel-Excel sheets.
     */
    public static function applyToWorksheet(Worksheet $sheet): void
    {
        $columns = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        $sheet->getPageSetup()
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setOrientation($columns >= self::LANDSCAPE_FROM_COLUMNS ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.4)->setRight(0.4);
        $sheet->getPageSetup()->setHorizontalCentered(true);
    }

    /** For rap2hpoutre/fast-excel: `->configureOptionsUsing(A4Paper::fastExcelOptions())`. */
    public static function fastExcelOptions(): callable
    {
        return function ($options) {
            if ($options instanceof XlsxOptions) {
                $options->setPageSetup(new SpoutPageSetup(PageOrientation::PORTRAIT, PaperSize::A4, 0, 1));
            }
        };
    }
}
